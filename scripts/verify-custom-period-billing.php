<?php

/**
 * 本機 MySQL 驗證：自訂期別月價×月數、單／雙／多科、期中加入。
 * 全程交易，結束 rollback，不留下測試資料。
 *
 * 用法：php scripts/verify-custom-period-billing.php
 */

use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\FeePlan;
use App\Models\FeePlanPeriod;
use App\Models\FeePlanPeriodCoursePrice;
use App\Models\GradeLevel;
use App\Models\Student;
use App\Support\CustomBillingPeriod;
use App\Support\EnrollmentPricing;
use App\Support\PricingGroup;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$failures = [];
$passes = 0;

$assert = function (bool $ok, string $label, mixed $expected = null, mixed $actual = null) use (&$failures, &$passes): void {
    if ($ok) {
        $passes++;
        echo "  ✓ {$label}\n";

        return;
    }
    $failures[] = $label;
    $detail = ($expected !== null || $actual !== null)
        ? '（期望 '.json_encode($expected, JSON_UNESCAPED_UNICODE).'，實際 '.json_encode($actual, JSON_UNESCAPED_UNICODE).'）'
        : '';
    echo "  ✗ {$label}{$detail}\n";
};

echo "=== 自訂期別收費／繳費計價驗證 ===\n";

DB::beginTransaction();

