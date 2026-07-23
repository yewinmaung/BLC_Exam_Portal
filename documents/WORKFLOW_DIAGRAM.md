# Exam Session Recovery Workflow Diagram

## Flow Chart: Exam Attempt Lifecycle

```
┌─────────────────────────────────────────────────────────────────────────┐
│                          STUDENT STARTS EXAM                            │
└────────────────────────────────┬────────────────────────────────────────┘
                                 │
                                 ▼
                    ┌────────────────────────┐
                    │  Create ExamAttempt    │
                    │  status: in_progress   │
                    │  attempt_number: N     │
                    │  expires_at: NOW + 30m │
                    └───────────┬────────────┘
                                │
                ┌───────────────┴───────────────┐
                │                               │
                ▼                               ▼
    ┌───────────────────────┐       ┌──────────────────────┐
    │  NORMAL CONTINUATION  │       │  DISCONNECT EVENT    │
    │  Student answers      │       │  (network/browser)   │
    │  questions normally   │       └──────────┬───────────┘
    └───────────┬───────────┘                  │
                │                              │
                │                              ▼
                │              ┌──────────────────────────────┐
                │              │  Status: in_progress →       │
                │              │  terminated_pending_review   │
                │              │  Set: disconnected_at        │
                │              │  Set: last_question_id       │
                │              └──────────┬───────────────────┘
                │                         │
                │                         │
                │         ┌───────────────┴────────────────┐
                │         │                                │
                │         ▼                                ▼
                │  ┌──────────────────┐        ┌────────────────────┐
                │  │ STUDENT RETURNS  │        │ RECOVERY WINDOW    │
                │  │ within 10 min    │        │ EXPIRES (>10 min)  │
                │  └────────┬─────────┘        └────────┬───────────┘
                │           │                           │
                │           ▼                           ▼
                │  ┌──────────────────┐        ┌────────────────────┐
                │  │ attemptRecovery  │        │  Recovery FAILS    │
                │  │ Check schedule   │        │  Show error msg    │
                │  │ Calculate time   │        │  Contact instructor│
                │  └────────┬─────────┘        └────────────────────┘
                │           │
                │           ▼
                │  ┌──────────────────────────────────┐
                │  │ Calculate Remaining Time:        │
                │  │ exam_time = expires_at - now     │
                │  │ sched_time = ends_at - now       │
                │  │ final = MIN(exam_time, sched)    │
                │  └────────┬─────────────────────────┘
                │           │
                │           ▼
                │  ┌──────────────────────────────────┐
                │  │ Recovery SUCCEEDS                │
                │  │ Status: → in_progress            │
                │  │ Update: expires_at = NOW + final │
                │  │ Preserve: All saved answers      │
                │  │ Resume: From last_question_id    │
                │  └────────┬─────────────────────────┘
                │           │
                └───────────┴──────────────┐
                                           │
                            ┌──────────────┴─────────────┐
                            │                            │
                            ▼                            ▼
                ┌───────────────────────┐    ┌──────────────────────┐
                │  STUDENT SUBMITS      │    │  ANTI-CHEAT TRIGGERS │
                │  (Button click)       │    │  (3 warnings)        │
                └───────────┬───────────┘    └──────────┬───────────┘
                            │                           │
                            ▼                           ▼
                ┌───────────────────────┐    ┌──────────────────────┐
                │  Status: submitted    │    │  Status: terminated  │
                │  submitted_at: NOW    │    │  terminated_at: NOW  │
                │  COUNTS AS ATTEMPT ✓  │    │  COUNTS AS ATTEMPT ✓ │
                └───────────────────────┘    └──────────────────────┘
```

---

## Timer Calculation Logic

