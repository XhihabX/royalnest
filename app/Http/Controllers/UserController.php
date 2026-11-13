<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Display a listing of system users.
     */
    public function index(Request $request): Response
    {
        $users = User::with('roles')
            ->latest('id')
            ->paginate(15)
            ->through(function ($user) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'primary_role' => $user->primary_role,
                    'is_super_admin' => (bool) $user->is_super_admin,
                    'is_active' => (bool) $user->is_active,
                    'created_at' => $user->created_at?->toDateString(),
                ];
            });

        $roles = Role::select('id', 'name', 'display_name', 'description')->get();

        return Inertia::render('Users/Index', [
            'users' => $users,
            'roles' => $roles,
        ]);
    }

    /**
     * Store a newly created user with secure role assignment.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:150|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'primary_role' => ['required', 'string', Rule::in(['manager', 'cashier', 'visitor'])],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::create([
            'branch_id' => $request->user()?->branch_id ?? 1,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'primary_role' => $validated['primary_role'],
            'is_super_admin' => false,
            'is_active' => true,
            'password' => Hash::make($validated['password']),
        ]);

        // Attach role in user_roles pivot
        $role = Role::where('name', $validated['primary_role'])->first();
        if ($role) {
            $user->roles()->sync([$role->id]);
        }

        AuditLogger::log(
            action: 'created',
            description: "Created new user '{$user->name}' with role '{$user->primary_role}'",
            entity: $user,
            module: 'rbac'
        );

        return back()->with('success', "User '{$user->name}' successfully created.");
    }

    /**
     * Update an existing user.
     */
    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'phone' => 'nullable|string|max:20',
            'primary_role' => ['required', 'string'],
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ]);

        // Super Admin protection guard: role cannot be downgraded
        if ($user->isSuperAdmin() && $validated['primary_role'] !== 'owner') {
            throw ValidationException::withMessages([
                'primary_role' => 'The Primary Super Admin role cannot be changed or downgraded.',
            ]);
        }

        $before = $user->only(['name', 'email', 'phone', 'primary_role']);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->phone = $validated['phone'];

        if (!$user->isSuperAdmin()) {
            $user->primary_role = $validated['primary_role'];
            $role = Role::where('name', $validated['primary_role'])->first();
            if ($role) {
                $user->roles()->sync([$role->id]);
            }
        }

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        AuditLogger::log(
            action: 'updated',
            description: "Updated user '{$user->name}' profile",
            entity: $user,
            beforePayload: $before,
            afterPayload: $user->only(['name', 'email', 'phone', 'primary_role']),
            module: 'rbac'
        );

        return back()->with('success', "User '{$user->name}' updated.");
    }

    /**
     * Toggle user active/inactive status.
     */
    public function toggleStatus(Request $request, User $user): RedirectResponse
    {
        if ($user->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'user' => 'The Primary Super Admin account cannot be deactivated.',
            ]);
        }

        if ($user->id === $request->user()->id) {
            throw ValidationException::withMessages([
                'user' => 'You cannot deactivate your own active session account.',
            ]);
        }

        $user->is_active = !$user->is_active;
        $user->save();

        AuditLogger::log(
            action: 'status_toggled',
            description: ($user->is_active ? 'Activated' : 'Deactivated') . " user '{$user->name}'",
            entity: $user,
            module: 'rbac'
        );

        return back()->with('success', "User '{$user->name}' status updated.");
    }

    /**
     * Soft delete a user.
     */
    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'user' => 'The Primary Super Admin account cannot be deleted.',
            ]);
        }

        if ($user->id === $request->user()->id) {
            throw ValidationException::withMessages([
                'user' => 'You cannot delete your own account.',
            ]);
        }

        $userName = $user->name;
        $user->delete();

        AuditLogger::log(
            action: 'deleted',
            description: "Deleted user '{$userName}'",
            entity: $user,
            module: 'rbac'
        );

        return back()->with('success', "User '{$userName}' deleted.");
    }
}

