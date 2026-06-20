{{-- Pinned sign-out link for inline sidebar layouts --}}
<form action="{{ route('logout') }}" method="POST" class="mt-3">
    @csrf
    <button type="submit" class="btn btn-outline-secondary btn-sm w-100">
        <i class="bi bi-box-arrow-right me-1"></i> Sign Out
    </button>
</form>
