<?php

use App\Models\AcademicYear;
use App\Models\AnnualDepositRecord;
use App\Models\FeePlan;
use App\Models\GradeLevel;
use App\Models\Student;
use App\Models\User;
use App\Support\PricingGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function depositCtx(): array
{
    $year = AcademicYear::query()->create([
        'year_code' => '115',
        'name' => '115學年度',
        'is_current' => true,
    ]);
    $grade2 = GradeLevel::query()->create([
        'name' => '國二',
        'code' => 8,
        'is_active' => true,
        'sort_order' => 8,
    ]);
    $grade3 = GradeLevel::query()->create([
        'name' => '國三',
        'code' => 9,
        'is_active' => true,
        'sort_order' => 9,
    ]);
    $student = Student::query()->create([
        'student_code' => 'D'.uniqid(),
        'academic_year_id' => $year->id,
        'grade_level_id' => $grade2->id,
        'name' => '訂金測試生',
        'status' => 'active',
    ]);
    // 實務上年繳方案多掛國三；國二學生繳訂金時仍應可選。
    $plan = FeePlan::query()->create([
        'academic_year_id' => $year->id,
        'grade_level_id' => $grade3->id,
        'group_name' => '科研科全科',
        'pricing_group' => PricingGroup::CORE,
        'unit' => 'custom_period',
        'plan_kind' => 'annual_package',
        'allow_annual' => true,
        'annual_start_month' => 3,
        'annual_end_month' => 5,
        'annual_end_next_year' => true,
        'package_total' => 136000,
        'list_price' => 0,
        'material_fee' => 0,
        'material_unit' => 'term',
        'sort_order' => 0,
        'is_active' => true,
    ]);
    $admin = User::query()->create([
        'name' => '管理員',
        'email' => 'deposit-'.uniqid('', true).'@example.test',
        'password' => bcrypt('password'),
        'role' => 'admin',
        'is_active' => true,
    ]);

    return compact('year', 'grade2', 'grade3', 'student', 'plan', 'admin');
}

