<?php

namespace App\Http\Controllers;

use App\Models\UserTodo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserTodoController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
        ]);

        UserTodo::query()->create([
            'user_id' => (int) $request->user()->id,
            'title' => trim($validated['title']),
        ]);

        return back();
    }

    public function update(Request $request, UserTodo $todo): RedirectResponse
    {
        $this->authorizeTodo($request, $todo);

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:200'],
            'completed' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('title', $validated)) {
            $todo->title = trim((string) $validated['title']);
        }
        if (array_key_exists('completed', $validated)) {
            $todo->completed = (bool) $validated['completed'];
        }
        $todo->save();

        return back();
    }

    public function destroy(Request $request, UserTodo $todo): RedirectResponse
    {
        $this->authorizeTodo($request, $todo);
        $todo->delete();

        return back();
    }

    private function authorizeTodo(Request $request, UserTodo $todo): void
    {
        if ((int) $todo->user_id !== (int) $request->user()->id) {
            abort(403);
        }
    }
}
