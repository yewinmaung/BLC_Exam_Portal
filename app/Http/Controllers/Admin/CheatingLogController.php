<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CheatingLog;

class CheatingLogController extends Controller
{
    public function index()
    {
        $logs = CheatingLog::with(['student', 'attempt.exam'])->latest()->get();

        return view('admin.cheating-logs.index', compact('logs'));
    }
}
