<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use App\Models\ScheduleException;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class HolidayController extends Controller
{
    public function index(): Response
    {
        $holidays = Holiday::query()
            ->orderBy('date')
            ->get(['id', 'date', 'name', 'is_custom'])
            ->map(fn (Holiday $holiday): array => [
                'id' => $holiday->id,
                'date' => $holiday->date->toDateString(),
                'name' => $holiday->name,
                'is_custom' => (bool) $holiday->is_custom,
            ])
            ->values()
            ->all();

        $exceptions = [];
        if (Schema::hasTable('schedule_exceptions')) {
            $exceptions = ScheduleException::query()
                ->with([
                    'gradeLevel:id,name',
                    'courses:id,name,course_category_id',
                    'courses.courseCategory:id,name',
                ])
                ->orderByDesc('date_from')
                ->orderBy('type')
                ->get()
                ->map(function (ScheduleException $ex): array {
                    $courseLabels = $ex->courses
                        ->map(fn ($c) => $c->courseCategory?->name ?: $c->name)
                        ->filter()
                        ->values()
                        ->all();

                    return [
                        'id' => $ex->id,
                        'type' => $ex->type,
                        'date_from' => $ex->date_from->toDateString(),
                        'date_to' => $ex->date_to->toDateString(),
                        'name' => $ex->name,
                        'grade_level_id' => $ex->grade_level_id !== null ? (int) $ex->grade_level_id : null,
                        'grade_name' => $ex->gradeLevel?->name,
                        'course_ids' => $ex->courses->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
                        'course_labels' => $courseLabels,
                        'all_courses' => $courseLabels === [],
                        'start_time' => $ex->start_time ? substr((string) $ex->start_time, 0, 5) : null,
                        'end_time' => $ex->end_time ? substr((string) $ex->end_time, 0, 5) : null,
                    ];
                })
                ->values()
                ->all();
        }

        return Inertia::render('Holidays/Index', [
            'holidays' => $holidays,
            'exceptions' => $exceptions,
            'grades' => ScheduleExceptionController::gradeOptions(),
            'courses' => ScheduleExceptionController::courseOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'date' => ['required', 'date', Rule::unique('holidays', 'date')],
            'name' => ['required', 'string', 'max:255'],
        ]);

        Holiday::query()->create([
            'date' => $validated['date'],
            'name' => $validated['name'],
            'is_custom' => true,
        ]);

        return back()->with('success', '已新增假日。');
    }

    public function destroy(Holiday $holiday): RedirectResponse
    {
        $holiday->delete();

        return back()->with('success', '已刪除假日。');
    }

    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $path = $request->file('file')?->getRealPath();
        if ($path === false || $path === null) {
            return back()->withErrors(['file' => '無法讀取上傳檔案。']);
        }

        $handle = fopen($path, 'r');
        if ($handle === false) {
            return back()->withErrors(['file' => '無法開啟上傳檔案。']);
        }

        $imported = 0;
        $skippedWeekend = 0;
        $isHeader = true;

        while (($row = fgetcsv($handle)) !== false) {
            if ($row === [null] || $row === false) {
                continue;
            }

            // 去 BOM（第一欄可能帶 \xEF\xBB\xBF）
            if (isset($row[0]) && is_string($row[0])) {
                $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', $row[0]) ?? $row[0];
            }

            $subject = trim((string) ($row[0] ?? ''));
            $startDate = trim((string) ($row[1] ?? ''));

            if ($isHeader && (strcasecmp($subject, 'Subject') === 0 || $subject === '主旨')) {
                $isHeader = false;
                continue;
            }
            $isHeader = false;

            if ($subject === '' || $startDate === '') {
                continue;
            }

            if ($subject === '例假日') {
                $skippedWeekend++;
                continue;
            }

            try {
                $date = Carbon::parse(str_replace('/', '-', $startDate))->toDateString();
            } catch (\Throwable) {
                continue;
            }

            Holiday::query()->updateOrCreate(
                ['date' => $date],
                [
                    'name' => $subject,
                    'is_custom' => false,
                ]
            );
            $imported++;
        }

        fclose($handle);

        return back()->with(
            'success',
            sprintf('已匯入 %d 筆國定假日／補假（略過例假日 %d 筆）。', $imported, $skippedWeekend)
        );
    }
}
