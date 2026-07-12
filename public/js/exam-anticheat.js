(function () {
    'use strict';

    const body = document.getElementById('examBody');
    if (!body) return;

    const csrf         = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const saveUrl      = body.dataset.saveUrl;
    const violationUrl = body.dataset.violationUrl;
    const submitUrl    = body.dataset.submitUrl;
    const disconnectUrl = body.dataset.disconnectUrl;
    const endsAt       = parseInt(body.dataset.endsAt, 10) * 1000;

    // ── Security policy flags (set by server from DB settings) ────────────
    // Each flag gates whether its corresponding listener is registered.
    const policy = {
        fullscreen  : body.dataset.policyFullscreen  !== '0',
        blur        : body.dataset.policyBlur        !== '0',
        tabSwitch   : body.dataset.policyTabSwitch   !== '0',
        rightClick  : body.dataset.policyRightClick  !== '0',
        copy        : body.dataset.policyCopy        !== '0',
        paste       : body.dataset.policyPaste       !== '0',
        devtools    : body.dataset.policyDevtools    !== '0',
        keyboard    : body.dataset.policyKeyboard    !== '0',
    };

    let examStarted   = false;
    let examLocked    = false;   // set to true when lockExamInterface() is called
    let currentIndex  = 0;
    let isSubmitting  = false;   // flag to prevent disconnect recording during intentional submit

    const blocks       = Array.from(document.querySelectorAll('.question-block'));
    const navButtons   = Array.from(document.querySelectorAll('.q-nav-btn'));
    const progressFill = document.getElementById('progressFill');
    const progressText = document.getElementById('progressText');
    const warningBox   = document.getElementById('warningBox');
    const warningText  = document.getElementById('warningText');

    // Ensure each block has a question ID stored as data attribute
    blocks.forEach(block => {
        if (!block.dataset.questionId) {
            // Try to extract from the first input/textarea/radio in the block
            const input = block.querySelector('[data-question-id]');
            if (input) {
                block.dataset.questionId = input.dataset.questionId;
            }
        }
    });

    /* ════════════════════════════════════════
       Interval / timer handles
       All handles are stored here so lockExamInterface() can cancel every
       background process with a single pass.
    ════════════════════════════════════════ */
    const intervals = [];    // clearInterval handles
    const timeouts  = [];    // clearTimeout handles (debounces)

    function trackInterval(id) { intervals.push(id); return id; }
    function trackTimeout(id)  { timeouts.push(id);  return id; }

    /* ════════════════════════════════════════
       Fullscreen gate
    ════════════════════════════════════════ */
    const fsOverlay = document.getElementById('fsOverlay');

    // Check if this is a resumed exam (overlay is hidden)
    const isResume = fsOverlay && fsOverlay.style.display === 'none';

    if (isResume) {
        // Auto-start for resumed exams
        examStarted = true;
        // Try to enter fullscreen (will fail silently if not allowed)
        document.documentElement.requestFullscreen().catch(() => {});
    }

    document.getElementById('enterFullscreen')?.addEventListener('click', async () => {
        try {
            await document.documentElement.requestFullscreen();
        } catch (_e) {
            // Fullscreen may be blocked in some browsers — allow anyway.
        }
        examStarted = true;
        if (fsOverlay) fsOverlay.style.display = 'none';
    });

    /* ════════════════════════════════════════
       Anti-cheat detection
       All listeners defined as named references so they can be removed
       by lockExamInterface().
    ════════════════════════════════════════ */
    function onContextMenu(e) { e.preventDefault(); }

    function onCopyCutPaste(e) { e.preventDefault(); }

    function onKeydown(e) {
        if (
            e.key === 'F12' ||
            (e.ctrlKey && e.shiftKey && ['I', 'J', 'C'].includes(e.key)) ||
            (e.ctrlKey && e.key === 'u')
        ) {
            e.preventDefault();
            reportViolation('devtools_shortcut', 'DevTools shortcut blocked');
        }
    }

    function onVisibilityChange() {
        if (document.hidden) reportViolation('tab_switch', 'Tab switched');
    }

    function onWindowBlur() {
        if (examStarted) reportViolation('window_blur', 'Window lost focus');
    }

    function onFullscreenChange() {
        if (!document.fullscreenElement && examStarted) {
            reportViolation('fullscreen_exit', 'Exited fullscreen');
        }
    }

    if (policy.rightClick)  document.addEventListener('contextmenu',      onContextMenu);
    if (policy.copy)        document.addEventListener('copy',             onCopyCutPaste);
    if (policy.copy)        document.addEventListener('cut',              onCopyCutPaste);
    if (policy.paste)       document.addEventListener('paste',            onCopyCutPaste);
    if (policy.keyboard)    document.addEventListener('keydown',          onKeydown);
    if (policy.tabSwitch)   document.addEventListener('visibilitychange', onVisibilityChange);
    if (policy.blur)        window.addEventListener(  'blur',             onWindowBlur);
    if (policy.fullscreen)  document.addEventListener('fullscreenchange', onFullscreenChange);

    /* ════════════════════════════════════════
       Browser close / page unload detection
       Record temporary disconnect for auto-recovery
    ════════════════════════════════════════ */
    window.addEventListener('beforeunload', function (e) {
        // Only record disconnect if:
        // 1. Exam has started
        // 2. Exam is not locked (not already terminated)
        // 3. Not in the middle of an intentional submit
        if (examStarted && !examLocked && !isSubmitting && disconnectUrl) {
            // Get current question ID
            const currentQuestionId = blocks[currentIndex]?.dataset.questionId || null;

            // Use synchronous fetch with keepalive to ensure the request completes
            // even if the page is being unloaded
            navigator.sendBeacon(disconnectUrl, new Blob([JSON.stringify({
                question_id: currentQuestionId,
                reason: 'browser_close',
                _token: csrf
            })], { type: 'application/json' }));
        }
    });

    /* ════════════════════════════════════════
       Violation reporting
    ════════════════════════════════════════ */
    function reportViolation(type, details) {
        if (!examStarted || examLocked) return;

        fetch(violationUrl, {
            method:  'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept':       'application/json',
            },
            body: JSON.stringify({ type, details }),
        })
        .then(r => r.json())
        .then(handleViolationResponse)
        .catch(() => {});
    }

    function handleViolationResponse(data) {
        // Show the warning message for Tier 1 and Tier 2.
        if (warningBox && warningText && !examLocked) {
            warningText.textContent = data.message || 'Violation recorded.';
            warningBox.classList.add('show');
            const hideId = setTimeout(() => warningBox.classList.remove('show'), 5000);
            trackTimeout(hideId);
        }

        // Tier 3: lock exam immediately, then redirect after brief delay.
        if (data.terminated) {
            lockExamInterface(data.message);

            // Short delay so the overlay renders before redirect.
            setTimeout(() => {
                window.location.href = data.redirect || '/student/exams';
            }, 3000);
        }
    }

    /* ════════════════════════════════════════
       Full interface shutdown
       Called on Tier 3 termination.  After this function runs:
        - No further fetch() requests will be made.
        - All intervals and timeouts are cleared.
        - All detection event listeners are removed.
        - All answer inputs are disabled.
        - A lock overlay is displayed.
    ════════════════════════════════════════ */
    function lockExamInterface(message) {
        if (examLocked) return;   // idempotent guard
        examLocked  = true;
        examStarted = false;      // prevents any new reportViolation() calls

        const lockMessage = message || 'Your exam has been locked due to repeated security violations.';

        // ── 1. Stop all timers ─────────────────────────────────────────
        intervals.forEach(clearInterval);
        timeouts.forEach(clearTimeout);

        // ── 2. Remove all event listeners ─────────────────────────────
        if (policy.rightClick)  document.removeEventListener('contextmenu',      onContextMenu);
        if (policy.copy)        document.removeEventListener('copy',             onCopyCutPaste);
        if (policy.copy)        document.removeEventListener('cut',              onCopyCutPaste);
        if (policy.paste)       document.removeEventListener('paste',            onCopyCutPaste);
        if (policy.keyboard)    document.removeEventListener('keydown',          onKeydown);
        if (policy.tabSwitch)   document.removeEventListener('visibilitychange', onVisibilityChange);
        if (policy.blur)        window.removeEventListener(  'blur',             onWindowBlur);
        if (policy.fullscreen)  document.removeEventListener('fullscreenchange', onFullscreenChange);

        // ── 3. Disable all answer controls ────────────────────────────
        document.querySelectorAll(
            '.answer-input, .answer-blank, .answer-text, .mcq-option, ' +
            '.sidebar-submit-btn, #submitBtn, .btn-nav, .q-nav-btn'
        ).forEach(el => {
            el.disabled = true;
            el.style.pointerEvents = 'none';
            el.style.opacity = '0.45';
        });

        // ── 4. Exit fullscreen if active ──────────────────────────────
        if (document.fullscreenElement) {
            document.exitFullscreen().catch(() => {});
        }

        // ── 5. Show lock overlay ──────────────────────────────────────
        const overlay = document.createElement('div');
        overlay.id = 'examLockedOverlay';
        overlay.style.cssText = [
            'position:fixed', 'inset:0', 'z-index:9999',
            'background:rgba(7,29,64,0.97)',
            'display:flex', 'align-items:center', 'justify-content:center',
            'color:#fff', 'text-align:center', 'padding:2rem',
            'font-family:Inter,sans-serif',
        ].join(';');
        overlay.innerHTML = `
            <div style="max-width:480px">
                <div style="font-size:3rem;margin-bottom:1rem">🔒</div>
                <h3 style="font-size:1.4rem;font-weight:800;margin-bottom:.75rem">
                    Exam Session Locked
                </h3>
                <p style="font-size:.95rem;color:rgba(255,255,255,.8);line-height:1.6;margin-bottom:1.5rem">
                    ${lockMessage.replace(/\n/g, '<br>')}
                </p>
                <p style="font-size:.8rem;color:rgba(255,255,255,.5)">
                    Redirecting to exam list in a moment…
                </p>
            </div>`;
        document.body.appendChild(overlay);
    }

    /* ════════════════════════════════════════
       Answer saving
    ════════════════════════════════════════ */
    function saveAnswer(questionId, answerId, answerText) {
        if (examLocked) return;   // no requests after lock

        fetch(saveUrl, {
            method:  'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept':       'application/json',
            },
            body: JSON.stringify({
                question_id: questionId,
                answer_id:   answerId   || null,
                answer_text: answerText || null,
            }),
        }).catch(() => {});
    }

    // MCQ / True-False
    document.querySelectorAll('.mcq-option').forEach(label => {
        label.addEventListener('click', function () {
            if (examLocked) return;
            const radio = this.querySelector('input[type="radio"]');
            if (!radio) return;
            const qid = radio.dataset.questionId;
            document.querySelectorAll(`#options_${qid} .mcq-option`)
                    .forEach(l => l.classList.remove('selected'));
            this.classList.add('selected');
            radio.checked = true;
            saveAnswer(qid, radio.value, null);
            refreshNav();
        });
    });

    // Fill in the blank
    document.querySelectorAll('.answer-blank').forEach(input => {
        let dId;
        input.addEventListener('input', function () {
            if (examLocked) return;
            clearTimeout(dId);
            dId = trackTimeout(setTimeout(() => {
                saveAnswer(this.dataset.questionId, null, this.value.trim());
                refreshNav();
            }, 800));
        });
    });

    // Essay
    document.querySelectorAll('.answer-text').forEach(textarea => {
        let dId;
        textarea.addEventListener('input', function () {
            if (examLocked) return;
            clearTimeout(dId);
            dId = trackTimeout(setTimeout(() => {
                saveAnswer(this.dataset.questionId, null, this.value.trim());
                refreshNav();
            }, 1500));
        });
    });

    // Periodic MCQ auto-save — tracked so it stops on lock
    trackInterval(setInterval(() => {
        if (examLocked) return;
        document.querySelectorAll('.answer-input:checked').forEach(radio => {
            saveAnswer(radio.dataset.questionId, radio.value, null);
        });
    }, 10000));

    /* ════════════════════════════════════════
       Answered check
    ════════════════════════════════════════ */
    function isAnswered(block) {
        const type = block.dataset.type;
        if (type === 'mcq' || type === 'true_false') {
            return !!block.querySelector('.answer-input:checked');
        }
        if (type === 'fill_blank') {
            const inp = block.querySelector('.answer-blank');
            return inp && inp.value.trim().length > 0;
        }
        const ta = block.querySelector('.answer-text');
        return ta && ta.value.trim().length > 0;
    }

    /* ════════════════════════════════════════
       Navigation
    ════════════════════════════════════════ */
    function refreshNav() {
        let answered = 0;
        blocks.forEach((block, idx) => {
            const btn = navButtons[idx];
            if (!btn) return;
            const ans = isAnswered(block);
            if (ans) answered++;
            btn.classList.toggle('active',   idx === currentIndex);
            btn.classList.toggle('answered', ans);
        });
        const pct = blocks.length > 0
            ? Math.round((answered / blocks.length) * 100)
            : 0;
        if (progressFill) progressFill.style.width = pct + '%';
        if (progressText) progressText.textContent = `${answered} / ${blocks.length}`;
    }

    function showQuestion(index) {
        if (index < 0 || index >= blocks.length) return;
        currentIndex = index;
        blocks.forEach((b, i) => b.classList.toggle('active', i === index));
        blocks[index]?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        refreshNav();
    }

    navButtons.forEach((btn, idx) => btn.addEventListener('click', () => showQuestion(idx)));

    document.querySelectorAll('.prev-question').forEach(btn => {
        btn.addEventListener('click', () => showQuestion(currentIndex - 1));
    });
    document.querySelectorAll('.next-question').forEach(btn => {
        btn.addEventListener('click', () => showQuestion(currentIndex + 1));
    });

    /* ════════════════════════════════════════
       Timer  — tracked so lockExamInterface() stops it
    ════════════════════════════════════════ */
    const timerEl   = document.getElementById('timer');
    const timerText = document.getElementById('timerText');

    trackInterval(setInterval(() => {
        if (examLocked) return;

        const left = endsAt - Date.now();
        if (left <= 0) {
            // ── CRITICAL FIX: Stop cheating detection before auto-submit ──
            // Set examStarted to false so that exiting fullscreen during
            // auto-submission does NOT trigger a cheating violation.
            examStarted = false;
            isSubmitting = true;  // Prevent disconnect recording
            
            // Clear all intervals via lockExamInterface then auto-submit.
            intervals.forEach(clearInterval);
            if (timerText) timerText.textContent = '00:00';
            document.getElementById('examForm')?.submit();
            return;
        }
        const m = Math.floor(left / 60000);
        const s = Math.floor((left % 60000) / 1000);
        if (timerText) {
            timerText.textContent =
                `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
        }
        if (timerEl) timerEl.classList.toggle('warning', left < 300000);
    }, 1000));

    /* ════════════════════════════════════════
       Submit button
    ════════════════════════════════════════ */
    document.getElementById('submitBtn')?.addEventListener('click', () => {
        if (examLocked) return;
        const answered   = blocks.filter(b => isAnswered(b)).length;
        const unanswered = blocks.length - answered;
        const msg = unanswered > 0
            ? `You have ${unanswered} unanswered question(s). Submit anyway?`
            : 'Submit exam? This cannot be undone.';
        if (confirm(msg)) {
            // ── CRITICAL FIX: Stop cheating detection before submission ───
            // Set examStarted to false so that exiting fullscreen during
            // submission does NOT trigger a cheating violation.
            examStarted = false;
            isSubmitting = true;  // Prevent disconnect recording
            
            intervals.forEach(clearInterval);   // stop all background timers
            const form = document.getElementById('examForm');
            form.action = submitUrl;
            form.submit();
        }
    });

    /* ════════════════════════════════════════
       Init
    ════════════════════════════════════════ */
    // For resumed exams, start at first unanswered question
    // For new exams, start at question 0
    let startIndex = 0;
    if (isResume) {
        // Find first unanswered question
        for (let i = 0; i < blocks.length; i++) {
            if (!isAnswered(blocks[i])) {
                startIndex = i;
                break;
            }
        }
    }
    showQuestion(startIndex);
})();
