<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\Teacher;
use App\Models\User;
use App\Support\ClassroomRecurringScheduleLabel;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class TeacherController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();
        $query = Teacher::query()->with([
            'user:id,name,email',
            'classrooms' => fn ($q) => $q->with('course:id,name')->orderBy('name'),
        ]);
        if ($user?->role === User::ROLE_TEACHER) {
            $query->where('user_id', $user->id);
        }

        return Inertia::render('Teachers/Index', [
            'teachers' => $query
                ->latest('id')
                ->paginate(50)
                ->through(function (Teacher $teacher): array {
                    $courses = $teacher->classrooms
                        ->pluck('course.name')
                        ->filter()
                        ->unique()
                        ->sort()
                        ->values()
                        ->all();
                    $classrooms = $teacher->classrooms
                        ->sortBy('name')
                        ->map(fn (Classroom $c) => [
                            'id' => $c->id,
                            'name' => $c->name,
                            'color' => $c->color,
                        ])
                        ->values()
                        ->all();

                    return [
                        'id' => $teacher->id,
                        'name' => $teacher->name,
                        'phone' => $teacher->phone,
                        'status' => $teacher->status,
                        'user' => $teacher->user
                            ? [
                                'id' => $teacher->user->id,
                                'name' => $teacher->user->name,
                                'email' => $teacher->user->email,
                            ]
                            : null,
                        'courses' => $courses,
                        'classrooms' => $classrooms,
                    ];
                }),
        ]);
    }

    public function coursesSchedule(Teacher $teacher): Response
    {
        $user = auth()->user();
        if ($user?->role === User::ROLE_TEACHER && (int) $teacher->user_id !== (int) $user->id) {
            abort(403);
        }

        $hasSchedulesTable = Schema::hasTable('classroom_schedules');

        $query = Classroom::query()
            ->where('teacher_id', $teacher->id)
            ->where('status', 'active')
            ->with([
                'course.courseCategory',
                'teacher',
                'extraSessionModels',
            ])
            ->orderBy('name');

        if ($hasSchedulesTable) {
            $query->with('schedules:id,classroom_id,weekday,start_time,end_time');
        }

        $classroomModels = $query->get();

        $scheduleClassrooms = $classroomModels->map(function (Classroom $classroom) use ($hasSchedulesTable) {
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
            $fromDb = $classroom->extraSessionModels->map(fn ($x) => [
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

        return Inertia::render('Teachers/CoursesSchedule', [
            'teacher' => [
                'id' => $teacher->id,
                'name' => $teacher->name,
            ],
            'courseRows' => $courseRows,
            'scheduleClassrooms' => $scheduleClassrooms,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Teachers/Create', [
            'teacherUsers' => User::query()
                ->where('role', User::ROLE_TEACHER)
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['nullable', 'exists:users,id'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'status' => ['required', 'in:active,paused'],
            'note' => ['nullable', 'string'],
        ]);

        Teacher::query()->create($validated);

        return to_route('teachers.index');
    }

    public function edit(Teacher $teacher): Response
    {
        return Inertia::render('Teachers/Edit', [
            'teacher' => $teacher,
            'teacherUsers' => User::query()
                ->where('role', User::ROLE_TEACHER)
                ->orderBy('name')
                ->get(['id', 'name', 'email']),
        ]);
    }

    public function update(Request $request, Teacher $teacher): RedirectResponse
    {
        $validated = $request->validate([
            'user_id' => ['nullable', 'exists:users,id'],
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:32'],
            'status' => ['required', 'in:active,paused'],
            'note' => ['nullable', 'string'],
        ]);

        $teacher->update($validated);

        return to_route('teachers.index');
    }

    public function destroy(Teacher $teacher): RedirectResponse
    {
        $teacher->delete();

        return to_route('teachers.index');
    }
}