```
┌─────────────────────────────────────────────────────────────────────────┐
│                     TIMER CALCULATION ON RECOVERY                       │
└─────────────────────────────────────────────────────────────────────────┘

Inputs:
  • started_at:     10:30 AM
  • expires_at:     11:00 AM (original: started_at + 30 minutes)
  • disconnected_at: 10:45 AM
  • reconnected_at: 10:50 AM
  • schedule.starts_at: 10:00 AM
  • schedule.ends_at:   12:00 PM

Step 1: Calculate remaining exam duration
  original_duration = 30 minutes
  time_used = disconnected_at - started_at = 15 minutes
  remaining_exam_time = expires_at - reconnected_at
                      = 11:00 AM - 10:50 AM = 10 minutes

Step 2: Calculate remaining schedule time
  remaining_schedule_time = schedule.ends_at - reconnected_at
                          = 12:00 PM - 10:50 AM = 70 minutes

Step 3: Apply MIN rule
  final_available_time = MIN(10 minutes, 70 minutes) = 10 minutes

Step 4: Calculate new expires_at
  new_expires_at = reconnected_at + final_available_time
                 = 10:50 AM + 10 minutes = 11:00 AM

Result:
  • Student gets 10 minutes (remaining exam time)
  • Schedule is not limiting in this case
  • Timer shows: 10:00 countdown

┌─────────────────────────────────────────────────────────────────────────┐
│                  SCHEDULE-LIMITED SCENARIO                              │
└─────────────────────────────────────────────────────────────────────────┘

Inputs:
  • started_at:     10:30 AM
  • expires_at:     11:00 AM (30 minutes)
  • disconnected_at: 10:40 AM
  • reconnected_at: 11:55 AM (very late!)
  • schedule.ends_at: 12:00 PM

Step 1: Calculate remaining exam duration
  remaining_exam_time = expires_at - reconnected_at
                      = 11:00 AM - 11:55 AM = -55 minutes (NEGATIVE!)
                      → max(0, -55) = 0 minutes

Step 2: Calculate remaining schedule time
  remaining_schedule_time = 12:00 PM - 11:55 AM = 5 minutes

Step 3: Apply MIN rule
  final_available_time = MIN(0 minutes, 5 minutes) = 0 minutes

Result:
  • Recovery FAILS
  • Message: "Your exam session has expired"
  • Student cannot continue

Alternative: Student reconnects at 11:52 AM
  remaining_exam_time = max(0, 11:00 - 11:52) = 0 minutes
  remaining_schedule_time = 12:00 - 11:52 = 8 minutes
  final_time = MIN(0, 8) = 0 minutes
  → Recovery FAILS (exam duration expired)
```

---

## Status Transition Matrix

```
┌───────────────────────────────────────────────────────────────────────────┐
│                          STATUS TRANSITIONS                               │
├───────────────────┬───────────────────────────────────────────────────────┤
│ FROM              │ TO                     │ TRIGGER                       │
├───────────────────┼───────────────────────┼───────────────────────────────┤
│ in_progress       │ submitted              │ Student clicks Submit         │
│ in_progress       │ terminated             │ 3 anti-cheat warnings         │
│ in_progress       │ terminated_pending_review │ Browser close/disconnect │
│ terminated_pending_review │ in_progress  │ Recovery within 10 minutes    │
│ terminated_pending_review │ [blocked]    │ Recovery after 10 minutes     │
└───────────────────┴───────────────────────┴───────────────────────────────┘
```

---

## Attempt Counting Decision Tree

