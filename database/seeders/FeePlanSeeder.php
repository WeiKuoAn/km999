<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\Course;
use App\Models\CourseBundle;
use App\Models\FeePlan;
use App\Models\GradeLevel;
use App\Support\PricingGroup;
use Illuminate\Database\Seeder;

class FeePlanSeeder extends Seeder
{
    public function run(): void
    {
        $yearId = AcademicYear::query()->where('is_current', true)->value('id')
            ?? AcademicYear::query()->orderByDesc('year_code')->value('id');

        $grades = GradeLevel::query()->get()->keyBy('name');

        $rows = [
            [
                'grade' => '國一',
                'group_name' => '英／數',
                'pricing_group' => PricingGroup::CORE,
                'unit' => 'month',
                'list_price' => 3600,
                'quarter_price' => 3200,
                'bundle_price' => 3000,
                'material_fee' => 1200,
                'material_unit' => 'term',
                'sort_order' => 10,
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
            ],
            [
                'grade' => '國二',
                'group_name' => '英／數／理',
                'pricing_group' => PricingGroup::CORE,
                'unit' => 'month',
                'list_price' => 3600,
                'quarter_price' => 3200,
                'bundle_price' => 3000,
                'material_fee' => 1200,
                'material_unit' => 'term',
                'sort_order' => 30,
            ],
            [
                'grade' => '國二',
                'group_name' => '理化科研',
                'pricing_group' => PricingGroup::RESEARCH,
                'unit' => 'session_block',
                'session_block_size' => 4,
                // 月價；一期總額 = 1100×4 / 950×4
                'list_price' => 1100,
                'quarter_price' => 950,
                'material_fee' => 1500,
                'material_unit' => 'subject',
                'sort_order' => 40,
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
            ],
            [
                'grade' => '國三',
                'group_name' => '英／數／理',
                'pricing_group' => PricingGroup::CORE,
                'unit' => 'month',
                'list_price' => 3600,
                'quarter_price' => 3300,
                'bundle_price' => 3100,
                'material_fee' => 1500,
                'material_unit' => 'term',
                'sort_order' => 60,
            ],
            [
                'grade' => '國三',
                'group_name' => '理化科研',
                'pricing_group' => PricingGroup::RESEARCH,
                'unit' => 'session_block',
                'session_block_size' => 4,
                'list_price' => 1100,
                'quarter_price' => 950,
                'material_fee' => 1500,
                'material_unit' => 'subject',
                'sort_order' => 70,
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
                    'academic_year_id' => $yearId,
                ],
                [
                    'pricing_group' => $row['pricing_group'],
                    'unit' => $row['unit'],
                    'session_block_size' => $row['session_block_size'] ?? null,
                    'list_price' => $row['list_price'],
                    'quarter_price' => $row['quarter_price'] ?? null,
                    'quarter_single_price' => null,
                    'quarter_double_price' => null,
                    'material_fee' => $row['material_fee'],
                    'material_unit' => $row['material_unit'],
                    'sort_order' => $row['sort_order'],
                    'is_active' => true,
                ]
            );

            $courseIds = Course::query()
                ->where('pricing_group', $row['pricing_group'])
                ->pluck('id')
                ->all();

            $plan->courses()->sync($courseIds);

            if (isset($row['bundle_price']) && count($courseIds) >= 2) {
                CourseBundle::query()->updateOrCreate(
                    [
                        'name' => $row['group_name'].' 雙科',
                        'grade_level_id' => $grade->id,
                        'academic_year_id' => $yearId,
                    ],
                    [
                        'min_courses' => 2,
                        'price' => $row['bundle_price'],
                        'is_active' => true,
                        'sort_order' => $row['sort_order'],
                    ]
                )->courses()->sync($courseIds);
            }
        }
    }
}
