# Quick Reference: Exam Recovery Changes

## Files Modified (5 total)

### 1. `app/Http/Controllers/Student/ExamController.php`

**Location 1 - Line ~81-83:**
```php
// REMOVED 'terminated_pending_review' from status array
whereIn('status', ['submitted', 'terminated', 'suspicious', 'rejected'])
```

**Location 2 - Line ~139-141:**
```php
// REMOVED 'terminated_pending_review' from status array
whereIn('status', ['submitted', 'terminated', 'suspicious', 'rejected'])
```

---

### 2. `app/Services/ExamAccessService.php`

**Location - Line ~129-131:**
```php
// REMOVED 'terminated_pending_review' from status array
whereIn('status', ['submitted', 'terminated', 'suspicious', 'rejected'])
```

---

### 3. `app/Http/Controllers/Student/ReAttemptController.php`

**Location 1 - Line ~45-47:**
```php
// REMOVED 'terminated_pending_review' from status array
whereIn('status', ['submitted', 'terminated', 'suspicious', 'rejected'])
```

**Location 2 - Line ~85-87:**
```php
// REMOVED 'terminated_pending_review' from status array
whereIn('status', ['submitted', 'terminated', 'suspicious', 'rejected'])
```

---

### 4. `app/Services/SessionRecoveryService.php`

**Major Changes in `attemptAutoRecovery()` method:**

**Added:**
- Schedule loading and validation
- Remaining exam time calculation
- Remaining schedule time calculation
- MIN(exam time, schedule time) logic
- `expires_at` recalculation
- Schedule end warning message

**Key Logic:**
```php
// Calculate remaining times
$remainingExamMinutes = max(0, $originalExpiresAt->diffInMinutes($now, false) * -1);
$remainingScheduleMinutes = max(0, $scheduleEndsAt->diffInMinutes($now, false) * -1);

// Apply MIN rule
$finalAvailableMinutes = min($remainingExamMinutes, $remainingScheduleMinutes);

// Recalculate expires_at
$newExpiresAt = $now->copy()->addMinutes($finalAvailableMinutes);

// Update attempt
$attempt->update([
    'status'     => 'in_progress',
    'expires_at' => $newExpiresAt,  // NEW: Timer recalculated
]);
```

---

### 5. `app/Http/Controllers/Student/ExamSessionController.php`

**Major Changes in `take()` method:**

**Added:**
- Schedule end check (auto-submit if schedule ended)
- Timer calculation using MIN rule
- `effectiveEndsAt` calculation for frontend
- `scheduleEndsAt` passed to view

**Key Logic:**
```php
// Check if schedule has ended
if ($schedule && now()->gt($schedule->ends_at)) {
    $this->submitAttempt($attempt);
    return redirect()->route('student.exams.show', $attempt->exam_id)
        ->with('success', 'Exam schedule ended. Exam auto-submitted.');
}

// Calculate final available time
$remainingExamSeconds = max(0, $attempt->expires_at->diffInSeconds($now, false) * -1);
if ($schedule) {
    $remainingScheduleSeconds = max(0, $schedule->ends_at->diffInSeconds($now, false) * -1);
    $finalAvailableSeconds = min($remainingExamSeconds, $remainingScheduleSeconds);
} else {
    $finalAvailableSeconds = $remainingExamSeconds;
}

// Calculate effective end timestamp
$effectiveEndsAt = $now->copy()->addSeconds($finalAvailableSeconds);

// Pass to view
return view('student.exam.take', [
    // ... other data ...
    'endsAt'         => $effectiveEndsAt->timestamp,  // CHANGED: Uses calculated time
    'scheduleEndsAt' => $schedule ? $schedule->ends_at->timestamp : null,  // NEW
]);
```

---

## Summary of Changes

### Attempt Counting:
- **Removed:** `'terminated_pending_review'` from all attempt count queries
- **Files:** ExamController.php (2x), ExamAccessService.php (1x), ReAttemptController.php (2x)
- **Impact:** Temporary disconnects no longer count as consumed attempts

### Timer Calculation:
- **Added:** MIN(remaining exam duration, remaining schedule time) rule
- **Files:** SessionRecoveryService.php, ExamSessionController.php
- **Impact:** Exam respects schedule end time, recovery recalculates remaining time

### Schedule Enforcement:
- **Added:** Auto-submit when schedule ends
- **File:** ExamSessionController.php
- **Impact:** Exam cannot continue beyond schedule end time

