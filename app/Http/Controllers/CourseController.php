<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\GradeLevel;
use App\Models\User;
use App\Support\PricingGroup;
use App\Support\WeekdayDates;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class CourseController extends Controller
{
    public function index(): Response
    {
        $user = auth()->user();
        $teacherId = $user?->teacher?->id;

        $query = Course::query();
        if ($user?->role === User::ROLE_TEACHER) {
            if ($teacherId === null) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereHas('classrooms', fn ($q) => $q->where('teacher_id', $teacherId));
            }
        }

        return Inertia::render('Courses/Index', [
            'courses' => $query
                ->with(['courseCategory', 'coursePrices'])
                ->join('course_categories', 'courses.course_category_id', '=', 'course_categories.id')
                ->orderBy('course_categories.sort_order')
                ->orderBy('course_categories.name')
                ->orderBy('courses.name')
                ->select('courses.*')
                ->paginate(50),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Courses/Create', [
            'categories' => CourseCategory::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'sort_order']),
            'gradeLevels' => $this->gradeLevelOptions(),
            'pricingGroups' => PricingGroup::options(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $allowedGrades = $this->activeGradeNames();

        if ($request->input('color') === '') {
            $request->merge(['color' => null]);
        }

        $validated = $request->validate([
            'course_category_id' => ['required', 'exists:course_categories,id'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('courses', 'name')->where(
                    fn ($query) => $query->where('course_category_id', $request->integer('course_category_id'))
                ),
            ],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'status' => ['required', 'in:active,paused'],
            'pricing_group' => ['nullable', 'string', Rule::in(PricingGroup::keys())],
            'schedules' => ['nullable', 'array'],
            'schedules.*.level' => ['nullable', 'string', Rule::in($allowedGrades)],
            'schedules.*.weekday' => ['required', 'integer', 'between:1,7'],
            'schedules.*.start_time' => ['nullable', 'date_format:H:i'],
            'schedules.*.end_time' => ['nullable', 'date_format:H:i'],
            'levels' => ['nullable', 'array'],
            'levels.*' => ['string', Rule::in($allowedGrades)],
        ]);

        $priceRows = $this->levelsToPriceRows($validated['levels'] ?? [], $allowedGrades);
        $levels = array_values(array_unique(array_filter(
            $validated['levels'] ?? [],
            fn (string $name): bool => in_array($name, $allowedGrades, true)
        )));
        $schedules = $this->normalizedSchedulesForLevels($validated['schedules'] ?? [], $levels);
        $this->assertScheduleTimesValid($schedules);
        $weekdays = WeekdayDates::weekdaysFromSchedules($schedules);

        DB::transaction(function () use ($validated, $priceRows, $schedules, $weekdays): void {
            $course = Course::query()->create([
                'course_category_id' => $validated['course_category_id'],
                'name' => $validated['name'],
                'color' => $validated['color'] ?? null,
                'status' => $validated['status'],
                'pricing_group' => $validated['pricing_group'] ?? null,
                'weekdays' => $weekdays === [] ? null : $weekdays,
                'schedules' => $schedules === [] ? null : $schedules,
            ]);

            foreach ($priceRows as $row) {
                $course->coursePrices()->create($row);
            }
        });

        return to_route('courses.index');
    }

    public function edit(Course $course): Response
    {
        $course->load(['coursePrices', 'courseCategory']);
        $schedules = WeekdayDates::normalizeSchedules($course->schedules);
        if ($schedules === []) {
            $levels = $course->coursePrices->pluck('level')->filter()->values()->all();
            $schedules = collect(WeekdayDates::normalize($course->weekdays))
                ->flatMap(function (int $d) use ($levels) {
                    if ($levels === []) {
                        return [[
                            'level' => null,
                            'weekday' => $d,
                            'start_time' => null,
                            'end_time' => null,
                        ]];
                    }

                    return collect($levels)->map(fn (string $level) => [
                        'level' => $level,
                        'weekday' => $d,
                        'start_time' => null,
                        'end_time' => null,
                    ]);
                })
                ->values()
                ->all();
        }

        return Inertia::render('Courses/Edit', [
            'course' => array_merge($course->toArray(), [
                'schedules' => $schedules,
            ]),
            'categories' => CourseCategory::query()
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'sort_order']),
            'gradeLevels' => $this->gradeLevelOptions(),
            'pricingGroups' => PricingGroup::options(),
        ]);
    }

    public function update(Request $request, Course $course): RedirectResponse
    {
        $allowedGrades = $this->activeGradeNames();

        if ($request->input('color') === '') {
            $request->merge(['color' => null]);
        }

        $validated = $request->validate([
            'course_category_id' => ['required', 'exists:course_categories,id'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('courses', 'name')
                    ->where(
                        fn ($query) => $query->where('course_category_id', $request->integer('course_category_id'))
                    )
                    ->ignore($course->id),
            ],
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'status' => ['required', 'in:active,paused'],
            'pricing_group' => ['nullable', 'string', Rule::in(PricingGroup::keys())],
            'schedules' => ['nullable', 'array'],
            'schedules.*.level' => ['nullable', 'string', Rule::in($allowedGrades)],
            'schedules.*.weekday' => ['required', 'integer', 'between:1,7'],
            'schedules.*.start_time' => ['nullable', 'date_format:H:i'],
            'schedules.*.end_time' => ['nullable', 'date_format:H:i'],
            'levels' => ['nullable', 'array'],
            'levels.*' => ['string', Rule::in($allowedGrades)],
        ]);

        $priceRows = $this->levelsToPriceRows($validated['levels'] ?? [], $allowedGrades);
        $levels = array_values(array_unique(array_filter(
            $validated['levels'] ?? [],
            fn (string $name): bool => in_array($name, $allowedGrades, true)
        )));
        $schedules = $this->normalizedSchedulesForLevels($validated['schedules'] ?? [], $levels);
        $this->assertScheduleTimesValid($schedules);
        $weekdays = WeekdayDates::weekdaysFromSchedules($schedules);

        DB::transaction(function () use ($course, $validated, $priceRows, $schedules, $weekdays): void {
            $course->update([
                'course_category_id' => $validated['course_category_id'],
                'name' => $validated['name'],
                'color' => $validated['color'] ?? null,
                'status' => $validated['status'],
                'pricing_group' => $validated['pricing_group'] ?? null,
                'weekdays' => $weekdays === [] ? null : $weekdays,
                'schedules' => $schedules === [] ? null : $schedules,
            ]);

            $course->coursePrices()->delete();

            foreach ($priceRows as $row) {
                $course->coursePrices()->create($row);
            }
        });

        return to_route('courses.index');
    }

    public function destroy(Course $course): RedirectResponse
    {
        $course->delete();

        return to_route('courses.index');
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

    /**
     * @return list<string>
     */
    private function activeGradeNames(): array
    {
        return GradeLevel::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('name')
            ->all();
    }

    /**
     * @param  list<string>  $levels
     * @param  list<string>  $allowedGrades
     * @return list<array{level: ?string, duration_hours: float, tuition: int, sort_order: int}>
     */
    private function levelsToPriceRows(array $levels, array $allowedGrades): array
    {
        $unique = array_values(array_unique(array_filter(
            $levels,
            fn (string $name): bool => in_array($name, $allowedGrades, true)
        )));

        if ($unique === []) {
            return [[
                'level' => null,
                'duration_hours' => 1.0,
                'tuition' => 0,
                'sort_order' => 0,
            ]];
        }

        $order = array_flip($allowedGrades);
        usort($unique, fn (string $a, string $b): int => ($order[$a] ?? 99) <=> ($order[$b] ?? 99));

        $out = [];
        foreach ($unique as $index => $level) {
            $out[] = [
                'level' => $level,
                'duration_hours' => 1.0,
                'tuition' => 0,
                'sort_order' => $index,
            ];
        }

        return $out;
    }

    /**
     * @param  list<array{level:?string, weekday:int, start_time:?string, end_time:?string}>  $schedules
     */
    private function assertScheduleTimesValid(array $schedules): void
    {
        foreach ($schedules as $i => $row) {
            $start = $row['start_time'] ?? null;
            $end = $row['end_time'] ?? null;
            if ($start === null && $end === null) {
                continue;
            }
            if ($start === null || $end === null) {
                throw ValidationException::withMessages([
                    "schedules.{$i}.end_time" => '開始與結束時間請一併填寫，或皆留空。',
                ]);
            }
            if ($start >= $end) {
                throw ValidationException::withMessages([
                    "schedules.{$i}.end_time" => '結束時間需晚於開始時間。',
                ]);
            }
        }
    }

    /**
     * @param  array<int, mixed>  $raw
     * @param  list<string>  $levels
     * @return list<array{level:?string, weekday:int, start_time:?string, end_time:?string}>
     */
    private function normalizedSchedulesForLevels(array $raw, array $levels): array
    {
        $schedules = WeekdayDates::normalizeSchedules($raw);
        if ($levels === []) {
            // 不分年級：level 一律清成 null
            return array_map(function (array $row): array {
                $row['level'] = null;

                return $row;
            }, $schedules);
        }

        $allowed = array_flip($levels);

        return array_values(array_filter(
            $schedules,
            fn (array $row): bool => isset($allowed[(string) ($row['level'] ?? '')])
        ));
    }
}
