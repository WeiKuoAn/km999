<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\CourseCategory;
use Illuminate\Database\Seeder;

class CourseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $definitions = [
            ['category_name' => '程式課', 'name' => 'scratch', 'tiers' => [[null, 600]]],
            ['category_name' => '程式課', 'name' => 'python', 'tiers' => [[null, 600]]],
            ['category_name' => '機器人課', 'name' => 'scratch', 'tiers' => [[null, 600]]],
            ['category_name' => '數學課', 'name' => '數學', 'tiers' => [['國小', 600], ['國中', 700]]],
            ['category_name' => '家教', 'name' => '家教', 'tiers' => [[null, 1200]]],
        ];

        foreach ($definitions as $def) {
            $category = CourseCategory::query()->where('name', $def['category_name'])->firstOrFail();

            $course = Course::query()->updateOrCreate(
                [
                    'course_category_id' => $category->id,
                    'name' => $def['name'],
                ],
                [
                    'status' => 'active',
                ]
            );

            $course->coursePrices()->delete();

            foreach ($def['tiers'] as $i => $tier) {
                [$level, $tuition] = $tier;
                $course->coursePrices()->create([
                    'level' => $level,
                    'tuition' => $tuition,
                    'sort_order' => $i,
                ]);
            }
        }
    }
}
