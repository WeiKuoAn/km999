<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use App\Models\Teacher;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $validated = $request->validate([
            'teacher_id' => ['nullable', 'integer', 'exists:teachers,id'],
        ]);

        $user = auth()->user();
        $teacherId = null;
        if ($user?->role === User::ROLE_TEACHER) {
            $teacherId = $user->teacher?->id;
        } elseif (isset($validated['teacher_id'])) {
            $teacherId = (int) $validated['teacher_id'];
        }

        $hasSchedulesTable = Schema::hasTable('classroom_schedules');

        $query = Classroom::query()
            ->where('status', 'active')
            ->with([
                'course.courseCategory',
                'teacher:id,name',
                'extraSessionModels',
            ])
            ->orderBy('name');

        if ($teacherId !== null) {
            $query->where('teacher_id', $teacherId);
        }

        if ($hasSchedulesTable) {
            $query->with([
                'schedules:id,classroom_id,course_id,weekday,start_time,end_time',
                'schedules.course:id,course_category_id,name',
                'schedules.course.courseCategory:id,name',
            ]);
        }

        $canFilterByTeacher = $user?->role !== User::ROLE_TEACHER;

        return Inertia::render('Calendar', [
            'scheduleClassrooms' => $query->get()->map(function (Classroom $classroom) use ($hasSchedulesTable) {
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
            }),
            'teacherOptions' => $canFilterByTeacher
                ? Teacher::query()->where('status', 'active')->orderBy('name')->get(['id', 'name'])->all()
                : [],
            'canFilterByTeacher' => $canFilterByTeacher,
            'filters' => [
                'teacher_id' => $teacherId === null ? '' : (string) $teacherId,
            ],
        ]);
    }
}
