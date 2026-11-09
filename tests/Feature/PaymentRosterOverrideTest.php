<?php

use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\FeePlan;
use App\Models\GradeLevel;
use App\Models\PaymentRosterOverride;
use App\Models\Reconciliation;
use App\Models\Student;
use App\Models\User;
use App\Support\PaymentRosterBuilder;
use App\Support\PricingGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function rosterOverrideAdmin(): User
{
    return User::query()->create([
        'name' => '名單金額管理員',
        'email' => 'roster-override-'.uniqid('', true).'@example.test',
        'password' => bcrypt('password'),
        'role' => 'admin',
        'is_active' => true,
    ]);
}

function rosterOverrideCtx(): array
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
    $plan = FeePlan::query()->create([
        'academic_year_id' => $year->id,
        'grade_level_id' => $grade->id,
        'group_name' => '數學',
        'pricing_group' => PricingGroup::CORE,
        'unit' => 'month',
        'list_price' => 3200,
        'material_fee' => 0,
        'material_unit' => 'term',
        'sort_order' => 0,
        'is_active' => true,
    ]);
    $plan->courses()->sync([$math->id]);

    $student = Student::query()->create([
        'student_code' => 'OV001',
        'name' => '調價測試',
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

    return compact('year', 'grade', 'math', 'student');
}

test('roster override changes displayed fee without creating payment', function () {
    ['grade' => $grade, 'student' => $student] = rosterOverrideCtx();
    $admin = rosterOverrideAdmin();

    $before = PaymentRosterBuilder::build('', null, '2026-10', (int) $grade->id);
    $row = collect($before)->firstWhere('student_id', $student->id);
    expect($row)->not->toBeNull();
    $estimated = (int) $row['fee'];
    expect($estimated)->toBeGreaterThan(0);

    $this->actingAs($admin)
        ->post(route('payment-lists.fee-override.store'), [
            'student_id' => $student->id,
            'start_year' => (int) $row['start_year'],
            'start_month' => (int) $row['start_month'],
            'end_year' => (int) $row['end_year'],
            'end_month' => (int) $row['end_month'],
            'fee' => 19999,
            'original_fee' => $estimated,
            'note' => '兄弟優惠',
            'grade_level_id' => $grade->id,
            'period_start' => '2026-10',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(PaymentRosterOverride::query()->count())->toBe(1);
    expect(Reconciliation::query()->where('status', 'unpaid')->count())->toBe(0);

    $after = PaymentRosterBuilder::build('', null, '2026-10', (int) $grade->id);
    $adjusted = collect($after)->firstWhere('student_id', $student->id);
    expect($adjusted)->not->toBeNull()
        ->and((int) $adjusted['fee'])->toBe(19999)
        ->and($adjusted['fee_overridden'])->toBeTrue()
        ->and((int) $adjusted['estimated_fee'])->toBe($estimated)
        ->and((string) $adjusted['note'])->toContain('兄弟優惠');
});

test('roster override course months update notice study period', function () {
    ['grade' => $grade, 'math' => $math, 'student' => $student] = rosterOverrideCtx();

    $row = collect(PaymentRosterBuilder::build('', null, '2026-10', (int) $grade->id))
        ->firstWhere('student_id', $student->id);
    expect($row)->not->toBeNull();

    $startY = (int) $row['start_year'];
    $startM = (int) $row['start_month'];
    $cid = (string) $math->id;

    PaymentRosterOverride::query()->create([
        'student_id' => $student->id,
        'start_year' => $startY,
        'start_month' => $startM,
        'end_year' => 2027,
        'end_month' => 2,
        'course_ids' => [(int) $math->id],
        'course_months' => [
            $cid => ['2026-12', '2027-1', '2027-2'],
        ],
        'fee' => 17100,
        'original_fee' => (int) $row['fee'],
    ]);

    $adjusted = collect(PaymentRosterBuilder::build('', null, '2026-10', (int) $grade->id))
        ->firstWhere('student_id', $student->id);

    expect($adjusted)->not->toBeNull()
        ->and((int) $adjusted['end_year'])->toBe(2027)
        ->and((int) $adjusted['end_month'])->toBe(2)
        ->and((int) $adjusted['notice']['start_year'])->toBe(2026)
        ->and((int) $adjusted['notice']['start_month'])->toBe(12)
        ->and((int) $adjusted['notice']['end_year'])->toBe(2027)
        ->and((int) $adjusted['notice']['end_month'])->toBe(2);

    // 確保 PDF normalize 會用到更新後年月
    $ref = new ReflectionClass(\App\Support\PaymentNoticePdf::class);
    $method = $ref->getMethod('normalizeNotice');
    $method->setAccessible(true);
    $normalized = $method->invoke(null, $adjusted['notice']);
    expect((string) $normalized['period_label'])->toContain('2 月');
});

test('clear roster override restores estimate', function () {
    ['grade' => $grade, 'student' => $student] = rosterOverrideCtx();
    $admin = rosterOverrideAdmin();

    $row = collect(PaymentRosterBuilder::build('', null, '2026-10', (int) $grade->id))
        ->firstWhere('student_id', $student->id);
    expect($row)->not->toBeNull();

    PaymentRosterOverride::query()->create([
        'student_id' => $student->id,
        'start_year' => (int) $row['start_year'],
        'start_month' => (int) $row['start_month'],
        'fee' => 1111,
        'original_fee' => (int) $row['fee'],
    ]);

    $this->actingAs($admin)
        ->delete(route('payment-lists.fee-override.destroy'), [
            'student_id' => $student->id,
            'start_year' => (int) $row['start_year'],
            'start_month' => (int) $row['start_month'],
            'grade_level_id' => $grade->id,
            'period_start' => '2026-10',
        ])
        ->assertRedirect();

    expect(PaymentRosterOverride::query()->count())->toBe(0);
    $restored = collect(PaymentRosterBuilder::build('', null, '2026-10', (int) $grade->id))
        ->firstWhere('student_id', $student->id);
    expect($restored)->not->toBeNull()
        ->and((bool) ($restored['fee_overridden'] ?? false))->toBeFalse()
        ->and((int) $restored['fee'])->toBe((int) $row['fee']);
});
