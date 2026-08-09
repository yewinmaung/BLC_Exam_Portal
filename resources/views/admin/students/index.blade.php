@extends('layouts.app')
@section('title', 'Students')
@section('page-title', 'Students')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Admin', 'url' => route('admin.dashboard')],
        ['label' => 'Students'],
    ]])
@endsection
@section('sidebar')
@include('partials.admin-sidebar')
@endsection

@section('content')
<div class="page-header">
    <div></div>
    @if(Route::has('admin.students.create'))
    <a href="{{ route('admin.students.create') }}" class="btn btn-primary">
        <i class="bi bi-person-plus me-1"></i> Add Student
    </a>
    @endif
</div>

{{-- ── View-mode toggle tabs ── --}}
<ul class="nav nav-tabs mb-3" id="studentViewTabs" role="tablist" style="border-bottom:2px solid #e2e8f0">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="tab-list" data-bs-toggle="tab" data-bs-target="#panel-list"
                type="button" role="tab" style="font-size:0.845rem;font-weight:600">
            <i class="bi bi-list-ul me-1"></i> All Students
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-group" data-bs-toggle="tab" data-bs-target="#panel-group"
                type="button" role="tab" style="font-size:0.845rem;font-weight:600">
            <i class="bi bi-diagram-3 me-1"></i> By Group
        </button>
    </li>
</ul>

<div class="tab-content" id="studentViewContent">

{{-- ═══════════════════════════════════════════════════════════
     TAB 1 — ALL STUDENTS (existing table view)
═══════════════════════════════════════════════════════════ --}}
<div class="tab-pane fade show active" id="panel-list" role="tabpanel">

    {{-- Search & Filter --}}
    <div class="card mb-3">
        <div class="card-body py-2 px-3">
            <form method="GET" action="{{ route('admin.students.index') }}">
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <div class="input-group" style="max-width:320px">
                        <span class="input-group-text" style="background:#f8f9fc;border-right:0;border-color:#e2e8f0">
                            <i class="bi bi-search" style="color:#9ca3af;font-size:0.8rem"></i>
                        </span>
                        <input type="text" name="search" value="{{ request('search') }}"
                               class="form-control" style="border-left:0;border-color:#e2e8f0;font-size:0.855rem"
                               placeholder="Name or email…" maxlength="100" autocomplete="off">
                        @if(request('search'))
                        <a href="{{ route('admin.students.index', request()->except('search','page')) }}"
                           class="input-group-text text-muted" style="background:#f8f9fc;border-color:#e2e8f0;text-decoration:none">
                            <i class="bi bi-x"></i>
                        </a>
                        @endif
                    </div>
                    <select name="year_level_id" class="form-select form-select-sm" style="max-width:150px;font-size:0.8rem">
                        <option value="">All Year Levels</option>
                        @foreach($yearLevels as $yl)
                        <option value="{{ $yl->id }}" {{ request('year_level_id') == $yl->id ? 'selected' : '' }}>
                            {{ $yl->name }}
                        </option>
                        @endforeach
                    </select>
                    <select name="status" class="form-select form-select-sm" style="max-width:130px;font-size:0.8rem">
                        <option value="">All Status</option>
                        <option value="active"   {{ request('status') === 'active'   ? 'selected' : '' }}>Active</option>
                        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Inactive</option>
                    </select>
                    <button type="submit" class="btn btn-primary btn-sm px-3">
                        <i class="bi bi-funnel-fill me-1"></i>Filter
                    </button>
                    @if(request()->hasAny(['search','year_level_id','status']))
                    <a href="{{ route('admin.students.index') }}" class="btn btn-outline-secondary btn-sm" title="Reset">
                        <i class="bi bi-arrow-counterclockwise"></i>
                    </a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex align-items-center justify-content-between">
            <span><i class="bi bi-mortarboard me-2"></i>All Students</span>
            <span class="badge" style="background:var(--royal-light,#ede9fe);color:var(--royal,#3730a3)">
                {{ $students->total() }} total
            </span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Enrollments</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($students as $s)
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <div style="width:30px;height:30px;border-radius:50%;background:linear-gradient(135deg,#0e6b6b,#0d9488);color:#fff;display:flex;align-items:center;justify-content:center;font-size:0.72rem;font-weight:700;flex-shrink:0">
                                        {{ strtoupper(substr($s->name,0,1)) }}
                                    </div>
                                    <span style="font-weight:600">{{ $s->name }}</span>
                                </div>
                            </td>
                            <td class="text-muted">{{ $s->email }}</td>
                            <td>
                                <span class="badge" style="background:#ede9fe;color:#3730a3">
                                    {{ $s->enrollments_count }}
                                </span>
                            </td>
                            <td>
                                @if($s->is_active)
                                    <span class="status-pill status-approved">Active</span>
                                @else
                                    <span class="status-pill status-closed">Inactive</span>
                                @endif
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <a href="{{ route('admin.students.show', $s) }}" class="btn btn-sm btn-outline-primary" title="View">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    <a href="{{ route('admin.students.edit', $s) }}" class="btn btn-sm btn-outline-secondary" title="Edit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form action="{{ route('admin.students.destroy', $s) }}" method="POST" class="d-inline"
                                          onsubmit="return confirm('Permanently delete {{ addslashes($s->name) }}?')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" title="Delete">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="bi bi-mortarboard d-block mb-2" style="font-size:2rem;opacity:0.3"></i>
                                No students found.
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($students->hasPages())
            <div class="p-3 border-top d-flex align-items-center justify-content-between flex-wrap gap-2">
                <span class="text-muted" style="font-size:0.8rem">
                    Showing {{ $students->firstItem() }} to {{ $students->lastItem() }} of {{ $students->total() }} entries
                </span>
                {{ $students->links() }}
            </div>
            @endif
        </div>
    </div>

