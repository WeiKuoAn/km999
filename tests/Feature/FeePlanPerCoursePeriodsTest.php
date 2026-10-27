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

function perCourseCtx(): array
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
    ]);
    $category = CourseCategory::query()->create([
        'name' => '國中課程',
        'sort_order' => 1,
    ]);

    return compact('year', 'grade', 'category');
}

function perCourseCourse(CourseCategory $category, string $name): Course
{
    return Course::query()->create([
        'course_category_id' => $category->id,
        'name' => $name,
        'color' => '#0d9488',
        'status' => 'active',
        'pricing_group' => PricingGroup::CORE,
        'weekdays' => [2],
        'schedules' => [],
    ]);
}

/** 避開本機缺 intl 時 Faker 產生 email 失敗 */
function perCourseUser(): User
{
    return User::factory()->create([
        'name' => '測試管理員',
        'email' => 'per-course-'.uniqid('', true).'@example.test',
    ]);
}

function sharedPeriodPayload(AcademicYear $year, GradeLevel $grade, array $courseIds, array $periodCoursePrices): array
{
    return [
        'academic_year_id' => $year->id,
        'grade_level_id' => $grade->id,
        'course_ids' => $courseIds,
        'group_name' => '英數理',
        'pricing_group' => PricingGroup::CORE,
        'unit' => 'custom_period',
        'per_course_periods' => false,
        'session_block_size' => null,
        'list_price' => 0,
        'quarter_price' => null,
        'material_fee' => 0,
        'material_unit' => 'term',
        'sort_order' => 10,
        'is_active' => true,
        'periods' => [
            [
                'course_id' => null,
                'start_month' => 7,
                'end_month' => 9,
                'course_prices' => $periodCoursePrices,
            ],
        ],
        'bundles' => [],
    ];
}

test('shared custom period plan stores course prices without course_id on periods', function () {
    ['year' => $year, 'grade' => $grade, 'category' => $category] = perCourseCtx();
    $english = perCourseCourse($category, '英文');
    $math = perCourseCourse($category, '數學');

    $this->actingAs(perCourseUser())
        ->post(route('fee-plans.store'), sharedPeriodPayload($year, $grade, [$english->id, $math->id], [
            $english->id => ['total_price' => 1800, 'double_price' => 1600, 'triple_price' => null],
            $math->id => ['total_price' => 1900, 'double_price' => 1700, 'triple_price' => null],
        ]))
        ->assertRedirect(route('fee-plans.index'));

    $plan = FeePlan::query()->sole();
    expect($plan->per_course_periods)->toBeFalse()
        ->and($plan->unit)->toBe('custom_period');

    $period = $plan->periods()->with('coursePrices')->sole();
    expect($period->course_id)->toBeNull()
        ->and($period->start_month)->toBe(7)
        ->and($period->end_month)->toBe(9)
        ->and($period->coursePrices)->toHaveCount(2);

    $byCourse = $period->coursePrices->keyBy('course_id');
    expect((int) $byCourse[$english->id]->total_price)->toBe(1800)
        ->and((int) $byCourse[$english->id]->double_price)->toBe(1600)
        ->and((int) $byCourse[$math->id]->total_price)->toBe(1900);
});

