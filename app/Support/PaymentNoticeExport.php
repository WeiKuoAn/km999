<?php

namespace App\Support;

use App\Models\Student;
use App\Models\User;

/**
 * 組出繳費條 PDF 用的 notice 清單（含教師可見範圍過濾）。
 */
final class PaymentNoticeExport
{
    /**
     * @return list<array{filename:string, notice:array<string, mixed>}>
     */
    public static function buildItems(
        ?string $q,
        ?int $year,
        ?string $periodStart,
        User $user,
        ?int $studentId = null,
    ): array {
        $q = $q !== null ? trim($q) : null;
        if ($q === '') {
            $q = null;
        }

        $rows = PaymentRosterBuilder::build($q, $year, $periodStart);
        $rows = self::filterRowsForUser($rows, $user);

        if ($studentId !== null) {
            $rows = array_values(array_filter(
                $rows,
                fn (array $row): bool => (int) ($row['student_id'] ?? 0) === $studentId
            ));
        }

        $items = [];
        foreach ($rows as $row) {
            $notice = $row['notice'] ?? null;
            if (! is_array($notice)) {
                continue;
            }
            $code = trim((string) ($row['student_code'] ?? ''));
            $name = (string) ($row['student_name'] ?? '');
            $items[] = [
                'filename' => ($code !== '' ? $code.'_' : '').$name.'_'.($notice['plan_label'] ?? '繳費條'),
                'notice' => $notice,
            ];
        }

        return $items;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public static function filterRowsForUser(array $rows, User $user): array
    {
        if ($user->role !== User::ROLE_TEACHER) {
            return $rows;
        }

        $user->loadMissing('teacher');
        $teacherId = $user->teacher?->id;
        if ($teacherId === null) {
            return [];
        }

        $allowedIds = Student::query()
            ->whereHas('enrollments.classroom', fn ($q) => $q->where('teacher_id', $teacherId))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $allowed = array_fill_keys($allowedIds, true);

        return array_values(array_filter(
            $rows,
            fn (array $row): bool => isset($allowed[(int) ($row['student_id'] ?? 0)])
        ));
    }

    public static function cacheKey(string $exportId): string
    {
        return 'payment_notices_pdf:'.$exportId;
    }

    public static function relativePath(string $exportId): string
    {
        return 'exports/payment-notices/'.$exportId.'.pdf';
    }
}
