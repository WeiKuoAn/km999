<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        return Inertia::render('Holidays/Index', [
            'holidays' => $holidays,
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
