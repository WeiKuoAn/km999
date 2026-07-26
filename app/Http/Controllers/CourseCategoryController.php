<?php

namespace App\Http\Controllers;

use App\Models\CourseCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CourseCategoryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('CourseCategories/Index', [
            'categories' => CourseCategory::query()
                ->withCount('courses')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->paginate(50),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('CourseCategories/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:course_categories,name'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:255'],
        ]);

        CourseCategory::query()->create([
            'name' => $validated['name'],
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return to_route('course-categories.index');
    }

    public function edit(CourseCategory $courseCategory): Response
    {
        return Inertia::render('CourseCategories/Edit', [
            'category' => $courseCategory,
        ]);
    }

    public function update(Request $request, CourseCategory $courseCategory): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:course_categories,name,'.$courseCategory->id],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:255'],
        ]);

        $courseCategory->update([
            'name' => $validated['name'],
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return to_route('course-categories.index');
    }

    public function destroy(CourseCategory $courseCategory): RedirectResponse
    {
        if ($courseCategory->courses()->exists()) {
            return back()->withErrors([
                'delete' => '此類別下尚有課程，請先修改或刪除相關課程後再刪除類別。',
            ]);
        }

        $courseCategory->delete();

        return to_route('course-categories.index');
    }
}
