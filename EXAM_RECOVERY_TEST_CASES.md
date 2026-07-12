# Exam Session Recovery & Timer Calculation - Test Cases

## Overview
This document provides comprehensive test cases to verify the exam session recovery workflow and timer calculation modifications.

## What Was Changed

### Files Modified:
1. **app/Http/Controllers/Student/ExamController.php** - Fixed attempt counting (removed `terminated_pending_review`)
2. **app/Services/ExamAccessService.php** - Fixed attempt counting
3. **app/Http/Controllers/Student/ReAttemptController.php** - Fixed attempt counting
4. **app/Services/SessionRecoveryService.php** - Added timer recalculation logic
5. **app/Http/Controllers/Student/ExamSessionController.php** - Added schedule constraint checking and timer calculation

### Key Changes:

#### 1. Attempt Counting Rule (✓ Fixed)
- **Before**: `terminated_pending_review` status was counted as a consumed attempt
- **After**: Only `['submitted', 'terminated', 'suspicious', 'rejected']` count as consumed attempts
- **Result**: Students with disconnected sessions (`terminated_pending_review`) can recover without losing an attempt

#### 2. Timer Calculation (✓ Fixed)
- **Before**: Timer only used `attempt->expires_at`, ignoring schedule end time
- **After**: Timer uses `MIN(Remaining exam duration, Remaining schedule time)`
- **Result**: Exam automatically ends at schedule end time even if student has remaining exam duration

#### 3. Session Recovery (✓ Enhanced)
- **Before**: Recovery restored status but didn't recalculate timer
- **After**: Recovery recalculates `expires_at` based on remaining time and schedule constraints
- **Result**: Student gets exact remaining time, respecting schedule limits

---

## Test Case 1: Basic Session Recovery (Same Attempt)

### Setup:
- Exam Schedule: 10:00 AM - 12:00 PM
- Exam Duration: 30 minutes
- Attempt Limit: 2

### Test Steps:

1. **Student starts exam at 10:30 AM**
   - Expected: `started_at = 10:30 AM`, `expires_at = 11:00 AM`
   - Check: `attempt_number = 1`, `status = 'in_progress'`

2. **Student answers Question 1 and disconnects at 10:35 AM**
   - Expected: `status = 'terminated_pending_review'`
   - Expected: `disconnected_at = 10:35 AM`
   - Expected: `last_question_id = 1` (or next unanswered)
   - Check database: Answer to Question 1 should be saved in `student_answers`

3. **Student reconnects at 10:36 AM (within 10-minute recovery window)**
   - Expected: Recovery successful
   - Expected: `status = 'in_progress'` (SAME attempt)
   - Expected: New `expires_at = 10:36 AM + 24 minutes = 11:00 AM` (original remaining time)
   - Expected: `attempt_number` remains `1` (NOT incremented)
   - Expected: Previous answer to Question 1 is still there
   - Expected: Student continues from Question 1 or next unanswered

4. **Verify attempt count**
   - Query: `SELECT COUNT(*) FROM exam_attempts WHERE student_id = X AND exam_id = Y AND status IN ('submitted', 'terminated', 'suspicious', 'rejected')`
   - Expected: `0` (because status is `in_progress`)
   - Expected: Student can still use their 2 attempts

### SQL Verification:
```sql
-- Check attempt details
SELECT id, attempt_number, status, started_at, expires_at, disconnected_at, last_question_id
FROM exam_attempts 
WHERE student_id = [STUDENT_ID] AND exam_id = [EXAM_ID];

-- Check saved answers
SELECT question_id, answer_id, answer_text, created_at, updated_at
FROM student_answers
WHERE attempt_id = [ATTEMPT_ID];

-- Check recovery log
SELECT disconnect_reason, disconnected_at, reconnected_at, recovery_status,
       disconnected_duration_seconds
FROM session_recovery_logs
WHERE attempt_id = [ATTEMPT_ID]
ORDER BY disconnected_at DESC;
```

