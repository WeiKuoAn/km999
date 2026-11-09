<?php

namespace App\Support;

use App\Models\Course;
use App\Models\PaymentRosterOverride;
use App\Models\Reconciliation;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

final class PaymentRosterBuilder
{
    /** @var array<string, list<array<string, mixed>>> */
    private static array $candidatesCache = [];

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
    /**
     * 依該年級學生「實際下一期起始月」列出選項（已繳到幾月就從下個月起算）。
     * 不跑費用試算，只推算帳期，給篩選下拉用。
     *
     * @return list<array{value:string, label:string}>
     */
    public static function periodOptionsForGrade(int $gradeLevelId, ?string $q = null): array
    {
        $candidates = self::collectPendingCandidates($q, $gradeLevelId);
        /** @var array<int, array{start_y:int, start_m:int, end_m:int, student_ids:array<int, true>}> $meta */
        $meta = [];

        foreach ($candidates as $candidate) {
            $studentId = (int) $candidate['student']->id;
            foreach ($candidate['months_by_course'] as $courseMonths) {
                if ($courseMonths === []) {
                    continue;
                }
                $first = $courseMonths[0];
                $last = $courseMonths[array_key_last($courseMonths)];
                $key = (int) $first['y'] * 12 + (int) $first['m'];
                if (! isset($meta[$key])) {
                    $meta[$key] = [
                        'start_y' => (int) $first['y'],
                        'start_m' => (int) $first['m'],
                        'end_m' => (int) $last['m'],
                        'student_ids' => [],
                    ];
                }
                $meta[$key]['student_ids'][$studentId] = true;
            }
        }

        ksort($meta);
        $options = [];
        foreach ($meta as $row) {
            $count = count($row['student_ids']);
            $options[] = [
                'value' => sprintf('%04d-%02d', $row['start_y'], $row['start_m']),
                'label' => sprintf(
                    '%d/%d 起（%d 人待繳）',
                    $row['start_y'],
                    $row['start_m'],
                    $count
                ),
            ];
        }

        return $options;
    }

