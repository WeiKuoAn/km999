<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\GradeLevel;
use Illuminate\Database\Seeder;

class StudentCodeSettingsSeeder extends Seeder
{
    public function run(): void
    {
        AcademicYear::query()->updateOrCreate(
            ['year_code' => '115'],
            [
                'name' => '115學年度',
                'is_current' => true,
                'sort_order' => 115,
            ]
        );

        $grades = [
            ['name' => '國一', 'code' => 7, 'sort_order' => 1],
            ['name' => '國二', 'code' => 8, 'sort_order' => 2],
            ['name' => '國三', 'code' => 9, 'sort_order' => 3],
        ];

        foreach ($grades as $grade) {
            GradeLevel::query()->updateOrCreate(
                ['name' => $grade['name']],
                [
                    'code' => $grade['code'],
                    'sort_order' => $grade['sort_order'],
                    'is_active' => true,
                ]
            );
        }
    }
}
