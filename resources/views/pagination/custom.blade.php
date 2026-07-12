@if ($paginator->hasPages())
<nav class="blc-pagination" aria-label="Pagination">
    <ul class="blc-pager">

        {{-- Previous --}}
        @if ($paginator->onFirstPage())
            <li class="blc-page-item disabled" aria-disabled="true">
                <span class="blc-page-link blc-page-prev">
                    <i class="bi bi-chevron-left"></i>
                </span>
            </li>
        @else
            <li class="blc-page-item">
                <a class="blc-page-link blc-page-prev" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous">
                    <i class="bi bi-chevron-left"></i>
                </a>
            </li>
        @endif

        {{-- Page numbers --}}
        @foreach ($elements as $element)

            {{-- Dots --}}
            @if (is_string($element))
                <li class="blc-page-item blc-page-dots" aria-disabled="true">
                    <span class="blc-page-link">{{ $element }}</span>
                </li>
            @endif

            {{-- Links --}}
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <li class="blc-page-item active" aria-current="page">
                            <span class="blc-page-link blc-page-active">{{ $page }}</span>
                        </li>
                    @else
                        <li class="blc-page-item">
                            <a class="blc-page-link" href="{{ $url }}">{{ $page }}</a>
                        </li>
                    @endif
                @endforeach
            @endif

        @endforeach

        {{-- Next --}}
        @if ($paginator->hasMorePages())
            <li class="blc-page-item">
                <a class="blc-page-link blc-page-next" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next">
                    <i class="bi bi-chevron-right"></i>
                </a>
            </li>
        @else
            <li class="blc-page-item disabled" aria-disabled="true">
                <span class="blc-page-link blc-page-next">
                    <i class="bi bi-chevron-right"></i>
                </span>
            </li>
        @endif

    </ul>
</nav>

<style>
/* ── BLC Custom Pagination ─────────────────────────────────────────── */
.blc-pagination { display:inline-flex; }

.blc-pager {
    display: flex;
    align-items: center;
    gap: 3px;
    list-style: none;
    margin: 0;
    padding: 0;
}

.blc-page-link {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 32px;
    height: 32px;
    padding: 0 8px;
    border-radius: 8px;
    font-size: 0.8rem;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.14s ease;
    border: 1.5px solid transparent;
    color: #5a5a7a;
    background: transparent;
    cursor: pointer;
    user-select: none;
    line-height: 1;
}

/* Hover state */
.blc-page-item:not(.disabled):not(.active) .blc-page-link:hover {
    background: #eef2ff;
    color: #3730a3;
    border-color: #c7d2fe;
}

/* Active page */
.blc-page-item.active .blc-page-link {
    background: linear-gradient(135deg, #1e1b6e, #3730a3);
    color: #fff;
    border-color: transparent;
    box-shadow: 0 2px 8px rgba(55, 48, 163, 0.35);
    cursor: default;
}

/* Disabled prev/next */
.blc-page-item.disabled .blc-page-link {
    color: #d1d5db;
    cursor: not-allowed;
    background: transparent;
}

/* Prev / Next arrow buttons */
.blc-page-prev,
.blc-page-next {
    min-width: 32px;
    font-size: 0.72rem;
}

/* Dots */
.blc-page-dots .blc-page-link {
    color: #9ca3af;
    font-size: 0.8rem;
    cursor: default;
    min-width: 24px;
    padding: 0 2px;
}

/* Dark mode */
[data-theme="dark"] .blc-page-link {
    color: #a5b4fc;
}
[data-theme="dark"] .blc-page-item:not(.disabled):not(.active) .blc-page-link:hover {
    background: rgba(99,102,241,0.15);
    color: #818cf8;
    border-color: rgba(99,102,241,0.3);
}
[data-theme="dark"] .blc-page-item.disabled .blc-page-link {
    color: #374151;
}
</style>
@endif
