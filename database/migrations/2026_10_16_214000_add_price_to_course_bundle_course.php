<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 組合優惠改為各科各自優惠價：pivot.price 為該科每月價；null＝此科不優惠（收原價）。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('course_bundle_course', function (Blueprint $table) {
            $table->unsignedInteger('price')->nullable()->after('course_id');
        });

        // 舊資料：組合統一價複製到每一科
        $rows = DB::table('course_bundle_course')
            ->join('course_bundles', 'course_bundles.id', '=', 'course_bundle_course.course_bundle_id')
            ->select(
                'course_bundle_course.id',
                'course_bundles.price as bundle_price',
            )
            ->get();

        foreach ($rows as $row) {
            DB::table('course_bundle_course')
                ->where('id', $row->id)
                ->update(['price' => (int) $row->bundle_price]);
        }
    }

    public function down(): void
    {
        Schema::table('course_bundle_course', function (Blueprint $table) {
            $table->dropColumn('price');
        });
    }
};
