<?php

namespace App\Support;

use App\Models\Classroom;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class StudentEnrollmentSync
{
    /**
     * @param  array<int, int|string>  $classroomIds
     */
    public static function sync(Student $student, array $classroomIds, ?User $user): void
    {
        $classroomIds = array_values(array_unique(array_map('intval', $classroomIds)));

        if ($user?->role === User::ROLE_TEACHER) {
            self::syncForTeacher($student, $classroomIds, $user);

            return;
        }

        self::syncFull($student, $classroomIds);
    }

    /**
     * @param  array<int, int>  $classroomIds
     */
    private static function syncForTeacher(Student $student, array $classroomIds, User $user): void
    {
        $teacherId = $user->teacher?->id;
        if ($teacherId === null) {
            throw ValidationException::withMessages([
                'classroom_ids' => '你沒有可指派的班級。',
            ]);
        }

        $managedIds = Classroom::query()
            ->where('teacher_id', $teacherId)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $invalid = array_diff($classroomIds, $managedIds);
        if ($invalid !== []) {
            throw ValidationException::withMessages([
                'classroom_ids' => '所選班級無效或你沒有權限指派。',
            ]);
        }

        $classrooms = $classroomIds === []
            ? collect()
            : Classroom::query()
                ->whereIn('id', $classroomIds)
                ->with(['course.coursePrices'])
                ->get()
                ->keyBy('id');

        DB::transaction(function () use ($student, $classroomIds, $classrooms, $managedIds): void {
            $existing = Enrollment::query()
                ->where('student_id', $student->id)
                ->get()
                ->keyBy('classroom_id');

            self::markEnrollmentsLeft(
                Enrollment::query()
                    ->where('student_id', $student->id)
                    ->whereIn('classroom_id', $managedIds)
                    ->whereNotIn('classroom_id', $classroomIds)
            );

            foreach ($classroomIds as $classroomId) {
                self::upsertEnrollment($student, $classroomId, $classrooms->get($classroomId), $existing->get($classroomId));
            }
        });
    }

    /**
     * @param  array<int, int>  $classroomIds
     */
    private static function syncFull(Student $student, array $classroomIds): void
    {
        if ($classroomIds === []) {
            self::markEnrollmentsLeft(Enrollment::query()->where('student_id', $student->id));

            return;
        }

        $classrooms = Classroom::query()
            ->whereIn('id', $classroomIds)
            ->with(['course.coursePrices'])
            ->get()
            ->keyBy('id');

        if ($classrooms->count() !== count($classroomIds)) {
            throw ValidationException::withMessages([
                'classroom_ids' => '所選班級無效。',
            ]);
        }

        DB::transaction(function () use ($student, $classroomIds, $classrooms): void {
            $existing = Enrollment::query()
                ->where('student_id', $student->id)
                ->get()
                ->keyBy('classroom_id');

            self::markEnrollmentsLeft(
                Enrollment::query()
                    ->where('student_id', $student->id)
                    ->whereNotIn('classroom_id', $classroomIds)
            );

            foreach ($classroomIds as $classroomId) {
                self::upsertEnrollment($student, $classroomId, $classrooms->get($classroomId), $existing->get($classroomId));
            }
        });
    }

    private static function upsertEnrollment(
        Student $student,
        int $classroomId,
        ?Classroom $classroom,
        ?Enrollment $prior,
    ): void {
        if ($prior !== null) {
            $prior->update([
                'status' => 'active',
                'left_at' => null,
            ]);

            return;
        }

        if ($classroom === null) {
            return;
        }

        $prices = $classroom->course?->coursePrices ?? collect();
        $tuition = CourseTuition::fromSchoolSegment($prices, $student->school_segment);

        $enrollment = Enrollment::query()->create([
            'classroom_id' => $classroomId,
            'student_id' => $student->id,
            'tuition_amount' => $tuition,
            'status' => 'active',
            'joined_at' => now()->toDateString(),
            'left_at' => null,
        ]);

        EnrollmentTuitionSync::seedFromCoursePrices($enrollment, $prices, $student->school_segment);
    }

    private static function markEnrollmentsLeft($query): void
    {
        $query->where('status', 'active')->update([
            'status' => 'left',
            'left_at' => now()->toDateString(),
        ]);
    }
}
