<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Users/Index', [
            'users' => User::query()
                ->latest('id')
                ->paginate(50),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Users/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in([User::ROLE_SUPER_ADMIN, User::ROLE_ADMIN, User::ROLE_TEACHER])],
            'is_active' => ['required', 'boolean'],
        ]);

        User::query()->create($validated);

        return to_route('users.index');
    }

    public function edit(User $user): Response
    {
        return Inertia::render('Users/Edit', [
            'user' => $user->only(['id', 'name', 'email', 'role', 'is_active']),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:8'],
            'role' => ['required', Rule::in([User::ROLE_SUPER_ADMIN, User::ROLE_ADMIN, User::ROLE_TEACHER])],
            'is_active' => ['required', 'boolean'],
        ]);

        if (($validated['password'] ?? '') === '') {
            unset($validated['password']);
        }

        $user->update($validated);

        return to_route('users.index');
    }

    public function destroy(User $user): RedirectResponse
    {
        if (auth()->id() === $user->id) {
            return back()->withErrors([
                'delete' => '不可刪除目前登入中的帳號。',
            ]);
        }

        $user->delete();

        return to_route('users.index');
    }
}