---

## Test Case 2: Timer Calculation with Schedule Constraint

### Setup:
- Exam Schedule: 10:00 AM - 12:00 PM
- Exam Duration: 30 minutes
- Student starts: 10:30 AM
- Student disconnects: 10:45 AM (used 15 minutes, 15 minutes remaining)
- Student reconnects: 11:55 AM (very late!)

### Test Steps:

1. **Student reconnects at 11:55 AM**
   - Remaining exam duration: 15 minutes (based on original expires_at = 11:00 AM would be negative, so use 0)
   - Remaining schedule time: 5 minutes (12:00 PM - 11:55 AM)
   - Expected: Final available time = `MIN(0, 5) = 0` minutes
   - Expected: Recovery FAILS with message "exam session has expired"
   - Alternative: If system allows negative time as 0, then student might get 5 minutes
   
2. **Better test: Student reconnects at 11:50 AM**
   - Original `expires_at` was 11:00 AM (30 min from 10:30 AM)
   - Time used before disconnect: 15 minutes (10:30 - 10:45)
   - Remaining exam duration based on `expires_at`: Would be negative (11:00 AM has passed)
   - Remaining schedule time: 10 minutes (12:00 PM - 11:50 AM)
   - Expected: Recovery might fail due to expired `expires_at`

### Corrected Test:
Let's use a scenario where student returns within exam time but schedule is limiting:

- Exam Schedule: 10:00 AM - 11:00 AM (shorter schedule)
- Exam Duration: 30 minutes
- Student starts: 10:30 AM
- Original expires_at: 11:00 AM (10:30 + 30 min)
- Student disconnects: 10:40 AM (used 10 minutes, 20 minutes remaining)
- Student reconnects: 10:50 AM

### Expected:
- Remaining exam time: 20 minutes (would end at 11:10 AM)
- Remaining schedule time: 10 minutes (ends at 11:00 AM)
- Final available time: `MIN(20, 10) = 10 minutes`
- New `expires_at = 10:50 AM + 10 minutes = 11:00 AM`
- Message should warn: "The exam schedule ends at 11:00 AM, so your available time is limited by the schedule."

---

## Test Case 3: Recovery Window Expiry

### Setup:
- Exam Schedule: 10:00 AM - 12:00 PM
- Exam Duration: 30 minutes
- Recovery window: 10 minutes (from config)

### Test Steps:

1. **Student starts at 10:30 AM, disconnects at 10:35 AM**
   - Status: `terminated_pending_review`
   - `disconnected_at = 10:35 AM`

2. **Student reconnects at 10:46 AM (11 minutes later)**
   - Expected: Recovery FAILS
   - Expected: Message "recovery window (10 minutes) has passed"
   - Expected: Recovery log updated with `recovery_status = 'expired'`
   - Expected: `reconnected_at` is recorded
   - Expected: Student must contact instructor

### SQL Verification:
```sql
SELECT recovery_status, disconnected_at, reconnected_at, 
       disconnected_duration_seconds
FROM session_recovery_logs
WHERE attempt_id = [ATTEMPT_ID];

-- Should show:
-- recovery_status = 'expired'
-- disconnected_duration_seconds = 660 (11 minutes)
```

---

## Test Case 4: Attempt Limit with Recovery

### Setup:
- Exam Schedule: 10:00 AM - 12:00 PM
- Attempt Limit: 2

### Test Steps:

1. **Attempt 1: Student completes exam**
   - Student submits normally
   - Status: `submitted`
   - Used attempts: 1

2. **Attempt 2: Student starts and disconnects**
   - Student starts second attempt
   - Student disconnects
   - Status: `terminated_pending_review`
   - Check: Used attempts query should return 1 (only first submitted counts)

3. **Student recovers Attempt 2**
   - Recovery successful
   - Status: `in_progress`
   - Still on attempt 2 (same attempt_number)
   - Check: Used attempts = 1

