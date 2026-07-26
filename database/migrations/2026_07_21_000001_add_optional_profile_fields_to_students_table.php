<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->string('graduate_school')->nullable()->after('parent_phone');
            $table->string('current_school')->nullable()->after('graduate_school');
            $table->string('class_name', 64)->nullable()->after('current_school')->comment('年級班別，如 忠班');
            $table->string('id_number', 32)->nullable()->after('class_name');
            $table->string('address')->nullable()->after('id_number');
            $table->string('gender', 16)->nullable()->after('address');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn([
                'graduate_school',
                'current_school',
                'class_name',
                'id_number',
                'address',
                'gender',
            ]);
        });
    }
};
