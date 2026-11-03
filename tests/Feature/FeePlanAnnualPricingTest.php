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

function annualPlanCtx(): array
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
    $course = Course::query()->create([
        'course_category_id' => $category->id,
        'name' => '科研數學',
        'color' => '#0d9488',
        'status' => 'active',
        'pricing_group' => PricingGroup::CORE,
        'weekdays' => [2],
        'schedules' => [],
    ]);

    return compact('year', 'grade', 'category', 'course');
}

function annualPlanAdmin(): User
{
    return User::query()->create([
        'name' => '管理員',
        'email' => 'annual-'.uniqid('', true).'@example.test',
        'password' => bcrypt('password'),
        'role' => 'admin',
        'is_active' => true,
    ]);
}

function annualPlanPayload(AcademicYear $year, GradeLevel $grade, Course $course, array $overrides = []): array
{
    return array_merge([
        'academic_year_id' => $year->id,
        'grade_level_id' => $grade->id,
        'course_ids' => [$course->id],
        'group_name' => '數學科研',
        'pricing_group' => PricingGroup::CORE,
        'unit' => 'custom_period',
        'per_course_periods' => false,
        'allow_annual' => true,
        'annual_start_month' => 7,
        'annual_end_month' => 6,
        'annual_prices' => [
            $course->id => 40000,
        ],
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
                    $course->id => [
                        'total_price' => 3800,
                        'double_price' => null,
                        'triple_price' => null,
                    ],
                ],
            ],
            [
                'course_id' => null,
                'start_month' => 10,
                'end_month' => 12,
                'course_prices' => [
                    $course->id => [
                        'total_price' => 3800,
                        'double_price' => null,
                        'triple_price' => null,
                    ],
                ],
            ],
            [
                'course_id' => null,
                'start_month' => 1,
                'end_month' => 3,
                'course_prices' => [
                    $course->id => [
                        'total_price' => 3800,
                        'double_price' => null,
                        'triple_price' => null,
                    ],
                ],
            ],
            [
                'course_id' => null,
                'start_month' => 4,
                'end_month' => 6,
                'course_prices' => [
                    $course->id => [
                        'total_price' => 3800,
                        'double_price' => null,
                        'triple_price' => null,
                    ],
                ],
            ],
        ],
        'bundles' => [],
    ], $overrides);
}

test('fee plan can store annual preferential prices', function () {
    ['year' => $year, 'grade' => $grade, 'course' => $course] = annualPlanCtx();

    $this->actingAs(annualPlanAdmin())
        ->post(route('fee-plans.store'), annualPlanPayload($year, $grade, $course))
        ->assertRedirect(route('fee-plans.index'));

    $plan = FeePlan::query()->with('annualPrices')->sole();
    expect($plan->allow_annual)->toBeTrue()
        ->and((int) $plan->annual_start_month)->toBe(7)
        ->and((int) $plan->annual_end_month)->toBe(6)
        ->and($plan->annualPrices)->toHaveCount(1)
        ->and((int) $plan->annualPrices->first()->amount)->toBe(40000);
});

test('allow annual requires amount for every course', function () {
    ['year' => $year, 'grade' => $grade, 'course' => $course] = annualPlanCtx();

    $this->actingAs(annualPlanAdmin())
        ->post(route('fee-plans.store'), annualPlanPayload($year, $grade, $course, [
            'annual_prices' => [],
        ]))
        ->assertSessionHasErrors('annual_prices.'.$course->id);
});

test('annual pay cycle with july start uses annual preferential total', function () {
    ['year' => $year, 'grade' => $grade, 'course' => $course] = annualPlanCtx();

    $this->actingAs(annualPlanAdmin())
        ->post(route('fee-plans.store'), annualPlanPayload($year, $grade, $course));

    $student = Student::query()->create([
        'name' => '年繳測試生',
        'academic_year_id' => $year->id,
        'grade_level_id' => $grade->id,
        'status' => 'active',
    ]);

    $months = [];
    $y = 2026;
    $m = 7;
    for ($i = 0; $i < 12; $i++) {
        $months[] = ['y' => $y, 'm' => $m];
        $m++;
        if ($m > 12) {
            $m = 1;
            $y++;
        }
    }

    $sessions = array_map(
        fn (array $month): array => [
            'date' => sprintf('%04d-%02d-01', $month['y'], $month['m']),
            'course_id' => $course->id,
        ],
        $months,
    );

    $quote = EnrollmentPricing::quote(
        $student->load(['academicYear', 'gradeLevel']),
        [$course->id],
        'annual',
        $sessions,
        courseMonths: [
            $course->id => array_map(
                fn (array $month): string => $month['y'].'-'.$month['m'],
                $months,
            ),
        ],
    );

    expect((int) $quote['tuition_total'])->toBe(40000)
        ->and((int) $quote['lines'][0]['tuition'])->toBe(40000);
});

