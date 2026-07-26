<?php

namespace App\Support;

use App\Models\Classroom;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * 班級固定週期時段（教室 schedules 或舊版 weekday 欄位）的人類可讀彙整。
 */
final class ClassroomRecurringScheduleLabel
{
    private const WEEKDAY_ZH = [
        1 => '一',
        2 => '二',
        3 => '三',
        4 => '四',
        5 => '五',
        6 => '六',
        7 => '日',
    ];

    /**
     * 同一課程下多班級：單一班級只列時段；多班級則「班級名：時段」以分號分隔。
     *
     * @param  Collection<int, Classroom>  $classrooms
     */
    public static function summarizeCollection(Collection $classrooms, bool $hasSchedulesTable): string
    {
        if ($classrooms->isEmpty()) {
            return '—';
        }

        $sorted = $classrooms->sortBy('name')->values();

        if ($sorted->count() === 1) {
            $line = self::forClassroom($sorted->first(), $hasSchedulesTable);

            return $line !== '' ? $line : '—';
        }

        $parts = [];
        foreach ($sorted as $classroom) {
            $line = self::forClassroom($classroom, $hasSchedulesTable);
            if ($line !== '') {
                $parts[] = $classroom->name.'：'.$line;
            }
        }

        return $parts !== [] ? implode('；', $parts) : '—';
    }

    /** 例：週一 14:00—16:00、週三 12:00—14:00 */
    public static function forClassroom(Classroom $classroom, bool $hasSchedulesTable): string
    {
        $segments = [];

        if ($hasSchedulesTable && $classroom->relationLoaded('schedules') && $classroom->schedules->isNotEmpty()) {
            $rows = $classroom->schedules->sort(function ($a, $b): int {
                $wa = (int) $a->weekday;
                $wb = (int) $b->weekday;
                if ($wa !== $wb) {
                    return $wa <=> $wb;
                }

                return strcmp((string) $a->start_time, (string) $b->start_time);
            })->values();
            foreach ($rows as $s) {
                $wd = (int) $s->weekday;
                $segments[] = '週'.(self::WEEKDAY_ZH[$wd] ?? '?').' '
                    .self::formatHm((string) $s->start_time).'—'.self::formatHm((string) $s->end_time);
            }
        }

        if ($segments === [] && $classroom->weekday !== null
            && $classroom->start_time !== null && $classroom->end_time !== null) {
            $wd = (int) $classroom->weekday;
            $segments[] = '週'.(self::WEEKDAY_ZH[$wd] ?? '?').' '
                .self::formatHm((string) $classroom->start_time).'—'.self::formatHm((string) $classroom->end_time);
        }

        $segments = array_values(array_unique(array_filter($segments)));

        return implode('、', $segments);
    }

    private static function formatHm(string $t): string
    {
        $t = trim($t);
        if ($t === '') {
            return '';
        }
        try {
            return Carbon::parse($t)->format('H:i');
        } catch (\Throwable) {
            return strlen($t) >= 5 ? substr($t, 0, 5) : $t;
        }
    }
}
