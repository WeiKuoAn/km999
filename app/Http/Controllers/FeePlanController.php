<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\CourseBundle;
use App\Models\FeePlan;
use App\Models\GradeLevel;
use App\Support\PricingGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class FeePlanController extends Controller
{
    public function index(Request $request): Response
    {
        $gradeFilter = $request->string('grade')->toString();

        $query = FeePlan::query()
            ->with(['gradeLevel:id,name,code', 'academicYear:id,year_code,name', 'courses:id,name,color,status'])
            ->orderBy('sort_order')
            ->orderBy('id');

        if ($gradeFilter !== '' && $gradeFilter !== '全部') {
            if ($gradeFilter === '全年級') {
                $query->whereNull('grade_level_id');
            } else {
                $query->whereHas('gradeLevel', fn ($q) => $q->where('name', $gradeFilter));
            }
        }

        $bundles = CourseBundle::query()
            ->with('courses:id')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return Inertia::render('FeePlans/Index', [
            'plans' => $query
                ->paginate(50)
                ->through(fn (FeePlan $plan): array => [
                    ...$this->planPayload($plan),
                    'bundles' => $this->bundlesForPlan($plan, $bundles),
                ])
                ->withQueryString(),
            'filters' => [
                'grade' => $gradeFilter !== '' ? $gradeFilter : '全部',
            ],
            'gradeOptions' => $this->gradeFilterOptions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('FeePlans/Create', [
            'academicYears' => $this->academicYearOptions(),
            'gradeLevels' => $this->gradeLevelOptions(),
            'courses' => $this->courseOptions(),
            'defaultAcademicYearId' => AcademicYear::query()->where('is_current', true)->value('id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatePlan($request);
        $courseIds = $validated['course_ids'];
        $bundles = $validated['bundles'] ?? [];
        unset($validated['course_ids'], $validated['bundles']);

        DB::transaction(function () use ($validated, $courseIds, $bundles): void {
            $plan = FeePlan::query()->create($validated);
            $plan->courses()->sync($courseIds);
            $this->syncBundles($plan, $bundles, []);
        });

        return to_route('fee-plans.index');
    }

    public function edit(FeePlan $feePlan): Response
    {
        $feePlan->load(['gradeLevel', 'academicYear', 'courses']);

        return Inertia::render('FeePlans/Edit', [
            'plan' => [
                ...$this->planPayload($feePlan),
                'bundles' => $this->matchingBundles($feePlan, CourseBundle::query()->with('courses:id')->orderBy('sort_order')->orderBy('id')->get())
                    ->map(fn (CourseBundle $bundle): array => [
                        'id' => $bundle->id,
                        'name' => $bundle->name,
                        'course_ids' => $bundle->courses->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(),
                        'min_courses' => $bundle->min_courses,
                        'price' => $bundle->price,
                    ])
                    ->values()
                    ->all(),
            ],
            'academicYears' => $this->academicYearOptions(),
            'gradeLevels' => $this->gradeLevelOptions(),
            'courses' => $this->courseOptions(),
        ]);
    }

    public function update(Request $request, FeePlan $feePlan): RedirectResponse
    {
        $validated = $this->validatePlan($request, $feePlan);
        $courseIds = $validated['course_ids'];
        $bundles = $validated['bundles'] ?? [];
        unset($validated['course_ids'], $validated['bundles']);

        $previousBundleIds = $this->matchingBundles(
            $feePlan->load('courses:id'),
            CourseBundle::query()->with('courses:id')->get()
        )->pluck('id')->all();

        DB::transaction(function () use ($feePlan, $validated, $courseIds, $bundles, $previousBundleIds): void {
            $feePlan->update($validated);
            $feePlan->courses()->sync($courseIds);
            $this->syncBundles($feePlan, $bundles, $previousBundleIds);
        });

        return to_route('fee-plans.index');
    }

    /**
     * 表單上的雙科／多科優惠寫入組合優惠（年級、學年跟著收費設定）；表單拿掉的就刪除。
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  list<int>  $previousIds
     */
    private function syncBundles(FeePlan $plan, array $rows, array $previousIds): void
    {
        $keepIds = [];
        $courseNames = Course::query()->pluck('name', 'id');

        foreach ($rows as $row) {
            $courseIds = array_values(array_map('intval', $row['course_ids']));
            $minCourses = (int) $row['min_courses'];
            $name = trim((string) ($row['name'] ?? ''));
            if ($name === '') {
                $name = $this->defaultBundleName($courseIds, $minCourses, $courseNames->all());
            }

            $attributes = [
                'name' => $name,
                'grade_level_id' => $plan->grade_level_id,
                'academic_year_id' => $plan->academic_year_id,
                'min_courses' => $minCourses,
                'price' => (int) $row['price'],
                'is_active' => true,
                'sort_order' => $plan->sort_order,
            ];

            $bundle = ! empty($row['id']) ? CourseBundle::query()->find((int) $row['id']) : null;
            if ($bundle !== null) {
                $bundle->update($attributes);
            } else {
                $bundle = CourseBundle::query()->create($attributes);
            }
            $bundle->courses()->sync($courseIds);
            $keepIds[] = $bundle->id;
        }

        $removeIds = array_diff($previousIds, $keepIds);
        if ($removeIds !== []) {
            CourseBundle::query()->whereKey($removeIds)->delete();
        }
    }

    /**
     * 例：英文課＋數學課、至少 2 科 → 「英數 雙科」。
     *
     * @param  list<int>  $courseIds
     * @param  array<int, string>  $courseNames
     */
    private function defaultBundleName(array $courseIds, int $minCourses, array $courseNames): string
    {
        $short = implode('', array_map(
            fn (int $id): string => mb_substr((string) ($courseNames[$id] ?? ''), 0, 1),
            $courseIds
        ));
        $count = match ($minCourses) {
            2 => '雙科',
            3 => '三科',
            default => $minCourses.'科',
        };

        return trim($short.' '.$count);
    }

    public function destroy(FeePlan $feePlan): RedirectResponse
    {
        $feePlan->delete();

        return to_route('fee-plans.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePlan(Request $request, ?FeePlan $currentPlan = null): array
    {
        $validated = $request->validate([
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'grade_level_id' => ['nullable', 'integer', 'exists:grade_levels,id'],
            'course_ids' => ['required', 'array', 'min:1'],
            'course_ids.*' => ['required', 'integer', 'distinct', 'exists:courses,id'],
            'group_name' => ['required', 'string', 'max:64'],
            'pricing_group' => ['required', 'string', Rule::in(PricingGroup::keys())],
            'unit' => ['required', 'in:month,session_block'],
            'session_block_size' => ['nullable', 'integer', 'min:1', 'max:99'],
            'list_price' => ['required', 'integer', 'min:0'],
            'quarter_price' => ['nullable', 'integer', 'min:0'],
            'quarter_single_price' => ['nullable', 'integer', 'min:0'],
            'quarter_double_price' => ['nullable', 'integer', 'min:0'],
            'material_fee' => ['required', 'integer', 'min:0'],
            'material_unit' => ['required', 'in:term,subject,class_day'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['boolean'],
            'bundles' => ['nullable', 'array'],
            'bundles.*.id' => ['nullable', 'integer', 'exists:course_bundles,id'],
            'bundles.*.name' => ['nullable', 'string', 'max:64'],
            'bundles.*.course_ids' => ['required', 'array', 'min:2'],
            'bundles.*.course_ids.*' => ['required', 'integer', 'exists:courses,id'],
            'bundles.*.min_courses' => ['required', 'integer', 'min:2'],
            'bundles.*.price' => ['required', 'integer', 'min:0'],
        ], [
            'bundles.*.course_ids.min' => '優惠至少要勾 2 門課。',
            'bundles.*.price.required' => '請填優惠價。',
        ]);

        $bundleErrors = [];
        foreach ($validated['bundles'] ?? [] as $i => $bundle) {
            $validated['bundles'][$i]['course_ids'] = $bundle['course_ids'] = array_values(array_unique(array_map('intval', $bundle['course_ids'])));
            if ((int) $bundle['min_courses'] > count($bundle['course_ids'])) {
                $bundleErrors["bundles.{$i}.min_courses"] = '「至少報幾科」不能超過勾選的課數。';
            }
            if (array_intersect(array_map('intval', $bundle['course_ids']), array_map('intval', $validated['course_ids'])) === []) {
                $bundleErrors["bundles.{$i}.course_ids"] = '優惠要包含這個收費設定的課程。';
            }
        }
        if ($bundleErrors !== []) {
            throw ValidationException::withMessages($bundleErrors);
        }

        if (($validated['unit'] ?? '') === 'session_block') {
            $validated['session_block_size'] = $validated['session_block_size'] ?? 4;
        } else {
            $validated['session_block_size'] = null;
        }

        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['grade_level_id'] = isset($validated['grade_level_id'])
            ? (int) $validated['grade_level_id']
            : null;
        $validated['academic_year_id'] = isset($validated['academic_year_id'])
            ? (int) $validated['academic_year_id']
            : null;

        $gradeId = $validated['grade_level_id'];

        $conflictingPlan = FeePlan::query()
            ->when(
                $gradeId === null,
                // 全年級：同學年下任何年級已套用同課目即衝突
                fn ($query) => $query,
                // 指定年級：與同年級或全年級衝突
                fn ($query) => $query->where(function ($builder) use ($gradeId): void {
                    $builder->whereNull('grade_level_id')
                        ->orWhere('grade_level_id', $gradeId);
                })
            )
            ->when(
                $validated['academic_year_id'] === null,
                fn ($query) => $query->whereNull('academic_year_id'),
                fn ($query) => $query->where('academic_year_id', $validated['academic_year_id'])
            )
            ->when($currentPlan !== null, fn ($query) => $query->whereKeyNot($currentPlan->getKey()))
            ->whereHas('courses', fn ($query) => $query->whereIn('courses.id', $validated['course_ids']))
            ->first();

        if ($conflictingPlan !== null) {
            $conflictGrade = $conflictingPlan->gradeLevel?->name ?? '全年級';
            throw ValidationException::withMessages([
                'course_ids' => "所選課目已套用於同學年、「{$conflictGrade}」的「{$conflictingPlan->group_name}」，請勿重複設定。",
            ]);
        }

        return $validated;
    }

    /**
     * 與此收費設定同年級、同學年且含有其課程的組合優惠。
     *
     * @param  Collection<int, CourseBundle>  $bundles
     * @return Collection<int, CourseBundle>
     */
    private function matchingBundles(FeePlan $plan, Collection $bundles): Collection
    {
        $planCourseIds = $plan->courses->pluck('id')->map(fn ($id): int => (int) $id)->all();

        return $bundles
            ->filter(fn (CourseBundle $bundle): bool => $bundle->grade_level_id == $plan->grade_level_id
                && $bundle->academic_year_id == $plan->academic_year_id
                && $bundle->courses->pluck('id')->intersect($planCourseIds)->isNotEmpty())
            ->values();
    }

    /**
     * @param  Collection<int, CourseBundle>  $bundles
     * @return list<array{id:int, name:string, price:int, min_courses:int}>
     */
    private function bundlesForPlan(FeePlan $plan, Collection $bundles): array
    {
        return $this->matchingBundles($plan, $bundles)
            ->map(fn (CourseBundle $bundle): array => [
                'id' => $bundle->id,
                'name' => $bundle->name,
                'price' => $bundle->price,
                'min_courses' => $bundle->min_courses,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function planPayload(FeePlan $plan): array
    {
        return [
            'id' => $plan->id,
            'academic_year_id' => $plan->academic_year_id,
            'academic_year_name' => $plan->academicYear?->name,
            'grade_level_id' => $plan->grade_level_id,
            'grade_name' => $plan->gradeLevel?->name ?? '全年級',
            'group_name' => $plan->group_name,
            'pricing_group' => $plan->pricing_group,
            'pricing_group_label' => PricingGroup::label($plan->pricing_group),
            'course_ids' => $plan->courses->pluck('id')->map(fn ($id): int => (int) $id)->values()->all(),
            'course_names' => $plan->courses->pluck('name')->values()->all(),
            'unit' => $plan->unit,
            'session_block_size' => $plan->session_block_size,
            'list_price' => $plan->list_price,
            'quarter_price' => $plan->quarter_price,
            'quarter_single_price' => $plan->quarter_single_price,
            'quarter_double_price' => $plan->quarter_double_price,
            'material_fee' => $plan->material_fee,
            'material_unit' => $plan->material_unit,
            'sort_order' => $plan->sort_order,
            'is_active' => $plan->is_active,
            'list_label' => $plan->listPriceLabel(),
            'quarter_label' => $plan->quarterLabel(),
            'material_label' => $plan->materialLabel(),
            'unit_label' => match (true) {
                $plan->unit === 'month' => '按月',
                (int) ($plan->session_block_size ?? 3) === 3 => '按季',
                default => '每 '.(int) ($plan->session_block_size ?? 4).' 個月',
            },
        ];
    }

    /**
     * @return list<string>
     */
    private function gradeFilterOptions(): array
    {
        return array_merge(
            ['全部', '全年級'],
            GradeLevel::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('code')
                ->pluck('name')
                ->all()
        );
    }

    /**
     * @return list<array{id:int,year_code:string,name:string}>
     */
    private function academicYearOptions(): array
    {
        return AcademicYear::query()
            ->orderByDesc('year_code')
            ->get(['id', 'year_code', 'name'])
            ->map(fn (AcademicYear $y): array => [
                'id' => $y->id,
                'year_code' => $y->year_code,
                'name' => $y->displayName(),
            ])
            ->all();
    }

    /**
     * @return list<array{id:int,name:string,code:int}>
     */
    private function gradeLevelOptions(): array
    {
        return GradeLevel::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get(['id', 'name', 'code'])
            ->map(fn (GradeLevel $g): array => [
                'id' => $g->id,
                'name' => $g->name,
                'code' => $g->code,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function courseOptions(): array
    {
        return Course::query()
            ->with(['courseCategory:id,name,sort_order', 'coursePrices:id,course_id,level'])
            ->get(['id', 'course_category_id', 'name', 'color', 'status', 'pricing_group'])
            ->sortBy([
                fn (Course $course): int => (int) ($course->courseCategory?->sort_order ?? 999),
                fn (Course $course): string => $course->courseCategory?->name ?? '',
                fn (Course $course): string => $course->name,
            ])
            ->map(fn (Course $course): array => [
                'id' => $course->id,
                'name' => $course->name,
                'color' => $course->color,
                'status' => $course->status,
                'pricing_group' => $course->pricing_group,
                'pricing_group_label' => PricingGroup::label($course->pricing_group),
                'category_id' => $course->course_category_id,
                'category_name' => $course->courseCategory?->name ?? '未分類',
                'levels' => $course->coursePrices->pluck('level')->filter()->unique()->values()->all(),
            ])
            ->values()
            ->all();
    }
}
