<?php

use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\FeePlan;
use App\Models\GradeLevel;
use App\Models\Student;
use App\Models\StudentAnnualIntent;
use App\Models\User;
use App\Support\PricingGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function promotionAnnualAdmin(): User
{
    return User::query()->create([
        'name' => '轉檔管理員',
        'email' => 'promo-annual-'.uniqid('', true).'@example.test',
        'password' => bcrypt('password'),
        'role' => 'admin',
        'is_active' => true,
    ]);
}

function promotionAnnualCtx(): array
{
    $year = AcademicYear::query()->create([
        'year_code' => '115',
        'name' => '115學年度',
        'is_current' => true,
    ]);
    $g8 = GradeLevel::query()->create([
        'name' => '國二',
        'code' => 8,
        'is_active' => true,
        'sort_order' => 8,
    ]);
    $g9 = GradeLevel::query()->create([
        'name' => '國三',
        'code' => 9,
        'is_active' => true,
        'sort_order' => 9,
    ]);
    $category = CourseCategory::query()->create([
        'name' => '國中課程',
        'sort_order' => 1,
    ]);
    $chinese = Course::query()->create([
        'course_category_id' => $category->id,
        'name' => '國文課',
        'color' => '#0d9488',
        'status' => 'active',
        'pricing_group' => PricingGroup::CORE,
        'weekdays' => [2],
        'schedules' => [],
    ]);
    $social = Course::query()->create([
        'course_category_id' => $category->id,
        'name' => '社會課',
        'color' => '#2563eb',
        'status' => 'active',
        'pricing_group' => PricingGroup::CORE,
        'weekdays' => [3],
        'schedules' => [],
    ]);

    $plan = FeePlan::query()->create([
        'academic_year_id' => $year->id,
        'grade_level_id' => $g9->id,
        'group_name' => '國社年繳',
        'pricing_group' => PricingGroup::CORE,
        'unit' => 'custom_period',
        'plan_kind' => 'annual_package',
        'allow_annual' => true,
        'annual_start_month' => 7,
        'annual_end_month' => 6,
        'package_total' => 37000,
        'list_price' => 0,
        'material_fee' => 0,
        'material_unit' => 'term',
        'sort_order' => 0,
        'is_active' => true,
    ]);
    $plan->courses()->sync([$chinese->id, $social->id]);

    $student = Student::query()->create([
        'name' => '轉檔測試生',
        'student_code' => '11508001',
        'academic_year_id' => $year->id,
        'grade_level_id' => $g8->id,
        'status' => 'active',
    ]);

    return compact('year', 'g8', 'g9', 'plan', 'student', 'chinese', 'social');
}

test('g8 to g9 preview includes annual packages dialog flag', function () {
    ['g8' => $g8, 'g9' => $g9, 'plan' => $plan] = promotionAnnualCtx();
    $admin = promotionAnnualAdmin();

    $this->actingAs($admin)
        ->get(route('student-promotions.index', [
            'from_grade_level_id' => $g8->id,
            'to_grade_level_id' => $g9->id,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('StudentPromotions/Index')
            ->where('needs_annual_dialog', true)
            ->has('annual_packages', 1)
            ->where('annual_packages.0.fee_plan_id', $plan->id)
            ->where('annual_packages.0.label', '國社年繳'));
});

test('g8 to g9 promote stores annual intent', function () {
    ['g8' => $g8, 'g9' => $g9, 'plan' => $plan, 'student' => $student] = promotionAnnualCtx();
    $admin = promotionAnnualAdmin();

    $this->actingAs($admin)
        ->post(route('student-promotions.store'), [
            'from_grade_level_id' => $g8->id,
            'to_grade_level_id' => $g9->id,
            'graduate' => false,
            'status' => 'active',
            'student_ids' => [$student->id],
            'annual_selections' => [
                [
                    'student_id' => $student->id,
                    'fee_plan_id' => $plan->id,
                    'note' => '家長已確認',
                ],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success');

    $student->refresh();
    expect((int) $student->grade_level_id)->toBe($g9->id);

    $intent = StudentAnnualIntent::query()->where('student_id', $student->id)->planned()->first();
    expect($intent)->not->toBeNull()
        ->and((int) $intent->fee_plan_id)->toBe($plan->id)
        ->and($intent->package_label)->toBe('國社年繳')
        ->and((int) $intent->package_total)->toBe(37000)
        ->and($intent->note)->toBe('家長已確認');
});

test('g8 to g9 promote can record no annual', function () {
    ['g8' => $g8, 'g9' => $g9, 'student' => $student] = promotionAnnualCtx();
    $admin = promotionAnnualAdmin();

    $this->actingAs($admin)
        ->post(route('student-promotions.store'), [
            'from_grade_level_id' => $g8->id,
            'to_grade_level_id' => $g9->id,
            'status' => 'active',
            'student_ids' => [$student->id],
            'annual_selections' => [
                [
                    'student_id' => $student->id,
                    'fee_plan_id' => null,
                    'note' => '',
                ],
            ],
        ])
        ->assertRedirect();

    $intent = StudentAnnualIntent::query()->where('student_id', $student->id)->planned()->first();
    expect($intent)->not->toBeNull()
        ->and($intent->fee_plan_id)->toBeNull()
        ->and($intent->package_label)->toBeNull();
});

test('non g8 to g9 promote does not write annual intent', function () {
    $year = AcademicYear::query()->create([
        'year_code' => '115',
        'name' => '115學年度',
        'is_current' => true,
    ]);
    $g7 = GradeLevel::query()->create([
        'name' => '國一',
        'code' => 7,
        'is_active' => true,
        'sort_order' => 7,
    ]);
    $g8 = GradeLevel::query()->create([
        'name' => '國二',
        'code' => 8,
        'is_active' => true,
        'sort_order' => 8,
    ]);
    $student = Student::query()->create([
        'name' => '國一測試',
        'student_code' => '11507001',
        'academic_year_id' => $year->id,
        'grade_level_id' => $g7->id,
        'status' => 'active',
    ]);
    $admin = promotionAnnualAdmin();

    $this->actingAs($admin)
        ->post(route('student-promotions.store'), [
            'from_grade_level_id' => $g7->id,
            'to_grade_level_id' => $g8->id,
            'status' => 'active',
            'student_ids' => [$student->id],
            'annual_selections' => [
                [
                    'student_id' => $student->id,
                    'fee_plan_id' => null,
                    'note' => 'should ignore',
                ],
            ],
        ])
        ->assertRedirect();

    expect(StudentAnnualIntent::query()->count())->toBe(0);
});
