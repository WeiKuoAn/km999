<?php

use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\GradeLevel;
use App\Models\Reconciliation;
use App\Models\Student;
use App\Models\StudentCourseDrop;
use App\Models\User;
use App\Support\PricingGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

function coursesScheduleAdmin(): User
{
    return User::query()->create([
        'name' => '管理員',
        'email' => 'courses-schedule-'.uniqid('', true).'@example.test',
        'password' => bcrypt('password'),
        'role' => 'admin',
        'is_active' => true,
    ]);
}

test('courses schedule lists subjects paid in earlier periods when not dropped', function () {
    Carbon::setTestNow('2026-11-06');

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
        'weekdays' => [3],
        'schedules' => [],
    ]);
    $english = Course::query()->create([
        'course_category_id' => $category->id,
        'name' => '英文課',
        'color' => '#0d9488',
        'status' => 'active',
        'pricing_group' => PricingGroup::CORE,
        'weekdays' => [1],
        'schedules' => [],
    ]);
    $science = Course::query()->create([
        'course_category_id' => $category->id,
        'name' => '理化課',
        'color' => '#b45309',
        'status' => 'active',
        'pricing_group' => PricingGroup::CORE,
        'weekdays' => [5],
        'schedules' => [],
    ]);

    $student = Student::query()->create([
        'student_code' => 'CS001',
        'name' => '翊瑄測試',
        'academic_year_id' => $year->id,
        'grade_level_id' => $grade->id,
        'status' => 'active',
    ]);

    // 數學／英文已繳到本月；理化只繳到上個月（不同期別）
    foreach ([$math->id, $english->id] as $courseId) {
        Reconciliation::query()->create([
            'student_id' => $student->id,
            'classroom_id' => null,
            'course_id' => $courseId,
            'pay_cycle' => 'quarterly',
            'billing_year' => 2026,
            'billing_month' => 11,
            'expected_amount' => 3000,
            'paid_amount' => 3000,
            'paid_date' => '2026-10-22',
            'status' => 'paid',
        ]);
    }
    Reconciliation::query()->create([
        'student_id' => $student->id,
        'classroom_id' => null,
        'course_id' => $science->id,
        'pay_cycle' => 'quarterly',
        'billing_year' => 2026,
        'billing_month' => 10,
        'expected_amount' => 3000,
        'paid_amount' => 3000,
        'paid_date' => '2026-10-22',
        'status' => 'paid',
    ]);

    $this->actingAs(coursesScheduleAdmin())
        ->get(route('students.courses-schedule', $student))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Students/CoursesSchedule')
            ->has('courseRows', 3)
            ->where(
                'courseRows',
                fn ($rows) => collect($rows)->pluck('course_name')->sort()->values()->all()
                    === collect(['數學課', '英文課', '理化課'])->sort()->values()->all()
            ));
});

test('courses schedule hides dropped courses even with paid history', function () {
    Carbon::setTestNow('2026-11-06');

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
    $science = Course::query()->create([
        'course_category_id' => $category->id,
        'name' => '理化課',
        'color' => '#b45309',
        'status' => 'active',
        'pricing_group' => PricingGroup::CORE,
        'weekdays' => [5],
        'schedules' => [],
    ]);
    $student = Student::query()->create([
        'student_code' => 'CS002',
        'name' => '停修測試',
        'academic_year_id' => $year->id,
        'grade_level_id' => $grade->id,
        'status' => 'active',
    ]);

    Reconciliation::query()->create([
        'student_id' => $student->id,
        'classroom_id' => null,
        'course_id' => $science->id,
        'pay_cycle' => 'quarterly',
        'billing_year' => 2026,
        'billing_month' => 10,
        'expected_amount' => 3000,
        'paid_amount' => 3000,
        'paid_date' => '2026-10-22',
        'status' => 'paid',
    ]);

    if (Schema::hasTable('student_course_drops')) {
        StudentCourseDrop::query()->create([
            'student_id' => $student->id,
            'course_id' => $science->id,
            'dropped_at' => now(),
        ]);
    }

    $this->actingAs(coursesScheduleAdmin())
        ->get(route('students.courses-schedule', $student))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Students/CoursesSchedule')
            ->where('courseRows', fn ($rows) => collect($rows)->pluck('course_name')->doesntContain('理化課')));
});
