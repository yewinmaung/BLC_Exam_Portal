# Phase 2: Teacher Re-Attempt Request - Quick Reference

## 🎯 **What Was Done?**

Teachers can now select eligible students and submit re-attempt requests to Admin for approval.

---

## 📍 **Changed Files**

### **2 Files Modified**

1. ✅ `app/Http/Controllers/Teacher/ExamController.php`
   - Enhanced `results()` method (added filters, search, incomplete students)
   - Enhanced `reattemptStore()` method (added bulk support)

2. ✅ `resources/views/teacher/exams/results.blade.php`
   - Added filter dropdown
   - Added search box
   - Added multi-select checkboxes
   - Added bulk submission modal
   - Added JavaScript for selection management

---

## 🔐 **Eligibility Rules**

### **Eligible Students (Can Request)**
- ✅ Students who failed honestly
- ✅ Students who didn't complete the exam
- ✅ Students with technical issues

### **Ineligible Students (Cannot Request)**
- ❌ Students with security violations
- ❌ Terminated attempts
- ❌ Cheating cases
- ❌ Already have pending request
- ❌ Already have approved re-attempt
- ❌ Passed students

---

## 🚀 **How to Use**

### **As Teacher: Submit Re-Attempt Request**

1. **Navigate to Results**
   ```
   Teacher Dashboard → My Exams → Select Exam → Results Tab
   ```

2. **Filter Students** (Optional)
   - Select "Eligible for Re-Attempt" from dropdown
   - This shows only students who can be requested

3. **Select Students**
   - **Single**: Click checkbox next to one student
   - **Multiple**: Click multiple checkboxes
   - **All**: Click "Select All" header checkbox

4. **Submit Request**
   - Click "Submit Re-Attempt Request (X)" button
   - Enter reason in modal
   - Click "Send to Admin"

5. **View Request Status**
   - Navigate to "Re-Attempts" page
   - See all pending requests

---

## 📊 **Filter Options**

| Filter | Shows | Use Case |
|--------|-------|----------|
| **All Students** | Everyone with results | General overview |
| **Failed Students** | Failed (no cheating) | Find honest failures |
| **Incomplete** | Enrolled but no attempt | Students who missed exam |
| **Eligible** | Failed + incomplete (no cheating) | **Best for re-attempts** |
| **Requested** | Already have pending/approved request | Track request status |

---

## ✅ **What Works**

| Feature | Status | Notes |
|---------|--------|-------|
| View results | ✅ | All students with scores |
| Filter students | ✅ | 5 filter types |
| Search students | ✅ | By name or email |
| Select single | ✅ | One checkbox |
| Select multiple | ✅ | Many checkboxes |
| Select all | ✅ | Header checkbox |
| Clear selection | ✅ | Reset button |
| Disabled checkboxes | ✅ | For ineligible students |
| Bulk submission | ✅ | Up to all eligible |
| Duplicate prevention | ✅ | Cannot request twice |
| Validation | ✅ | Reason required |
| Success message | ✅ | Confirmation shown |

---

## 🔒 **Teacher Permissions**

### **CAN Do:**
- ✅ View exam results
- ✅ Filter and search students
- ✅ Select eligible students
- ✅ Submit re-attempt requests
- ✅ View request status

### **CANNOT Do:**
- ❌ Approve requests (Admin only)
- ❌ Reject requests (Admin only)
- ❌ Schedule re-attempts (Future phase)
- ❌ Create exam attempts (Future phase)
- ❌ Modify approved requests

---

## 🐛 **Troubleshooting**

### **Problem: Checkbox is disabled**

**Possible reasons:**
1. Student passed the exam
2. Student has pending/approved request
3. Student has security violation
4. Student was terminated for cheating

**Solution**: Hover over disabled checkbox to see tooltip with reason.

---

### **Problem: "Submit Request" button not visible**

**Cause**: No students selected

**Solution**: Select at least one eligible student checkbox.

---

### **Problem: Request submission fails**

**Check:**
1. Reason field is filled
2. At least one student selected
3. Students don't already have pending requests
4. Teacher owns the exam

---

## 📋 **Request Workflow**

