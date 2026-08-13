{{--
    Partial: inbox pagination bar only.
    Used by EmailController::inboxRows() for AJAX-based table refresh.
    Variables: $emails (LengthAwarePaginator)
--}}
<div class="px-3 py-2 border-top d-flex align-items-center justify-content-between flex-wrap gap-2"
     style="background:#fafbff">
    <span style="font-size:0.78rem;color:#6b7280">
        Showing <strong>{{ $emails->firstItem() }}</strong>–<strong>{{ $emails->lastItem() }}</strong>
        of <strong>{{ $emails->total() }}</strong>
    </span>
    {{ $emails->withQueryString()->links() }}
</div>
