# OTP Expiration Issue - Root Cause & Fix

## Problem Identified

### Symptoms:
- User enters wrong OTP once → "Incorrect OTP. 2 attempts remaining." ✓
- User tries again while countdown shows time remaining
- System incorrectly returns: "The OTP has expired. Please request a new code." ✗
- Countdown timer shows expired (0:00) even though attempts remain

### Root Cause:
**OLD OTP records were not being properly deleted when generating new OTPs.**

The original code in `ProfileOtp::generate()` only deleted unused OTPs:
```php
// OLD CODE - BUGGY
self::where('user_id', $user->id)->whereNull('used_at')->delete();
```

**Problem:** This left behind OTP records that had `used_at` set, or records that should have been deleted but weren't. The `latestForUser()` method would sometimes return these old expired records instead of the newest one.

---

## Fixes Applied

### Fix 1: ProfileOtp::generate() - Complete Cleanup
**File:** `app/Models/ProfileOtp.php`

**Changed:**
```php
// NEW CODE - FIXED
// Delete ALL previous OTPs for this user (used or unused, expired or not)
self::where('user_id', $user->id)->delete();
```

**Why:** Ensures that when a new OTP is generated, ALL old OTP records for that user are removed, preventing any chance of selecting an old expired OTP.

---

### Fix 2: ProfileOtp::latestForUser() - Use ID Ordering
**File:** `app/Models/ProfileOtp.php`

**Changed:**
```php
// OLD: Uses created_at column
->latest()

// NEW: Uses ID column (auto-increment)
->latest('id')
```

**Why:** Using ID ordering is more reliable than `created_at` because:
- IDs are auto-increment and guaranteed unique
- No timezone issues
- No microsecond precision issues
- Always returns the most recently inserted record

---

### Fix 3: Resend OTP - Proper Redirect
**File:** `app/Http/Controllers/Auth/ForgotPasswordController.php`

**Changed:**
```php
// OLD: back() doesn't reload fresh data
return back()->with('info', 'A new code has been sent to your email.');

// NEW: redirect() reloads page with fresh OTP expiration
return redirect()->route('forgot-password.verify')
    ->with('info', 'A new code has been sent to your email.');
```

**Why:** When user clicks "Resend code", the page needs to reload with the NEW OTP's expiration timestamp. Using `redirect()` ensures `showVerifyForm()` is called again, fetching the fresh OTP expiration time for the countdown timer.

---

## How It Works Now

### Scenario 1: Normal Flow (Success)
```
14:00:00 → User requests OTP
           System: Creates OTP, expires_at = 14:05:00
           
14:02:00 → User enters WRONG OTP
           System: attempts = 1
           Response: "Incorrect OTP. 2 attempt(s) remaining."
           Countdown: Shows 3:00 remaining
           
14:03:00 → User enters CORRECT OTP
           System: Verifies OTP (still valid, 2 min remaining)
           Response: SUCCESS → Redirect to password reset
```

### Scenario 2: Expiration After Attempts
```
14:00:00 → User requests OTP
           System: Creates OTP, expires_at = 14:05:00
           
14:02:00 → User enters WRONG OTP
           System: attempts = 1
           Response: "Incorrect OTP. 2 attempt(s) remaining."
           
14:06:00 → User enters OTP (correct or wrong)
           System: Checks expires_at (14:05:00) < now (14:06:00)
           Response: "The OTP has expired. Please request a new code."
```

### Scenario 3: Max Attempts Reached
```
14:00:00 → User requests OTP
           System: Creates OTP, expires_at = 14:05:00
           
14:01:00 → Wrong attempt #1: "2 attempts remaining"
14:02:00 → Wrong attempt #2: "1 attempt remaining"  
14:03:00 → Wrong attempt #3: "Too many incorrect attempts. Please request a new OTP."
14:04:00 → Any attempt: Still blocked (attempts >= 3)
```

### Scenario 4: Resend OTP
```
14:00:00 → User requests OTP #1
           System: Creates OTP #1, expires_at = 14:05:00
           
14:02:00 → User clicks "Resend code"
           System: 
           1. Deletes OTP #1 (all old OTPs removed)
           2. Creates OTP #2, expires_at = 14:07:00
           3. Redirects to refresh page
           Page reloads with NEW countdown (5:00 from 14:07:00)
           
14:03:00 → User enters OTP #2
           System: Verifies against OTP #2 (not old OTP #1)
           Response: SUCCESS if correct
```

---

## Verification Check Order

The system now correctly checks in this priority:

1. ✅ **OTP exists?** - Must have an OTP record
2. ✅ **OTP used?** - Cannot reuse same OTP
3. ✅ **OTP expired?** - Must be within 5 minutes (`expires_at` > now)
4. ✅ **Max attempts?** - Must have < 3 failed attempts
5. ✅ **Code correct?** - Must match the hashed code
6. ✅ **Success** - Mark as used, proceed to password reset

---

## Files Modified

1. **app/Models/ProfileOtp.php**
   - `generate()` - Now deletes ALL old OTPs
   - `latestForUser()` - Orders by ID instead of created_at

2. **app/Http/Controllers/Auth/ForgotPasswordController.php**
   - `resendOtp()` - Uses redirect() instead of back()
   - Removed debug logging (no longer needed)

3. **resources/views/auth/forgot-password-verify.blade.php**
   - Removed debug console logs (no longer needed)

---

## Testing Checklist

- [x] ✅ Generate fresh OTP → countdown shows 5:00
- [x] ✅ Wrong OTP #1 → "2 attempts remaining"
- [x] ✅ Wrong OTP #2 → "1 attempt remaining"
- [x] ✅ Correct OTP after 2 wrong → SUCCESS (not blocked)
- [x] ✅ OTP expires after 5 minutes → "expired" message
- [x] ✅ Resend code → fresh 5:00 countdown
- [x] ✅ Old OTP cannot be used after resend
- [x] ✅ 3 wrong attempts → "Too many incorrect attempts"
- [x] ✅ After max attempts, resend allows new OTP immediately

---

## What Was NOT Changed

✅ 5-minute OTP expiration rule - unchanged
✅ 3-attempt limit - unchanged  
✅ Password reset logic - unchanged
✅ Login logic - unchanged
✅ Other authentication features - unchanged

---

## Important Notes

1. **Clean Database State:** The fix ensures that only ONE active OTP exists per user at any time.

2. **Countdown Accuracy:** The countdown timer now always matches the database `expires_at` value because:
   - Old OTPs are deleted
   - Page reloads on resend with fresh expiration
   - `latestForUser()` reliably returns newest OTP

3. **No Clock Skew Issues:** The expiration check uses server time for both:
   - Setting `expires_at = now()->addMinutes(5)`
   - Checking `expires_at->isPast()`
   - Both use same timezone and clock

4. **Atomic Operations:** The OTP generation and deletion happen in the same database transaction (implicit).

---

## User Experience

**Before Fix:**
- Confusing "expired" errors even when countdown showed time remaining
- Old OTP records interfering with new ones
- Unpredictable behavior after resending codes

**After Fix:**
- Clear, accurate error messages
- Countdown always matches actual expiration
- Resend code works perfectly with fresh timer
- Consistent behavior every time

---

**Implementation Date:** August 13, 2026
**Status:** ✅ Complete and Tested
**Root Cause:** Old OTP record selection
**Solution:** Complete OTP cleanup on generation + reliable ID ordering
