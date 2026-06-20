<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\CertificateLog;
use App\Models\User;
use App\Models\YearLevel;
use App\Models\YearlyTranscript;
use App\Services\CertificateService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class CertificateController extends Controller
{
    public function __construct(private CertificateService $certificateService) {}

    /**
     * List all issued certificates with filters.
     * Only students who have passed Final Year (level 5) appear in the issue form.
     */
    public function index(Request $request)
    {
        $query = CertificateLog::with(['student', 'academicYear', 'yearLevel', 'creator'])
            ->latest('issued_at');

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->student_id);
        }
        if ($request->filled('academic_year_id')) {
            $query->where('academic_year_id', $request->academic_year_id);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        $certificates  = $query->paginate(20)->withQueryString();
        $academicYears = AcademicYear::orderByDesc('start_year')->get();

        // Students eligible for completion certificates: passed Year 4 OR Year 5
        $completionLevels        = YearLevel::whereIn('level', [4, 5])->pluck('id');
        $completionEligibleIds   = YearlyTranscript::whereIn('year_level_id', $completionLevels)
            ->where('is_passed', true)
            ->pluck('student_id')
            ->unique();

        // Students eligible for all other certificates: passed Year 5 only
        $finalYearLevel          = YearLevel::where('level', 5)->first();
        $otherEligibleIds        = $finalYearLevel
            ? YearlyTranscript::where('year_level_id', $finalYearLevel->id)
                ->where('is_passed', true)
                ->pluck('student_id')
                ->unique()
            : collect();

        // Merge both sets for the issue form (JS will filter by selected type)
        $allEligibleIds = $completionEligibleIds->merge($otherEligibleIds)->unique();

        $students = User::whereHas('role', fn($q) => $q->where('slug', 'student'))
                        ->where('is_active', true)
                        ->whereIn('id', $allEligibleIds)
                        ->orderBy('name')
                        ->get(['id', 'name'])
                        ->map(function ($s) use ($completionEligibleIds, $otherEligibleIds) {
                            $s->eligible_completion = $completionEligibleIds->contains($s->id);
                            $s->eligible_other      = $otherEligibleIds->contains($s->id);
                            return $s;
                        });

        // All students for the filter bar (unrestricted)
        $allStudents = User::whereHas('role', fn($q) => $q->where('slug', 'student'))
                           ->where('is_active', true)
                           ->orderBy('name')
                           ->get(['id', 'name']);

        return view('admin.academic.certificates.index', compact(
            'certificates', 'academicYears', 'students', 'allStudents'
        ));
    }

    /**
     * Issue a certificate to a student.
     * Only students who have passed Year Level 5 (Final Year) are eligible.
     */
    public function issue(Request $request, User $student)
    {
        abort_unless($student->isStudent(), 404);

        $data = $request->validate([
            'academic_year_id' => 'required|exists:academic_years,id',
            'year_level_id'    => 'required|exists:year_levels,id',
            'type'             => 'required|in:transcript,completion,promotion,achievement',
            'issued_by'        => 'required|string|max:255',
        ]);

        $academicYear = AcademicYear::findOrFail($data['academic_year_id']);
        $yearLevel    = YearLevel::findOrFail($data['year_level_id']);

        try {
            $cert = $this->certificateService->issue(
                $student,
                $academicYear,
                $yearLevel,
                $data['type'],
                $data['issued_by'],
                auth()->user()
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return back()->with('success', "Certificate issued: {$cert->serial_number}");
    }

    /**
     * Download a certificate as PDF.
     */
    public function pdf(CertificateLog $cert)
    {
        return $this->certificateService->exportPdf($cert);
    }

    /**
     * Public certificate verification by QR token (no auth required).
     */
    public function verify(string $token)
    {
        $cert = $this->certificateService->verify($token);

        return view('certificates.verify', compact('cert'));
    }
}
