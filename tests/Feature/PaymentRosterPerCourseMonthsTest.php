<?php

use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\FeePlan;
use App\Models\FeePlanPeriod;
use App\Models\FeePlanPeriodCoursePrice;
use App\Models\GradeLevel;
use App\Models\Reconciliation;
use App\Models\Student;
use App\Models\User;
use App\Support\PaymentRosterBuilder;
use App\Support\PricingGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('roster fee uses per-course months when end months differ', function () {
    $year = AcademicYear::query()->create([
        'year_code' => '115',
        'name' => '115學年度',
        'is_current' => true,
    ]);
    $grade = GradeLevel::query()->create([
        'name' => '國二',
        'code' => 8,
        'is_active' => true,
        'sort_order' => 8,
    ]);
    $category = CourseCategory::query()->create([
        'name' => '國中課程',
        'sort_order' => 1,
    ]);
    $science = Course::query()->create([
        'course_category_id' => $category->id,
        'name' => '理化課',
        'color' => '#0d9488',
        'status' => 'active',
        'pricing_group' => PricingGroup::CORE,
        'weekdays' => [2, 5],
        'schedules' => [],
    ]);
    $chinese = Course::query()->create([
        'course_category_id' => $category->id,
        'name' => '國文課',
        'color' => '#db2777',
        'status' => 'active',
        'pricing_group' => PricingGroup::CORE,
        'weekdays' => [6],
        'schedules' => [],
    ]);

    $plan = FeePlan::query()->create([
        'academic_year_id' => $year->id,
        'grade_level_id' => $grade->id,
        'group_name' => '國理',
        'pricing_group' => PricingGroup::CORE,
        'unit' => 'custom_period',
        'per_course_periods' => true,
        'list_price' => 0,
        'material_fee' => 0,
        'material_unit' => 'term',
        'sort_order' => 0,
        'is_active' => true,
    ]);
    $plan->courses()->sync([
        $science->id => ['material_fee' => 2400],
        $chinese->id => ['material_fee' => 1800],
    ]);

    // 理化：11–1；國文：9–12（下一期：理化 12–1、國文僅 12）
    foreach ([
        [$science->id, 11, 1, 3200],
        [$chinese->id, 9, 12, 1800],
    ] as [$courseId, $start, $end, $price]) {
        $period = FeePlanPeriod::query()->create([
            'fee_plan_id' => $plan->id,
            'course_id' => $courseId,
            'start_month' => $start,
            'end_month' => $end,
            'total_price' => $price,
            'sort_order' => 0,
        ]);
        FeePlanPeriodCoursePrice::query()->create([
            'fee_plan_period_id' => $period->id,
            'course_id' => $courseId,
            'total_price' => $price,
        ]);
    }

    $student = Student::query()->create([
        'student_code' => 'ROSTER001',
        'name' => '分科帳期生',
        'academic_year_id' => $year->id,
        'grade_level_id' => $grade->id,
        'status' => 'active',
    ]);

    foreach ([$science->id, $chinese->id] as $courseId) {
        Reconciliation::query()->create([
            'student_id' => $student->id,
            'classroom_id' => null,
            'course_id' => $courseId,
            'pay_cycle' => 'quarterly',
            'billing_year' => 2026,
            'billing_month' => 11,
            'expected_amount' => $courseId === $science->id ? 3200 : 1800,
            'paid_amount' => $courseId === $science->id ? 3200 : 1800,
            'paid_date' => '2026-11-01',
            'status' => 'paid',
        ]);
    }

    $rows = PaymentRosterBuilder::build('', null, '2026-12', (int) $grade->id);
    $row = collect($rows)->firstWhere('student_id', $student->id);

    expect($row)->not->toBeNull()
        // 理化 12+1＝6400、國文僅 12＝1800、教材半年 1200 → 9400
        // （若誤用聯集月份灌國文 1 月會變成 11200）
        ->and((int) $row['fee'])->toBe(9400)
        ->and($row['period_label'])->toContain('國文');
});
