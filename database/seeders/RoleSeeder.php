<?php

namespace Database\Seeders;

use App\Enums\RoleSlug;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['name' => 'Administrator', 'slug' => RoleSlug::ADMIN],
            ['name' => 'Teacher', 'slug' => RoleSlug::TEACHER],
            ['name' => 'Student', 'slug' => RoleSlug::STUDENT],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['slug' => $role['slug']], $role);
        }
    }
}
