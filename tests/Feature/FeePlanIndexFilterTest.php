<?php

use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\FeePlan;
use App\Models\GradeLevel;
use App\Models\User;
use App\Support\PricingGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('fee plans index filters by grade kind year and keyword', function () {
    $admin = User::query()->create([
        'name' => '收費設定管理員',
        'email' => 'fee-filter-'.uniqid('', true).'@example.test',
        'password' => bcrypt('password'),
        'role' => 'admin',
        'is_active' => true,
    ]);
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
    $math = Course::query()->create([
        'course_category_id' => $category->id,
        'name' => '數學課',
        'color' => '#2563eb',
        'status' => 'active',
        'pricing_group' => PricingGroup::CORE,
        'weekdays' => [2],
        'schedules' => [],
    ]);

    $period = FeePlan::query()->create([
        'academic_year_id' => $year->id,
        'grade_level_id' => $g8->id,
        'group_name' => '國二期別',
        'pricing_group' => PricingGroup::CORE,
        'unit' => 'month',
        'plan_kind' => 'period',
        'list_price' => 3200,
        'material_fee' => 0,
        'material_unit' => 'term',
        'sort_order' => 0,
        'is_active' => true,
    ]);
    $period->courses()->sync([$math->id]);

    $annual = FeePlan::query()->create([
        'academic_year_id' => $year->id,
        'grade_level_id' => $g9->id,
        'group_name' => '科研科全科',
        'pricing_group' => PricingGroup::CORE,
        'unit' => 'custom_period',
        'plan_kind' => 'annual_package',
        'allow_annual' => true,
        'annual_start_month' => 3,
        'annual_end_month' => 8,
        'package_total' => 136000,
        'list_price' => 0,
        'material_fee' => 0,
        'material_unit' => 'term',
        'sort_order' => 1,
        'is_active' => true,
    ]);
    $annual->courses()->sync([$math->id]);

    $this->actingAs($admin)
        ->get(route('fee-plans.index', [
            'grade' => '國三',
            'plan_kind' => 'annual_package',
            'academic_year_id' => (string) $year->id,
            'q' => '科研',
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('FeePlans/Index')
            ->has('plans.data', 1)
            ->where('plans.data.0.id', $annual->id)
            ->where('filters.grade', '國三')
            ->where('filters.plan_kind', 'annual_package')
            ->where('gradeOptions', ['全部', '全年級', '國二', '國三']));
});
