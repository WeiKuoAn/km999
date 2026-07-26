<?php

use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\FeePlan;
use App\Models\GradeLevel;
use App\Models\Student;
use App\Models\User;
use App\Support\EnrollmentPricing;
use App\Support\PricingGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function feePlanTestContext(): array
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
        'name' => '國中課程',
        'sort_order' => 1,
    ]);

    return compact('year', 'grade', 'category');
}

function feePlanTestCourse(CourseCategory $category, string $name, string $group = PricingGroup::CORE): Course
{
    return Course::query()->create([
        'course_category_id' => $category->id,
        'name' => $name,
        'color' => '#0d9488',
        'status' => 'active',
        'pricing_group' => $group,
        'weekdays' => [2],
        'schedules' => [],
    ]);
}

function feePlanTestPlan(GradeLevel $grade, ?AcademicYear $year, array $overrides = []): FeePlan
{
    return FeePlan::query()->create(array_merge([
        'academic_year_id' => $year?->id,
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
    ], $overrides));
}

function feePlanTestPayload(AcademicYear $year, GradeLevel $grade, array $courseIds, array $overrides = []): array
{
    return array_merge([
        'academic_year_id' => $year->id,
        'grade_level_id' => $grade->id,
        'course_ids' => $courseIds,
        'group_name' => '核心科',
        'pricing_group' => PricingGroup::CORE,
        'unit' => 'month',
        'session_block_size' => null,
        'list_price' => 3600,
        'quarter_price' => null,
        'quarter_single_price' => 3200,
        'quarter_double_price' => 3000,
        'material_fee' => 0,
        'material_unit' => 'term',
        'sort_order' => 10,
        'is_active' => true,
    ], $overrides);
}

test('fee plan creation stores selected courses', function () {
    ['year' => $year, 'grade' => $grade, 'category' => $category] = feePlanTestContext();
    $course = feePlanTestCourse($category, '英文');

    $response = $this
        ->actingAs(User::factory()->create())
        ->post(route('fee-plans.store'), feePlanTestPayload($year, $grade, [$course->id]));

    $response->assertRedirect(route('fee-plans.index'));
    $plan = FeePlan::query()->sole();
    expect($plan->courses()->pluck('courses.id')->all())->toBe([$course->id]);
});

test('fee plan update replaces selected courses', function () {
    ['year' => $year, 'grade' => $grade, 'category' => $category] = feePlanTestContext();
    $english = feePlanTestCourse($category, '英文');
    $math = feePlanTestCourse($category, '數學');
    $plan = feePlanTestPlan($grade, $year);
    $plan->courses()->attach($english);

    $response = $this
        ->actingAs(User::factory()->create())
        ->put(route('fee-plans.update', $plan), feePlanTestPayload($year, $grade, [$math->id]));

    $response->assertRedirect(route('fee-plans.index'));
    expect($plan->courses()->pluck('courses.id')->all())->toBe([$math->id]);
});

test('fee plan requires at least one selected course', function () {
    ['year' => $year, 'grade' => $grade] = feePlanTestContext();

    $response = $this
        ->actingAs(User::factory()->create())
        ->post(route('fee-plans.store'), feePlanTestPayload($year, $grade, []));

    $response->assertSessionHasErrors('course_ids');
    expect(FeePlan::query()->count())->toBe(0);
});

test('same course cannot use two plans in the same academic year and grade', function () {
    ['year' => $year, 'grade' => $grade, 'category' => $category] = feePlanTestContext();
    $course = feePlanTestCourse($category, '英文');
    $plan = feePlanTestPlan($grade, $year);
    $plan->courses()->attach($course);

    $response = $this
        ->actingAs(User::factory()->create())
        ->post(route('fee-plans.store'), feePlanTestPayload($year, $grade, [$course->id], [
            'group_name' => '另一個價目',
        ]));

    $response->assertSessionHasErrors('course_ids');
    expect(FeePlan::query()->count())->toBe(1);
});

test('pricing only applies a plan to its selected courses', function () {
    ['year' => $year, 'grade' => $grade, 'category' => $category] = feePlanTestContext();
    $english = feePlanTestCourse($category, '英文');
    $math = feePlanTestCourse($category, '數學');
    $plan = feePlanTestPlan($grade, $year);
    $plan->courses()->attach($english);
    $student = Student::query()->create([
        'name' => '測試學生',
        'academic_year_id' => $year->id,
        'grade_level_id' => $grade->id,
    ]);

    $subjects = collect(EnrollmentPricing::subjectsForStudent($student->load(['academicYear', 'gradeLevel'])))
        ->keyBy('id');

    expect($subjects[$english->id]['fee_plan_id'])->toBe($plan->id)
        ->and($subjects[$math->id]['fee_plan_id'])->toBeNull();

    $quote = EnrollmentPricing::quote($student, [$english->id, $math->id], 'monthly', [
        ['date' => '2026-09-01', 'course_id' => $english->id],
        ['date' => '2026-09-01', 'course_id' => $math->id],
    ]);

    expect(array_column($quote['lines'], 'course_id'))->toBe([$english->id]);
});

test('removing a course binding stops that plan from applying', function () {
    ['year' => $year, 'grade' => $grade, 'category' => $category] = feePlanTestContext();
    $course = feePlanTestCourse($category, '英文');
    $plan = feePlanTestPlan($grade, $year);
    $plan->courses()->attach($course);
    $plan->courses()->detach($course);
    $student = Student::query()->create([
        'name' => '測試學生',
        'academic_year_id' => $year->id,
        'grade_level_id' => $grade->id,
    ]);

    $subject = collect(EnrollmentPricing::subjectsForStudent($student->load(['academicYear', 'gradeLevel'])))
        ->firstWhere('id', $course->id);

    expect($subject['fee_plan_id'])->toBeNull();
});

test('academic year plan takes priority over a shared plan', function () {
    ['year' => $year, 'grade' => $grade, 'category' => $category] = feePlanTestContext();
    $course = feePlanTestCourse($category, '英文');
    $shared = feePlanTestPlan($grade, null, ['list_price' => 3000, 'sort_order' => 1]);
    $specific = feePlanTestPlan($grade, $year, ['list_price' => 4200, 'sort_order' => 99]);
    $shared->courses()->attach($course);
    $specific->courses()->attach($course);
    $student = Student::query()->create([
        'name' => '測試學生',
        'academic_year_id' => $year->id,
        'grade_level_id' => $grade->id,
    ]);

    $subject = collect(EnrollmentPricing::subjectsForStudent($student->load(['academicYear', 'gradeLevel'])))
        ->firstWhere('id', $course->id);

    expect($subject['fee_plan_id'])->toBe($specific->id)
        ->and($subject['list'])->toBe(4200);
});

test('core double-course pricing remains available through pricing groups', function () {
    ['year' => $year, 'grade' => $grade, 'category' => $category] = feePlanTestContext();
    $english = feePlanTestCourse($category, '英文');
    $math = feePlanTestCourse($category, '數學');
    $plan = feePlanTestPlan($grade, $year);
    $plan->courses()->attach([$english->id, $math->id]);
    $student = Student::query()->create([
        'name' => '測試學生',
        'academic_year_id' => $year->id,
        'grade_level_id' => $grade->id,
    ]);

    $quote = EnrollmentPricing::quote($student, [$english->id, $math->id], 'quarterly', [
        ['date' => '2026-09-01', 'course_id' => $english->id],
        ['date' => '2026-09-02', 'course_id' => $math->id],
    ]);

    expect($quote['core_count'])->toBe(2)
        ->and($quote['tuition_total'])->toBe(6000);
});
