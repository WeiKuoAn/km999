<?php

namespace App\Http\Controllers;

use App\Models\UserTodo;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $todos = UserTodo::query()
            ->where('user_id', $user->id)
            ->orderBy('completed')
            ->orderByDesc('created_at')
            ->get(['id', 'title', 'completed']);

        return Inertia::render('Dashboard', [
            'todos' => $todos,
            'todayDate' => Carbon::today()->toDateString(),
            'nowDisplay' => Carbon::now()->format('Y-m-d H:i'),
            'filters' => [
                'teacher_id' => '',
            ],
        ]);
    }
}
