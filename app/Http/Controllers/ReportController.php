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
            'q' => ['nullable', 'string', 'max:255'],
            'teacher_id' => ['nullable', 'integer', 'exists:teachers,id'],
        ]);

        $year = (int) ($validated['year'] ?? Carbon::today()->year);
        $q = trim((string) ($validated['q'] ?? ''));
        $teacherId = isset($validated['teacher_id']) ? (int) $validated['teacher_id'] : null;

        $batchDateSql = 'COALESCE(DATE(reconciliations.paid_date), DATE(reconciliations.created_at))';
        $payCycleSql = "COALESCE(reconciliations.pay_cycle, 'quarterly')";

        $base = Reconciliation::query()
            ->join('students', 'students.id', '=', 'reconciliations.student_id')
            ->leftJoin('grade_levels', 'grade_levels.id', '=', 'students.grade_level_id')
            ->leftJoin('users as settled_users', 'settled_users.id', '=', 'reconciliations.settled_by_user_id')
            ->where('reconciliations.status', 'paid')
            ->where('reconciliations.billing_year', $year);

        if ($teacherId !== null) {
            $base->whereHas('classroom', fn ($builder) => $builder->where('teacher_id', $teacherId));
        }

        if ($q !== '') {
            $base->where(function ($builder) use ($q): void {
                $builder->where('students.name', 'like', '%'.$q.'%')
                    ->orWhere('students.student_code', 'like', '%'.$q.'%');
            });
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

        $batches = (clone $base)
            ->selectRaw("
                MAX(reconciliations.id) as id,
                reconciliations.student_id,
                students.student_code,
                students.name as student_name,
                grade_levels.name as grade_name,
                {$payCycleSql} as pay_cycle,
                {$batchDateSql} as batch_date,
                MIN(reconciliations.billing_year * 12 + reconciliations.billing_month) as start_key,
                MAX(reconciliations.billing_year * 12 + reconciliations.billing_month) as end_key,
                SUM(reconciliations.expected_amount) as expected_total,
                SUM(reconciliations.paid_amount) as paid_total,
                COUNT(DISTINCT reconciliations.course_id) as course_count,
                MAX(reconciliations.paid_date) as paid_date,
                MAX(settled_users.name) as settled_by_name
            ")
            ->groupBy([
                'reconciliations.student_id',
                'students.student_code',
                'students.name',
                'grade_levels.name',
                DB::raw($payCycleSql),
                DB::raw($batchDateSql),
            ])
            ->orderByDesc('end_key')
            ->orderByRaw('MAX(reconciliations.id) DESC')
            ->paginate(50)
            ->withQueryString()
            ->through(function (Reconciliation $row): array {
                $startKey = (int) $row->start_key;
                $endKey = (int) $row->end_key;
                $startYear = intdiv($startKey - 1, 12);
                $startMonth = (($startKey - 1) % 12) + 1;
                $endYear = intdiv($endKey - 1, 12);
                $endMonth = (($endKey - 1) % 12) + 1;

                $paidDate = $row->paid_date;
                if ($paidDate instanceof Carbon) {
                    $paidDateStr = $paidDate->toDateString();
                } elseif (is_string($paidDate) && $paidDate !== '') {
                    $paidDateStr = substr($paidDate, 0, 10);
                } else {
                    $paidDateStr = null;
                }

                return [
                    'id' => (int) $row->id,
                    'student_id' => (int) $row->student_id,
                    'student_code' => $row->student_code,
                    'student_name' => $row->student_name ?? '—',
                    'grade_name' => $row->grade_name,
                    'start_year' => $startYear,
                    'start_month' => $startMonth,
                    'end_year' => $endYear,
                    'end_month' => $endMonth,
                    'period_label' => $startKey === $endKey
                        ? sprintf('%d/%d', $startYear, $startMonth)
                        : sprintf('%d/%d — %d/%d', $startYear, $startMonth, $endYear, $endMonth),
                    'expected_total' => (int) $row->expected_total,
                    'paid_total' => (int) $row->paid_total,
                    'course_count' => (int) $row->course_count,
                    'paid_date' => $paidDateStr,
                    'settled_by_name' => $row->settled_by_name ?? '—',
                    'pay_cycle' => $row->pay_cycle,
                ];
            });

        return Inertia::render('Reports/Index', [
            'year' => $year,
            'monthRows' => $monthRows,
            'yearTotal' => $yearTotal,
            'batches' => $batches,
            'teacherOptions' => Teacher::query()->where('status', 'active')->orderBy('name')->get(['id', 'name'])->all(),
            'canFilterByTeacher' => true,
            'filters' => [
                'year' => (string) $year,
                'q' => $q,
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