---

## Testing Checklist

### Database Queries to Test:

```sql
-- 1. Verify attempt counting (should exclude terminated_pending_review)
SELECT COUNT(*) FROM exam_attempts 
WHERE student_id = ? AND exam_id = ? 
AND status IN ('submitted', 'terminated', 'suspicious', 'rejected');

-- 2. Check recovery log
SELECT * FROM session_recovery_logs 
WHERE attempt_id = ? 
ORDER BY disconnected_at DESC;

-- 3. Verify attempt details
SELECT id, attempt_number, status, started_at, expires_at, 
       disconnected_at, last_question_id
FROM exam_attempts 
WHERE student_id = ? AND exam_id = ?;

-- 4. Check saved answers
SELECT question_id, answer_id, answer_text, updated_at
FROM student_answers 
WHERE attempt_id = ?
ORDER BY question_id;
```

### Manual Testing Steps:

1. **Test Attempt Counting:**
   - [ ] Start exam (attempt 1, status: in_progress)
   - [ ] Disconnect (status: terminated_pending_review)
   - [ ] Check: `SELECT COUNT(*) ... status IN (...)` should return 0
   - [ ] Recover (status: in_progress)
   - [ ] Submit (status: submitted)
   - [ ] Check: `SELECT COUNT(*)` should return 1

2. **Test Timer Calculation:**
   - [ ] Create exam with schedule 10:00-11:00, duration 30 min
   - [ ] Start at 10:30 (expires_at = 11:00)
   - [ ] Disconnect at 10:40
   - [ ] Recover at 10:50
   - [ ] Check: New expires_at should be 11:00 (MIN(20 min, 10 min) = 10 min from 10:50)

3. **Test Schedule Constraint:**
   - [ ] Continue exam until 11:00 (schedule end)
   - [ ] Verify: Exam auto-submits at 11:00
   - [ ] Check: Status becomes 'submitted'

4. **Test Answer Persistence:**
   - [ ] Answer 3 questions
   - [ ] Disconnect
   - [ ] Check database: 3 answers should exist
   - [ ] Recover
   - [ ] Check UI: All 3 answers visible
   - [ ] Navigate to questions: Answers still there

5. **Test Multiple Disconnects:**
   - [ ] Disconnect 3 times, recover each time
   - [ ] Check: 3 recovery logs, all with status 'recovered'
   - [ ] Check: Same attempt_number throughout
   - [ ] Check: All answers preserved

---

## Expected Behavior

### Status Transitions:

```
Normal Flow:
in_progress → submitted (student submits)

Disconnect Flow:
in_progress → terminated_pending_review (disconnect)
terminated_pending_review → in_progress (recovery within 10 min)
terminated_pending_review → [blocked] (recovery after 10 min)

Cheating Flow:
in_progress → terminated (3 warnings)
```

### Attempt Counting:

| Status | Counts as Attempt? |
|--------|-------------------|
| `in_progress` | ❌ No |
| `terminated_pending_review` | ❌ No |
| `submitted` | ✅ Yes |
| `terminated` | ✅ Yes |
| `suspicious` | ✅ Yes |
| `rejected` | ✅ Yes |

### Timer Calculation:

```
Original expires_at = started_at + exam_duration
On recovery:
  remaining_exam_time = expires_at - now
  remaining_schedule_time = schedule.ends_at - now
  final_time = MIN(remaining_exam_time, remaining_schedule_time)
  new_expires_at = now + final_time
```

---

## Rollback Commands

If needed to revert changes:

```bash
# View git history
git log --oneline -10

# Revert the commit (replace <hash> with actual commit)
git revert <commit-hash>

# Or reset to previous commit (destructive!)
git reset --hard HEAD~1

# Clear Laravel cache
php artisan config:clear
php artisan cache:clear
php artisan view:clear

# No migrations to rollback
```

---

## Configuration

Edit `config/exam_security.php`:

```php
'recovery_time_limit' => 600, // seconds (10 minutes)
```

Change this value to adjust recovery window. Then run:

```bash
php artisan config:clear
```

---

## Contact

For questions or issues:
- Check `IMPLEMENTATION_SUMMARY.md` for detailed explanation
- Check `EXAM_RECOVERY_TEST_CASES.md` for test scenarios
- Review code comments in modified files
