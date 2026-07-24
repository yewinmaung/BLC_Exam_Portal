(function () {
    'use strict';

    /* ── DOM refs ── */
    const qType            = document.getElementById('qType');
    const answersBlock     = document.getElementById('answersBlock');
    const answersList      = document.getElementById('answersList');
    const addAnswerBtn     = document.getElementById('addAnswerBtn');
    const blankAnswersBlock= document.getElementById('blankAnswersBlock');
    const blankAnswersList = document.getElementById('blankAnswersList');
    const addBlankAnswerBtn= document.getElementById('addBlankAnswerBtn');
    const fillBlankHint    = document.getElementById('fillBlankHint');
    const form             = document.getElementById('questionForm');

    if (!qType || !form) return;

    let answerIndex      = 0;
    let blankAnswerIndex = 0;

    /* ════════════════════════════════════════
       MCQ / True-False answer rows
    ════════════════════════════════════════ */
    function createAnswerRow(label, value, isCorrect) {
        const idx = answerIndex++;
        const row = document.createElement('div');
        row.className = 'input-group answer-row';
        row.style.cssText = 'border-radius:8px;overflow:hidden';
        row.innerHTML = `
            <span class="input-group-text choice-label"
                  style="background:#f8faff;border-color:#d0d8e8;font-weight:700;color:var(--blc-navy,#0b2a5b);min-width:38px;justify-content:center">
                ${label}.
            </span>
            <input type="text"
                   name="answers[${idx}][content]"
                   class="form-control"
                   placeholder="Answer option"
                   value="${escHtml(value || '')}"
                   style="border-color:#d0d8e8">
            <label class="input-group-text correct-label"
                   title="Mark as correct"
                   style="cursor:pointer;gap:0.35rem;border-color:#d0d8e8;background:#f8faff;font-size:0.8rem;font-weight:500;color:#6b7280;white-space:nowrap">
                <input type="radio"
                       name="correct_choice"
                       value="${idx}"
                       class="correct-radio"
                       style="accent-color:var(--blc-navy-2,#0f3a7a)"
                       ${isCorrect ? 'checked' : ''}>
                Correct
            </label>
            <button type="button"
                    class="btn btn-outline-danger btn-remove"
                    title="Remove"
                    style="border-color:#d0d8e8">
                <i class="bi bi-x"></i>
            </button>
        `;
        row.querySelector('.btn-remove').addEventListener('click', () => {
            row.remove();
            reindexLabels();
        });
        if (answersList) answersList.appendChild(row);
    }

    function reindexLabels() {
        const labels = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        if (!answersList) return;
        answersList.querySelectorAll('.answer-row').forEach((row, i) => {
            row.querySelector('.choice-label').textContent = labels[i] + '.';
        });
    }

    function syncCorrectToHidden() {
        form.querySelectorAll('input[name^="answers"][name$="[is_correct]"]').forEach(el => el.remove());
        const selected = form.querySelector('input[name="correct_choice"]:checked');
        if (!answersList) return;
        answersList.querySelectorAll('.answer-row').forEach((row) => {
            const input = row.querySelector('input[type="text"]');
            if (!input) return;
            const nameMatch = input.name.match(/answers\[(\d+)\]/);
            if (!nameMatch) return;
            const idx = nameMatch[1];
            const hidden = document.createElement('input');
            hidden.type  = 'hidden';
            hidden.name  = `answers[${idx}][is_correct]`;
            hidden.value = (selected && selected.value === idx) ? '1' : '0';
            form.appendChild(hidden);
        });
    }

    /* ════════════════════════════════════════
       Fill-in-the-blank answer rows
    ════════════════════════════════════════ */
    function createBlankAnswerRow(value) {
        const idx = blankAnswerIndex++;
        const row = document.createElement('div');
        row.className = 'input-group blank-answer-row';
        row.style.cssText = 'border-radius:8px;overflow:hidden';
        row.innerHTML = `
            <span class="input-group-text"
                  style="background:#eff6ff;border-color:#bfdbfe;color:#1d4ed8;font-size:0.8rem;font-weight:700;white-space:nowrap">
                <i class="bi bi-check2-circle me-1"></i> Answer
            </span>
            <input type="text"
                   name="blank_answers[${idx}]"
                   class="form-control"
                   placeholder="Accepted answer (e.g. Paris)"
                   value="${escHtml(value || '')}"
                   style="border-color:#bfdbfe">
            <button type="button"
                    class="btn btn-outline-danger btn-remove-blank"
                    title="Remove"
                    style="border-color:#bfdbfe">
                <i class="bi bi-x"></i>
            </button>
        `;
        row.querySelector('.btn-remove-blank').addEventListener('click', () => row.remove());
        if (blankAnswersList) blankAnswersList.appendChild(row);
    }

    /* ════════════════════════════════════════
       Type-change UI logic
    ════════════════════════════════════════ */
    function resetAnswersForType(type, keepExisting) {
        if (!keepExisting) {
            if (answersList)      answersList.innerHTML      = '';
            if (blankAnswersList) blankAnswersList.innerHTML = '';
            answerIndex      = 0;
            blankAnswerIndex = 0;
        }

        /* MCQ */
        if (type === 'mcq') {
            show(answersBlock);
            hide(blankAnswersBlock);
            hide(fillBlankHint);
            if (!keepExisting) {
                createAnswerRow('A', '', false);
                createAnswerRow('B', '', false);
                createAnswerRow('C', '', false);
                createAnswerRow('D', '', false);
            }

        /* True / False */
        } else if (type === 'true_false') {
            show(answersBlock);
            hide(blankAnswersBlock);
            hide(fillBlankHint);
            if (!keepExisting) {
                createAnswerRow('A', 'True',  false);
                createAnswerRow('B', 'False', false);
            }

        /* Fill in the blank */
        } else if (type === 'fill_blank') {
            hide(answersBlock);
            show(blankAnswersBlock);
            show(fillBlankHint);
            if (!keepExisting) {
                createBlankAnswerRow('');
            }

        /* Essay */
        } else {
            hide(answersBlock);
            hide(blankAnswersBlock);
            hide(fillBlankHint);
        }
    }

    function show(el) { el && el.classList.remove('d-none'); }
    function hide(el) { el && el.classList.add('d-none'); }

    function escHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    /* ════════════════════════════════════════
       Edit-mode: pre-populate existing answers
    ════════════════════════════════════════ */
    function populateExisting(type, existing) {
        if (!existing || existing.length === 0) return;

        if (type === 'fill_blank') {
            if (blankAnswersList) blankAnswersList.innerHTML = '';
            blankAnswerIndex = 0;
            existing.forEach(a => createBlankAnswerRow(a.content));

        } else if (type === 'mcq' || type === 'true_false') {
            if (answersList) answersList.innerHTML = '';
            answerIndex = 0;
            const labels = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
            existing.forEach((a, i) => createAnswerRow(labels[i] || String(i + 1), a.content, a.is_correct));
        }
    }

    /* ════════════════════════════════════════
       Event listeners
    ════════════════════════════════════════ */
    qType.addEventListener('change', () => {
        resetAnswersForType(qType.value, false);
    });

    addAnswerBtn?.addEventListener('click', () => {
        const count  = answersList ? answersList.querySelectorAll('.answer-row').length : 0;
        const label  = String.fromCharCode(65 + count);
        createAnswerRow(label, '', false);
    });

    addBlankAnswerBtn?.addEventListener('click', () => {
        createBlankAnswerRow('');
    });

    form.addEventListener('submit', (e) => {
        // Remove any previous inline error
        const prev = form.querySelector('.qb-inline-error');
        if (prev) prev.remove();

        const type = qType.value;

        if (type === 'fill_blank') {
            // Must have at least one non-empty accepted answer
            const filled = blankAnswersList
                ? [...blankAnswersList.querySelectorAll('input[type="text"]')]
                    .filter(i => i.value.trim() !== '')
                : [];
            if (filled.length === 0) {
                e.preventDefault();
                showInlineError('Please add at least one accepted answer before saving.');
                addBlankAnswerBtn?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }
        } else {
            // MCQ / True-False: must have a correct radio selected AND that answer non-empty
            syncCorrectToHidden();
            const selected = form.querySelector('input[name="correct_choice"]:checked');
            if (!selected) {
                e.preventDefault();
                showInlineError('Please mark the correct answer before saving.');
                answersBlock?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }
            // Also verify the selected option has content
            const selectedRow = answersList?.querySelector(
                `input[type="text"][name="answers[${selected.value}][content]"]`
            );
            if (selectedRow && selectedRow.value.trim() === '') {
                e.preventDefault();
                showInlineError('The correct answer option cannot be empty.');
                selectedRow.focus();
                return;
            }
        }
    });

    function showInlineError(msg) {
        const div = document.createElement('div');
        div.className = 'qb-inline-error alert alert-danger d-flex align-items-center gap-2 mt-2 mb-0';
        div.style.fontSize = '0.83rem';
        div.innerHTML = '<i class="bi bi-exclamation-triangle-fill flex-shrink-0"></i><span>' + escHtml(msg) + '</span>';
        // Insert before the Save button
        const saveBtn = form.querySelector('button[type="submit"]');
        if (saveBtn) saveBtn.before(div);
        else form.appendChild(div);
    }

    /* ════════════════════════════════════════
       Init
    ════════════════════════════════════════ */
    const isEditMode = window.editMode === true;
    const existing   = window.existingAnswers || [];
    const initType   = qType.value;

    if (isEditMode && existing.length > 0) {
        /* Show correct panels first, then populate */
        resetAnswersForType(initType, true);
        populateExisting(initType, existing);
    } else {
        resetAnswersForType(initType, false);
    }

})();
