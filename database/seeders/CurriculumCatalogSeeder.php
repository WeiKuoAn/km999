<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\FeePlan;
use App\Models\GradeLevel;
use App\Support\PricingGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 一次建立：學年、年級、課程類別、課程、收費標準（含適用課目綁定）。
 * 內容對應目前後台國中課程與價目設定。
 */
class CurriculumCatalogSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $year = $this->seedAcademicYears();
            $this->seedGradeLevels();
            $this->seedCourseCategories();
            $courses = $this->seedCourses();
            $this->seedFeePlans($year, $courses);
        });
    }

    private function seedAcademicYears(): AcademicYear
    {
        AcademicYear::query()->where('is_current', true)->update(['is_current' => false]);

        return AcademicYear::query()->updateOrCreate(
            ['year_code' => '115'],
            [
                'name' => '115學年度',
                'is_current' => true,
                'sort_order' => 115,
            ]
        );
    }

    private function seedGradeLevels(): void
    {
        $rows = [
            ['name' => '國一', 'code' => 7, 'sort_order' => 1],
            ['name' => '國二', 'code' => 8, 'sort_order' => 2],
            ['name' => '國三', 'code' => 9, 'sort_order' => 3],
        ];

        foreach ($rows as $row) {
            GradeLevel::query()->updateOrCreate(
                ['name' => $row['name']],
                [
                    'code' => $row['code'],
                    'sort_order' => $row['sort_order'],
                    'is_active' => true,
                ]
            );
        }
    }

    private function seedCourseCategories(): void
    {
        $rows = [
            ['name' => '英文', 'sort_order' => 1],
            ['name' => '數學', 'sort_order' => 2],
            ['name' => '理化', 'sort_order' => 3],
            ['name' => '國文', 'sort_order' => 4],
            ['name' => '生物', 'sort_order' => 5],
            ['name' => '社會', 'sort_order' => 6],
        ];

        foreach ($rows as $row) {
            CourseCategory::query()->updateOrCreate(
                ['name' => $row['name']],
                ['sort_order' => $row['sort_order']]
            );
        }
    }

    /**
     * @return array<string, Course>
     */
    private function seedCourses(): array
    {
        $definitions = [
            [
                'category' => '英文',
                'name' => '英文課',
                'color' => '#0d9488',
                'pricing_group' => PricingGroup::CORE,
                'levels' => ['國一', '國二', '國三'],
                'schedules' => [
                    ['level' => '國一', 'weekday' => 1, 'start_time' => '17:30', 'end_time' => '19:00'],
                    ['level' => '國一', 'weekday' => 4, 'start_time' => '17:30', 'end_time' => '19:00'],
                    ['level' => '國二', 'weekday' => 1, 'start_time' => '14:00', 'end_time' => '15:30'],
                    ['level' => '國二', 'weekday' => 4, 'start_time' => '14:00', 'end_time' => '15:30'],
                    ['level' => '國三', 'weekday' => 1, 'start_time' => '19:30', 'end_time' => '21:00'],
                    ['level' => '國三', 'weekday' => 4, 'start_time' => '19:30', 'end_time' => '21:00'],
                ],
            ],
            [
                'category' => '數學',
                'name' => '數學課',
                'color' => '#1e3a5f',
                'pricing_group' => PricingGroup::CORE,
                'levels' => ['國一', '國二', '國三'],
                'schedules' => [
                    ['level' => '國一', 'weekday' => 3, 'start_time' => '17:30', 'end_time' => '19:00'],
                    ['level' => '國一', 'weekday' => 6, 'start_time' => '09:00', 'end_time' => '10:30'],
                    ['level' => '國二', 'weekday' => 3, 'start_time' => '14:00', 'end_time' => '15:30'],
                    ['level' => '國二', 'weekday' => 6, 'start_time' => '09:00', 'end_time' => '10:30'],
                    ['level' => '國三', 'weekday' => 3, 'start_time' => '19:30', 'end_time' => '21:00'],
                    ['level' => '國三', 'weekday' => 6, 'start_time' => '10:30', 'end_time' => '12:00'],
                ],
            ],
            [
                'category' => '理化',
                'name' => '理化課',
                'color' => '#0d9488',
                'pricing_group' => PricingGroup::CORE,
                'levels' => ['國二', '國三'],
                'schedules' => [
                    ['level' => '國二', 'weekday' => 2, 'start_time' => '14:00', 'end_time' => '15:30'],
                    ['level' => '國二', 'weekday' => 5, 'start_time' => '14:00', 'end_time' => '15:30'],
                    ['level' => '國三', 'weekday' => 2, 'start_time' => '19:30', 'end_time' => '21:00'],
                    ['level' => '國三', 'weekday' => 5, 'start_time' => '19:30', 'end_time' => '21:00'],
                ],
            ],
            [
                'category' => '理化',
                'name' => '理化科研班',
                'color' => '#a9fed2',
                'pricing_group' => PricingGroup::RESEARCH,
                'levels' => ['國二', '國三'],
                'schedules' => [
                    ['level' => '國二', 'weekday' => 2, 'start_time' => '14:00', 'end_time' => '15:30'],
                    ['level' => '國二', 'weekday' => 5, 'start_time' => '14:00', 'end_time' => '15:30'],
                    ['level' => '國三', 'weekday' => 2, 'start_time' => '19:30', 'end_time' => '21:00'],
                    ['level' => '國三', 'weekday' => 5, 'start_time' => '19:30', 'end_time' => '21:00'],
                ],
            ],
            [
                'category' => '國文',
                'name' => '國文課',
                'color' => '#1e3a5f',
                'pricing_group' => PricingGroup::HUMANITIES,
                'levels' => ['國一', '國二', '國三'],
                'schedules' => [
                    ['level' => '國一', 'weekday' => 2, 'start_time' => '17:00', 'end_time' => '18:30'],
                    ['level' => '國一', 'weekday' => 2, 'start_time' => '18:30', 'end_time' => '20:00'],
                    ['level' => '國二', 'weekday' => 6, 'start_time' => '13:00', 'end_time' => '15:00'],
                    ['level' => '國三', 'weekday' => 6, 'start_time' => '15:10', 'end_time' => '16:40'],
                ],
            ],
            [
                'category' => '生物',
                'name' => '生物課',
                'color' => '#0d9488',
                'pricing_group' => PricingGroup::HUMANITIES,
                'levels' => ['國一'],
                'schedules' => [
                    ['level' => '國一', 'weekday' => 5, 'start_time' => '17:30', 'end_time' => '19:00'],
                ],
            ],
            [
                'category' => '社會',
                'name' => '社會課',
                'color' => '#0d9488',
                'pricing_group' => PricingGroup::HUMANITIES,
                'levels' => ['國三'],
                'schedules' => [
                    ['level' => '國三', 'weekday' => 6, 'start_time' => '13:30', 'end_time' => '15:00'],
                ],
            ],
        ];

        $courses = [];

        foreach ($definitions as $def) {
            $category = CourseCategory::query()->where('name', $def['category'])->firstOrFail();
            $weekdays = collect($def['schedules'])
                ->pluck('weekday')
                ->unique()
                ->sort()
                ->values()
                ->all();

            $course = Course::query()->updateOrCreate(
                [
                    'course_category_id' => $category->id,
                    'name' => $def['name'],
                ],
                [
                    'color' => $def['color'],
                    'status' => 'active',
                    'pricing_group' => $def['pricing_group'],
                    'weekdays' => $weekdays,
                    'schedules' => $def['schedules'],
                ]
            );

            $course->coursePrices()->delete();
            foreach ($def['levels'] as $index => $level) {
                $course->coursePrices()->create([
                    'level' => $level,
                    'duration_hours' => 1.5,
                    'tuition' => 0,
                    'sort_order' => $index,
                ]);
            }

            $courses[$def['name']] = $course;
        }

        return $courses;
    }

    /**
     * @param  array<string, Course>  $courses
     */
    private function seedFeePlans(AcademicYear $year, array $courses): void
    {
        $grades = GradeLevel::query()->get()->keyBy('name');

        $rows = [
            [
                'grade' => '國一',
                'group_name' => '英／數',
                'pricing_group' => PricingGroup::CORE,
                'unit' => 'month',
                'list_price' => 3600,
                'quarter_single_price' => 3200,
                'quarter_double_price' => 3000,
                'material_fee' => 1200,
                'material_unit' => 'term',
                'sort_order' => 10,
                'course_names' => ['英文課', '數學課'],
            ],
            [
                'grade' => '國一',
                'group_name' => '國／生',
                'pricing_group' => PricingGroup::HUMANITIES,
                'unit' => 'month',
                'list_price' => 2000,
                'quarter_price' => 1800,
                'material_fee' => 900,
                'material_unit' => 'term',
                'sort_order' => 20,
                'course_names' => ['國文課', '生物課'],
            ],
            [
                'grade' => '國二',
                'group_name' => '英／數／理',
                'pricing_group' => PricingGroup::CORE,
                'unit' => 'month',
                'list_price' => 3600,
                'quarter_single_price' => 3200,
                'quarter_double_price' => 3000,
                'material_fee' => 1200,
                'material_unit' => 'term',
                'sort_order' => 30,
                'course_names' => ['英文課', '數學課', '理化課'],
            ],
            [
                'grade' => '國二',
                'group_name' => '理化科研',
                'pricing_group' => PricingGroup::RESEARCH,
                'unit' => 'session_block',
                'session_block_size' => 4,
                'list_price' => 4400,
                'quarter_price' => 3800,
                'material_fee' => 1500,
                'material_unit' => 'subject',
                'sort_order' => 40,
                'course_names' => ['理化科研班'],
            ],
            [
                'grade' => '國二',
                'group_name' => '國文',
                'pricing_group' => PricingGroup::HUMANITIES,
                'unit' => 'month',
                'list_price' => 2000,
                'quarter_price' => 1800,
                'material_fee' => 900,
                'material_unit' => 'term',
                'sort_order' => 50,
                'course_names' => ['國文課'],
            ],
            [
                'grade' => '國三',
                'group_name' => '英／數／理',
                'pricing_group' => PricingGroup::CORE,
                'unit' => 'month',
                'list_price' => 3600,
                'quarter_single_price' => 3300,
                'quarter_double_price' => 3100,
                'material_fee' => 1500,
                'material_unit' => 'term',
                'sort_order' => 60,
                'course_names' => ['英文課', '數學課', '理化課'],
            ],
            [
                'grade' => '國三',
                'group_name' => '理化科研',
                'pricing_group' => PricingGroup::RESEARCH,
                'unit' => 'session_block',
                'session_block_size' => 4,
                'list_price' => 4400,
                'quarter_price' => 3800,
                'material_fee' => 1500,
                'material_unit' => 'subject',
                'sort_order' => 70,
                'course_names' => ['理化科研班'],
            ],
            [
                'grade' => '國三',
                'group_name' => '國／社',
                'pricing_group' => PricingGroup::HUMANITIES,
                'unit' => 'month',
                'list_price' => 2000,
                'quarter_price' => 1800,
                'material_fee' => 1000,
                'material_unit' => 'term',
                'sort_order' => 80,
                'course_names' => ['國文課', '社會課'],
            ],
        ];

        foreach ($rows as $row) {
            $grade = $grades->get($row['grade']);
            if ($grade === null) {
                continue;
            }

            $plan = FeePlan::query()->updateOrCreate(
                [
                    'grade_level_id' => $grade->id,
                    'group_name' => $row['group_name'],
                    'academic_year_id' => $year->id,
                ],
                [
                    'pricing_group' => $row['pricing_group'],
                    'unit' => $row['unit'],
                    'session_block_size' => $row['session_block_size'] ?? null,
                    'list_price' => $row['list_price'],
                    'quarter_price' => $row['quarter_price'] ?? null,
                    'quarter_single_price' => $row['quarter_single_price'] ?? null,
                    'quarter_double_price' => $row['quarter_double_price'] ?? null,
                    'material_fee' => $row['material_fee'],
                    'material_unit' => $row['material_unit'],
                    'sort_order' => $row['sort_order'],
                    'is_active' => true,
                ]
            );

            $courseIds = collect($row['course_names'])
                ->map(fn (string $name): ?int => $courses[$name]?->id)
                ->filter()
                ->values()
                ->all();

            $plan->courses()->sync($courseIds);
        }
    }
}
