<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$cols = \Illuminate\Support\Facades\DB::select("SHOW COLUMNS FROM inbox_emails");
echo "=== COLUMNS ===\n";
foreach ($cols as $c) { echo "  " . $c->Field . " (" . $c->Type . ")\n"; }

$idxs = \Illuminate\Support\Facades\DB::select("SHOW INDEX FROM inbox_emails");
echo "\n=== INDEXES ===\n";
foreach ($idxs as $i) { echo "  " . $i->Key_name . " -> " . $i->Column_name . "\n"; }

echo "\n=== MIGRATIONS (email related) ===\n";
$migs = \Illuminate\Support\Facades\DB::table('migrations')->where('migration','like','%inbox%')->orWhere('migration','like','%thread%')->get();
foreach ($migs as $m) { echo "  [batch {$m->batch}] {$m->migration}\n"; }
