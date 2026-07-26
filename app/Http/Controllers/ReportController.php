<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Reconciliation;
use App\Models\Teacher;
use Carbon\Carbon;
use Illuminate\Http\Request;
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

        $query = Reconciliation::query()
            ->where('billing_year', $year)
            ->where('status', 'paid');

        if ($teacherId !== null) {
            $query->whereHas('classroom', fn ($q) => $q->where('teacher_id', $teacherId));
        }

        $monthly = $query
            ->selectRaw('billing_month, SUM(paid_amount) as revenue')
            ->groupBy('billing_month')
            ->pluck('revenue', 'billing_month');

        $rows = [];
        $yearTotal = 0;
        for ($m = 1; $m <= 12; $m++) {
            $revenue = (int) ($monthly[$m] ?? 0);
            $rows[] = [
                'month' => $m,
                'revenue' => $revenue,
            ];
            $yearTotal += $revenue;
        }

        return Inertia::render('Reports/Index', [
            'year' => $year,
            'rows' => $rows,
            'yearTotal' => $yearTotal,
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
