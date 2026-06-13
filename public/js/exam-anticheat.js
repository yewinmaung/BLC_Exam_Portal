(function () {
    'use strict';

    const body = document.getElementById('examBody');
    if (!body) return;

    const csrf         = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const saveUrl      = body.dataset.saveUrl;
    const violationUrl = body.dataset.violationUrl;
    const submitUrl    = body.dataset.submitUrl;
    const endsAt       = parseInt(body.dataset.endsAt, 10) * 1000;

    let examStarted        = false;
    let currentIndex       = 0;
    const blocks           = Array.from(document.querySelectorAll('.question-block'));
    const navButtons       = Array.from(document.querySelectorAll('.q-nav-btn'));
    const progressFill     = document.getElementById('progressFill');
    const progressText     = document.getElementById('progressText');
    const warningBox       = document.getElementById('warningBox');
    const warningText      = document.getElementById('warningText');

    /* ════════════════════════════════════════
       Fullscreen gate
    ════════════════════════════════════════ */
    const fsOverlay = document.getElementById('fsOverlay');

    document.getElementById('enterFullscreen')?.addEventListener('click', async () => {
        try {
            await document.documentElement.requestFullscreen();
        } catch (e) {
            // Fullscreen may be blocked — allow anyway on some browsers
        }
        examStarted = true;
        if (fsOverlay) fsOverlay.style.display = 'none';
    });

    /* ════════════════════════════════════════
       Anti-cheat
    ════════════════════════════════════════ */
    document.addEventListener('contextmenu', e => e.preventDefault());
    document.addEventListener('copy',  e => e.preventDefault());
    document.addEventListener('cut',   e => e.preventDefault());
    document.addEventListener('paste', e => e.preventDefault());

    document.addEventListener('keydown', e => {
        if (e.key === 'F12' ||
            (e.ctrlKey && e.shiftKey && ['I','J','C'].includes(e.key)) ||
            (e.ctrlKey && e.key === 'u')) {
            e.preventDefault();
            reportViolation('devtools_shortcut', 'DevTools shortcut blocked');
        }
    });

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) reportViolation('tab_switch', 'Tab switched');
    });

    window.addEventListener('blur', () => {
        if (examStarted) reportViolation('window_blur', 'Window lost focus');
    });

    document.addEventListener('fullscreenchange', () => {
        if (!document.fullscreenElement && examStarted) {
            reportViolation('fullscreen_exit', 'Exited fullscreen');
        }
    });

    function reportViolation(type, details) {
        if (!examStarted) return;
        fetch(violationUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ type, details }),
        })
        .then(r => r.json())
        .then(data => {
            if (warningBox && warningText) {
                warningText.textContent = data.message || 'Violation recorded.';
                warningBox.classList.add('show');
                setTimeout(() => warningBox.classList.remove('show'), 5000);
            }
            if (data.terminated) {
                alert(data.message);
                window.location.href = data.redirect || '/student/exams';
            }
        })
        .catch(() => {});
    }

    /* ════════════════════════════════════════
       Answer saving
    ════════════════════════════════════════ */
    function saveAnswer(questionId, answerId, answerText) {
        fetch(saveUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                question_id: questionId,
                answer_id:   answerId   || null,
                answer_text: answerText || null,
            }),
        }).catch(() => {});
    }

    // MCQ / True-False — click on label
    document.querySelectorAll('.mcq-option').forEach(label => {
        label.addEventListener('click', function () {
            const radio = this.querySelector('input[type="radio"]');
            if (!radio) return;
            const qid = radio.dataset.questionId;
            // Deselect siblings
            document.querySelectorAll(`#options_${qid} .mcq-option`).forEach(l => l.classList.remove('selected'));
            this.classList.add('selected');
            radio.checked = true;
            saveAnswer(qid, radio.value, null);
            refreshNav();
        });
    });

    // Fill in the blank
    document.querySelectorAll('.answer-blank').forEach(input => {
        let debounce;
        input.addEventListener('input', function () {
            clearTimeout(debounce);
            debounce = setTimeout(() => {
                saveAnswer(this.dataset.questionId, null, this.value.trim());
                refreshNav();
            }, 800);
        });
    });

    // Essay
    document.querySelectorAll('.answer-text').forEach(textarea => {
        let debounce;
        textarea.addEventListener('input', function () {
            clearTimeout(debounce);
            debounce = setTimeout(() => {
                saveAnswer(this.dataset.questionId, null, this.value.trim());
                refreshNav();
            }, 1500);
        });
    });

    // Periodic auto-save for MCQ
    setInterval(() => {
        document.querySelectorAll('.answer-input:checked').forEach(radio => {
            saveAnswer(radio.dataset.questionId, radio.value, null);
        });
    }, 10000);

    /* ════════════════════════════════════════
       Question answered check
    ════════════════════════════════════════ */
    function isAnswered(block) {
        const qid  = block.dataset.questionId;
        const type = block.dataset.type;

        if (type === 'mcq' || type === 'true_false') {
            return !!block.querySelector('.answer-input:checked');
        }
        if (type === 'fill_blank') {
            const inp = block.querySelector('.answer-blank');
            return inp && inp.value.trim().length > 0;
        }
        // essay
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

        // Progress
        const pct = blocks.length > 0 ? Math.round((answered / blocks.length) * 100) : 0;
        if (progressFill) progressFill.style.width = pct + '%';
        if (progressText) progressText.textContent = `${answered} / ${blocks.length}`;
    }

    function showQuestion(index) {
        if (index < 0 || index >= blocks.length) return;
        currentIndex = index;
        blocks.forEach((b, i) => b.classList.toggle('active', i === index));
        // Scroll to top of question area
        blocks[index]?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        refreshNav();
    }

    // Nav button clicks
    navButtons.forEach((btn, idx) => {
        btn.addEventListener('click', () => showQuestion(idx));
    });

    // Prev / Next inside question cards
    document.querySelectorAll('.prev-question').forEach((btn, idx) => {
        btn.addEventListener('click', () => showQuestion(currentIndex - 1));
    });
    document.querySelectorAll('.next-question').forEach((btn, idx) => {
        btn.addEventListener('click', () => showQuestion(currentIndex + 1));
    });

    /* ════════════════════════════════════════
       Timer
    ════════════════════════════════════════ */
    const timerEl   = document.getElementById('timer');
    const timerText = document.getElementById('timerText');

    const timerInterval = setInterval(() => {
        const left = endsAt - Date.now();
        if (left <= 0) {
            clearInterval(timerInterval);
            if (timerText) timerText.textContent = '00:00';
            document.getElementById('examForm').submit();
            return;
        }
        const m = Math.floor(left / 60000);
        const s = Math.floor((left % 60000) / 1000);
        if (timerText) timerText.textContent = `${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;

        // Warning colour when < 5 minutes
        if (timerEl) timerEl.classList.toggle('warning', left < 300000);
    }, 1000);

    /* ════════════════════════════════════════
       Submit
    ════════════════════════════════════════ */
    document.getElementById('submitBtn')?.addEventListener('click', () => {
        const answered = blocks.filter(b => isAnswered(b)).length;
        const unanswered = blocks.length - answered;
        const msg = unanswered > 0
            ? `You have ${unanswered} unanswered question(s). Submit anyway?`
            : 'Submit exam? This cannot be undone.';
        if (confirm(msg)) {
            clearInterval(timerInterval);
            const form = document.getElementById('examForm');
            form.action = submitUrl;
            form.submit();
        }
    });

    /* ════════════════════════════════════════
       Init
    ════════════════════════════════════════ */
    showQuestion(0);
})();
