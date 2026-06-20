<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $body = '<h2>&#128075; Welcome to the University Portal!</h2>'
            . '<p>Dear <strong>{{name}}</strong>,</p>'
            . '<p>Your account has been created successfully. You can now log in and access your courses and exams.</p>'
            . '<div style="background:#f0f4ff;border-left:4px solid #3730a3;padding:14px 18px;margin:20px 0;border-radius:4px">'
            . '<strong>Login Email:</strong> {{email}}<br>'
            . '<strong>Portal URL:</strong> <a href="{{app_url}}">{{app_url}}</a>'
            . '</div>'
            . '<p style="text-align:center;margin-top:24px">'
            . '<a href="{{app_url}}/login" style="display:inline-block;background:#3730a3;color:#fff;padding:12px 28px;border-radius:6px;text-decoration:none;font-weight:700">Log In Now &rarr;</a>'
            . '</p>'
            . '<p>Best regards,<br>{{app_name}} Team</p>';

        DB::table('email_templates')
            ->where('slug', 'welcome')
            ->update([
                'subject'    => 'Welcome to {{app_name}}!',
                'body_html'  => $body,
                'is_active'  => 1,
                'updated_at' => now(),
            ]);
    }

    public function down(): void {}
};
