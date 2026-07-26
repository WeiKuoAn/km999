<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\FeePlan;
use App\Models\GradeLevel;
use App\Support\PricingGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            $query->whereHas('gradeLevel', fn ($q) => $q->where('name', $gradeFilter));
        }

        return Inertia::render('FeePlans/Index', [
            'plans' => $query
                ->paginate(50)
                ->through(fn (FeePlan $plan): array => $this->planPayload($plan))
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
            'pricingGroups' => PricingGroup::options(),
            'courses' => $this->courseOptions(),
            'defaultAcademicYearId' => AcademicYear::query()->where('is_current', true)->value('id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validatePlan($request);
        $courseIds = $validated['course_ids'];
        unset($validated['course_ids']);

        DB::transaction(function () use ($validated, $courseIds): void {
            $plan = FeePlan::query()->create($validated);
            $plan->courses()->sync($courseIds);
        });

        return to_route('fee-plans.index');
    }

    public function edit(FeePlan $feePlan): Response
    {
        return Inertia::render('FeePlans/Edit', [
            'plan' => $this->planPayload($feePlan->load(['gradeLevel', 'academicYear', 'courses'])),
            'academicYears' => $this->academicYearOptions(),
            'gradeLevels' => $this->gradeLevelOptions(),
            'pricingGroups' => PricingGroup::options(),
            'courses' => $this->courseOptions(),
        ]);
    }

    public function update(Request $request, FeePlan $feePlan): RedirectResponse
    {
        $validated = $this->validatePlan($request, $feePlan);
        $courseIds = $validated['course_ids'];
        unset($validated['course_ids']);

        DB::transaction(function () use ($feePlan, $validated, $courseIds): void {
            $feePlan->update($validated);
            $feePlan->courses()->sync($courseIds);
        });

        return to_route('fee-plans.index');
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
            'grade_level_id' => ['required', 'integer', 'exists:grade_levels,id'],
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
        ]);

        if (($validated['unit'] ?? '') === 'session_block') {
            $validated['session_block_size'] = $validated['session_block_size'] ?? 4;
        } else {
            $validated['session_block_size'] = null;
        }

        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['is_active'] = $request->boolean('is_active', true);

        $conflictingPlan = FeePlan::query()
            ->where('grade_level_id', $validated['grade_level_id'])
            ->when(
                $validated['academic_year_id'] === null,
                fn ($query) => $query->whereNull('academic_year_id'),
                fn ($query) => $query->where('academic_year_id', $validated['academic_year_id'])
            )
            ->when($currentPlan !== null, fn ($query) => $query->whereKeyNot($currentPlan->getKey()))
            ->whereHas('courses', fn ($query) => $query->whereIn('courses.id', $validated['course_ids']))
            ->first();

        if ($conflictingPlan !== null) {
            throw ValidationException::withMessages([
                'course_ids' => "所選課目已套用於同學年、同年級的「{$conflictingPlan->group_name}」，請勿重複設定。",
            ]);
        }

        return $validated;
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
            'grade_name' => $plan->gradeLevel?->name,
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
            'unit_label' => $plan->unit === 'session_block' ? '堂塊' : '月',
        ];
    }

    /**
     * @return list<string>
     */
    private function gradeFilterOptions(): array
    {
        return array_merge(
            ['全部'],
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
