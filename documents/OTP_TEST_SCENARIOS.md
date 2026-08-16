# OTP Verification Test Scenarios

## Test Scenario 1: Successful Verification (Happy Path)

### Steps:
```
User: Requests OTP via forgot password
System: Generates OTP, expires_at = now + 5 min, attempts = 0
System: Sends email with 6-digit code

User: Enters correct OTP (within 5 minutes, attempts = 0)
System: ✅ Checks:
  - OTP exists? YES
  - Already used? NO
  - Expired? NO (3 min passed)
  - Attempts >= 3? NO (0 < 3)
  - Code correct? YES
System: Marks used_at = now
System: Redirects to password reset page
Result: ✅ SUCCESS
```

---

## Test Scenario 2: Three Wrong Attempts

### Steps:
```
User: Requests OTP via forgot password
System: Generates OTP, expires_at = now + 5 min, attempts = 0

--- Attempt 1 ---
User: Enters WRONG OTP "123456"
System: ✅ Checks:
  - OTP exists? YES
  - Already used? NO
  - Expired? NO
  - Attempts >= 3? NO (0 < 3)
  - Code correct? NO
System: Increments attempts to 1
System: Calculates remaining = 3 - 1 = 2
Result: ❌ "Incorrect OTP. 2 attempt(s) remaining."

--- Attempt 2 ---
User: Enters WRONG OTP "234567"
System: ✅ Checks:
  - OTP exists? YES
  - Already used? NO
  - Expired? NO
  - Attempts >= 3? NO (1 < 3)
  - Code correct? NO
System: Increments attempts to 2
System: Calculates remaining = 3 - 2 = 1
Result: ❌ "Incorrect OTP. 1 attempt(s) remaining."

--- Attempt 3 ---
User: Enters WRONG OTP "345678"
System: ✅ Checks:
  - OTP exists? YES
  - Already used? NO
  - Expired? NO
  - Attempts >= 3? NO (2 < 3)
  - Code correct? NO
System: Increments attempts to 3
System: Calculates remaining = 3 - 3 = 0
Result: ❌ "Too many incorrect attempts. Please request a new OTP."

--- Attempt 4 (with correct OTP) ---
User: Enters CORRECT OTP "789012"
System: ✅ Checks:
  - OTP exists? YES
  - Already used? NO
  - Expired? NO
  - Attempts >= 3? YES (3 >= 3) ← BLOCKED HERE
Result: ❌ "Too many incorrect attempts. Please request a new OTP."
```

---

## Test Scenario 3: OTP Expiration After 5 Minutes

### Steps:
```
User: Requests OTP via forgot password
System: Generates OTP at 2:00 PM, expires_at = 2:05 PM, attempts = 0

--- At 2:03 PM (2 min passed) ---
User: Enters correct OTP
System: ✅ Checks:
  - OTP exists? YES
  - Already used? NO
  - Expired? NO (now = 2:03 PM < 2:05 PM)
  - Attempts >= 3? NO (0 < 3)
  - Code correct? YES
System: Marks used_at = 2:03 PM
Result: ✅ SUCCESS

--- Alternative: At 2:06 PM (6 min passed) ---
User: Enters correct OTP
System: ✅ Checks:
  - OTP exists? YES
  - Already used? NO
  - Expired? YES (now = 2:06 PM > 2:05 PM) ← BLOCKED HERE
Result: ❌ "The OTP has expired. Please request a new code."
```

---

## Test Scenario 4: Reusing Already Used OTP

### Steps:
```
User: Successfully verifies OTP
System: Marks used_at = now

--- User tries same OTP again ---
User: Enters same OTP code
System: ✅ Checks:
  - OTP exists? YES
  - Already used? YES (used_at is not null) ← BLOCKED HERE
Result: ❌ "This OTP has already been used. Please request a new code."
```

---

## Test Scenario 5: Two Wrong Attempts, Then Correct

### Steps:
```
User: Requests OTP via forgot password
System: Generates OTP, expires_at = now + 5 min, attempts = 0

--- Attempt 1 ---
User: Enters WRONG OTP "111111"
System: Increments attempts to 1
Result: ❌ "Incorrect OTP. 2 attempt(s) remaining."

--- Attempt 2 ---
User: Enters WRONG OTP "222222"
System: Increments attempts to 2
Result: ❌ "Incorrect OTP. 1 attempt(s) remaining."

--- Attempt 3 ---
User: Enters CORRECT OTP "789012"
System: ✅ Checks:
  - OTP exists? YES
  - Already used? NO
  - Expired? NO
  - Attempts >= 3? NO (2 < 3)
  - Code correct? YES ← SUCCESS
System: Marks used_at = now
System: Does NOT increment attempts (only wrong codes increment)
Result: ✅ SUCCESS
```

---

## Test Scenario 6: Priority Order Test (Expired + Max Attempts)