test('per-course periods allow different month ranges and multi-subject discount', function () {
    ['year' => $year, 'grade' => $grade, 'category' => $category] = perCourseCtx();
    $english = perCourseCourse($category, '英文');
    $math = perCourseCourse($category, '數學');
    $physics = perCourseCourse($category, '理化');

    $payload = [
        'academic_year_id' => $year->id,
        'grade_level_id' => $grade->id,
        'course_ids' => [$english->id, $math->id, $physics->id],
        'group_name' => '英數理異期',
        'pricing_group' => PricingGroup::CORE,
        'unit' => 'custom_period',
        'per_course_periods' => true,
        'session_block_size' => null,
        'list_price' => 0,
        'quarter_price' => null,
        'material_fee' => 0,
        'material_unit' => 'term',
        'sort_order' => 10,
        'is_active' => true,
        'periods' => [
            // 英：6–8、9–11
            [
                'course_id' => $english->id,
                'start_month' => 6,
                'end_month' => 8,
                'total_price' => 2000,
                'double_price' => 1800,
                'triple_price' => 1600,
            ],
            [
                'course_id' => $english->id,
                'start_month' => 9,
                'end_month' => 11,
                'total_price' => 2000,
                'double_price' => 1800,
                'triple_price' => 1600,
            ],
            // 數：同英
            [
                'course_id' => $math->id,
                'start_month' => 6,
                'end_month' => 8,
                'total_price' => 2100,
                'double_price' => 1900,
                'triple_price' => 1700,
            ],
            [
                'course_id' => $math->id,
                'start_month' => 9,
                'end_month' => 11,
                'total_price' => 2100,
                'double_price' => 1900,
                'triple_price' => 1700,
            ],
            // 理：7–10、11–1
            [
                'course_id' => $physics->id,
                'start_month' => 7,
                'end_month' => 10,
                'total_price' => 2200,
                'double_price' => 2000,
                'triple_price' => 1800,
            ],
            [
                'course_id' => $physics->id,
                'start_month' => 11,
                'end_month' => 1,
                'total_price' => 2200,
                'double_price' => 2000,
                'triple_price' => 1800,
            ],
        ],
        'bundles' => [],
    ];

    $this->actingAs(perCourseUser())
        ->post(route('fee-plans.store'), $payload)
        ->assertRedirect(route('fee-plans.index'));

    $plan = FeePlan::query()->with(['periods.coursePrices', 'courses'])->sole();
    expect($plan->per_course_periods)->toBeTrue()
        ->and($plan->periods)->toHaveCount(6);

    $englishPeriods = $plan->periods->where('course_id', $english->id)->values();
    $physicsPeriods = $plan->periods->where('course_id', $physics->id)->values();
    expect($englishPeriods)->toHaveCount(2)
        ->and((int) $englishPeriods[0]->start_month)->toBe(6)
        ->and((int) $englishPeriods[0]->end_month)->toBe(8)
        ->and($physicsPeriods)->toHaveCount(2)
        ->and((int) $physicsPeriods[0]->start_month)->toBe(7)
        ->and((int) $physicsPeriods[0]->end_month)->toBe(10);

    $lines = $plan->periodSummaryLines();
    expect($lines)->toHaveCount(3)
        ->and(collect($lines)->pluck('label')->all())->toContain('英文', '數學', '理化')
        ->and(collect($lines)->every(fn (array $line): bool => $line['months'] === 0))->toBeTrue();

    $student = Student::query()->create([
        'name' => '測試學生',
        'academic_year_id' => $year->id,
        'grade_level_id' => $grade->id,
    ]);

    $subjects = collect(EnrollmentPricing::subjectsForStudent($student->load(['academicYear', 'gradeLevel'])))
        ->keyBy('id');

    expect($subjects[$english->id]['periods'])->toHaveCount(2)
        ->and($subjects[$english->id]['periods'][0]['start_month'])->toBe(6)
        ->and($subjects[$physics->id]['periods'][0]['start_month'])->toBe(7);

    // 三科同設定 → 多科月價；英 9 月＝1600、理 9 月＝1800
    $quote = EnrollmentPricing::quote($student, [$english->id, $math->id, $physics->id], 'quarterly', [
        ['date' => '2026-09-01', 'course_id' => $english->id],
        ['date' => '2026-09-02', 'course_id' => $math->id],
        ['date' => '2026-09-03', 'course_id' => $physics->id],
    ]);

    $byCourse = collect($quote['lines'])->keyBy('course_id');
    expect((int) $byCourse[$english->id]['unit_price'])->toBe(1600)
        ->and((int) $byCourse[$math->id]['unit_price'])->toBe(1700)
        ->and((int) $byCourse[$physics->id]['unit_price'])->toBe(1800)
        ->and((int) $byCourse[$english->id]['tuition'])->toBe(1600)
        ->and((int) $byCourse[$physics->id]['tuition'])->toBe(1800);
});

test('per-course mode requires a period for every selected course', function () {
    ['year' => $year, 'grade' => $grade, 'category' => $category] = perCourseCtx();
    $english = perCourseCourse($category, '英文');
    $math = perCourseCourse($category, '數學');

    $this->actingAs(perCourseUser())
        ->post(route('fee-plans.store'), [
            'academic_year_id' => $year->id,
            'grade_level_id' => $grade->id,
            'course_ids' => [$english->id, $math->id],
            'group_name' => '缺數期',
            'pricing_group' => PricingGroup::CORE,
            'unit' => 'custom_period',
            'per_course_periods' => true,
            'list_price' => 0,
            'material_fee' => 0,
            'material_unit' => 'term',
            'sort_order' => 0,
            'is_active' => true,
            'periods' => [
                [
                    'course_id' => $english->id,
                    'start_month' => 7,
                    'end_month' => 8,
                    'total_price' => 1800,
                ],
            ],
            'bundles' => [],
        ])
        ->assertSessionHasErrors('periods');
});
