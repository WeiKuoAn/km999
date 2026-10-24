<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Reconciliation;
use App\Models\Teacher;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'teacher_id' => ['nullable', 'integer', 'exists:teachers,id'],
        ]);

        $year = (int) ($validated['year'] ?? Carbon::today()->year);
        $teacherId = isset($validated['teacher_id']) ? (int) $validated['teacher_id'] : null;

        $base = Reconciliation::query()
            ->join('students', 'students.id', '=', 'reconciliations.student_id')
            ->leftJoin('grade_levels', 'grade_levels.id', '=', 'students.grade_level_id')
            ->where('reconciliations.status', 'paid')
            ->where('reconciliations.billing_year', $year);

        if ($teacherId !== null) {
            $base->whereHas('classroom', fn ($builder) => $builder->where('teacher_id', $teacherId));
        }

        $monthly = (clone $base)
            ->selectRaw('reconciliations.billing_month, SUM(reconciliations.paid_amount) as revenue')
            ->groupBy('reconciliations.billing_month')
            ->pluck('revenue', 'billing_month');

        $monthRows = [];
        $yearTotal = 0;
        for ($m = 1; $m <= 12; $m++) {
            $revenue = (int) ($monthly[$m] ?? 0);
            $monthRows[] = [
                'month' => $m,
                'revenue' => $revenue,
            ];
            $yearTotal += $revenue;
        }

        $pieByGrade = (clone $base)
            ->selectRaw("
                COALESCE(grade_levels.name, '未分年級') as label,
                COALESCE(MIN(grade_levels.sort_order), 9999) as sort_order,
                SUM(reconciliations.paid_amount) as value
            ")
            ->groupBy('grade_levels.name')
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get()
            ->map(fn ($row): array => [
                'label' => (string) $row->label,
                'value' => (int) $row->value,
            ])
            ->values()
            ->all();

        $pieBySubject = (clone $base)
            ->leftJoin('courses', 'courses.id', '=', 'reconciliations.course_id')
            ->leftJoin('course_categories', 'course_categories.id', '=', 'courses.course_category_id')
            ->selectRaw("
                COALESCE(course_categories.name, courses.name, '未分科目') as label,
                COALESCE(MIN(course_categories.sort_order), 9999) as sort_order,
                SUM(reconciliations.paid_amount) as value
            ")
            ->groupBy(DB::raw('COALESCE(course_categories.name, courses.name, \'未分科目\')'))
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get()
            ->map(fn ($row): array => [
                'label' => (string) $row->label,
                'value' => (int) $row->value,
            ])
            ->values()
            ->all();

        return Inertia::render('Reports/Index', [
            'year' => $year,
            'monthRows' => $monthRows,
            'yearTotal' => $yearTotal,
            'pieByGrade' => $pieByGrade,
            'pieBySubject' => $pieBySubject,
            'teacherOptions' => Teacher::query()->where('status', 'active')->orderBy('name')->get(['id', 'name'])->all(),
            'canFilterByTeacher' => true,
            'filters' => [
                'year' => (string) $year,
                'teacher_id' => $teacherId === null ? '' : (string) $teacherId,
            ],
        ]);
    }

    public function attendanceRate(Request $request): Response
    {
        $today = Carbon::today();
        $validated = $request->validate([
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'month' => ['nullable', 'integer', 'between:1,12'],
            'teacher_id' => ['nullable', 'integer', 'exists:teachers,id'],
        ]);
        $year = (int) ($validated['year'] ?? $today->year);
        $month = (int) ($validated['month'] ?? $today->month);
        $teacherId = isset($validated['teacher_id']) ? (int) $validated['teacher_id'] : null;

        $query = Attendance::query()
            ->from('attendances as a')
            ->join('classrooms as cl', 'cl.id', '=', 'a.classroom_id')
            ->join('courses as c', 'c.id', '=', 'cl.course_id')
            ->join('course_categories as cc', 'cc.id', '=', 'c.course_category_id')
            ->leftJoin('teachers as t', 't.id', '=', 'cl.teacher_id')
            ->whereYear('a.class_date', $year)
            ->whereMonth('a.class_date', $month);

        if ($teacherId !== null) {
            $query->where('cl.teacher_id', $teacherId);
        }

        $rows = $query
            ->selectRaw('
                cl.id as classroom_id,
                cl.name as classroom_name,
                c.id as course_id,
                c.name as course_name,
                cc.name as category_name,
                t.name as teacher_name,
                COUNT(*) as total_count,
                SUM(CASE WHEN a.status IN ("present","late","makeup") THEN 1 ELSE 0 END) as attended_count,
                COUNT(DISTINCT a.student_id) as student_count
            ')
            ->groupBy('cl.id', 'cl.name', 'c.id', 'c.name', 'cc.name', 't.name')
            ->orderBy('t.name')
            ->orderBy('cl.name')
            ->get()
            ->map(fn ($r) => [
                'classroom_id' => (int) $r->classroom_id,
                'classroom_name' => $r->classroom_name,
                'course_id' => (int) $r->course_id,
                'course_name' => $r->course_name,
                'category_name' => $r->category_name,
                'teacher_name' => $r->teacher_name,
                'student_count' => (int) $r->student_count,
                'total_count' => (int) $r->total_count,
                'attended_count' => (int) $r->attended_count,
                'attendance_rate' => (int) $r->total_count > 0
                    ? round(((int) $r->attended_count / (int) $r->total_count) * 100, 1)
                    : null,
            ])
            ->values();

        return Inertia::render('Reports/AttendanceRate', [
            'filters' => [
                'year' => (string) $year,
                'month' => (string) $month,
                'teacher_id' => $teacherId === null ? '' : (string) $teacherId,
            ],
            'teacherOptions' => Teacher::query()->where('status', 'active')->orderBy('name')->get(['id', 'name'])->all(),
            'canFilterByTeacher' => true,
            'rows' => $rows,
        ]);
    }
}
