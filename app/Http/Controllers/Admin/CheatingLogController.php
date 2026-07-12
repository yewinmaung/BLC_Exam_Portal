<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CheatingLog;
use Illuminate\Http\Request;

class CheatingLogController extends Controller
{
    public function index(Request $request)
    {
        // Validate all inputs — prevents injection and type confusion
        $validated = $request->validate([
            'search'         => ['nullable', 'string', 'max:100'],
            'violation_type' => ['nullable', 'string', 'max:100'],
        ]);

        $search    = $validated['search'] ?? null;
        $violation = $validated['violation_type'] ?? null;

        // Distinct violation types for filter dropdown (safe, no raw queries)
        $violationTypes = CheatingLog::distinct()
            ->orderBy('violation_type')
            ->pluck('violation_type')
            ->filter()
            ->values();

        $query = CheatingLog::with(['student', 'attempt.exam'])
            ->when($search, function ($q) use ($search) {
                $q->whereHas('student', fn ($s) =>
                    $s->where('name', 'like', '%' . $search . '%')
                      ->orWhere('email', 'like', '%' . $search . '%')
                )->orWhereHas('attempt.exam', fn ($e) =>
                    $e->where('title', 'like', '%' . $search . '%')
                );
            })
            ->when($violation, fn ($q) =>
                $q->where('violation_type', $violation)
            )
            ->latest();

        $logs = $query->paginate(20)->withQueryString();

        return view('admin.cheating-logs.index', compact('logs', 'violationTypes'));
    }
}
