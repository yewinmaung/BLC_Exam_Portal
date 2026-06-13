<?php

namespace Database\Seeders;

use App\Enums\RoleSlug;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            QuestionCategorySeeder::class,
            AcademicYearSeeder::class,
            YearLevelSeeder::class,
            EmailTemplateSeeder::class,
        ]);

        $adminRole = Role::where('slug', RoleSlug::ADMIN)->first();
        $teacherRole = Role::where('slug', RoleSlug::TEACHER)->first();
        $studentRole = Role::where('slug', RoleSlug::STUDENT)->first();

        User::firstOrCreate(
            ['email' => config('believe.default_admin.email', 'admin@blc.edu.mm')],
            [
                'name' => config('believe.default_admin.name', 'BLC Administrator'),
                'password' => Hash::make(config('believe.default_admin.password', 'password')),
                'role_id' => $adminRole->id,
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );

        User::firstOrCreate(
            ['email' => 'teacher@believeexam.com'],
            [
                'name' => 'Demo Teacher',
                'password' => Hash::make('password'),
                'role_id' => $teacherRole->id,
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );

        User::firstOrCreate(
            ['email' => 'student@believeexam.com'],
            [
                'name' => 'Demo Student',
                'password' => Hash::make('password'),
                'role_id' => $studentRole->id,
                'academic_year' => 1,
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );

        User::whereHas('role', fn ($q) => $q->where('slug', RoleSlug::STUDENT))
            ->whereNull('academic_year')
            ->update(['academic_year' => 1]);

        $this->call(DemoDataSeeder::class);
    }
}
