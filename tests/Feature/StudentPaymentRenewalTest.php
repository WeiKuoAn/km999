<?php

use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\FeePlan;
use App\Models\GradeLevel;
use App\Models\Reconciliation;
use App\Models\Student;
use App\Models\User;
use App\Support\BillingRenewal;
use App\Support\PricingGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function renewalTestContext(): array
{
    $year = AcademicYear::query()->create([
        'year_code' => '115',
        'name' => '115學年度',
        'is_current' => true,
    ]);
    $grade = GradeLevel::query()->create([
        'name' => '國一',
        'code' => 7,
        'is_active' => true,
    ]);
    $category = CourseCategory::query()->create([
        'name' => '英文',
        'sort_order' => 1,
    ]);
    $course = Course::query()->create([
        'course_category_id' => $category->id,
        'name' => '英文課',
        'color' => '#0d9488',
        'status' => 'active',
        'pricing_group' => PricingGroup::CORE,
        'weekdays' => [1, 4],
        'schedules' => [
            ['level' => '國一', 'weekday' => 1, 'start_time' => '17:30', 'end_time' => '19:00'],
            ['level' => '國一', 'weekday' => 4, 'start_time' => '17:30', 'end_time' => '19:00'],
        ],
    ]);
    $course->coursePrices()->create([
        'level' => '國一',
        'duration_hours' => 1.5,
        'tuition' => 0,
        'sort_order' => 0,
    ]);
    $plan = FeePlan::query()->create([
        'academic_year_id' => $year->id,
        'grade_level_id' => $grade->id,
        'group_name' => '核心科',
        'pricing_group' => PricingGroup::CORE,
        'unit' => 'month',
        'list_price' => 3600,
        'quarter_single_price' => 3200,
        'quarter_double_price' => 3000,
        'material_fee' => 0,
        'material_unit' => 'term',
        'sort_order' => 10,
        'is_active' => true,
    ]);
    $plan->courses()->sync([$course->id]);

    $student = Student::query()->create([
        'student_code' => '11507001',
        'academic_year_id' => $year->id,
        'grade_level_id' => $grade->id,
        'name' => '陳曉明',
        'status' => 'active',
    ]);

    return compact('year', 'grade', 'category', 'course', 'plan', 'student');
}

it('create page marks prior payments and suggested start date', function () {
    ['student' => $student, 'course' => $course] = renewalTestContext();

    Reconciliation::query()->create([
        'student_id' => $student->id,
        'classroom_id' => null,
        'course_id' => $course->id,
        'pay_cycle' => 'quarterly',
        'billing_year' => 2026,
        'billing_month' => 9,
        'expected_amount' => 3200,
        'paid_amount' => 0,
        'status' => 'unpaid',
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('student-payments.create', ['student_id' => $student->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('StudentPayments/Create')
            ->where('has_prior_payments', true)
            ->where('suggested_start_date', '2026-10-01')
        );
});

it('store forces start date not earlier than suggested when prior payments exist', function () {
    ['student' => $student, 'course' => $course] = renewalTestContext();

    Reconciliation::query()->create([
        'student_id' => $student->id,
        'classroom_id' => null,
        'course_id' => $course->id,
        'pay_cycle' => 'quarterly',
        'billing_year' => 2026,
        'billing_month' => 9,
        'expected_amount' => 3200,
        'paid_amount' => 0,
        'status' => 'unpaid',
    ]);

    $sessions = BillingRenewal::defaultSessions($student, [$course->id], '2026-10-01', 'quarterly');
    expect($sessions)->not->toBeEmpty();

    $this->actingAs(User::factory()->create())
        ->post(route('student-payments.store', $student), [
            'course_ids' => [$course->id],
            'pay_cycle' => 'quarterly',
            'sessions' => $sessions,
            'allowance' => 0,
            'start_date' => '2026-07-01',
        ])
        ->assertRedirect(route('student-payments.show', $student));

    expect(
        Reconciliation::query()
            ->where('student_id', $student->id)
            ->where('billing_year', 2026)
            ->whereIn('billing_month', [10, 11, 12])
            ->count()
    )->toBeGreaterThan(0);
});

it('renew next creates three months for quarterly from last cycle', function () {
    ['student' => $student, 'course' => $course] = renewalTestContext();

    foreach ([7, 8, 9] as $month) {
        Reconciliation::query()->create([
            'student_id' => $student->id,
            'classroom_id' => null,
            'course_id' => $course->id,
            'pay_cycle' => 'quarterly',
            'billing_year' => 2026,
            'billing_month' => $month,
            'expected_amount' => 3200,
            'paid_amount' => 0,
            'status' => 'unpaid',
        ]);
    }

    $snapshot = BillingRenewal::lastBillingSnapshot($student);
    expect($snapshot)->not->toBeNull()
        ->and($snapshot['course_ids'])->toBe([$course->id])
        ->and($snapshot['pay_cycle'])->toBe('quarterly')
        ->and(BillingRenewal::nextStartDate($snapshot['end_year'], $snapshot['end_month']))->toBe('2026-10-01');

    $this->actingAs(User::factory()->create())
        ->post(route('student-payments.renew-next', $student))
        ->assertRedirect(route('student-payments.show', $student));

    $months = Reconciliation::query()
        ->where('student_id', $student->id)
        ->where('course_id', $course->id)
        ->where('billing_year', 2026)
        ->whereIn('billing_month', [10, 11, 12])
        ->pluck('billing_month')
        ->sort()
        ->values()
        ->all();

    expect($months)->toBe([10, 11, 12]);
});

it('renew next rejects when target months already paid', function () {
    ['student' => $student, 'course' => $course] = renewalTestContext();

    Reconciliation::query()->create([
        'student_id' => $student->id,
        'classroom_id' => null,
        'course_id' => $course->id,
        'pay_cycle' => 'quarterly',
        'billing_year' => 2026,
        'billing_month' => 9,
        'expected_amount' => 3200,
        'paid_amount' => 0,
        'status' => 'unpaid',
    ]);

    Reconciliation::query()->create([
        'student_id' => $student->id,
        'classroom_id' => null,
        'course_id' => $course->id,
        'pay_cycle' => 'quarterly',
        'billing_year' => 2026,
        'billing_month' => 10,
        'expected_amount' => 3200,
        'paid_amount' => 3200,
        'status' => 'paid',
        'paid_date' => '2026-10-05',
    ]);

    $this->actingAs(User::factory()->create())
        ->from(route('student-payments.show', $student))
        ->post(route('student-payments.renew-next', $student))
        ->assertRedirect(route('student-payments.show', $student))
        ->assertSessionHasErrors('renewal');
});