```
                    ┌──────────────────────┐
                    │  Check Attempt Count │
                    └──────────┬───────────┘
                               │
         ┌─────────────────────┴─────────────────────┐
         │                                           │
         ▼                                           ▼
┌──────────────────────┐                  ┌──────────────────────┐
│ Query Database:      │                  │ Status Categories:   │
│ SELECT COUNT(*)      │                  │                      │
│ WHERE status IN (...) │                 │ ✓ submitted          │
└──────────┬───────────┘                  │ ✓ terminated         │
           │                              │ ✓ suspicious         │
           │                              │ ✓ rejected           │
           │                              │ ✗ in_progress        │
           │                              │ ✗ terminated_pending │
           │                              └──────────────────────┘
           │
           ▼
┌──────────────────────┐
│ Statuses that COUNT: │
│ • submitted          │ ← Student finished exam
│ • terminated         │ ← Terminated by cheating (3 warnings)
│ • suspicious         │ ← Flagged for review
│ • rejected           │ ← Rejected by admin after review
└──────────┬───────────┘
           │
           ▼
┌──────────────────────┐
│ Statuses that DON'T: │
│ • in_progress        │ ← Currently taking exam
│ • terminated_pending │ ← Temporarily disconnected, can recover
└──────────────────────┘

Example Scenario:
  Attempt 1: submitted (counts)
  Attempt 2: terminated_pending_review (doesn't count)
  Total consumed: 1
  Can start new: YES (if limit = 2 or 3)
```

---

## Recovery Window Timeline

```
Time:  10:30    10:35    10:40    10:45    10:50
       │        │        │        │        │
       ▼        ▼        ▼        ▼        ▼
       ╔════════╗
       ║ EXAM   ║
       ║ ACTIVE ║
       ╚════════╝─────── disconnected_at
                         │
                         │◄────────────────────────► 10 minutes window
                         │                          │
                         ▼                          ▼
                ┌────────────────────────┐  ┌───────────────┐
                │ RECOVERY ALLOWED       │  │ WINDOW CLOSED │
                │ Status: term_pending   │  │ Recovery FAILS│
                │ Can reconnect          │  │               │
                └────────────────────────┘  └───────────────┘
                         │
                         │
            ┌────────────┼────────────┐
            │            │            │
            ▼            ▼            ▼
       10:36 (1 min) 10:40 (5 min) 10:44 (9 min)
         ✓ Success     ✓ Success     ✓ Success
       
                                     ▼
                                  10:46 (11 min)
                                     ✗ FAIL
```

---

## Data Flow: Answer Persistence

```
┌─────────────────────────────────────────────────────────────────────────┐
│                    ANSWER AUTO-SAVE MECHANISM                           │
└─────────────────────────────────────────────────────────────────────────┘

Student Actions:
  1. Selects MCQ option B
  2. Types fill-in-blank answer
  3. Writes essay paragraph
                │
                ▼
        ┌───────────────┐
        │ Frontend JS   │
        │ Debounce:     │
        │ • MCQ: 0ms    │
        │ • Fill: 800ms │
        │ • Essay: 1.5s │
        └───────┬───────┘
                │
                ▼
        ┌───────────────┐
        │ AJAX POST     │
        │ /exam/save    │
        └───────┬───────┘
                │
                ▼
        ┌───────────────────────┐
        │ ExamSessionController │
        │ saveAnswer()          │
        └───────┬───────────────┘
                │
                ▼
        ┌─────────────────────────────┐
        │ StudentAnswer::updateOrCreate│
        │ (attempt_id, question_id)    │
        └───────┬─────────────────────┘
                │
                ▼
        ┌───────────────┐
        │ Database      │
        │ student_answers│
        │ • attempt_id  │
        │ • question_id │
        │ • answer_id   │ (for MCQ)
        │ • answer_text │ (for text)
        └───────────────┘

On Disconnect:
  ✓ All saved answers remain in database
  ✓ attempt_id stays the same
  ✓ No deletion occurs

On Recovery:
  ✓ Same attempt_id is restored
  ✓ Query: SELECT * FROM student_answers WHERE attempt_id = ?
  ✓ Frontend displays all saved answers
  ✓ Student continues from last position
```

---

## Multi-Disconnect Scenario