</div>{{-- /panel-list --}}

{{-- ═══════════════════════════════════════════════════════════
     TAB 2 — BY GROUP (accordion: AY → Year Level → Semester → Major)
═══════════════════════════════════════════════════════════ --}}
<div class="tab-pane fade" id="panel-group" role="tabpanel">

    @php
        // Sort academic years newest-first using the start_year lookup we passed from the controller
        $sortedGrouped = collect($groupedRecords)->sortByDesc(function ($ylGroups, $ayName) use ($ayStartYears) {
            return $ayStartYears[$ayName] ?? 0;
        });
    @endphp

    @if($sortedGrouped->isEmpty())
    <div class="card">
        <div class="card-body text-center py-5 text-muted">
            <i class="bi bi-diagram-3 d-block mb-2" style="font-size:2rem;opacity:0.3"></i>
            No active student year records found.
        </div>
    </div>
    @else

    {{-- ── Expand / Collapse all ── --}}
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div style="font-size:0.82rem;color:#6b7280">
            <i class="bi bi-info-circle me-1"></i>
            Showing only students with an <strong>active</strong> year record.
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnExpandAll">
                <i class="bi bi-chevron-double-down me-1"></i>Expand All
            </button>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnCollapseAll">
                <i class="bi bi-chevron-double-up me-1"></i>Collapse All
            </button>
        </div>
    </div>

    <div class="accordion" id="groupAccordion">

    @foreach($sortedGrouped as $ayName => $ylGroups)
    @php
        $aySlug      = 'ay-' . \Illuminate\Support\Str::slug($ayName);
        $ayTotal     = collect($ylGroups)->flatten(3)->count();
    @endphp

    {{-- ── Academic Year panel ── --}}
    <div class="accordion-item mb-2 border rounded overflow-hidden shadow-sm" style="border-color:#e2e8f0!important">

        <h2 class="accordion-header" id="hd-{{ $aySlug }}">
            <button class="accordion-button collapsed"
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#col-{{ $aySlug }}"
                    aria-expanded="false"
                    aria-controls="col-{{ $aySlug }}"
                    style="background:#f0f4ff;font-weight:700;font-size:0.9rem;color:#1a2540">
                <div class="d-flex align-items-center gap-3 w-100 me-2">
                    <div style="width:36px;height:36px;border-radius:10px;background:linear-gradient(135deg,#1e1b6e,#3730a3);display:flex;align-items:center;justify-content:center;flex-shrink:0">
                        <i class="bi bi-calendar3" style="color:#fff;font-size:0.95rem"></i>
                    </div>
                    <div style="flex:1;min-width:0">
                        <div style="font-weight:700;color:#1a2540">{{ $ayName }}</div>
                        <div style="font-size:0.72rem;font-weight:500;color:#6b7280;margin-top:1px">Academic Year</div>
                    </div>
                    <span class="badge me-2" style="background:#eef2ff;color:#3730a3;font-size:0.73rem;font-weight:700;padding:4px 10px;border-radius:20px">
                        {{ $ayTotal }} student{{ $ayTotal !== 1 ? 's' : '' }}
                    </span>
                </div>
            </button>
        </h2>

        <div id="col-{{ $aySlug }}"
             class="accordion-collapse collapse"
             aria-labelledby="hd-{{ $aySlug }}"
             data-bs-parent="">

            <div class="accordion-body p-0">

            @foreach($ylGroups as $ylName => $semGroups)
            @php
                $ylSlug  = $aySlug . '-yl-' . \Illuminate\Support\Str::slug($ylName);
                $ylTotal = collect($semGroups)->flatten(2)->count();
            @endphp

            {{-- ── Year Level nested accordion ── --}}
            <div class="accordion" id="acc-{{ $ylSlug }}">
                <div class="accordion-item border-0 border-top" style="border-top-color:#e8eaf2!important">

                    <h2 class="accordion-header" id="hd-{{ $ylSlug }}">
                        <button class="accordion-button collapsed ps-4"
                                type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#col-{{ $ylSlug }}"
                                aria-expanded="false"
                                style="background:#f7f9ff;font-size:0.855rem;font-weight:700;color:#3730a3">
                            <div class="d-flex align-items-center gap-2 w-100 me-2">
                                <i class="bi bi-layers" style="color:#6366f1;font-size:0.95rem;flex-shrink:0"></i>
                                <span style="flex:1">{{ $ylName }}</span>
                                <span class="badge me-2" style="background:#e0e7ff;color:#3730a3;font-size:0.7rem;font-weight:700;padding:3px 9px;border-radius:20px">
                                    {{ $ylTotal }} student{{ $ylTotal !== 1 ? 's' : '' }}
                                </span>
                            </div>
                        </button>
                    </h2>

                    <div id="col-{{ $ylSlug }}"
                         class="accordion-collapse collapse"
                         aria-labelledby="hd-{{ $ylSlug }}"
                         data-bs-parent="">

                        <div class="accordion-body p-0">

                        @foreach($semGroups as $semLabel => $majorGroups)
                        @php
                            $semSlug  = $ylSlug . '-' . \Illuminate\Support\Str::slug($semLabel);
                            $semTotal = collect($majorGroups)->flatten(1)->count();
                            $semNum   = (int) filter_var($semLabel, FILTER_SANITIZE_NUMBER_INT);
                        @endphp

                        {{-- ── Semester nested accordion ── --}}
                        <div class="accordion" id="acc-{{ $semSlug }}">
                            <div class="accordion-item border-0 border-top" style="border-top-color:#eef0f8!important">

                                <h2 class="accordion-header" id="hd-{{ $semSlug }}">
                                    <button class="accordion-button collapsed ps-5"
                                            type="button"
                                            data-bs-toggle="collapse"
                                            data-bs-target="#col-{{ $semSlug }}"
                                            aria-expanded="false"
                                            style="background:#fafbff;font-size:0.825rem;font-weight:600;color:#4f46e5">
                                        <div class="d-flex align-items-center gap-2 w-100 me-2">
                                            <i class="bi bi-{{ $semNum === 1 ? '1' : ($semNum === 2 ? '2' : 'infinity') }}-circle"
                                               style="color:#7c3aed;font-size:0.9rem;flex-shrink:0"></i>
                                            <span style="flex:1">{{ $semLabel }}</span>
                                            <span class="badge me-2" style="background:#f3f4f6;color:#7c3aed;font-size:0.68rem;font-weight:700;padding:3px 8px;border-radius:20px">
                                                {{ $semTotal }} student{{ $semTotal !== 1 ? 's' : '' }}
                                            </span>
                                        </div>
                                    </button>
                                </h2>

                                <div id="col-{{ $semSlug }}"
                                     class="accordion-collapse collapse"
                                     aria-labelledby="hd-{{ $semSlug }}"
                                     data-bs-parent="">

                                    <div class="accordion-body p-0">

                                    @foreach($majorGroups as $majorName => $records)
                                    @php
                                        $majSlug  = $semSlug . '-' . \Illuminate\Support\Str::slug($majorName);
                                        $majTotal = $records->count();
                                        $isMajor  = $majorName !== 'No Major';
                                    @endphp

                                    {{-- ── Major group ── --}}
                                    <div class="border-top" style="border-top-color:#f1f3f9!important">

                                        {{-- Major header chip --}}
                                        <div class="d-flex align-items-center justify-content-between px-4 py-2"
                                             style="background:#fff;cursor:pointer"
                                             data-bs-toggle="collapse"
                                             data-bs-target="#col-{{ $majSlug }}"
                                             aria-expanded="true">
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="bi bi-collection" style="color:{{ $isMajor ? '#0891b2' : '#9ca3af' }};font-size:0.85rem"></i>
                                                @if($isMajor)
                                                <span class="badge" style="background:#e0f2fe;color:#0369a1;font-size:0.75rem;font-weight:700;padding:3px 10px;border-radius:20px">
                                                    {{ $majorName }}
                                                </span>
                                                @else
                                                <span style="font-size:0.8rem;font-weight:600;color:#9ca3af">
                                                    No Major (Year 1)
                                                </span>
                                                @endif
                                            </div>
                                            <div class="d-flex align-items-center gap-2">
                                                <span class="badge" style="background:#f3f4f6;color:#374151;font-size:0.68rem;font-weight:600;padding:2px 8px;border-radius:20px">
                                                    {{ $majTotal }} student{{ $majTotal !== 1 ? 's' : '' }}
                                                </span>
                                                <i class="bi bi-chevron-down" style="font-size:0.75rem;color:#9ca3af;transition:transform 0.2s" id="chev-{{ $majSlug }}"></i>
                                            </div>
                                        </div>

                                        {{-- Student table --}}
                                        <div id="col-{{ $majSlug }}" class="collapse show">
                                            <table class="table table-sm mb-0" style="font-size:0.835rem">
                                                <thead style="background:#f8f9fc">
                                                    <tr>
                                                        <th style="padding:0.4rem 1.5rem;font-size:0.68rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;border-bottom:1.5px solid #e8eaf2;width:40px">#</th>
                                                        <th style="padding:0.4rem 0.75rem;font-size:0.68rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;border-bottom:1.5px solid #e8eaf2">Student</th>
                                                        <th style="padding:0.4rem 0.75rem;font-size:0.68rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;border-bottom:1.5px solid #e8eaf2">Email</th>
                                                        <th style="padding:0.4rem 0.75rem;font-size:0.68rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;border-bottom:1.5px solid #e8eaf2">Status</th>
                                                        <th style="padding:0.4rem 0.75rem;font-size:0.68rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:0.04em;border-bottom:1.5px solid #e8eaf2;width:80px"></th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($records->sortBy(fn($r) => $r->student?->name) as $rowIdx => $rec)
                                                    @php $stu = $rec->student; @endphp
                                                    @if($stu)
                                                    <tr style="transition:background 0.1s" onmouseover="this.style.background='#f8f9ff'" onmouseout="this.style.background=''">
                                                        <td style="padding:0.55rem 1.5rem;color:#9ca3af;font-size:0.75rem">
                                                            {{ $loop->iteration }}
                                                        </td>
                                                        <td style="padding:0.55rem 0.75rem">
                                                            <div class="d-flex align-items-center gap-2">
                                                                <div style="width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,#0e6b6b,#0d9488);color:#fff;display:flex;align-items:center;justify-content:center;font-size:0.68rem;font-weight:700;flex-shrink:0">
                                                                    {{ strtoupper(substr($stu->name,0,1)) }}
                                                                </div>
                                                                <span style="font-weight:600;color:#111827">{{ $stu->name }}</span>
                                                            </div>
                                                        </td>
                                                        <td style="padding:0.55rem 0.75rem;color:#6b7280;font-size:0.8rem">
                                                            {{ $stu->email }}
                                                        </td>
                                                        <td style="padding:0.55rem 0.75rem">
                                                            @if($stu->is_active)
                                                                <span class="status-pill status-approved" style="font-size:0.68rem">Active</span>
                                                            @else
                                                                <span class="status-pill status-closed" style="font-size:0.68rem">Inactive</span>
                                                            @endif
                                                        </td>
                                                        <td style="padding:0.55rem 0.75rem">
                                                            <div class="d-flex gap-1">
                                                                <a href="{{ route('admin.students.show', $stu) }}"
                                                                   class="btn btn-sm btn-outline-primary"
                                                                   style="padding:2px 7px" title="View">
                                                                    <i class="bi bi-eye" style="font-size:0.75rem"></i>
                                                                </a>
                                                                <a href="{{ route('admin.students.edit', $stu) }}"
                                                                   class="btn btn-sm btn-outline-secondary"
                                                                   style="padding:2px 7px" title="Edit">
                                                                    <i class="bi bi-pencil" style="font-size:0.75rem"></i>
                                                                </a>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                    @endif
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>

                                    </div>{{-- /major group --}}
                                    @endforeach

                                    </div>
                                </div>
                            </div>
                        </div>{{-- /semester accordion --}}
                        @endforeach

                        </div>
                    </div>
                </div>
            </div>{{-- /year level accordion --}}
            @endforeach

            </div>
        </div>
    </div>{{-- /AY accordion-item --}}

    @endforeach
    </div>{{-- /groupAccordion --}}

    @endif
