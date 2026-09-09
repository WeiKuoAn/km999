<?php

namespace App\Support;

use App\Models\Course;
use App\Models\GradeLevel;
use App\Models\Student;
use App\Models\StudentCourseDrop;
use Illuminate\Support\Facades\Schema;

/**
 * 轉檔時科目對應：同一 course_id 在新年級仍有價目／課表 → 可自動；否則需手動選課。
 */
final class PromotionCourses
{
    /**
     * @return array{
     *   mode:'auto'|'manual',
     *   current_course_ids:list<int>,
     *   current_labels:list<string>,
     *   transferable_ids:list<int>,
     *   transferable_labels:list<string>,
     *   blocked_ids:list<int>,
     *   blocked_labels:list<string>,
     *   target_subjects:list<array{id:int,name:string,category:string|null}>,
     *   note:string|null
     * }
     */
    public static function analyze(Student $student, GradeLevel $toGrade): array
    {
        $currentIds = self::currentCourseIds($student);
        $targetSubjects = self::subjectsForTargetGrade($student, $toGrade);
        $targetIdSet = array_fill_keys(
            array_map(fn (array $s): int => (int) $s['id'], $targetSubjects),
            true
        );

        $transferable = [];
        $blocked = [];
        foreach ($currentIds as $id) {
            if (isset($targetIdSet[$id])) {
                $transferable[] = $id;
            } else {
                $blocked[] = $id;
            }
        }

        $mode = $blocked === [] ? 'auto' : 'manual';
        $labels = self::courseLabels(array_values(array_unique([...$currentIds, ...$transferable, ...$blocked])));

        $note = null;
        if ($mode === 'auto') {
            $note = $transferable === []
                ? '無進行中科目，僅更新年級與學號'
                : '科目不變，轉檔後自動改用'.$toGrade->name.'課表／價目：'.implode('、', array_map(fn (int $id) => $labels[$id] ?? (string) $id, $transferable));
        } else {
            $note = '有科目無法對應'.$toGrade->name.'（'.implode('、', array_map(fn (int $id) => $labels[$id] ?? (string) $id, $blocked)).'），請手動選擇'.$toGrade->name.'要修的課';
        }

        return [
            'mode' => $mode,
            'current_course_ids' => $currentIds,
            'current_labels' => array_values(array_map(fn (int $id) => $labels[$id] ?? (string) $id, $currentIds)),
            'transferable_ids' => $transferable,
            'transferable_labels' => array_values(array_map(fn (int $id) => $labels[$id] ?? (string) $id, $transferable)),
            'blocked_ids' => $blocked,
            'blocked_labels' => array_values(array_map(fn (int $id) => $labels[$id] ?? (string) $id, $blocked)),
            'target_subjects' => $targetSubjects,
            'note' => $note,
        ];
    }

    /**
     * 轉檔後寫入預選科目，並把未選的舊科標為停修（不刪歷史收款）。
     *
     * @param  list<int>  $courseIds
     * @param  list<int>  $previousCourseIds  轉檔前進行中科目
     */
    public static function applyAfterPromote(Student $student, array $courseIds, array $previousCourseIds = []): void
    {
        $courseIds = array_values(array_unique(array_map('intval', $courseIds)));
        $previousCourseIds = array_values(array_unique(array_map('intval', $previousCourseIds)));
        $student->update(['intended_course_ids' => $courseIds === [] ? null : $courseIds]);

        if (! Schema::hasTable('student_course_drops')) {
            return;
        }

        foreach ($previousCourseIds as $courseId) {
            if (in_array($courseId, $courseIds, true)) {
                StudentCourseDrop::query()
                    ->where('student_id', $student->id)
                    ->where('course_id', $courseId)
                    ->delete();
            } else {
                StudentCourseDrop::query()->updateOrCreate(
                    [
                        'student_id' => $student->id,
                        'course_id' => $courseId,
                    ],
                    []
                );
            }
        }

        foreach ($courseIds as $courseId) {
            StudentCourseDrop::query()
                ->where('student_id', $student->id)
                ->where('course_id', $courseId)
                ->delete();
        }
    }

    /**
     * @return list<int>
     */
    public static function intendedOrSnapshotCourseIds(Student $student): array
    {
        $intended = $student->intended_course_ids;
        if (is_array($intended) && $intended !== []) {
            return array_values(array_unique(array_map('intval', $intended)));
        }

        $snapshot = BillingRenewal::lastPaidRenewalSnapshot($student);

        return $snapshot['course_ids'] ?? [];
    }

    /**
     * @return list<int>
     */
    private static function currentCourseIds(Student $student): array
    {
        return self::currentCourseIdsIgnoringIntended($student);
    }

    /**
     * 轉檔前的進行中科目：最近一輪已繳（已排除停修）。
     *
     * @return list<int>
     */
    private static function currentCourseIdsIgnoringIntended(Student $student): array
    {
        $snapshot = BillingRenewal::lastPaidRenewalSnapshot($student);

        return $snapshot['course_ids'] ?? [];
    }

    /**
     * @return list<array{id:int,name:string,category:string|null}>
     */
    private static function subjectsForTargetGrade(Student $student, GradeLevel $toGrade): array
    {
        $student->loadMissing('academicYear');
        $attrs = $student->getAttributes();
        $attrs['grade_level_id'] = $toGrade->id;
        $proxy = (new Student)->newFromBuilder($attrs);
        $proxy->setRelation('gradeLevel', $toGrade);
        $proxy->setRelation('academicYear', $student->academicYear);

        return collect(EnrollmentPricing::subjectsForStudent($proxy))
            ->filter(fn (array $s): bool => ! empty($s['fee_plan_id']))
            ->map(fn (array $s): array => [
                'id' => (int) $s['id'],
                'name' => (string) $s['name'],
                'category' => $s['category'] ?? null,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $courseIds
     * @return array<int, string>
     */
    private static function courseLabels(array $courseIds): array
    {
        if ($courseIds === []) {
            return [];
        }

        return Course::query()
            ->with('courseCategory:id,name')
            ->whereIn('id', $courseIds)
            ->get(['id', 'name', 'course_category_id'])
            ->mapWithKeys(fn (Course $c) => [
                (int) $c->id => (string) ($c->courseCategory?->name ?: $c->name),
            ])
            ->all();
    }
}
