<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\CourseBundle;
use App\Models\FeePlan;
use App\Models\GradeLevel;
use App\Support\CustomBillingPeriod;
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
            ->with(['gradeLevel:id,name,code', 'academicYear:id,year_code,name', 'courses:id,name,color,status', 'periods'])
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
            ->with('courses:id,name')
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
        $periods = $validated['periods'] ?? [];
        unset($validated['course_ids'], $validated['bundles'], $validated['periods']);

        DB::transaction(function () use ($validated, $courseIds, $bundles, $periods): void {
            $plan = FeePlan::query()->create($validated);
            $plan->courses()->sync($courseIds);
            $this->syncPeriods($plan, $periods);
            $this->syncBundles($plan, $bundles, []);
        });

        return to_route('fee-plans.index');
    }

    public function edit(FeePlan $feePlan): Response
    {
        $feePlan->load(['gradeLevel', 'academicYear', 'courses', 'periods']);

        return Inertia::render('FeePlans/Edit', [
            'plan' => [
                ...$this->planPayload($feePlan),
                'bundles' => $this->matchingBundles($feePlan, CourseBundle::query()->with('courses:id,name')->orderBy('sort_order')->orderBy('id')->get())
                    ->map(fn (CourseBundle $bundle): array => $this->bundleFormRow($bundle))
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
        $periods = $validated['periods'] ?? [];
        unset($validated['course_ids'], $validated['bundles'], $validated['periods']);

        $previousBundleIds = $this->matchingBundles(
            $feePlan->load('courses:id'),
            CourseBundle::query()->with('courses:id')->get()
        )->pluck('id')->all();

        DB::transaction(function () use ($feePlan, $validated, $courseIds, $bundles, $periods, $previousBundleIds): void {
            $feePlan->update($validated);
            $feePlan->courses()->sync($courseIds);
            $this->syncPeriods($feePlan, $periods);
            $this->syncBundles($feePlan, $bundles, $previousBundleIds);
        });

        return to_route('fee-plans.index');
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function syncPeriods(FeePlan $plan, array $rows): void
    {
        $plan->periods()->delete();

        if ($plan->unit !== 'custom_period') {
            return;
        }

        foreach (array_values($rows) as $i => $row) {
            $plan->periods()->create([
                'start_month' => (int) $row['start_month'],
                'end_month' => (int) $row['end_month'],
                'total_price' => (int) $row['total_price'],
                'sort_order' => $i,
            ]);
        }
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

            /** @var array<int, int|null> $coursePrices */
            $coursePrices = [];
            foreach ($courseIds as $courseId) {
                $raw = $row['course_prices'][$courseId] ?? $row['course_prices'][(string) $courseId] ?? null;
                $coursePrices[$courseId] = $raw === null || $raw === ''
                    ? null
                    : (int) $raw;
            }
            $priced = array_values(array_filter($coursePrices, fn (?int $p): bool => $p !== null));

            $attributes = [
                'name' => $name,
                'grade_level_id' => $plan->grade_level_id,
                'academic_year_id' => $plan->academic_year_id,
                'min_courses' => $minCourses,
                // 摘要：有填優惠的科最低價（相容舊欄位／列表）
                'price' => $priced === [] ? 0 : min($priced),
                'is_active' => true,
                // 收費設定建立的專屬組合優先於 seed 通用雙科（sort 10+）
                'sort_order' => 0,
            ];

            $bundle = ! empty($row['id']) ? CourseBundle::query()->find((int) $row['id']) : null;
            if ($bundle !== null) {
                $bundle->update($attributes);
            } else {
                $bundle = CourseBundle::query()->create($attributes);
            }

            $sync = [];
            foreach ($courseIds as $courseId) {
                $sync[$courseId] = ['price' => $coursePrices[$courseId]];
            }
            $bundle->courses()->sync($sync);
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
            'unit' => ['required', 'in:month,session_block,custom_period'],
            'session_block_size' => ['nullable', 'integer', 'min:1', 'max:99'],
            'periods' => ['nullable', 'array'],
            'periods.*.start_month' => ['required', 'integer', 'between:1,12'],
            'periods.*.end_month' => ['required', 'integer', 'between:1,12'],
            'periods.*.total_price' => ['required', 'integer', 'min:1'],
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
            'bundles.*.course_prices' => ['nullable', 'array'],
            'bundles.*.course_prices.*' => ['nullable', 'integer', 'min:0'],
            'bundles.*.min_courses' => ['required', 'integer', 'min:2'],
        ], [
            'bundles.*.course_ids.min' => '優惠至少要勾 2 門課。',
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
            $prices = $bundle['course_prices'] ?? [];
            $hasPriced = false;
            foreach ($bundle['course_ids'] as $courseId) {
                $raw = $prices[$courseId] ?? $prices[(string) $courseId] ?? null;
                if ($raw !== null && $raw !== '') {
                    $hasPriced = true;
                    break;
                }
            }
            if (! $hasPriced) {
                $bundleErrors["bundles.{$i}.course_prices"] = '請至少為一科填寫優惠價（留白的科維持原價）。';
            }
        }
        if ($bundleErrors !== []) {
            throw ValidationException::withMessages($bundleErrors);
        }

        // 自訂期別：至少一期、月份不可重疊
        if (($validated['unit'] ?? '') === 'custom_period') {
            $periods = $validated['periods'] ?? [];
            if ($periods === []) {
                throw ValidationException::withMessages([
                    'periods' => '自訂期別請至少新增一期（起始月、結束月、一期總價）。',
                ]);
            }
            $seenMonths = [];
            $periodErrors = [];
            foreach ($periods as $i => $period) {
                foreach (CustomBillingPeriod::monthNumbers((int) $period['start_month'], (int) $period['end_month']) as $month) {
                    if (isset($seenMonths[$month])) {
                        $periodErrors["periods.{$i}.start_month"] = '期別月份重疊：'.$month.' 月已被其他期別使用。';
                        break;
                    }
                    $seenMonths[$month] = true;
                }
            }
            if ($periodErrors !== []) {
                throw ValidationException::withMessages($periodErrors);
            }
            $validated['periods'] = array_values($periods);
        } else {
            $validated['periods'] = [];
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
     * @return array{id:int, name:string, course_ids:list<int>, course_prices:array<int, int|null>, min_courses:int, price:int, summary:string}
     */
    private function bundleFormRow(CourseBundle $bundle): array
    {
        $courseIds = $bundle->courses->pluck('id')->map(fn ($id): int => (int) $id)->values()->all();
        $coursePrices = [];
        $parts = [];
        foreach ($bundle->courses as $course) {
            $id = (int) $course->id;
            $raw = $course->pivot->price ?? null;
            $price = $raw === null ? null : (int) $raw;
            $coursePrices[$id] = $price;
            $parts[] = $price === null
                ? $course->name.'（原價）'
                : $course->name.' '.number_format($price).'／月';
        }

        return [
            'id' => $bundle->id,
            'name' => $bundle->name,
            'course_ids' => $courseIds,
            'course_prices' => $coursePrices,
            'min_courses' => $bundle->min_courses,
            'price' => (int) $bundle->price,
            'summary' => implode('、', $parts),
        ];
    }

    /**
     * @param  Collection<int, CourseBundle>  $bundles
     * @return list<array{id:int, name:string, price:int, min_courses:int, summary:string}>
     */
    private function bundlesForPlan(FeePlan $plan, Collection $bundles): array
    {
        return $this->matchingBundles($plan, $bundles)
            ->map(fn (CourseBundle $bundle): array => [
                'id' => $bundle->id,
                'name' => $bundle->name,
                'price' => $bundle->price,
                'min_courses' => $bundle->min_courses,
                'summary' => $this->bundleFormRow($bundle)['summary'],
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
                $plan->unit === 'custom_period' => '自訂期別',
                $plan->unit === 'month' => '按月',
                (int) ($plan->session_block_size ?? 3) === 3 => '按季',
                default => '每 '.(int) ($plan->session_block_size ?? 4).' 個月',
            },
            'periods' => $plan->relationLoaded('periods')
                ? $plan->periods->map(fn ($period): array => [
                    'start_month' => (int) $period->start_month,
                    'end_month' => (int) $period->end_month,
                    'total_price' => (int) $period->total_price,
                ])->values()->all()
                : [],
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