</div>{{-- /panel-group --}}

</div>{{-- /tab-content --}}
@endsection

@push('styles')
<style>
/* ── Accordion tweaks ── */
.accordion-button:not(.collapsed) { box-shadow: none; }
.accordion-button:focus            { box-shadow: none; }
.accordion-button::after           { flex-shrink: 0; }

/* ── Chevron rotation for major collapse ── */
[data-bs-toggle="collapse"][aria-expanded="true"]  #chev-{{ '' }},
.collapse.show + * #chev-slug { transform: rotate(180deg); }
</style>
@endpush

@push('scripts')
<script>
(function () {
    'use strict';

    // ── Expand / Collapse All ────────────────────────────────────────────
    document.getElementById('btnExpandAll')?.addEventListener('click', function () {
        document.querySelectorAll('#panel-group .accordion-collapse:not(.show)')
            .forEach(el => {
                const bsCollapse = bootstrap.Collapse.getOrCreateInstance(el, { toggle: false });
                bsCollapse.show();
            });
    });

    document.getElementById('btnCollapseAll')?.addEventListener('click', function () {
        document.querySelectorAll('#panel-group .accordion-collapse.show')
            .forEach(el => {
                const bsCollapse = bootstrap.Collapse.getOrCreateInstance(el, { toggle: false });
                bsCollapse.hide();
            });
    });

    // ── Chevron rotation for major-level toggles ─────────────────────────
    document.querySelectorAll('#panel-group [data-bs-toggle="collapse"]').forEach(function (btn) {
        const targetId = btn.getAttribute('data-bs-target');
        if (!targetId) return;
        const target = document.querySelector(targetId);
        if (!target) return;

        // Find the chevron inside this specific button
        const chev = btn.querySelector('.bi-chevron-down, .bi-chevron-up');
        if (!chev) return;

        // Sync chevron on events
        target.addEventListener('show.bs.collapse', function () {
            chev.style.transform = 'rotate(180deg)';
        });
        target.addEventListener('hide.bs.collapse', function () {
            chev.style.transform = 'rotate(0deg)';
        });

        // Set initial state
        if (target.classList.contains('show')) {
            chev.style.transform = 'rotate(180deg)';
        }
    });

    // ── Remember active tab in sessionStorage ────────────────────────────
    const tabs = document.querySelectorAll('#studentViewTabs button[data-bs-toggle="tab"]');
    const savedTab = sessionStorage.getItem('students-active-tab');

    if (savedTab) {
        const tab = document.querySelector('#studentViewTabs button[data-bs-target="' + savedTab + '"]');
        if (tab) {
            bootstrap.Tab.getOrCreateInstance(tab).show();
        }
    }

    tabs.forEach(function (tab) {
        tab.addEventListener('shown.bs.tab', function (e) {
            sessionStorage.setItem('students-active-tab', e.target.getAttribute('data-bs-target'));
        });
    });
})();
</script>
@endpush
