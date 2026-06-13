<?php

namespace Database\Seeders;

use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;

class EmailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'name'      => 'Exam Published',
                'slug'      => 'exam_published',
                'subject'   => 'New Exam Available: {{exam_name}}',
                'event'     => 'exam_published',
                'body_html' => <<<HTML
<h2>📝 New Exam Available</h2>
<p>Dear <strong>{{student_name}}</strong>,</p>
<p>A new exam has been published for your course <strong>{{course_name}}</strong>.</p>
<div style="background:#f0f4ff;border-left:4px solid #3730a3;padding:14px 18px;margin:20px 0;border-radius:4px">
    <strong>Exam:</strong> {{exam_name}}<br>
    <strong>Course:</strong> {{course_name}}
</div>
<p>Please log in to your student portal to view the schedule.</p>
HTML,
                'is_active' => true,
            ],
            [
                'name'      => 'Exam Submitted for Approval',
                'slug'      => 'exam_submitted',
                'subject'   => 'Exam Pending Approval: {{exam_name}}',
                'event'     => 'exam_submitted',
                'body_html' => <<<HTML
<h2>📋 Exam Pending Approval</h2>
<p>Dear Admin,</p>
<p>Teacher <strong>{{teacher_name}}</strong> has submitted <strong>{{exam_name}}</strong> for your review.</p>
<p>Please log in to review and approve.</p>
HTML,
                'is_active' => true,
            ],
            [
                'name'      => 'Exam Reminder',
                'slug'      => 'exam_reminder',
                'subject'   => 'Reminder: {{exam_name}} is coming up!',
                'event'     => 'exam_reminder',
                'body_html' => <<<HTML
<h2>⏰ Exam Reminder</h2>
<p>Dear <strong>{{student_name}}</strong>,</p>
<p>This is a reminder that your exam <strong>{{exam_name}}</strong> for <strong>{{course_name}}</strong> is coming up soon.</p>
<p>Make sure you are prepared and have a stable internet connection.</p>
HTML,
                'is_active' => true,
            ],
            [
                'name'      => 'Result Published',
                'slug'      => 'result_published',
                'subject'   => 'Your Result for {{exam_name}} is Available',
                'event'     => 'result_published',
                'body_html' => <<<HTML
<h2>📊 Your Exam Result</h2>
<p>Dear <strong>{{student_name}}</strong>,</p>
<p>Your result for <strong>{{exam_name}}</strong> has been published.</p>
<div style="background:#f0f4ff;border-left:4px solid #3730a3;padding:14px 18px;margin:20px 0;border-radius:4px">
    <strong>Result:</strong> {{result}}<br>
    <strong>GPA:</strong> {{gpa}}
</div>
<p>Log in to your portal to view detailed results.</p>
HTML,
                'is_active' => true,
            ],
            [
                'name'      => 'Welcome Email',
                'slug'      => 'welcome',
                'subject'   => 'Welcome to {{app_name}}!',
                'event'     => 'student_created',
                'body_html' => <<<HTML
<h2>👋 Welcome to the University Portal!</h2>
<p>Dear <strong>{{student_name}}</strong>,</p>
<p>Your student account has been created successfully. You can now log in and access your courses and exams.</p>
<div style="background:#f0f4ff;border-left:4px solid #3730a3;padding:14px 18px;margin:20px 0;border-radius:4px">
    <strong>Student ID:</strong> {{student_id}}<br>
    <strong>Email:</strong> {{student_name}}
</div>
<p>If you have any questions, please contact your institution.</p>
HTML,
                'is_active' => true,
            ],
        ];

        foreach ($templates as $tmpl) {
            EmailTemplate::firstOrCreate(
                ['slug' => $tmpl['slug']],
                $tmpl
            );
        }
    }
}
