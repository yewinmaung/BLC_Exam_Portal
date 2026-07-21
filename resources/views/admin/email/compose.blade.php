@extends('layouts.app')
@section('title', 'Compose Email')
@section('page-title', 'Compose Email')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Email'],
        ['label' => 'Compose'],
    ]])
@endsection
@section('sidebar')@include('partials.admin-sidebar')@endsection

@push('styles')
<style>
/* ── Step indicators ── */
.compose-steps {
    display: flex; align-items: center; gap: 0; margin-bottom: 1.75rem;
}
.compose-step {
    display: flex; align-items: center; gap: 0.5rem;
    font-size: 0.78rem; font-weight: 600; color: #9ca3af;
}
.compose-step.active  { color: var(--blc-royal, #2d27a0); }
.compose-step.done    { color: #16a34a; }
.step-num {
    width: 26px; height: 26px; border-radius: 50%; border: 2px solid #e2e8f0;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.72rem; font-weight: 800; background: #fff; flex-shrink: 0;
}
.compose-step.active .step-num { border-color: var(--blc-royal, #2d27a0); color: var(--blc-royal, #2d27a0); background: #eef2ff; }
.compose-step.done   .step-num { border-color: #16a34a; color: #fff; background: #16a34a; }
.step-divider { flex: 1; height: 2px; background: #e2e8f0; margin: 0 0.5rem; min-width: 24px; max-width: 48px; }

/* ── Variable chips ── */
.var-chip {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 0.72rem; font-weight: 700; padding: 2px 8px;
    border-radius: 4px; font-family: monospace;
}
.var-chip.auto   { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
.var-chip.manual { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }

/* ── Preview iframe ── */
#previewFrame {
    width: 100%; border: none; min-height: 480px; display: block;
    border-radius: 0 0 10px 10px;
}
.preview-subject-bar {
    background: #f8f9fc; border: 1px solid #e2e8f0;
    border-bottom: none; border-radius: 10px 10px 0 0;
    padding: 0.6rem 1rem; font-size: 0.83rem;
}
.preview-subject-bar strong { color: #1a2540; }

/* ── Section panels ── */
.compose-panel { display: none; }
.compose-panel.active { display: block; }

/* ── Field label uppercase ── */
.field-section-label {
    font-size: 0.69rem; font-weight: 700; color: #9ca3af;
    text-transform: uppercase; letter-spacing: 0.07em;
    margin-bottom: 0.6rem;
}
</style>
@endpush

@section('content')

{{-- Template metadata passed to JS as JSON (server-rendered, no extra AJAX) --}}
<script id="templateData" type="application/json">
{!! json_encode(
    $templates->map(fn($t) => [
        'slug'        => $t->slug,
        'name'        => $t->name,
        'subject'     => $t->subject,
        'body_html'   => $t->body_html,
        'manual_vars' => $t->manual_vars,
        'auto_vars'   => $t->auto_vars,
    ])->keyBy('slug')
) !!}
</script>

<div style="max-width:800px">

    {{-- ── Step indicator ── --}}
    <div class="compose-steps" id="stepIndicator">
        <div class="compose-step active" id="stepLbl1">
            <div class="step-num">1</div>
            <span>Template &amp; Recipients</span>
        </div>
        <div class="step-divider"></div>
        <div class="compose-step" id="stepLbl2">
            <div class="step-num">2</div>
            <span>Fill Variables</span>
        </div>
        <div class="step-divider"></div>
        <div class="compose-step" id="stepLbl3">
            <div class="step-num">3</div>
            <span>Preview &amp; Send</span>
        </div>
    </div>

    {{-- ════ STEP 1 — Template + recipient ════ --}}
    <div class="compose-panel active" id="panelStep1">
        <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-file-earmark-code" style="color:var(--blc-royal,#2d27a0)"></i>
                Step 1 — Choose Template &amp; Recipients
            </div>
            <div class="card-body">

                <div class="mb-4">
                    <label class="form-label fw-semibold" style="font-size:0.82rem;font-weight:600">
                        Email Template <span class="text-danger">*</span>
                    </label>
                    <select id="templateSelect" class="form-select">
                        <option value="">— Select a template —</option>
                        @foreach($templates as $tmpl)
                        <option value="{{ $tmpl->slug }}">{{ $tmpl->name }}</option>
                        @endforeach
                    </select>
                    <div class="form-text" style="font-size:0.75rem;margin-top:0.4rem">
                        Select a template to see which variables it requires.
                    </div>
                </div>

                <div id="varSummary" style="display:none" class="mb-4">
                    <div class="field-section-label">Detected Variables</div>
                    <div id="varChips" class="d-flex flex-wrap gap-2 mb-2"></div>
                    <div style="font-size:0.75rem;color:#6b7280">
                        <span style="background:#f0fdf4;color:#166534;padding:1px 6px;border-radius:3px;font-weight:700">Green</span>
                        = resolved automatically &nbsp;
                        <span style="background:#fef3c7;color:#92400e;padding:1px 6px;border-radius:3px;font-weight:700">Yellow</span>
                        = you must provide a value
                    </div>
                </div>

                <hr class="my-4">

                <div class="mb-3">
                    <div class="field-section-label">Send Mode</div>
                    <div class="d-flex gap-3">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="uiMode" id="modeSingle" value="single" checked>
                            <label class="form-check-label" for="modeSingle" style="font-size:0.85rem;cursor:pointer">Single recipient</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="uiMode" id="modeGroup" value="group">
                            <label class="form-check-label" for="modeGroup" style="font-size:0.85rem;cursor:pointer">Recipient group</label>
                        </div>
                    </div>
                </div>

                <div id="fieldSingle" class="mb-3">
                    <label class="form-label" style="font-size:0.82rem;font-weight:600">To — Email Address</label>
                    <input type="email" id="uiToEmail" class="form-control" placeholder="recipient@example.com">
                </div>

                <div id="fieldGroup" class="mb-3" style="display:none">
                    <label class="form-label" style="font-size:0.82rem;font-weight:600">Recipient Group</label>
                    <select id="uiRecipients" class="form-select">
                        <option value="">— Select group —</option>
                        @foreach($groups as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <button type="button" class="btn btn-primary" id="btnStep1Next">
                    Continue <i class="bi bi-arrow-right ms-1"></i>
                </button>
                <div id="step1Error" class="text-danger mt-2" style="font-size:0.83rem"></div>

            </div>
        </div>
    </div>

    {{-- ════ STEP 2 — Variable input form ════ --}}
    <div class="compose-panel" id="panelStep2">
        <div class="card">
            <div class="card-header d-flex align-items-center gap-2">
                <i class="bi bi-input-cursor-text" style="color:var(--blc-royal,#2d27a0)"></i>
                Step 2 — Fill in Template Variables
            </div>
            <div class="card-body">

                <div id="noManualVarsMsg" style="display:none">
                    <div class="alert alert-success d-flex gap-2 align-items-center mb-4" style="font-size:0.84rem">
                        <i class="bi bi-check-circle-fill"></i>
                        <span>All variables in this template are resolved automatically. No manual input needed.</span>
                    </div>
                </div>

                <div id="dynamicVarsForm"></div>

                <div class="d-flex gap-2 mt-3">
                    <button type="button" class="btn btn-outline-secondary" id="btnStep2Back">
                        <i class="bi bi-arrow-left me-1"></i> Back
                    </button>
                    <button type="button" class="btn btn-primary" id="btnStep2Preview">
                        <i class="bi bi-eye me-1"></i> Preview Email
                    </button>
                </div>
                <div id="step2Error" class="text-danger mt-2" style="font-size:0.83rem"></div>

            </div>
        </div>
    </div>

    {{-- ════ STEP 3 — Preview + Send ════ --}}
    <div class="compose-panel" id="panelStep3">
        <div class="card mb-3">
            <div class="card-header d-flex align-items-center justify-content-between">
                <span>
                    <i class="bi bi-eye me-2" style="color:var(--blc-royal,#2d27a0)"></i>
                    Step 3 — Preview &amp; Send
                </span>
                <span id="previewBadge" class="badge" style="background:#eef2ff;color:#3730a3;font-size:0.75rem"></span>
            </div>
            <div class="card-body pb-2">
                <div class="d-flex align-items-start gap-3 mb-3" style="flex-wrap:wrap">
                    <div style="flex:1;min-width:200px">
                        <div class="field-section-label">Recipient</div>
                        <div id="previewRecipient" style="font-size:0.88rem;color:#374151;font-weight:600"></div>
                        <div id="previewSampleNote" style="font-size:0.75rem;color:#9ca3af;display:none">
                            <i class="bi bi-info-circle me-1"></i>Showing sample — each recipient will be personalised
                        </div>
                    </div>
                    <div style="flex:1;min-width:200px">
                        <div class="field-section-label">Template</div>
                        <div id="previewTemplateName" style="font-size:0.88rem;color:#374151"></div>
                    </div>
                </div>
            </div>
            <div class="preview-subject-bar">
                <strong>Subject:</strong>
                <span id="previewSubject" style="color:#374151;margin-left:0.4rem"></span>
            </div>
            <iframe id="previewFrame" title="Email Preview" sandbox="allow-same-origin"></iframe>
        </div>

        <div class="card">
            <div class="card-body">
                <form method="POST" action="{{ route('admin.email.compose.send') }}" id="sendForm">
                    @csrf
                    <input type="hidden" name="mode"          id="hiddenMode">
                    <input type="hidden" name="template_slug" id="hiddenSlug">
                    <input type="hidden" name="to_email"      id="hiddenToEmail">
                    <input type="hidden" name="recipients"    id="hiddenRecipients">
                    <input type="hidden" name="subject"       id="hiddenSubject">
                    <input type="hidden" name="body_html"     id="hiddenBody">
                    <div id="hiddenVarsContainer"></div>

                    <div class="d-flex gap-2 align-items-center">
                        <button type="button" class="btn btn-outline-secondary" id="btnStep3Back">
                            <i class="bi bi-arrow-left me-1"></i> Back
                        </button>
                        <button type="submit" class="btn btn-primary" id="btnSend">
                            <i class="bi bi-send me-1"></i> Send Email
                        </button>
                        <span id="sendSpinner" style="display:none;font-size:0.83rem" class="text-muted">
                            <span class="spinner-border spinner-border-sm me-1"></span> Queuing&hellip;
                        </span>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
{{-- PHP value injected before the JS block so it is available at runtime --}}
<script>window._COMPOSE_PREVIEW_URL = @json(route('admin.email.compose.preview'));</script>
<script>
(function () {
    'use strict';

    var TMPL_DATA   = JSON.parse(document.getElementById('templateData').textContent);
    var CSRF        = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    var PREVIEW_URL = window._COMPOSE_PREVIEW_URL;

    var panelStep1    = document.getElementById('panelStep1');
    var panelStep2    = document.getElementById('panelStep2');
    var panelStep3    = document.getElementById('panelStep3');
    var stepLbls      = [
        null,
        document.getElementById('stepLbl1'),
        document.getElementById('stepLbl2'),
        document.getElementById('stepLbl3')
    ];

    var tmplSelect    = document.getElementById('templateSelect');
    var varSummary    = document.getElementById('varSummary');
    var varChips      = document.getElementById('varChips');
    var fieldSingle   = document.getElementById('fieldSingle');
    var fieldGroup    = document.getElementById('fieldGroup');
    var uiToEmail     = document.getElementById('uiToEmail');
    var uiRecipients  = document.getElementById('uiRecipients');
    var step1Error    = document.getElementById('step1Error');
    var step2Error    = document.getElementById('step2Error');
    var noManualMsg   = document.getElementById('noManualVarsMsg');
    var dynForm       = document.getElementById('dynamicVarsForm');
    var previewFrame  = document.getElementById('previewFrame');
    var previewSubj   = document.getElementById('previewSubject');
    var previewRecip  = document.getElementById('previewRecipient');
    var previewBadge  = document.getElementById('previewBadge');
    var previewName   = document.getElementById('previewTemplateName');
    var previewSample = document.getElementById('previewSampleNote');
    var hiddenMode    = document.getElementById('hiddenMode');
    var hiddenSlug    = document.getElementById('hiddenSlug');
    var hiddenToEmail = document.getElementById('hiddenToEmail');
    var hiddenRecips  = document.getElementById('hiddenRecipients');
    var hiddenSubj    = document.getElementById('hiddenSubject');
    var hiddenBody    = document.getElementById('hiddenBody');
    var hiddenVarsCont= document.getElementById('hiddenVarsContainer');
    var sendForm      = document.getElementById('sendForm');
    var sendSpinner   = document.getElementById('sendSpinner');
    var btnSend       = document.getElementById('btnSend');

    var currentTmpl = null;
    var currentMode = 'single';

    /* ── Step navigation ── */
    function showStep(n) {
        [panelStep1, panelStep2, panelStep3].forEach(function (p, i) {
            p.classList.toggle('active', i + 1 === n);
        });
        stepLbls.forEach(function (el, i) {
            if (!el) return;
            el.classList.remove('active', 'done');
            if (i === n)    el.classList.add('active');
            else if (i < n) el.classList.add('done');
            var num = el.querySelector('.step-num');
            if (!num) return;
            if (i < n) {
                num.innerHTML = '<i class="bi bi-check2" style="font-size:0.85rem"></i>';
            } else {
                num.textContent = String(i);
            }
        });
    }

    /* ── Template selected → build chips ── */
    tmplSelect.addEventListener('change', function () {
        currentTmpl = TMPL_DATA[this.value] || null;
        varChips.innerHTML = '';
        varSummary.style.display = 'none';
        if (!currentTmpl) return;

        currentTmpl.auto_vars.forEach(function (v) {
            varChips.insertAdjacentHTML('beforeend',
                '<span class="var-chip auto">'
                + '<i class="bi bi-check-circle-fill" style="font-size:0.65rem"></i>'
                + '{{' + v + '}}'
                + '</span>'
            );
        });

        currentTmpl.manual_vars.forEach(function (v) {
            varChips.insertAdjacentHTML('beforeend',
                '<span class="var-chip manual">'
                + '<i class="bi bi-pencil-fill" style="font-size:0.65rem"></i>'
                + '{{' + v + '}}'
                + '</span>'
            );
        });

        varSummary.style.display = currentTmpl.all_vars.length > 0 ? '' : 'none';
    });

    /* ── Send mode toggle ── */
    document.querySelectorAll('input[name="uiMode"]').forEach(function (r) {
        r.addEventListener('change', function () {
            currentMode = this.value;
            fieldSingle.style.display = currentMode === 'single' ? '' : 'none';
            fieldGroup.style.display  = currentMode === 'group'  ? '' : 'none';
        });
    });

    /* ── Step 1 → 2 ── */
    document.getElementById('btnStep1Next').addEventListener('click', function () {
        step1Error.textContent = '';
        if (!currentTmpl) { step1Error.textContent = 'Please select a template.'; return; }
        if (currentMode === 'single' && !uiToEmail.value.trim()) { step1Error.textContent = 'Please enter the recipient email address.'; return; }
        if (currentMode === 'group'  && !uiRecipients.value)     { step1Error.textContent = 'Please select a recipient group.'; return; }
        buildDynamicForm(currentTmpl.manual_vars);
        showStep(2);
    });

    /* ── Build variable input form ── */
    function labelFromKey(key) {
        return key.replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); });
    }

    function buildDynamicForm(manualVars) {
        dynForm.innerHTML = '';
        if (manualVars.length === 0) { noManualMsg.style.display = ''; return; }
        noManualMsg.style.display = 'none';
        dynForm.insertAdjacentHTML('beforeend', '<div class="field-section-label mb-3">Required Variables</div>');
        manualVars.forEach(function (varKey) {
            var lbl  = labelFromKey(varKey);
            var code = '{{' + varKey + '}}';
            var ph   = 'Value for {{' + varKey + '}}';
            dynForm.insertAdjacentHTML('beforeend',
                '<div class="mb-3">'
                + '<label class="form-label" style="font-size:0.82rem;font-weight:600">'
                + lbl
                + ' <code style="font-size:0.71rem;background:#fef3c7;color:#92400e;padding:1px 5px;border-radius:3px;margin-left:4px">' + code + '</code>'
                + '</label>'
                + '<input type="text" id="var_' + varKey + '" class="form-control dynamic-var-input"'
                + ' data-var="' + varKey + '" placeholder="' + ph + '" autocomplete="off">'
                + '</div>'
            );
        });
    }

    /* ── Step 2 back ── */
    document.getElementById('btnStep2Back').addEventListener('click', function () { showStep(1); });

    /* ── Step 2 → Preview ── */
    document.getElementById('btnStep2Preview').addEventListener('click', async function () {
        step2Error.textContent = '';

        var adminVars = {};
        document.querySelectorAll('.dynamic-var-input').forEach(function (inp) {
            adminVars[inp.dataset.var] = inp.value;
        });

        if (currentTmpl.manual_vars.length > 0) {
            var missing = currentTmpl.manual_vars.filter(function (k) {
                return !(adminVars[k] || '').trim();
            });
            if (missing.length > 0) {
                step2Error.textContent = 'Please fill in: ' + missing.map(labelFromKey).join(', ');
                return;
            }
        }

        var payload = {
            template_slug : currentTmpl.slug,
            vars          : adminVars,
            mode          : currentMode,
            to_email      : currentMode === 'single' ? uiToEmail.value    : '',
            recipients    : currentMode === 'group'  ? uiRecipients.value : ''
        };

        var btn = document.getElementById('btnStep2Preview');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Loading\u2026';

        try {
            var resp = await fetch(PREVIEW_URL, {
                method  : 'POST',
                headers : { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                body    : JSON.stringify(payload)
            });

            if (!resp.ok) {
                var e = await resp.json().catch(function () { return {}; });
                step2Error.textContent = e.message || 'Preview failed.';
                return;
            }

            var data = await resp.json();

            previewSubj.textContent           = data.subject;
            previewRecip.textContent          = data.recipient_info;
            previewName.textContent           = currentTmpl.name;
            previewBadge.textContent          = currentMode === 'group' ? 'Group Send' : 'Single';
            previewSample.style.display       = data.is_sample ? '' : 'none';
            previewFrame.srcdoc               = data.body_html;

            hiddenMode.value    = currentMode;
            hiddenSlug.value    = currentTmpl.slug;
            hiddenToEmail.value = currentMode === 'single' ? uiToEmail.value    : '';
            hiddenRecips.value  = currentMode === 'group'  ? uiRecipients.value : '';
            hiddenSubj.value    = data.subject;
            hiddenBody.value    = data.body_html;

            hiddenVarsCont.innerHTML = '';
            Object.keys(adminVars).forEach(function (k) {
                var inp = document.createElement('input');
                inp.type  = 'hidden';
                inp.name  = 'vars[' + k + ']';
                inp.value = adminVars[k];
                hiddenVarsCont.appendChild(inp);
            });

            showStep(3);

        } catch (err) {
            step2Error.textContent = 'Network error. Please try again.';
        } finally {
            btn.disabled  = false;
            btn.innerHTML = '<i class="bi bi-eye me-1"></i> Preview Email';
        }
    });

    /* ── Step 3 back ── */
    document.getElementById('btnStep3Back').addEventListener('click', function () { showStep(2); });

    /* ── Send ── */
    sendForm.addEventListener('submit', function () {
        btnSend.disabled          = true;
        btnSend.style.display     = 'none';
        sendSpinner.style.display = '';
    });

    showStep(1);

})();
</script>
@endpush
