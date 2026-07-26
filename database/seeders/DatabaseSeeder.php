<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(SuperAdminSeeder::class);
        $this->call(StudentCodeSettingsSeeder::class);
        // 學年、年級、課程類別、課程、收費標準（含適用課目）
        $this->call(CurriculumCatalogSeeder::class);
    }
}
