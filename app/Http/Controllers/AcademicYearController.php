<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AcademicYearController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('AcademicYears/Index', [
            'years' => AcademicYear::query()
                ->withCount('students')
                ->orderByDesc('year_code')
                ->paginate(50),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('AcademicYears/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'year_code' => ['required', 'string', 'max:8', 'regex:/^\d{2,4}$/', 'unique:academic_years,year_code'],
            'name' => ['nullable', 'string', 'max:255'],
            'is_current' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        DB::transaction(function () use ($validated): void {
            $isCurrent = (bool) ($validated['is_current'] ?? false);
            if ($isCurrent) {
                AcademicYear::query()->update(['is_current' => false]);
            }

            AcademicYear::query()->create([
                'year_code' => $validated['year_code'],
                'name' => $validated['name'] ?: ($validated['year_code'].'學年度'),
                'is_current' => $isCurrent,
                'sort_order' => $validated['sort_order'] ?? 0,
            ]);
        });

        return to_route('academic-years.index');
    }

    public function edit(AcademicYear $academicYear): Response
    {
        return Inertia::render('AcademicYears/Edit', [
            'year' => $academicYear,
        ]);
    }

    public function update(Request $request, AcademicYear $academicYear): RedirectResponse
    {
        $validated = $request->validate([
            'year_code' => ['required', 'string', 'max:8', 'regex:/^\d{2,4}$/', 'unique:academic_years,year_code,'.$academicYear->id],
            'name' => ['nullable', 'string', 'max:255'],
            'is_current' => ['sometimes', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        DB::transaction(function () use ($validated, $academicYear): void {
            $isCurrent = (bool) ($validated['is_current'] ?? false);
            if ($isCurrent) {
                AcademicYear::query()->where('id', '!=', $academicYear->id)->update(['is_current' => false]);
            }

            $academicYear->update([
                'year_code' => $validated['year_code'],
                'name' => $validated['name'] ?: ($validated['year_code'].'學年度'),
                'is_current' => $isCurrent,
                'sort_order' => $validated['sort_order'] ?? 0,
            ]);
        });

        return to_route('academic-years.index');
    }

    public function destroy(AcademicYear $academicYear): RedirectResponse
    {
        if ($academicYear->students()->exists()) {
            return back()->withErrors([
                'delete' => '此學年已有學生使用，無法刪除。',
            ]);
        }

        $academicYear->delete();

        return to_route('academic-years.index');
    }
}
