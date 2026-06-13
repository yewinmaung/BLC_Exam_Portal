@php
    $items = $items ?? [];
    // Auto-resolve role home URL for first item if no URL given
    if (count($items) > 0 && empty($items[0]['url']) && auth()->check()) {
        $slug = auth()->user()->role->slug ?? '';
        $items[0]['url'] = match($slug) {
            'admin'   => route('admin.dashboard'),
            'teacher' => route('teacher.dashboard'),
            'student' => route('student.dashboard'),
            default   => null,
        };
    }
@endphp
@if(count($items) > 0)
<nav aria-label="breadcrumb" class="mb-1">
    <ol class="breadcrumb mb-0" style="font-size:0.76rem;gap:0.1rem">
        @foreach($items as $i => $item)
            @php($isLast = $i === count($items) - 1)
            @if($isLast)
                <li class="breadcrumb-item active fw-500"
                    aria-current="page"
                    style="color:#9ca3af;font-weight:500">
                    {{ $item['label'] }}
                </li>
            @else
                <li class="breadcrumb-item">
                    @if(!empty($item['url']))
                        <a href="{{ $item['url'] }}"
                           class="text-decoration-none fw-500"
                           style="color:var(--blc-royal,#2d27a0);font-weight:500;transition:color 0.15s"
                           onmouseover="this.style.color='var(--blc-royal-dark,#1e1b6e)'"
                           onmouseout="this.style.color='var(--blc-royal,#2d27a0)'">
                            {{ $item['label'] }}
                        </a>
                    @else
                        <span style="color:#6b7280;font-weight:500">{{ $item['label'] }}</span>
                    @endif
                </li>
            @endif
        @endforeach
    </ol>
</nav>
@endif
