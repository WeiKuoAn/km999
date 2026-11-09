<?php

use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\FeePlan;
use App\Models\GradeLevel;
use App\Models\Reconciliation;
use App\Models\Student;
use App\Support\BillingRenewal;
use App\Support\PaymentRosterBuilder;
use App\Support\PricingGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function shortTermRosterCtx(): array
{
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
    $math = Course::query()->create([
        'course_category_id' => $category->id,
        'name' => '數學課',
        'color' => '#2563eb',
        'status' => 'active',
        'pricing_group' => PricingGroup::CORE,
        'weekdays' => [2],
        'schedules' => [],
    ]);
    $camp = Course::query()->create([
        'course_category_id' => $category->id,
        'name' => '夏令營',
        'color' => '#ea580c',
        'status' => 'active',
        'pricing_group' => PricingGroup::CORE,
        'weekdays' => [1, 2, 3, 4, 5],
        'schedules' => [],
        'start_date' => '2026-08-01',
        'end_date' => '2026-08-12',
    ]);

    $plan = FeePlan::query()->create([
        'academic_year_id' => $year->id,
        'grade_level_id' => $grade->id,
        'group_name' => '測試組',
        'pricing_group' => PricingGroup::CORE,
        'unit' => 'month',
        'list_price' => 3200,
        'material_fee' => 0,
        'material_unit' => 'term',
        'sort_order' => 0,
        'is_active' => true,
    ]);
    $plan->courses()->sync([$math->id, $camp->id]);

    return compact('year', 'grade', 'math', 'camp', 'plan');
}

test('short term only payment does not appear on roster', function () {
    ['year' => $year, 'grade' => $grade, 'camp' => $camp] = shortTermRosterCtx();

    $student = Student::query()->create([
        'student_code' => 'SHORT001',
        'name' => '短期生',
        'academic_year_id' => $year->id,
        'grade_level_id' => $grade->id,
        'status' => 'active',
    ]);

    Reconciliation::query()->create([
        'student_id' => $student->id,
        'course_id' => $camp->id,
        'pay_cycle' => 'monthly',
        'billing_year' => 2026,
        'billing_month' => 8,
        'expected_amount' => 5000,
        'paid_amount' => 5000,
        'paid_date' => '2026-08-01',
        'status' => 'paid',
    ]);

    expect(BillingRenewal::lastPaidRenewalSnapshot($student))->toBeNull();

    $rows = PaymentRosterBuilder::build('', null, '2026-09', (int) $grade->id);
    expect(collect($rows)->firstWhere('student_id', $student->id))->toBeNull();
});

test('short term course is excluded when mixed with long term', function () {
    ['year' => $year, 'grade' => $grade, 'math' => $math, 'camp' => $camp] = shortTermRosterCtx();

    $student = Student::query()->create([
        'student_code' => 'MIX001',
        'name' => '長短期混合',
        'academic_year_id' => $year->id,
        'grade_level_id' => $grade->id,
        'status' => 'active',
    ]);

    foreach ([7, 8, 9] as $month) {
        Reconciliation::query()->create([
            'student_id' => $student->id,
            'course_id' => $math->id,
            'pay_cycle' => 'quarterly',
            'billing_year' => 2026,
            'billing_month' => $month,
            'expected_amount' => 3200,
            'paid_amount' => 3200,
            'paid_date' => sprintf('2026-%02d-01', $month),
            'status' => 'paid',
        ]);
    }

    Reconciliation::query()->create([
        'student_id' => $student->id,
        'course_id' => $camp->id,
        'pay_cycle' => 'monthly',
        'billing_year' => 2026,
        'billing_month' => 8,
        'expected_amount' => 5000,
        'paid_amount' => 5000,
        'paid_date' => '2026-08-01',
        'status' => 'paid',
    ]);

    $snapshot = BillingRenewal::lastPaidRenewalSnapshot($student);
    expect($snapshot)->not->toBeNull()
        ->and($snapshot['course_ids'])->toBe([(int) $math->id])
        ->and($snapshot['course_ids'])->not->toContain((int) $camp->id);

    $rows = PaymentRosterBuilder::build('', null, '2026-10', (int) $grade->id);
    $row = collect($rows)->firstWhere('student_id', $student->id);
    expect($row)->not->toBeNull()
        ->and($row['course_ids'] ?? [])->not->toContain((int) $camp->id);
});
