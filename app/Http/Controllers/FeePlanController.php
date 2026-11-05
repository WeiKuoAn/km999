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
            ->with(['gradeLevel:id,name,code', 'academicYear:id,year_code,name', 'courses:id,name,color,status', 'periods.coursePrices', 'periods.course:id,name'])
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
                    'period_lines' => $plan->periodSummaryLines(),
                    'tier_lines' => $plan->tierSummaryLines(),
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
        $annualPrices = $validated['annual_prices'] ?? [];
        unset($validated['course_ids'], $validated['bundles'], $validated['periods'], $validated['annual_prices']);

        DB::transaction(function () use ($validated, $courseIds, $bundles, $periods, $annualPrices): void {
            $plan = FeePlan::query()->create($validated);
            $plan->courses()->sync($courseIds);
            $this->syncPeriods($plan, $periods, $courseIds);
            $this->syncAnnualPrices($plan, $annualPrices, $courseIds);
            $this->syncBundles($plan, $bundles, []);
        });

        return to_route('fee-plans.index');
    }

    public function edit(FeePlan $feePlan): Response
    {
        $feePlan->load(['gradeLevel', 'academicYear', 'courses', 'periods.coursePrices', 'annualPrices']);

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
        $annualPrices = $validated['annual_prices'] ?? [];
        unset($validated['course_ids'], $validated['bundles'], $validated['periods'], $validated['annual_prices']);

        $previousBundleIds = $this->matchingBundles(
            $feePlan->load('courses:id'),
            CourseBundle::query()->with('courses:id')->get()
        )->pluck('id')->all();

        DB::transaction(function () use ($feePlan, $validated, $courseIds, $bundles, $periods, $annualPrices, $previousBundleIds): void {
            $feePlan->update($validated);
            $feePlan->courses()->sync($courseIds);
            $this->syncPeriods($feePlan, $periods, $courseIds);
            $this->syncAnnualPrices($feePlan, $annualPrices, $courseIds);
            $this->syncBundles($feePlan, $bundles, $previousBundleIds);
        });

        return to_route('fee-plans.index');
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<int>  $courseIds
     */
    private function syncPeriods(FeePlan $plan, array $rows, array $courseIds): void
    {
        $plan->periods()->delete();

        if ($plan->unit !== 'custom_period') {
            return;
        }

        if ($plan->per_course_periods) {
            $sort = 0;
            foreach (array_values($rows) as $row) {
                $courseId = (int) ($row['course_id'] ?? 0);
                if (! in_array($courseId, $courseIds, true)) {
                    continue;
                }
                $total = (int) ($row['total_price'] ?? 0);
                if ($total < 1) {
                    $raw = is_array($row['course_prices'] ?? null)
                        ? ($row['course_prices'][$courseId] ?? $row['course_prices'][(string) $courseId] ?? null)
                        : null;
                    $total = is_array($raw) ? (int) ($raw['total_price'] ?? 0) : 0;
                }
                if ($total < 1) {
                    continue;
                }
                $double = $row['double_price'] ?? null;
                $triple = $row['triple_price'] ?? null;
                $nestedPrices = is_array($row['course_prices'] ?? null)
                    ? ($row['course_prices'][$courseId] ?? $row['course_prices'][(string) $courseId] ?? null)
                    : null;
                if (is_array($nestedPrices)) {
                    if ($double === null || $double === '') {
                        $double = $nestedPrices['double_price'] ?? null;
                    }
                    if ($triple === null || $triple === '') {
                        $triple = $nestedPrices['triple_price'] ?? null;
                    }
                }
                $doublePrice = $double === null || $double === '' ? null : (int) $double;
                $triplePrice = $triple === null || $triple === '' ? null : (int) $triple;

                $period = $plan->periods()->create([
                    'course_id' => $courseId,
                    'start_month' => (int) $row['start_month'],
                    'end_month' => (int) $row['end_month'],
                    'total_price' => $total,
                    'double_price' => $doublePrice,
                    'triple_price' => $triplePrice,
                    'sort_order' => $sort++,
                ]);
                $period->coursePrices()->create([
                    'course_id' => $courseId,
                    'total_price' => $total,
                    'double_price' => $doublePrice,
                    'triple_price' => $triplePrice,
                ]);
            }

            return;
        }

        foreach (array_values($rows) as $i => $row) {
            $coursePriceRows = is_array($row['course_prices'] ?? null) ? $row['course_prices'] : [];
            $singles = [];
            $normalized = [];

            foreach ($courseIds as $courseId) {
                $raw = $coursePriceRows[$courseId] ?? $coursePriceRows[(string) $courseId] ?? null;
                if (! is_array($raw)) {
                    continue;
                }
                $total = (int) ($raw['total_price'] ?? 0);
                if ($total < 1) {
                    continue;
                }
                $double = $raw['double_price'] ?? null;
                $triple = $raw['triple_price'] ?? null;
                $singles[] = $total;
                $normalized[$courseId] = [
                    'total_price' => $total,
                    'double_price' => $double === null || $double === '' ? null : (int) $double,
                    'triple_price' => $triple === null || $triple === '' ? null : (int) $triple,
                ];
            }

            $period = $plan->periods()->create([
                'course_id' => null,
                'start_month' => (int) $row['start_month'],
                'end_month' => (int) $row['end_month'],
                // 列表摘要用：各科單科最低價（相容舊欄位）
                'total_price' => $singles === [] ? 0 : min($singles),
                'double_price' => null,
                'triple_price' => null,
                'sort_order' => $i,
            ]);

            foreach ($normalized as $courseId => $prices) {
                $period->coursePrices()->create([
                    'course_id' => $courseId,
                    'total_price' => $prices['total_price'],
                    'double_price' => $prices['double_price'],
                    'triple_price' => $prices['triple_price'],
                ]);
            }
        }
    }

    /**
     * @param  array<int|string, mixed>  $prices  course_id => amount
     * @param  list<int>  $courseIds
     */
    private function syncAnnualPrices(FeePlan $plan, array $prices, array $courseIds): void
    {
        $plan->annualPrices()->delete();

        if (! $plan->allow_annual) {
            return;
        }

        foreach ($courseIds as $courseId) {
            $raw = $prices[$courseId] ?? $prices[(string) $courseId] ?? null;
            $amount = is_numeric($raw) ? (int) $raw : 0;
            if ($amount < 1) {
                continue;
            }
            $plan->annualPrices()->create([
                'course_id' => $courseId,
                'amount' => $amount,
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
            3 => '多科',
            default => $minCourses.'科',
        };

        return trim($short.' '.$count);
    }

    public function destroy(FeePlan $feePlan): RedirectResponse
    {
        $feePlan->delete();

        return to_route('fee-plans.index');
    }

    /** 複製整筆收費設定（含期別、各科月價），導向編輯頁。 */
    public function duplicate(FeePlan $feePlan): RedirectResponse
    {
        $feePlan->load(['courses:id', 'periods.coursePrices', 'annualPrices']);

        $copy = DB::transaction(function () use ($feePlan): FeePlan {
            $clone = $feePlan->replicate([
                'created_at',
                'updated_at',
            ]);
            $clone->group_name = $feePlan->group_name.'（複製）';
            $clone->is_active = false;
            $clone->save();
            $clone->courses()->sync($feePlan->courses->pluck('id')->all());

            foreach ($feePlan->periods as $period) {
                $periodClone = $period->replicate(['created_at', 'updated_at']);
                $periodClone->fee_plan_id = $clone->id;
                $periodClone->save();

                foreach ($period->coursePrices as $row) {
                    $priceClone = $row->replicate(['created_at', 'updated_at']);
                    $priceClone->fee_plan_period_id = $periodClone->id;
                    $priceClone->save();
                }
            }

            foreach ($feePlan->annualPrices as $row) {
                $priceClone = $row->replicate(['created_at', 'updated_at']);
                $priceClone->fee_plan_id = $clone->id;
                $priceClone->save();
            }

            return $clone;
        });

        return to_route('fee-plans.edit', $copy)
            ->with('success', '已複製收費設定，請確認後啟用。');
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePlan(Request $request, ?FeePlan $currentPlan = null): array
    {
        $validated = $request->validate([
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            // null＝全年級（統一價／同期別可用）；價格或期別不同請分年級建
            'grade_level_id' => ['nullable', 'integer', 'exists:grade_levels,id'],
            'course_ids' => ['required', 'array', 'min:1'],
            'course_ids.*' => ['required', 'integer', 'distinct', 'exists:courses,id'],
            'group_name' => ['required', 'string', 'max:64'],
            'pricing_group' => ['required', 'string', Rule::in(PricingGroup::keys())],
            'unit' => ['required', 'in:custom_period'],
            'plan_kind' => ['required', 'in:period,annual_package'],
            'per_course_periods' => ['boolean'],
            'allow_annual' => ['boolean'],
            'annual_start_month' => ['nullable', 'integer', 'between:1,12'],
            'annual_end_month' => ['nullable', 'integer', 'between:1,12'],
            'package_total' => ['nullable', 'integer', 'min:1'],
            'annual_prices' => ['nullable', 'array'],
            'annual_prices.*' => ['nullable', 'integer', 'min:1'],
            'session_block_size' => ['nullable', 'integer', 'min:1', 'max:99'],
            'periods' => ['nullable', 'array'],
            'periods.*.start_month' => ['required', 'integer', 'between:1,12'],
            'periods.*.end_month' => ['required', 'integer', 'between:1,12'],
            'periods.*.course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'periods.*.total_price' => ['nullable', 'integer', 'min:1'],
            'periods.*.double_price' => ['nullable', 'integer', 'min:1'],
            'periods.*.triple_price' => ['nullable', 'integer', 'min:1'],
            'periods.*.course_prices' => ['nullable', 'array'],
            'periods.*.course_prices.*.total_price' => ['nullable', 'integer', 'min:1'],
            'periods.*.course_prices.*.double_price' => ['nullable', 'integer', 'min:1'],
            'periods.*.course_prices.*.triple_price' => ['nullable', 'integer', 'min:1'],
            'list_price' => ['nullable', 'integer', 'min:0'],
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

        $validated['plan_kind'] = (string) ($validated['plan_kind'] ?? 'period');
        $validated['per_course_periods'] = (bool) ($validated['per_course_periods'] ?? false);
        $validated['allow_annual'] = (bool) ($validated['allow_annual'] ?? false);
        $isAnnualPackage = $validated['plan_kind'] === 'annual_package';

        if ($isAnnualPackage) {
            // 年繳方案：總額＋起迄月；可與期別設定共用科目
            $packageErrors = [];
            $packageTotal = (int) ($validated['package_total'] ?? 0);
            if ($packageTotal < 1) {
                $packageErrors['package_total'] = '請填寫年繳方案總額。';
            }
            $startMonth = (int) ($validated['annual_start_month'] ?? 0);
            $endMonth = (int) ($validated['annual_end_month'] ?? 0);
            if ($startMonth < 1 || $startMonth > 12) {
                $packageErrors['annual_start_month'] = '請選擇方案起始月。';
            }
            if ($endMonth < 1 || $endMonth > 12) {
                $packageErrors['annual_end_month'] = '請選擇方案結束月。';
            }
            if ($packageErrors !== []) {
                throw ValidationException::withMessages($packageErrors);
            }
            $validated['package_total'] = $packageTotal;
            $validated['allow_annual'] = true;
            $validated['annual_start_month'] = $startMonth;
            $validated['annual_end_month'] = $endMonth;
            $validated['annual_prices'] = [];
            $validated['periods'] = [];
            $validated['per_course_periods'] = false;
            $validated['material_fee'] = (int) ($validated['material_fee'] ?? 0);
        } else {
            $validated['package_total'] = null;

            if ($validated['allow_annual']) {
                $annualErrors = [];
                $startMonth = (int) ($validated['annual_start_month'] ?? 0);
                $endMonth = (int) ($validated['annual_end_month'] ?? 0);
                if ($startMonth < 1 || $startMonth > 12) {
                    $annualErrors['annual_start_month'] = '請選擇年繳起始月。';
                }
                if ($endMonth < 1 || $endMonth > 12) {
                    $annualErrors['annual_end_month'] = '請選擇年繳結束月。';
                }
                $rawAnnual = is_array($validated['annual_prices'] ?? null) ? $validated['annual_prices'] : [];
                $normalizedAnnual = [];
                foreach (array_map('intval', $validated['course_ids']) as $courseId) {
                    $raw = $rawAnnual[$courseId] ?? $rawAnnual[(string) $courseId] ?? null;
                    if ($raw === null || $raw === '' || (int) $raw < 1) {
                        $annualErrors["annual_prices.{$courseId}"] = '開放年繳時，請為每一科填寫年繳金額。';

                        continue;
                    }
                    $normalizedAnnual[$courseId] = (int) $raw;
                }
                if ($annualErrors !== []) {
                    throw ValidationException::withMessages($annualErrors);
                }
                $validated['annual_start_month'] = $startMonth;
                $validated['annual_end_month'] = $endMonth;
                $validated['annual_prices'] = $normalizedAnnual;
            } else {
                $validated['annual_start_month'] = null;
                $validated['annual_end_month'] = null;
                $validated['annual_prices'] = [];
            }

            // 期別收費：至少一期；月份允許重疊
            $periods = $validated['periods'] ?? [];
            if ($periods === []) {
                throw ValidationException::withMessages([
                    'periods' => '自訂期別請至少新增一期（起始月、結束月、各科月價）。',
                ]);
            }
            $periodErrors = [];
            $planCourseIds = array_map('intval', $validated['course_ids']);
            $planCourseSet = array_fill_keys($planCourseIds, true);

            if ($validated['per_course_periods']) {
                $hasPeriodByCourse = [];
                foreach ($periods as $i => $period) {
                    $courseId = (int) ($period['course_id'] ?? 0);
                    if (! isset($planCourseSet[$courseId])) {
                        $periodErrors["periods.{$i}.course_id"] = '期別科目必須是此收費設定勾選的課。';

                        continue;
                    }
                    $hasPeriodByCourse[$courseId] = true;
                    $total = $period['total_price'] ?? null;
                    if ($total === null || $total === '') {
                        $raw = is_array($period['course_prices'] ?? null)
                            ? ($period['course_prices'][$courseId] ?? $period['course_prices'][(string) $courseId] ?? null)
                            : null;
                        $total = is_array($raw) ? ($raw['total_price'] ?? null) : null;
                    }
                    if ($total === null || $total === '' || (int) $total < 1) {
                        $periodErrors["periods.{$i}.total_price"] = '請填寫該科單科每月價。';
                    }
                }
                foreach ($planCourseIds as $courseId) {
                    if (! isset($hasPeriodByCourse[$courseId])) {
                        $periodErrors['periods'] = '每科不同期時，請為每一科至少新增一期。';
                        break;
                    }
                }
            } else {
                foreach ($periods as $i => $period) {
                    $prices = is_array($period['course_prices'] ?? null) ? $period['course_prices'] : [];
                    foreach ($planCourseIds as $courseId) {
                        $raw = $prices[$courseId] ?? $prices[(string) $courseId] ?? null;
                        $total = is_array($raw) ? ($raw['total_price'] ?? null) : null;
                        if ($total === null || $total === '' || (int) $total < 1) {
                            $periodErrors["periods.{$i}.course_prices.{$courseId}.total_price"] = '請為每一科填寫單科每月價。';
                        }
                    }
                }
            }
            if ($periodErrors !== []) {
                throw ValidationException::withMessages($periodErrors);
            }
            $validated['periods'] = array_values($periods);
        }

        $validated['session_block_size'] = null;

        // 不再使用參考原價；欄位保留相容，存檔一律寫 0
        $validated['list_price'] = 0;
        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['grade_level_id'] = isset($validated['grade_level_id'])
            ? (int) $validated['grade_level_id']
            : null;
        $validated['academic_year_id'] = isset($validated['academic_year_id'])
            ? (int) $validated['academic_year_id']
            : null;

        $gradeId = $validated['grade_level_id'];

        // 期別設定：同課不可重複掛兩筆「期別」；年繳方案可與期別／其他方案共用科目
        if (! $isAnnualPackage) {
            $conflictingPlan = FeePlan::query()
                ->where(function ($builder): void {
                    $builder->where('plan_kind', 'period')
                        ->orWhereNull('plan_kind')
                        ->orWhere('plan_kind', '');
                })
                ->when(
                    $gradeId === null,
                    fn ($query) => $query,
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
                    'course_ids' => "所選課目已套用於同學年、「{$conflictGrade}」的期別設定「{$conflictingPlan->group_name}」，請勿重複設定。",
                ]);
            }
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
     * @return array{id:int, name:string, course_ids:list<int>, course_prices:array<int, int|null>, min_courses:int, price:int, summary:string, summary_lines:list<string>}
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
            'summary_lines' => $parts,
        ];
    }

    /**
     * @param  Collection<int, CourseBundle>  $bundles
     * @return list<array{id:int, name:string, price:int, min_courses:int, summary:string, summary_lines:list<string>}>
     */
    private function bundlesForPlan(FeePlan $plan, Collection $bundles): array
    {
        return $this->matchingBundles($plan, $bundles)
            ->map(function (CourseBundle $bundle): array {
                $row = $this->bundleFormRow($bundle);

                return [
                    'id' => $bundle->id,
                    'name' => $bundle->name,
                    'price' => $bundle->price,
                    'min_courses' => $bundle->min_courses,
                    'summary' => $row['summary'],
                    'summary_lines' => $row['summary_lines'],
                ];
            })
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
            'plan_kind' => $plan->isAnnualPackage() ? 'annual_package' : 'period',
            'per_course_periods' => (bool) $plan->per_course_periods,
            'allow_annual' => (bool) $plan->allow_annual,
            'annual_start_month' => ($plan->allow_annual || $plan->isAnnualPackage())
                ? $plan->annualStartMonth()
                : null,
            'annual_end_month' => ($plan->allow_annual || $plan->isAnnualPackage())
                ? $plan->annualEndMonth()
                : null,
            'package_total' => $plan->package_total !== null ? (int) $plan->package_total : null,
            'annual_prices' => $plan->relationLoaded('annualPrices')
                ? $plan->annualPrices
                    ->mapWithKeys(fn ($row): array => [(int) $row->course_id => (int) $row->amount])
                    ->all()
                : [],
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
            'unit_label' => $plan->isAnnualPackage() ? '年繳方案' : '自訂期別',
            'periods' => $plan->relationLoaded('periods')
                ? $plan->periods->map(function ($period) use ($plan): array {
                    $coursePrices = [];
                    if ($period->relationLoaded('coursePrices')) {
                        foreach ($period->coursePrices as $row) {
                            $coursePrices[(int) $row->course_id] = [
                                'total_price' => (int) $row->total_price,
                                'double_price' => $row->double_price !== null ? (int) $row->double_price : null,
                                'triple_price' => $row->triple_price !== null ? (int) $row->triple_price : null,
                            ];
                        }
                    }

                    // 分科期別：價格在 period 列
                    if ($period->course_id !== null && $coursePrices === [] && (int) $period->total_price > 0) {
                        $coursePrices[(int) $period->course_id] = [
                            'total_price' => (int) $period->total_price,
                            'double_price' => $period->double_price !== null ? (int) $period->double_price : null,
                            'triple_price' => $period->triple_price !== null ? (int) $period->triple_price : null,
                        ];
                    }

                    // 相容：尚無分科價時，把期別統一價當成每一科
                    if ($coursePrices === [] && (int) $period->total_price > 0) {
                        foreach ($plan->courses as $course) {
                            $coursePrices[(int) $course->id] = [
                                'total_price' => (int) $period->total_price,
                                'double_price' => $period->double_price !== null ? (int) $period->double_price : null,
                                'triple_price' => $period->triple_price !== null ? (int) $period->triple_price : null,
                            ];
                        }
                    }

                    return [
                        'course_id' => $period->course_id !== null ? (int) $period->course_id : null,
                        'start_month' => (int) $period->start_month,
                        'end_month' => (int) $period->end_month,
                        'total_price' => (int) $period->total_price,
                        'double_price' => $period->double_price !== null ? (int) $period->double_price : null,
                        'triple_price' => $period->triple_price !== null ? (int) $period->triple_price : null,
                        'course_prices' => $coursePrices,
                    ];
                })->values()->all()
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
