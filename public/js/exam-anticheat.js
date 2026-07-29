(function () {
    'use strict';

    const body = document.getElementById('examBody');
    if (!body) return;

    const csrf          = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const saveUrl       = body.dataset.saveUrl;
    const violationUrl  = body.dataset.violationUrl;
    const submitUrl     = body.dataset.submitUrl;
    const disconnectUrl = body.dataset.disconnectUrl;
    const endsAt        = parseInt(body.dataset.endsAt, 10) * 1000;

    // ── Security policy flags ────────────────────────────────────────────
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

    // ── Warning count — kept in sync with every server response ─────────
    // Initialised from the server so page refreshes stay correct.
    let warningCount = parseInt(body.dataset.warningCount || '0', 10);
    const MAX_WARNINGS = 3;

    let examStarted   = false;
    let examLocked    = false;
    let currentIndex  = 0;
    let isSubmitting  = false;

    const blocks       = Array.from(document.querySelectorAll('.question-block'));
    const navButtons   = Array.from(document.querySelectorAll('.q-nav-btn'));
    const progressFill = document.getElementById('progressFill');
    const progressText = document.getElementById('progressText');
    const warningBox   = document.getElementById('warningBox');
    const warningText  = document.getElementById('warningText');

    blocks.forEach(block => {
        if (!block.dataset.questionId) {
            const input = block.querySelector('[data-question-id]');
            if (input) block.dataset.questionId = input.dataset.questionId;
        }
    });

    /* ════════════════════════════════════════
       Interval / timer handles
    ════════════════════════════════════════ */
    const intervals = [];
    const timeouts  = [];
    function trackInterval(id) { intervals.push(id); return id; }
    function trackTimeout(id)  { timeouts.push(id);  return id; }

    /* ════════════════════════════════════════
       Fullscreen gate
    ════════════════════════════════════════ */
    const fsOverlay         = document.getElementById('fsOverlay');
    const isSessionRecovery = body.dataset.sessionRecovery === '1';
    const isReturning       = body.dataset.returning === '1' || isSessionRecovery;
    const resumeQuestionId  = body.dataset.resumeQuestionId || '';

    let violationGraceUntil = 0;

    function activateExamSession() {
        examStarted = true;
        violationGraceUntil = Date.now() + 2500;
        if (fsOverlay) fsOverlay.style.display = 'none';
    }

    document.getElementById('enterFullscreen')?.addEventListener('click', async () => {
        try { await document.documentElement.requestFullscreen(); } catch (_e) {}
        activateExamSession();
        if (policy.fullscreen && !document.fullscreenElement && examStarted) {
            openFsRecovery();
        }
    });

    /* ════════════════════════════════════════
       Anti-cheat detection listeners
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

    // ── Guard flag: ensures only ONE focus-loss violation is counted per
    //    FS recovery window, even if both visibilitychange AND blur fire.
    //    Both events often fire together when the student switches tabs;
    //    only the first one increments the warning count (+1 total for FOCUS_LOST).
    let focusLostDuringRecovery = false;

    function onVisibilityChange() {
        if (Date.now() < violationGraceUntil) return;
        if (!document.hidden) return;

        if (fsRecoveryPending) {
            // ── COMPOUND VIOLATION: FULLSCREEN_EXIT + FOCUS_LOST ──────────
            // Focus was lost during the 10-second FS recovery window.
            // Stop the FS timer so it cannot fire a third independent violation.
            cancelFsRecovery(false);

            if (!focusLostDuringRecovery) {
                focusLostDuringRecovery = true;
                // Send both violations sequentially.
                // After BOTH are confirmed by the server (warningCount will reach +2),
                // the second response handler will show the 15-second decision modal
                // because it detects the compound-violation state.
                sendCompoundViolation('tab_switch', 'Focus lost (tab switch) during fullscreen recovery');
            }
            // If blur also fires in the same cluster, the guard prevents a third call.
            return;
        }

        // Normal path — not during FS recovery.
        if (focusLostDuringRecovery) return;  // trailing blur from same cluster

        reportViolation('tab_switch', 'Tab switched');
    }

    function onWindowBlur() {
        if (Date.now() < violationGraceUntil) return;
        if (!examStarted) return;

        if (fsRecoveryPending) {
            // ── COMPOUND VIOLATION: FULLSCREEN_EXIT + FOCUS_LOST ──────────
            cancelFsRecovery(false);

            if (!focusLostDuringRecovery) {
                focusLostDuringRecovery = true;
                sendCompoundViolation('window_blur', 'Focus lost (window blur) during fullscreen recovery');
            }
            return;
        }

        if (focusLostDuringRecovery) return;  // trailing event from same cluster

        reportViolation('window_blur', 'Window lost focus');
    }

    /**
     * Send the compound FULLSCREEN_EXIT + FOCUS_LOST violations sequentially.
     *
     * The server receives two separate violation POST requests:
     *   1. fullscreen_exit  → warningCount +1
     *   2. focusLostType    → warningCount +1
     *
     * After the SECOND response arrives, if the exam is still active,
     * show the 15-second decision modal so the student can choose to
     * continue or confirm they want to leave.
     */
    function sendCompoundViolation(focusLostType, focusLostDetails) {
        // Send violation 1: FULLSCREEN_EXIT
        fetch(violationUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
            body: JSON.stringify({ type: 'fullscreen_exit', details: 'Exited fullscreen before returning' }),
        })
        .then(r => r.json())
        .then(data => {
            handleViolationResponse(data);
            if (data.terminated || examLocked) return;  // already terminated after first violation

            // Send violation 2: FOCUS_LOST (tab switch or window blur)
            return fetch(violationUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body: JSON.stringify({ type: focusLostType, details: focusLostDetails }),
            })
            .then(r => r.json())
            .then(data2 => {
                handleViolationResponse(data2);
                if (data2.terminated || examLocked) return;  // terminated after second violation

                // Both violations recorded. Show the 15-second decision modal
                // so the student can choose to return or confirm exit.
                showFinalWarningModal();
            });
        })
        .catch(() => {});
    }

    // Reset the focus-lost guard when the student returns focus.
    function onVisibilityShow() { if (!document.hidden) focusLostDuringRecovery = false; }
    function onWindowFocus()    { focusLostDuringRecovery = false; }
    document.addEventListener('visibilitychange', onVisibilityShow);
    window.addEventListener('focus', onWindowFocus);

    /* ════════════════════════════════════════
       Fullscreen recovery — 10-second grace window (normal flow)

       Used when warningCount < 2.
         1. Show modal with 10-second countdown.
         2. Student returns → cancelFsRecovery() → no violation.
         3. Timer expires  → reportViolation('fullscreen_exit').

       Tab/blur during the countdown fire dual violations (see above).
    ════════════════════════════════════════ */
    const FS_RECOVERY_SECONDS = 10;
    let fsRecoveryTimer   = null;
    let fsRecoveryPending = false;

    const fsRecoveryOverlay   = document.getElementById('fsRecoveryOverlay');
    const fsRecoveryCountdown = document.getElementById('fsRecoveryCountdown');
    const fsRecoveryBar       = document.getElementById('fsRecoveryBar');

    function showFsRecoveryModal() {
        if (!fsRecoveryOverlay) return;
        let remaining = FS_RECOVERY_SECONDS;
        if (fsRecoveryCountdown) fsRecoveryCountdown.textContent = remaining;
        if (fsRecoveryBar)       fsRecoveryBar.style.width = '100%';
        fsRecoveryOverlay.style.display = 'flex';
        fsRecoveryPending = true;
        focusLostDuringRecovery = false;  // reset for this new recovery window

        fsRecoveryTimer = setInterval(() => {
            remaining -= 1;
            const pct = Math.max(0, (remaining / FS_RECOVERY_SECONDS) * 100);
            if (fsRecoveryCountdown) fsRecoveryCountdown.textContent = remaining;
            if (fsRecoveryBar)       fsRecoveryBar.style.width = pct + '%';

            if (remaining <= 0) {
                clearInterval(fsRecoveryTimer);
                fsRecoveryTimer   = null;
                fsRecoveryPending = false;
                hideFsRecoveryModal();
                // Scenario 1 Case B: grace expired, count FULLSCREEN_EXIT (+1),
                // then auto-restore fullscreen so the student can continue the exam.
                reportViolation('fullscreen_exit', 'Exited fullscreen — grace period expired');
                autoRestoreFullscreen();
            }
        }, 1000);
    }

    function hideFsRecoveryModal() {
        if (fsRecoveryOverlay) fsRecoveryOverlay.style.display = 'none';
    }

    /**
     * Cancel the 10-second FS recovery timer.
     * @param {boolean} sendViolation  false = stop silently (caller sends its own violations)
     */
    function cancelFsRecovery(sendViolation) {
        if (fsRecoveryTimer) {
            clearInterval(fsRecoveryTimer);
            fsRecoveryTimer = null;
        }
        fsRecoveryPending = false;
        hideFsRecoveryModal();
        // Callers that pass false handle their own violation calls.
    }

    /* ════════════════════════════════════════
       Final Warning modal — 15-second countdown (Scenario 3)

       Shown ONLY when warningCount === 2 (one warning remaining).

       Three outcomes:
         A) Student clicks "Return Fullscreen" → restore FS, no violation, no log.
         B) Student clicks "Exit Fullscreen"   → reportViolation → termination.
         C) 15 seconds expire (no action)      → auto-restore FS, no violation, no log.
    ════════════════════════════════════════ */
    const FINAL_WARNING_SECONDS = 15;
    let finalWarningTimer   = null;
    let finalWarningPending = false;

    const finalWarningOverlay   = document.getElementById('finalWarningOverlay');
    const finalWarningCountdown = document.getElementById('finalWarningCountdown');
    const finalWarningBar       = document.getElementById('finalWarningBar');

    function showFinalWarningModal() {
        if (!finalWarningOverlay) return;
        let remaining = FINAL_WARNING_SECONDS;
        if (finalWarningCountdown) finalWarningCountdown.textContent = remaining;
        if (finalWarningBar)       finalWarningBar.style.width = '100%';
        // Show current warning count in the modal body
        const countEl = document.getElementById('finalWarningCount');
        if (countEl) countEl.textContent = warningCount;
        finalWarningOverlay.style.display = 'flex';
        finalWarningPending = true;

        finalWarningTimer = setInterval(() => {
            remaining -= 1;
            const pct = Math.max(0, (remaining / FINAL_WARNING_SECONDS) * 100);
            if (finalWarningCountdown) finalWarningCountdown.textContent = remaining;
            if (finalWarningBar)       finalWarningBar.style.width = pct + '%';

            if (remaining <= 0) {
                // Outcome C: time expired — auto-restore fullscreen, NO violation.
                clearInterval(finalWarningTimer);
                finalWarningTimer   = null;
                finalWarningPending = false;
                hideFinalWarningModal();
                autoRestoreFullscreen();
            }
        }, 1000);
    }

    function hideFinalWarningModal() {
        if (finalWarningOverlay) finalWarningOverlay.style.display = 'none';
    }

    function cancelFinalWarning() {
        if (finalWarningTimer) {
            clearInterval(finalWarningTimer);
            finalWarningTimer = null;
        }
        finalWarningPending = false;
        hideFinalWarningModal();
    }

    /**
     * Silently restore fullscreen without triggering a violation.
     * Used by Outcome A (button), Outcome C (auto-expire), and Case B (timer expiry).
     *
     * Because programmatic requestFullscreen() requires a user gesture context,
     * it may be blocked by the browser when called from a timer callback.
     * If the request fails (or isn't honoured after 800ms), show a fullscreen
     * re-entry gate so the student can click a button to manually re-enter.
     */
    function autoRestoreFullscreen() {
        // Grace window: fullscreenchange that fires on successful re-entry must NOT
        // trigger another recovery cycle.
        violationGraceUntil = Date.now() + 3000;

        document.documentElement.requestFullscreen()
            .then(() => {
                // Success — browser entered fullscreen. Grace is already set above.
            })
            .catch(() => {
                // Browser blocked the programmatic request (no user gesture).
                // Show the re-entry gate so the student must click to re-enter.
                showReentryGate();
            });

        // Belt-and-braces: even if the Promise resolves, the browser may not
        // immediately be in fullscreen. Check after 800ms and show the gate
        // if we still aren't fullscreen.
        setTimeout(() => {
            if (!document.fullscreenElement && !reentryGateVisible) {
                showReentryGate();
            }
        }, 800);
    }

    // ── Fullscreen re-entry gate ──────────────────────────────────────────
    // Shown when autoRestoreFullscreen() fails (browser blocked the gesture-less request).
    // Reuses the existing fsOverlay element with updated text.
    let reentryGateVisible = false;

    function showReentryGate() {
        if (reentryGateVisible || examLocked) return;
        reentryGateVisible = true;

        const overlay = document.getElementById('fsOverlay');
        if (!overlay) return;

        // Update overlay content to reflect the warning state
        const box = overlay.querySelector('.fs-modal-box');
        if (box) {
            box.innerHTML = `
                <div class="fs-modal-icon" style="background:linear-gradient(135deg,#b45309,#d97706)">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                </div>
                <h4 style="color:#92400e">⚠️ Warning Recorded</h4>
                <p style="font-size:0.875rem;color:#6b7280;margin-bottom:1.5rem;line-height:1.55">
                    <strong>Security warning ${warningCount} of 3 recorded.</strong><br>
                    You must return to fullscreen to continue your exam.
                </p>
                <button class="fs-start-btn" id="reentryFullscreenBtn" type="button"
                        style="background:linear-gradient(135deg,#b45309,#d97706)">
                    <i class="bi bi-fullscreen me-2"></i>Return to Fullscreen to Continue
                </button>`;

            // Bind the button
            box.querySelector('#reentryFullscreenBtn')?.addEventListener('click', async () => {
                try {
                    await document.documentElement.requestFullscreen();
                } catch (_e) {
                    // If still blocked, keep the gate open
                    return;
                }
                reentryGateVisible = false;
                overlay.style.display = 'none';
                // Restore the original overlay content for future use
                restoreOriginalFsOverlay();
            });
        }

        overlay.style.display = 'flex';
    }

    // Store original overlay HTML so we can restore it after re-entry
    const _originalFsOverlayHTML = document.getElementById('fsOverlay')?.querySelector('.fs-modal-box')?.innerHTML || '';

    function restoreOriginalFsOverlay() {
        const box = document.getElementById('fsOverlay')?.querySelector('.fs-modal-box');
        if (box && _originalFsOverlayHTML) box.innerHTML = _originalFsOverlayHTML;
        // Re-bind the original enterFullscreen button
        document.getElementById('enterFullscreen')?.addEventListener('click', async () => {
            try { await document.documentElement.requestFullscreen(); } catch (_e) {}
            // exam is already started — just restore fullscreen, don't re-activate
        });
    }

    /* ─────────────────────────────────────────────────────────────────────
       Outcome A — "Return to Fullscreen" button inside final-warning modal
    ───────────────────────────────────────────────────────────────────── */
    document.getElementById('finalWarningReturn')?.addEventListener('click', async () => {
        cancelFinalWarning();      // stop the 15-second countdown
        autoRestoreFullscreen();   // restore FS — no violation
    });

    /* ─────────────────────────────────────────────────────────────────────
       Outcome B — "Exit & Consume Warning" button inside final-warning modal
       This is an explicit, confirmed violation.
    ───────────────────────────────────────────────────────────────────── */
    document.getElementById('finalWarningExit')?.addEventListener('click', () => {
        cancelFinalWarning();    // stop the 15-second countdown
        // Deliberately NOT restoring fullscreen — student chose to exit.
        // Report the violation; the server will set warning_count to 3 and terminate.
        reportViolation('fullscreen_exit', 'Student confirmed fullscreen exit on final warning');
    });

    /* ════════════════════════════════════════
       Fullscreen change handler — routes to correct flow
    ════════════════════════════════════════ */
    function onFullscreenChange() {
        if (!examStarted) return;

        if (!document.fullscreenElement) {
            // Student exited fullscreen.
            if (finalWarningPending) return;  // already showing final warning — ignore re-fire
            if (fsRecoveryPending)   return;  // already counting down — ignore re-fire

            openFsRecovery();
        } else {
            // Student is back in fullscreen.
            if (finalWarningPending) {
                // Outcome A path triggered via browser (e.g. F11 key while modal is open):
                // treat the same as clicking "Return to Fullscreen".
                cancelFinalWarning();
            } else if (fsRecoveryPending) {
                cancelFsRecovery(false);   // silently cancel — no violation
            }
        }
    }

    /**
     * Entry point for any fullscreen exit event.
     * ALWAYS shows the standard 10-second recovery modal.
     *
     * The 15-second decision modal (showFinalWarningModal) is NEVER triggered
     * by a plain fullscreen exit. It is only shown after the compound violation
     * FULLSCREEN_EXIT + FOCUS_LOST is detected and confirmed with the server.
     */
    function openFsRecovery() {
        // Standard 10-second grace window for all FS exits, regardless of warning count.
        showFsRecoveryModal();
    }

    if (policy.rightClick) document.addEventListener('contextmenu',      onContextMenu);
    if (policy.copy)       document.addEventListener('copy',             onCopyCutPaste);
    if (policy.copy)       document.addEventListener('cut',              onCopyCutPaste);
    if (policy.paste)      document.addEventListener('paste',            onCopyCutPaste);
    if (policy.keyboard)   document.addEventListener('keydown',          onKeydown);
    if (policy.tabSwitch)  document.addEventListener('visibilitychange', onVisibilityChange);
    if (policy.blur)       window.addEventListener(  'blur',             onWindowBlur);
    if (policy.fullscreen) document.addEventListener('fullscreenchange', onFullscreenChange);

    /* ════════════════════════════════════════
       Browser close / page unload
    ════════════════════════════════════════ */
    window.addEventListener('beforeunload', function () {
        if (examStarted && !examLocked && !isSubmitting && disconnectUrl) {
            const currentQuestionId = blocks[currentIndex]?.dataset.questionId || '';
            const fd = new FormData();
            fd.append('_token', csrf);
            if (currentQuestionId) fd.append('question_id', currentQuestionId);
            fd.append('reason', 'browser_close');
            navigator.sendBeacon(disconnectUrl, fd);
        }
    });

    /* ════════════════════════════════════════
       Violation reporting
    ════════════════════════════════════════ */
    function reportViolation(type, details) {
        if (!examStarted || examLocked) return;
        if (Date.now() < violationGraceUntil) return;

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
        // Keep local warningCount in sync with the authoritative server value.
        if (typeof data.warning_count === 'number') {
            warningCount = data.warning_count;
        }

        // Show warning message for non-terminal violations.
        // Keep it visible for 8 seconds (longer than default) so the student sees it.
        if (warningBox && warningText && !examLocked) {
            warningText.textContent = data.message || 'Violation recorded.';
            warningBox.classList.add('show');
            const hideId = setTimeout(() => warningBox.classList.remove('show'), 8000);
            trackTimeout(hideId);
        }

        // Terminal violation — lock and redirect.
        if (data.terminated) {
            lockExamInterface(data.message);
            setTimeout(() => {
                window.location.href = data.redirect || '/student/exams';
            }, 3000);
        }
    }

    /* ════════════════════════════════════════
       Full interface shutdown (Tier 3 termination)
    ════════════════════════════════════════ */
    function lockExamInterface(message) {
        if (examLocked) return;
        examLocked  = true;
        examStarted = false;

        const lockMessage = message || 'Your exam has been locked due to repeated security violations.';

        // ── Stop all timers including recovery and final-warning timers ──
        if (fsRecoveryTimer) {
            clearInterval(fsRecoveryTimer);
            fsRecoveryTimer   = null;
            fsRecoveryPending = false;
        }
        if (finalWarningTimer) {
            clearInterval(finalWarningTimer);
            finalWarningTimer   = null;
            finalWarningPending = false;
        }
        reentryGateVisible = false;
        hideFsRecoveryModal();
        hideFinalWarningModal();
        // Also hide the re-entry gate if it's open
        const _fsOvr = document.getElementById('fsOverlay');
        if (_fsOvr) _fsOvr.style.display = 'none';
        intervals.forEach(clearInterval);
        timeouts.forEach(clearTimeout);

        // ── Remove all listeners ─────────────────────────────────────
        if (policy.rightClick) document.removeEventListener('contextmenu',      onContextMenu);
        if (policy.copy)       document.removeEventListener('copy',             onCopyCutPaste);
        if (policy.copy)       document.removeEventListener('cut',              onCopyCutPaste);
        if (policy.paste)      document.removeEventListener('paste',            onCopyCutPaste);
        if (policy.keyboard)   document.removeEventListener('keydown',          onKeydown);
        if (policy.tabSwitch)  document.removeEventListener('visibilitychange', onVisibilityChange);
        if (policy.tabSwitch)  document.removeEventListener('visibilitychange', onVisibilityShow);
        if (policy.blur)       window.removeEventListener(  'blur',             onWindowBlur);
        if (policy.blur)       window.removeEventListener(  'focus',            onWindowFocus);
        if (policy.fullscreen) document.removeEventListener('fullscreenchange', onFullscreenChange);

        // ── Disable all answer controls ──────────────────────────────
        document.querySelectorAll(
            '.answer-input, .answer-blank, .answer-text, .mcq-option, ' +
            '.sidebar-submit-btn, #submitBtn, .btn-nav, .q-nav-btn'
        ).forEach(el => {
            el.disabled = true;
            el.style.pointerEvents = 'none';
            el.style.opacity = '0.45';
        });

        if (document.fullscreenElement) {
            document.exitFullscreen().catch(() => {});
        }

        // ── Show lock overlay ────────────────────────────────────────
        const overlay = document.createElement('div');
        overlay.id = 'examLockedOverlay';
        overlay.style.cssText = [
            'position:fixed', 'inset:0', 'z-index:10001',
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
        if (examLocked) return;
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

    // Periodic MCQ auto-save
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
        if (type === 'mcq' || type === 'true_false') return !!block.querySelector('.answer-input:checked');
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
        const pct = blocks.length > 0 ? Math.round((answered / blocks.length) * 100) : 0;
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
       Exam countdown timer
    ════════════════════════════════════════ */
    const timerEl   = document.getElementById('timer');
    const timerText = document.getElementById('timerText');

    trackInterval(setInterval(() => {
        if (examLocked) return;
        const left = endsAt - Date.now();
        if (left <= 0) {
            examStarted  = false;
            isSubmitting = true;
            intervals.forEach(clearInterval);
            if (timerText) timerText.textContent = '00:00';
            document.getElementById('examForm')?.submit();
            return;
        }
        const m = Math.floor(left / 60000);
        const s = Math.floor((left % 60000) / 1000);
        if (timerText) {
            timerText.textContent = `${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
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
            examStarted  = false;
            isSubmitting = true;
            intervals.forEach(clearInterval);
            const form = document.getElementById('examForm');
            form.action = submitUrl;
            form.submit();
        }
    });

    /* ════════════════════════════════════════
       Init
    ════════════════════════════════════════ */
    let startIndex = 0;
    if (resumeQuestionId) {
        const idx = blocks.findIndex(b => String(b.dataset.questionId) === String(resumeQuestionId));
        if (idx >= 0) startIndex = idx;
    } else if (isReturning) {
        for (let i = 0; i < blocks.length; i++) {
            if (!isAnswered(blocks[i])) { startIndex = i; break; }
        }
    }
    showQuestion(startIndex);

    // ── Standard FS recovery: "Return to Fullscreen" button ─────────────
    document.getElementById('fsRecoveryReturnBtn')?.addEventListener('click', async () => {
        try { await document.documentElement.requestFullscreen(); } catch (_e) {}
        // fullscreenchange fires → calls cancelFsRecovery(false) automatically.
    });

})();
