<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$migration = '2026_07_25_000001_add_thread_columns_to_inbox_emails';

$exists = \Illuminate\Support\Facades\DB::table('migrations')
    ->where('migration', $migration)->exists();

if ($exists) {
    echo "Migration already recorded.\n";
} else {
    $batch = \Illuminate\Support\Facades\DB::table('migrations')->max('batch') + 1;
    \Illuminate\Support\Facades\DB::table('migrations')->insert([
        'migration' => $migration,
        'batch'     => $batch,
    ]);
    echo "Migration recorded as batch {$batch}.\n";
}
