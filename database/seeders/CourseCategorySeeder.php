<?php

namespace Database\Seeders;

use App\Models\CourseCategory;
use Illuminate\Database\Seeder;

class CourseCategorySeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['name' => '程式課', 'sort_order' => 1],
            ['name' => '機器人課', 'sort_order' => 2],
            ['name' => '數學課', 'sort_order' => 3],
            ['name' => '家教', 'sort_order' => 4],
        ];

        foreach ($rows as $row) {
            CourseCategory::query()->updateOrCreate(
                ['name' => $row['name']],
                ['sort_order' => $row['sort_order']]
            );
        }
    }
}
