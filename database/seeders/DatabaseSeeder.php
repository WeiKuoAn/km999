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
        $this->call(FeePlanSeeder::class);
        $this->call(CoursePricingGroupSeeder::class);
        // 課程類別／課程改由後台自行建立，不再 seed 預設資料
    }
}