4. **Student submits Attempt 2**
   - Status: `submitted`
   - Check: Used attempts = 2 (now both count)
   - Check: Cannot start Attempt 3 (limit reached)

### Verification:
```sql
-- Check consumed attempts
SELECT COUNT(*) as consumed_attempts
FROM exam_attempts 
WHERE student_id = [STUDENT_ID] 
  AND exam_id = [EXAM_ID]
  AND status IN ('submitted', 'terminated', 'suspicious', 'rejected');

-- Should return 2 only after both are submitted
```

---

## Test Case 5: Anti-Cheat Warning During Recovery

### Setup:
- Exam with anti-cheat enabled
- Student has disconnected and recovered

### Test Steps:

1. **Student starts exam and disconnects**
   - Status: `terminated_pending_review`

2. **Student recovers successfully**
   - Status: `in_progress`
   - Warning count: 0 (from cheating_logs)

3. **Student triggers 3 anti-cheat warnings**
   - Warning 1: Tab switch
   - Warning 2: Exit fullscreen  
   - Warning 3: Right click
   - Expected: Status changes to `terminated`
   - Expected: This NOW counts as a consumed attempt
   - Check: `status = 'terminated'` (not `terminated_pending_review`)

4. **Verify attempt count**
   - Query consumed attempts
   - Expected: This terminated attempt counts (status = 'terminated')
   - Student lost this attempt due to cheating

