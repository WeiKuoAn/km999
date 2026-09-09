<?php

namespace App\Support;

use App\Models\Holiday;
use App\Models\ScheduleException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * 實際上課日 = 固定星期課表 − 全校假日 − 停課例外 ＋ 補課例外
 */
final class ScheduleCalendar
{
    /**
     * 全校假日日期（Y-m-d）。
     *
     * @return array<string, true>
     */
    public static function holidaySet(string $from, string $to): array
    {
        return Holiday::query()
            ->whereBetween('date', [$from, $to])
            ->get(['date'])
            ->mapWithKeys(fn (Holiday $h) => [$h->date->toDateString() => true])
            ->all();
    }

    /**
     * 某年級＋科目在區間內的停課日。
     *
     * @return list<string> Y-m-d
     */
    public static function closedDates(?int $gradeLevelId, int $courseId, string $from, string $to): array
    {
        $dates = [];
        foreach (self::exceptionsOverlapping(ScheduleException::TYPE_CLOSURE, $from, $to) as $ex) {
            if (! $ex->appliesToGrade($gradeLevelId) || ! $ex->appliesToCourse($courseId)) {
                continue;
            }
            foreach (self::expandDates($ex->date_from->toDateString(), $ex->date_to->toDateString(), $from, $to) as $ymd) {
                $dates[$ymd] = true;
            }
        }

        return array_keys($dates);
    }

    /**
     * 某年級＋科目在區間內的補課日。
     *
     * @return list<array{date:string, name:string, start_time:?string, end_time:?string}>
     */
    public static function makeupSessions(?int $gradeLevelId, int $courseId, string $from, string $to): array
    {
        $out = [];
        foreach (self::exceptionsOverlapping(ScheduleException::TYPE_MAKEUP, $from, $to) as $ex) {
            if (! $ex->appliesToGrade($gradeLevelId) || ! $ex->appliesToCourse($courseId)) {
                continue;
            }
            foreach (self::expandDates($ex->date_from->toDateString(), $ex->date_to->toDateString(), $from, $to) as $ymd) {
                $out[] = [
                    'date' => $ymd,
                    'name' => $ex->name,
                    'start_time' => $ex->start_time,
                    'end_time' => $ex->end_time,
                ];
            }
        }

        usort($out, fn (array $a, array $b): int => $a['date'] <=> $b['date']);

        return $out;
    }

    /**
     * 前端用：依年級彙整停課／補課（含全校假日合併進 closures 時可另傳 holidays）。
     *
     * @return array{
     *   closures: list<array{date_from:string,date_to:string,name:string,grade_level_id:?int,course_ids:list<int>}>,
     *   makeups: list<array{date_from:string,date_to:string,name:string,grade_level_id:?int,course_ids:list<int>,start_time:?string,end_time:?string}>
     * }
     */
    public static function payloadForRange(string $from, string $to, ?int $gradeLevelId = null): array
    {
        if (! Schema::hasTable('schedule_exceptions')) {
            return ['closures' => [], 'makeups' => []];
        }

        $closures = [];
        $makeups = [];

        foreach (self::exceptionsOverlapping(null, $from, $to) as $ex) {
            if ($gradeLevelId !== null && ! $ex->appliesToGrade($gradeLevelId)) {
                continue;
            }
            $row = [
                'date_from' => $ex->date_from->toDateString(),
                'date_to' => $ex->date_to->toDateString(),
                'name' => $ex->name,
                'grade_level_id' => $ex->grade_level_id !== null ? (int) $ex->grade_level_id : null,
                'course_ids' => $ex->courses->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            ];
            if ($ex->type === ScheduleException::TYPE_MAKEUP) {
                $row['start_time'] = $ex->start_time;
                $row['end_time'] = $ex->end_time;
                $makeups[] = $row;
            } else {
                $closures[] = $row;
            }
        }

        return ['closures' => $closures, 'makeups' => $makeups];
    }

    /**
     * @return \Illuminate\Support\Collection<int, ScheduleException>
     */
    private static function exceptionsOverlapping(?string $type, string $from, string $to)
    {
        if (! Schema::hasTable('schedule_exceptions')) {
            return collect();
        }

        $q = ScheduleException::query()
            ->with(['courses:id,name,course_category_id'])
            ->where('date_from', '<=', $to)
            ->where('date_to', '>=', $from)
            ->orderBy('date_from');

        if ($type !== null) {
            $q->where('type', $type);
        }

        return $q->get();
    }

    /**
     * @return list<string>
     */
    private static function expandDates(string $rangeFrom, string $rangeTo, string $clipFrom, string $clipTo): array
    {
        $start = Carbon::parse(max($rangeFrom, $clipFrom))->startOfDay();
        $end = Carbon::parse(min($rangeTo, $clipTo))->startOfDay();
        if ($start->gt($end)) {
            return [];
        }

        $out = [];
        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $out[] = $d->toDateString();
        }

        return $out;
    }
}
