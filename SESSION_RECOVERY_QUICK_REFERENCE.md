# Session Recovery - Quick Reference

## 🎯 Purpose
Handle temporary exam interruptions (browser close, network error) without triggering cheating detection.

---

## ⏱️ Recovery Window
**Default**: 10 minutes (600 seconds)  
**Configurable**: `config/exam_security.php` → `recovery_time_limit`

---

## 📊 Status Flow

```
┌─────────────────┐
│  In Progress    │ ← Student taking exam
└────────┬────────┘
         │
         │ Browser closes / Network fails
         ▼
┌─────────────────────────────┐
│ Terminated Pending Review   │ ← Disconnect recorded
└────────┬────────────────────┘
         │
         │ Student returns
         ▼
    Within 10 min?
         │
    ┌────┴────┐
   YES       NO
    │         │
    ▼         ▼
┌────────┐  ┌─────────┐
│In Prog │  │ Expired │
│(Resume)│  │ (Error) │
└────────┘  └─────────┘
```

---

## 🔧 Key Components

### Backend
- **Model**: `SessionRecoveryLog` - Tracks all events
- **Service**: `SessionRecoveryService` - Business logic
- **Controller**: `ExamSessionController@disconnect` - Records disconnect
- **Controller**: `ExamSessionController@take` - Handles recovery

### Frontend
- **JavaScript**: `exam-anticheat.js` - Detects browser close
- **View**: `student/exam/take.blade.php` - Passes disconnect URL

### Database
- **Table**: `session_recovery_logs` - Full audit trail
- **Columns in exam_attempts**: `disconnected_at`, `last_question_id`

---

## 🚀 How It Works

### 1. Disconnect Detection
```javascript
window.addEventListener('beforeunload', function(e) {
    if (examStarted && !examLocked && !isSubmitting) {
        navigator.sendBeacon(disconnectUrl, data);
    }
});
```

### 2. Disconnect Recording
```php
// ExamSessionController@disconnect
$this->recovery->recordDisconnect($attempt, $questionId, $reason, $browserInfo);
// Status: in_progress → terminated_pending_review
```

### 3. Auto Recovery Check
```php
// ExamSessionController@take
if ($attempt->status === 'terminated_pending_review' && $attempt->disconnected_at) {
    $result = $this->recovery->attemptAutoRecovery($attempt);
    if ($result['success']) {
        // Status: terminated_pending_review → in_progress
        // Continue showing exam
    } else {
        // Recovery expired, show error
    }
}
```

---

## ✅ Critical Rules

### What This DOES
✅ Handles temporary browser close  
✅ Handles network disconnections  
✅ Preserves student progress  
✅ Logs all events for admin  
✅ Continues original timer (no extra time)

### What This DOES NOT Do
❌ Does NOT trigger cheating detection  
❌ Does NOT increase warning count  
❌ Does NOT create security violations  
❌ Does NOT grant extra exam time  
❌ Does NOT affect existing cheating system

---

## 🧪 Testing Checklist

- [ ] Student can resume within 10 minutes
- [ ] Student cannot resume after 10 minutes
- [ ] No disconnect recorded on intentional submit
- [ ] No disconnect recorded on time expiry
- [ ] Timer continues correctly after recovery
- [ ] Last question restored after recovery
- [ ] Recovery logs created in database
- [ ] No cheating warning triggered
- [ ] Recovery works with all question types
- [ ] Error message shown when expired

---

## 📝 Recovery Log Fields

```php
SessionRecoveryLog::create([
    'attempt_id'       => $attempt->id,
    'student_id'       => $attempt->student_id,
    'exam_id'          => $attempt->exam_id,
    'disconnect_reason' => 'browser_close',
    'disconnected_at'  => now(),
    'last_question_id' => 5,
    'reconnected_at'   => now()->addMinutes(3),
    'recovery_status'  => 'recovered', // or 'pending' or 'expired'
    'disconnected_duration_seconds' => 180,
    'browser_info'     => [...],
    'user_agent'       => '...',
    'ip_address'       => '...',
]);
```

---

## 🔍 Admin Queries

### View All Recovery Events
```sql
SELECT * FROM session_recovery_logs ORDER BY disconnected_at DESC;
```

### Count Successful Recoveries
```sql
SELECT COUNT(*) FROM session_recovery_logs WHERE recovery_status = 'recovered';
```

### Find Expired Recoveries
```sql
SELECT * FROM session_recovery_logs WHERE recovery_status = 'expired';
```

### Average Disconnect Duration
```sql
SELECT AVG(disconnected_duration_seconds) FROM session_recovery_logs WHERE recovery_status = 'recovered';
```

---

## ⚙️ Configuration

```php
// config/exam_security.php
return [
    // Recovery time window (seconds)
    'recovery_time_limit' => 600, // 10 minutes
    
    // Maximum extension for approved attempts (minutes)
    'max_resume_extension_minutes' => 120,
];
```

---

## 🚨 Troubleshooting

### Problem: Disconnect not recorded
**Check**:
1. Is `disconnectUrl` passed to JavaScript?
2. Is `examStarted` true?
3. Is `isSubmitting` false?
4. Browser supports `navigator.sendBeacon()`?

### Problem: Recovery always fails
**Check**:
1. Is `recovery_time_limit` too small?
2. Is attempt status `terminated_pending_review`?
3. Is `disconnected_at` set?
4. Check server time vs database time

### Problem: Timer not continuing correctly
**Check**:
1. `expires_at` should NOT change during recovery
2. Only `status` changes from `terminated_pending_review` to `in_progress`
3. Frontend timer uses `endsAt` from backend

---

## 📚 Related Files

### Core Implementation
- `app/Services/SessionRecoveryService.php`
- `app/Models/SessionRecoveryLog.php`
- `app/Models/ExamAttempt.php` (added methods)

### Controller & Routes
- `app/Http/Controllers/Student/ExamSessionController.php`
- `routes/web.php` (disconnect route)

### Frontend
- `public/js/exam-anticheat.js`
- `resources/views/student/exam/take.blade.php`

### Database
- `database/migrations/2026_07_08_000001_add_session_recovery_to_exam_attempts.php`

### Configuration
- `config/exam_security.php`

---

## 📞 Support

For questions or issues with session recovery:
1. Check logs in `session_recovery_logs` table
2. Check attempt status in `exam_attempts` table
3. Verify `disconnected_at` timestamp
4. Check recovery time limit in config
5. Review browser console for JavaScript errors

---

## ✨ Summary

**What**: Automatic recovery for temporary exam disconnections  
**When**: Within 10 minutes of disconnect  
**How**: Browser close detection → record disconnect → auto-resume on return  
**Why**: Fair handling of technical issues without cheating penalties  
**Impact**: None on existing cheating detection or security systems

---

Request ID: cbd3163b-da5d-4464-8672-1f92bf22445a  
Status: ✅ Complete
