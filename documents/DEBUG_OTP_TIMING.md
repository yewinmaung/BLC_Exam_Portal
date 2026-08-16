# OTP Timing Debug Guide

## What I Added

### 1. Browser Console Logging
When you load the OTP verification page, open your browser console (F12 → Console tab).

You will see debug information like:
```
OTP Countdown Debug:
  Expires at (timestamp): 1786610000
  Expires at (date): Thu Aug 13 2026 15:00:00 GMT+0630
  Current time (timestamp): 1786609700
  Current time (date): Thu Aug 13 2026 14:55:00 GMT+0630
  Initial remaining seconds: 300
```

This will tell you:
- When the OTP expires (in both timestamp and human-readable date)
- What the browser thinks the current time is
- How many seconds remain initially

### 2. Server-Side Logging
When you submit an OTP, check the Laravel log file at:
```
storage/logs/laravel.log
```

Look for entries like:
```
[2026-08-13 14:55:00] local.INFO: OTP Verification Debug  
{
  "otp_id": 123,
  "created_at": "2026-08-13 14:50:00",
  "expires_at": "2026-08-13 14:55:00",
  "current_time": "2026-08-13 14:54:30",
  "expires_at_timestamp": 1786610100,
  "current_timestamp": 1786610070,
  "is_past": false,
  "seconds_remaining": 30,
  "attempts": 1,
  "used_at": null
}
```

This will tell you:
- When OTP was created
- When it expires
- Current server time
- Whether server thinks it's expired
- How many attempts have been made

## How to Test

### Test 1: Fresh OTP
1. Request a new OTP (click "Resend code")
2. Open browser console (F12)
3. Refresh the page
4. Check console output for initial remaining seconds
5. **Expected:** Should show around 300 seconds (5 minutes)

### Test 2: Wrong OTP with Time Remaining
1. Request a new OTP
2. Wait 1 minute (countdown shows 4:xx)
3. Enter a wrong OTP
4. Check both:
   - Browser console: Should show remaining time > 0
   - Laravel log: Should show `is_past: false` and `seconds_remaining` > 0
5. **Expected:** "Incorrect OTP. 2 attempt(s) remaining."

### Test 3: Verify Timing Sync
1. Request a new OTP
2. In browser console, note the "Expires at (timestamp)"
3. In browser console, note the "Current time (timestamp)"
4. Calculate: expires_at - current_time = remaining_seconds
5. Check if countdown timer matches this calculation

### Test 4: Check for Clock Skew
Compare these three times:
- **Your computer clock:** What time does your system show?
- **Browser console:** "Current time (date)" from debug output
- **Server log:** "current_time" from Laravel log

If these differ by more than a few seconds, you have a clock synchronization issue.

## Common Issues

### Issue 1: Browser Time != Server Time
**Symptom:** Countdown shows time remaining but server says expired

**Cause:** Your computer clock is ahead of server clock

**Example:**
- Server time: 14:50:00
- Browser time: 14:56:00
- OTP expires: 14:55:00 (server time)
- Browser thinks: Still 1 minute left
- Server thinks: Already expired

**Solution:** Sync your computer clock with internet time

### Issue 2: Timezone Mismatch
**Symptom:** Countdown is way off (hours difference)

**Cause:** Server timezone != Browser timezone

**Check:**
- Server timezone: `Asia/Yangon` (GMT+6:30)
- Browser timezone: Check console output

**Note:** This should NOT matter because we use Unix timestamps, which are timezone-agnostic

### Issue 3: Stale OTP Record
**Symptom:** Countdown shows negative time or starts at 0:00

**Cause:** You're looking at an old expired OTP

**Solution:** Click "Resend code" to generate fresh OTP

## What to Send Me

If you still have issues after testing, please send me:

1. **Browser Console Output:**
   ```
   Copy all lines starting with "OTP Countdown Debug:"
   ```

2. **Laravel Log Entry:**
   ```
   Copy the JSON from storage/logs/laravel.log
   Look for: "OTP Verification Debug"
   ```

3. **Screenshot showing:**
   - Your computer's system clock (bottom-right corner of Windows)
   - The countdown timer on the page
   - The error message you're seeing

4. **Timeline of events:**
   ```
   10:00 - Requested OTP
   10:02 - Entered wrong code → "2 attempts remaining"
   10:03 - Entered code again → Got "expired" error
   Countdown showed: X:XX at this time
   ```

This will help me identify if it's:
- Clock skew between browser and server
- Database timezone issue
- Logic error in expiration check
- Or something else

## Temporary Workaround

Until we fix the root cause, you can:
1. Request a new OTP
2. Enter it within 1-2 minutes (don't wait)
3. If you make a mistake, request new OTP immediately

This ensures you're always working with fresh OTPs that definitely haven't expired.
