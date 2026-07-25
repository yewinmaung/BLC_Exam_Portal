@extends('layouts.app')
@section('title', 'Preview — ' . $template->name)
@section('page-title', 'Template Preview')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Email', 'url' => route('admin.email.index')],
        ['label' => 'Templates', 'url' => route('admin.email.templates')],
        ['label' => 'Preview'],
    ]])
@endsection
@section('sidebar')@include('partials.admin-sidebar')@endsection

@section('content')
<div style="max-width:720px">

    {{-- ── Actions ─────────────────────────────────────────────────────── --}}
    <div class="d-flex gap-2 mb-3">
        <a href="{{ route('admin.email.templates') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
        <a href="{{ route('admin.email.templates.edit', $template) }}" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
    </div>

    {{-- ── Meta strip ──────────────────────────────────────────────────── --}}
    <div class="card mb-3">
        <div class="card-body py-2 px-3" style="font-size:0.83rem">
            <strong>Slug:</strong> <code>{{ $template->slug }}</code>
            &nbsp;|&nbsp;
            <strong>Subject:</strong> {{ $rendered['subject'] }}
            &nbsp;|&nbsp;
            <strong>Status:</strong>
            <span style="color:{{ $template->is_active ? '#059669' : '#6b7280' }};font-weight:700">
                {{ $template->is_active ? 'Active' : 'Inactive' }}
            </span>
        </div>
    </div>

    {{-- ── Preview card ─────────────────────────────────────────────────── --}}
    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <span><i class="bi bi-eye me-2"></i>Rendered Preview (sample data)</span>
            <button type="button"
                    id="toggleSource"
                    class="btn btn-outline-secondary btn-sm"
                    title="Toggle HTML source">
                <i class="bi bi-code-slash me-1"></i>View Source
            </button>
        </div>

        {{-- Visual rendered preview --}}
        <div id="previewPane" class="card-body p-0" style="background:#f4f6fb;border-radius:0 0 8px 8px;overflow:hidden">
            <iframe id="previewFrame"
                    style="width:100%;border:none;display:block;min-height:200px"
                    title="Template Preview">
            </iframe>
        </div>

        {{-- Raw HTML source (hidden by default) --}}
        <div id="sourcePane" class="card-body p-0" style="display:none">
            <pre style="margin:0;padding:16px 20px;font-size:0.76rem;line-height:1.6;background:#1e1e2e;color:#cdd6f4;border-radius:0 0 8px 8px;overflow-x:auto;white-space:pre-wrap;word-break:break-all">{{ $rendered['bodyHtml'] }}</pre>
        </div>
    </div>

</div>

@push('scripts')
<script>
(function () {
    'use strict';

    // ── Write HTML into iframe via JS (reliable cross-browser) ──────────
    const frame = document.getElementById('previewFrame');
    if (frame) {
        const html = @json($rendered['bodyHtml'] ?? '');
        const doc  = frame.contentDocument || frame.contentWindow.document;
        doc.open();
        doc.write(html);
        doc.close();

        // Auto-resize to full content height
        function resize() {
            try {
                const h = doc.documentElement.scrollHeight || doc.body.scrollHeight;
                if (h > 100) frame.style.height = (h + 20) + 'px';
            } catch (e) {}
        }

        frame.addEventListener('load', resize);
        setTimeout(resize, 300);
        setTimeout(resize, 800);
    }

    // ── Toggle preview ↔ source ─────────────────────────────────────────
    const btn         = document.getElementById('toggleSource');
    const previewPane = document.getElementById('previewPane');
    const sourcePane  = document.getElementById('sourcePane');

    btn?.addEventListener('click', function () {
        const showingPreview = previewPane.style.display !== 'none';
        previewPane.style.display = showingPreview ? 'none' : '';
        sourcePane.style.display  = showingPreview ? ''     : 'none';
        this.innerHTML = showingPreview
            ? '<i class="bi bi-eye me-1"></i>View Preview'
            : '<i class="bi bi-code-slash me-1"></i>View Source';
    });
})();
</script>
@endpush

@endsection