test('quarterly cycle ignores annual preferential price', function () {
    ['year' => $year, 'grade' => $grade, 'course' => $course] = annualPlanCtx();

    $this->actingAs(annualPlanAdmin())
        ->post(route('fee-plans.store'), annualPlanPayload($year, $grade, $course));

    $student = Student::query()->create([
        'name' => '季繳測試生',
        'academic_year_id' => $year->id,
        'grade_level_id' => $grade->id,
        'status' => 'active',
    ]);

    $quote = EnrollmentPricing::quote(
        $student->load(['academicYear', 'gradeLevel']),
        [$course->id],
        'quarterly',
        [
            ['date' => '2026-07-01', 'course_id' => $course->id],
            ['date' => '2026-08-01', 'course_id' => $course->id],
            ['date' => '2026-09-01', 'course_id' => $course->id],
        ],
        courseMonths: [
            $course->id => ['2026-7', '2026-8', '2026-9'],
        ],
    );

    // 7–9 月：3800 × 3
    expect((int) $quote['tuition_total'])->toBe(11400);
});

test('matchesAnnualMonthSpan supports custom ranges', function () {
    $julyToJune = [];
    $y = 2026;
    $m = 7;
    for ($i = 0; $i < 12; $i++) {
        $julyToJune[] = ['y' => $y, 'm' => $m];
        $m++;
        if ($m > 12) {
            $m = 1;
            $y++;
        }
    }

    $septToJune = array_values(array_filter(
        $julyToJune,
        fn (array $row): bool => ! in_array((int) $row['m'], [7, 8], true)
    ));

    expect(EnrollmentPricing::matchesAnnualMonthSpan($julyToJune, 7, 6))->toBeTrue()
        ->and(EnrollmentPricing::matchesAnnualMonthSpan($septToJune, 9, 6))->toBeTrue()
        ->and(EnrollmentPricing::matchesAnnualMonthSpan(array_slice($julyToJune, 0, 3), 7, 6))->toBeFalse();
});

test('custom annual month range uses preferential total', function () {
    ['year' => $year, 'grade' => $grade, 'course' => $course] = annualPlanCtx();

    $this->actingAs(annualPlanAdmin())
        ->post(route('fee-plans.store'), annualPlanPayload($year, $grade, $course, [
            'annual_start_month' => 9,
            'annual_end_month' => 6,
            'annual_prices' => [$course->id => 32000],
        ]))
        ->assertRedirect(route('fee-plans.index'));

    $student = Student::query()->create([
        'name' => '九月年繳生',
        'academic_year_id' => $year->id,
        'grade_level_id' => $grade->id,
        'status' => 'active',
    ]);

    $months = [];
    $y = 2026;
    $m = 9;
    for ($i = 0; $i < 10; $i++) {
        $months[] = ['y' => $y, 'm' => $m];
        $m++;
        if ($m > 12) {
            $m = 1;
            $y++;
        }
    }

    $quote = EnrollmentPricing::quote(
        $student->load(['academicYear', 'gradeLevel']),
        [$course->id],
        'annual',
        array_map(
            fn (array $month): array => [
                'date' => sprintf('%04d-%02d-01', $month['y'], $month['m']),
                'course_id' => $course->id,
            ],
            $months,
        ),
        courseMonths: [
            $course->id => array_map(
                fn (array $month): string => $month['y'].'-'.$month['m'],
                $months,
            ),
        ],
    );

    expect((int) $quote['tuition_total'])->toBe(32000);
});
