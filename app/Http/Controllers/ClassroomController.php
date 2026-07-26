<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\ClassroomSchedule;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\GradeLevel;
use App\Models\Student;
use App\Models\Teacher;
use App\Support\EnrollmentTuitionSync;
use App\Support\MakeupAttendanceNote;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ClassroomController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'grade_level_id' => ['nullable', 'integer', 'exists:grade_levels,id'],
            'teacher_id' => ['nullable', 'integer', 'exists:teachers,id'],
            'status' => ['nullable', 'in:active,paused'],
        ]);

        $name = trim((string) ($validated['name'] ?? ''));
        $courseId = isset($validated['course_id']) ? (int) $validated['course_id'] : null;
        $gradeLevelId = isset($validated['grade_level_id']) ? (int) $validated['grade_level_id'] : null;
        $teacherId = isset($validated['teacher_id']) ? (int) $validated['teacher_id'] : null;
        $status = $validated['status'] ?? '';

        $hasSchedulesTable = Schema::hasTable('classroom_schedules');
        $query = Classroom::query()
            ->with([
                'course:id,course_category_id,name',
                'course.courseCategory:id,name',
                'course.coursePrices',
                'gradeLevel:id,name,code',
                'teacher:id,name',
            ])
            ->latest('id');

        if ($name !== '') {
            $query->where('name', 'like', '%'.$name.'%');
        }
        if ($courseId !== null) {
            $query->where(function ($q) use ($courseId, $hasSchedulesTable) {
                $q->where('course_id', $courseId);
                if ($hasSchedulesTable) {
                    $q->orWhereHas('schedules', fn ($s) => $s->where('course_id', $courseId));
                }
            });
        }
        if ($gradeLevelId !== null) {
            $query->where('grade_level_id', $gradeLevelId);
        }
        if ($teacherId !== null) {
            $query->where('teacher_id', $teacherId);
        }
        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($hasSchedulesTable) {
            $query->with([
                'schedules' => fn ($q) => $q
                    ->orderBy('weekday')
                    ->orderBy('start_time')
                    ->select('id', 'classroom_id', 'course_id', 'weekday', 'start_time', 'end_time'),
                'schedules.course:id,course_category_id,name',
                'schedules.course.courseCategory:id,name',
                'schedules.course.coursePrices',
            ]);
        }

        return Inertia::render('Classrooms/Index', [
            'classrooms' => $query->paginate(50)->through(function (Classroom $c) use ($hasSchedulesTable) {
                $arr = $c->toArray();
                if (! $hasSchedulesTable) {
                    $arr['schedules'] = ($c->weekday !== null && $c->start_time !== null && $c->end_time !== null)
                        ? [[
                            'weekday' => (int) $c->weekday,
                            'start_time' => (string) $c->start_time,
                            'end_time' => (string) $c->end_time,
                        ]]
                        : [];
                }

                return $arr;
            })->withQueryString(),
            'courseFilterOptions' => Course::query()
                ->with('courseCategory:id,name')
                ->orderBy('name')
                ->get(['id', 'course_category_id', 'name'])
                ->map(fn (Course $course): array => [
                    'id' => $course->id,
                    'name' => $course->courseCategory
                        ? "{$course->courseCategory->name} / {$course->name}"
                        : $course->name,
                ])
                ->all(),
            'gradeFilterOptions' => $this->gradeLevelOptions(),
            'teacherFilterOptions' => Teacher::query()
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Teacher $teacher): array => [
                    'id' => $teacher->id,
                    'name' => $teacher->name,
                ])
                ->all(),
            'filters' => [
                'name' => $name,
                'course_id' => $courseId === null ? '' : (string) $courseId,
                'grade_level_id' => $gradeLevelId === null ? '' : (string) $gradeLevelId,
                'teacher_id' => $teacherId === null ? '' : (string) $teacherId,
                'status' => $status,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Classrooms/Create', [
            'courses' => Course::query()
                ->with(['courseCategory', 'coursePrices'])
                ->join('course_categories', 'courses.course_category_id', '=', 'course_categories.id')
                ->orderBy('course_categories.sort_order')
                ->orderBy('course_categories.name')
                ->orderBy('courses.name')
                ->select('courses.*')
                ->get(),
            'teachers' => Teacher::query()->where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'gradeLevels' => $this->gradeLevelOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if ($request->input('color') === '') {
            $request->merge(['color' => null]);
        }

        $validated = $request->validate([
            'grade_level_id' => ['required', 'integer', 'exists:grade_levels,id'],
            'teacher_id' => ['nullable', 'exists:teachers,id'],
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'schedules' => ['required', 'array', 'min:1'],
            'schedules.*.course_id' => ['required', 'integer', 'exists:courses,id'],
            'schedules.*.weekday' => ['required', 'integer', 'between:1,7'],
            'schedules.*.start_time' => ['required', 'date_format:H:i'],
            'schedules.*.end_time' => ['required', 'date_format:H:i'],
            'active_periods' => ['nullable', 'array', 'max:30'],
            'active_periods.*.start_date' => ['nullable', 'date'],
            'active_periods.*.end_date' => ['nullable', 'date'],
            'status' => ['required', 'in:active,paused'],
        ]);

        $activePeriods = Classroom::normalizeActivePeriodsInput($validated['active_periods'] ?? null);
        $this->assertActivePeriodRangesValid($activePeriods);

        $schedules = $this->validatedSchedules($validated['schedules']);
        $primaryCourseId = $schedules[0]['course_id'] ?? null;

        $this->assertTeacherHasNoActiveScheduleConflict(
            isset($validated['teacher_id']) ? (int) $validated['teacher_id'] : null,
            $schedules,
            $validated['status'],
            null,
            [],
        );

        $classroom = Classroom::query()->create([
            'course_id' => $primaryCourseId,
            'grade_level_id' => $validated['grade_level_id'],
            'teacher_id' => $validated['teacher_id'] ?? null,
            'name' => $validated['name'],
            'color' => $validated['color'] ?? null,
            'weekday' => $schedules[0]['weekday'] ?? null,
            'start_time' => $schedules[0]['start_time'] ?? null,
            'end_time' => $schedules[0]['end_time'] ?? null,
            'active_periods' => $activePeriods,
            'start_date' => null,
            'end_date' => null,
            'status' => $validated['status'],
        ]);
        $this->syncSchedules($classroom, $schedules);

        return to_route('classrooms.index');
    }

    public function edit(Classroom $classroom): Response
    {
        return Inertia::render('Classrooms/Edit', [
            'classroom' => array_merge($classroom->toArray(), [
                'schedules' => Schema::hasTable('classroom_schedules')
                    ? $classroom->schedules()
                        ->orderBy('weekday')
                        ->orderBy('start_time')
                        ->get(['id', 'course_id', 'weekday', 'start_time', 'end_time'])
                        ->map(fn ($s) => [
                            'id' => $s->id,
                            'course_id' => $s->course_id ?? $classroom->course_id,
                            'weekday' => $s->weekday,
                            'start_time' => $s->start_time,
                            'end_time' => $s->end_time,
                        ])
                        ->values()
                    : collect(($classroom->weekday !== null && $classroom->start_time !== null && $classroom->end_time !== null)
                        ? [[
                            'id' => 0,
                            'course_id' => $classroom->course_id,
                            'weekday' => (int) $classroom->weekday,
                            'start_time' => (string) $classroom->start_time,
                            'end_time' => (string) $classroom->end_time,
                        ]]
                        : []),
            ]),
            'courses' => Course::query()
                ->with(['courseCategory', 'coursePrices'])
                ->join('course_categories', 'courses.course_category_id', '=', 'course_categories.id')
                ->orderBy('course_categories.sort_order')
                ->orderBy('course_categories.name')
                ->orderBy('courses.name')
                ->select('courses.*')
                ->get(),
            'teachers' => Teacher::query()
                ->where('status', 'active')
                ->orWhere('id', $classroom->teacher_id)
                ->orderBy('name')
                ->get(['id', 'name']),
            'gradeLevels' => $this->gradeLevelOptions(),
        ]);
    }

    public function editStudents(Classroom $classroom): Response
    {
        $classroom->load([
            'course.coursePrices' => fn ($q) => $q->orderBy('sort_order')->orderBy('id'),
        ]);

        return Inertia::render('Classrooms/Students', [
            'classroom' => [
                'id' => $classroom->id,
                'name' => $classroom->name,
                'course_id' => $classroom->course_id,
            ],
            'course_prices' => $classroom->course !== null
                ? $classroom->course->coursePrices
                    ->map(fn ($p) => [
                        'level' => $p->level,
                        'duration_hours' => (float) ($p->duration_hours ?? 1.0),
                        'tuition' => $p->tuition,
                    ])
                    ->values()
                    ->all()
                : [],
            'students' => Student::query()
                ->where('status', 'active')
                ->orderBy('name')
                ->orderBy('id')
                ->get(['id', 'name', 'school_segment']),
            'enrollments' => Enrollment::query()
                ->where('classroom_id', $classroom->id)
                ->where('status', 'active')
                ->with(['student:id,name,school_segment', 'tuitionRates'])
                ->latest('id')
                ->get(['id', 'classroom_id', 'student_id', 'tuition_amount', 'status'])
                ->map(function (Enrollment $enrollment) use ($classroom): array {
                    $prices = $classroom->course?->coursePrices ?? collect();

                    return [
                        'id' => $enrollment->id,
                        'student_id' => $enrollment->student_id,
                        'tuition_amount' => (int) $enrollment->tuition_amount,
                        'status' => $enrollment->status,
                        'student' => $enrollment->student !== null ? [
                            'id' => $enrollment->student->id,
                            'name' => $enrollment->student->name,
                            'school_segment' => $enrollment->student->school_segment,
                        ] : null,
                        'tuition_by_duration' => EnrollmentTuitionSync::displayRows(
                            $enrollment->tuitionRates,
                            $prices,
                            $enrollment->student?->school_segment,
                        ),
                    ];
                })
                ->values()
                ->all(),
        ]);
    }

    public function attendanceRate(Request $request, Classroom $classroom): Response
    {
        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'month' => ['nullable', 'integer', 'between:1,12'],
        ]);

        $today = Carbon::today();
        $year = (int) ($validated['year'] ?? $today->year);
        $month = (int) ($validated['month'] ?? $today->month);
        $monthStart = Carbon::create($year, $month, 1)->startOfDay();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $classroom->load([
            'course.courseCategory',
            'schedules:id,classroom_id,weekday,start_time,end_time',
            'extraSessionModels.students:id,name',
        ]);

        $enrollments = Enrollment::query()
            ->where('classroom_id', $classroom->id)
            ->where('status', 'active')
            ->with('student:id,name,phone')
            ->orderBy('id')
            ->get();

        // 排課日出勤（含請假）：實際記在本班的紀錄。
        $attendanceMap = Attendance::query()
            ->where('classroom_id', $classroom->id)
            ->whereDate('class_date', '>=', $monthStart->toDateString())
            ->whereDate('class_date', '<=', $monthEnd->toDateString())
            ->get()
            ->groupBy('student_id');

        // 歸帳到本班的篩選：補課／加課若有指定補課班級則以其為準，否則照實際點名班級。
        $billedToThisClassroom = function ($q) use ($classroom): void {
            $q->where('makeup_for_classroom_id', $classroom->id)
                ->orWhere(function ($qq) use ($classroom): void {
                    $qq->whereNull('makeup_for_classroom_id')
                        ->where('classroom_id', $classroom->id);
                });
        };

        // 補課：歸帳到本班（補的是本班的課），可能來自其他班級的點名紀錄。
        $makeupByStudent = Attendance::query()
            ->where('status', 'makeup')
            ->where($billedToThisClassroom)
            ->where(function ($q) use ($monthStart, $monthEnd): void {
                $q->whereDate('class_date', '>=', $monthStart->toDateString())
                    ->whereDate('class_date', '<=', $monthEnd->toDateString())
                    ->orWhere(function ($qq) use ($monthStart, $monthEnd): void {
                        $qq->where('note', 'like', '補課日期:%')
                            ->whereRaw('STR_TO_DATE(REPLACE(SUBSTRING_INDEX(note, " ", 1), "補課日期:", ""), "%Y-%m-%d") >= ?', [$monthStart->toDateString()])
                            ->whereRaw('STR_TO_DATE(REPLACE(SUBSTRING_INDEX(note, " ", 1), "補課日期:", ""), "%Y-%m-%d") <= ?', [$monthEnd->toDateString()]);
                    });
            })
            ->get()
            ->groupBy('student_id');

        // 加課：歸帳到本班的額外出勤。
        $extraByStudent = Attendance::query()
            ->where('status', 'extra')
            ->where($billedToThisClassroom)
            ->whereDate('class_date', '>=', $monthStart->toDateString())
            ->whereDate('class_date', '<=', $monthEnd->toDateString())
            ->get()
            ->groupBy('student_id');

        $weekdayList = $classroom->schedules->pluck('weekday')->map(fn ($d) => (int) $d)->unique()->values()->all();
        if ($weekdayList === [] && $classroom->weekday !== null) {
            $weekdayList = [(int) $classroom->weekday];
        }
        $rows = $enrollments->map(function (Enrollment $enrollment) use ($attendanceMap, $makeupByStudent, $extraByStudent, $monthStart, $monthEnd, $classroom, $weekdayList): array {
            $scheduledDates = $classroom->scheduledWeekdayDatesInMonth($weekdayList, $monthStart, $monthEnd);
            $scheduledDates = Classroom::mergeRecurringDatesWithExtras(
                $scheduledDates,
                $classroom->extra_sessions,
                $monthStart,
                $monthEnd,
                $classroom,
                $enrollment->student_id,
            );
            $expectedDays = count($scheduledDates);

            $student = $enrollment->student;
            $records = collect($attendanceMap->get($enrollment->student_id, collect()));
            $recordsByDate = $records->groupBy(fn ($r) => Carbon::parse($r->class_date)->toDateString());
            $makeupRecords = collect($makeupByStudent->get($enrollment->student_id, collect()));
            $makeupMap = MakeupAttendanceNote::originalMissedToMakeupSessionMap(
                $makeupRecords->all(),
            );
            $dateBoxes = collect($scheduledDates)->map(function (string $date) use ($recordsByDate, $makeupMap): array {
                $dateRecords = collect($recordsByDate->get($date, collect()));
                $presentOnDay = $dateRecords->contains(fn ($r) => in_array($r->status, ['present', 'late'], true));
                $excusedOnDay = $dateRecords->contains(fn ($r) => $r->status === 'excused');
                $makeupSessionDate = $makeupMap[$date] ?? null;
                $madeUp = $makeupSessionDate !== null;
                $attended = $presentOnDay || $madeUp;
                $isExcused = $excusedOnDay && ! $presentOnDay && ! $madeUp;

                $displayText = '未出席';
                $makeupNote = null;
                if ($presentOnDay) {
                    $displayText = '已出席';
                } elseif ($madeUp) {
                    $displayText = '已出席';
                    $makeupNote = '已於 '.$this->formatMakeupClassDisplay($makeupSessionDate).' 補課';
                } elseif ($isExcused) {
                    $displayText = '請假';
                }

                return [
                    'date' => $date,
                    'attended' => $attended,
                    'use_present_style' => $presentOnDay || $madeUp,
                    'is_excused' => $isExcused,
                    'display_text' => $displayText,
                    'makeup_note' => $makeupNote,
                ];
            })->values()->all();
            $attendedCount = collect($dateBoxes)->where('attended', true)->count();
            $absentCount = max($expectedDays - $attendedCount, 0);
            $makeupDays = collect(array_keys($makeupMap))->filter(fn ($orig) => in_array($orig, $scheduledDates, true))->count();

            $extras = collect($extraByStudent->get($enrollment->student_id, collect()));
            $extraDays = $extras->count();
            $extraBoxes = $extras
                ->sortBy(fn ($r) => Carbon::parse($r->class_date)->toDateString())
                ->map(fn ($r) => [
                    'date' => Carbon::parse($r->class_date)->toDateString(),
                    'display_text' => '已加課',
                ])
                ->values()
                ->all();

            // 實際出席天數 = 應上課天數 + 加課天數；出席率 = 已出席（含加課）／實際出席天數。
            $attendedInclExtra = $attendedCount + $extraDays;
            $actualTotal = $expectedDays + $extraDays;
            $attendanceRate = $actualTotal > 0 ? round(($attendedInclExtra / $actualTotal) * 100, 1) : null;

            $makeupBoxesGrouped = [];
            foreach ($makeupMap as $originalDate => $sessionDate) {
                if (! in_array($originalDate, $scheduledDates, true)) {
                    continue;
                }
                if (! isset($makeupBoxesGrouped[$sessionDate])) {
                    $makeupBoxesGrouped[$sessionDate] = [
                        'makeup_session_date' => $sessionDate,
                        'makeup_time_display' => $this->formatMakeupClassDisplay($sessionDate),
                        'original_dates' => [],
                    ];
                }
                $makeupBoxesGrouped[$sessionDate]['original_dates'][] = $originalDate;
            }
            $makeupBoxes = collect($makeupBoxesGrouped)
                ->map(function (array $box): array {
                    $box['original_dates'] = array_values(array_unique($box['original_dates']));
                    sort($box['original_dates']);

                    return $box;
                })
                ->sortBy('makeup_session_date')
                ->values()
                ->all();

            return [
                'student_id' => $enrollment->student_id,
                'student_name' => $student?->name ?? '-',
                'student_phone' => $student?->phone,
                'expected_days' => $expectedDays,
                'attended_days' => $attendedInclExtra,
                'actual_total_days' => $actualTotal,
                'absent_days' => $absentCount,
                'makeup_days' => $makeupDays,
                'extra_days' => $extraDays,
                'attendance_rate' => $attendanceRate,
                'date_boxes' => $dateBoxes,
                'makeup_boxes' => $makeupBoxes,
                'extra_boxes' => $extraBoxes,
            ];
        })->values();

        return Inertia::render('Classrooms/AttendanceRate', [
            'classroom' => [
                'id' => $classroom->id,
                'name' => $classroom->name,
                'course_name' => $classroom->course?->name ?? '-',
                'course_category_name' => $classroom->course?->courseCategory?->name ?? '-',
                'weekday_labels' => collect($weekdayList)->map(fn ($d) => '週'.$this->weekdayLabel((int) $d))->join('、'),
            ],
            'filters' => [
                'year' => (string) $year,
                'month' => (string) $month,
            ],
            'rows' => $rows,
        ]);
    }

    public function storeEnrollments(Request $request, Classroom $classroom): RedirectResponse
    {
        $validated = $request->validate([
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.student_id' => ['required', 'integer', 'exists:students,id', 'distinct'],
            'entries.*.tuition_amount' => ['required', 'integer', 'min:0'],
            'entries.*.tuition_by_duration' => ['nullable', 'array'],
            'entries.*.tuition_by_duration.*.duration_hours' => ['required', 'numeric', 'min:0.5', 'max:24'],
            'entries.*.tuition_by_duration.*.tuition_amount' => ['required', 'integer', 'min:0'],
        ]);

        $classroom->load(['course.coursePrices']);

        foreach ($validated['entries'] as $entry) {
            $enrollment = Enrollment::query()->updateOrCreate(
                [
                    'classroom_id' => $classroom->id,
                    'student_id' => $entry['student_id'],
                ],
                [
                    'tuition_amount' => $entry['tuition_amount'],
                    'status' => 'active',
                    'joined_at' => now()->toDateString(),
                    'left_at' => null,
                ]
            );

            if (! empty($entry['tuition_by_duration'])) {
                EnrollmentTuitionSync::sync($enrollment, $entry['tuition_by_duration']);
            }
        }

        return to_route('classrooms.students.index', $classroom);
    }

    public function updateEnrollment(Request $request, Classroom $classroom, Enrollment $enrollment): RedirectResponse
    {
        abort_if((int) $enrollment->classroom_id !== (int) $classroom->id, 404);

        $validated = $request->validate([
            'tuition_amount' => ['required', 'integer', 'min:0'],
            'tuition_by_duration' => ['nullable', 'array'],
            'tuition_by_duration.*.duration_hours' => ['required', 'numeric', 'min:0.5', 'max:24'],
            'tuition_by_duration.*.tuition_amount' => ['required', 'integer', 'min:0'],
        ]);

        $enrollment->update([
            'tuition_amount' => $validated['tuition_amount'],
        ]);

        if (! empty($validated['tuition_by_duration'])) {
            EnrollmentTuitionSync::sync($enrollment, $validated['tuition_by_duration']);
        } elseif ($request->has('tuition_by_duration')) {
            EnrollmentTuitionSync::sync($enrollment, []);
        }

        return back();
    }

    public function destroyEnrollment(Classroom $classroom, Enrollment $enrollment): RedirectResponse
    {
        abort_if((int) $enrollment->classroom_id !== (int) $classroom->id, 404);

        if ($enrollment->status === 'active') {
            $enrollment->update([
                'status' => 'left',
                'left_at' => now()->toDateString(),
            ]);
        }

        return back();
    }

    public function update(Request $request, Classroom $classroom): RedirectResponse
    {
        if ($request->input('color') === '') {
            $request->merge(['color' => null]);
        }

        $validated = $request->validate([
            'grade_level_id' => ['required', 'integer', 'exists:grade_levels,id'],
            'teacher_id' => ['nullable', 'exists:teachers,id'],
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'schedules' => ['required', 'array', 'min:1'],
            'schedules.*.course_id' => ['required', 'integer', 'exists:courses,id'],
            'schedules.*.weekday' => ['required', 'integer', 'between:1,7'],
            'schedules.*.start_time' => ['required', 'date_format:H:i'],
            'schedules.*.end_time' => ['required', 'date_format:H:i'],
            'active_periods' => ['nullable', 'array', 'max:30'],
            'active_periods.*.start_date' => ['nullable', 'date'],
            'active_periods.*.end_date' => ['nullable', 'date'],
            'status' => ['required', 'in:active,paused'],
        ]);

        $activePeriods = Classroom::normalizeActivePeriodsInput($validated['active_periods'] ?? null);
        $this->assertActivePeriodRangesValid($activePeriods);

        $schedules = $this->validatedSchedules($validated['schedules']);
        $primaryCourseId = $schedules[0]['course_id'] ?? null;

        $this->assertTeacherHasNoActiveScheduleConflict(
            isset($validated['teacher_id']) ? (int) $validated['teacher_id'] : null,
            $schedules,
            $validated['status'],
            $classroom->id,
            [],
        );

        $classroom->update([
            'course_id' => $primaryCourseId,
            'grade_level_id' => $validated['grade_level_id'],
            'teacher_id' => $validated['teacher_id'] ?? null,
            'name' => $validated['name'],
            'color' => $validated['color'] ?? null,
            'weekday' => $schedules[0]['weekday'] ?? null,
            'start_time' => $schedules[0]['start_time'] ?? null,
            'end_time' => $schedules[0]['end_time'] ?? null,
            'active_periods' => $activePeriods,
            'start_date' => null,
            'end_date' => null,
            'status' => $validated['status'],
        ]);
        $this->syncSchedules($classroom, $schedules);

        return to_route('classrooms.index');
    }

    public function destroy(Classroom $classroom): RedirectResponse
    {
        $classroom->delete();

        return to_route('classrooms.index');
    }

    /**
     * 依需求：允許同一老師在同一時段授課，因此不再阻擋跨班級的時段重疊。
     *
     * 保留此方法與呼叫點以便日後需要時恢復檢查；目前不做任何驗證。
     */
    private function assertTeacherHasNoActiveScheduleConflict(
        ?int $teacherId,
        array $schedules,
        string $status,
        ?int $ignoreClassroomId,
        array $extraSessions = [],
    ): void {
        // 同一老師可排在相同（重疊）時段，無需檢查。
    }

    private function validatedSchedules(array $schedules): array
    {
        $normalized = [];
        foreach ($schedules as $i => $schedule) {
            $start = $this->normalizeTimeForCompare((string) ($schedule['start_time'] ?? ''));
            $end = $this->normalizeTimeForCompare((string) ($schedule['end_time'] ?? ''));
            if ($start >= $end) {
                throw ValidationException::withMessages([
                    "schedules.{$i}.end_time" => '結束時間需晚於開始時間。',
                ]);
            }
            $normalized[] = [
                'course_id' => (int) $schedule['course_id'],
                'weekday' => (int) $schedule['weekday'],
                'start_time' => $start,
                'end_time' => $end,
            ];
        }

        for ($i = 0; $i < count($normalized); $i++) {
            for ($j = $i + 1; $j < count($normalized); $j++) {
                if ($normalized[$i]['weekday'] !== $normalized[$j]['weekday']) {
                    continue;
                }
                if ($this->timeRangesOverlap(
                    $normalized[$i]['start_time'],
                    $normalized[$i]['end_time'],
                    $normalized[$j]['start_time'],
                    $normalized[$j]['end_time'],
                )) {
                    throw ValidationException::withMessages([
                        'schedules' => "同一班級在週{$this->weekdayLabel($normalized[$i]['weekday'])} 有重疊時段，請調整。",
                    ]);
                }
            }
        }

        usort($normalized, fn ($a, $b) => $a['weekday'] <=> $b['weekday'] ?: strcmp($a['start_time'], $b['start_time']));

        return $normalized;
    }

    private function syncSchedules(Classroom $classroom, array $schedules): void
    {
        if (! Schema::hasTable('classroom_schedules')) {
            return;
        }
        $classroom->schedules()->delete();
        if ($schedules === []) {
            return;
        }
        $classroom->schedules()->createMany($schedules);
    }

    private function normalizeTimeForCompare(string $time): string
    {
        $time = trim($time);
        if (strlen($time) === 5) {
            return $time.':00';
        }

        return $time;
    }

    private function timeRangesOverlap(string $aStart, string $aEnd, string $bStart, string $bEnd): bool
    {
        $bStart = $this->normalizeTimeForCompare($bStart);
        $bEnd = $this->normalizeTimeForCompare($bEnd);

        return $aStart < $bEnd && $bStart < $aEnd;
    }

    private function weekdayLabel(int $weekday): string
    {
        return match ($weekday) {
            1 => '一',
            2 => '二',
            3 => '三',
            4 => '四',
            5 => '五',
            6 => '六',
            7 => '日',
            default => '?',
        };
    }

    private function formatMakeupClassDisplay(string $dateYmd): string
    {
        try {
            $d = Carbon::parse($dateYmd)->startOfDay();
            $w = match ($d->dayOfWeek) {
                0 => '日',
                1 => '一',
                2 => '二',
                3 => '三',
                4 => '四',
                5 => '五',
                6 => '六',
                default => '?',
            };

            return $d->format('Y-m-d').'（週'.$w.'）';
        } catch (\Throwable) {
            return $dateYmd;
        }
    }

    /**
     * @param  list<array{start_date: ?string, end_date: ?string}>|null  $periods
     */
    private function assertActivePeriodRangesValid(?array $periods): void
    {
        if ($periods === null) {
            return;
        }
        foreach ($periods as $row) {
            $s = $row['start_date'] ?? null;
            $e = $row['end_date'] ?? null;
            if ($s !== null && $e !== null && $e < $s) {
                throw ValidationException::withMessages([
                    'active_periods' => '每個開課區間的結束日必須晚於或等於開始日。',
                ]);
            }
        }
    }

    /**
     * @return list<array{id:int,name:string,code:int}>
     */
    private function gradeLevelOptions(): array
    {
        return GradeLevel::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('code')
            ->get(['id', 'name', 'code'])
            ->map(fn (GradeLevel $g): array => [
                'id' => $g->id,
                'name' => $g->name,
                'code' => $g->code,
            ])
            ->all();
    }
}
