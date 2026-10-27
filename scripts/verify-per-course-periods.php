<?php

/**
 * 本機 MySQL 驗證：每科不同期 + 同設定多科優惠。
 * 全程交易，結束 rollback。
 *
 * 用法：php scripts/verify-per-course-periods.php
 */

use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\FeePlan;
use App\Models\GradeLevel;
use App\Models\Student;
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

$clearSubjectsCache = function (): void {
    $ref = new \ReflectionClass(EnrollmentPricing::class);
    $prop = $ref->getProperty('subjectsByGradeYearCache');
    $prop->setAccessible(true);
    $prop->setValue(null, []);
};

echo "=== 每科不同期／多科優惠驗證 ===\n";

DB::beginTransaction();

try {
    $year = AcademicYear::query()->firstOrCreate(
        ['year_code' => '998'],
        ['name' => '測試學年998', 'is_current' => false]
    );
    $grade = GradeLevel::query()->firstOrCreate(
        ['code' => 96],
        ['name' => '測試年級異期', 'is_active' => true]
    );
    $category = CourseCategory::query()->firstOrCreate(
        ['name' => '測試分類_per_course'],
        ['sort_order' => 91]
    );

    $makeCourse = function (string $name) use ($category): Course {
        return Course::query()->create([
            'course_category_id' => $category->id,
            'name' => $name,
            'color' => '#0d9488',
            'status' => 'active',
            'pricing_group' => PricingGroup::CORE,
            'weekdays' => [2],
            'schedules' => [],
        ]);
    };

    $english = $makeCourse('測試英文_異期');
    $math = $makeCourse('測試數學_異期');
    $physics = $makeCourse('測試理化_異期');

    $plan = FeePlan::query()->create([
        'academic_year_id' => $year->id,
        'grade_level_id' => $grade->id,
        'group_name' => '英數理異期驗證',
        'pricing_group' => PricingGroup::CORE,
        'unit' => 'custom_period',
        'per_course_periods' => true,
        'list_price' => 0,
        'material_fee' => 0,
        'material_unit' => 'term',
        'sort_order' => 1,
        'is_active' => true,
    ]);
    $plan->courses()->sync([$english->id, $math->id, $physics->id]);

    $addPeriod = function (int $courseId, int $start, int $end, int $single, int $double, int $triple) use ($plan): void {
        $period = $plan->periods()->create([
            'course_id' => $courseId,
            'start_month' => $start,
            'end_month' => $end,
            'total_price' => $single,
            'double_price' => $double,
            'triple_price' => $triple,
            'sort_order' => $plan->periods()->count(),
        ]);
        $period->coursePrices()->create([
            'course_id' => $courseId,
            'total_price' => $single,
            'double_price' => $double,
            'triple_price' => $triple,
        ]);
    };

    $addPeriod($english->id, 6, 8, 2000, 1800, 1600);
    $addPeriod($english->id, 9, 11, 2000, 1800, 1600);
    $addPeriod($math->id, 6, 8, 2100, 1900, 1700);
    $addPeriod($math->id, 9, 11, 2100, 1900, 1700);
    $addPeriod($physics->id, 7, 10, 2200, 2000, 1800);
    $addPeriod($physics->id, 11, 1, 2200, 2000, 1800);

    $plan->load(['periods.coursePrices', 'courses']);
    $assert($plan->periods->count() === 6, '分科期別共 6 筆');
    $assert(
        $plan->periods->where('course_id', $english->id)->count() === 2
        && (int) $plan->periods->where('course_id', $physics->id)->first()->start_month === 7,
        '英／理起迄月不同'
    );

    $lines = $plan->periodSummaryLines();
    $assert(count($lines) === 3, '列表摘要三科各一行', 3, count($lines));
    $assert(collect($lines)->every(fn (array $l): bool => $l['months'] === 0), '分科摘要 months=0');

    $student = Student::query()->create([
        'name' => '異期驗證學生',
        'academic_year_id' => $year->id,
        'grade_level_id' => $grade->id,
    ]);

    $clearSubjectsCache();
    $subjects = collect(EnrollmentPricing::subjectsForStudent($student->load(['academicYear', 'gradeLevel'])))
        ->keyBy('id');

    $assert(count($subjects[$english->id]['periods'] ?? []) === 2, '英文科目 periods 為 2');
    $assert(
        (int) ($subjects[$english->id]['periods'][0]['start_month'] ?? 0) === 6,
        '英文第一期起月=6'
    );
    $assert(
        (int) ($subjects[$physics->id]['periods'][0]['start_month'] ?? 0) === 7,
        '理化第一期起月=7'
    );

    $quote = EnrollmentPricing::quote($student, [$english->id, $math->id, $physics->id], 'quarterly', [
        ['date' => '2026-09-01', 'course_id' => $english->id],
        ['date' => '2026-09-02', 'course_id' => $math->id],
        ['date' => '2026-09-03', 'course_id' => $physics->id],
    ]);
    $byCourse = collect($quote['lines'])->keyBy('course_id');

    $assert((int) $byCourse[$english->id]['unit_price'] === 1600, '三科時英文 9 月用多科價', 1600, $byCourse[$english->id]['unit_price'] ?? null);
    $assert((int) $byCourse[$math->id]['unit_price'] === 1700, '三科時數學 9 月用多科價', 1700, $byCourse[$math->id]['unit_price'] ?? null);
    $assert((int) $byCourse[$physics->id]['unit_price'] === 1800, '三科時理化 9 月用多科價', 1800, $byCourse[$physics->id]['unit_price'] ?? null);
    $assert((int) $byCourse[$english->id]['tuition'] === 1600, '英文 9 月學費=1600');
    $assert((int) $byCourse[$physics->id]['tuition'] === 1800, '理化 9 月學費=1800');

    // 共用期別模式仍可存
    $shared = FeePlan::query()->create([
        'academic_year_id' => $year->id,
        'grade_level_id' => $grade->id,
        'group_name' => '共用期驗證',
        'pricing_group' => PricingGroup::CORE,
        'unit' => 'custom_period',
        'per_course_periods' => false,
        'list_price' => 0,
        'material_fee' => 0,
        'material_unit' => 'term',
        'sort_order' => 2,
        'is_active' => true,
    ]);
    // 用另一組課避免衝突（上面英數理已綁定）
    $extra = $makeCourse('測試共用科_異期');
    $shared->courses()->sync([$extra->id]);
    $period = $shared->periods()->create([
        'course_id' => null,
        'start_month' => 7,
        'end_month' => 9,
        'total_price' => 1500,
        'sort_order' => 0,
    ]);
    $period->coursePrices()->create([
        'course_id' => $extra->id,
        'total_price' => 1500,
        'double_price' => null,
        'triple_price' => null,
    ]);
    $assert($shared->fresh()->per_course_periods === false, '共用模式旗標為 false');
    $assert($shared->periods()->sole()->course_id === null, '共用期別 course_id 為 null');
} finally {
    DB::rollBack();
}

echo "\n結果：通過 {$passes}，失敗 ".count($failures)."\n";
exit($failures === [] ? 0 : 1);
