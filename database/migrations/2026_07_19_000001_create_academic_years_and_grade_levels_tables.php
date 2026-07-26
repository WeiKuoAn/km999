<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_years', function (Blueprint $table) {
            $table->id();
            $table->string('year_code', 8)->unique()->comment('民國學年度，如 115');
            $table->string('name')->nullable()->comment('顯示名稱，如 115學年度');
            $table->boolean('is_current')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('grade_levels', function (Blueprint $table) {
            $table->id();
            $table->string('name', 32)->unique()->comment('如 國一');
            $table->unsignedTinyInteger('code')->unique()->comment('編號，如 7');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('students', function (Blueprint $table) {
            $table->string('student_code', 32)->nullable()->unique()->after('id');
            $table->foreignId('academic_year_id')->nullable()->after('student_code')->constrained('academic_years')->nullOnDelete();
            $table->foreignId('grade_level_id')->nullable()->after('academic_year_id')->constrained('grade_levels')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropConstrainedForeignId('grade_level_id');
            $table->dropConstrainedForeignId('academic_year_id');
            $table->dropColumn('student_code');
        });

        Schema::dropIfExists('grade_levels');
        Schema::dropIfExists('academic_years');
    }
};
