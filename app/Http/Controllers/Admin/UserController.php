<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleSlug;
use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Support\AcademicYear;
use App\Services\EmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function __construct(
        private ActivityLogService $activityLog,
        private EmailService $emailService
    ) {
    }

    public function index(Request $request)
    {
        $search  = $request->string('search')->trim()->limit(100)->value();
        $roleId  = $request->filled('role_id') ? (int) $request->role_id : null;
        $status  = $request->filled('status') ? $request->status : null;

        $roles = Role::orderBy('name')->get();

        $users = User::with('role')
            ->when($search, fn ($q) =>
                $q->where('name',  'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
            )
            ->when($roleId, fn ($q) => $q->where('role_id', $roleId))
            ->when($status === 'active',   fn ($q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('admin.users.index', compact('users', 'roles'));
    }

    public function create()
    {
        $roles = Role::all();
        $years = AcademicYear::OPTIONS;
        $studentRoleId = Role::where('slug', RoleSlug::STUDENT)->value('id');

        return view('admin.users.create', compact('roles', 'years', 'studentRoleId'));
    }

    public function store(Request $request)
    {
        $studentRoleId = Role::where('slug', RoleSlug::STUDENT)->value('id');

        $data = $request->validate([
            'name'           => 'required|string|max:255',
            'email'          => 'required|email|unique:users,email',
            'password'       => 'required|min:8',
            'role_id'        => 'required|exists:roles,id',
            'phone'          => 'nullable|string',
            'academic_year'  => [
                Rule::requiredIf((int) $request->role_id === (int) $studentRoleId),
                'nullable',
                'integer',
                'min:1',
                'max:5',
            ],
        ]);

        $user = User::create([
            'name'              => $data['name'],
            'email'             => $data['email'],
            'password'          => Hash::make($data['password']),
            'role_id'           => $data['role_id'],
            'phone'             => $data['phone'] ?? null,
            'academic_year'     => (int) $data['role_id'] === (int) $studentRoleId
                ? (int) $data['academic_year']
                : null,
            'email_verified_at' => now(),
            'is_active'         => true,
        ]);

        $this->activityLog->log('user_created', "Created user {$user->email}", $user);

        // Send welcome email
        $this->emailService->sendWelcomeEmail($user);

        return redirect()->route('admin.users.index')->with('success', 'User created successfully.');
    }

    public function edit(User $user)
    {
        $roles = Role::all();
        $years = AcademicYear::OPTIONS;
        $studentRoleId = Role::where('slug', RoleSlug::STUDENT)->value('id');

        return view('admin.users.edit', compact('user', 'roles', 'years', 'studentRoleId'));
    }

    public function update(Request $request, User $user)
    {
        $studentRoleId = Role::where('slug', RoleSlug::STUDENT)->value('id');

        $data = $request->validate([
            'name'          => 'required|string|max:255',
            'email'         => 'required|email|unique:users,email,' . $user->id,
            'role_id'       => 'required|exists:roles,id',
            'phone'         => 'nullable|string',
            'is_active'     => 'boolean',
            'password'      => 'nullable|min:8',
            'academic_year' => [
                Rule::requiredIf((int) $request->role_id === (int) $studentRoleId),
                'nullable',
                'integer',
                'min:1',
                'max:5',
            ],
        ]);

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $data['is_active'] = $request->boolean('is_active');
        $data['academic_year'] = (int) $data['role_id'] === (int) $studentRoleId
            ? (int) $data['academic_year']
            : null;

        $user->update($data);

        return redirect()->route('admin.users.index')->with('success', 'User updated.');
    }

    public function terminate(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->withErrors(['error' => 'Cannot terminate your own account.']);
        }

        if (!$user->is_active) {
            return back()->withErrors(['error' => 'User is already terminated.']);
        }

        $user->update([
            'is_active'          => false,
            'exam_session_token' => null,
        ]);

        if ($user->email) {
            try {
                app(\App\Services\EmailService::class)->send(
                    $user->email,
                    $user->name,
                    '[' . config('app.name') . '] Your Account Has Been Suspended',
                    view('emails.account-terminated', ['user' => $user])->render(),
                    'account_terminated',
                    'account_terminated',
                    $user->id,
                    false   // sync — user may lose access immediately
                );
            } catch (\Throwable $e) {
                logger()->error('AccountTerminatedMail failed: ' . $e->getMessage());
            }
        }

        $this->activityLog->log('user_terminated', "Terminated user {$user->email}", $user);

        return back()->with('success', "{$user->name}'s account has been suspended and they have been notified by email.");
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return back()->withErrors(['error' => 'Cannot delete your own account.']);
        }

        $email = $user->email;
        $name  = $user->name;

        $user->forceDelete();

        $this->activityLog->log('user_deleted', "Permanently deleted user {$email}");

        return redirect()->route('admin.users.index')
            ->with('success', "{$name}'s account has been permanently deleted.");
    }
}