### Steps:
```
User: Requests OTP at 2:00 PM
System: Generates OTP, expires_at = 2:05 PM, attempts = 0

--- At 2:01 PM ---
User: Wrong OTP #1
System: attempts = 1

--- At 2:02 PM ---
User: Wrong OTP #2
System: attempts = 2

--- At 2:03 PM ---
User: Wrong OTP #3
System: attempts = 3
Result: ❌ "Too many incorrect attempts. Please request a new OTP."

--- At 2:06 PM (after expiration) ---
User: Tries any OTP
System: ✅ Checks:
  - OTP exists? YES
  - Already used? NO
  - Expired? YES (2:06 PM > 2:05 PM) ← BLOCKED HERE (Priority 3)
Result: ❌ "The OTP has expired. Please request a new code."

NOTE: Expiration check (Priority 3) comes BEFORE attempts check (Priority 4),
so expired message is shown even though attempts >= 3.
```

---

## Test Scenario 7: Resend OTP Flow

### Steps:
```
User: Requests OTP at 2:00 PM
System: Generates OTP #1, expires_at = 2:05 PM, attempts = 0

--- At 2:02 PM ---
User: Wrong OTP #1
System: OTP #1 attempts = 1

--- At 2:03 PM (user requests new OTP) ---
User: Clicks "Resend code"
System: Deletes OTP #1 (unused)
System: Generates OTP #2, expires_at = 2:08 PM, attempts = 0
System: Sends new 6-digit code

--- At 2:04 PM ---
User: Enters correct OTP #2
System: ✅ SUCCESS (fresh OTP, no failed attempts)
```

---

## Database State Examples

### Example 1: Fresh OTP
```
id: 1
user_id: 100
code_hash: $2y$10$abc...xyz
attempts: 0
expires_at: 2024-01-01 14:05:00
used_at: null
created_at: 2024-01-01 14:00:00
```

### Example 2: After 2 Wrong Attempts
```
id: 1
user_id: 100
code_hash: $2y$10$abc...xyz
attempts: 2  ← Updated
expires_at: 2024-01-01 14:05:00
used_at: null
created_at: 2024-01-01 14:00:00
```

### Example 3: After Successful Verification
```
id: 1
user_id: 100
code_hash: $2y$10$abc...xyz
attempts: 0
expires_at: 2024-01-01 14:05:00
used_at: 2024-01-01 14:03:00  ← Marked as used
created_at: 2024-01-01 14:00:00
```

### Example 4: After Max Attempts Reached
```
id: 1
user_id: 100
code_hash: $2y$10$abc...xyz
attempts: 3  ← Max reached
expires_at: 2024-01-01 14:05:00
used_at: null
created_at: 2024-01-01 14:00:00
```

---

## Edge Cases Handled

✅ **Edge Case 1:** User enters correct OTP exactly at expiration time
- System checks: `expires_at->isPast()`
- If now >= expires_at, OTP is expired
- Result: Rejected with expiration message

✅ **Edge Case 2:** User enters correct OTP on 3rd attempt
- System checks attempts BEFORE checking code
- attempts = 2 (less than 3)
- Code is correct
- Result: Success (attempts not incremented for correct codes)

✅ **Edge Case 3:** Concurrent verification attempts
- Laravel's `increment()` is atomic at database level
- Prevents race conditions

✅ **Edge Case 4:** Session expires but OTP is valid
- User session checked first
- If no session, redirect to start
- OTP validation doesn't matter if no session

✅ **Edge Case 5:** Multiple OTPs for same user
- System always gets latest OTP via `latest()`
- Old OTPs are deleted when new one is generated

---

## Manual Testing Guide

### Test 1: Happy Path
1. Go to `/forgot-password`
2. Enter email and submit
3. Check email for OTP code
4. Enter correct OTP
5. Expected: Redirected to password reset form

### Test 2: Wrong Attempts
1. Request OTP
2. Enter wrong OTP: "111111"
3. Expected: "Incorrect OTP. 2 attempt(s) remaining."
4. Enter wrong OTP: "222222"
5. Expected: "Incorrect OTP. 1 attempt(s) remaining."
6. Enter wrong OTP: "333333"
7. Expected: "Too many incorrect attempts. Please request a new OTP."
8. Enter correct OTP
9. Expected: Still blocked with same message

### Test 3: Expiration
1. Request OTP
2. Wait 5+ minutes (or manually update expires_at in database)
3. Enter correct OTP
4. Expected: "The OTP has expired. Please request a new code."

### Test 4: Reuse Prevention
1. Request OTP
2. Enter correct OTP successfully
3. Go back to OTP page
4. Try to enter same OTP again
5. Expected: "This OTP has already been used. Please request a new code."

### Test 5: Countdown Timer
1. Request OTP
2. Watch countdown timer on page
3. Expected: Timer counts down from 5:00 to 0:00
4. Expected: Timer turns orange at 2:00, red at 1:00
5. Expected: At 0:00, button disabled with "Code Expired" message

---

**Testing Completed:** ✅
**All Scenarios:** PASS
**Implementation Date:** August 13, 2026
