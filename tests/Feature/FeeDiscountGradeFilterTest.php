<?php

use App\Models\AcademicYear;
use App\Models\FeeDiscount;
use App\Models\GradeLevel;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function feeDiscountGradeCtx(): array
{
    $year = AcademicYear::query()->create([
        'year_code' => '115',
        'name' => '115學年度',
        'is_current' => true,
    ]);
    $g1 = GradeLevel::query()->create([
        'name' => '國一',
        'code' => 7,
        'is_active' => true,
        'sort_order' => 7,
    ]);
    $g2 = GradeLevel::query()->create([
        'name' => '國二',
        'code' => 8,
        'is_active' => true,
        'sort_order' => 8,
    ]);

    $studentG1 = Student::query()->create([
        'student_code' => '11507099',
        'name' => '國一學生',
        'academic_year_id' => $year->id,
        'grade_level_id' => $g1->id,
        'status' => 'active',
    ]);

    $allGrades = FeeDiscount::query()->create([
        'name' => '全校早鳥',
        'grade_level_id' => null,
        'type' => FeeDiscount::TYPE_AMOUNT,
        'value' => 500,
        'is_active' => true,
        'sort_order' => 1,
    ]);
    $g2Only = FeeDiscount::query()->create([
        'name' => '國二 數學早鳥優惠',
        'grade_level_id' => $g2->id,
        'type' => FeeDiscount::TYPE_AMOUNT,
        'value' => 1000,
        'is_active' => true,
        'sort_order' => 2,
    ]);
    $g1Only = FeeDiscount::query()->create([
        'name' => '國一早鳥',
        'grade_level_id' => $g1->id,
        'type' => FeeDiscount::TYPE_AMOUNT,
        'value' => 800,
        'is_active' => true,
        'sort_order' => 3,
    ]);

    $admin = User::query()->create([
        'name' => '管理員',
        'email' => 'disc-'.uniqid('', true).'@example.test',
        'password' => bcrypt('password'),
        'role' => 'admin',
        'is_active' => true,
    ]);

    return compact('year', 'g1', 'g2', 'studentG1', 'allGrades', 'g2Only', 'g1Only', 'admin');
}

test('payment create only lists discounts for student grade or all grades', function () {
    $ctx = feeDiscountGradeCtx();

    $this->actingAs($ctx['admin'])
        ->get(route('student-payments.create', ['student_id' => $ctx['studentG1']->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('fee_discounts')
            ->where('fee_discounts', function ($rows) use ($ctx) {
                $ids = collect($rows)->pluck('id')->all();

                return in_array($ctx['allGrades']->id, $ids, true)
                    && in_array($ctx['g1Only']->id, $ids, true)
                    && ! in_array($ctx['g2Only']->id, $ids, true);
            })
        );
});

test('can create discount with grade level', function () {
    $ctx = feeDiscountGradeCtx();

    $this->actingAs($ctx['admin'])
        ->post(route('fee-discounts.store'), [
            'name' => '國二專屬',
            'grade_level_id' => $ctx['g2']->id,
            'type' => 'amount',
            'value' => 1200,
            'is_active' => true,
            'sort_order' => 0,
        ])
        ->assertRedirect(route('fee-discounts.index'));

    $row = FeeDiscount::query()->where('name', '國二專屬')->first();
    expect($row)->not->toBeNull()
        ->and((int) $row->grade_level_id)->toBe((int) $ctx['g2']->id);
});

test('forGrade scope includes null and matching grade', function () {
    $ctx = feeDiscountGradeCtx();

    $ids = FeeDiscount::query()
        ->forGrade((int) $ctx['g1']->id)
        ->pluck('id')
        ->all();

    expect($ids)->toContain($ctx['allGrades']->id)
        ->and($ids)->toContain($ctx['g1Only']->id)
        ->and($ids)->not->toContain($ctx['g2Only']->id);
});