### Key Point:
- `terminated_pending_review` = temporary disconnect (recoverable, doesn't count)
- `terminated` = cheating termination (permanent, counts as consumed attempt)

---

## Test Case 6: Answer Persistence After Recovery

### Setup:
- Exam with 10 questions (mixed types)

### Test Steps:

1. **Student starts exam**
   - Answers Question 1 (MCQ) - Select option B
   - Answers Question 2 (Fill blank) - Types "photosynthesis"
   - Answers Question 3 (Essay) - Types 100 words
   - Currently on Question 4

2. **Student disconnects at Question 4**
   - Status: `terminated_pending_review`
   - `last_question_id = 4`

3. **Student recovers**
   - Status: `in_progress`
   - Expected: Redirect to Question 4 (last position)
   
4. **Verify answers**
   - Question 1: Option B still selected
   - Question 2: "photosynthesis" still there
   - Question 3: Essay text still there
   - Questions 5-10: Empty (not answered yet)

5. **Student continues answering**
   - Answers Question 4
   - Expected: Auto-save works normally
   - Can navigate to previous questions and see saved answers

### SQL Verification:
```sql
SELECT q.id as question_id, q.type, 
       sa.answer_id, sa.answer_text, sa.updated_at
FROM questions q
LEFT JOIN student_answers sa ON sa.question_id = q.id 
  AND sa.attempt_id = [ATTEMPT_ID]
WHERE q.exam_id = [EXAM_ID]
ORDER BY q.id;

-- Should show:
-- Question 1: answer_id = [ID of Option B]
-- Question 2: answer_text = "photosynthesis"
-- Question 3: answer_text = [100-word essay]
-- Question 4+: NULL (not answered before disconnect)
```

---

## Test Case 7: Multiple Disconnects

### Setup:
- Student disconnects multiple times

### Test Steps:

1. **First disconnect at 10:35 AM**
   - Answers Question 1
   - Disconnects at Question 2
   - Recovers at 10:36 AM

2. **Second disconnect at 10:40 AM**
   - Answers Questions 2-3
   - Disconnects at Question 4
   - Recovers at 10:42 AM

3. **Third disconnect at 10:50 AM**
   - Answers Questions 4-5
   - Disconnects at Question 6
   - Recovers at 10:52 AM

4. **Verify:**
   - All answers from 1-5 are preserved
   - Only ONE attempt (same `attempt_number`)
   - Three entries in `session_recovery_logs`
   - Each recovery log has:
     - `recovery_status = 'recovered'`
     - Correct `disconnected_duration_seconds`

### SQL Verification:
```sql
SELECT id, disconnected_at, reconnected_at, 
       recovery_status, disconnected_duration_seconds,
       last_question_id
FROM session_recovery_logs
WHERE attempt_id = [ATTEMPT_ID]
ORDER BY disconnected_at ASC;

-- Should show 3 rows, all with recovery_status = 'recovered'
```

---

## Manual Testing Checklist

### Frontend Verification:

- [ ] Timer displays correctly after recovery
- [ ] Timer shows warning (red) when < 5 minutes
- [ ] Auto-submit occurs when timer reaches 0
- [ ] Auto-submit occurs when schedule ends
- [ ] Question navigator shows answered questions (green)
- [ ] Resume exam opens at last unanswered question
- [ ] All saved answers are visible after recovery
- [ ] Auto-save continues to work after recovery

### Backend Verification:

- [ ] Recovery API endpoint works (`POST /student/exam/{attempt}/disconnect`)
- [ ] Recovery log is created with correct data
- [ ] Attempt status changes correctly
- [ ] `expires_at` is recalculated properly
- [ ] Schedule end time is respected
- [ ] Attempt count query excludes `terminated_pending_review`
- [ ] Answers are not deleted during disconnect
- [ ] Same `attempt_id` is used (no new attempt created)

### Database Verification:

- [ ] `exam_attempts.status` transitions correctly
- [ ] `exam_attempts.disconnected_at` is set
- [ ] `exam_attempts.last_question_id` is set
- [ ] `exam_attempts.expires_at` is updated on recovery
- [ ] `session_recovery_logs` table has entries
- [ ] `student_answers` are preserved
- [ ] Recovery logs have correct timestamps

---

## Edge Cases

### Edge Case 1: Disconnect at last second
- Disconnect with 1 second remaining
- Recovery within window
- Should get minimal time (maybe 0-1 minutes)

### Edge Case 2: Schedule ends during disconnect
- Disconnect at 10:55 AM
- Schedule ends at 11:00 AM
- Recover at 11:05 AM
- Should fail (schedule ended)

### Edge Case 3: Browser crash during answer saving
- Student typing answer
- Browser crashes before auto-save
- Last saved state should be restored
- Partial answer may be lost if not auto-saved yet

### Edge Case 4: Multiple tabs
- Student opens exam in two tabs
- One tab disconnects
- Other tab continues
- Should be handled by session token validation

---

## Performance Testing

### Load Test:
- 100 students taking exam simultaneously
- 20% disconnect and reconnect
- Verify:
  - Recovery API response time < 500ms
  - Database queries are optimized
  - No deadlocks or race conditions

---

## Rollback Plan

If issues occur, the changes can be reverted by:

1. Restoring the 5 modified files from git
2. Running migration rollback if database changes were made (none in this case)
3. Clearing Laravel cache: `php artisan cache:clear`

---

## Configuration

### Recovery Time Limit:
Located in `config/exam_security.php`:
```php
'recovery_time_limit' => 600, // seconds (10 minutes)
```

To change recovery window, modify this value and restart the application.

---

## Success Criteria

✅ **The implementation is successful if:**

1. `terminated_pending_review` attempts do NOT count toward attempt limit
2. Same `attempt_id` is restored on recovery (no new attempt created)
3. All saved answers persist after recovery
4. Timer calculation respects MIN(remaining exam time, remaining schedule time)
5. Student continues from last question after recovery
6. Recovery fails gracefully if window expires
7. Schedule end time forces auto-submit
8. Multiple disconnects are handled correctly
9. Anti-cheat warnings still work after recovery
10. Submitted/terminated attempts still count correctly

---

## Notes

- Session recovery is for **temporary interruptions** (network issues, browser close)
- Cheating termination (3 warnings) is **permanent** and counts as an attempt
- Recovery window is configurable (default: 10 minutes)
- Schedule end time always takes precedence over exam duration