```
Timeline:
10:30                     10:50                     11:05
  │                         │                         │
  ▼                         ▼                         ▼
  START ──────► DISCONNECT 1 ──────► RECOVER ──────► DISCONNECT 2
  │             (10:35)      │        (10:36)         │ (10:55)
  │             Question 2   │        Resume Q2       │ Question 5
  │                          │                        │
  │ Q1: Answered             │ Q2-3: Answered         │ Q4-5: Answered
  │                          │                        │
                                                      ▼
                                              RECOVER (10:57)
                                              Resume Q6
                                              Q1-5: Still saved ✓
                                              Continue to Q10

Database State:
  exam_attempts:
    • id: 123
    • attempt_number: 1 (SAME throughout)
    • status: changes (in_progress ↔ terminated_pending_review)
    • disconnected_at: 10:55 (last disconnect)
    • last_question_id: 5 (last position)

  student_answers:
    • (123, Q1, answer_B)  ← Saved at 10:32
    • (123, Q2, "text")    ← Saved at 10:37
    • (123, Q3, answer_A)  ← Saved at 10:40
    • (123, Q4, "essay")   ← Saved at 10:52
    • (123, Q5, answer_C)  ← Saved at 10:54
    → All preserved through disconnects ✓

  session_recovery_logs:
    • Log 1: disconnect 10:35, recover 10:36, status: recovered
    • Log 2: disconnect 10:55, recover 10:57, status: recovered
    → Audit trail for admin ✓
```

---

## Edge Case: Schedule End During Disconnect

```
Scenario:
  Schedule: 10:00 AM - 11:00 AM
  Student starts: 10:30 AM (expires_at = 11:00 AM)
  Student disconnects: 10:45 AM
  Schedule ends: 11:00 AM ← SCHEDULE CLOSES
  Student reconnects: 11:05 AM ← TOO LATE

Timeline:
10:30       10:45          11:00         11:05
  │           │              │             │
  START    DISCONNECT    SCHEDULE END   RECONNECT
            (active)       (ended)      (attempt)
              │              │             │
              └──────────────┴─────────────┘
                    15 min      5 min
                                           │
                                           ▼
                                   ┌───────────────┐
                                   │ RECOVERY FAILS│
                                   │ Schedule ended│
                                   └───────────────┘

Recovery Logic:
  remaining_exam_time = 11:00 - 11:05 = -5 min → max(0, -5) = 0
  remaining_schedule_time = 11:00 - 11:05 = -5 min → max(0, -5) = 0
  final_time = MIN(0, 0) = 0 minutes
  
  Result: Recovery FAILS
  Message: "exam schedule or time limit has ended"
```

---

## Summary Flowchart

```
         START
           │
           ▼
    ┌──────────────┐
    │ Student      │
    │ Taking Exam  │
    └──────┬───────┘
           │
     ┌─────┴─────┐
     │           │
     ▼           ▼
   Normal    Disconnect ──────► terminated_pending_review
   Flow          │                      │
     │           │              ┌───────┴───────┐
     │           │              │               │
     ▼           │              ▼               ▼
  Submit      Recover      Recovery OK     Recovery FAIL
  or 3x       within       (MIN timer)     (window expired)
  Warnings    10 min           │               │
     │           │              │               │
     │           └──────► in_progress      [Blocked]
     │                         │
     │                         │
     └─────────────┬───────────┘
                   │
            ┌──────┴──────┐
            │             │
            ▼             ▼
        submitted    terminated
        (counts)     (counts)
```

---

## Key Takeaways

1. **Same Attempt ID** - Recovery uses the same attempt, no new record created
2. **MIN Rule** - Timer = MIN(remaining exam time, remaining schedule time)
3. **Answer Persistence** - All saved answers are preserved through disconnects
4. **10-Minute Window** - Student has 10 minutes to reconnect after disconnect
5. **Schedule Priority** - Exam cannot continue beyond schedule end time
6. **Attempt Counting** - Only final statuses (submitted, terminated, etc.) count
7. **Recovery Logs** - Full audit trail for admin review