try {
    $year = AcademicYear::query()->firstOrCreate(
        ['year_code' => '999'],
        ['name' => '測試學年999', 'is_current' => false]
    );
    $grade = GradeLevel::query()->firstOrCreate(
        ['code' => 97],
        ['name' => '測試年級', 'is_active' => true]
    );
    $category = CourseCategory::query()->firstOrCreate(
        ['name' => '測試分類_billing'],
        ['sort_order' => 90]
    );

    $chinese = Course::query()->create([
        'course_category_id' => $category->id,
        'name' => '測試國文課_billing',
        'color' => '#0d9488',
        'status' => 'active',
        'pricing_group' => PricingGroup::CORE,
        'weekdays' => [2],
        'schedules' => [],
    ]);
    $bio = Course::query()->create([
        'course_category_id' => $category->id,
        'name' => '測試生物課_billing',
        'color' => '#0369a1',
        'status' => 'active',
        'pricing_group' => PricingGroup::CORE,
        'weekdays' => [4],
        'schedules' => [],
    ]);
    $eng = Course::query()->create([
        'course_category_id' => $category->id,
        'name' => '測試英文課_billing',
        'color' => '#a16207',
        'status' => 'active',
        'pricing_group' => PricingGroup::CORE,
        'weekdays' => [3],
        'schedules' => [],
    ]);

    $plan = FeePlan::query()->create([
        'academic_year_id' => $year->id,
        'grade_level_id' => $grade->id,
        'group_name' => '測試自訂期別',
        'pricing_group' => PricingGroup::CORE,
        'unit' => 'custom_period',
        'list_price' => 0,
        'material_fee' => 0,
        'material_unit' => 'term',
        'sort_order' => 90,
        'is_active' => true,
    ]);
    $plan->courses()->sync([$chinese->id, $bio->id, $eng->id]);

    /** @var FeePlanPeriod $period */
    $period = $plan->periods()->create([
        'start_month' => 7,
        'end_month' => 9,
        'total_price' => 2000,
        'sort_order' => 1,
    ]);

    foreach (
        [
            $chinese->id => ['total_price' => 2000, 'double_price' => 1800, 'triple_price' => 1600],
            $bio->id => ['total_price' => 2000, 'double_price' => 1800, 'triple_price' => 1600],
            $eng->id => ['total_price' => 2000, 'double_price' => 1800, 'triple_price' => 1600],
        ] as $courseId => $prices
    ) {
        FeePlanPeriodCoursePrice::query()->create([
            'fee_plan_period_id' => $period->id,
            'course_id' => $courseId,
            ...$prices,
        ]);
    }

    $student = Student::query()->create([
        'name' => '計價測試生',
        'student_code' => 'BILL-TEST-001',
        'academic_year_id' => $year->id,
        'grade_level_id' => $grade->id,
        'status' => 'active',
    ]);
    $student->load(['academicYear', 'gradeLevel']);

    $sessionsFor = function (array $courseIds, array $months) {
        $sessions = [];
        foreach ($courseIds as $cid) {
            foreach ($months as [$y, $m, $d]) {
                $sessions[] = [
                    'date' => sprintf('%04d-%02d-%02d', $y, $m, $d),
                    'course_id' => $cid,
                ];
            }
        }

        return $sessions;
    };

    $julSep = [[2026, 7, 7], [2026, 8, 4], [2026, 9, 1]];
    $augSep = [[2026, 8, 4], [2026, 9, 1]];

    echo "\n[A] 隔離測試資料（單科 2000／雙科 1800／多科 1600，7–9）\n";

    $single = EnrollmentPricing::quote(
        $student,
        [$chinese->id],
        'quarterly',
        $sessionsFor([$chinese->id], $julSep),
    );
    $assert(
        $single['tuition_total'] === 6000,
        '單科完整一期：2000×3＝6000',
        6000,
        $single['tuition_total'],
    );
    $assert(
        (int) ($single['lines'][0]['unit_price'] ?? 0) === 2000,
        '單科 unit_price 為月價 2000',
        2000,
        $single['lines'][0]['unit_price'] ?? null,
    );
    $assert(
        (int) ($single['lines'][0]['months'] ?? 0) === 3,
        '單科 months＝3',
        3,
        $single['lines'][0]['months'] ?? null,
    );

    $dual = EnrollmentPricing::quote(
        $student,
        [$chinese->id, $bio->id],
        'quarterly',
        $sessionsFor([$chinese->id, $bio->id], $julSep),
    );
    $assert(
        $dual['tuition_total'] === 10800,
        '雙科完整一期：1800×3×2＝10800',
        10800,
        $dual['tuition_total'],
    );
    $assert(
        collect($dual['lines'])->every(fn ($line) => (int) $line['unit_price'] === 1800),
        '雙科各科 unit_price＝1800',
    );

    $multi = EnrollmentPricing::quote(
        $student,
        [$chinese->id, $bio->id, $eng->id],
        'quarterly',
        $sessionsFor([$chinese->id, $bio->id, $eng->id], $julSep),
    );
    $assert(
        $multi['tuition_total'] === 14400,
        '多科完整一期：1600×3×3＝14400',
        14400,
        $multi['tuition_total'],
    );

    $midJoin = EnrollmentPricing::quote(
        $student,
        [$chinese->id],
        'quarterly',
        $sessionsFor([$chinese->id], $augSep),
        courseMonths: [$chinese->id => ['2026-8', '2026-9']],
    );
    $assert(
        $midJoin['tuition_total'] === 4000,
        '期中加入（剩 8–9）：2000×2＝4000',
        4000,
        $midJoin['tuition_total'],
    );

    echo "\n[B] 本機既有收費設定（fee plan #1 英／數 單 3200／雙 3000）\n";

    $livePlan = FeePlan::query()
        ->with(['courses', 'periods.coursePrices', 'gradeLevel', 'academicYear'])
        ->where('unit', 'custom_period')
        ->find(1);

    if ($livePlan === null || $livePlan->courses->count() < 2) {
        echo "  （略過：找不到 plan #1）\n";
    } else {
        $c1 = (int) $livePlan->courses[0]->id;
        $c2 = (int) $livePlan->courses[1]->id;
        $liveStudent = Student::query()
            ->where('grade_level_id', $livePlan->grade_level_id)
            ->when(
                $livePlan->academic_year_id,
                fn ($q) => $q->where('academic_year_id', $livePlan->academic_year_id)
            )
            ->first();

        if ($liveStudent === null) {
            echo "  （略過：無對應學生）\n";
        } else {
            $liveStudent->load(['academicYear', 'gradeLevel']);
            $subjects = collect(EnrollmentPricing::subjectsForStudent($liveStudent))->keyBy('id');
            $assert(
                (int) ($subjects[$c1]['fee_plan_id'] ?? 0) === (int) $livePlan->id,
                "學生「{$liveStudent->name}」套用 plan #{$livePlan->id}",
            );

            $liveSingle = EnrollmentPricing::quote(
                $liveStudent,
                [$c1],
                'quarterly',
                $sessionsFor([$c1], $julSep),
                courseMonths: [$c1 => ['2026-7', '2026-8', '2026-9']],
            );
            $assert(
                $liveSingle['tuition_total'] === 9600,
                '既有單科 7–9：3200×3＝9600',
                9600,
                $liveSingle['tuition_total'],
            );

            $liveDual = EnrollmentPricing::quote(
                $liveStudent,
                [$c1, $c2],
                'quarterly',
                $sessionsFor([$c1, $c2], $julSep),
                courseMonths: [
                    $c1 => ['2026-7', '2026-8', '2026-9'],
                    $c2 => ['2026-7', '2026-8', '2026-9'],
                ],
            );
            $assert(
                $liveDual['tuition_total'] === 18000,
                '既有雙科 7–9：3000×3×2＝18000',
                18000,
                $liveDual['tuition_total'],
            );

            // 模擬「建立未繳帳單」金額是否與試算一致（不寫入 DB）
            $assert(
                $liveDual['grand_total'] === $liveDual['tuition_total'] + $liveDual['material_total'],
                'grand_total＝學費＋教材',
            );
        }
    }

    echo "\n[C] 國文／生物：兩個月、四個月期別（月價 1800）\n";

    $cnBioPlan = FeePlan::query()->create([
        'academic_year_id' => $year->id,
        'grade_level_id' => $grade->id,
        'group_name' => '測試國／生不等長',
        'pricing_group' => PricingGroup::CORE,
        'unit' => 'custom_period',
        'list_price' => 0,
        'material_fee' => 0,
        'material_unit' => 'term',
        'sort_order' => 91,
        'is_active' => true,
    ]);
    // 與前一個 plan 錯開科目綁定：用同一國文／生物，但先拆掉舊綁定再掛新 plan
    $plan->courses()->detach([$chinese->id, $bio->id]);
    $cnBioPlan->courses()->sync([$chinese->id, $bio->id]);

    $twoMonth = $cnBioPlan->periods()->create([
        'start_month' => 7,
        'end_month' => 8,
        'total_price' => 1800,
        'sort_order' => 1,
    ]);
    $fourMonth = $cnBioPlan->periods()->create([
        'start_month' => 9,
        'end_month' => 12,
        'total_price' => 1800,
        'sort_order' => 2,
    ]);
    foreach ([$twoMonth, $fourMonth] as $row) {
        foreach ([$chinese->id, $bio->id] as $courseId) {
            FeePlanPeriodCoursePrice::query()->create([
                'fee_plan_period_id' => $row->id,
                'course_id' => $courseId,
                'total_price' => 1800,
                'double_price' => 1600,
                'triple_price' => null,
            ]);
        }
    }

    // subjectsForStudent 會快取／重查；換綁定後重新 load
    $student->refresh()->load(['academicYear', 'gradeLevel']);

    $julAug = [[2026, 7, 7], [2026, 8, 4]];
    $sepDec = [[2026, 9, 1], [2026, 10, 6], [2026, 11, 3], [2026, 12, 1]];
    $novDec = [[2026, 11, 3], [2026, 12, 1]];

    $twoSingle = EnrollmentPricing::quote(
        $student,
        [$chinese->id],
        'quarterly',
        $sessionsFor([$chinese->id], $julAug),
        courseMonths: [$chinese->id => ['2026-7', '2026-8']],
    );
    $assert(
        $twoSingle['tuition_total'] === 3600,
        '國文兩個月期（7–8）：1800×2＝3600',
        3600,
        $twoSingle['tuition_total'],
    );
    $assert(
        (int) ($twoSingle['lines'][0]['months'] ?? 0) === 2,
        '兩個月期 months＝2',
        2,
        $twoSingle['lines'][0]['months'] ?? null,
    );

    $twoDual = EnrollmentPricing::quote(
        $student,
        [$chinese->id, $bio->id],
        'quarterly',
        $sessionsFor([$chinese->id, $bio->id], $julAug),
        courseMonths: [
            $chinese->id => ['2026-7', '2026-8'],
            $bio->id => ['2026-7', '2026-8'],
        ],
    );
    $assert(
        $twoDual['tuition_total'] === 6400,
        '國文＋生物兩個月雙科：1600×2×2＝6400',
        6400,
        $twoDual['tuition_total'],
    );

    $fourSingle = EnrollmentPricing::quote(
        $student,
        [$bio->id],
        'quarterly',
        $sessionsFor([$bio->id], $sepDec),
        courseMonths: [$bio->id => ['2026-9', '2026-10', '2026-11', '2026-12']],
    );
    $assert(
        $fourSingle['tuition_total'] === 7200,
        '生物四個月期（9–12）：1800×4＝7200',
        7200,
        $fourSingle['tuition_total'],
    );
    $assert(
        (int) ($fourSingle['lines'][0]['months'] ?? 0) === 4,
        '四個月期 months＝4',
        4,
        $fourSingle['lines'][0]['months'] ?? null,
    );

    $fourMid = EnrollmentPricing::quote(
        $student,
        [$chinese->id],
        'quarterly',
        $sessionsFor([$chinese->id], $novDec),
        courseMonths: [$chinese->id => ['2026-11', '2026-12']],
    );
    $assert(
        $fourMid['tuition_total'] === 3600,
        '四個月期中加入（剩 11–12）：1800×2＝3600',
        3600,
        $fourMid['tuition_total'],
    );

    $crossPeriods = EnrollmentPricing::quote(
        $student,
        [$chinese->id],
        'quarterly',
        $sessionsFor([$chinese->id], array_merge($julAug, $sepDec)),
        courseMonths: [$chinese->id => ['2026-7', '2026-8', '2026-9', '2026-10', '2026-11', '2026-12']],
    );
    $assert(
        $crossPeriods['tuition_total'] === 3600 + 7200,
        '跨兩期（2月＋4月）同科：3600＋7200＝10800',
        10800,
        $crossPeriods['tuition_total'],
    );

    echo "\n[D] 單元規則（CustomBillingPeriod）\n";
    $assert(CustomBillingPeriod::monthCount(7, 9) === 3, '7–9 月數＝3');
    $assert(CustomBillingPeriod::monthCount(7, 8) === 2, '7–8 月數＝2');
    $assert(CustomBillingPeriod::monthCount(9, 12) === 4, '9–12 月數＝4');
    $assert(CustomBillingPeriod::periodTotalForSubjectCount([
        'total_price' => 1800,
        'month_count' => 3,
    ], 1) === 5400, '1800×3＝5400');
    $assert(CustomBillingPeriod::periodTotalForSubjectCount([
        'total_price' => 1800,
        'month_count' => 2,
    ], 1) === 3600, '1800×2＝3600');
    $assert(CustomBillingPeriod::periodTotalForSubjectCount([
        'total_price' => 1800,
        'month_count' => 4,
    ], 1) === 7200, '1800×4＝7200');
} catch (Throwable $e) {
    $failures[] = '例外：'.$e->getMessage();
    echo '  ✗ 例外：'.$e->getMessage()."\n".$e->getTraceAsString()."\n";
} finally {
    DB::rollBack();
    echo "\n（已 rollback，未寫入資料庫）\n";
}

echo "\n=== 結果：通過 {$passes}，失敗 ".count($failures)." ===\n";
exit($failures === [] ? 0 : 1);
