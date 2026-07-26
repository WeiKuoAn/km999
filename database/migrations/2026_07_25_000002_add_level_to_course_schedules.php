<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $courses = DB::table('courses')->whereNotNull('schedules')->get(['id', 'schedules']);

        foreach ($courses as $course) {
            $schedules = json_decode((string) $course->schedules, true);
            if (! is_array($schedules) || $schedules === []) {
                continue;
            }

            $needsLevel = false;
            foreach ($schedules as $row) {
                if (! is_array($row) || ! array_key_exists('level', $row)) {
                    $needsLevel = true;
                    break;
                }
            }
            if (! $needsLevel) {
                continue;
            }

            $levels = DB::table('course_prices')
                ->where('course_id', $course->id)
                ->whereNotNull('level')
                ->where('level', '!=', '')
                ->pluck('level')
                ->unique()
                ->values()
                ->all();

            $new = [];
            foreach ($schedules as $row) {
                if (! is_array($row)) {
                    continue;
                }
                if (array_key_exists('level', $row)) {
                    $new[] = $row;
                    continue;
                }
                if ($levels === []) {
                    $new[] = array_merge($row, ['level' => null]);
                } else {
                    foreach ($levels as $level) {
                        $new[] = array_merge($row, ['level' => $level]);
                    }
                }
            }

            DB::table('courses')->where('id', $course->id)->update([
                'schedules' => json_encode(array_values($new), JSON_UNESCAPED_UNICODE),
            ]);
        }
    }

    public function down(): void
    {
        // 無法可靠還原「依年級複製」前的資料，保留 level 欄位即可。
    }
};
