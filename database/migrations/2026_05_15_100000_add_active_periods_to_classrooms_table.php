<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->json('active_periods')->nullable()->after('extra_sessions');
        });

        if (! Schema::hasTable('classrooms')) {
            return;
        }

        $classrooms = DB::table('classrooms')
            ->select('id', 'start_date', 'end_date', 'active_periods')
            ->get();

        foreach ($classrooms as $row) {
            if ($row->active_periods !== null) {
                continue;
            }
            if ($row->start_date === null && $row->end_date === null) {
                continue;
            }

            DB::table('classrooms')->where('id', $row->id)->update([
                'active_periods' => json_encode([[
                    'start_date' => $row->start_date,
                    'end_date' => $row->end_date,
                ]], JSON_THROW_ON_ERROR),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('classrooms', function (Blueprint $table) {
            $table->dropColumn('active_periods');
        });
    }
};
