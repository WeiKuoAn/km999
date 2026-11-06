<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateFeeStandardPdfJob;
use App\Models\AcademicYear;
use App\Models\FeeStandardTemplate;
use App\Models\FeeStandardTemplateItem;
use App\Models\GradeLevel;
use App\Models\User;
use App\Support\FeeStandardExport;
use App\Support\FeeStandardPdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FeeStandardTemplateController extends Controller
{
    public function index(Request $request): Response
    {
        $gradeFilter = $request->string('grade')->toString();

        $query = FeeStandardTemplate::query()
            ->with(['gradeLevel:id,name,code', 'academicYear:id,year_code,name'])
            ->withCount('items')
            ->orderBy('sort_order')
            ->orderBy('id');

        if ($gradeFilter !== '' && $gradeFilter !== '全部') {
            $query->whereHas('gradeLevel', fn ($q) => $q->where('name', $gradeFilter));
        }

        /** @var User $user */
        $user = $request->user();
        $canManage = $user->hasRole(User::ROLE_SUPER_ADMIN, User::ROLE_ADMIN);

        return Inertia::render('FeeStandardTemplates/Index', [
            'templates' => $query
                ->paginate(50)
                ->through(fn (FeeStandardTemplate $template): array => $this->listPayload($template))
                ->withQueryString(),
            'filters' => [
                'grade' => $gradeFilter !== '' ? $gradeFilter : '全部',
            ],
            'gradeOptions' => $this->gradeFilterOptions(),
            'canManage' => $canManage,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('FeeStandardTemplates/Create', [
            'academicYears' => $this->academicYearOptions(),
            'gradeLevels' => $this->gradeLevelOptions(),
            'defaultAcademicYearId' => AcademicYear::query()->where('is_current', true)->value('id'),
            'defaultFooter' => FeeStandardTemplate::DEFAULT_FOOTER,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateTemplate($request);

        DB::transaction(function () use ($validated): void {
            $items = $validated['items'];
            unset($validated['items']);
            $template = FeeStandardTemplate::query()->create($validated);
            $this->syncItems($template, $items);
        });

        return to_route('fee-standard-templates.index')
            ->with('success', '已新增收費標準模板。');
    }

    public function edit(FeeStandardTemplate $feeStandardTemplate): Response
    {
        $feeStandardTemplate->load(['items', 'gradeLevel:id,name', 'academicYear:id,year_code,name']);

        return Inertia::render('FeeStandardTemplates/Edit', [
            'template' => $this->formPayload($feeStandardTemplate),
            'academicYears' => $this->academicYearOptions(),
            'gradeLevels' => $this->gradeLevelOptions(),
            'defaultFooter' => FeeStandardTemplate::DEFAULT_FOOTER,
        ]);
    }

    public function update(Request $request, FeeStandardTemplate $feeStandardTemplate): RedirectResponse
    {
        $validated = $this->validateTemplate($request);

        DB::transaction(function () use ($feeStandardTemplate, $validated): void {
            $items = $validated['items'];
            unset($validated['items']);
            $feeStandardTemplate->update($validated);
            $this->syncItems($feeStandardTemplate, $items);
        });

        return to_route('fee-standard-templates.index')
            ->with('success', '已更新收費標準模板。');
    }

    public function destroy(FeeStandardTemplate $feeStandardTemplate): RedirectResponse
    {
        $feeStandardTemplate->delete();

        return to_route('fee-standard-templates.index')
            ->with('success', '已刪除收費標準模板。');
    }

    public function duplicate(FeeStandardTemplate $feeStandardTemplate): RedirectResponse
    {
        $feeStandardTemplate->load('items');

        $copy = DB::transaction(function () use ($feeStandardTemplate): FeeStandardTemplate {
            $clone = $feeStandardTemplate->replicate(['created_at', 'updated_at']);
            $clone->title = $feeStandardTemplate->title.'（複製）';
            $clone->is_active = false;
            $clone->save();

            foreach ($feeStandardTemplate->items as $item) {
                $itemClone = $item->replicate(['created_at', 'updated_at']);
                $itemClone->fee_standard_template_id = $clone->id;
                $itemClone->save();
            }

            return $clone;
        });

        return to_route('fee-standard-templates.edit', $copy)
            ->with('success', '已複製模板，請確認後啟用。');
    }

    /** 模板適用年級的學生清單（供前端勾選匯出；預設隱藏已匯出） */
    public function students(Request $request, FeeStandardTemplate $feeStandardTemplate): JsonResponse
    {
        $includeExported = $request->boolean('include_exported');

        /** @var User $user */
        $user = $request->user();
        $students = FeeStandardExport::studentsForTemplate(
            $feeStandardTemplate,
            $user,
            null,
            $includeExported,
        )
            ->map(fn ($s): array => [
                'id' => (int) $s->id,
                'name' => (string) $s->name,
                'student_code' => (string) ($s->student_code ?? ''),
            ])
            ->values()
            ->all();

        return response()->json([
            'students' => $students,
            'exported_count' => FeeStandardExport::exportedCount($feeStandardTemplate),
            'include_exported' => $includeExported,
        ]);
    }

    /**
     * 同步匯出（人數 ≤ 門檻）或啟動背景匯出。
     * scope=grade｜students；students 時需 student_ids。
     */
    public function pdf(Request $request, FeeStandardTemplate $feeStandardTemplate): HttpResponse|JsonResponse
    {
        $validated = $request->validate([
            'scope' => ['required', 'in:grade,students'],
            'student_ids' => ['nullable', 'array'],
            'student_ids.*' => ['integer', 'exists:students,id'],
            'async' => ['nullable', 'boolean'],
            'include_exported' => ['nullable', 'boolean'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $studentIds = $validated['scope'] === 'students'
            ? array_values(array_map('intval', $validated['student_ids'] ?? []))
            : null;
        // 指定學生＝明確勾選，允許再匯出已匯出者；年級全部＝略過已匯出
        $includeExported = $validated['scope'] === 'students'
            || (bool) ($validated['include_exported'] ?? false);

        if ($validated['scope'] === 'students' && ($studentIds === null || $studentIds === [])) {
            throw ValidationException::withMessages([
                'student_ids' => '請至少選擇一位學生。',
            ]);
        }

        $feeStandardTemplate->load('items');
        $pages = FeeStandardExport::buildPages(
            $feeStandardTemplate,
            $user,
            $studentIds,
            $includeExported,
        );
        if ($pages === []) {
            abort(404, '目前沒有可匯出的學生（可能都已匯出過）。');
        }

        $forceAsync = (bool) ($validated['async'] ?? false);
        $useAsync = $forceAsync || count($pages) > FeeStandardExport::ASYNC_THRESHOLD;
        $exportedStudentIds = array_values(array_map(
            fn (array $page): int => (int) $page['student_id'],
            $pages,
        ));

        if ($useAsync && $request->expectsJson()) {
            $exportId = (string) Str::uuid();
            Cache::put(FeeStandardExport::cacheKey($exportId), [
                'status' => 'queued',
                'user_id' => $user->id,
                'message' => '已排入背景產生，請稍候…',
            ], now()->addHours(2));

            GenerateFeeStandardPdfJob::dispatch(
                $exportId,
                $user->id,
                $feeStandardTemplate->id,
                $studentIds,
                $includeExported,
            )->afterResponse();

            return response()->json([
                'id' => $exportId,
                'status' => 'queued',
                'message' => '已在背景產生 PDF，完成後可下載。',
            ]);
        }

        if (function_exists('set_time_limit')) {
            @set_time_limit(180);
        }
        @ini_set('memory_limit', '512M');

        $pdf = FeeStandardPdf::render($pages);
        FeeStandardExport::markExported($feeStandardTemplate, $exportedStudentIds, (int) $user->id);
        $filename = $feeStandardTemplate->title.'_收費標準.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.rawurlencode($filename).'"; filename*=UTF-8\'\''.rawurlencode($filename),
        ]);
    }

    public function exportStatus(Request $request, string $export): JsonResponse
    {
        $payload = Cache::get(FeeStandardExport::cacheKey($export));
        if (! is_array($payload) || (int) ($payload['user_id'] ?? 0) !== (int) $request->user()->id) {
            return response()->json(['status' => 'missing', 'message' => '找不到匯出任務。'], 404);
        }

        return response()->json([
            'id' => $export,
            'status' => $payload['status'] ?? 'unknown',
            'message' => $payload['message'] ?? null,
        ]);
    }

    public function downloadExport(Request $request, string $export): StreamedResponse
    {
        $payload = Cache::get(FeeStandardExport::cacheKey($export));
        if (! is_array($payload)
            || (int) ($payload['user_id'] ?? 0) !== (int) $request->user()->id
            || ($payload['status'] ?? '') !== 'ready'
            || empty($payload['path'])
        ) {
            abort(404, '檔案尚未就緒或不存在。');
        }

        $path = (string) $payload['path'];
        if (! Storage::disk('local')->exists($path)) {
            abort(404, '檔案不存在。');
        }

        $filename = (string) ($payload['filename'] ?? '收費標準.pdf');

        return Storage::disk('local')->download($path, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateTemplate(Request $request): array
    {
        $validated = $request->validate([
            'academic_year_id' => ['nullable', 'integer', 'exists:academic_years,id'],
            'grade_level_id' => ['required', 'integer', 'exists:grade_levels,id'],
            'title' => ['required', 'string', 'max:128'],
            'deadline_label' => ['nullable', 'string', 'max:64'],
            'footer_note' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.kind' => ['nullable', 'in:check,table'],
            'items.*.label' => ['required', 'string', 'max:255'],
            'items.*.heading_color' => ['nullable', 'string', 'max:32'],
            'items.*.detail' => ['nullable', 'string', 'max:2000'],
            'items.*.table_headers' => ['nullable', 'array'],
            'items.*.table_headers.*' => ['nullable', 'string', 'max:128'],
            'items.*.table_rows' => ['nullable', 'array'],
            'items.*.table_rows.*' => ['nullable', 'array'],
            'items.*.table_rows.*.*' => ['nullable', 'string', 'max:500'],
            'items.*.sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ], [
            'items.required' => '請至少新增一個項目或表格。',
            'items.min' => '請至少新增一個項目或表格。',
        ]);

        $validated['academic_year_id'] = isset($validated['academic_year_id'])
            ? (int) $validated['academic_year_id']
            : null;
        $validated['grade_level_id'] = (int) $validated['grade_level_id'];
        $validated['deadline_label'] = isset($validated['deadline_label']) && trim((string) $validated['deadline_label']) !== ''
            ? trim((string) $validated['deadline_label'])
            : null;
        $validated['footer_note'] = isset($validated['footer_note']) && trim((string) $validated['footer_note']) !== ''
            ? trim((string) $validated['footer_note'])
            : null;
        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['sort_order'] = (int) ($validated['sort_order'] ?? 0);

        $itemErrors = [];
        $normalizedItems = [];
        foreach (array_values($validated['items']) as $i => $item) {
            $kind = ($item['kind'] ?? FeeStandardTemplateItem::KIND_CHECK) === FeeStandardTemplateItem::KIND_TABLE
                ? FeeStandardTemplateItem::KIND_TABLE
                : FeeStandardTemplateItem::KIND_CHECK;
            $label = trim((string) $item['label']);
            $headingColor = isset($item['heading_color']) && trim((string) $item['heading_color']) !== ''
                ? trim((string) $item['heading_color'])
                : null;

            if ($kind === FeeStandardTemplateItem::KIND_TABLE) {
                $headers = array_values(array_map(
                    fn ($h): string => trim((string) $h),
                    is_array($item['table_headers'] ?? null) ? $item['table_headers'] : [],
                ));
                if (count($headers) < 2) {
                    $itemErrors["items.{$i}.table_headers"] = '表格至少需要 2 欄。';
                }
                if (count($headers) > 6) {
                    $itemErrors["items.{$i}.table_headers"] = '表格最多 6 欄。';
                }
                foreach ($headers as $hi => $header) {
                    if ($header === '') {
                        $itemErrors["items.{$i}.table_headers.{$hi}"] = '請填寫欄位標題。';
                    }
                }
                $rows = [];
                foreach (is_array($item['table_rows'] ?? null) ? $item['table_rows'] : [] as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $cells = array_values(array_map(
                        fn ($cell): string => trim((string) $cell),
                        $row,
                    ));
                    while (count($cells) < count($headers)) {
                        $cells[] = '';
                    }
                    $cells = array_slice($cells, 0, max(2, count($headers)));
                    if (collect($cells)->every(fn (string $c): bool => $c === '')) {
                        continue;
                    }
                    $rows[] = $cells;
                }
                if ($rows === []) {
                    $itemErrors["items.{$i}.table_rows"] = '請至少填一列表格內容。';
                }

                $normalizedItems[] = [
                    'kind' => FeeStandardTemplateItem::KIND_TABLE,
                    'label' => $label,
                    'heading_color' => $headingColor,
                    'detail' => null,
                    'table_headers' => $headers,
                    'table_rows' => $rows,
                    'sort_order' => (int) ($item['sort_order'] ?? $i),
                ];
            } else {
                $normalizedItems[] = [
                    'kind' => FeeStandardTemplateItem::KIND_CHECK,
                    'label' => $label,
                    'heading_color' => null,
                    'detail' => isset($item['detail']) && trim((string) $item['detail']) !== ''
                        ? trim((string) $item['detail'])
                        : null,
                    'table_headers' => null,
                    'table_rows' => null,
                    'sort_order' => (int) ($item['sort_order'] ?? $i),
                ];
            }
        }
        if ($itemErrors !== []) {
            throw ValidationException::withMessages($itemErrors);
        }
        $validated['items'] = $normalizedItems;

        return $validated;
    }

    /**
     * @param  list<array{
     *   kind:string,
     *   label:string,
     *   heading_color:?string,
     *   detail:?string,
     *   table_headers:?list<string>,
     *   table_rows:?list<list<string>>,
     *   sort_order:int
     * }>  $items
     */
    private function syncItems(FeeStandardTemplate $template, array $items): void
    {
        $template->items()->delete();
        foreach (array_values($items) as $i => $item) {
            $template->items()->create([
                'kind' => $item['kind'] ?? FeeStandardTemplateItem::KIND_CHECK,
                'label' => $item['label'],
                'heading_color' => $item['heading_color'] ?? null,
                'detail' => $item['detail'] ?? null,
                'table_headers' => $item['table_headers'] ?? null,
                'table_rows' => $item['table_rows'] ?? null,
                'sort_order' => $item['sort_order'] ?? $i,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function listPayload(FeeStandardTemplate $template): array
    {
        return [
            'id' => $template->id,
            'title' => $template->title,
            'grade_name' => $template->gradeLevel?->name,
            'academic_year_name' => $template->academicYear?->displayName(),
            'deadline_label' => $template->deadline_label,
            'items_count' => (int) ($template->items_count ?? $template->items()->count()),
            'is_active' => (bool) $template->is_active,
            'sort_order' => (int) $template->sort_order,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formPayload(FeeStandardTemplate $template): array
    {
        return [
            'id' => $template->id,
            'academic_year_id' => $template->academic_year_id,
            'grade_level_id' => $template->grade_level_id,
            'title' => $template->title,
            'deadline_label' => $template->deadline_label ?? '',
            'footer_note' => $template->footer_note ?? '',
            'is_active' => (bool) $template->is_active,
            'sort_order' => (int) $template->sort_order,
            'items' => $template->items->map(fn (FeeStandardTemplateItem $item): array => [
                'kind' => $item->isTable()
                    ? FeeStandardTemplateItem::KIND_TABLE
                    : FeeStandardTemplateItem::KIND_CHECK,
                'label' => $item->label,
                'heading_color' => $item->heading_color ?? '',
                'detail' => $item->detail ?? '',
                'table_headers' => is_array($item->table_headers) ? $item->table_headers : [],
                'table_rows' => is_array($item->table_rows) ? $item->table_rows : [],
                'sort_order' => (int) $item->sort_order,
            ])->values()->all(),
        ];
    }

    /**
     * @return list<string>
     */
    private function gradeFilterOptions(): array
    {
        return array_merge(
            ['全部'],
            GradeLevel::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('code')
                ->pluck('name')
                ->all()
        );
    }

    /**
     * @return list<array{id:int,year_code:string,name:string}>
     */
    private function academicYearOptions(): array
    {
        return AcademicYear::query()
            ->orderByDesc('year_code')
            ->get(['id', 'year_code', 'name'])
            ->map(fn (AcademicYear $y): array => [
                'id' => $y->id,
                'year_code' => $y->year_code,
                'name' => $y->displayName(),
            ])
            ->all();
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
