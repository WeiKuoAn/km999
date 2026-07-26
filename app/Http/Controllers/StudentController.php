<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Attendance;
use App\Models\Classroom;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\GradeLevel;
use App\Models\Reconciliation;
use App\Models\Student;
use App\Models\User;
use App\Support\ClassroomRecurringScheduleLabel;
use App\Support\CourseTuition;
use App\Support\EnrollmentTuitionSync;
use App\Support\MakeupAttendanceNote;
use App\Support\StudentCodeGenerator;
use App\Support\StudentEnrollmentSync;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:active,paused'],
        ]);

        $name = trim((string) ($validated['name'] ?? ''));
        $status = $validated['status'] ?? '';

        $user = auth()->user();
        $teacherId = $user?->teacher?->id;

        $query = Student::query();
        if ($user?->role === User::ROLE_TEACHER) {
            if ($teacherId === null) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereHas('enrollments.classroom', fn ($q) => $q->where('teacher_id', $teacherId));
            }
        }

        if ($name !== '') {
            $query->where(function ($q) use ($name) {
                $q->where('name', 'like', '%'.$name.'%')
                    ->orWhere('student_code', 'like', '%'.$name.'%');
            });
        }
        if ($status !== '') {
            $query->where('status', $status);
        }

        return Inertia::render('Students/Index', [
            'students' => $query
                ->with(['gradeLevel:id,name,code'])
                ->latest('id')
                ->paginate(50)
                ->through(function (Student $student): array {
                    return [
                        'id' => $student->id,
                        'student_code' => $student->student_code,
                        'name' => $student->name,
                        'grade_name' => $student->gradeLevel?->name,
                        'phone' => $student->phone,
                        'parent_name' => $student->parent_name,
                        'parent_phone' => $student->parent_phone,
                        'status' => $student->status,
                    ];
                })
                ->withQueryString(),
            'filters' => [
                'name' => $name,
                'status' => $status,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Students/Create', [
            'academicYears' => $this->academicYearOptions(),
            'gradeLevels' => $this->gradeLevelOptions(),
            'defaultAcademicYearId' => AcademicYear::query()->where('is_current', true)->value('id'),
        ]);
    }

    public function nextCode(Request $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validate([
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'grade_level_id' => ['required', 'integer', 'exists:grade_levels,id'],
        ]);

        $year = AcademicYear::query()->findOrFail($validated['academic_year_id']);
        $grade = GradeLevel::query()->findOrFail($validated['grade_level_id']);

        return response()->json([
            'student_code' => StudentCodeGenerator::previewNext($year, $grade),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(array_merge([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'graduate_school' => ['nullable', 'string', 'max:255'],
            'current_school' => ['nullable', 'string', 'max:255'],
            'class_name' => ['nullable', 'string', 'max:64'],
            'id_number' => ['nullable', 'string', 'max:32'],
            'gender' => ['nullable', 'string', 'max:16', 'in:男,女'],
            'status' => ['required', 'in:active,paused'],
            'note' => ['nullable', 'string'],
            'academic_year_id' => ['required', 'integer', 'exists:academic_years,id'],
            'grade_level_id' => ['required', 'integer', 'exists:grade_levels,id'],
        ], $this->studentContactRules()));

        $validated = $this->prepareStudentPayload($validated);

        $year = AcademicYear::query()->findOrFail($validated['academic_year_id']);
        $grade = GradeLevel::query()->findOrFail($validated['grade_level_id']);

        DB::transaction(function () use ($validated, $year, $grade): void {
            $validated['student_code'] = StudentCodeGenerator::allocate($year, $grade);
            $validated['school_segment'] = null;
            Student::query()->create($validated);
        });

        return to_route('students.index');
    }

    public function edit(Student $student): Response
    {
        $this->authorizeTeacherCanViewStudent($student);

        return Inertia::render('Students/Edit', [
            'student' => [
                'id' => $student->id,
                'student_code' => $student->student_code,
                'name' => $student->name,
                'phone' => $student->phone,
                'parent_phones' => $this->parentPhonesForForm($student),
                'graduate_school' => $student->graduate_school,
                'current_school' => $student->current_school,
                'class_name' => $student->class_name,
                'id_number' => $student->id_number,
                'address_city' => $student->address_city,
                'address_district' => $student->address_district,
                'address_zip' => $student->address_zip,
                'address_detail' => $student->address_detail
                    ?? (($student->address_city || $student->address_district) ? null : $student->address),
                'gender' => $student->gender,
                'status' => $student->status,
                'note' => $student->note,
                'academic_year_id' => $student->academic_year_id,
                'grade_level_id' => $student->grade_level_id,
            ],
            'academicYears' => $this->academicYearOptions(),
            'gradeLevels' => $this->gradeLevelOptions(),
        ]);
    }

    public function update(Request $request, Student $student): RedirectResponse
    {
        $this->authorizeTeacherCanViewStudent($student);

        $validated = $request->validate(array_merge([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'graduate_school' => ['nullable', 'string', 'max:255'],
            'current_school' => ['nullable', 'string', 'max:255'],
            'class_name' => ['nullable', 'string', 'max:64'],
            'id_number' => ['nullable', 'string', 'max:32'],
            'gender' => ['nullable', 'string', 'max:16', 'in:男,女'],
            'status' => ['required', 'in:active,paused'],
            'note' => ['nullable', 'string'],
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'grade_level_id' => ['nullable', 'integer', 'exists:grade_levels,id'],
        ], $this->studentContactRules()));

        $validated = $this->prepareStudentPayload($validated);

        DB::transaction(function () use ($student, $validated): void {
            // 已有學號者不重編號；僅補齊尚未有學號的舊資料
            if ($student->student_code === null
                && ! empty($validated['academic_year_id'])
                && ! empty($validated['grade_level_id'])
            ) {
                $year = AcademicYear::query()->findOrFail($validated['academic_year_id']);
                $grade = GradeLevel::query()->findOrFail($validated['grade_level_id']);
                $validated['student_code'] = StudentCodeGenerator::allocate($year, $grade);
            }

            $validated['school_segment'] = null;
            $student->update($validated);
        });

        return to_route('students.edit', $student)->with('success', '已更新成功！');
    }

    public function destroy(Student $student): RedirectResponse
    {
        $this->authorizeTeacherCanViewStudent($student);

        $student->delete();

        return to_route('students.index');
    }

    public function payments(Student $student): Response
    {
        $this->authorizeTeacherCanViewStudent($student);

        $rows = Reconciliation::query()
            ->where('student_id', $student->id)
            ->with([
                'classroom.course.courseCategory',
                'course.courseCategory',
                'settledByUser:id,name',
            ])
            ->orderByDesc('billing_year')
            ->orderByDesc('billing_month')
            ->orderByDesc('id')
            ->paginate(50)
            ->through(fn (Reconciliation $row): array => [
                'id' => $row->id,
                'billing_year' => $row->billing_year,
                'billing_month' => $row->billing_month,
                'classroom_name' => $row->classroom?->name ?? '-',
                'course_name' => $row->course?->name ?? $row->classroom?->course?->name ?? '-',
                'course_category_name' => $row->course?->courseCategory?->name
                    ?? $row->classroom?->course?->courseCategory?->name
                    ?? '-',
                'expected_amount' => (int) $row->expected_amount,
                'paid_amount' => (int) $row->paid_amount,
                'paid_date' => $row->paid_date?->toDateString(),
                'status' => $row->status,
                'settled_by_name' => $row->settledByUser?->name ?? '-',
                'note' => $row->note,
            ]);

        return Inertia::render('Students/Payments', [
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
            ],
            'rows' => $rows,
        ]);
    }

    public function attendanceRate(Request $request, Student $student): Response
    {
        $this->authorizeTeacherCanViewStudent($student);

        $today = Carbon::today();
        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'month' => ['nullable', 'integer', 'between:1,12'],
        ]);
        $year = (int) ($validated['year'] ?? $today->year);
        $month = (int) ($validated['month'] ?? $today->month);

        $monthStart = Carbon::create($year, $month, 1)->startOfDay();
        $monthEnd = $monthStart->copy()->endOfMonth();

        $hasSchedulesTable = Schema::hasTable('classroom_schedules');
        $classroomsQuery = Classroom::query()
            ->whereHas('enrollments', fn ($q) => $q
                ->where('student_id', $student->id)
                ->where('status', 'active'))
            ->with([
                'course.courseCategory',
            ])
            ->orderBy('name');
        if ($hasSchedulesTable) {
            $classroomsQuery->with('schedules:id,classroom_id,weekday,start_time,end_time');
        }
        $classroomsQuery->with('extraSessionModels.students:id,name');

        $user = auth()->user();
        if ($user?->role === User::ROLE_TEACHER) {
            $teacherId = $user->teacher?->id;
            if ($teacherId === null) {
                $classroomsQuery->whereRaw('1=0');
            } else {
                $classroomsQuery->where('teacher_id', $teacherId);
            }
        }
        $classrooms = $classroomsQuery->get();

        $attendanceByClassroom = Attendance::query()
            ->where('student_id', $student->id)
            ->where(function ($q) use ($monthStart, $monthEnd): void {
                $q->whereDate('class_date', '>=', $monthStart->toDateString())
                    ->whereDate('class_date', '<=', $monthEnd->toDateString())
                    ->orWhere(function ($qq) use ($monthStart, $monthEnd): void {
                        $qq->where('status', 'makeup')
                            ->where('note', 'like', '補課日期:%')
                            ->whereRaw('STR_TO_DATE(REPLACE(note, "補課日期:", ""), "%Y-%m-%d") >= ?', [$monthStart->toDateString()])
                            ->whereRaw('STR_TO_DATE(REPLACE(note, "補課日期:", ""), "%Y-%m-%d") <= ?', [$monthEnd->toDateString()]);
                    });
            })
            ->get()
            ->groupBy('classroom_id');

        // 加課：歸帳到 makeup_for_classroom_id（沒有則記在實際點名班級）。
        $extraByClassroom = Attendance::query()
            ->where('student_id', $student->id)
            ->where('status', 'extra')
            ->whereDate('class_date', '>=', $monthStart->toDateString())
            ->whereDate('class_date', '<=', $monthEnd->toDateString())
            ->get()
            ->groupBy(fn ($r) => (int) ($r->makeup_for_classroom_id ?? $r->classroom_id));

        // 補課：歸帳到 makeup_for_classroom_id（補的是哪一門課），可能與實際點名班級不同。
        $makeupByClassroom = Attendance::query()
            ->where('student_id', $student->id)
            ->where('status', 'makeup')
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
            ->groupBy(fn ($r) => (int) ($r->makeup_for_classroom_id ?? $r->classroom_id));

        $rows = $classrooms->map(function (Classroom $classroom) use ($attendanceByClassroom, $extraByClassroom, $makeupByClassroom, $monthStart, $monthEnd, $hasSchedulesTable, $student): array {
            $weekdayList = $hasSchedulesTable
                ? $classroom->schedules->pluck('weekday')->filter()->map(fn ($d) => (int) $d)->unique()->values()->all()
                : (($classroom->weekday !== null) ? [(int) $classroom->weekday] : []);

            $scheduledDates = $classroom->scheduledWeekdayDatesInMonth($weekdayList, $monthStart, $monthEnd);
            $scheduledDates = Classroom::mergeRecurringDatesWithExtras(
                $scheduledDates,
                $classroom->extra_sessions,
                $monthStart,
                $monthEnd,
                $classroom,
                $student->id,
            );
            $expectedDays = count($scheduledDates);

            $records = collect($attendanceByClassroom->get($classroom->id, collect()));
            $recordsByDate = $records->groupBy(fn ($r) => Carbon::parse($r->class_date)->toDateString());
            // 補課依「補課班級」歸帳，可能來自其他班級的點名紀錄。
            $makeupRecords = collect($makeupByClassroom->get($classroom->id, collect()));
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
                    // 已補課：視為已出席，並標註實際補課時間。
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

            // 加課：歸到本班的加課出勤（額外多上的課）。
            $extras = collect($extraByClassroom->get($classroom->id, collect()));
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
                'classroom_id' => $classroom->id,
                'classroom_name' => $classroom->name,
                'course_name' => $classroom->course?->name ?? '-',
                'course_category_name' => $classroom->course?->courseCategory?->name ?? '-',
                'weekday_labels' => implode('、', array_map(fn ($d) => '週'.$this->weekdayLabel($d), $weekdayList)),
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

        return Inertia::render('Students/AttendanceRate', [
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
            ],
            'filters' => [
                'year' => (string) $year,
                'month' => (string) $month,
            ],
            'rows' => $rows,
        ]);
    }

    public function coursesSchedule(Student $student): Response
    {
        $this->authorizeTeacherCanViewStudent($student);

        $hasSchedulesTable = Schema::hasTable('classroom_schedules');

        $enrollmentQuery = Enrollment::query()
            ->where('student_id', $student->id)
            ->where('status', 'active')
            ->whereHas('student', fn ($q) => $q->where('status', 'active'));

        $user = auth()->user();
        if ($user?->role === User::ROLE_TEACHER) {
            $teacherId = $user->teacher?->id;
            $classroomIds = $teacherId === null
                ? []
                : (clone $enrollmentQuery)
                    ->whereHas('classroom', fn ($q) => $q->where('teacher_id', $teacherId))
                    ->pluck('classroom_id')
                    ->unique()
                    ->values()
                    ->all();
        } else {
            $classroomIds = $enrollmentQuery->pluck('classroom_id')->unique()->values()->all();
        }

        $query = Classroom::query()
            ->whereIn('id', $classroomIds)
            ->where('status', 'active')
            ->with([
                'course.courseCategory',
                'teacher',
                'extraSessionModels.students:id',
            ])
            ->orderBy('name');

        if ($hasSchedulesTable) {
            $query->with('schedules:id,classroom_id,weekday,start_time,end_time');
        }

        $classroomModels = $query->get();

        $scheduleClassrooms = $classroomModels->map(function (Classroom $classroom) use ($hasSchedulesTable, $student) {
            $arr = $classroom->toArray();
            if (! $hasSchedulesTable) {
                $arr['schedules'] = ($classroom->weekday !== null && $classroom->start_time !== null && $classroom->end_time !== null)
                    ? [[
                        'weekday' => (int) $classroom->weekday,
                        'start_time' => (string) $classroom->start_time,
                        'end_time' => (string) $classroom->end_time,
                    ]]
                    : [];
            }
            $fromDb = $classroom->extraSessionModels
                ->filter(fn ($x) => $x->students->isEmpty() || $x->students->contains('id', $student->id))
                ->map(fn ($x) => [
                    'date' => $x->session_date->toDateString(),
                    'start_time' => Carbon::parse($x->start_time)->format('H:i:s'),
                    'end_time' => Carbon::parse($x->end_time)->format('H:i:s'),
                ])->values()->all();
            $fromJson = is_array($classroom->extra_sessions) ? $classroom->extra_sessions : [];
            $arr['extra_sessions'] = array_values(array_merge($fromJson, $fromDb));
            $arr['date_range_unrestricted'] = $classroom->dateRangeUnrestricted();
            $arr['teaching_periods'] = $classroom->teachingPeriodsForFrontend();

            return $arr;
        });

        $courseRows = $classroomModels
            ->groupBy('course_id')
            ->map(function ($group) use ($hasSchedulesTable) {
                /** @var Collection<int, Classroom> $group */
                $first = $group->first();
                $names = $group->pluck('name')->unique()->sort()->values()->implode('、');
                $scheduleLabel = ClassroomRecurringScheduleLabel::summarizeCollection($group, $hasSchedulesTable);

                if ($first->course) {
                    return [
                        'course_category_name' => $first->course->courseCategory?->name ?? '—',
                        'course_name' => $first->course->name,
                        'classroom_count' => $group->count(),
                        'classrooms_label' => $names !== '' ? $names : '—',
                        'schedule_label' => $scheduleLabel,
                    ];
                }

                return [
                    'course_category_name' => '—',
                    'course_name' => '（未指定課程）',
                    'classroom_count' => $group->count(),
                    'classrooms_label' => $names !== '' ? $names : '—',
                    'schedule_label' => $scheduleLabel,
                ];
            })
            ->values()
            ->sortBy(fn (array $row) => [$row['course_category_name'], $row['course_name']])
            ->values()
            ->all();

        return Inertia::render('Students/CoursesSchedule', [
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
            ],
            'courseRows' => $courseRows,
            'scheduleClassrooms' => $scheduleClassrooms,
        ]);
    }

    /**
     * @return array<int, array{id: int, name: string, color: string|null, course_label: string}>
     */
    private function classroomOptionsForForm(?User $user): array
    {
        $query = Classroom::query()
            ->where('status', 'active')
            ->with(['course.courseCategory', 'course.coursePrices'])
            ->orderBy('name');

        if ($user?->role === User::ROLE_TEACHER) {
            $teacherId = $user->teacher?->id;
            if ($teacherId === null) {
                return [];
            }
            $query->where('teacher_id', $teacherId);
        }

        return $query->get()->map(function (Classroom $classroom): array {
            $course = $classroom->course;

            return [
                'id' => $classroom->id,
                'name' => $classroom->name,
                'color' => $classroom->color,
                'course_label' => $course !== null
                    ? $this->formatCourseLabelForOption($course)
                    : '—',
                'course_prices' => $course !== null
                    ? $course->coursePrices->map(fn ($p) => [
                        'level' => $p->level,
                        'duration_hours' => (float) ($p->duration_hours ?? 1.0),
                        'tuition' => (int) $p->tuition,
                    ])->values()->all()
                    : [],
            ];
        })->values()->all();
    }

    private function formatCourseLabelForOption(Course $course): string
    {
        $category = $course->courseCategory?->name ?? '—';
        $prices = $course->coursePrices;
        if ($prices->isEmpty()) {
            return "{$category} / {$course->name}";
        }

        $tiers = CourseTuition::formatPriceTiersSummary($prices);

        return "{$category} / {$course->name}（{$tiers}）";
    }

    /**
     * 將該學生所有「上課中」報名的學費，依新年級重新對應課程年級價。
     * 已繳費的月份在學費袋是以 Reconciliation 快照顯示，因此不受此變更影響。
     */
    private function repriceActiveEnrollmentsForGrade(Student $student): void
    {
        $student->loadMissing('gradeLevel');
        $gradeName = $student->gradeLevel?->name;

        $enrollments = Enrollment::query()
            ->where('student_id', $student->id)
            ->where('status', 'active')
            ->with('classroom.course.coursePrices')
            ->get();

        foreach ($enrollments as $enrollment) {
            $prices = $enrollment->classroom?->course?->coursePrices;
            if ($prices === null) {
                continue;
            }
            EnrollmentTuitionSync::seedFromCoursePrices(
                $enrollment,
                $prices,
                $gradeName,
            );
        }
    }

    private function authorizeTeacherCanViewStudent(Student $student): void
    {
        $user = auth()->user();
        if ($user?->role !== User::ROLE_TEACHER) {
            return;
        }
        $teacherId = $user->teacher?->id;
        if ($teacherId === null) {
            abort(403, '你沒有可查看的學生資料。');
        }

        $canView = $student->enrollments()
            ->whereHas('classroom', fn ($q) => $q->where('teacher_id', $teacherId))
            ->exists();
        if (! $canView) {
            abort(403, '你只能查看自己班級的學生資料。');
        }
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
     * @return list<array{id:int,year_code:string,name:string,is_current:bool}>
     */
    private function academicYearOptions(): array
    {
        return AcademicYear::query()
            ->orderByDesc('year_code')
            ->get(['id', 'year_code', 'name', 'is_current'])
            ->map(fn (AcademicYear $y): array => [
                'id' => $y->id,
                'year_code' => $y->year_code,
                'name' => $y->displayName(),
                'is_current' => $y->is_current,
            ])
            ->all();
    }

    /**
     * @return list<array{id:int,name:string,code:int,code_padded:string}>
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
                'code_padded' => $g->codePadded(),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function studentContactRules(): array
    {
        return [
            'parent_phones' => ['nullable', 'array'],
            'parent_phones.*.title' => ['nullable', 'string', 'max:32'],
            'parent_phones.*.phone' => ['nullable', 'string', 'max:32'],
            'address_city' => ['nullable', 'string', 'max:32'],
            'address_district' => ['nullable', 'string', 'max:32'],
            'address_zip' => ['nullable', 'string', 'max:8'],
            'address_detail' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function prepareStudentPayload(array $validated): array
    {
        $phones = collect($validated['parent_phones'] ?? [])
            ->map(fn ($phone): array => [
                'title' => trim((string) ($phone['title'] ?? '')),
                'phone' => trim((string) ($phone['phone'] ?? '')),
            ])
            ->filter(fn (array $phone): bool => $phone['title'] !== '' || $phone['phone'] !== '')
            ->values()
            ->all();

        $validated['parent_phones'] = $phones !== [] ? $phones : null;
        $first = $phones[0] ?? null;
        $validated['parent_name'] = $first['title'] ?? null;
        $validated['parent_phone'] = $first['phone'] ?? null;

        $zip = trim((string) ($validated['address_zip'] ?? ''));
        $city = trim((string) ($validated['address_city'] ?? ''));
        $district = trim((string) ($validated['address_district'] ?? ''));
        $detail = trim((string) ($validated['address_detail'] ?? ''));

        $validated['address_city'] = $city !== '' ? $city : null;
        $validated['address_district'] = $district !== '' ? $district : null;
        $validated['address_zip'] = $zip !== '' ? $zip : null;
        $validated['address_detail'] = $detail !== '' ? $detail : null;

        $addressParts = array_filter([$zip, $city.$district, $detail], fn (string $part): bool => $part !== '');
        $validated['address'] = $addressParts !== [] ? implode(' ', $addressParts) : null;

        return $validated;
    }

    /**
     * @return list<array{title:string,phone:string}>
     */
    private function parentPhonesForForm(Student $student): array
    {
        if (is_array($student->parent_phones) && $student->parent_phones !== []) {
            return array_map(fn (array $phone): array => [
                'title' => (string) ($phone['title'] ?? ''),
                'phone' => (string) ($phone['phone'] ?? ''),
            ], $student->parent_phones);
        }

        if ($student->parent_phone || $student->parent_name) {
            return [
                ['title' => $student->parent_name ?? '', 'phone' => $student->parent_phone ?? ''],
                ['title' => '', 'phone' => ''],
            ];
        }

        return [
            ['title' => '', 'phone' => ''],
            ['title' => '', 'phone' => ''],
        ];
    }
}
