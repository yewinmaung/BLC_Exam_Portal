<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Certificate — {{ $cert->serial_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 12px; color: #1a1a2e; background: #fff; }
        .page {
            width: 100%; padding: 40px 60px;
            border: 14px double #1e1b6e;
            min-height: 500px;
            position: relative;
        }

        /* Ornamental corners */
        .corner { position: absolute; width: 50px; height: 50px; }
        .corner-tl { top: 12px; left: 12px; border-top: 4px solid #d4af37; border-left: 4px solid #d4af37; }
        .corner-tr { top: 12px; right: 12px; border-top: 4px solid #d4af37; border-right: 4px solid #d4af37; }
        .corner-bl { bottom: 12px; left: 12px; border-bottom: 4px solid #d4af37; border-left: 4px solid #d4af37; }
        .corner-br { bottom: 12px; right: 12px; border-bottom: 4px solid #d4af37; border-right: 4px solid #d4af37; }

        .header { text-align: center; padding-bottom: 20px; border-bottom: 2px solid #d4af37; margin-bottom: 24px; }
        .institution { font-size: 22px; font-weight: 700; color: #1e1b6e; letter-spacing: 2px; }
        .cert-title { font-size: 30px; font-weight: 800; color: #d4af37; letter-spacing: 4px; margin: 10px 0 4px; text-transform: uppercase; }
        .cert-type { font-size: 13px; color: #6b7280; text-transform: uppercase; letter-spacing: 3px; }

        .body { text-align: center; padding: 20px 0; }
        .body .presented-to { font-size: 13px; color: #6b7280; margin-bottom: 6px; }
        .body .student-name { font-size: 28px; font-weight: 700; color: #1e1b6e; border-bottom: 2px solid #d4af37; display: inline-block; padding-bottom: 4px; margin-bottom: 16px; }
        .body .description { font-size: 12px; color: #374151; line-height: 1.8; max-width: 480px; margin: 0 auto 16px; }
        .body .meta { font-size: 11px; color: #6b7280; }
        .body .meta span { margin: 0 8px; }

        .footer { display: table; width: 100%; margin-top: 36px; border-top: 1px solid #e5e7eb; padding-top: 16px; }
        .sig-block { display: table-cell; width: 30%; text-align: center; vertical-align: bottom; }
        .qr-block { display: table-cell; width: 40%; text-align: center; vertical-align: bottom; }
        .sig-line { border-top: 1px solid #374151; margin: 40px 20px 4px; }
        .sig-label { font-size: 9px; color: #6b7280; }
        .serial { font-size: 9px; color: #9ca3af; text-align: center; margin-top: 12px; }
    </style>
</head>
<body>
<div class="page">
    <div class="corner corner-tl"></div>
    <div class="corner corner-tr"></div>
    <div class="corner corner-bl"></div>
    <div class="corner corner-br"></div>

    {{-- Header --}}
    <div class="header">
        <div class="institution">{{ config('app.name') }}</div>
        <div class="cert-title">Certificate</div>
        <div class="cert-type">of {{ ucfirst($cert->type) }}</div>
    </div>

    {{-- Body --}}
    <div class="body">
        <div class="presented-to">This is to certify that</div>
        <div class="student-name">{{ $cert->student->name }}</div>

        <div class="description">
            @if($cert->type === 'completion')
                has successfully completed the academic requirements for
            @elseif($cert->type === 'promotion')
                has been officially promoted upon completion of
            @elseif($cert->type === 'achievement')
                has demonstrated outstanding achievement during
            @else
                has fulfilled the academic requirements of
            @endif
            <strong>{{ $cert->yearLevel->name ?? '' }}</strong>
            during the academic year
            <strong>{{ $cert->academicYear->name ?? '' }}</strong>.

            @if($transcript && $transcript->gpa)
            <br><br>Final GPA: <strong>{{ number_format($transcript->gpa, 2) }}</strong>
            &nbsp;|&nbsp; Grade: <strong>{{ $transcript->grade }}</strong>
            &nbsp;|&nbsp; Status: <strong>{{ $transcript->is_passed ? 'PASSED' : 'FAILED' }}</strong>
            @endif
        </div>

        <div class="meta">
            <span>Issued by: <strong>{{ $cert->issued_by }}</strong></span>
            <span>·</span>
            <span>Date: <strong>{{ \Carbon\Carbon::parse($cert->issued_at)->format('F d, Y') }}</strong></span>
        </div>
    </div>

    {{-- Footer --}}
    <div class="footer">
        <div class="sig-block">
            <div class="sig-line"></div>
            <div class="sig-label">Issued By</div>
            <div style="font-size:9px;font-weight:600;color:#374151;margin-top:2px">{{ $cert->issued_by }}</div>
        </div>

        <div class="qr-block">
            @if(!empty($qrSvg))
            <div style="width:70px;height:70px;display:block;margin:0 auto 4px">{!! $qrSvg !!}</div>
            @elseif(!empty($qrBase64))
            <img src="data:image/png;base64,{{ $qrBase64 }}" width="70" height="70" style="display:block;margin:0 auto 4px">
            @endif
            <div style="font-size:8px;color:#9ca3af">Scan to verify</div>
            <div style="font-size:8px;color:#6b7280;word-break:break-all;max-width:160px;margin:2px auto">{{ $verifyUrl }}</div>
        </div>

        <div class="sig-block">
            <div class="sig-line"></div>
            <div class="sig-label">Principal / Director</div>
        </div>
    </div>

    <div class="serial">Serial No: {{ $cert->serial_number }}</div>
</div>
</body>
</html>
