<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\ClassroomExtraSession;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Support\CourseTuition;
use App\Support\MakeupAttendanceNote;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
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

    public function index(Request $request): Response
    {
        $today = Carbon::today();
        $todayWeekday = $today->isoWeekday();

        $validated = $request->validate([
            'weekday' => ['nullable', 'string', 'max:2'],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'classroom_id' => ['nullable', 'integer', Classroom::existsRuleForFilter(auth()->user())],
            'teacher_id' => ['nullable', 'integer', 'exists:teachers,id'],
            'roll_status' => ['nullable', 'string', 'max:16'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $user = auth()->user();
        [$filterTeacherId, $canFilterByTeacher, $teacherFilterValue, $forceEmptyTeacherScope] = $this->resolveIndexTeacherFilter(
            $user,
            isset($validated['teacher_id']) ? (int) $validated['teacher_id'] : null,
        );

        [$filterAllWeekdays, $weekday, $weekdayFilterValue] = $this->resolveWeekdayFilter($request, $todayWeekday);
        $hasSchedulesTable = Schema::hasTable('classroom_schedules');
        $courseId = isset($validated['course_id']) ? (int) $validated['course_id'] : null;
        $classroomId = isset($validated['classroom_id']) ? (int) $validated['classroom_id'] : null;
        $rawRoll = (string) ($validated['roll_status'] ?? '');
        $rollStatus = in_array($rawRoll, ['done', 'pending'], true) ? $rawRoll : null;

        $fromRaw = (string) ($validated['from'] ?? '');
        $toRaw = (string) ($validated['to'] ?? '');
        if ($fromRaw !== '' && $toRaw !== '') {
            return $this->indexDateRange(
                $fromRaw,
                $toRaw,
                $courseId,
                $classroomId,
                $rollStatus,
                $hasSchedulesTable,
                $today,
                $todayWeekday,
                $filterAllWeekdays ? null : $weekday,
                $weekdayFilterValue,
                $filterTeacherId,
                $canFilterByTeacher,
                $teacherFilterValue,
                $forceEmptyTeacherScope,
            );
        }

        $classroomsQuery = Classroom::query()->where('status', 'active');

        if (! $filterAllWeekdays) {
            if ($hasSchedulesTable) {
                $classroomsQuery->whereHas('schedules', fn ($q) => $q->where('weekday', $weekday));
            } else {
                $classroomsQuery->where('weekday', $weekday);
            }
        } elseif ($hasSchedulesTable) {
            $classroomsQuery->where(function ($q): void {
                $q->whereHas('schedules')
                    ->orWhereNotNull('weekday')
                    ->orWhereNotNull('extra_sessions')
                    ->orWhereHas('extraSessionModels');
            });
        }

        $this->applyTeacherScopeToQuery($classroomsQuery, $filterTeacherId, $forceEmptyTeacherScope);

        if ($courseId !== null) {
            $classroomsQuery->where(function ($q) use ($courseId, $hasSchedulesTable) {
                $q->where('course_id', $courseId);
                if ($hasSchedulesTable) {
                    $q->orWhereHas('schedules', fn ($s) => $s->where('course_id', $courseId));
                }
            });
        }

        $classroomFilterOptions = (clone $classroomsQuery)->orderBy('name')->get(['id', 'name', 'color']);

        if ($classroomId !== null) {
            $classroomsQuery->where('id', $classroomId);
        }

        $classroomsQuery->with([
            'course.courseCategory',
            'course.coursePrices',
            'teacher:id,name',
        ]);
        if ($hasSchedulesTable) {
            $classroomsQuery->with([
                'schedules' => fn ($q) => $filterAllWeekdays
                    ? $q->orderBy('weekday')->orderBy('start_time')
                    : $q->where('weekday', $weekday)->orderBy('start_time'),
            ]);
        }

        $classrooms = $classroomsQuery->orderBy('name')->get();
        $monthStart = $today->copy()->startOfMonth();
        $monthEnd = $today->copy()->endOfMonth();
        $classrooms = $this->mergeClassroomsWithExtraSessionsForWeekdayIndex(
            $classrooms,
            $filterAllWeekdays ? null : $weekday,
            $monthStart,
            $monthEnd,
            $courseId,
            $classroomId,
            $hasSchedulesTable,
            $filterTeacherId,
            $forceEmptyTeacherScope,
        );
        $classrooms = $classrooms->filter(
            fn (Classroom $c) => $c->isActiveOnDate($today)
        )->values();

        $courseOptions = Course::query()
            ->with('courseCategory:id,name')
            ->where('status', 'active')
            ->join('course_categories', 'courses.course_category_id', '=', 'course_categories.id')
            ->orderBy('course_categories.sort_order')
            ->orderBy('course_categories.name')
            ->orderBy('courses.name')
            ->select('courses.*')
            ->get();

        $classroomsPayload = $this->mapClassroomsRollPayload($classrooms, $today, $hasSchedulesTable);
        foreach ($classroomsPayload as $i => $row) {
            $cid = (int) $row['id'];
            $c = $classrooms->firstWhere('id', $cid);
            if ($c instanceof Classroom) {
                $classroomsPayload[$i] = $filterAllWeekdays
                    ? $this->appendIndexExtraSlotsForAllWeekdays($classroomsPayload[$i], $c, $monthStart, $monthEnd)
                    : $this->appendIndexExtraSlotsForFilteredWeekday(
                        $classroomsPayload[$i],
                        $c,
                        $weekday,
                        $monthStart,
                        $monthEnd,
                    );
            }
        }

        if ($rollStatus === 'done') {
            $classroomsPayload = array_values(array_filter(
                $classroomsPayload,
                fn (array $row) => ($row['roll_call_done_for_today'] ?? false) === true
            ));
        } elseif ($rollStatus === 'pending') {
            $classroomsPayload = array_values(array_filter(
                $classroomsPayload,
                fn (array $row) => ($row['roll_call_done_for_today'] ?? false) === false
            ));
        }

        return Inertia::render('Attendances/Index', [
            'classrooms' => $classroomsPayload,
            'courseOptions' => $courseOptions,
            'classroomFilterOptions' => $classroomFilterOptions,
            'teacherOptions' => $canFilterByTeacher
                ? Teacher::query()->where('status', 'active')->orderBy('name')->get(['id', 'name'])->all()
                : [],
            'canFilterByTeacher' => $canFilterByTeacher,
            'filters' => [
                'weekday' => $weekdayFilterValue,
                'course_id' => $courseId === null ? '' : (string) $courseId,
                'classroom_id' => $classroomId === null ? '' : (string) $classroomId,
                'teacher_id' => $teacherFilterValue,
                'roll_status' => $rollStatus === 'done' || $rollStatus === 'pending' ? $rollStatus : '',
            ],
            'defaultWeekday' => (string) $todayWeekday,
            'todayDate' => $today->toDateString(),
            'todayWeekdayLabel' => '週'.(self::WEEKDAY_ZH[$todayWeekday] ?? ''),
            'selectedWeekdayLabel' => $filterAllWeekdays ? '全部' : '週'.(self::WEEKDAY_ZH[$weekday] ?? ''),
        ]);
    }

    /**
     * 日期區間模式：將區間內每個有排課（含加課）的日期展開成一列，供逐日補登點名。
     */
    private function indexDateRange(
        string $fromRaw,
        string $toRaw,
        ?int $courseId,
        ?int $classroomId,
        ?string $rollStatus,
        bool $hasSchedulesTable,
        Carbon $today,
        int $todayWeekday,
        ?int $weekdayFilter = null,
        string $weekdayFilterValue = '',
        ?int $filterTeacherId = null,
        bool $canFilterByTeacher = false,
        string $teacherFilterValue = '',
        bool $forceEmptyTeacherScope = false,
    ): Response {
        $start = Carbon::parse($fromRaw)->startOfDay();
        $end = Carbon::parse($toRaw)->startOfDay();
        if ($end->lt($start)) {
            [$start, $end] = [$end, $start];
        }

        // 避免一次展開過長區間造成效能問題（最多 92 天）。
        $maxDays = 92;
        $rangeTruncated = false;
        if ($start->diffInDays($end) > $maxDays) {
            $end = $start->copy()->addDays($maxDays);
            $rangeTruncated = true;
        }

        $classroomsQuery = Classroom::query()->where('status', 'active');
        $this->applyTeacherScopeToQuery($classroomsQuery, $filterTeacherId, $forceEmptyTeacherScope);
        $hasSchedulesTableForFilter = Schema::hasTable('classroom_schedules');
        if ($courseId !== null) {
            $classroomsQuery->where(function ($q) use ($courseId, $hasSchedulesTableForFilter) {
                $q->where('course_id', $courseId);
                if ($hasSchedulesTableForFilter) {
                    $q->orWhereHas('schedules', fn ($s) => $s->where('course_id', $courseId));
                }
            });
        }

        $classroomFilterOptions = (clone $classroomsQuery)->orderBy('name')->get(['id', 'name', 'color']);

        if ($classroomId !== null) {
            $classroomsQuery->where('id', $classroomId);
        }

        $classroomsQuery->with([
            'course.courseCategory',
            'course.coursePrices',
            'teacher:id,name',
            'extraSessionModels.students:id',
        ]);
        if ($hasSchedulesTable) {
            $classroomsQuery->with(['schedules:id,classroom_id,weekday,start_time,end_time']);
        }

        $classrooms = $classroomsQuery->orderBy('name')->get();

        $rows = [];
        foreach ($classrooms as $classroom) {
            $base = $classroom->toArray();
            $cursor = $start->copy();
            while ($cursor->lte($end)) {
                $dateStr = $cursor->toDateString();
                $weekday = $cursor->isoWeekday();

                // 若有指定星期，僅展開該星期的日期。
                if ($weekdayFilter !== null && $weekday !== $weekdayFilter) {
                    $cursor->addDay();

                    continue;
                }

                $slots = $this->scheduleSlotsForDate($classroom, $cursor, $hasSchedulesTable);
                if ($slots !== []) {
                    $rows[] = $this->buildDateRangeRow($base, $classroom, $dateStr, $weekday, $slots);
                }

                $cursor->addDay();
            }
        }

        usort($rows, function (array $a, array $b): int {
            return [$a['date'], $a['name']] <=> [$b['date'], $b['name']];
        });

        if ($rollStatus === 'done') {
            $rows = array_values(array_filter($rows, fn (array $r) => ($r['roll_call_done_for_today'] ?? false) === true));
        } elseif ($rollStatus === 'pending') {
            $rows = array_values(array_filter($rows, fn (array $r) => ($r['roll_call_done_for_today'] ?? false) === false));
        }

        $courseOptions = Course::query()
            ->with('courseCategory:id,name')
            ->where('status', 'active')
            ->join('course_categories', 'courses.course_category_id', '=', 'course_categories.id')
            ->orderBy('course_categories.sort_order')
            ->orderBy('course_categories.name')
            ->orderBy('courses.name')
            ->select('courses.*')
            ->get();

        return Inertia::render('Attendances/Index', [
            'classrooms' => $rows,
            'courseOptions' => $courseOptions,
            'classroomFilterOptions' => $classroomFilterOptions,
            'teacherOptions' => $canFilterByTeacher
                ? Teacher::query()->where('status', 'active')->orderBy('name')->get(['id', 'name'])->all()
                : [],
            'canFilterByTeacher' => $canFilterByTeacher,
            'filters' => [
                'weekday' => $weekdayFilterValue,
                'course_id' => $courseId === null ? '' : (string) $courseId,
                'classroom_id' => $classroomId === null ? '' : (string) $classroomId,
                'teacher_id' => $teacherFilterValue,
                'roll_status' => $rollStatus === 'done' || $rollStatus === 'pending' ? $rollStatus : '',
                'from' => $start->toDateString(),
                'to' => $end->toDateString(),
            ],
            'defaultWeekday' => (string) $todayWeekday,
            'todayDate' => $today->toDateString(),
            'todayWeekdayLabel' => '週'.(self::WEEKDAY_ZH[$todayWeekday] ?? ''),
            'selectedWeekdayLabel' => $weekdayFilter === null ? '全部' : '週'.(self::WEEKDAY_ZH[$weekdayFilter] ?? ''),
            'dateRangeMode' => true,
            'rangeTruncated' => $rangeTruncated,
        ]);
    }

    /**
     * 取得某班級在某日的上課時段（含一般排課與加課）。
     *
     * @return array<int, array{weekday: int, start_time: string, end_time: string}>
     */
    private function scheduleSlotsForDate(Classroom $classroom, Carbon $date, bool $hasSchedulesTable): array
    {
        $weekday = $date->isoWeekday();
        $dateStr = $date->toDateString();

        if ($hasSchedulesTable) {
            $slots = $classroom->schedules
                ->where('weekday', $weekday)
                ->sortBy('start_time')
                ->map(fn ($s) => [
                    'weekday' => (int) $s->weekday,
                    'start_time' => (string) $s->start_time,
                    'end_time' => (string) $s->end_time,
                ])
                ->values()
                ->all();
        } else {
            $slots = ((int) $classroom->weekday === $weekday && $classroom->start_time !== null && $classroom->end_time !== null)
                ? [[
                    'weekday' => (int) $classroom->weekday,
                    'start_time' => (string) $classroom->start_time,
                    'end_time' => (string) $classroom->end_time,
                ]]
                : [];
        }

        if ($slots !== [] && ! $classroom->isActiveOnDate($date)) {
            $slots = [];
        }

        if ($slots === []) {
            $extra = $classroom->resolveExtraSessionForDate($dateStr);
            if ($extra !== null && $classroom->isActiveOnDate($date)) {
                $slots = [[
                    'weekday' => $weekday,
                    'start_time' => (string) ($extra['start_time'] ?? ''),
                    'end_time' => (string) ($extra['end_time'] ?? ''),
                ]];
            }
        }

        return $slots;
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<int, array{weekday: int, start_time: string, end_time: string}>  $slots
     * @return array<string, mixed>
     */
    private function buildDateRangeRow(array $base, Classroom $classroom, string $dateStr, int $weekday, array $slots): array
    {
        $row = $base;
        $row['today_schedules'] = $slots;
        $row['date'] = $dateStr;
        $row['weekday_label'] = '週'.(self::WEEKDAY_ZH[$weekday] ?? '');
        $row['roll_call_done_for_today'] = $this->isRollCallCompleteForDay($classroom, $dateStr);

        return $row;
    }

    /**
     * @return array{0: ?int, 1: bool, 2: string, 3: bool} [filterTeacherId, canFilterByTeacher, teacherFilterValue, forceEmpty]
     */
    private function resolveIndexTeacherFilter(?User $user, ?int $requestedTeacherId): array
    {
        if ($user?->role === User::ROLE_TEACHER) {
            $id = $user->teacher?->id;

            return [$id, false, '', $id === null];
        }

        $canFilter = $user?->role !== User::ROLE_TEACHER;
        if ($requestedTeacherId === null) {
            return [null, $canFilter, '', false];
        }

        return [$requestedTeacherId, $canFilter, (string) $requestedTeacherId, false];
    }

    private function applyTeacherScopeToQuery($query, ?int $filterTeacherId, bool $forceEmpty): void
    {
        if ($forceEmpty) {
            $query->whereRaw('1 = 0');

            return;
        }

        if ($filterTeacherId !== null) {
            $query->where('teacher_id', $filterTeacherId);
        }
    }

    /**
     * @return array{0: bool, 1: int|null, 2: string} [filterAllWeekdays, weekdayForQuery, filterValue]
     */
    private function resolveWeekdayFilter(Request $request, int $todayWeekday): array
    {
        if (! $request->has('weekday')) {
            return [false, $todayWeekday, (string) $todayWeekday];
        }

        $raw = $request->input('weekday');
        if ($raw === '' || $raw === null) {
            return [true, null, ''];
        }

        $w = (int) $raw;
        if ($w < 1 || $w > 7) {
            return [false, $todayWeekday, (string) $todayWeekday];
        }

        return [false, $w, (string) $w];
    }

    public function live(Request $request): Response|RedirectResponse
    {
        $user = auth()->user();
        $validated = $request->validate([
            'teacher_id' => ['nullable', 'integer', 'exists:teachers,id'],
        ]);

        $now = Carbon::now();
        $today = $now->copy()->startOfDay();
        $weekday = $now->isoWeekday();
        $timeStr = $now->format('H:i:s');
        $hasSchedulesTable = Schema::hasTable('classroom_schedules');

        $classroomsQuery = Classroom::query()->where('status', 'active');

        if ($hasSchedulesTable) {
            $classroomsQuery->whereExists(function ($q) use ($weekday, $timeStr) {
                $q->from('classroom_schedules')
                    ->selectRaw('1')
                    ->whereColumn('classroom_schedules.classroom_id', 'classrooms.id')
                    ->where('classroom_schedules.weekday', $weekday)
                    ->where('classroom_schedules.start_time', '<=', $timeStr)
                    ->where('classroom_schedules.end_time', '>=', $timeStr);
            });
        } else {
            $classroomsQuery->where('weekday', $weekday)
                ->whereNotNull('start_time')
                ->whereNotNull('end_time')
                ->where('start_time', '<=', $timeStr)
                ->where('end_time', '>=', $timeStr);
        }

        if ($user?->role === User::ROLE_TEACHER) {
            $teacherId = $user->teacher?->id;
            if ($teacherId === null) {
                $classroomsQuery->whereRaw('1 = 0');
            } else {
                $classroomsQuery->where('teacher_id', $teacherId);
            }
        } elseif (! empty($validated['teacher_id'])) {
            $classroomsQuery->where('teacher_id', (int) $validated['teacher_id']);
        }

        $classroomsQuery->with([
            'course.courseCategory',
            'course.coursePrices',
            'teacher:id,name',
        ]);
        if ($hasSchedulesTable) {
            $classroomsQuery->with([
                'schedules' => fn ($q) => $q
                    ->where('weekday', $weekday)
                    ->orderBy('start_time'),
            ]);
        }

        $classrooms = $classroomsQuery->orderBy('name')->get();

        $extraLive = $this->classroomsMatchingLiveExtraSessions(
            $user,
            isset($validated['teacher_id']) ? (int) $validated['teacher_id'] : null,
            $hasSchedulesTable,
            $now,
        );
        $classrooms = $classrooms->merge($extraLive)->unique('id')->sortBy('name')->values();

        $payload = $this->mapClassroomsRollPayload($classrooms, $today, $hasSchedulesTable);
        foreach ($payload as $i => $row) {
            $c = $classrooms->firstWhere('id', (int) $row['id']);
            if ($c instanceof Classroom) {
                $row = $this->prependTodayExtraSessionForLive($row, $c, $now);
            }
            $payload[$i] = $this->filterSchedulesForLiveSlot($row, $now);
        }

        if (count($payload) === 1) {
            return redirect()->route('attendances.roll-call', [
                'classroom' => (int) $payload[0]['id'],
                'date' => $today->toDateString(),
            ]);
        }

        $canFilterByTeacher = $user?->role !== User::ROLE_TEACHER;
        $teacherOptions = $canFilterByTeacher
            ? Teacher::query()->where('status', 'active')->orderBy('name')->get(['id', 'name'])->all()
            : [];

        return Inertia::render('Attendances/LiveRollCall', [
            'classrooms' => $payload,
            'teacherOptions' => $teacherOptions,
            'filters' => [
                'teacher_id' => $user?->role === User::ROLE_TEACHER
                    ? ''
                    : (isset($validated['teacher_id']) ? (string) (int) $validated['teacher_id'] : ''),
            ],
            'todayDate' => $today->toDateString(),
            'nowDisplay' => $now->format('Y-m-d H:i'),
            'currentWeekdayLabel' => '週'.(self::WEEKDAY_ZH[$weekday] ?? ''),
            'canFilterByTeacher' => $canFilterByTeacher,
            'serverTimezone' => config('app.timezone'),
        ]);
    }

    /**
     * 總覽頁：目前正在上課、可點名的班級。
     *
     * @return array<int, array<string, mixed>>
     */
    public function liveClassesForDashboard(?User $user, ?int $filterTeacherId = null): array
    {
        $now = Carbon::now();
        $today = $now->copy()->startOfDay();
        $weekday = $now->isoWeekday();
        $timeStr = $now->format('H:i:s');
        $hasSchedulesTable = Schema::hasTable('classroom_schedules');

        $classroomsQuery = Classroom::query()->where('status', 'active');

        if ($hasSchedulesTable) {
            $classroomsQuery->whereExists(function ($q) use ($weekday, $timeStr) {
                $q->from('classroom_schedules')
                    ->selectRaw('1')
                    ->whereColumn('classroom_schedules.classroom_id', 'classrooms.id')
                    ->where('classroom_schedules.weekday', $weekday)
                    ->where('classroom_schedules.start_time', '<=', $timeStr)
                    ->where('classroom_schedules.end_time', '>=', $timeStr);
            });
        } else {
            $classroomsQuery->where('weekday', $weekday)
                ->whereNotNull('start_time')
                ->whereNotNull('end_time')
                ->where('start_time', '<=', $timeStr)
                ->where('end_time', '>=', $timeStr);
        }

        if ($user?->role === User::ROLE_TEACHER) {
            $teacherId = $user->teacher?->id;
            if ($teacherId === null) {
                $classroomsQuery->whereRaw('1 = 0');
            } else {
                $classroomsQuery->where('teacher_id', $teacherId);
            }
        } elseif ($filterTeacherId !== null) {
            $classroomsQuery->where('teacher_id', $filterTeacherId);
        }

        $classroomsQuery->with([
            'course.courseCategory',
            'teacher:id,name',
        ]);
        if ($hasSchedulesTable) {
            $classroomsQuery->with([
                'schedules' => fn ($q) => $q
                    ->where('weekday', $weekday)
                    ->orderBy('start_time'),
            ]);
        }

        $classrooms = $classroomsQuery->orderBy('name')->get();
        $extraLive = $this->classroomsMatchingLiveExtraSessions($user, $filterTeacherId, $hasSchedulesTable, $now);
        $classrooms = $classrooms->merge($extraLive)->unique('id')->sortBy('name')->values();

        $todayStr = $today->toDateString();
        $rows = [];
        foreach ($classrooms as $classroom) {
            $base = $this->mapClassroomsRollPayload(new EloquentCollection([$classroom]), $today)[0] ?? null;
            if ($base === null) {
                continue;
            }
            $base = $this->prependTodayExtraSessionForLive($base, $classroom, $now);
            $base = $this->filterSchedulesForLiveSlot($base, $now);
            $rows[] = $this->formatDashboardRollCallRow($base, $classroom, $todayStr);
        }

        return $rows;
    }

    /**
     * 總覽頁：近 N 日內尚未完成點名的班級與日期（補點名待辦）。
     *
     * @return array<int, array<string, mixed>>
     */
    public function pendingRollCallsForDashboard(
        ?User $user,
        ?int $filterTeacherId = null,
        int $lookbackDays = 14,
        int $limit = 30,
    ): array {
        $today = Carbon::today();
        $start = $today->copy()->subDays(max(1, $lookbackDays));
        $hasSchedulesTable = Schema::hasTable('classroom_schedules');

        $classroomsQuery = Classroom::query()->where('status', 'active');
        if ($user?->role === User::ROLE_TEACHER) {
            $teacherId = $user->teacher?->id;
            if ($teacherId === null) {
                $classroomsQuery->whereRaw('1 = 0');
            } else {
                $classroomsQuery->where('teacher_id', $teacherId);
            }
        } elseif ($filterTeacherId !== null) {
            $classroomsQuery->where('teacher_id', $filterTeacherId);
        }

        $classroomsQuery->with([
            'course.courseCategory',
            'teacher:id,name',
            'extraSessionModels.students:id',
        ]);
        if ($hasSchedulesTable) {
            $classroomsQuery->with('schedules:id,classroom_id,weekday,start_time,end_time');
        }

        $classrooms = $classroomsQuery->orderBy('name')->get();
        $items = [];

        foreach ($classrooms as $classroom) {
            $cursor = $start->copy();
            while ($cursor->lte($today)) {
                $dateStr = $cursor->toDateString();
                $slots = $this->scheduleSlotsForDate($classroom, $cursor, $hasSchedulesTable);
                if ($slots !== [] && ! $this->isRollCallCompleteForDay($classroom, $dateStr)) {
                    $enrolled = $this->requiredStudentIdsForRollCallDay($classroom, $dateStr)->count();
                    $attendanceCount = Attendance::query()
                        ->where('classroom_id', $classroom->id)
                        ->whereDate('class_date', $dateStr)
                        ->count();
                    $items[] = [
                        'classroom_id' => $classroom->id,
                        'classroom_name' => $classroom->name,
                        'classroom_color' => $classroom->color,
                        'date' => $dateStr,
                        'weekday_label' => '週'.(self::WEEKDAY_ZH[$cursor->isoWeekday()] ?? ''),
                        'teacher_name' => $classroom->teacher?->name,
                        'course_label' => $this->dashboardCourseLabel($classroom),
                        'enrolled_count' => $enrolled,
                        'attendance_count' => $attendanceCount,
                        'roll_call_href' => '/attendances/classrooms/'.$classroom->id.'/roll-call?date='.urlencode($dateStr),
                    ];
                }
                $cursor->addDay();
            }
        }

        usort($items, fn (array $a, array $b) => [$b['date'], $a['classroom_name']] <=> [$a['date'], $b['classroom_name']]);

        return array_slice($items, 0, $limit);
    }

    /** @param  array<string, mixed>  $row */
    private function formatDashboardRollCallRow(array $row, Classroom $classroom, string $dateStr): array
    {
        $schedules = collect($row['today_schedules'] ?? [])->map(function ($s) {
            $start = substr((string) ($s['start_time'] ?? ''), 0, 5);
            $end = substr((string) ($s['end_time'] ?? ''), 0, 5);

            return $start.' — '.$end;
        })->implode('、');

        return [
            'classroom_id' => (int) $row['id'],
            'classroom_name' => (string) $row['name'],
            'classroom_color' => $row['color'] ?? null,
            'teacher_name' => $row['teacher']['name'] ?? null,
            'course_label' => $this->dashboardCourseLabel($classroom),
            'schedule_text' => $schedules !== '' ? $schedules : '—',
            'roll_call_done' => (bool) ($row['roll_call_done_for_today'] ?? false),
            'roll_call_href' => '/attendances/classrooms/'.(int) $row['id'].'/roll-call?date='.urlencode($dateStr),
        ];
    }

    private function dashboardCourseLabel(Classroom $classroom): string
    {
        $course = $classroom->course;
        $cat = $course?->courseCategory?->name;
        $name = $course?->name ?? '—';

        return ($cat ? $cat.' / ' : '').$name;
    }

    public function rollCall(Request $request, Classroom $classroom): Response
    {
        if ($classroom->status !== 'active') {
            abort(403, '僅能上課中的班級進行點名。');
        }

        $this->authorizeTeacherClassroom($classroom);

        $classroom->load([
            'course.courseCategory',
            'course.coursePrices',
            'teacher:id,name',
        ]);
        $hasSchedulesTable = Schema::hasTable('classroom_schedules');
        if ($hasSchedulesTable) {
            $classroom->load('schedules:id,classroom_id,weekday,start_time,end_time');
        }

        $requestedDate = $request->query('date');
        $classDate = is_string($requestedDate) && $requestedDate !== ''
            ? $requestedDate
            : $this->defaultRollCallDateForClassroom($classroom, Carbon::today(), $hasSchedulesTable);
        if (! $this->isValidDateString($classDate)) {
            abort(404);
        }
        $classroom->loadMissing('extraSessionModels.students');

        $enrollments = Enrollment::query()
            ->where('classroom_id', $classroom->id)
            ->where('status', 'active')
            ->whereHas('student', fn ($q) => $q->where('status', 'active'))
            ->with('student:id,name,phone')
            ->orderBy('id')
            ->get();

        $extraRow = $classroom->extraSessionModels->first(fn (ClassroomExtraSession $r) => $r->session_date->toDateString() === $classDate);
        if ($extraRow !== null && $extraRow->students->isNotEmpty()) {
            $allowed = $extraRow->students->pluck('id')->all();
            $enrollments = $enrollments->whereIn('student_id', $allowed)->values();
        }

        $enrolledStudentIds = $enrollments->pluck('student_id')->all();
        $leftEnrollmentStudentIds = $this->leftEnrollmentStudentIdsForClassroom((int) $classroom->id);

        $attendances = Attendance::query()
            ->where('classroom_id', $classroom->id)
            ->whereDate('class_date', $classDate)
            ->with('student:id,name,phone')
            ->get()
            ->keyBy('student_id');

        $rows = [];

        foreach ($enrollments as $enrollment) {
            $student = $enrollment->student;
            if ($student === null) {
                continue;
            }
            $a = $attendances->get($student->id);
            $rawNote = (string) ($a?->note ?? '');
            $legacyMakeup = str_starts_with($rawNote, '補課日期:');
            // 補課的「補課時間」現在代表原請假日（被補的那一天）。
            $makeupDate = ($a?->status === 'makeup' || $legacyMakeup)
                ? (MakeupAttendanceNote::originalMissedDate($rawNote) ?? '')
                : '';
            $baseStatus = $a?->status === 'extra'
                ? 'extra'
                : (($a?->status === 'makeup' || $legacyMakeup) ? 'makeup' : ($a?->status ?? 'present'));
            $normalizedStatus = $baseStatus === 'extra' ? 'extra' : $this->normalizeRollCallStatus($baseStatus);
            $rows[] = [
                'attendance_id' => $a?->id,
                'student_id' => $student->id,
                'student_name' => $student->name,
                'student_phone' => $student->phone,
                'from_enrollment' => true,
                'status' => $normalizedStatus,
                'makeup_date' => $makeupDate,
                'note' => $legacyMakeup ? '' : $rawNote,
                'makeup_for_classroom_id' => $a?->makeup_for_classroom_id
                    ?? ($normalizedStatus === 'makeup' ? $classroom->id : null),
            ];
        }

        foreach ($attendances->values() as $a) {
            if (! in_array($a->student_id, $enrolledStudentIds, true) && $a->student !== null) {
                $rawNote = (string) ($a->note ?? '');
                $legacyMakeup = str_starts_with($rawNote, '補課日期:');
                $makeupDate = ($a->status === 'makeup' || $legacyMakeup)
                    ? (MakeupAttendanceNote::originalMissedDate($rawNote) ?? '')
                    : '';
                $baseStatus = $a->status === 'extra'
                    ? 'extra'
                    : (($a->status === 'makeup' || $legacyMakeup) ? 'makeup' : $a->status);
                $rows[] = [
                    'attendance_id' => $a->id,
                    'student_id' => $a->student_id,
                    'student_name' => $a->student->name,
                    'student_phone' => $a->student->phone,
                    'from_enrollment' => false,
                    'protected' => in_array((int) $a->student_id, $leftEnrollmentStudentIds, true),
                    'status' => $baseStatus === 'extra' ? 'extra' : $this->normalizeRollCallStatus($baseStatus),
                    'makeup_date' => $makeupDate,
                    'note' => $legacyMakeup ? '' : $rawNote,
                    'makeup_for_classroom_id' => $a->makeup_for_classroom_id,
                ];
            }
        }

        $studentsForAdd = Student::query()
            ->where('status', 'active')
            ->orderBy('id')
            ->get(['id', 'name', 'phone']);

        // 該學生在籍的課程選項（補課時可選「補哪一門課」，用於學費歸帳）。
        $makeupClassOptionsQuery = Enrollment::query()
            ->where('status', 'active')
            ->whereHas('classroom', fn ($q) => $q->where('status', 'active'));
        $this->scopeMakeupClassOptionsForTeacher($makeupClassOptionsQuery);
        $makeupClassOptions = $makeupClassOptionsQuery
            ->with(['classroom.course.courseCategory'])
            ->get()
            ->groupBy('student_id')
            ->map(fn ($items) => $items
                ->sortBy(fn (Enrollment $e) => $e->classroom?->name ?? '')
                ->map(fn (Enrollment $e) => [
                    'classroom_id' => (int) $e->classroom_id,
                    'label' => $this->makeupClassLabel($e->classroom),
                ])
                ->values()
                ->all())
            ->all();

        // 每位學生「在各班的請假日期」，供補課時直接挑選被補的那一天。
        // 結構：leaveDates[student_id][classroom_id] = ['Y-m-d', ...]
        $leaveDates = Attendance::query()
            ->where('status', 'excused')
            ->whereHas('classroom', fn ($q) => $q->where('status', 'active'))
            ->get(['student_id', 'classroom_id', 'class_date'])
            ->groupBy('student_id')
            ->map(fn ($byStudent) => $byStudent
                ->groupBy('classroom_id')
                ->map(fn ($items) => $items
                    ->map(fn ($a) => Carbon::parse($a->class_date)->toDateString())
                    ->unique()
                    ->sortDesc()
                    ->values()
                    ->all())
                ->all())
            ->all();

        $d = Carbon::parse($classDate);
        $extra = $classroom->resolveExtraSessionForDate($d->toDateString());
        $scheduleForDate = $hasSchedulesTable
            ? $classroom->schedules
                ->where('weekday', $d->isoWeekday())
                ->sortBy('start_time')
                ->values()
                ->map(fn ($s) => [
                    'weekday' => $s->weekday,
                    'start_time' => $s->start_time,
                    'end_time' => $s->end_time,
                ])
                ->all()
            : (($classroom->weekday === $d->isoWeekday() && $classroom->start_time !== null && $classroom->end_time !== null)
                ? [[
                    'weekday' => (int) $classroom->weekday,
                    'start_time' => (string) $classroom->start_time,
                    'end_time' => (string) $classroom->end_time,
                ]]
                : []);
        if ($scheduleForDate === [] && $extra !== null) {
            $scheduleForDate = [[
                'weekday' => $d->isoWeekday(),
                'start_time' => (string) ($extra['start_time'] ?? ''),
                'end_time' => (string) ($extra['end_time'] ?? ''),
            ]];
        }

        $coursePrices = $classroom->course?->coursePrices ?? collect();
        $durationOptions = CourseTuition::distinctDurations($coursePrices);
        $requiresDurationChoice = CourseTuition::hasMultipleDurations($coursePrices);
        $savedDuration = $attendances->first(fn (Attendance $a) => $a->duration_hours !== null)?->duration_hours;
        $sessionDurationHours = $requiresDurationChoice
            ? ($savedDuration !== null
                ? CourseTuition::normalizeDuration($savedDuration)
                : ($durationOptions[0] ?? null))
            : null;

        return Inertia::render('Attendances/RollCall', [
            'classroom' => $classroom,
            'classDate' => $classDate,
            'classDateWeekdayLabel' => '週'.(self::WEEKDAY_ZH[$d->isoWeekday()] ?? ''),
            'scheduleForDate' => $scheduleForDate,
            'rows' => $rows,
            'studentsForAdd' => $studentsForAdd,
            'makeupClassOptions' => $makeupClassOptions,
            'leaveDates' => $leaveDates,
            'readonly' => $this->isRollCallCompleteForDay($classroom, $classDate),
            'backUrl' => $this->sanitizeAttendancesReturnUrl($request->query('return')) ?? '/attendances',
            'durationOptions' => $durationOptions,
            'durationHours' => $sessionDurationHours,
            'requiresDurationChoice' => $requiresDurationChoice,
        ]);
    }

    /**
     * 取得某班級在指定日期的上課時段（含當日單次加課）。
     *
     * @return array<int, array{start: string, end: string}>
     */
    private function classroomTimeRangesOnDate(?Classroom $classroom, string $dateYmd): array
    {
        if ($classroom === null) {
            return [];
        }
        $iso = Carbon::parse($dateYmd)->isoWeekday();
        $ranges = [];

        if (Schema::hasTable('classroom_schedules')) {
            $classroom->loadMissing('schedules');
            foreach ($classroom->schedules as $s) {
                if ((int) $s->weekday === $iso && $s->start_time && $s->end_time) {
                    $ranges[] = [
                        'start' => $this->normalizeTimeHis((string) $s->start_time),
                        'end' => $this->normalizeTimeHis((string) $s->end_time),
                    ];
                }
            }
        } elseif ((int) $classroom->weekday === $iso && $classroom->start_time && $classroom->end_time) {
            $ranges[] = [
                'start' => $this->normalizeTimeHis((string) $classroom->start_time),
                'end' => $this->normalizeTimeHis((string) $classroom->end_time),
            ];
        }

        $extra = $classroom->resolveExtraSessionForDate($dateYmd);
        if ($extra !== null && ! empty($extra['start_time']) && ! empty($extra['end_time'])) {
            $ranges[] = [
                'start' => $this->normalizeTimeHis((string) $extra['start_time']),
                'end' => $this->normalizeTimeHis((string) $extra['end_time']),
            ];
        }

        return $ranges;
    }

    /**
     * 學生在指定日期已占用的時段（在籍班級的課表 + 當日已存在的出勤），排除指定班級。
     *
     * @return array<int, array{start: string, end: string, label: string}>
     */
    private function studentBusyRangesOnDate(int $studentId, string $dateYmd, int $excludeClassroomId): array
    {
        $busy = [];

        $enrolledClassrooms = Classroom::query()
            ->whereHas('enrollments', fn ($q) => $q->where('student_id', $studentId)->where('status', 'active'))
            ->where('id', '!=', $excludeClassroomId)
            ->with('schedules')
            ->get();
        foreach ($enrolledClassrooms as $c) {
            foreach ($this->classroomTimeRangesOnDate($c, $dateYmd) as $r) {
                $busy[] = ['start' => $r['start'], 'end' => $r['end'], 'label' => (string) $c->name];
            }
        }

        $attendances = Attendance::query()
            ->where('student_id', $studentId)
            ->whereDate('class_date', $dateYmd)
            ->whereIn('status', ['present', 'late', 'makeup', 'extra'])
            ->where('classroom_id', '!=', $excludeClassroomId)
            ->with('classroom.schedules')
            ->get();
        foreach ($attendances as $a) {
            if ($a->classroom === null) {
                continue;
            }
            foreach ($this->classroomTimeRangesOnDate($a->classroom, $dateYmd) as $r) {
                $busy[] = ['start' => $r['start'], 'end' => $r['end'], 'label' => (string) $a->classroom->name];
            }
        }

        return $busy;
    }

    /**
     * 找出第一筆與目標時段重疊的占用時段。
     *
     * @param  array<int, array{start: string, end: string}>  $targetRanges
     * @param  array<int, array{start: string, end: string, label: string}>  $busyRanges
     * @return array{start: string, end: string, label: string}|null
     */
    private function firstTimeConflict(array $targetRanges, array $busyRanges): ?array
    {
        foreach ($busyRanges as $b) {
            foreach ($targetRanges as $t) {
                if ($t['start'] < $b['end'] && $b['start'] < $t['end']) {
                    return $b;
                }
            }
        }

        return null;
    }

    private function normalizeTimeHis(string $time): string
    {
        try {
            return Carbon::parse($time)->format('H:i:s');
        } catch (\Throwable) {
            return $time;
        }
    }

    private function makeupClassLabel(?Classroom $classroom): string
    {
        if ($classroom === null) {
            return '';
        }
        $category = $classroom->course?->courseCategory?->name;
        $course = $classroom->course?->name ?? '課程';
        $coursePart = $category ? "{$category} / {$course}" : $course;

        return trim($coursePart.' · '.$classroom->name, ' ·');
    }

    /**
     * 僅允許回到線上點名首頁（保留其查詢字串），避免開放轉址。
     */
    private function sanitizeAttendancesReturnUrl(mixed $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }
        if (! str_starts_with($url, '/attendances')) {
            return null;
        }
        if (str_contains($url, "\n") || str_contains($url, "\r")) {
            return null;
        }

        return $url;
    }

    /**
     * 僅允許回到學生出勤列表（保留其查詢字串），避免開放轉址。
     */
    private function sanitizeStudentAttendancesReturnUrl(mixed $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }
        if (! str_starts_with($url, '/student-attendances')) {
            return null;
        }
        if (str_contains($url, "\n") || str_contains($url, "\r")) {
            return null;
        }

        return $url;
    }

    public function storeDay(Request $request, Classroom $classroom): RedirectResponse
    {
        if ($classroom->status !== 'active') {
            abort(403, '僅能上課中的班級進行點名。');
        }

        $this->authorizeTeacherClassroom($classroom);

        $validated = $request->validate([
            'class_date' => ['required', 'date'],
            'duration_hours' => ['nullable', 'numeric', 'min:0.5', 'max:24'],
            'entries' => ['present', 'array'],
            'entries.*.student_id' => ['required', 'integer', 'exists:students,id'],
            'entries.*.status' => ['required', 'in:present,excused,makeup,extra'],
            'entries.*.note' => ['nullable', 'string', 'max:500'],
            'entries.*.makeup_for_classroom_id' => [
                'nullable', 'integer', 'exists:classrooms,id',
                'required_if:entries.*.status,makeup',
                'required_if:entries.*.status,extra',
            ],
        ], [
            'entries.*.makeup_for_classroom_id.required_if' => '補課／加課學生請選擇班級。',
        ]);

        $dupCheck = collect($validated['entries'])->pluck('student_id');
        if ($dupCheck->count() !== $dupCheck->unique()->count()) {
            throw ValidationException::withMessages([
                'entries' => '同一學生不可重複列在點名表中。',
            ]);
        }

        foreach ($validated['entries'] as $row) {
            if (in_array($row['status'] ?? '', ['makeup', 'extra'], true)) {
                $this->authorizeTeacherMakeupClassroomId(
                    isset($row['makeup_for_classroom_id']) ? (int) $row['makeup_for_classroom_id'] : null
                );
            }
        }

        $classDate = Carbon::parse($validated['class_date'])->toDateString();
        $submittedIds = collect($validated['entries'])->pluck('student_id')->unique()->values();

        $classroom->loadMissing('course.coursePrices');
        $durationOptions = CourseTuition::distinctDurations($classroom->course?->coursePrices ?? collect());
        $multiDuration = CourseTuition::hasMultipleDurations($classroom->course?->coursePrices ?? collect());
        $sessionDuration = null;
        if ($multiDuration) {
            if (! isset($validated['duration_hours'])) {
                throw ValidationException::withMessages([
                    'duration_hours' => '此課程有多種時數方案，請選擇今日時數。',
                ]);
            }
            $sessionDuration = CourseTuition::normalizeDuration($validated['duration_hours']);
            if ($durationOptions !== [] && ! in_array($sessionDuration, $durationOptions, true)) {
                throw ValidationException::withMessages([
                    'duration_hours' => '所選時數不在此課程的價目方案中。',
                ]);
            }
        }

        $classroom->loadMissing('extraSessionModels.students');

        // 加課：學生在同一天的時段不可與其他課程重複。
        $extraRanges = $this->classroomTimeRangesOnDate($classroom, $classDate);
        if ($extraRanges !== []) {
            foreach ($validated['entries'] as $i => $row) {
                if (($row['status'] ?? '') !== 'extra') {
                    continue;
                }
                $conflict = $this->firstTimeConflict(
                    $extraRanges,
                    $this->studentBusyRangesOnDate((int) $row['student_id'], $classDate, (int) $classroom->id),
                );
                if ($conflict !== null) {
                    $name = Student::query()->whereKey($row['student_id'])->value('name') ?? '該生';
                    throw ValidationException::withMessages([
                        'entries' => "{$name} 的加課時段與「{$conflict['label']}」（{$conflict['start']}–{$conflict['end']}）重複，請改其他時段。",
                    ]);
                }
            }
        }

        // 已完成點名仍允許再次儲存（補登漏記或修正錯誤）。

        $requiredEnrollmentIds = $this->requiredStudentIdsForRollCallDay($classroom, $classDate);

        if ($requiredEnrollmentIds->isNotEmpty()) {
            foreach ($requiredEnrollmentIds as $sid) {
                if (! $submittedIds->contains($sid)) {
                    throw ValidationException::withMessages([
                        'entries' => '在籍學生皆需點名，請勿移除在籍列。補課學生請用「新增學生」加入。',
                    ]);
                }
            }
        }

        $leftEnrollmentStudentIds = $this->leftEnrollmentStudentIdsForClassroom((int) $classroom->id);

        DB::transaction(function () use ($classroom, $classDate, $validated, $submittedIds, $sessionDuration, $leftEnrollmentStudentIds): void {
            foreach ($validated['entries'] as $row) {
                Attendance::query()->updateOrCreate(
                    [
                        'classroom_id' => $classroom->id,
                        'student_id' => $row['student_id'],
                        'class_date' => $classDate,
                    ],
                    [
                        'status' => $row['status'],
                        'duration_hours' => $sessionDuration,
                        'note' => $row['note'] ?? null,
                        'makeup_for_classroom_id' => in_array($row['status'], ['makeup', 'extra'], true)
                            ? ($row['makeup_for_classroom_id'] ?? null)
                            : null,
                    ]
                );
            }

            $purgeQuery = Attendance::query()
                ->where('classroom_id', $classroom->id)
                ->whereDate('class_date', $classDate);

            if ($leftEnrollmentStudentIds !== []) {
                $purgeQuery->whereNotIn('student_id', $leftEnrollmentStudentIds);
            }

            if ($submittedIds->isEmpty()) {
                $purgeQuery->delete();
            } else {
                $purgeQuery->whereNotIn('student_id', $submittedIds->all())->delete();
            }
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => '已儲存出勤紀錄。',
        ]);

        // 儲存後自動返回線上點名首頁（保留原本的篩選條件）。
        $backUrl = $this->sanitizeAttendancesReturnUrl($request->query('return'));

        return redirect($backUrl ?? route('attendances.index'));
    }

    public function studentIndex(Request $request): Response
    {
        $validated = $request->validate([
            'classroom_id' => ['nullable', 'integer', Classroom::existsRuleForFilter(auth()->user())],
            'teacher_id' => ['nullable', 'integer', 'exists:teachers,id'],
            'student_name' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:present,excused,makeup,extra'],
            'weekday' => ['nullable', 'integer', 'between:1,7'],
            'month' => ['nullable', 'date_format:Y-m'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        $classroomId = isset($validated['classroom_id']) ? (int) $validated['classroom_id'] : null;
        $studentName = trim((string) ($validated['student_name'] ?? ''));
        $status = trim((string) ($validated['status'] ?? ''));
        $weekday = isset($validated['weekday']) ? (int) $validated['weekday'] : null;
        $month = $validated['month'] ?? null;
        $dateFrom = $validated['date_from'] ?? null;
        $dateTo = $validated['date_to'] ?? null;

        if ($month === null && $dateFrom === null && $dateTo === null) {
            $month = Carbon::today()->format('Y-m');
        }

        $query = Attendance::query()
            ->with([
                'classroom.course.courseCategory',
                'classroom.course.coursePrices',
                'student:id,name,phone',
            ])
            ->orderByDesc('class_date')
            ->orderByDesc('id');

        $user = auth()->user();
        $canFilterByTeacher = $user?->role !== User::ROLE_TEACHER;
        $filterTeacherId = null;
        if ($user?->role === User::ROLE_TEACHER) {
            $filterTeacherId = $user->teacher?->id;
            if ($filterTeacherId === null) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereHas('classroom', fn ($q) => $q->where('teacher_id', $filterTeacherId));
            }
        } elseif (isset($validated['teacher_id'])) {
            $filterTeacherId = (int) $validated['teacher_id'];
            $query->whereHas('classroom', fn ($q) => $q->where('teacher_id', $filterTeacherId));
        }

        if ($classroomId !== null) {
            $query->where('classroom_id', $classroomId);
        }
        if ($studentName !== '') {
            $query->whereHas('student', fn ($q) => $q->where('name', 'like', '%'.$studentName.'%'));
        }
        if ($status !== '') {
            $query->where('status', $status);
        }
        if ($weekday !== null) {
            // MySQL WEEKDAY(): 0=週一 .. 6=週日；ISO weekday 為 1..7。
            $query->whereRaw('WEEKDAY(class_date) = ?', [$weekday - 1]);
        }
        if ($month !== null) {
            [$my, $mm] = array_map('intval', explode('-', $month));
            $query->whereYear('class_date', $my)->whereMonth('class_date', $mm);
        }
        if ($dateFrom !== null) {
            $query->whereDate('class_date', '>=', $dateFrom);
        }
        if ($dateTo !== null) {
            $query->whereDate('class_date', '<=', $dateTo);
        }

        $rows = $query->paginate(50)->withQueryString()->through(function (Attendance $a): array {
            $note = (string) ($a->note ?? '');
            $hasMakeupPrefix = str_starts_with($note, '補課日期:');
            $isMakeup = $a->status === 'makeup' || $hasMakeupPrefix;
            $scheduled = MakeupAttendanceNote::scheduledMakeupDate($note);
            if ($a->status === 'makeup') {
                // 補課時間＝被補的原請假日（非實際補課當天）。
                $makeupDate = MakeupAttendanceNote::originalMissedDate($note) ?? $a->class_date?->toDateString();
            } elseif (in_array($a->status, ['absent', 'excused'], true)) {
                $makeupDate = $scheduled;
            } else {
                $makeupDate = null;
            }

            $prices = $a->classroom?->course?->coursePrices ?? collect();
            $multiDuration = CourseTuition::hasMultipleDurations($prices);

            return [
                'id' => $a->id,
                'classroom_id' => $a->classroom_id,
                'class_date' => $a->class_date?->toDateString(),
                'status' => $a->status,
                'classroom_name' => $a->classroom?->name ?? '-',
                'classroom_color' => $a->classroom?->color,
                'course_name' => $a->classroom?->course?->name ?? '-',
                'course_category_name' => $a->classroom?->course?->courseCategory?->name ?? '-',
                'duration_hours' => $multiDuration && $a->duration_hours !== null
                    ? CourseTuition::normalizeDuration($a->duration_hours)
                    : null,
                'student_name' => $a->student?->name ?? '-',
                'student_phone' => $a->student?->phone,
                'is_makeup' => $isMakeup,
                'makeup_date' => $makeupDate,
            ];
        });

        return Inertia::render('Attendances/StudentIndex', [
            'rows' => $rows,
            'classroomFilterOptions' => Classroom::selectOptionsForFilter(auth()->user()),
            'teacherOptions' => $canFilterByTeacher
                ? Teacher::query()->where('status', 'active')->orderBy('name')->get(['id', 'name'])->all()
                : [],
            'canFilterByTeacher' => $canFilterByTeacher,
            'filters' => [
                'classroom_id' => $classroomId === null ? '' : (string) $classroomId,
                'teacher_id' => $filterTeacherId === null ? '' : (string) $filterTeacherId,
                'student_name' => $studentName,
                'status' => $status,
                'weekday' => $weekday === null ? '' : (string) $weekday,
                'month' => $month ?? '',
                'date_from' => $dateFrom ?? '',
                'date_to' => $dateTo ?? '',
            ],
        ]);
    }

    public function updateMakeupDate(Request $request, Attendance $attendance): RedirectResponse
    {
        $validated = $request->validate([
            'makeup_date' => ['required', 'date'],
        ]);

        $attendance->load('classroom:id,teacher_id');
        if ($attendance->classroom === null) {
            abort(404);
        }
        $this->authorizeTeacherClassroom($attendance->classroom);

        if (! in_array($attendance->status, ['absent', 'excused', 'makeup'], true)) {
            throw ValidationException::withMessages([
                'makeup_date' => '僅缺席/請假/補課資料可設定或變更補課日期。',
            ]);
        }

        $makeupClassDate = Carbon::parse($validated['makeup_date'])->toDateString();

        if ($attendance->status === 'makeup') {
            $this->rescheduleMakeupAttendance($attendance, $makeupClassDate);
        } else {
            $this->scheduleOrUpdateMakeupFromAbsence($attendance, $makeupClassDate);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => '已更新補課日期。',
        ]);

        $returnUrl = $this->sanitizeStudentAttendancesReturnUrl($request->query('return'));

        return redirect($returnUrl ?? route('student-attendances.index'));
    }

    public function editStudentAttendance(Request $request, Attendance $attendance): Response
    {
        $attendance->load([
            'classroom.course.courseCategory',
            'classroom.course.coursePrices',
            'classroom.teacher:id,name',
            'student:id,name,phone',
        ]);

        if ($attendance->classroom === null || $attendance->student === null) {
            abort(404);
        }

        $this->authorizeTeacherClassroom($attendance->classroom);

        $classDateYmd = $attendance->class_date?->toDateString() ?? '';
        $rawNote = (string) ($attendance->note ?? '');
        $displayStatus = match ($attendance->status) {
            'extra', 'makeup', 'present', 'excused' => $attendance->status,
            default => 'present',
        };

        // 補課日期改為「被補的原請假日」，供補課時間下拉對應請假日。
        $makeupDate = $displayStatus === 'makeup'
            ? (MakeupAttendanceNote::originalMissedDate($rawNote) ?? '')
            : '';

        $course = $attendance->classroom->course;
        $coursePrices = $course?->coursePrices ?? collect();
        $durationOptions = CourseTuition::distinctDurations($coursePrices);
        $requiresDurationChoice = CourseTuition::hasMultipleDurations($coursePrices);
        $durationHours = $requiresDurationChoice
            ? ($attendance->duration_hours !== null
                ? CourseTuition::normalizeDuration($attendance->duration_hours)
                : ($durationOptions[0] ?? null))
            : null;

        // 該生在各班的請假日期：leaveDates[classroom_id] = ['Y-m-d', ...]
        $leaveDates = Attendance::query()
            ->where('student_id', $attendance->student->id)
            ->where('status', 'excused')
            ->whereHas('classroom', fn ($q) => $q->where('status', 'active'))
            ->get(['classroom_id', 'class_date'])
            ->groupBy('classroom_id')
            ->map(fn ($items) => $items
                ->map(fn ($a) => Carbon::parse($a->class_date)->toDateString())
                ->unique()
                ->sortDesc()
                ->values()
                ->all())
            ->all();

        // 該學生在籍的課程選項（補課／加課時可選歸帳班級）。
        $makeupClassOptionsQuery = Enrollment::query()
            ->where('student_id', $attendance->student->id)
            ->where('status', 'active')
            ->whereHas('classroom', fn ($q) => $q->where('status', 'active'));
        $this->scopeMakeupClassOptionsForTeacher($makeupClassOptionsQuery);
        $makeupClassOptions = $makeupClassOptionsQuery
            ->with(['classroom.course.courseCategory'])
            ->get()
            ->sortBy(fn (Enrollment $e) => $e->classroom?->name ?? '')
            ->map(fn (Enrollment $e) => [
                'classroom_id' => (int) $e->classroom_id,
                'label' => $this->makeupClassLabel($e->classroom),
            ])
            ->values()
            ->all();

        $makeupForClassroomId = $attendance->makeup_for_classroom_id
            ?? (in_array($displayStatus, ['makeup', 'extra'], true) ? $attendance->classroom->id : null);

        $allowedMakeupIds = collect($makeupClassOptions)->pluck('classroom_id')->all();
        if ($makeupForClassroomId !== null && ! in_array($makeupForClassroomId, $allowedMakeupIds, true)) {
            $makeupForClassroomId = in_array($attendance->classroom->id, $allowedMakeupIds, true)
                ? $attendance->classroom->id
                : ($allowedMakeupIds[0] ?? null);
        }

        return Inertia::render('Attendances/StudentAttendanceEdit', [
            'attendance' => [
                'id' => $attendance->id,
                'class_date' => $classDateYmd,
                'status' => $displayStatus,
                'note' => $displayStatus === 'makeup' && str_starts_with($rawNote, '補課日期:') ? '' : $rawNote,
                'makeup_date' => $makeupDate,
                'makeup_for_classroom_id' => $makeupForClassroomId,
                'duration_hours' => $durationHours,
            ],
            'student' => [
                'id' => $attendance->student->id,
                'name' => $attendance->student->name,
                'phone' => $attendance->student->phone,
            ],
            'classroom' => [
                'id' => $attendance->classroom->id,
                'name' => $attendance->classroom->name,
                'color' => $attendance->classroom->color,
                'teacher_name' => $attendance->classroom->teacher?->name,
                'course_category_name' => $course?->courseCategory?->name ?? '—',
                'course_name' => $course?->name ?? '—',
            ],
            'makeupClassOptions' => $makeupClassOptions,
            'leaveDates' => $leaveDates,
            'durationOptions' => $durationOptions,
            'requiresDurationChoice' => $requiresDurationChoice,
            'returnUrl' => $this->sanitizeStudentAttendancesReturnUrl($request->query('return')) ?? route('student-attendances.index'),
        ]);
    }

    public function updateStudentAttendance(Request $request, Attendance $attendance): RedirectResponse
    {
        $validated = $request->validate([
            'class_date' => ['required', 'date'],
            'status' => ['required', 'in:present,excused,makeup,extra'],
            'note' => ['nullable', 'string', 'max:500'],
            'makeup_date' => ['nullable', 'date'],
            'makeup_for_classroom_id' => [
                'nullable', 'integer', 'exists:classrooms,id',
                'required_if:status,makeup',
                'required_if:status,extra',
            ],
            'duration_hours' => ['nullable', 'numeric', 'min:0.5', 'max:24'],
        ], [
            'makeup_for_classroom_id.required_if' => '狀態為「補課／加課」時，請選擇班級。',
        ]);

        $attendance->load(['classroom.course.coursePrices', 'classroom:id,teacher_id,status,course_id']);
        if ($attendance->classroom === null) {
            abort(404);
        }
        $this->authorizeTeacherClassroom($attendance->classroom);

        $classDate = Carbon::parse($validated['class_date'])->toDateString();
        $status = (string) $validated['status'];
        $userNote = trim((string) ($validated['note'] ?? ''));
        $userNote = $userNote === '' ? null : $userNote;
        $makeupDate = isset($validated['makeup_date'])
            ? Carbon::parse($validated['makeup_date'])->toDateString()
            : null;
        $makeupForClassroomId = in_array($status, ['makeup', 'extra'], true)
            ? (isset($validated['makeup_for_classroom_id']) ? (int) $validated['makeup_for_classroom_id'] : null)
            : null;

        if ($classDate !== $attendance->class_date?->toDateString()) {
            $duplicate = Attendance::query()
                ->where('classroom_id', $attendance->classroom_id)
                ->where('student_id', $attendance->student_id)
                ->whereDate('class_date', $classDate)
                ->whereKeyNot($attendance->id)
                ->exists();
            if ($duplicate) {
                throw ValidationException::withMessages([
                    'class_date' => '此學生在該班級的這個日期已有其他出勤紀錄。',
                ]);
            }
        }

        if ($status === 'makeup' && $makeupDate === null) {
            throw ValidationException::withMessages([
                'makeup_date' => '狀態為「補課」時請填寫補課日期。',
            ]);
        }

        if (in_array($status, ['makeup', 'extra'], true)) {
            $this->authorizeTeacherMakeupClassroomId($makeupForClassroomId);
        }

        $durationOptions = CourseTuition::distinctDurations($attendance->classroom->course?->coursePrices ?? collect());
        $multiDuration = CourseTuition::hasMultipleDurations($attendance->classroom->course?->coursePrices ?? collect());
        $sessionDuration = null;
        if ($multiDuration) {
            if (! isset($validated['duration_hours'])) {
                throw ValidationException::withMessages([
                    'duration_hours' => '此課程有多種時數方案，請選擇時數。',
                ]);
            }
            $sessionDuration = CourseTuition::normalizeDuration($validated['duration_hours']);
            if ($durationOptions !== [] && ! in_array($sessionDuration, $durationOptions, true)) {
                throw ValidationException::withMessages([
                    'duration_hours' => '所選時數不在此課程的價目方案中。',
                ]);
            }
        }

        if ($status === 'extra') {
            $extraRanges = $this->classroomTimeRangesOnDate($attendance->classroom, $classDate);
            if ($extraRanges !== []) {
                $conflict = $this->firstTimeConflict(
                    $extraRanges,
                    $this->studentBusyRangesOnDate((int) $attendance->student_id, $classDate, (int) $attendance->classroom_id),
                );
                if ($conflict !== null) {
                    throw ValidationException::withMessages([
                        'status' => "加課時段與「{$conflict['label']}」（{$conflict['start']}–{$conflict['end']}）重複，請改其他時段。",
                    ]);
                }
            }
        }

        DB::transaction(function () use ($attendance, $classDate, $status, $userNote, $makeupDate, $makeupForClassroomId, $sessionDuration): void {
            $this->persistSingleAttendance($attendance, $status, $userNote, $makeupDate, $makeupForClassroomId, $classDate, $sessionDuration);
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => '已更新出勤紀錄。',
        ]);

        $returnUrl = $this->sanitizeStudentAttendancesReturnUrl($request->query('return'));

        return redirect($returnUrl ?? route('student-attendances.index'));
    }

    public function destroyStudentAttendance(Request $request, Attendance $attendance): RedirectResponse
    {
        $attendance->load('classroom:id,teacher_id');
        if ($attendance->classroom !== null) {
            $this->authorizeTeacherClassroom($attendance->classroom);
        }

        $attendance->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => '已移除出勤紀錄。',
        ]);

        $returnUrl = $this->sanitizeStudentAttendancesReturnUrl($request->query('return'));

        return redirect($returnUrl ?? route('student-attendances.index'));
    }

    public function bulkDestroyStudentAttendance(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $ids = array_values(array_unique(array_map('intval', $validated['ids'])));

        $records = Attendance::query()
            ->whereIn('id', $ids)
            ->with('classroom:id,teacher_id')
            ->get();

        if ($records->count() !== count($ids)) {
            throw ValidationException::withMessages([
                'ids' => '部分出勤紀錄不存在或已被刪除，請重新整理後再試。',
            ]);
        }

        foreach ($records as $attendance) {
            if ($attendance->classroom === null) {
                throw ValidationException::withMessages([
                    'ids' => '部分出勤紀錄缺少班級資料，無法刪除。',
                ]);
            }
            $this->authorizeTeacherClassroom($attendance->classroom);
        }

        $deleted = Attendance::query()->whereIn('id', $ids)->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => "已移除 {$deleted} 筆出勤紀錄。",
        ]);

        $returnUrl = $this->sanitizeStudentAttendancesReturnUrl($request->query('return'));

        return redirect($returnUrl ?? route('student-attendances.index'));
    }

    private function persistSingleAttendance(
        Attendance $attendance,
        string $status,
        ?string $userNote,
        ?string $makeupDate,
        ?int $makeupForClassroomId = null,
        ?string $classDate = null,
        ?float $durationHours = null,
    ): void {
        $sessionYmd = $classDate ?? $attendance->class_date?->toDateString();
        if ($sessionYmd === null) {
            throw ValidationException::withMessages([
                'class_date' => '出勤日期無效。',
            ]);
        }

        $rawNote = (string) ($attendance->note ?? '');
        $wasMakeup = $attendance->status === 'makeup';
        $origMissed = MakeupAttendanceNote::originalMissedDate($rawNote);

        if ($status === 'extra') {
            $attendance->update([
                'status' => 'extra',
                'class_date' => $sessionYmd,
                'note' => $userNote,
                'makeup_for_classroom_id' => $makeupForClassroomId,
                'duration_hours' => $durationHours,
            ]);

            return;
        }

        if ($status === 'makeup') {
            $missed = $makeupDate ?? $origMissed ?? $sessionYmd;
            $attended = $sessionYmd;
            $systemNote = $missed === $attended
                ? '補課日期:'.$missed
                : '補課日期:'.$missed.' 補課已排:'.$attended;
            $attendance->update([
                'status' => 'makeup',
                'class_date' => $sessionYmd,
                'note' => $systemNote,
                'makeup_for_classroom_id' => $makeupForClassroomId,
                'duration_hours' => $durationHours,
            ]);

            return;
        }

        if ($wasMakeup) {
            $attendance->update([
                'status' => $status,
                'class_date' => $sessionYmd,
                'note' => $userNote,
                'makeup_for_classroom_id' => null,
                'duration_hours' => $durationHours,
            ]);

            return;
        }

        $attendance->update([
            'status' => $status,
            'class_date' => $sessionYmd,
            'note' => $userNote,
            'makeup_for_classroom_id' => null,
            'duration_hours' => $durationHours,
        ]);
    }

    private function mergeScheduledMakeupIntoNote(string $note, string $schedYmd): string
    {
        if (preg_match('/\s*補課已排:\d{4}-\d{2}-\d{2}/u', $note)) {
            return trim((string) preg_replace('/\s*補課已排:\d{4}-\d{2}-\d{2}/u', ' 補課已排:'.$schedYmd, $note));
        }

        return trim($note.' 補課已排:'.$schedYmd);
    }

    private function scheduleOrUpdateMakeupFromAbsence(Attendance $attendance, string $makeupClassDate): void
    {
        $originalDate = $attendance->class_date?->toDateString();
        if ($originalDate === null) {
            throw ValidationException::withMessages([
                'makeup_date' => '無法解析原上課日。',
            ]);
        }
        if ($originalDate === $makeupClassDate) {
            throw ValidationException::withMessages([
                'makeup_date' => '補課日期不可與原出勤日期相同。',
            ]);
        }

        DB::transaction(function () use ($attendance, $originalDate, $makeupClassDate): void {
            $note = (string) ($attendance->note ?? '');

            Attendance::query()
                ->where('classroom_id', $attendance->classroom_id)
                ->where('student_id', $attendance->student_id)
                ->where('status', 'makeup')
                ->where('note', 'like', '補課日期:'.$originalDate.'%')
                ->delete();

            $attendance->update([
                'note' => $this->mergeScheduledMakeupIntoNote($note, $makeupClassDate),
            ]);

            $makeupNote = $originalDate === $makeupClassDate
                ? '補課日期:'.$originalDate
                : '補課日期:'.$originalDate.' 補課已排:'.$makeupClassDate;

            Attendance::query()->updateOrCreate(
                [
                    'classroom_id' => $attendance->classroom_id,
                    'student_id' => $attendance->student_id,
                    'class_date' => $makeupClassDate,
                ],
                [
                    'status' => 'makeup',
                    'note' => $makeupNote,
                ]
            );
        });
    }

    private function rescheduleMakeupAttendance(Attendance $attendance, string $makeupClassDate): void
    {
        $note = (string) ($attendance->note ?? '');
        $origMissed = MakeupAttendanceNote::originalMissedDate($note);
        if ($origMissed === null) {
            $origMissed = $attendance->class_date?->toDateString();
        }
        if ($origMissed === null) {
            throw ValidationException::withMessages([
                'makeup_date' => '無法解析原缺席上課日。',
            ]);
        }

        if ($makeupClassDate === $origMissed) {
            throw ValidationException::withMessages([
                'makeup_date' => '實際補課日不可與原缺席上課日相同。',
            ]);
        }

        $oldSession = $attendance->class_date?->toDateString();
        if ($oldSession === $makeupClassDate) {
            return;
        }

        $conflict = Attendance::query()
            ->where('classroom_id', $attendance->classroom_id)
            ->where('student_id', $attendance->student_id)
            ->whereDate('class_date', $makeupClassDate)
            ->where('id', '!=', $attendance->id)
            ->exists();
        if ($conflict) {
            throw ValidationException::withMessages([
                'makeup_date' => '該日已有其他出勤紀錄，請改選其他日期或先調整原紀錄。',
            ]);
        }

        $newNote = $origMissed === $makeupClassDate
            ? '補課日期:'.$origMissed
            : '補課日期:'.$origMissed.' 補課已排:'.$makeupClassDate;

        $attendance->update([
            'class_date' => $makeupClassDate,
            'note' => $newNote,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    /**
     * 非班級上課日補登時，預設帶入最近一個排課星期（例：週日補登週六班 → 上週六）。
     *
     * @return array<int, int>
     */
    private function classroomScheduleWeekdays(Classroom $classroom, bool $hasSchedulesTable): array
    {
        if ($hasSchedulesTable && $classroom->relationLoaded('schedules') && $classroom->schedules->isNotEmpty()) {
            return $classroom->schedules
                ->pluck('weekday')
                ->map(fn ($w) => (int) $w)
                ->unique()
                ->values()
                ->all();
        }

        if ($classroom->weekday !== null) {
            return [(int) $classroom->weekday];
        }

        return [];
    }

    private function defaultRollCallDateForClassroom(Classroom $classroom, Carbon $today, bool $hasSchedulesTable): string
    {
        $weekdays = $this->classroomScheduleWeekdays($classroom, $hasSchedulesTable);
        if ($weekdays === []) {
            return $today->toDateString();
        }

        $todayWd = $today->isoWeekday();
        if (in_array($todayWd, $weekdays, true)) {
            return $today->toDateString();
        }

        for ($offset = 1; $offset <= 7; $offset++) {
            $candidate = $today->copy()->subDays($offset);
            if (in_array($candidate->isoWeekday(), $weekdays, true)) {
                return $candidate->toDateString();
            }
        }

        return $today->toDateString();
    }

    private function mapClassroomsRollPayload(EloquentCollection $classrooms, Carbon $today, bool $hasSchedulesTable = true): array
    {
        return $classrooms->map(function (Classroom $c) use ($today, $hasSchedulesTable) {
            $arr = $c->toArray();
            $arr['today_schedules'] = collect($arr['schedules'] ?? [])->map(fn ($s) => [
                'weekday' => (int) $s['weekday'],
                'start_time' => $s['start_time'],
                'end_time' => $s['end_time'],
            ])->values()->all();
            if ($arr['today_schedules'] === []) {
                if ($c->weekday !== null && $c->start_time !== null && $c->end_time !== null) {
                    $arr['today_schedules'] = [[
                        'weekday' => (int) $c->weekday,
                        'start_time' => (string) $c->start_time,
                        'end_time' => (string) $c->end_time,
                    ]];
                }
            }
            $rollCallDate = $this->defaultRollCallDateForClassroom($c, $today, $hasSchedulesTable);
            $arr['roll_call_done_for_today'] = $this->isRollCallCompleteForDay($c, $rollCallDate);

            return $arr;
        })->values()->all();
    }

    /** @param  array<string, mixed>  $row */
    private function filterSchedulesForLiveSlot(array $row, Carbon $now): array
    {
        $wd = $now->isoWeekday();
        $t = $now->format('H:i:s');
        $slots = collect($row['today_schedules'] ?? [])->filter(function ($s) use ($wd, $t) {
            return (int) $s['weekday'] === $wd
                && strcmp((string) $s['start_time'], $t) <= 0
                && strcmp((string) $s['end_time'], $t) >= 0;
        })->values()->all();
        $row['today_schedules'] = $slots;

        return $row;
    }

    /**
     * @param  EloquentCollection<int, Classroom>  $classrooms
     * @return EloquentCollection<int, Classroom>
     */
    private function mergeClassroomsWithExtraSessionsForWeekdayIndex(
        EloquentCollection $classrooms,
        ?int $weekday,
        Carbon $monthStart,
        Carbon $monthEnd,
        ?int $courseId,
        ?int $classroomId,
        bool $hasSchedulesTable,
        ?int $filterTeacherId = null,
        bool $forceEmptyTeacherScope = false,
    ): EloquentCollection {
        $q = Classroom::query()
            ->where('status', 'active')
            ->where(function ($w) use ($monthStart, $monthEnd): void {
                $w->whereNotNull('extra_sessions')
                    ->orWhereHas('extraSessionModels', function ($qq) use ($monthStart, $monthEnd): void {
                        $qq->whereBetween('session_date', [$monthStart->toDateString(), $monthEnd->toDateString()]);
                    });
            })
            ->with([
                'course.courseCategory',
                'course.coursePrices',
                'teacher:id,name',
                'extraSessionModels' => fn ($qq) => $qq
                    ->whereBetween('session_date', [$monthStart->toDateString(), $monthEnd->toDateString()]),
            ]);
        if ($hasSchedulesTable) {
            $q->with([
                'schedules' => fn ($qq) => $weekday === null
                    ? $qq->orderBy('weekday')->orderBy('start_time')
                    : $qq->where('weekday', $weekday)->orderBy('start_time'),
            ]);
        }
        $this->applyTeacherScopeToQuery($q, $filterTeacherId, $forceEmptyTeacherScope);
        if ($courseId !== null) {
            $q->where('course_id', $courseId);
        }
        if ($classroomId !== null) {
            $q->where('id', $classroomId);
        }
        $existingIds = $classrooms->pluck('id')->all();
        $more = $q->orderBy('name')->get()->filter(function (Classroom $c) use ($weekday, $monthStart, $monthEnd, $existingIds): bool {
            if (in_array($c->id, $existingIds, true)) {
                return false;
            }

            return $weekday === null
                ? $this->classroomHasExtraInMonth($c, $monthStart, $monthEnd)
                : $this->classroomHasExtraOnWeekdayBetween($c, $weekday, $monthStart, $monthEnd);
        });

        return $classrooms->merge($more)->sortBy('name')->values();
    }

    private function classroomHasExtraInMonth(Classroom $c, Carbon $monthStart, Carbon $monthEnd): bool
    {
        $c->loadMissing('extraSessionModels');
        foreach ($c->extra_sessions ?? [] as $ex) {
            if (empty($ex['date'])) {
                continue;
            }
            try {
                $d = Carbon::parse($ex['date'])->startOfDay();
            } catch (\Throwable) {
                continue;
            }
            if ($d->betweenIncluded($monthStart, $monthEnd)) {
                return true;
            }
        }
        foreach ($c->extraSessionModels as $ex) {
            $d = $ex->session_date->copy()->startOfDay();
            if ($d->betweenIncluded($monthStart, $monthEnd)) {
                return true;
            }
        }

        return false;
    }

    private function classroomHasExtraOnWeekdayBetween(Classroom $c, int $weekday, Carbon $monthStart, Carbon $monthEnd): bool
    {
        $c->loadMissing('extraSessionModels');
        foreach ($c->extra_sessions ?? [] as $ex) {
            if (empty($ex['date'])) {
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
            if ($d->isoWeekday() === $weekday) {
                return true;
            }
        }
        foreach ($c->extraSessionModels as $ex) {
            $d = $ex->session_date->copy()->startOfDay();
            if ($d->lt($monthStart) || $d->gt($monthEnd)) {
                continue;
            }
            if ($d->isoWeekday() === $weekday) {
                return true;
            }
        }

        return false;
    }

    /** @param  array<string, mixed>  $row */
    private function appendIndexExtraSlotsForFilteredWeekday(
        array $row,
        Classroom $c,
        int $weekday,
        Carbon $monthStart,
        Carbon $monthEnd,
    ): array {
        $slots = $row['today_schedules'] ?? [];
        foreach ($c->extra_sessions ?? [] as $ex) {
            if (empty($ex['date'])) {
                continue;
            }
            try {
                $d = Carbon::parse($ex['date'])->startOfDay();
            } catch (\Throwable) {
                continue;
            }
            if ($d->lt($monthStart) || $d->gt($monthEnd) || $d->isoWeekday() !== $weekday) {
                continue;
            }
            $slots[] = [
                'weekday' => $weekday,
                'start_time' => $this->normalizeRollCallTime((string) ($ex['start_time'] ?? '')),
                'end_time' => $this->normalizeRollCallTime((string) ($ex['end_time'] ?? '')),
            ];
        }
        $c->loadMissing('extraSessionModels');
        foreach ($c->extraSessionModels as $ex) {
            $d = $ex->session_date->copy()->startOfDay();
            if ($d->lt($monthStart) || $d->gt($monthEnd) || $d->isoWeekday() !== $weekday) {
                continue;
            }
            $slots[] = [
                'weekday' => $weekday,
                'start_time' => $this->normalizeRollCallTime(Carbon::parse($ex->start_time)->format('H:i:s')),
                'end_time' => $this->normalizeRollCallTime(Carbon::parse($ex->end_time)->format('H:i:s')),
            ];
        }
        $row['today_schedules'] = $slots;

        return $row;
    }

    /** @param  array<string, mixed>  $row */
    private function appendIndexExtraSlotsForAllWeekdays(
        array $row,
        Classroom $c,
        Carbon $monthStart,
        Carbon $monthEnd,
    ): array {
        $slots = $row['today_schedules'] ?? [];
        foreach ($c->extra_sessions ?? [] as $ex) {
            if (empty($ex['date'])) {
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
            $slots[] = [
                'weekday' => $d->isoWeekday(),
                'start_time' => $this->normalizeRollCallTime((string) ($ex['start_time'] ?? '')),
                'end_time' => $this->normalizeRollCallTime((string) ($ex['end_time'] ?? '')),
            ];
        }
        $c->loadMissing('extraSessionModels');
        foreach ($c->extraSessionModels as $ex) {
            $d = $ex->session_date->copy()->startOfDay();
            if ($d->lt($monthStart) || $d->gt($monthEnd)) {
                continue;
            }
            $slots[] = [
                'weekday' => $d->isoWeekday(),
                'start_time' => $this->normalizeRollCallTime(Carbon::parse($ex->start_time)->format('H:i:s')),
                'end_time' => $this->normalizeRollCallTime(Carbon::parse($ex->end_time)->format('H:i:s')),
            ];
        }
        $row['today_schedules'] = $slots;

        return $row;
    }

    /**
     * @return EloquentCollection<int, Classroom>
     */
    private function classroomsMatchingLiveExtraSessions(
        ?User $user,
        ?int $filterTeacherId,
        bool $hasSchedulesTable,
        Carbon $now,
    ): EloquentCollection {
        $today = $now->toDateString();
        $t = $now->format('H:i:s');
        $idsFromDb = ClassroomExtraSession::query()
            ->whereDate('session_date', $today)
            ->where('start_time', '<=', $t)
            ->where('end_time', '>=', $t)
            ->pluck('classroom_id')
            ->unique()
            ->all();

        $q = Classroom::query()
            ->where('status', 'active')
            ->where(function ($w) use ($idsFromDb): void {
                $w->whereNotNull('extra_sessions');
                if ($idsFromDb !== []) {
                    $w->orWhereIn('id', $idsFromDb);
                }
            })
            ->with([
                'course.courseCategory',
                'course.coursePrices',
                'teacher:id,name',
                'extraSessionModels' => fn ($qq) => $qq->whereDate('session_date', $today),
            ]);
        if ($hasSchedulesTable) {
            $wd = $now->isoWeekday();
            $q->with([
                'schedules' => fn ($qq) => $qq
                    ->where('weekday', $wd)
                    ->orderBy('start_time'),
            ]);
        }
        if ($user?->role === User::ROLE_TEACHER) {
            $teacherId = $user->teacher?->id;
            if ($teacherId === null) {
                $q->whereRaw('1 = 0');
            } else {
                $q->where('teacher_id', $teacherId);
            }
        } elseif ($filterTeacherId !== null && $filterTeacherId > 0) {
            $q->where('teacher_id', $filterTeacherId);
        }

        return $q->orderBy('name')->get()->filter(function (Classroom $c) use ($today, $t): bool {
            return $this->classroomLiveExtraMatchesNow($c, $today, $t);
        });
    }

    private function classroomLiveExtraMatchesNow(Classroom $c, string $today, string $t): bool
    {
        $ex = $c->resolveExtraSessionForDate($today);
        if ($ex === null) {
            return false;
        }
        $st = $this->normalizeRollCallTime((string) ($ex['start_time'] ?? ''));
        $en = $this->normalizeRollCallTime((string) ($ex['end_time'] ?? ''));
        if ($st === '' || $en === '') {
            return false;
        }

        return strcmp($st, $t) <= 0 && strcmp($en, $t) >= 0;
    }

    private function normalizeRollCallTime(string $time): string
    {
        $time = trim($time);
        if (strlen($time) === 5) {
            return $time.':00';
        }

        return $time;
    }

    /** @param  array<string, mixed>  $row */
    private function prependTodayExtraSessionForLive(array $row, Classroom $c, Carbon $now): array
    {
        $ex = $c->resolveExtraSessionForDate($now->toDateString());
        if ($ex === null) {
            return $row;
        }
        $slot = [
            'weekday' => $now->isoWeekday(),
            'start_time' => $this->normalizeRollCallTime((string) ($ex['start_time'] ?? '')),
            'end_time' => $this->normalizeRollCallTime((string) ($ex['end_time'] ?? '')),
        ];
        $sched = $row['today_schedules'] ?? [];
        array_unshift($sched, $slot);
        $row['today_schedules'] = $sched;

        return $row;
    }

    private function isRollCallCompleteForDay(Classroom $classroom, string $classDate): bool
    {
        $required = $this->requiredStudentIdsForRollCallDay($classroom, $classDate);
        $enrolled = $required->count();
        $attendanceRows = Attendance::query()
            ->where('classroom_id', $classroom->id)
            ->whereDate('class_date', $classDate)
            ->count();

        return $enrolled > 0 ? $attendanceRows >= $enrolled : $attendanceRows > 0;
    }

    /** @return Collection<int, int> */
    private function requiredStudentIdsForRollCallDay(Classroom $classroom, string $classDate): Collection
    {
        $classroom->loadMissing('extraSessionModels.students');
        $extraRow = $classroom->extraSessionModels->first(fn (ClassroomExtraSession $r) => $r->session_date->toDateString() === $classDate);

        $base = Enrollment::query()
            ->where('classroom_id', $classroom->id)
            ->where('status', 'active')
            ->whereHas('student', fn ($q) => $q->where('status', 'active'))
            ->pluck('student_id')
            ->unique()
            ->map(fn ($id) => (int) $id);

        if ($extraRow !== null && $extraRow->students->isNotEmpty()) {
            $allowed = $extraRow->students->pluck('id')->map(fn ($id) => (int) $id);

            return $base->filter(fn (int $sid) => $allowed->contains($sid))->values();
        }

        return $base->values();
    }

    /**
     * 已離班學生的點名紀錄應保留，不可在儲存點名時一併刪除。
     *
     * @return array<int, int>
     */
    private function leftEnrollmentStudentIdsForClassroom(int $classroomId): array
    {
        return Enrollment::query()
            ->where('classroom_id', $classroomId)
            ->where('status', 'left')
            ->pluck('student_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function isValidDateString(string $value): bool
    {
        try {
            Carbon::parse($value);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function authorizeTeacherClassroom(Classroom $classroom): void
    {
        $user = auth()->user();
        if ($user?->role !== User::ROLE_TEACHER) {
            return;
        }
        $teacherId = $user->teacher?->id;
        if ($teacherId === null || (int) $classroom->teacher_id !== (int) $teacherId) {
            abort(403, '你只能操作自己的班級。');
        }
    }

    /** 老師帳號：補課／加課班級選項僅限自己負責的班級。 */
    private function scopeMakeupClassOptionsForTeacher($query): void
    {
        $user = auth()->user();
        if ($user?->role !== User::ROLE_TEACHER) {
            return;
        }
        $teacherId = $user->teacher?->id;
        if ($teacherId === null) {
            $query->whereRaw('1 = 0');

            return;
        }
        $query->whereHas('classroom', fn ($q) => $q->where('teacher_id', $teacherId));
    }

    private function authorizeTeacherMakeupClassroomId(?int $classroomId): void
    {
        if ($classroomId === null) {
            return;
        }
        $user = auth()->user();
        if ($user?->role !== User::ROLE_TEACHER) {
            return;
        }
        $teacherId = $user->teacher?->id;
        if ($teacherId === null) {
            abort(403, '你只能選擇自己的班級。');
        }
        $owned = Classroom::query()
            ->whereKey($classroomId)
            ->where('teacher_id', $teacherId)
            ->exists();
        if (! $owned) {
            abort(403, '你只能選擇自己的班級。');
        }
    }

    /** 點名僅允許出席／請假／補課；舊資料缺席、遲到改以出席顯示並可重存。 */
    private function normalizeRollCallStatus(string $status): string
    {
        if (in_array($status, ['present', 'excused', 'makeup'], true)) {
            return $status;
        }

        return 'present';
    }
}
