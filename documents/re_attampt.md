# RE-ATTEMPT BUSINESS RULES

A student may only take an exam if:

* Exam is active
* Attempt count is available

Default:
allowed_attempts = 1

If student fails/disqualified/missed exam:

Teacher may submit re-attempt request.

Rules:

* One pending request per student per exam
* Duplicate pending requests not allowed

Admin Approval:

When approved:

1. Increase allowed_attempts by 1
2. Create new exam_session
3. Set re_attempt_start_at
4. Set re_attempt_end_at
5. Notify student
6. Notify teacher
7. Create audit log

When rejected:

1. Keep exam locked
2. Store rejection reason
3. Notify student

Student Access Logic:

IF request_status = approved
AND current_time between re_attempt_start_at and re_attempt_end_at

THEN:
Show Start Exam button

ELSE:
Hide Start Exam button

Maximum attempts:
3

Audit logs required for:

* create
* approve
* reject
* schedule change
* exam access
