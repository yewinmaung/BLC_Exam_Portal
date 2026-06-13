<?php

namespace App\Services;

use App\Enums\RoleSlug;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class EnsureDefaultAdminService
{
    public function run(): bool
    {
        if (!Schema::hasTable('users') || !Schema::hasTable('roles')) {
            return false;
        }

        $email = config('believe.default_admin.email', 'admin@blc.edu.mm');

        if (User::withTrashed()->where('email', $email)->exists()) {
            return false;
        }

        $adminRole = Role::firstOrCreate(
            ['slug' => RoleSlug::ADMIN],
            ['name' => 'Administrator']
        );

        Role::firstOrCreate(
            ['slug' => RoleSlug::TEACHER],
            ['name' => 'Teacher']
        );

        Role::firstOrCreate(
            ['slug' => RoleSlug::STUDENT],
            ['name' => 'Student']
        );

        User::create([
            'name'              => config('believe.default_admin.name', 'BLC Administrator'),
            'email'             => $email,
            'password'          => Hash::make(config('believe.default_admin.password', 'password')),
            'role_id'           => $adminRole->id,
            'email_verified_at' => now(),
            'is_active'         => true,
        ]);

        return true;
    }
}
