<?php

namespace App\Support;

use App\Models\Course;
use App\Models\Reconciliation;
use App\Models\Student;
use Carbon\Carbon;

final class PaymentRosterBuilder
{
    /**
     * 尚未繳下一期費用的學生名單（已繳 7–9 → 列出 10–12）。
     *
     * @return list<array{
     *   student_id:int,
     *   student_code:?string,
     *   student_name:string,
     *   grade_name:?string,
     *   subjects_label:string,
     *   subject_marks:list<string>,
     *   period_label:string,
     *   subjects_months:string,
     *   start_year:int,
     *   start_month:int,
     *   end_year:int,
     *   end_month:int,
     *   fee:int,
     *   note:string,
     *   pay_cycle:string,
     *   pay_cycle_label:string,
     *   fee_source:string,
     *   course_ids:list<int>
     * }>
     */
    /**
     * @return list<array<string, mixed>>
     */
    public static function build(
        ?string $q = null,
        ?int $year = null,
        ?string $periodStart = null,
        ?int $gradeLevelId = null,
    ): array {
        return self::buildRoster($q, $year, $periodStart, $gradeLevelId)['rows'];
    }

    /**
     * 依「帳期起始月」分批：傳入 'YYYY-MM' 只列該月開始的科目；'all' 列全部。
     * 建議搭配 grade_level_id，避免一次掃全校。
     *
     * @return array{
     *   rows:list<array<string, mixed>>,
     *   period_options:list<array{value:string, label:string}>,
     *   selected_period:string
     * }
     */
    public static function buildRoster(
        ?string $q = null,
        ?int $year = null,
        ?string $periodStart = null,
        ?int $gradeLevelId = null,
    ): array {
        $q = $q !== null ? trim($q) : '';

        $studentsQuery = Student::query()
            ->with(['gradeLevel:id,name', 'academicYear:id,year_code,name'])
            ->where('status', 'active')
            ->whereExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('reconciliations')
                    ->whereColumn('reconciliations.student_id', 'students.id')
                    ->where('reconciliations.status', 'paid')
                    ->whereNotNull('reconciliations.course_id');
            })
            ->when($gradeLevelId !== null, fn ($builder) => $builder->where('grade_level_id', $gradeLevelId))
            ->when($q !== '', function ($builder) use ($q): void {
                $builder->where(function ($inner) use ($q): void {
                    $inner->where('name', 'like', '%'.$q.'%')
                        ->orWhere('student_code', 'like', '%'.$q.'%');
                });
            })
            ->orderBy('name');

        $students = $studentsQuery->get();

        if ($students->isEmpty()) {
            return [
                'rows' => [],
                'period_options' => [],
                'selected_period' => self::normalizePeriodStart($periodStart) ?? '',
            ];
        }

        /** @var list<array{student:Student, pay_cycle:string, course_ids:list<int>, plan:array<string, mixed>, months_by_course:array<int, list<array{y:int,m:int}>>}> $candidates */
        $candidates = [];

        foreach ($students as $student) {
            $snapshot = BillingRenewal::lastPaidRenewalSnapshot($student);
            if ($snapshot === null) {
                continue;
            }

            $intended = is_array($student->intended_course_ids) ? $student->intended_course_ids : [];
            $courseIds = $intended !== []
                ? array_values(array_unique(array_map('intval', $intended)))
                : $snapshot['course_ids'];
            if ($courseIds === []) {
                continue;
            }

            $payCycle = $snapshot['pay_cycle'];
            $plan = BillingRenewal::perCourseNextPeriod(
                $student,
                $courseIds,
                BillingRenewal::nextStartDate($snapshot['end_year'], $snapshot['end_month']),
                $payCycle,
                true,
            );
            if ($plan['months'] === []) {
                continue;
            }

            /** @var array<int, list<array{y:int,m:int}>> $monthsByCourse */
            $monthsByCourse = [];
            foreach ($plan['course_months'] as $cid => $labels) {
                $monthsByCourse[(int) $cid] = array_map(function (string $label): array {
                    [$y, $m] = array_map('intval', explode('-', $label));

                    return ['y' => $y, 'm' => $m];
                }, $labels);
            }

            $candidates[] = [
                'student' => $student,
                'pay_cycle' => $payCycle,
                'course_ids' => $courseIds,
                'plan' => $plan,
                'months_by_course' => $monthsByCourse,
            ];
        }

        // 所有科目的「下一期起始月」選項（受年份篩選影響）
        $startKeys = [];
        foreach ($candidates as $candidate) {
            foreach ($candidate['months_by_course'] as $courseMonths) {
                if ($courseMonths === []) {
                    continue;
                }
                $first = $courseMonths[0];
                $last = $courseMonths[array_key_last($courseMonths)];
                if ($year !== null && (int) $first['y'] !== $year && (int) $last['y'] !== $year) {
                    continue;
                }
                $startKeys[(int) $first['y'] * 12 + (int) $first['m']] = true;
            }
        }
        $startKeys = array_keys($startKeys);
        sort($startKeys);

        $periodOptions = array_map(function (int $key): array {
            $y = intdiv($key - 1, 12);
            $m = (($key - 1) % 12) + 1;

            return [
                'value' => sprintf('%04d-%02d', $y, $m),
                'label' => sprintf('%d/%d 起', $y, $m),
            ];
        }, $startKeys);

        // 解析所選起始月：'all'＝全部、'YYYY-MM'＝指定月；未指定則不列（由控制器要求先選）
        $normalizedPeriod = self::normalizePeriodStart($periodStart);
        $selectedPeriod = '';
        $selectedKey = null;
        if ($normalizedPeriod === 'all') {
            $selectedPeriod = 'all';
        } elseif ($normalizedPeriod !== null) {
            [$py, $pm] = array_map('intval', explode('-', $normalizedPeriod));
            $selectedKey = $py * 12 + $pm;
            $selectedPeriod = $normalizedPeriod;
        }

        if ($selectedPeriod === '' || ($selectedPeriod !== 'all' && $selectedKey === null)) {
            return [
                'rows' => [],
                'period_options' => $periodOptions,
                'selected_period' => '',
            ];
        }

        $rows = [];

        foreach ($candidates as $candidate) {
            $student = $candidate['student'];
            $payCycle = $candidate['pay_cycle'];
            $plan = $candidate['plan'];
            $allMonthsByCourse = $candidate['months_by_course'];

            // 依起始月篩選科目
            $monthsByCourse = [];
            $courseIds = [];
            foreach ($candidate['course_ids'] as $cid) {
                $courseMonths = $allMonthsByCourse[$cid] ?? [];
                if ($courseMonths === []) {
                    continue;
                }
                if ($selectedPeriod !== 'all' && $selectedKey !== null) {
                    $first = $courseMonths[0];
                    if ((int) $first['y'] * 12 + (int) $first['m'] !== $selectedKey) {
                        continue;
                    }
                }
                $monthsByCourse[$cid] = $courseMonths;
                $courseIds[] = $cid;
            }
            if ($courseIds === [] || $monthsByCourse === []) {
                continue;
            }

            // 篩選後帳期（各科月份聯集）
            $monthMap = [];
            foreach ($monthsByCourse as $courseMonths) {
                foreach ($courseMonths as $month) {
                    $monthMap[(int) $month['y'] * 12 + (int) $month['m']] = $month;
                }
            }
            ksort($monthMap);
            $months = array_values($monthMap);
            if ($months === []) {
                continue;
            }

            $startYear = (int) $months[0]['y'];
            $startMonth = (int) $months[0]['m'];
            $endYear = (int) $months[array_key_last($months)]['y'];
            $endMonth = (int) $months[array_key_last($months)]['m'];

            if ($year !== null && $startYear !== $year && $endYear !== $year) {
                continue;
            }

            $startDate = sprintf('%04d-%02d-01', $startYear, $startMonth);
            $filteredCourseMonths = [];
            foreach ($monthsByCourse as $cid => $courseMonths) {
                $filteredCourseMonths[$cid] = array_map(
                    fn (array $month): string => ((int) $month['y']).'-'.((int) $month['m']),
                    $courseMonths
                );
            }
            $isSplit = count(array_unique(array_map(
                fn (array $courseMonths): int => (int) $courseMonths[0]['y'] * 12 + (int) $courseMonths[0]['m'],
                $monthsByCourse
            ))) > 1;

            // 下一期已繳 → 不列入
            $conflict = false;
            foreach ($monthsByCourse as $cid => $courseMonthList) {
                if (BillingRenewal::hasPaidConflicts($student, [$cid], $courseMonthList)) {
                    $conflict = true;
                    break;
                }
            }
            if ($conflict) {
                continue;
            }

            $existing = Reconciliation::query()
                ->where('student_id', $student->id)
                ->where('status', '!=', 'cancelled')
                ->where(function ($query) use ($monthsByCourse): void {
                    foreach ($monthsByCourse as $cid => $courseMonthList) {
                        foreach ($courseMonthList as $month) {
                            $query->orWhere(fn ($w) => $w
                                ->where('course_id', $cid)
                                ->where('billing_year', $month['y'])
                                ->where('billing_month', $month['m']));
                        }
                    }
                })
                ->get(['expected_amount', 'paid_amount', 'status', 'course_id']);

            $feeSource = 'estimate';
            $note = '';
            $quote = null;
            $subjectsById = collect(EnrollmentPricing::subjectsForStudent($student))->keyBy('id')->all();
            if ($existing->isNotEmpty()) {
                $unpaid = $existing->where('status', 'unpaid');
                if ($unpaid->isEmpty()) {
                    // 僅有已繳以外的狀態且無未繳 → 略過
                    continue;
                }
                $fee = (int) $unpaid->sum('expected_amount');
                $feeSource = 'unpaid';
            } else {
                $sessions = array_values(array_filter(
                    $plan['sessions'],
                    fn (array $session): bool => in_array((int) ($session['course_id'] ?? 0), $courseIds, true)
                ));
                if ($sessions === []) {
                    continue;
                }
                $materialCharges = MaterialHalfYear::autoChargesForCourseMonths($student, $filteredCourseMonths, $subjectsById);
                $quote = EnrollmentPricing::quote(
                    $student,
                    $courseIds,
                    $payCycle,
                    $sessions,
                    0,
                    $startDate,
                    [],
                    $materialCharges,
                    $isSplit ? $filteredCourseMonths : null,
                );
                $fee = (int) ($quote['grand_total'] ?? 0);
                if ($fee <= 0 || ($quote['lines'] ?? []) === []) {
                    continue;
                }
                $materialTotal = (int) ($quote['material_total'] ?? 0);
                if ($materialTotal > 0) {
                    $halves = collect($materialCharges)
                        ->map(fn (array $c): string => EnrollmentPricing::halfYearLabel($c['period_year'], $c['period_half']))
                        ->unique()
                        ->implode('、');
                    $note = sprintf('含教材 %s（%s）', number_format($materialTotal), $halves);
                }
            }

            $subjects = self::subjectLabels($courseIds);
            $periodLabel = $isSplit
                ? self::splitPeriodLabel($monthsByCourse)
                : self::periodLabel($startYear, $startMonth, $endYear, $endMonth);

            $rows[] = [
                'student_id' => $student->id,
                'student_code' => $student->student_code,
                'student_name' => $student->name,
                'grade_name' => $student->gradeLevel?->name,
                'subjects_label' => $subjects === [] ? '—' : implode('、', $subjects),
                'subject_marks' => $subjects,
                'period_label' => $periodLabel,
                'subjects_months' => ($subjects === [] ? '—' : implode('、', $subjects)).'｜'.$periodLabel,
                'start_year' => $startYear,
                'start_month' => $startMonth,
                'end_year' => $endYear,
                'end_month' => $endMonth,
                'fee' => $fee,
                'note' => $note,
                'pay_cycle' => $payCycle,
                'pay_cycle_label' => BillingRenewal::payCycleLabel($payCycle),
                'fee_source' => $feeSource,
                'course_ids' => $courseIds,
                'notice' => self::buildNotice(
                    $student->name,
                    $subjects,
                    $payCycle,
                    $startYear,
                    $startMonth,
                    $endYear,
                    $endMonth,
                    $fee,
                    $note,
                    $subjectsById,
                    $courseIds,
                    $quote,
                    count($months),
                ),
            ];
        }

        usort($rows, function (array $a, array $b): int {
            return [$a['start_year'], $a['start_month'], $a['student_name']]
                <=> [$b['start_year'], $b['start_month'], $b['student_name']];
        });

        return [
            'rows' => $rows,
            'period_options' => $periodOptions,
            'selected_period' => $selectedPeriod,
        ];
    }

    /**
     * @param  list<int>  $courseIds
     * @return list<string>
     */
    private static function subjectLabels(array $courseIds): array
    {
        if ($courseIds === []) {
            return [];
        }

        return Course::query()
            ->with('courseCategory:id,name')
            ->whereIn('id', $courseIds)
            ->orderBy('name')
            ->get()
            ->map(fn (Course $c) => $c->courseCategory?->name ?: $c->name)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * 各科帳期不同時：「數學、英文 1–3月；理化 2–4月」。
     *
     * @param  array<int, list<array{y:int,m:int}>>  $monthsByCourse
     */
    private static function splitPeriodLabel(array $monthsByCourse): string
    {
        $groups = [];
        foreach ($monthsByCourse as $cid => $courseMonths) {
            if ($courseMonths === []) {
                continue;
            }
            $first = $courseMonths[0];
            $last = $courseMonths[array_key_last($courseMonths)];
            $label = self::periodLabel($first['y'], $first['m'], $last['y'], $last['m']);
            $groups[$label][] = (int) $cid;
        }

        $parts = [];
        foreach ($groups as $label => $ids) {
            $parts[] = implode('、', self::subjectLabels($ids)).' '.$label;
        }

        return implode('；', $parts);
    }

    private static function periodLabel(int $startYear, int $startMonth, int $endYear, int $endMonth): string
    {
        if ($startYear === $endYear && $startMonth === $endMonth) {
            return sprintf('%d月', $startMonth);
        }
        if ($startYear === $endYear) {
            return sprintf('%d–%d月', $startMonth, $endMonth);
        }

        return sprintf('%d/%d—%d/%d', $startYear, $startMonth, $endYear, $endMonth);
    }

    /**
     * 繳費條用欄位（各科條列：優惠含教材／原價／教材學期價）。
     *
     * @param  list<string>  $subjects
     * @param  array<int, array<string, mixed>>  $subjectsById
     * @param  list<int>  $courseIds
     * @param  array<string, mixed>|null  $quote
     * @return array<string, mixed>
     */
    private static function buildNotice(
        string $studentName,
        array $subjects,
        string $payCycle,
        int $startYear,
        int $startMonth,
        int $endYear,
        int $endMonth,
        int $fee,
        string $note,
        array $subjectsById,
        array $courseIds,
        ?array $quote,
        int $monthCount,
    ): array {
        $cycleLabel = BillingRenewal::payCycleLabel($payCycle);
        $subjectsLabel = $subjects === [] ? '課程' : implode('、', $subjects);
        $subjectShort = implode('', array_map(
            fn (string $name): string => mb_substr(self::shortSubjectName($name), 0, 1),
            $subjects
        ));
        if ($subjectShort === '') {
            $subjectShort = '課';
        }

        $tuitionHeading = match ($payCycle) {
            'monthly' => '月繳學費',
            'annual' => '年繳學費',
            default => '季繳學費',
        };
        // 「含教材」依各科本期是否實際收取教材費再顯示
        $preferLabel = match ($payCycle) {
            'monthly' => '月繳優惠',
            'annual' => '年繳優惠',
            default => '季繳優惠',
        };

        /** @var list<array{name:string, preferential_total:int, list_price:int, material_charged:int, material_semester:int}> $subjectLines */
        $subjectLines = [];
        if (is_array($quote) && ($quote['lines'] ?? []) !== []) {
            foreach ($quote['lines'] as $line) {
                $cid = (int) ($line['course_id'] ?? 0);
                $rawName = (string) ($line['course_name'] ?? ($subjectsById[$cid]['name'] ?? '課程'));
                // 列表顯示用類別名時，優先對應 courseIds 順序的 subjects 標籤
                $labelIndex = array_search($cid, $courseIds, true);
                if ($labelIndex !== false && isset($subjects[$labelIndex])) {
                    $rawName = $subjects[$labelIndex];
                }
                $tuition = (int) ($line['tuition'] ?? 0);
                $material = (int) ($line['material'] ?? 0);
                $listPrice = (int) ($subjectsById[$cid]['list'] ?? 0);
                $materialSemester = self::materialSemesterAmount($subjectsById[$cid] ?? []);

                $subjectLines[] = [
                    'name' => self::shortSubjectName($rawName),
                    'preferential_total' => $tuition + $material,
                    'list_price' => $listPrice,
                    'material_charged' => $material,
                    'material_semester' => $materialSemester,
                ];
            }
        } else {
            // 已有未繳帳單、無試算明細時：平均攤到各科（教材從備註拆）
            $materialTotal = 0;
            if (preg_match('/含教材\s*([\d,]+)/u', $note, $m)) {
                $materialTotal = (int) str_replace(',', '', $m[1]);
            }
            $n = max(1, count($courseIds));
            $tuitionTotal = max(0, $fee - $materialTotal);
            foreach ($courseIds as $i => $cid) {
                $listPrice = (int) ($subjectsById[$cid]['list'] ?? 0);
                $materialSemester = self::materialSemesterAmount($subjectsById[$cid] ?? []);
                $name = $subjects[$i] ?? (string) ($subjectsById[$cid]['name'] ?? '課程');
                $shareTuition = (int) floor($tuitionTotal / $n) + ($i === 0 ? $tuitionTotal % $n : 0);
                $shareMaterial = (int) floor($materialTotal / $n) + ($i === 0 ? $materialTotal % $n : 0);
                $subjectLines[] = [
                    'name' => self::shortSubjectName($name),
                    'preferential_total' => $shareTuition + $shareMaterial,
                    'list_price' => $listPrice,
                    'material_charged' => $shareMaterial,
                    'material_semester' => $materialSemester,
                ];
            }
        }

        $deadline = Carbon::create($startYear, $startMonth, 1)->subMonth();

        return [
            'student_name' => $studentName,
            'subjects_label' => $subjectsLabel,
            'pay_cycle_label' => $cycleLabel,
            'tuition_heading' => $tuitionHeading,
            'prefer_label' => $preferLabel,
            'plan_label' => $subjectShort.$cycleLabel,
            'start_year' => $startYear,
            'start_month' => $startMonth,
            'end_year' => $endYear,
            'end_month' => $endMonth,
            'subject_lines' => $subjectLines,
            'grand_total' => $fee,
            'deadline_month' => (int) $deadline->month,
            'deadline_day' => 20,
            'month_count' => max(1, $monthCount),
        ];
    }

    /** @param  array<string, mixed>  $subject */
    private static function materialSemesterAmount(array $subject): int
    {
        $fee = (int) ($subject['material'] ?? 0);
        if ($fee <= 0) {
            return 0;
        }
        $unit = (string) ($subject['material_unit'] ?? 'term');

        // 耗材按日：顯示日單價；其餘為年費÷2＝學期（半年）價
        if ($unit === 'class_day') {
            return $fee;
        }

        return EnrollmentPricing::semiAnnualMaterialFee($fee);
    }

    private static function shortSubjectName(string $name): string
    {
        $trimmed = preg_replace('/(課|班)$/u', '', trim($name));

        return $trimmed !== null && $trimmed !== '' ? $trimmed : $name;
    }

    /** @return 'all'|string|null YYYY-MM 或 all；無效則 null */
    public static function normalizePeriodStart(?string $periodStart): ?string
    {
        if ($periodStart === null) {
            return null;
        }
        $periodStart = trim($periodStart);
        if ($periodStart === '' || $periodStart === 'all') {
            return $periodStart === 'all' ? 'all' : null;
        }
        if (! preg_match('/^(\d{4})-(\d{1,2})$/', $periodStart, $mm)) {
            return null;
        }
        $month = (int) $mm[2];
        if ($month < 1 || $month > 12) {
            return null;
        }

        return sprintf('%04d-%02d', (int) $mm[1], $month);
    }
}
