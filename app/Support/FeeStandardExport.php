<?php

namespace App\Support;

use App\Models\FeeStandardTemplate;
use App\Models\FeeStandardTemplateExport;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * 依模板＋學生組出收費標準勾選單頁面資料（含教師可見範圍）。
 */
final class FeeStandardExport
{
    public const ASYNC_THRESHOLD = 80;

    /**
     * @param  list<int>|null  $studentIds  null＝該年級尚未匯出（或含已匯出）的可見學生
     * @return list<array{
     *   student_id:int,
     *   student_name:string,
     *   student_code:string,
     *   title:string,
     *   deadline_label:?string,
     *   footer_note:string,
     *   items:list<array{label:string, detail:?string}>
     * }>
     */
    public static function buildPages(
        FeeStandardTemplate $template,
        User $user,
        ?array $studentIds = null,
        bool $includeExported = false,
    ): array {
        $template->loadMissing(['items']);
        $students = self::studentsForTemplate($template, $user, $studentIds, $includeExported);
        if ($students->isEmpty()) {
            return [];
        }

        $items = $template->items
            ->map(fn ($item): array => [
                'label' => (string) $item->label,
                'detail' => $item->detail !== null && trim((string) $item->detail) !== ''
                    ? (string) $item->detail
                    : null,
            ])
            ->values()
            ->all();

        $title = (string) $template->title;
        $deadline = $template->deadline_label !== null && trim((string) $template->deadline_label) !== ''
            ? (string) $template->deadline_label
            : null;
        $footer = $template->footerText();

        return $students
            ->map(fn (Student $student): array => [
                'student_id' => (int) $student->id,
                'student_name' => (string) $student->name,
                'student_code' => trim((string) ($student->student_code ?? '')),
                'title' => $title,
                'deadline_label' => $deadline,
                'footer_note' => $footer,
                'items' => $items,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<int>|null  $studentIds
     * @return Collection<int, Student>
     */
    public static function studentsForTemplate(
        FeeStandardTemplate $template,
        User $user,
        ?array $studentIds = null,
        bool $includeExported = false,
    ): Collection {
        $query = Student::query()
            ->where('status', 'active')
            ->where('grade_level_id', $template->grade_level_id)
            ->orderBy('student_code')
            ->orderBy('name');

        if ($template->academic_year_id !== null) {
            $query->where('academic_year_id', $template->academic_year_id);
        }

        if ($studentIds !== null) {
            $ids = array_values(array_unique(array_map('intval', $studentIds)));
            if ($ids === []) {
                return collect();
            }
            $query->whereIn('id', $ids);
        }

        if (! $includeExported) {
            $query->whereNotIn('id', function ($sub) use ($template): void {
                $sub->select('student_id')
                    ->from('fee_standard_template_exports')
                    ->where('fee_standard_template_id', $template->id);
            });
        }

        if ($user->role === User::ROLE_TEACHER) {
            $user->loadMissing('teacher');
            $teacherId = $user->teacher?->id;
            if ($teacherId === null) {
                return collect();
            }
            $query->whereHas(
                'enrollments.classroom',
                fn ($q) => $q->where('teacher_id', $teacherId)
            );
        }

        return $query->get(['id', 'name', 'student_code', 'grade_level_id', 'academic_year_id']);
    }

    /** @param  list<int>  $studentIds */
    public static function markExported(
        FeeStandardTemplate $template,
        array $studentIds,
        ?int $userId = null,
    ): void {
        $ids = array_values(array_unique(array_filter(array_map('intval', $studentIds))));
        if ($ids === []) {
            return;
        }

        $now = Carbon::now();
        foreach ($ids as $studentId) {
            FeeStandardTemplateExport::query()->updateOrCreate(
                [
                    'fee_standard_template_id' => $template->id,
                    'student_id' => $studentId,
                ],
                [
                    'exported_by' => $userId,
                    'exported_at' => $now,
                ],
            );
        }
    }

    public static function exportedCount(FeeStandardTemplate $template): int
    {
        return FeeStandardTemplateExport::query()
            ->where('fee_standard_template_id', $template->id)
            ->count();
    }

    public static function cacheKey(string $exportId): string
    {
        return 'fee_standard_pdf:'.$exportId;
    }

    public static function relativePath(string $exportId): string
    {
        return 'exports/fee-standards/'.$exportId.'.pdf';
    }
}
