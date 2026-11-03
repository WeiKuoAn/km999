<?php

use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\FeePlan;
use App\Models\GradeLevel;
use App\Models\Student;
use App\Models\User;
use App\Support\Grade9AnnualPackage;
use App\Support\PricingGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function g9PkgCtx(): array
{
    $year = AcademicYear::query()->create([
        'year_code' => '115',
        'name' => '115學年度',
        'is_current' => true,
    ]);
    $grade = GradeLevel::query()->create([
        'name' => '國三',
        'code' => 9,
        'is_active' => true,
        'sort_order' => 9,
    ]);
    $category = CourseCategory::query()->create([
        'name' => '國中課程',
        'sort_order' => 1,
    ]);

    $make = function (string $name) use ($category): Course {
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

    $chinese = $make('國文課');
    $english = $make('英文課');
    $math = $make('數學課');
    $science = $make('理化課');
    $scienceResearch = $make('理化科研');
    $social = $make('社會課');

    $student = Student::query()->create([
        'name' => '國三測試',
        'academic_year_id' => $year->id,
        'grade_level_id' => $grade->id,
        'status' => 'active',
    ]);

    $mkPackage = function (string $name, array $courseIds, int $total, int $start, int $end) use ($year, $grade): FeePlan {
        $plan = FeePlan::query()->create([
            'academic_year_id' => $year->id,
            'grade_level_id' => $grade->id,
            'group_name' => $name,
            'pricing_group' => PricingGroup::CORE,
            'unit' => 'custom_period',
            'plan_kind' => 'annual_package',
            'allow_annual' => true,
            'annual_start_month' => $start,
            'annual_end_month' => $end,
            'package_total' => $total,
            'list_price' => 0,
            'material_fee' => 0,
            'material_unit' => 'term',
            'sort_order' => 0,
            'is_active' => true,
        ]);
        $plan->courses()->sync($courseIds);

        return $plan;
    };

    $mkPackage('全科年繳', [$chinese->id, $english->id, $math->id, $science->id, $social->id], 120000, 7, 12);
    $mkPackage('全科科研年繳', [$chinese->id, $english->id, $math->id, $scienceResearch->id, $social->id], 136000, 7, 12);
    $mkPackage('國社年繳', [$chinese->id, $social->id], 37000, 7, 6);

    return compact(
        'student',
        'chinese',
        'english',
        'math',
        'science',
        'scienceResearch',
        'social',
        'year',
        'grade',
    );
}

test('annual packages match exact course sets from fee plans', function () {
    $ctx = g9PkgCtx();
    $student = $ctx['student']->load('gradeLevel');

    $full = Grade9AnnualPackage::matchPackage(
        $student,
        [$ctx['chinese']->id, $ctx['english']->id, $ctx['math']->id, $ctx['science']->id, $ctx['social']->id],
        'annual',
        '2026-07-01',
    );
    expect($full)->not->toBeNull()
        ->and($full['label'])->toBe('全科年繳')
        ->and($full['total'])->toBe(120_000);

    $research = Grade9AnnualPackage::matchPackage(
        $student,
        [$ctx['chinese']->id, $ctx['english']->id, $ctx['math']->id, $ctx['scienceResearch']->id, $ctx['social']->id],
        'annual',
        '2026-07-01',
    );
    expect($research)->not->toBeNull()
        ->and($research['label'])->toBe('全科科研年繳')
        ->and($research['total'])->toBe(136_000);

    $guoshe = Grade9AnnualPackage::matchPackage(
        $student,
        [$ctx['chinese']->id, $ctx['social']->id],
        'annual',
        '2026-07-01',
    );
    expect($guoshe)->not->toBeNull()
        ->and($guoshe['label'])->toBe('國社年繳')
        ->and($guoshe['total'])->toBe(37_000);
});

test('annual package quote uses package total', function () {
    $ctx = g9PkgCtx();
    $ids = [$ctx['chinese']->id, $ctx['social']->id];
    $quote = Grade9AnnualPackage::quote(
        $ctx['student']->load('gradeLevel'),
        $ids,
        '2026-07-01',
    );

    expect((int) $quote['grand_total'])->toBe(37_000)
        ->and($quote['package']['label'])->toBe('國社年繳');
});

test('fee plan can store annual package sharing courses with period plan', function () {
    $ctx = g9PkgCtx();
    $admin = User::query()->create([
        'name' => '管理員',
        'email' => 'pkg-'.uniqid('', true).'@example.test',
        'password' => bcrypt('password'),
        'role' => 'admin',
        'is_active' => true,
    ]);

    // 期別設定：國／社
    $this->actingAs($admin)
        ->post(route('fee-plans.store'), [
            'academic_year_id' => $ctx['year']->id,
            'grade_level_id' => $ctx['grade']->id,
            'course_ids' => [$ctx['chinese']->id, $ctx['social']->id],
            'group_name' => '國／社期別',
            'pricing_group' => PricingGroup::CORE,
            'unit' => 'custom_period',
            'plan_kind' => 'period',
            'per_course_periods' => false,
            'allow_annual' => false,
            'list_price' => 0,
            'material_fee' => 0,
            'material_unit' => 'term',
            'sort_order' => 0,
            'is_active' => true,
            'periods' => [[
                'course_id' => null,
                'start_month' => 7,
                'end_month' => 9,
                'course_prices' => [
                    $ctx['chinese']->id => ['total_price' => 1800],
                    $ctx['social']->id => ['total_price' => 1800],
                ],
            ]],
            'bundles' => [],
        ])
        ->assertRedirect(route('fee-plans.index'));

    // 年繳方案可再掛同樣科目
    $this->actingAs($admin)
        ->post(route('fee-plans.store'), [
            'academic_year_id' => $ctx['year']->id,
            'grade_level_id' => $ctx['grade']->id,
            'course_ids' => [$ctx['chinese']->id, $ctx['social']->id],
            'group_name' => '國社年繳測試',
            'pricing_group' => PricingGroup::CORE,
            'unit' => 'custom_period',
            'plan_kind' => 'annual_package',
            'package_total' => 37000,
            'annual_start_month' => 7,
            'annual_end_month' => 6,
            'list_price' => 0,
            'material_fee' => 0,
            'material_unit' => 'term',
            'sort_order' => 0,
            'is_active' => true,
            'periods' => [],
            'bundles' => [],
        ])
        ->assertRedirect(route('fee-plans.index'));

    expect(FeePlan::query()->where('plan_kind', 'annual_package')->where('group_name', '國社年繳測試')->exists())->toBeTrue();
});
