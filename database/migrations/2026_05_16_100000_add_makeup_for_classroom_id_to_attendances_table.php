<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('attendances', 'makeup_for_classroom_id')) {
            Schema::table('attendances', function (Blueprint $table) {
                // 補課歸屬班級：A班學生到別班補課時，記錄這筆補課實際是補哪一班的課（用於學費計算）。
                $table->foreignId('makeup_for_classroom_id')
                    ->nullable()
                    ->after('classroom_id')
                    ->constrained('classrooms')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('attendances', 'makeup_for_classroom_id')) {
            Schema::table('attendances', function (Blueprint $table) {
                $table->dropConstrainedForeignId('makeup_for_classroom_id');
            });
        }
    }
};
