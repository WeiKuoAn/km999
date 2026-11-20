<?php

use App\Models\AcademicYear;
use App\Models\AnnualDepositRecord;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\GradeLevel;
use App\Models\Reconciliation;
use App\Models\Student;
use App\Models\User;
use App\Support\PricingGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function financialDeleteCtx(): array
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
        'name' => '數學',
        'sort_order' => 1,
    ]);
    $course = Course::query()->create([
        'course_category_id' => $category->id,
        'name' => '數學課',
        'color' => '#0d9488',
        'status' => 'active',
        'pricing_group' => PricingGroup::CORE,
        'weekdays' => [2],
        'schedules' => [],
    ]);
    $admin = User::query()->create([
        'name' => '管理員',
        'email' => 'fin-del-'.uniqid('', true).'@example.test',
        'password' => bcrypt('password'),
        'role' => 'admin',
        'is_active' => true,
    ]);
    $student = Student::query()->create([
        'student_code' => 'D'.uniqid(),
        'academic_year_id' => $year->id,
        'grade_level_id' => $grade->id,
        'name' => '帳務保護生',
        'status' => 'active',
    ]);

    return compact('year', 'grade', 'category', 'course', 'admin', 'student');
}

test('student without financial records can be deleted', function () {
    $ctx = financialDeleteCtx();

    $this->actingAs($ctx['admin'])
        ->delete(route('students.destroy', $ctx['student']))
        ->assertRedirect(route('students.index'));

    expect(Student::query()->whereKey($ctx['student']->id)->exists())->toBeFalse();
});

test('deleting student with paid reconciliation keeps revenue snapshot', function () {
    $ctx = financialDeleteCtx();
    $code = $ctx['student']->student_code;

    $reco = Reconciliation::query()->create([
        'student_id' => $ctx['student']->id,
        'classroom_id' => null,
        'course_id' => $ctx['course']->id,
        'billing_year' => 2026,
        'billing_month' => 11,
        'expected_amount' => 3600,
        'paid_amount' => 3600,
        'paid_date' => '2026-11-20',
        'status' => 'paid',
    ]);

    expect($reco->fresh()->student_name)->toBe('帳務保護生')
        ->and($reco->fresh()->student_code)->toBe($code)
        ->and($reco->fresh()->grade_name)->toBe('國二');

    $this->actingAs($ctx['admin'])
        ->delete(route('students.destroy', $ctx['student']))
        ->assertRedirect(route('students.index'));

    expect(Student::query()->whereKey($ctx['student']->id)->exists())->toBeFalse();

    $fresh = $reco->fresh();
    expect($fresh)->not->toBeNull()
        ->and($fresh->student_id)->toBeNull()
        ->and((int) $fresh->paid_amount)->toBe(3600)
        ->and($fresh->student_name)->toBe('帳務保護生')
        ->and($fresh->student_code)->toBe($code)
        ->and($fresh->status)->toBe('paid');

    // 營收加總不依賴 students join（刪學生後仍計入）
    $revenue = (int) Reconciliation::query()
        ->where('status', 'paid')
        ->where('billing_year', 2026)
        ->where('billing_month', 11)
        ->sum('paid_amount');

    expect($revenue)->toBe(3600);
});

test('deleting student with annual deposit keeps deposit snapshot', function () {
    $ctx = financialDeleteCtx();

    $deposit = AnnualDepositRecord::query()->create([
        'student_id' => $ctx['student']->id,
        'fee_plan_id' => null,
        'fee_label' => '年繳訂金',
        'amount' => 1000,
        'paid_on' => '2026-11-20',
        'note' => null,
        'recorded_by_user_id' => $ctx['admin']->id,
    ]);

    $this->actingAs($ctx['admin'])
        ->delete(route('students.destroy', $ctx['student']))
        ->assertRedirect(route('students.index'));

    $fresh = $deposit->fresh();
    expect($fresh)->not->toBeNull()
        ->and($fresh->student_id)->toBeNull()
        ->and((int) $fresh->amount)->toBe(1000)
        ->and($fresh->student_name)->toBe('帳務保護生');
});
