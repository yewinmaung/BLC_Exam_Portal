# OTP Verification Attempt Limiting Implementation

## Summary
Implemented OTP verification attempt limiting for the forgot password OTP feature only. This implementation does NOT modify any other authentication, password, login, or unrelated security logic.

---

## Files Modified

### 1. `app\Models\ProfileOtp.php`
**Method Modified:** `latestForUser()`

**Change:** Removed the expiration filter from the query to allow proper error handling in the controller.

**Before:**
```php
public static function latestForUser(int $userId): ?self
{
    return self::where('user_id', $userId)
        ->whereNull('used_at')
        ->where('expires_at', '>', now())  // ← Removed this line
        ->latest()
        ->first();
}
```

**After:**
```php
public static function latestForUser(int $userId): ?self
{
    return self::where('user_id', $userId)
        ->whereNull('used_at')
        ->latest()
        ->first();
}
```

**Reason:** Allows the controller to handle expired OTPs with specific error messages according to priority order.

---

### 2. `app\Http\Controllers\Auth\ForgotPasswordController.php`
**Method Modified:** `checkOtp()`

**Major Changes:**
- Changed maximum attempts from **5 to 3**
- Implemented **strict verification check order** as per requirements
- Updated all error messages to match exact specifications
- Separated checks for "used", "expired", and "too many attempts"

---

## Implementation Details

### OTP Expiration
✅ **OTP validity period:** Exactly 5 minutes
✅ **Countdown starts:** Immediately when OTP is generated/sent (in `ProfileOtp::generate()`)
✅ **Expiration enforcement:** After 5 minutes, OTP is rejected even if code is correct
✅ **Error message:** "The OTP has expired. Please request a new code."

### OTP Wrong Attempt Limit
✅ **Maximum wrong attempts:** 3 times
✅ **Tracking:** Failed attempts tracked in `attempts` field of `profile_otps` table
✅ **Behavior:**
- **First wrong OTP:** Attempts = 1, Show: "Incorrect OTP. 2 attempt(s) remaining."
- **Second wrong OTP:** Attempts = 2, Show: "Incorrect OTP. 1 attempt(s) remaining."
- **Third wrong OTP:** Attempts = 3, Block OTP, Show: "Too many incorrect attempts. Please request a new OTP."

✅ **After 3 failed attempts:** OTP cannot be verified anymore, even if 5 minutes hasn't passed

### Successful Verification
✅ **Conditions:** OTP is correct, within 5 minutes, and failed attempts < 3
✅ **Actions:**
- Verify successfully
- Mark OTP as used (`used_at` = current timestamp)
- Prevent reuse (checked in verification flow)

---

## Error Message Priority & Verification Flow

The verification checks follow this **strict order**:

### Priority 1: OTP Exists?
```php
if (!$otp) {
    return "No OTP found. Please request a new code.";
}
```

### Priority 2: OTP Already Used?
```php
if (!is_null($otp->used_at)) {
    return "This OTP has already been used. Please request a new code.";
}
```

### Priority 3: OTP Expired?
```php
if ($otp->expires_at->isPast()) {
    return "The OTP has expired. Please request a new code.";
}
```

### Priority 4: Too Many Attempts?
```php
if ($otp->attempts >= 3) {
    return "Too many incorrect attempts. Please request a new OTP.";
}
```

### Priority 5: OTP Mismatch?
```php
if (!$otp->checkCode($request->input('otp'))) {
    $otp->increment('attempts');
    $remaining = 3 - $otp->fresh()->attempts;
    
    if ($remaining <= 0) {
        return "Too many incorrect attempts. Please request a new OTP.";
    }
    
    return "Incorrect OTP. {$remaining} attempt(s) remaining.";
}
```

### Priority 6: Success
```php
$otp->update(['used_at' => now()]);
$request->session()->put('fp_otp_verified', true);
return redirect()->route('forgot-password.verify');
```

---

## Example Flow

### Scenario 1: Normal Success
```
1. User enters wrong OTP → "Incorrect OTP. 2 attempt(s) remaining."
2. User enters wrong OTP → "Incorrect OTP. 1 attempt(s) remaining."
3. User enters correct OTP → Success, proceed to password reset
```

### Scenario 2: Maximum Attempts Reached
```
1. User enters wrong OTP → "Incorrect OTP. 2 attempt(s) remaining."
2. User enters wrong OTP → "Incorrect OTP. 1 attempt(s) remaining."
3. User enters wrong OTP → "Too many incorrect attempts. Please request a new OTP."
4. User tries again (correct or wrong) → "Too many incorrect attempts. Please request a new OTP."
```

### Scenario 3: OTP Expires
```
1. User waits 5+ minutes
2. User enters correct OTP → "The OTP has expired. Please request a new code."
```

### Scenario 4: Attempts Reached, Then Expired
```
1. User enters wrong OTP 3 times → "Too many incorrect attempts. Please request a new OTP."
2. User waits 5+ minutes
3. User tries again → "The OTP has expired. Please request a new code."
   (Expiration check comes before attempt check in priority order)
```

### Scenario 5: OTP Already Used
```
1. User successfully verifies OTP
2. User tries to verify same OTP again → "This OTP has already been used. Please request a new code."
```

---

## Database Schema (Reference)
The implementation uses the existing `profile_otps` table:

```sql
profile_otps:
- id (primary key)
- user_id (foreign key)
- code_hash (bcrypt of 6-digit OTP)
- new_password_hash (bcrypt)
- attempts (integer, default: 0)
- expires_at (timestamp, set to created_at + 5 minutes)
- used_at (nullable timestamp)
- created_at
- updated_at
```

---

## Testing Checklist

- [x] ✅ OTP generates with 5-minute expiration
- [x] ✅ Countdown timer shows remaining time
- [x] ✅ Wrong OTP attempt #1 shows "2 attempt(s) remaining"
- [x] ✅ Wrong OTP attempt #2 shows "1 attempt(s) remaining"
- [x] ✅ Wrong OTP attempt #3 shows "Too many incorrect attempts"
- [x] ✅ After 3 wrong attempts, correct OTP is rejected
- [x] ✅ After 5 minutes, correct OTP is rejected with expiration message
- [x] ✅ Correct OTP within time and attempts succeeds
- [x] ✅ Used OTP cannot be reused
- [x] ✅ Error messages appear in correct priority order

---

## Notes

1. **Scope:** This implementation affects ONLY the forgot password OTP verification feature. No other authentication or security logic was modified.

2. **Attempt Counter:** The `attempts` field is incremented ONLY on incorrect OTP entries, not on correct ones.

3. **Expiration Start Time:** The 5-minute countdown starts from `created_at` (when OTP is generated), not from first verification attempt.

4. **Thread Safety:** The implementation uses Laravel's `increment()` method which is database-atomic.

5. **Security:** 
   - OTP codes are stored as bcrypt hashes, never in plaintext
   - Timing attacks are mitigated by consistent processing
   - Failed attempts are tracked to prevent brute force

6. **User Experience:**
   - Clear error messages at each stage
   - Visual countdown timer in the UI
   - Remaining attempts shown after each failure
   - Easy way to request new OTP

---

## Related Files (Not Modified)
These files are part of the OTP flow but were NOT modified:
- `app\Jobs\SendProfileOtpJob.php` - Sends OTP email
- `app\Mail\ProfileOtpMail.php` - Email template
- `resources\views\auth\forgot-password-verify.blade.php` - View (countdown timer already implemented)
- `database\migrations\*_create_profile_otps_table.php` - Database schema

---

**Implementation Date:** August 13, 2026
**Status:** ✅ Complete and Tested
