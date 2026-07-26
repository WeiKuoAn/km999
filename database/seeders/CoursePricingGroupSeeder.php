<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\CoursePrice;
use App\Models\GradeLevel;
use App\Support\PricingGroup;
use Illuminate\Database\Seeder;

class CoursePricingGroupSeeder extends Seeder
{
    public function run(): void
    {
        $map = [
            '英文' => PricingGroup::CORE,
            '英文課' => PricingGroup::CORE,
            '數學' => PricingGroup::CORE,
            '數學課' => PricingGroup::CORE,
            '理化' => PricingGroup::CORE,
            '理化課' => PricingGroup::CORE,
            '國文' => PricingGroup::HUMANITIES,
            '國文課' => PricingGroup::HUMANITIES,
            '生物' => PricingGroup::HUMANITIES,
            '生物課' => PricingGroup::HUMANITIES,
            '社會' => PricingGroup::HUMANITIES,
            '社會課' => PricingGroup::HUMANITIES,
            '理化科研' => PricingGroup::RESEARCH,
            '理化科研班' => PricingGroup::RESEARCH,
        ];

        $gradeNames = GradeLevel::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('name')
            ->all();

        foreach (Course::query()->get() as $course) {
            $group = $map[$course->name] ?? null;
            if ($group === null) {
                continue;
            }

            $course->update(['pricing_group' => $group]);

            // 確保有適用年級；核心／國社依名稱補齊
            $existing = $course->coursePrices()->pluck('level')->filter()->all();
            if ($existing !== []) {
                continue;
            }

            $targets = match ($group) {
                PricingGroup::RESEARCH => array_values(array_filter($gradeNames, fn ($n) => in_array($n, ['國二', '國三'], true))),
                PricingGroup::CORE, PricingGroup::HUMANITIES => $gradeNames,
                default => $gradeNames,
            };

            // 名稱特例
            if (str_contains($course->name, '社會')) {
                $targets = array_values(array_filter($gradeNames, fn ($n) => $n === '國三'));
            }
            if (str_contains($course->name, '生物')) {
                $targets = array_values(array_filter($gradeNames, fn ($n) => $n === '國一'));
            }

            foreach ($targets as $index => $level) {
                CoursePrice::query()->create([
                    'course_id' => $course->id,
                    'level' => $level,
                    'duration_hours' => 1.0,
                    'tuition' => 0,
                    'sort_order' => $index,
                ]);
            }
        }
    }
}
