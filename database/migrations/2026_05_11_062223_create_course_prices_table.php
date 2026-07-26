<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('course_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained()->cascadeOnDelete();
            $table->string('level', 16)->nullable();
            $table->unsignedInteger('tuition');
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['course_id', 'sort_order']);
        });

        if (Schema::hasTable('courses') && Schema::hasColumn('courses', 'default_tuition')) {
            $courses = DB::table('courses')->select(['id', 'level', 'default_tuition'])->get();
            foreach ($courses as $row) {
                DB::table('course_prices')->insert([
                    'course_id' => $row->id,
                    'level' => $row->level,
                    'tuition' => $row->default_tuition,
                    'sort_order' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            Schema::table('courses', function (Blueprint $table) {
                $table->dropColumn(['level', 'default_tuition']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->string('level', 16)->nullable()->after('name');
            $table->unsignedInteger('default_tuition')->default(0)->after('level');
        });

        $prices = DB::table('course_prices')
            ->orderBy('course_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->groupBy('course_id');

        foreach ($prices as $courseId => $rows) {
            $first = $rows->first();
            DB::table('courses')->where('id', $courseId)->update([
                'level' => $first->level,
                'default_tuition' => $first->tuition,
            ]);
        }

        Schema::dropIfExists('course_prices');
    }
};
