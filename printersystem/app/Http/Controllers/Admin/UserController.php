<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(): View
    {
        $users = User::query()->withCount('printJobs')->orderBy('username')->paginate(20);

        return view('admin.users.index', compact('users'));
    }

    public function create(): View
    {
        return view('admin.users.create');
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = User::create([
            ...$request->safe()->only(['username', 'password', 'role']),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect()->route('admin.users.edit', $user)->with('success', 'User created successfully.');
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', compact('user'));
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $validated = $request->safe()->only(['username', 'role', 'is_active']);
        $newRole = UserRole::from($validated['role']);
        $willRemoveAdminAccess = $user->isAdmin() && ($newRole !== UserRole::Admin || ! $request->boolean('is_active'));

        if ($willRemoveAdminAccess && User::query()->where('role', UserRole::Admin)->where('is_active', true)->count() <= 1) {
            return back()->withErrors(['role' => 'The only active admin cannot be demoted or disabled.']);
        }

        if ($request->user()->is($user) && ! $request->boolean('is_active')) {
            return back()->withErrors(['is_active' => 'You cannot disable your own account.']);
        }

        if ($request->filled('password')) {
            $validated['password'] = $request->string('password')->toString();
        }

        $user->update($validated);

        return back()->with('success', 'User updated successfully.');
    }

    public function destroy(User $user): RedirectResponse
    {
        if (request()->user()->is($user)) {
            return back()->withErrors(['user' => 'You cannot delete your own account.']);
        }

        if ($user->isAdmin() && User::query()->where('role', UserRole::Admin)->where('is_active', true)->count() <= 1) {
            return back()->withErrors(['user' => 'The only active admin cannot be deleted.']);
        }

        if ($user->printJobs()->exists()) {
            $user->update(['is_active' => false]);

            return back()->with('success', 'User has print history, so the account was disabled instead of deleted.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')->with('success', 'User deleted successfully.');
    }
}
