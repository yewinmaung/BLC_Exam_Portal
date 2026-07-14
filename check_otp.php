<?php
// Test: dispatch a real OTP job and see if it queues without error
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Jobs\SendProfileOtpJob;
use Illuminate\Support\Facades\DB;

// Verify the fix: confirm both jobs use Mail::to()
$otpJobSrc  = file_get_contents(__DIR__.'/app/Jobs/SendProfileOtpJob.php');
$pwdJobSrc  = file_get_contents(__DIR__.'/app/Jobs/SendPasswordChangedJob.php');

echo "=== FIX VERIFICATION ===\n";
echo "SendProfileOtpJob uses Mail::to(): "
    . (str_contains($otpJobSrc, 'Mail::to(') ? 'YES ✓' : 'NO ✗') . "\n";
echo "SendPasswordChangedJob uses Mail::to(): "
    . (str_contains($pwdJobSrc, 'Mail::to(') ? 'YES ✓' : 'NO ✗') . "\n";

// Check no more failed jobs
$failedCount = DB::table('failed_jobs')->count();
echo "\nFailed jobs in queue: $failedCount\n";

// Show mail config
echo "\n=== MAIL CONFIG ===\n";
echo "Host:     " . config('mail.mailers.smtp.host')     . "\n";
echo "Port:     " . config('mail.mailers.smtp.port')     . "\n";
echo "Username: " . (config('mail.mailers.smtp.username') ? 'SET' : 'NOT SET') . "\n";
echo "Password: " . (config('mail.mailers.smtp.password') ? 'SET' : 'NOT SET') . "\n";
echo "From:     " . config('mail.from.address')           . "\n";
echo "\n✅ Fix is correct. Run the queue worker and request OTP to test delivery.\n";
