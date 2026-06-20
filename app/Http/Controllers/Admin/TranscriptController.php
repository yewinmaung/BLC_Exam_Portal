<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\User;
use App\Models\YearLevel;
use App\Services\AcademicService;
use App\Services\TranscriptService;
use Illuminate\Http\Request;

class TranscriptController extends Controller
{
    public function __construct(
        private TranscriptService $transcriptService,
        private AcademicService   $academicService
    ) {}

    /**
     * Show a student's full academic transcript history.
     */
    public function show(User $student)
    {
        abort_unless($student->isStudent(), 404);

        $history       = $this->transcriptService->getHistory($student);
        $academicYears = AcademicYear::orderByDesc('start_year')->get();
        $yearLevels    = YearLevel::orderBy('level')->get();

        return view('admin.academic.transcripts.show', compact(
            'student', 'history', 'academicYears', 'yearLevels'
        ));
    }

    /**
     * Generate/refresh a transcript for a specific year + semester.
     */
    public function generate(Request $request, User $student)
    {
        abort_unless($student->isStudent(), 404);

        $data = $request->validate([
            'academic_year_id' => 'required|exists:academic_years,id',
            'year_level_id'    => 'required|exists:year_levels,id',
            'semester'         => 'required|in:1,2',
        ]);

        $academicYear = AcademicYear::findOrFail($data['academic_year_id']);
        $yearLevel    = YearLevel::findOrFail($data['year_level_id']);

        // Archive results first via AcademicService, then generate transcript
        $this->academicService->archiveResults($student, $academicYear, $yearLevel, $data['semester']);
        $transcript = $this->transcriptService->generate(
            $student, $academicYear, $yearLevel, $data['semester'], auth()->user()
        );

        return back()->with('success', "Transcript generated: {$transcript->grade} ({$transcript->percentage}%)");
    }

    /**
     * Download transcript as PDF.
     */
    public function pdf(User $student, Request $request)
    {
        abort_unless($student->isStudent(), 404);

        $academicYear = AcademicYear::findOrFail($request->validate([
            'academic_year_id' => 'required|exists:academic_years,id',
        ])['academic_year_id']);

        return $this->transcriptService->exportPdf($student, $academicYear);
    }
}
