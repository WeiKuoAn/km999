<?php

namespace App\Models;

use App\Models\Concerns\HasExtraSessions;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

class Classroom extends Model
{
    use HasExtraSessions;

    protected $fillable = [
        'course_id',
        'grade_level_id',
        'teacher_id',
        'name',
        'color',
        'weekday',
        'start_time',
        'end_time',
        'start_date',
        'end_date',
        'active_periods',
        'extra_sessions',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'active_periods' => 'array',
            'extra_sessions' => 'array',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function gradeLevel(): BelongsTo
    {
        return $this->belongsTo(GradeLevel::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(ClassroomSchedule::class);
    }

    /**
     * 單次加課（指定日期）；學生範圍見 students()，無關聯時視為全班。
     *
     * @return HasMany<ClassroomExtraSession, $this>
     */
    public function extraSessionModels(): HasMany
    {
        return $this->hasMany(ClassroomExtraSession::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function reconciliations(): HasMany
    {
        return $this->hasMany(Reconciliation::class);
    }

    /**
     * 班級篩選下拉選項（老師僅自己的班級；管理員全部）。
     *
     * @return Collection<int, Classroom>
     */
    public static function selectOptionsForFilter(?User $user): Collection
    {
        $q = static::query()->orderBy('name')->select(['id', 'name', 'color']);
        if ($user?->role === User::ROLE_TEACHER && ($tid = $user->teacher?->id)) {
            $q->where('teacher_id', $tid);
        }

        return $q->get();
    }

    public static function existsRuleForFilter(?User $user): Exists
    {
        $rule = Rule::exists('classrooms', 'id');
        if ($user?->role === User::ROLE_TEACHER && ($tid = $user->teacher?->id)) {
            $rule->where('teacher_id', $tid);
        }

        return $rule;
    }

    /**
     * 未設定任何開課日期限制（含 active_periods 與舊 start/end 皆無）時為 true；行事曆應顯示全年符合星期的課程。
     */
    public function dateRangeUnrestricted(): bool
    {
        return $this->resolvedActivePeriodRanges() === null;
    }

    /**
     * 行事曆／前端用：開課區間聯集。空陣列表示未限制日期（等同全年有效）。
     *
     * @return list<array{start_date: string|null, end_date: string|null}>
     */
    public function teachingPeriodsForFrontend(): array
    {
        $ranges = $this->resolvedActivePeriodRanges();
        if ($ranges === null) {
            return [];
        }

        return array_map(fn (array $r) => [
            'start_date' => $r['start']?->toDateString(),
            'end_date' => $r['end']?->toDateString(),
        ], $ranges);
    }

    /**
     * 某月內「應出席」的星期日期（依班級開課區間與星期交集後聯集，不重複排序）。
     *
     * @param  array<int, int>  $weekdays  ISO 週一到週日 1–7
     * @return array<int, string>
     */
    public function scheduledWeekdayDatesInMonth(array $weekdays, Carbon $monthStart, Carbon $monthEnd): array
    {
        if ($weekdays === []) {
            return [];
        }

        $ranges = $this->resolvedActivePeriodRanges();
        $weekdaySet = array_values(array_unique(array_map('intval', $weekdays)));

        if ($ranges === null) {
            return $this->enumerateWeekdaysInWindow($monthStart->copy()->startOfDay(), $monthEnd->copy()->startOfDay(), $weekdaySet);
        }

        $dates = [];
        foreach ($ranges as $range) {
            $ws = $monthStart->copy()->startOfDay();
            $we = $monthEnd->copy()->startOfDay();
            if ($range['start'] !== null && $range['start']->greaterThan($ws)) {
                $ws = $range['start']->copy()->startOfDay();
            }
            if ($range['end'] !== null && $range['end']->lessThan($we)) {
                $we = $range['end']->copy()->startOfDay();
            }
            if ($ws->greaterThan($we)) {
                continue;
            }
            foreach ($this->enumerateWeekdaysInWindow($ws, $we, $weekdaySet) as $d) {
                $dates[$d] = true;
            }
        }

        $keys = array_keys($dates);
        sort($keys);

        return $keys;
    }

    /**
     * @return list<array{start: ?Carbon, end: ?Carbon}>|null null = 未設定區間（視為全程有效）
     */
    public function resolvedActivePeriodRanges(): ?array
    {
        $fromJson = $this->normalizeStoredActivePeriods($this->active_periods);
        if ($fromJson !== null) {
            return $fromJson;
        }

        if ($this->start_date || $this->end_date) {
            return [[
                'start' => $this->start_date?->copy()->startOfDay(),
                'end' => $this->end_date?->copy()->startOfDay(),
            ]];
        }

        return null;
    }

    /**
     * 某日期是否落在班級開課區間內（未設定區間時視為全程有效）。
     */
    public function isActiveOnDate(Carbon $date): bool
    {
        $ranges = $this->resolvedActivePeriodRanges();
        if ($ranges === null) {
            return true;
        }

        $day = $date->copy()->startOfDay();
        foreach ($ranges as $range) {
            if ($range['start'] !== null && $day->lt($range['start'])) {
                continue;
            }
            if ($range['end'] !== null && $day->gt($range['end'])) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * 表單／請求：整理為存入 JSON 的列（僅含至少填一端的列）；null 表示全程有效。
     *
     * @param  array<int, array<string, mixed>>|null  $input
     * @return list<array{start_date: ?string, end_date: ?string}>|null
     */
    public static function normalizeActivePeriodsInput(?array $input): ?array
    {
        if ($input === null || $input === []) {
            return null;
        }

        $rows = [];
        foreach ($input as $row) {
            if (! is_array($row)) {
                continue;
            }
            $startRaw = $row['start_date'] ?? null;
            $endRaw = $row['end_date'] ?? null;
            $s = ($startRaw !== null && $startRaw !== '') ? Carbon::parse((string) $startRaw)->toDateString() : null;
            $e = ($endRaw !== null && $endRaw !== '') ? Carbon::parse((string) $endRaw)->toDateString() : null;
            if ($s === null && $e === null) {
                continue;
            }
            $rows[] = ['start_date' => $s, 'end_date' => $e];
        }

        return $rows === [] ? null : $rows;
    }

    /**
     * @return list<array{start: ?Carbon, end: ?Carbon}>|null null = 改採舊欄位或全程有效
     */
    private function normalizeStoredActivePeriods(mixed $raw): ?array
    {
        if (! is_array($raw) || $raw === []) {
            return null;
        }

        $ranges = [];
        foreach ($raw as $row) {
            if (! is_array($row)) {
                continue;
            }
            $startRaw = $row['start_date'] ?? null;
            $endRaw = $row['end_date'] ?? null;
            if ($startRaw === null && $endRaw === null) {
                continue;
            }
            $ranges[] = [
                'start' => $startRaw !== null ? Carbon::parse((string) $startRaw)->startOfDay() : null,
                'end' => $endRaw !== null ? Carbon::parse((string) $endRaw)->startOfDay() : null,
            ];
        }

        return $ranges === [] ? null : $ranges;
    }

    /**
     * @param  array<int, int>  $weekdaySet
     * @return array<int, string>
     */
    private function enumerateWeekdaysInWindow(Carbon $windowStart, Carbon $windowEnd, array $weekdaySet): array
    {
        $dates = [];
        for ($date = $windowStart->copy(); $date->lessThanOrEqualTo($windowEnd); $date->addDay()) {
            if (in_array($date->isoWeekday(), $weekdaySet, true)) {
                $dates[] = $date->toDateString();
            }
        }

        return $dates;
    }
}
