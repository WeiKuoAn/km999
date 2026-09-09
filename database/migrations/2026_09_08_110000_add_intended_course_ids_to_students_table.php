<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            if (! Schema::hasColumn('students', 'intended_course_ids')) {
                $table->json('intended_course_ids')
                    ->nullable()
                    ->after('grade_level_id')
                    ->comment('轉檔後預選科目；收款優先使用');
            }
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            if (Schema::hasColumn('students', 'intended_course_ids')) {
                $table->dropColumn('intended_course_ids');
            }
        });
    }
};
