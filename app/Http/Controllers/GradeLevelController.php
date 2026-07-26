<?php

namespace App\Http\Controllers;

use App\Models\GradeLevel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GradeLevelController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('GradeLevels/Index', [
            'grades' => GradeLevel::query()
                ->withCount('students')
                ->orderBy('sort_order')
                ->orderBy('code')
                ->paginate(50),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('GradeLevels/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:32', 'unique:grade_levels,name'],
            'code' => ['required', 'integer', 'min:1', 'max:99', 'unique:grade_levels,code'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        GradeLevel::query()->create([
            'name' => $validated['name'],
            'code' => $validated['code'],
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return to_route('grade-levels.index');
    }

    public function edit(GradeLevel $gradeLevel): Response
    {
        return Inertia::render('GradeLevels/Edit', [
            'grade' => $gradeLevel,
        ]);
    }

    public function update(Request $request, GradeLevel $gradeLevel): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:32', 'unique:grade_levels,name,'.$gradeLevel->id],
            'code' => ['required', 'integer', 'min:1', 'max:99', 'unique:grade_levels,code,'.$gradeLevel->id],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $gradeLevel->update([
            'name' => $validated['name'],
            'code' => $validated['code'],
            'sort_order' => $validated['sort_order'] ?? 0,
            'is_active' => (bool) ($validated['is_active'] ?? true),
        ]);

        return to_route('grade-levels.index');
    }

    public function destroy(GradeLevel $gradeLevel): RedirectResponse
    {
        if ($gradeLevel->students()->exists()) {
            return back()->withErrors([
                'delete' => '此年級已有學生使用，無法刪除。',
            ]);
        }

        $gradeLevel->delete();

        return to_route('grade-levels.index');
    }
}
