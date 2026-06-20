<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\CertificateLog;
use App\Models\User;
use App\Models\YearLevel;
use App\Models\YearlyTranscript;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class CertificateService
{
    /**
     * Issue a certificate and return the CertificateLog.
     *
     * Rules by certificate type:
     *  - completion  → student must have passed Year Level 4 OR Year Level 5
     *  - all others  → student must have passed Year Level 5 (Final Year)
     *
     * Throws ValidationException if eligibility is not met.
     */
    public function issue(
        User $student,
        AcademicYear $academicYear,
        YearLevel $yearLevel,
        string $type,
        string $issuedBy,
        User $createdBy
    ): CertificateLog {
        if ($type === 'completion') {
            // Completion certificate: student must have passed Year 4 or Year 5
            $eligibleLevels = YearLevel::whereIn('level', [4, 5])->pluck('id');

            if ($eligibleLevels->isEmpty()) {
                throw ValidationException::withMessages([
                    'year_level_id' => 'Year Level 4 and 5 are not configured in the system.',
                ]);
            }

            $hasPassed = YearlyTranscript::where('student_id', $student->id)
                ->whereIn('year_level_id', $eligibleLevels)
                ->where('is_passed', true)
                ->exists();

            if (! $hasPassed) {
                throw ValidationException::withMessages([
                    'student_select' => "Completion certificate cannot be issued. {$student->name} has not passed Year 4 or Year 5.",
                ]);
            }
        } else {
            // All other types: student must have passed Year Level 5 (Final Year)
            $finalYearLevel = YearLevel::where('level', 5)->first();

            if (! $finalYearLevel) {
                throw ValidationException::withMessages([
                    'year_level_id' => 'Final Year (Year Level 5) is not configured in the system.',
                ]);
            }

            $hasPassed = YearlyTranscript::where('student_id', $student->id)
                ->where('year_level_id', $finalYearLevel->id)
                ->where('is_passed', true)
                ->exists();

            if (! $hasPassed) {
                throw ValidationException::withMessages([
                    'student_select' => "Certificate cannot be issued. {$student->name} has not passed Final Year (Year Level 5).",
                ]);
            }
        }

        $serial = $this->generateSerial();
        $token  = Str::uuid()->toString();

        return CertificateLog::create([
            'serial_number'    => $serial,
            'student_id'       => $student->id,
            'academic_year_id' => $academicYear->id,
            'year_level_id'    => $yearLevel->id,
            'type'             => $type,
            'issued_by'        => $issuedBy,
            'qr_token'         => $token,
            'issued_at'        => now(),
            'created_by'       => $createdBy->id,
        ]);
    }

    /**
     * Generate unique serial: CERT-YYYY-NNNN
     */
    public function generateSerial(): string
    {
        $year  = now()->year;
        $count = CertificateLog::whereYear('issued_at', $year)->count() + 1;
        return 'CERT-' . $year . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Export certificate as PDF.
     */
    public function exportPdf(CertificateLog $cert): \Illuminate\Http\Response
    {
        $cert->load(['student', 'academicYear', 'yearLevel', 'creator']);

        $transcript = YearlyTranscript::where([
            'student_id'       => $cert->student_id,
            'academic_year_id' => $cert->academic_year_id,
            'year_level_id'    => $cert->year_level_id,
        ])->first();

        // Generate QR code as inline SVG (no imagick extension required)
        $verifyUrl = url('/certificates/verify/' . $cert->qr_token);
        $qrSvg     = QrCode::format('svg')->size(120)->generate($verifyUrl);
        $qrBase64  = null; // kept for view compatibility; SVG used instead

        $pdf = Pdf::loadView('pdf.certificate', compact('cert', 'transcript', 'qrBase64', 'qrSvg', 'verifyUrl'))
            ->setPaper('a4', 'landscape');

        $filename = $cert->serial_number . '_' . str_replace(' ', '_', $cert->student->name) . '.pdf';

        return $pdf->download($filename);
    }

    /**
     * Verify a certificate by QR token.
     */
    public function verify(string $token): ?CertificateLog
    {
        return CertificateLog::with(['student', 'academicYear', 'yearLevel', 'creator'])
            ->where('qr_token', $token)
            ->first();
    }
}
