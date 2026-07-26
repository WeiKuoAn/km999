<?php

namespace App\Models\Concerns;

use App\Models\ClassroomExtraSession;
use Carbon\Carbon;

trait HasExtraSessions
{
    /**
     * 將單次加課日（落在月份內）合併進固定週期的上課日清單（Y-m-d）。
     * JSON 欄位之加課視為全班；資料表加課可依 $forStudentId 篩選受邀學生。
     *
     * @param  array<int, string>  $scheduledYmd
     * @return array<int, string>
     */
    public static function mergeRecurringDatesWithExtras(
        array $scheduledYmd,
        ?array $extraSessions,
        Carbon $monthStart,
        Carbon $monthEnd,
        ?self $classroom = null,
        ?int $forStudentId = null,
    ): array {
        $set = array_fill_keys($scheduledYmd, true);
        foreach ($extraSessions ?? [] as $ex) {
            if (! is_array($ex) || empty($ex['date'])) {
                continue;
            }
            try {
                $d = Carbon::parse($ex['date'])->startOfDay();
            } catch (\Throwable) {
                continue;
            }
            if ($d->lt($monthStart) || $d->gt($monthEnd)) {
                continue;
            }
            $set[$d->toDateString()] = true;
        }

        if ($classroom !== null) {
            $classroom->loadMissing('extraSessionModels.students');
            foreach ($classroom->extraSessionModels as $ex) {
                if (! $ex instanceof ClassroomExtraSession) {
                    continue;
                }
                $d = $ex->session_date->copy()->startOfDay();
                if ($d->lt($monthStart) || $d->gt($monthEnd)) {
                    continue;
                }
                if ($forStudentId !== null && $ex->students->isNotEmpty() && ! $ex->students->contains('id', $forStudentId)) {
                    continue;
                }
                $set[$ex->session_date->toDateString()] = true;
            }
        }

        $keys = array_keys($set);
        sort($keys);

        return $keys;
    }

    /**
     * 僅讀 JSON 欄位（相容舊資料）；新加課請用 resolveExtraSessionForDate。
     *
     * @return array{date?: string, start_time: string, end_time: string}|null
     */
    public static function extraSessionAtDate(?array $extraSessions, string $dateYmd): ?array
    {
        foreach ($extraSessions ?? [] as $ex) {
            if (! is_array($ex) || empty($ex['date'])) {
                continue;
            }
            try {
                if (Carbon::parse($ex['date'])->toDateString() !== $dateYmd) {
                    continue;
                }
            } catch (\Throwable) {
                continue;
            }

            return [
                'date' => $dateYmd,
                'start_time' => self::normalizeTimeToHiS((string) ($ex['start_time'] ?? '')),
                'end_time' => self::normalizeTimeToHiS((string) ($ex['end_time'] ?? '')),
            ];
        }

        return null;
    }

    /**
     * 指定曆日的單次加課時段（資料表優先，其次 JSON）。
     *
     * @return array{date: string, start_time: string, end_time: string}|null
     */
    public function resolveExtraSessionForDate(string $dateYmd): ?array
    {
        $this->loadMissing('extraSessionModels');
        foreach ($this->extraSessionModels as $row) {
            if ($row->session_date->toDateString() !== $dateYmd) {
                continue;
            }

            return [
                'date' => $dateYmd,
                'start_time' => Carbon::parse($row->start_time)->format('H:i:s'),
                'end_time' => Carbon::parse($row->end_time)->format('H:i:s'),
            ];
        }

        return self::extraSessionAtDate($this->extra_sessions, $dateYmd);
    }

    private static function normalizeTimeToHiS(string $t): string
    {
        $t = trim($t);
        if ($t === '') {
            return '';
        }
        if (strlen($t) === 5) {
            return $t.':00';
        }
        if (strlen($t) >= 8) {
            return substr($t, 0, 8);
        }

        return $t;
    }
}