    public static function buildRoster(
        ?string $q = null,
        ?int $year = null,
        ?string $periodStart = null,
        ?int $gradeLevelId = null,
    ): array {
        $candidates = self::collectPendingCandidates($q, $gradeLevelId);

        if ($candidates === []) {
            return [
                'rows' => [],
                'period_options' => [],
                'selected_period' => self::normalizePeriodStart($periodStart) ?? '',
            ];
        }

        // 所有科目的「下一期起始月」選項（受年份篩選影響）
        $startMeta = [];
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
                $key = (int) $first['y'] * 12 + (int) $first['m'];
                if (! isset($startMeta[$key])) {
                    $startMeta[$key] = [
                        'start_y' => (int) $first['y'],
                        'start_m' => (int) $first['m'],
                        'end_m' => (int) $last['m'],
                    ];
                }
            }
        }
        ksort($startMeta);

        $periodOptions = [];
        foreach ($startMeta as $row) {
            $periodOptions[] = [
                'value' => sprintf('%04d-%02d', $row['start_y'], $row['start_m']),
                'label' => sprintf('%d/%d 起', $row['start_y'], $row['start_m']),
            ];
        }

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

        $allCourseIds = [];
        foreach ($candidates as $candidate) {
            foreach ($candidate['course_ids'] as $cid) {
                $allCourseIds[] = (int) $cid;
            }
        }
        $subjectLabelMap = self::subjectLabelMap($allCourseIds);

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
            // 各科月份集合是否不同（起月相同但結束不同也算）
            $monthSetKeys = array_map(
                function (array $courseMonths): string {
                    $keys = array_map(
                        fn (array $month): string => ((int) $month['y']).'-'.((int) $month['m']),
                        $courseMonths
                    );
                    sort($keys);

                    return implode(',', $keys);
                },
                $monthsByCourse
            );
            $monthsDifferByCourse = count(array_unique(array_values($monthSetKeys))) > 1;

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
                // 一律帶各科月份：起月相同但結束不同時，不可用聯集月份灌到每一科
                $quote = EnrollmentPricing::quote(
                    $student,
                    $courseIds,
                    $payCycle,
                    $sessions,
                    0,
                    $startDate,
                    [],
                    $materialCharges,
                    $filteredCourseMonths,
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

            $subjects = self::subjectLabels($courseIds, $subjectLabelMap);
            $periodLabel = $monthsDifferByCourse
                ? self::splitPeriodLabel($monthsByCourse, $subjectLabelMap)
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

        $rows = self::applyFeeOverrides($rows);

        return [
            'rows' => $rows,
            'period_options' => $periodOptions,
            'selected_period' => $selectedPeriod,
        ];
    }

    /**
     * 套用繳費名單手動調整（科目／月份／金額；不開帳，只改顯示／繳費條）。
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private static function applyFeeOverrides(array $rows): array
    {
        if ($rows === [] || ! Schema::hasTable('payment_roster_overrides')) {
            return $rows;
        }

        $studentIds = array_values(array_unique(array_map(
            fn (array $row): int => (int) $row['student_id'],
            $rows
        )));

        $overrides = PaymentRosterOverride::query()
            ->whereIn('student_id', $studentIds)
            ->get()
            ->keyBy(fn (PaymentRosterOverride $row): string => self::overrideKey(
                (int) $row->student_id,
                (int) $row->start_year,
                (int) $row->start_month,
            ));

        if ($overrides->isEmpty()) {
            return $rows;
        }

        foreach ($rows as &$row) {
            $key = self::overrideKey(
                (int) $row['student_id'],
                (int) $row['start_year'],
                (int) $row['start_month'],
            );
            $override = $overrides->get($key);
            if ($override === null) {
                $row['fee_overridden'] = false;
                $row['estimated_fee'] = (int) $row['fee'];
                continue;
            }

            $estimated = (int) $row['fee'];
            $adjusted = max(0, (int) $override->fee);
            $row['estimated_fee'] = $estimated;
            $row['fee'] = $adjusted;
            $row['fee_overridden'] = true;
            $row['fee_source'] = 'override';
            $row['override_note'] = $override->note;
            $row['course_months'] = is_array($override->course_months) ? $override->course_months : null;

            $courseIds = is_array($override->course_ids)
                ? array_values(array_map('intval', $override->course_ids))
                : [];
            if ($courseIds !== []) {
                $row['course_ids'] = $courseIds;
                $subjects = self::subjectLabels($courseIds);
                $row['subjects_label'] = $subjects === [] ? '—' : implode('、', $subjects);
                $row['subject_marks'] = $subjects;
            }

            if (is_string($override->pay_cycle) && $override->pay_cycle !== '') {
                $row['pay_cycle'] = $override->pay_cycle;
                $row['pay_cycle_label'] = BillingRenewal::payCycleLabel($override->pay_cycle);
            }

            $monthsByCourse = [];
            if (is_array($override->course_months)) {
                foreach ($override->course_months as $cid => $keys) {
                    $list = [];
                    foreach ((array) $keys as $keyLabel) {
                        if (! is_string($keyLabel) || ! preg_match('/^(\d{4})-(\d{1,2})$/', $keyLabel, $m)) {
                            continue;
                        }
                        $list[] = ['y' => (int) $m[1], 'm' => (int) $m[2]];
                    }
                    if ($list !== []) {
                        usort($list, fn (array $a, array $b): int => [$a['y'], $a['m']] <=> [$b['y'], $b['m']]);
                        $monthsByCourse[(int) $cid] = $list;
                    }
                }
            }

            // 顯示用起始（篩選 key 仍用原本 start_year/month）
            $noticeStartY = (int) $row['start_year'];
            $noticeStartM = (int) $row['start_month'];

            if ($monthsByCourse !== []) {
                $flat = [];
                foreach ($monthsByCourse as $list) {
                    foreach ($list as $month) {
                        $flat[] = $month;
                    }
                }
                usort($flat, fn (array $a, array $b): int => [$a['y'], $a['m']] <=> [$b['y'], $b['m']]);
                $first = $flat[0];
                $last = $flat[array_key_last($flat)];
                $row['end_year'] = (int) $last['y'];
                $row['end_month'] = (int) $last['m'];
                $noticeStartY = (int) $first['y'];
                $noticeStartM = (int) $first['m'];
                $labelById = self::subjectLabelMap($row['course_ids'] ?? []);
                $monthSetKeys = array_map(
                    function (array $courseMonths): string {
                        $keys = array_map(
                            fn (array $month): string => ((int) $month['y']).'-'.((int) $month['m']),
                            $courseMonths
                        );
                        sort($keys);

                        return implode(',', $keys);
                    },
                    $monthsByCourse
                );
                $monthsDiffer = count(array_unique(array_values($monthSetKeys))) > 1;
                $periodLabel = $monthsDiffer
                    ? self::splitPeriodLabel($monthsByCourse, $labelById)
                    : self::periodLabel($noticeStartY, $noticeStartM, (int) $last['y'], (int) $last['m']);
                $row['period_label'] = $periodLabel;
                $subjects = $row['subject_marks'] ?? [];
                $row['subjects_months'] = ($subjects === [] ? '—' : implode('、', $subjects)).'｜'.$periodLabel;
            } elseif ($override->end_year !== null && $override->end_month !== null) {
                $row['end_year'] = (int) $override->end_year;
                $row['end_month'] = (int) $override->end_month;
                $row['period_label'] = self::periodLabel(
                    $noticeStartY,
                    $noticeStartM,
                    (int) $row['end_year'],
                    (int) $row['end_month'],
                );
                $subjects = $row['subject_marks'] ?? [];
                $row['subjects_months'] = ($subjects === [] ? '—' : implode('、', $subjects)).'｜'.$row['period_label'];
            }

            $mark = '名單已手動調整';
            if (is_string($override->note) && trim($override->note) !== '') {
                $mark .= '：'.trim($override->note);
            }
            $existingNote = trim((string) ($row['note'] ?? ''));
            // 去掉舊的「金額已手動調整」標記，改寫新標記
            $existingNote = trim((string) preg_replace('/；?金額已手動調整(?:：[^；]*)?/u', '', $existingNote));
            $existingNote = trim((string) preg_replace('/；?名單已手動調整(?:：[^；]*)?/u', '', $existingNote));
            $row['note'] = $existingNote === '' ? $mark : $existingNote.'；'.$mark;

            if (isset($row['notice']) && is_array($row['notice'])) {
                $row['notice']['grand_total'] = $adjusted;
                if (isset($row['subjects_label'])) {
                    $row['notice']['subjects_label'] = $row['subjects_label'];
                }
                if (isset($row['period_label'])) {
                    $row['notice']['period_label'] = $row['period_label'];
                }
                // 繳費條修業期間依 notice 年月組字；override 改月份後必須同步
                if (isset($row['end_year'], $row['end_month'])) {
                    $row['notice']['start_year'] = $noticeStartY;
                    $row['notice']['start_month'] = $noticeStartM;
                    $row['notice']['end_year'] = (int) $row['end_year'];
                    $row['notice']['end_month'] = (int) $row['end_month'];
                    $row['notice']['month_count'] = max(
                        1,
                        (($row['notice']['end_year'] - $noticeStartY) * 12)
                            + ((int) $row['end_month'] - $noticeStartM)
                            + 1
                    );
                }
            }
        }
        unset($row);

        return $rows;
    }

    private static function overrideKey(int $studentId, int $startYear, int $startMonth): string
    {
        return $studentId.'|'.$startYear.'|'.$startMonth;
    }

    /**
     * @param  list<int>  $courseIds
     * @param  array<int, string>  $labelById
     * @return list<string>
     */
    private static function subjectLabels(array $courseIds, array $labelById = []): array
    {
        if ($courseIds === []) {
            return [];
        }

        if ($labelById === []) {
            $labelById = self::subjectLabelMap($courseIds);
        }

        $labels = [];
        foreach ($courseIds as $cid) {
            $label = $labelById[(int) $cid] ?? null;
            if ($label !== null && $label !== '') {
                $labels[] = $label;
            }
        }

        return array_values(array_unique($labels));
    }

    /**
     * @param  list<int>  $courseIds
     * @return array<int, string>
     */
    private static function subjectLabelMap(array $courseIds): array
    {
        if ($courseIds === []) {
            return [];
        }

        // 用課程名稱，去掉結尾「課／班」；科研／高瞻等辨識字保留
        // 例：英文課→英文、理化科研班→理化科研、科研數學→科研數學、高瞻英文班→高瞻英文
        return Course::query()
            ->whereIn('id', array_values(array_unique($courseIds)))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->mapWithKeys(fn (Course $c): array => [
                (int) $c->id => self::shortSubjectName((string) $c->name),
            ])
            ->all();
    }

    /**
     * 各科帳期不同時：「數學、英文 1–3月；理化 2–4月」。
     *
     * @param  array<int, list<array{y:int,m:int}>>  $monthsByCourse
     * @param  array<int, string>  $labelById
     */
    private static function splitPeriodLabel(array $monthsByCourse, array $labelById = []): string
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
            $parts[] = implode('、', self::subjectLabels($ids, $labelById)).' '.$label;
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
                // 優先對應 courseIds 順序的課程名稱標籤
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

    /**
     * 蒐集「有下一期待繳」的學生（只推算帳期，不算費用）。
     *
     * @return list<array{
     *   student:Student,
     *   pay_cycle:string,
     *   course_ids:list<int>,
     *   plan:array<string, mixed>,
     *   months_by_course:array<int, list<array{y:int,m:int}>>
     * }>
     */
    private static function collectPendingCandidates(?string $q, ?int $gradeLevelId): array
    {
        $q = $q !== null ? trim($q) : '';
        $cacheKey = $q.'|'.($gradeLevelId ?? 'all');
        if (isset(self::$candidatesCache[$cacheKey])) {
            return self::$candidatesCache[$cacheKey];
        }

        $students = Student::query()
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
            ->orderBy('name')
            ->get();

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
            // 短期課一次性收費，不進續期名單
            $courseIds = BillingRenewal::filterRenewableCourseIds($courseIds);
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

            // 下一期已繳 → 不列入選項／名單
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

            $candidates[] = [
                'student' => $student,
                'pay_cycle' => $payCycle,
                'course_ids' => $courseIds,
                'plan' => $plan,
                'months_by_course' => $monthsByCourse,
            ];
        }

        return self::$candidatesCache[$cacheKey] = $candidates;
    }
}
