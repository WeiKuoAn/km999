<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\GradeLevel;
use App\Models\ScheduleException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ScheduleExceptionController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::in([ScheduleException::TYPE_CLOSURE, ScheduleException::TYPE_MAKEUP])],
            'date_from' => ['required', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'name' => ['required', 'string', 'max:255'],
            'grade_level_id' => ['nullable', 'integer', 'exists:grade_levels,id'],
            'course_ids' => ['nullable', 'array'],
            'course_ids.*' => ['integer', 'exists:courses,id'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'all_courses' => ['nullable', 'boolean'],
        ]);

        $dateFrom = $validated['date_from'];
        $dateTo = $validated['date_to'] ?? $dateFrom;
        $type = $validated['type'];
        $allCourses = (bool) ($validated['all_courses'] ?? false);
        $courseIds = array_values(array_unique(array_map('intval', $validated['course_ids'] ?? [])));

        if (
            $type === ScheduleException::TYPE_MAKEUP
            && ! empty($validated['start_time'])
            && ! empty($validated['end_time'])
            && $validated['end_time'] <= $validated['start_time']
        ) {
            throw ValidationException::withMessages([
                'end_time' => '結束時間需晚於開始時間。',
            ]);
        }

        if (! $allCourses && $courseIds === [] && ! empty($validated['grade_level_id'])) {
            throw ValidationException::withMessages([
                'course_ids' => '請選擇課程，或勾選「該年級全部課程」。',
            ]);
        }

        if ($allCourses) {
            $courseIds = [];
        }

        if ($type === ScheduleException::TYPE_CLOSURE && empty($validated['grade_level_id']) && $courseIds === []) {
            // 全校全科停課 OK（颱風）
        }

        $exception = ScheduleException::query()->create([
            'type' => $type,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'name' => $validated['name'],
            'grade_level_id' => $validated['grade_level_id'] ?? null,
            'start_time' => $type === ScheduleException::TYPE_MAKEUP ? ($validated['start_time'] ?? null) : null,
            'end_time' => $type === ScheduleException::TYPE_MAKEUP ? ($validated['end_time'] ?? null) : null,
        ]);

        if ($courseIds !== []) {
            $exception->courses()->sync($courseIds);
        }

        $label = $type === ScheduleException::TYPE_MAKEUP ? '補課' : '停課';

        return back()->with('success', "已新增{$label}設定。");
    }

    public function destroy(ScheduleException $scheduleException): RedirectResponse
    {
        $label = $scheduleException->type === ScheduleException::TYPE_MAKEUP ? '補課' : '停課';
        $scheduleException->delete();

        return back()->with('success', "已刪除{$label}設定。");
    }

    /**
     * @return list<array{id:int,name:string,category:string|null}>
     */
    public static function courseOptions(): array
    {
        return Course::query()
            ->with('courseCategory:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'course_category_id'])
            ->map(fn (Course $c): array => [
                'id' => $c->id,
                'name' => $c->name,
                'category' => $c->courseCategory?->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id:int,name:string,code:int|null}>
     */
    public static function gradeOptions(): array
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
            ->values()
            ->all();
    }
}
