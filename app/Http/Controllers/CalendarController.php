<?php

namespace App\Http\Controllers;

use App\Models\Course;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CalendarController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $courses = Course::query()
            ->where('status', 'active')
            ->with('courseCategory:id,name')
            ->orderBy('name')
            ->get();

        return Inertia::render('Calendar', [
            'scheduleClassrooms' => $courses->map(function (Course $course) {
                $category = $course->courseCategory
                    ? ['name' => $course->courseCategory->name]
                    : null;

                $schedules = collect(is_array($course->schedules) ? $course->schedules : [])
                    ->filter(function ($row) {
                        return is_array($row)
                            && isset($row['weekday'], $row['start_time'], $row['end_time'])
                            && $row['weekday'] !== null
                            && $row['start_time'] !== null
                            && $row['end_time'] !== null
                            && $row['start_time'] !== ''
                            && $row['end_time'] !== '';
                    })
                    ->map(function (array $row) use ($course, $category) {
                        $level = isset($row['level']) ? trim((string) $row['level']) : '';

                        return [
                            'weekday' => (int) $row['weekday'],
                            'start_time' => $this->normalizeTime((string) $row['start_time']),
                            'end_time' => $this->normalizeTime((string) $row['end_time']),
                            'level' => $level !== '' ? $level : null,
                            'course' => [
                                'name' => $level !== '' ? $level : $course->name,
                                'course_category' => $category,
                            ],
                        ];
                    })
                    ->values()
                    ->all();

                return [
                    'id' => $course->id,
                    'name' => $course->name,
                    'color' => $course->color,
                    'start_date' => null,
                    'end_date' => null,
                    'date_range_unrestricted' => true,
                    'teaching_periods' => [],
                    'schedules' => $schedules,
                    'extra_sessions' => [],
                    'course' => [
                        'name' => $course->name,
                        'course_category' => $category,
                    ],
                    'teacher' => null,
                ];
            })->values()->all(),
            'teacherOptions' => [],
            'canFilterByTeacher' => false,
            'filters' => [
                'teacher_id' => '',
            ],
        ]);
    }

    private function normalizeTime(string $time): string
    {
        $time = trim($time);
        if (preg_match('/^\d{1,2}:\d{2}$/', $time)) {
            return $time.':00';
        }

        return $time;
    }
}
