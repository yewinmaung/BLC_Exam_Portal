<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\CertificateLog;
use App\Models\User;
use App\Models\YearLevel;
use App\Models\YearlyTranscript;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class CertificateService
{
    /**
     * Issue a certificate and return the CertificateLog.
     */
    public function issue(
        User $student,
        AcademicYear $academicYear,
        YearLevel $yearLevel,
        string $type,
        string $issuedBy,
        User $createdBy
    ): CertificateLog {
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

        // Generate QR code as base64 PNG
        $verifyUrl = url('/certificates/verify/' . $cert->qr_token);
        $qrBase64  = base64_encode(
            QrCode::format('png')->size(120)->generate($verifyUrl)
        );

        $pdf = Pdf::loadView('pdf.certificate', compact('cert', 'transcript', 'qrBase64', 'verifyUrl'))
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
