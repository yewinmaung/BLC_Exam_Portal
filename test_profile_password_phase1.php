<?php

/**
 * One-off verification script for Phase 1 profile password change.
 * Run: php test_profile_password_phase1.php
 */

require __DIR__ . '/vendor/autoload.php';

$_ENV['APP_ENV'] = 'testing';
putenv('APP_ENV=testing');

$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Enums\RoleSlug;
use App\Jobs\SendPasswordChangedJob;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** @var TestCase $test */
$test = new class extends TestCase {
    use RefreshDatabase;

    public function executeChecks(): void
    {
        $this->createApplication();

        $role = Role::firstOrCreate(['slug' => RoleSlug::STUDENT], ['name' => 'Student']);
        $user = User::factory()->create([
            'role_id'  => $role->id,
            'password' => Hash::make('OldPass1'),
        ]);

        Queue::fake();

        // Validation: mismatch
        $r1 = $this->actingAs($user)->postJson(route('profile.password'), [
            'password'              => 'NewPass1',
            'password_confirmation' => 'Different1',
        ]);
        echo ($r1->status() === 422 ? 'PASS' : 'FAIL') . " — rejects password mismatch (422)\n";

        // Validation: weak password
        $r2 = $this->actingAs($user)->postJson(route('profile.password'), [
            'password'              => 'weak',
            'password_confirmation' => 'weak',
        ]);
        echo ($r2->status() === 422 ? 'PASS' : 'FAIL') . " — rejects weak password (422)\n";

        // Success
        $r3 = $this->actingAs($user)->postJson(route('profile.password'), [
            'password'              => 'NewPass1',
            'password_confirmation' => 'NewPass1',
        ]);
        $user->refresh();
        $ok = $r3->status() === 200
            && ($r3->json('success') === true)
            && Hash::check('NewPass1', $user->password);
        echo ($ok ? 'PASS' : 'FAIL') . " — updates password and returns success\n";

        Queue::assertPushed(SendPasswordChangedJob::class, fn ($job) => $job->userId === $user->id);
        echo "PASS — dispatches SendPasswordChangedJob\n";

        // Guest rejected
        auth()->logout();
        $r4 = $this->postJson(route('profile.password'), [
            'password'              => 'Another1',
            'password_confirmation' => 'Another1',
        ]);
        echo ($r4->status() === 401 ? 'PASS' : 'FAIL') . " — rejects unauthenticated request (401)\n";

        // OTP routes removed
        $otpRoutes = collect(Route::getRoutes())->filter(
            fn ($route) => str_contains($route->getName() ?? '', 'profile.otp')
        );
        echo ($otpRoutes->isEmpty() ? 'PASS' : 'FAIL') . " — profile OTP routes removed\n";
    }
};

$test->executeChecks();
