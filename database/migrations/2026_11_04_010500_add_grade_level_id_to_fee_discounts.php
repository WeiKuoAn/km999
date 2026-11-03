<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_discounts', function (Blueprint $table) {
            $table->foreignId('grade_level_id')
                ->nullable()
                ->after('name')
                ->constrained('grade_levels')
                ->nullOnDelete();
        });

        // 名稱以年級開頭者（例：國二 數學早鳥）自動掛上對應年級
        $grades = DB::table('grade_levels')->get(['id', 'name']);
        foreach ($grades as $grade) {
            DB::table('fee_discounts')
                ->whereNull('grade_level_id')
                ->where('name', 'like', $grade->name.'%')
                ->update(['grade_level_id' => $grade->id]);
        }
    }

    public function down(): void
    {
        Schema::table('fee_discounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('grade_level_id');
        });
    }
};