```
Teacher Action             →  System Response
─────────────────────────────────────────────────────
1. Open Results            →  Show all students
2. Apply Filter            →  Filter to eligible
3. Select Students         →  Enable bulk action
4. Click Submit Button     →  Open modal
5. Enter Reason            →  Validate input
6. Submit                  →  Create pending requests
7. Redirect                →  Show requests page
8. [Wait for Admin]        →  Status: Pending
```

---

## 💻 **Code Snippets**

### **Check Eligibility (in View)**
```php
@php
$isEligible = false;
$hasCheating = $r->attempt && in_array($r->attempt->status, ['terminated', 'suspicious', 'terminated_pending_review']);
$hasRequest = \App\Models\ReAttemptRequest::where('exam_id', $exam->id)
    ->where('student_id', $r->student->id)
    ->whereIn('status', ['pending', 'approved'])
    ->exists();

if (!$hasRequest && !$hasCheating && !$r->is_passed) {
    $isEligible = true;
}
@endphp
```

### **Submit Bulk Request (JavaScript)**
```javascript
function submitBulkRequest() {
    const reason = document.getElementById('bulkReason').value.trim();
    if (!reason) {
        alert('Please provide a reason.');
        return;
    }
    document.getElementById('bulkReattemptForm').submit();
}
```

---

## 📊 **Database Queries**

### **Get Eligible Students**
```php
// Failed students (no cheating)
$results->where('is_passed', false)
    ->whereDoesntHave('attempt', function($q) {
        $q->whereIn('status', ['terminated', 'suspicious', 'terminated_pending_review']);
    });

// Incomplete students
$enrolledIds = $exam->course->enrollments()->pluck('student_id');
$completedIds = $exam->results()->pluck('student_id');
$incompleteIds = $enrolledIds->diff($completedIds);
```

### **Check for Existing Request**
```php
$hasRequest = ReAttemptRequest::where('student_id', $studentId)
    ->where('exam_id', $examId)
    ->whereIn('status', ['pending', 'approved'])
    ->exists();
```

---

## 🎨 **UI Elements**

### **Selection Counter**
```html
<span id="selectedCount" class="badge bg-primary">
    <span id="selectedCountText">0</span> selected
</span>
```

### **Eligibility Badge**
```html
<!-- Eligible -->
<span class="badge bg-success">
    <i class="bi bi-check-circle"></i> Eligible
</span>

<!-- Already Requested -->
<span class="status-pill status-pending">Requested</span>

<!-- Not Eligible -->
<span class="text-muted">—</span>
```

---

## 🧪 **Quick Test**

### **Test Bulk Request**

1. **Setup**:
   - Create exam with 3 students
   - Students A & B fail, Student C passes

2. **Action**:
   - Filter: "Eligible for Re-Attempt"
   - Select: Student A & B checkboxes
   - Submit: Enter reason, click send

3. **Expected**:
   - 2 pending requests created
   - Redirect to re-attempts page
   - Success message shown
   - Requests visible with "Pending" status

---

## 📞 **Quick Help**

### **Key Routes**
- **View Results**: `/teacher/exams/{exam}/results`
- **Submit Request**: `POST /teacher/reattempts` (route: `teacher.reattempts.store`)
- **View Requests**: `/teacher/reattempts` (route: `teacher.reattempts.index`)

### **Key Methods**
- **Controller**: `Teacher\ExamController@results`
- **Controller**: `Teacher\ExamController@reattemptStore`
- **View**: `teacher.exams.results`

### **Key Models**
- `ReAttemptRequest` - Stores request data
- `Result` - Student exam results
- `ExamAttempt` - Check for violations
- `Enrollment` - Find incomplete students

---

## ✨ **Summary**

**What**: Teacher submits re-attempt requests for eligible students  
**Where**: Exam results page with filters and multi-select  
**How**: Select students, enter reason, submit to admin  
**Why**: Allow teachers to help students who failed honestly  

**Result**: ✅ Working, secure, user-friendly, tested

---

**Version**: Phase 2  
**Date**: 2026-07-12  
**Status**: Production Ready  
**Next**: Phase 3 - Admin Approval Workflow
