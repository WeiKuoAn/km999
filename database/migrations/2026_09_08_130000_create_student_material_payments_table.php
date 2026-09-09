<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('student_material_payments')) {
            Schema::create('student_material_payments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('student_id')->constrained()->cascadeOnDelete();
                $table->foreignId('course_id')->constrained()->cascadeOnDelete();
                $table->unsignedSmallInteger('period_year')->comment('半年所屬年份');
                $table->string('period_half', 2)->comment('H1=1–6；H2=7–12');
                $table->unsignedInteger('amount')->default(0);
                $table->date('paid_at');
                $table->foreignId('paid_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(
                    ['student_id', 'course_id', 'period_year', 'period_half'],
                    'student_material_period_unique'
                );
                $table->index(
                    ['student_id', 'period_year', 'period_half'],
                    'smp_student_period_idx'
                );
            });

            return;
        }

        // 上次 migration 失敗留下空表：補齊短名索引／唯一鍵
        $indexes = collect(DB::select('SHOW INDEX FROM student_material_payments'))
            ->pluck('Key_name')
            ->unique()
            ->all();

        Schema::table('student_material_payments', function (Blueprint $table) use ($indexes): void {
            if (! in_array('student_material_period_unique', $indexes, true)) {
                $table->unique(
                    ['student_id', 'course_id', 'period_year', 'period_half'],
                    'student_material_period_unique'
                );
            }
            if (! in_array('smp_student_period_idx', $indexes, true)) {
                $table->index(
                    ['student_id', 'period_year', 'period_half'],
                    'smp_student_period_idx'
                );
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_material_payments');
    }
};
