<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('course_bundles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 64);
            $table->foreignId('academic_year_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('grade_level_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('min_courses')->default(2);
            $table->unsignedInteger('price');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('course_bundle_course', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_bundle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->unique(['course_bundle_id', 'course_id']);
        });

        // 原收費標準的「季繳雙科價」改為組合優惠；季繳價改用原單科價，金額不變
        $plans = DB::table('fee_plans')->whereNotNull('quarter_double_price')->get();
        foreach ($plans as $plan) {
            $courseIds = DB::table('course_fee_plan')
                ->join('courses', 'courses.id', '=', 'course_fee_plan.course_id')
                ->where('course_fee_plan.fee_plan_id', $plan->id)
                ->where('courses.pricing_group', 'core')
                ->pluck('courses.id')
                ->all();

            if (count($courseIds) >= 2) {
                $bundleId = DB::table('course_bundles')->insertGetId([
                    'name' => $plan->group_name.' 雙科',
                    'academic_year_id' => $plan->academic_year_id,
                    'grade_level_id' => $plan->grade_level_id,
                    'min_courses' => 2,
                    'price' => (int) $plan->quarter_double_price,
                    'is_active' => (bool) $plan->is_active,
                    'sort_order' => (int) $plan->sort_order,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                foreach ($courseIds as $courseId) {
                    DB::table('course_bundle_course')->insert([
                        'course_bundle_id' => $bundleId,
                        'course_id' => $courseId,
                    ]);
                }
            }

            DB::table('fee_plans')->where('id', $plan->id)->update([
                'quarter_price' => $plan->quarter_single_price ?? $plan->quarter_price,
                'quarter_single_price' => null,
                'quarter_double_price' => null,
            ]);
        }
    }

    public function down(): void
    {
        $bundles = DB::table('course_bundles')->get();
        foreach ($bundles as $bundle) {
            $courseIds = DB::table('course_bundle_course')
                ->where('course_bundle_id', $bundle->id)
                ->pluck('course_id')
                ->all();
            $planIds = DB::table('course_fee_plan')
                ->whereIn('course_id', $courseIds)
                ->pluck('fee_plan_id')
                ->unique()
                ->all();
            DB::table('fee_plans')
                ->whereIn('id', $planIds)
                ->where('grade_level_id', $bundle->grade_level_id)
                ->where('academic_year_id', $bundle->academic_year_id)
                ->whereNull('quarter_double_price')
                ->update([
                    'quarter_single_price' => DB::raw('quarter_price'),
                    'quarter_double_price' => $bundle->price,
                    'quarter_price' => null,
                ]);
        }

        Schema::dropIfExists('course_bundle_course');
        Schema::dropIfExists('course_bundles');
    }
};
