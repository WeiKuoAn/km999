<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->json('schedules')->nullable()->after('weekdays')
                ->comment('[{weekday,start_time,end_time}] 課程預設上課時段');
        });

        // 既有 weekdays → schedules（時間先留空，之後可補）
        $courses = DB::table('courses')->whereNotNull('weekdays')->get(['id', 'weekdays']);
        foreach ($courses as $course) {
            $weekdays = json_decode((string) $course->weekdays, true);
            if (! is_array($weekdays) || $weekdays === []) {
                continue;
            }
            $schedules = [];
            foreach ($weekdays as $day) {
                $d = (int) $day;
                if ($d < 1 || $d > 7) {
                    continue;
                }
                $schedules[] = [
                    'weekday' => $d,
                    'start_time' => null,
                    'end_time' => null,
                ];
            }
            if ($schedules !== []) {
                DB::table('courses')->where('id', $course->id)->update([
                    'schedules' => json_encode($schedules, JSON_UNESCAPED_UNICODE),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('schedules');
        });
    }
};
