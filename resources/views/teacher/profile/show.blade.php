@extends('layouts.app')
@section('title', 'My Profile')
@section('page-title', 'My Profile')
@section('breadcrumbs')
    @include('partials.breadcrumbs', ['items' => [
        ['label' => 'Teacher', 'url' => route('teacher.dashboard')],
        ['label' => 'My Profile'],
    ]])
@endsection
@section('sidebar')
@include('partials.teacher-sidebar')
@endsection

@push('styles')
<style>
/* ── Profile info card ── */
.teacher-profile-avatar {
    width: 80px; height: 80px; border-radius: 50%;
    background: linear-gradient(135deg, var(--blc-navy, #0b2a5b), var(--blc-navy-2, #1a3d7c));
    color: #fff; font-size: 2rem; font-weight: 800;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 1rem;
    box-shadow: 0 4px 16px rgba(11,42,91,0.18);
}
.stat-box {
    text-align: center; padding: 0.75rem 0.5rem;
    border-radius: 10px; background: #f8faff;
    border: 1px solid #e8edf8;
}
.stat-box .stat-value {
    font-size: 1.5rem; font-weight: 800;
    color: var(--blc-navy, #0b2a5b); line-height: 1;
}
.stat-box .stat-label {
    font-size: 0.72rem; color: #6b7280;
    font-weight: 600; text-transform: uppercase;
    letter-spacing: 0.04em; margin-top: 0.25rem;
}

/* ── Section card ── */
.section-card {
    background: #fff; border-radius: 14px;
    border: 1px solid #e2e8f0;
    box-shadow: 0 2px 10px rgba(11,42,91,0.05);
    margin-bottom: 1.5rem; overflow: hidden;
}
.section-card-header {
    background: linear-gradient(135deg, #071d40, #0b2a5b);
    color: #fff; padding: 0.85rem 1.25rem;
    font-size: 0.88rem; font-weight: 700;
    display: flex; align-items: center; gap: 0.5rem;
}
.section-card-body { padding: 1.5rem 1.25rem; }

/* ── Password strength bar ── */
.pw-strength { height: 4px; border-radius: 2px; transition: width .3s, background .3s; margin-top: 6px; }
.pw-hint { font-size: 0.72rem; color: #9ca3af; margin-top: 4px; }

/* Password show/hide toggle */
.pw-toggle-btn {
    position: absolute; right: 0.85rem; top: 50%;
    transform: translateY(-50%);
    background: none; border: none; color: #9ca3af;
    cursor: pointer; font-size: 1rem; padding: 0;
    line-height: 1; transition: color 0.15s;
}
.pw-toggle-btn:hover { color: var(--blc-royal, #2d27a0); }

/* ── Course Cards ── */
.course-card-link { text-decoration: none; display: block; }
.course-card {
    background: #fff;
    border: 1.5px solid #e2e8f0;
    border-radius: 12px;
    padding: 1rem 1.1rem;
    transition: all 0.18s;
    cursor: pointer;
    height: 100%;
}
.course-card:hover {
    border-color: var(--blc-royal, #2d27a0);
    box-shadow: 0 6px 20px rgba(45,39,160,0.12);
    transform: translateY(-2px);
}
.course-card-icon {
    width: 42px; height: 42px; border-radius: 10px;
    background: #eef2ff;
    color: var(--blc-royal, #2d27a0);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.2rem; flex-shrink: 0;
    margin-bottom: 0.75rem;
}
.course-card-title {
    font-size: 0.9rem; font-weight: 700;
    color: var(--blc-navy, #0b2a5b);
    line-height: 1.35; margin-bottom: 0.3rem;
}
.course-card-code {
    font-size: 0.78rem; font-weight: 600;
    color: var(--blc-royal, #2d27a0);
    background: #eef2ff;
    padding: 2px 8px; border-radius: 20px;
    display: inline-block;
}
.course-card-meta {
    font-size: 0.75rem; color: #6b7280;
    margin-top: 0.4rem;
}
</style>
@endpush

@section('content')
<div class="row g-3">

    {{-- ── Left column: Profile info + Change Password ── --}}
    <div class="col-md-4 col-lg-4">

        {{-- Profile card --}}
        <div class="section-card">
            <div class="section-card-header">
                <i class="bi bi-person-circle"></i> Profile
            </div>
            <div class="card-body text-center p-4">
                <div class="teacher-profile-avatar">
                    {{ strtoupper(substr($teacher->name, 0, 1)) }}
                </div>
                <h5 class="mb-1 fw-800" style="color:var(--blc-navy,#0b2a5b)">{{ $teacher->name }}</h5>
                <p class="text-muted small mb-2">{{ $teacher->email }}</p>
                @if($teacher->phone)
                <p class="small mb-2"><i class="bi bi-telephone me-1"></i>{{ $teacher->phone }}</p>
                @endif
                <span class="badge" style="background:#eef2ff;color:var(--blc-royal,#2d27a0);font-size:0.78rem;font-weight:700">
                    <i class="bi bi-mortarboard-fill me-1"></i>Teacher
                </span>
                <hr class="my-3">
                <div class="row g-2">
                    <div class="col-4">
                        <div class="stat-box">
                            <div class="stat-value">{{ $stats['courses'] }}</div>
                            <div class="stat-label">Courses</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="stat-box">
                            <div class="stat-value">{{ $stats['exams'] }}</div>
                            <div class="stat-label">Exams</div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="stat-box">
                            <div class="stat-value" style="color:{{ $stats['pending'] > 0 ? '#d97706' : 'var(--blc-navy,#0b2a5b)' }}">
                                {{ $stats['pending'] }}
                            </div>
                            <div class="stat-label">Pending</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Change Password card --}}
        <div class="section-card">
            <div class="section-card-header">
                <i class="bi bi-shield-lock-fill"></i> Change Password
            </div>
            <div class="section-card-body">
                <p class="text-muted" style="font-size:0.83rem;margin-bottom:1.25rem;">
                    Enter and confirm your new password below. A confirmation email will be sent after the change is applied.
                </p>

                <div class="mb-3">
                    <label class="form-label fw-semibold" style="font-size:0.82rem;">New Password</label>
                    <div style="position:relative;">
                        <input type="password" id="newPassword" class="form-control"
                               placeholder="Min 8 chars, upper+lower+number"
                               autocomplete="new-password"
                               style="padding-right:2.8rem">
                        <button type="button" class="pw-toggle-btn" tabindex="-1"
                                onclick="togglePw('newPassword','pwIcon1')">
                            <i class="bi bi-eye" id="pwIcon1"></i>
                        </button>
                    </div>
                    <div class="pw-strength" id="pwStrengthBar" style="width:0%;background:#ef4444"></div>
                    <div class="pw-hint" id="pwHint"></div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold" style="font-size:0.82rem;">Confirm New Password</label>
                    <div style="position:relative;">
                        <input type="password" id="confirmPassword" class="form-control"
                               placeholder="Repeat password"
                               autocomplete="new-password"
                               style="padding-right:2.8rem">
                        <button type="button" class="pw-toggle-btn" tabindex="-1"
                                onclick="togglePw('confirmPassword','pwIcon2')">
                            <i class="bi bi-eye" id="pwIcon2"></i>
                        </button>
                    </div>
                </div>

                <div id="pwStepMsg"></div>

                <button type="button" class="btn btn-primary w-100" id="btnChangePassword" style="font-weight:700;">
                    <i class="bi bi-shield-lock-fill me-1"></i> Change Password
                </button>
            </div>
        </div>

    </div>

    {{-- ── Right column: Assigned Courses ── --}}
    <div class="col-md-8 col-lg-8">

        <div class="section-card">
            <div class="section-card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-book-fill me-1"></i> Assigned Courses</span>
                <span class="badge" style="background:rgba(255,255,255,0.2);color:#fff;font-size:0.78rem">
                    {{ $teacher->taughtCourses->count() }} course{{ $teacher->taughtCourses->count() !== 1 ? 's' : '' }}
                </span>
            </div>
            <div class="card-body p-3">

                @if($teacher->taughtCourses->isEmpty())
                <div class="text-center py-5 text-muted">
                    <i class="bi bi-book d-block mb-2" style="font-size:2.5rem;opacity:0.35"></i>
                    <p class="mb-0">No courses have been assigned yet.</p>
                    <small>Contact your administrator to get courses assigned.</small>
                </div>
                @else

                @php
                    $ylLabels = \App\Models\Course::$yearLevelLabels;
                    $ylColors = [
                        0 => ['bg'=>'#f1f5f9','color'=>'#475569'],
                        1 => ['bg'=>'#eef2ff','color'=>'#2d27a0'],
                        2 => ['bg'=>'#fdf4ff','color'=>'#7e22ce'],
                        3 => ['bg'=>'#fff7ed','color'=>'#c2410c'],
                        4 => ['bg'=>'#f0fdf4','color'=>'#166534'],
                        5 => ['bg'=>'#fff1f2','color'=>'#be123c'],
                    ];
                    $grouped = $teacher->taughtCourses
                        ->sortBy([
                            fn ($c) => $c->year_level ?? 0,
                            fn ($c) => $c->semester ?? 0,
                            fn ($c) => $c->title,
                        ])
                        ->groupBy(fn ($c) => $c->year_level ?? 0);
                @endphp

                @foreach($grouped->sortKeys() as $yl => $ylCourses)
                @php
                    $ylLabel  = $ylLabels[$yl] ?? 'Year ' . $yl;
                    $ylColor  = $ylColors[$yl] ?? ['bg'=>'#f3f4f6','color'=>'#374151'];
                    $ylCount  = $ylCourses->count();
                @endphp

                {{-- Year Level section header --}}
                <div class="d-flex align-items-center gap-2 mb-3 {{ !$loop->first ? 'mt-4' : '' }}">
                    <span style="display:inline-flex;align-items:center;justify-content:center;
                                 height:24px;padding:0 10px;border-radius:12px;
                                 background:{{ $ylColor['bg'] }};color:{{ $ylColor['color'] }};
                                 font-size:0.72rem;font-weight:800;white-space:nowrap;flex-shrink:0">
                        <i class="bi bi-layers me-1"></i>{{ $ylLabel }}
                    </span>
                    <span style="font-size:0.7rem;color:#9ca3af;font-weight:600">
                        {{ $ylCount }} course{{ $ylCount !== 1 ? 's' : '' }}
                    </span>
                    <div style="flex:1;height:1px;background:linear-gradient(to right,{{ $ylColor['bg'] }},transparent)"></div>
                </div>

                {{-- Course cards --}}
                <div class="row g-3">
                    @foreach($ylCourses as $course)
                    <div class="col-sm-6 col-lg-4">
                        <a href="{{ route('teacher.profile.course-detail', $course) }}" class="course-card-link">
                            <div class="course-card" style="border-top:3px solid {{ $ylColor['color'] }}30">
                                <div class="course-card-icon" style="background:{{ $ylColor['bg'] }};color:{{ $ylColor['color'] }}">
                                    <i class="bi bi-book-half"></i>
                                </div>
                                <div class="course-card-title">{{ $course->title }}</div>
                                @if($course->code)
                                <span class="course-card-code" style="background:{{ $ylColor['bg'] }};color:{{ $ylColor['color'] }}">
                                    {{ $course->code }}
                                </span>
                                @endif
                                <div class="course-card-meta">
                                    <i class="bi bi-bookmark me-1"></i>{{ $course->semesterLabel }}
                                </div>
                            </div>
                        </a>
                    </div>
                    @endforeach
                </div>

                @endforeach {{-- /year level --}}

                @endif

            </div>
        </div>

    </div>
</div>
@endsection

@push('scripts')
<script>
window.PROFILE_CONFIG = {
    photoUrl:    "{{ route('profile.photo') }}",
    passwordUrl: "{{ route('profile.password') }}",
    csrf:        "{{ csrf_token() }}",
    hasPhoto:    false,
};

function togglePw(inputId, iconId) {
    const input = document.getElementById(inputId);
    const icon  = document.getElementById(iconId);
    if (!input || !icon) return;
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        input.type = 'password';
        icon.className = 'bi bi-eye';
    }
}
</script>
<script src="{{ asset('js/profile.js') }}"></script>
@endpush