test('admin can view annual deposit records index', function () {
    $ctx = depositCtx();

    $this->actingAs($ctx['admin'])
        ->get(route('annual-deposit-records.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('AnnualDepositRecords/Index')
            ->has('records')
            ->has('filters.month')
            ->has('feePlans', 1)
            ->where('feePlans.0.id', $ctx['plan']->id)
            ->where('feePlans.0.label', '科研科全科')
            ->where('month_total', 0));
});

test('admin can list grade 2 students for deposit create', function () {
    $ctx = depositCtx();
    Student::query()->create([
        'student_code' => 'D'.uniqid(),
        'academic_year_id' => $ctx['year']->id,
        'grade_level_id' => $ctx['grade3']->id,
        'name' => '國三不該出現',
        'status' => 'active',
    ]);

    $this->actingAs($ctx['admin'])
        ->getJson(route('annual-deposit-records.students'))
        ->assertOk()
        ->assertJsonPath('grade_name', '國二')
        ->assertJsonCount(1, 'students')
        ->assertJsonPath('students.0.id', $ctx['student']->id)
        ->assertJsonPath('paid_count', 0);
});

test('deposit student list hides paid by default and can include them', function () {
    $ctx = depositCtx();
    $unpaid = Student::query()->create([
        'student_code' => 'D'.uniqid(),
        'academic_year_id' => $ctx['year']->id,
        'grade_level_id' => $ctx['grade2']->id,
        'name' => '尚未繳訂金',
        'status' => 'active',
    ]);

    AnnualDepositRecord::query()->create([
        'student_id' => $ctx['student']->id,
        'fee_plan_id' => $ctx['plan']->id,
        'fee_label' => '科研科全科訂金',
        'amount' => 1000,
        'paid_on' => '2026-11-20',
        'note' => null,
        'recorded_by_user_id' => $ctx['admin']->id,
    ]);

    $this->actingAs($ctx['admin'])
        ->getJson(route('annual-deposit-records.students'))
        ->assertOk()
        ->assertJsonPath('paid_count', 1)
        ->assertJsonCount(1, 'students')
        ->assertJsonPath('students.0.id', $unpaid->id)
        ->assertJsonPath('students.0.paid', false);

    $this->actingAs($ctx['admin'])
        ->getJson(route('annual-deposit-records.students', ['include_paid' => 1]))
        ->assertOk()
        ->assertJsonCount(2, 'students')
        ->assertJsonFragment([
            'id' => $ctx['student']->id,
            'paid' => true,
        ]);
});

test('admin can store multiple annual deposit records and view monthly total', function () {
    $ctx = depositCtx();
    $other = Student::query()->create([
        'student_code' => 'D'.uniqid(),
        'academic_year_id' => $ctx['year']->id,
        'grade_level_id' => $ctx['grade2']->id,
        'name' => '第二位',
        'status' => 'active',
    ]);

    $this->actingAs($ctx['admin'])
        ->post(route('annual-deposit-records.store'), [
            'paid_on' => '2026-03-19',
            'student_ids' => [$ctx['student']->id, $other->id],
            'fee_plan_id' => $ctx['plan']->id,
            'fee_label' => '科研科全科訂金',
            'amount' => 1000,
            'note' => '現金',
        ])
        ->assertRedirect(route('annual-deposit-records.index', [
            'month' => '2026-03',
        ]));

    AnnualDepositRecord::query()->create([
        'student_id' => $ctx['student']->id,
        'fee_plan_id' => $ctx['plan']->id,
        'fee_label' => '同月另一天',
        'amount' => 500,
        'paid_on' => '2026-03-01',
        'note' => null,
        'recorded_by_user_id' => $ctx['admin']->id,
    ]);

    AnnualDepositRecord::query()->create([
        'student_id' => $ctx['student']->id,
        'fee_plan_id' => null,
        'fee_label' => '下個月不計',
        'amount' => 999,
        'paid_on' => '2026-04-01',
        'note' => null,
        'recorded_by_user_id' => $ctx['admin']->id,
    ]);

    expect(AnnualDepositRecord::query()->count())->toBe(4);

    $this->actingAs($ctx['admin'])
        ->get(route('annual-deposit-records.index', ['month' => '2026-03']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('filters.month', '2026-03')
            ->where('month_total', 2500)
            ->has('records.data', 3));
});

test('store rejects non grade 2 students', function () {
    $ctx = depositCtx();
    $grade3Student = Student::query()->create([
        'student_code' => 'D'.uniqid(),
        'academic_year_id' => $ctx['year']->id,
        'grade_level_id' => $ctx['grade3']->id,
        'name' => '國三生',
        'status' => 'active',
    ]);

    $this->actingAs($ctx['admin'])
        ->from(route('annual-deposit-records.index'))
        ->post(route('annual-deposit-records.store'), [
            'paid_on' => '2026-03-19',
            'student_ids' => [$grade3Student->id],
            'fee_plan_id' => null,
            'fee_label' => '訂金',
            'amount' => 500,
            'note' => null,
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('student_ids');

    expect(AnnualDepositRecord::query()->count())->toBe(0);
});

test('admin can update and delete annual deposit record', function () {
    $ctx = depositCtx();
    $record = AnnualDepositRecord::query()->create([
        'student_id' => $ctx['student']->id,
        'fee_plan_id' => $ctx['plan']->id,
        'fee_label' => '科研訂金',
        'amount' => 1000,
        'paid_on' => '2026-03-19',
        'note' => null,
        'recorded_by_user_id' => $ctx['admin']->id,
    ]);

    $this->actingAs($ctx['admin'])
        ->put(route('annual-deposit-records.update', $record), [
            'student_id' => $ctx['student']->id,
            'fee_plan_id' => $ctx['plan']->id,
            'fee_label' => '科研訂金（改）',
            'amount' => 2000,
            'paid_on' => '2026-03-19',
            'note' => '轉帳',
        ])
        ->assertRedirect(route('annual-deposit-records.index', [
            'month' => '2026-03',
        ]));

    expect($record->fresh()->amount)->toBe(2000)
        ->and($record->fresh()->fee_label)->toBe('科研訂金（改）');

    $this->actingAs($ctx['admin'])
        ->delete(route('annual-deposit-records.destroy', $record))
        ->assertRedirect(route('annual-deposit-records.index', [
            'month' => '2026-03',
        ]));

    expect(AnnualDepositRecord::query()->whereKey($record->id)->exists())->toBeFalse();
});
