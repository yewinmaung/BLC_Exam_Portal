# Security Implementation

## Encryption

- Questions and answers stored in `content_encrypted` using `Illuminate\Support\Facades\Crypt` (AES-256-CBC).
- `App\Services\EncryptionService` handles encrypt/decrypt.
- `App\Services\ExamAccessService` controls who may decrypt:
  - **Before schedule end:** Admin and owning Teacher only (plus Student during active exam window).
  - **After schedule end:** All roles may view correct answers.

## Middleware

| Middleware | Purpose |
|------------|---------|
| `role:admin,teacher` | RBAC route protection |
| `exam.session` | Single active exam session per student |

## Anti-Cheating (Client)

`public/js/exam-anticheat.js` monitors:

- Tab switch (`visibilitychange`)
- Window blur
- Fullscreen exit
- DevTools shortcuts
- Disables context menu, copy, paste, text selection

Three violations trigger server-side termination via `CheatingDetectionService`, email alerts, and activity logs.

## Server Protections

- CSRF tokens on all forms
- Eloquent ORM (SQL injection prevention)
- Blade escaping (XSS prevention)
- Mass assignment via `$fillable`
- Authorization checks in controllers

## Session

- `exam_session_token` on user record prevents concurrent exam logins.
