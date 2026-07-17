<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());

echo "=== EMAIL TEMPLATES IN DATABASE ===\n";
$templates = App\Models\EmailTemplate::select('id','slug','name','is_active','event')->get();
foreach ($templates as $t) {
    echo "  {$t->id} | {$t->slug} | {$t->name} | active=" . ($t->is_active ? 'Y' : 'N') . "\n";
}

echo "\n=== EmailLog fillable ===\n";
$log = new App\Models\EmailLog();
$fillable = $log->getFillable();
$required = ['cc_email','cc_name','email_type','campaign_id'];
foreach ($required as $col) {
    echo "  {$col}: " . (in_array($col, $fillable) ? 'IN fillable ✓' : 'MISSING ✗') . "\n";
}

echo "\n=== RENDER TEST: welcome template ===\n";
$tmpl = App\Models\EmailTemplate::findBySlug('welcome');
if ($tmpl) {
    $rendered = $tmpl->render([
        'student_name' => 'Ye Win Aung',
        'name'         => 'Ye Win Aung',
        'email'        => 'yewinator@gmail.com',
        'app_name'     => 'Believe Learning Center',
        'app_url'      => 'http://localhost:8000',
    ]);
    echo "  Subject: {$rendered['subject']}\n";
    echo "  Body contains name: " . (str_contains($rendered['bodyHtml'], 'Ye Win Aung') ? 'YES ✓' : 'NO ✗') . "\n";
    echo "  No unreplaced {{vars}}: " . (!preg_match('/\{\{[a-z_]+\}\}/', $rendered['bodyHtml']) ? 'YES ✓' : 'NO — unreplaced vars remain ✗') . "\n";
} else {
    echo "  welcome template NOT FOUND ✗\n";
}

echo "\n=== RENDER TEST: exam_published template ===\n";
$tmpl2 = App\Models\EmailTemplate::findBySlug('exam_published');
if ($tmpl2) {
    $rendered2 = $tmpl2->render([
        'student_name'  => 'Ye Win Aung',
        'exam_name'     => 'Midterm Exam',
        'course_name'   => 'Computer Science 101',
        'total_marks'   => '100',
        'passing_marks' => '40',
        'app_name'      => 'Believe Learning Center',
        'app_url'       => 'http://localhost:8000',
    ]);
    echo "  Subject: {$rendered2['subject']}\n";
    echo "  Body contains student name: " . (str_contains($rendered2['bodyHtml'], 'Ye Win Aung') ? 'YES ✓' : 'NO ✗') . "\n";
    echo "  Body contains exam name: " . (str_contains($rendered2['bodyHtml'], 'Midterm Exam') ? 'YES ✓' : 'NO ✗') . "\n";
} else {
    echo "  exam_published template NOT FOUND ✗\n";
}

echo "\nDone.\n";
