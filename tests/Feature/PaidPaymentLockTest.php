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
use App\Support\BillingRenewal;
use App\Support\PricingGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function paidLockCtx(): array
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
    $english = Course::query()->create([
        'course_category_id' => $category->id,
        'name' => '英文課',
        'color' => '#0d9488',
        'status' => 'active',
        'pricing_group' => PricingGroup::CORE,
        'weekdays' => [2],
        'schedules' => [],
    ]);
    $math = Course::query()->create([
        'course_category_id' => $category->id,
        'name' => '數學課',
        'color' => '#2563eb',
        'status' => 'active',
        'pricing_group' => PricingGroup::CORE,
        'weekdays' => [3],
        'schedules' => [],
    ]);

    $plan = FeePlan::query()->create([
        'academic_year_id' => $year->id,
        'grade_level_id' => $grade->id,
        'group_name' => '英數',
        'pricing_group' => PricingGroup::CORE,
        'unit' => 'custom_period',
        'per_course_periods' => false,
        'list_price' => 0,
        'material_fee' => 0,
        'material_unit' => 'term',
        'sort_order' => 0,
        'is_active' => true,
    ]);
    $plan->courses()->sync([$english->id, $math->id]);
    $period = FeePlanPeriod::query()->create([
        'fee_plan_id' => $plan->id,
        'course_id' => null,
        'start_month' => 7,
        'end_month' => 9,
        'total_price' => 0,
        'sort_order' => 0,
    ]);
    foreach ([$english->id, $math->id] as $courseId) {
        FeePlanPeriodCoursePrice::query()->create([
            'fee_plan_period_id' => $period->id,
            'course_id' => $courseId,
            'total_price' => 3200,
            'double_price' => 3000,
            'triple_price' => null,
        ]);
    }

    $student = Student::query()->create([
        'student_code' => 'LOCK001',
        'name' => '鎖定測試生',
        'academic_year_id' => $year->id,
        'grade_level_id' => $grade->id,
        'status' => 'active',
    ]);

    $admin = User::query()->create([
        'name' => '管理員',
        'email' => 'paid-lock-'.uniqid('', true).'@example.test',
        'password' => bcrypt('password'),
        'role' => 'admin',
        'is_active' => true,
    ]);

    return compact('year', 'grade', 'english', 'math', 'plan', 'student', 'admin');
}

function seedPaidQuarter(Student $student, Course $course, int $amount = 9000): void
{
    foreach ([7, 8, 9] as $month) {
        Reconciliation::query()->create([
            'student_id' => $student->id,
            'classroom_id' => null,
            'course_id' => $course->id,
            'pay_cycle' => 'quarterly',
            'billing_year' => 2026,
            'billing_month' => $month,
            'expected_amount' => intdiv($amount, 3),
            'paid_amount' => intdiv($amount, 3),
            'paid_date' => '2026-07-01',
            'status' => 'paid',
            'receipt_no' => 'R-LOCK-1',
            'note' => '報名計價｜'.$course->name.'｜單價 3,000',
        ]);
    }
}

test('removing course from fee plan does not change paid reconciliations', function () {
    ['english' => $english, 'math' => $math, 'plan' => $plan, 'student' => $student, 'admin' => $admin] = paidLockCtx();
    seedPaidQuarter($student, $english, 9000);
    seedPaidQuarter($student, $math, 9000);

    $before = (int) Reconciliation::query()->where('student_id', $student->id)->sum('paid_amount');
    expect($before)->toBe(18000);

    $this->actingAs($admin)
        ->put(route('fee-plans.update', $plan), [
            'academic_year_id' => $plan->academic_year_id,
            'grade_level_id' => $plan->grade_level_id,
            'course_ids' => [$english->id], // 拿掉數學
            'group_name' => $plan->group_name,
            'pricing_group' => $plan->pricing_group,
            'unit' => 'custom_period',
            'per_course_periods' => false,
            'allow_annual' => false,
            'list_price' => 0,
            'material_fee' => 0,
            'material_unit' => 'term',
            'sort_order' => 0,
            'is_active' => true,
            'periods' => [
                [
                    'course_id' => null,
                    'start_month' => 7,
                    'end_month' => 9,
                    'course_prices' => [
                        $english->id => [
                            'total_price' => 9999,
                            'double_price' => null,
                            'triple_price' => null,
                        ],
                    ],
                ],
            ],
            'bundles' => [],
        ])
        ->assertRedirect(route('fee-plans.index'));

    $after = (int) Reconciliation::query()->where('student_id', $student->id)->sum('paid_amount');
    expect($after)->toBe(18000)
        ->and(Reconciliation::query()->where('student_id', $student->id)->where('course_id', $math->id)->count())->toBe(3);
});

test('store rejects edit requote for paid period', function () {
    ['english' => $english, 'student' => $student, 'admin' => $admin] = paidLockCtx();
    seedPaidQuarter($student, $english, 9000);

    $sessions = BillingRenewal::defaultSessions($student, [$english->id], '2026-07-01', 'quarterly');

    $this->actingAs($admin)
        ->from(route('student-payments.index'))
        ->post(route('student-payments.store', $student), [
            'course_ids' => [$english->id],
            'pay_cycle' => 'quarterly',
            'sessions' => $sessions,
            'allowance' => 0,
            'start_date' => '2026-07-01',
            'edit_from' => '2026-7',
            'edit_to' => '2026-9',
        ])
        ->assertRedirect(route('student-payments.index'))
        ->assertSessionHasErrors('edit');

    expect((int) Reconciliation::query()->where('student_id', $student->id)->sum('paid_amount'))->toBe(9000);
});

test('period meta can update receipt without changing amounts', function () {
    ['english' => $english, 'student' => $student, 'admin' => $admin] = paidLockCtx();
    seedPaidQuarter($student, $english, 9000);

    $this->actingAs($admin)
        ->patch(route('student-payments.period-meta.update', $student), [
            'from' => '2026-7',
            'to' => '2026-9',
            'receipt_no' => 'NEW-001',
            'paid_date' => '2026-07-15',
        ])
        ->assertRedirect();

    $rows = Reconciliation::query()->where('student_id', $student->id)->get();
    expect((int) $rows->sum('paid_amount'))->toBe(9000)
        ->and($rows->every(fn (Reconciliation $r) => $r->receipt_no === 'NEW-001'))->toBeTrue()
        ->and($rows->first()->paid_date?->toDateString())->toBe('2026-07-15');
});

test('create with edit params redirects to period meta', function () {
    ['english' => $english, 'student' => $student, 'admin' => $admin] = paidLockCtx();
    seedPaidQuarter($student, $english, 9000);

    $this->actingAs($admin)
        ->get(route('student-payments.create', [
            'student_id' => $student->id,
            'edit_from' => '2026-7',
            'edit_to' => '2026-9',
        ]))
        ->assertRedirect(route('student-payments.period-meta.edit', [
            'student' => $student->id,
            'from' => '2026-7',
            'to' => '2026-9',
        ]));
});
