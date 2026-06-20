<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate Verification — {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background: #f3f4f6; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .verify-card { max-width: 540px; width: 100%; }
    </style>
</head>
<body>
<div class="verify-card mx-auto p-3">
    <div class="text-center mb-4">
        <div style="font-size:2rem;font-weight:800;color:#1e1b6e">{{ config('app.name') }}</div>
        <div style="font-size:0.85rem;color:#6b7280">Certificate Verification Portal</div>
    </div>

    @if($cert)
    <div class="card border-0 shadow-sm">
        <div class="card-header text-center py-3" style="background:linear-gradient(135deg,#1e1b6e,#3730a3);border-radius:8px 8px 0 0">
            <i class="bi bi-patch-check-fill text-white" style="font-size:2rem"></i>
            <div class="text-white fw-bold mt-1">Certificate Verified ✓</div>
        </div>
        <div class="card-body p-4">
            <table class="table table-sm mb-0">
                <tr>
                    <th class="text-muted fw-normal border-0" style="width:140px">Serial No.</th>
                    <td class="border-0"><code>{{ $cert->serial_number }}</code></td>
                </tr>
                <tr>
                    <th class="text-muted fw-normal">Student</th>
                    <td><strong>{{ $cert->student->name ?? '—' }}</strong></td>
                </tr>
                <tr>
                    <th class="text-muted fw-normal">Academic Year</th>
                    <td>{{ $cert->academicYear->name ?? '—' }}</td>
                </tr>
                <tr>
                    <th class="text-muted fw-normal">Year Level</th>
                    <td>{{ $cert->yearLevel->name ?? '—' }}</td>
                </tr>
                <tr>
                    <th class="text-muted fw-normal">Type</th>
                    <td><span class="badge bg-secondary">{{ ucfirst($cert->type) }}</span></td>
                </tr>
                <tr>
                    <th class="text-muted fw-normal">Issued By</th>
                    <td>{{ $cert->issued_by }}</td>
                </tr>
                <tr>
                    <th class="text-muted fw-normal">Issued On</th>
                    <td>{{ \Carbon\Carbon::parse($cert->issued_at)->format('F d, Y') }}</td>
                </tr>
            </table>
        </div>
        <div class="card-footer text-center text-muted py-2" style="font-size:0.78rem">
            This certificate is authentic and issued by {{ config('app.name') }}.
        </div>
    </div>
    @else
    <div class="card border-danger">
        <div class="card-body text-center py-5">
            <i class="bi bi-x-circle-fill text-danger" style="font-size:2.5rem"></i>
            <div class="fw-bold mt-2 text-danger">Certificate Not Found</div>
            <div class="text-muted small mt-1">This QR code does not match any certificate in our system. It may be invalid or counterfeit.</div>
        </div>
    </div>
    @endif

    <div class="text-center mt-3">
        <a href="{{ route('home') }}" class="text-muted small">← Return to Portal</a>
    </div>
</div>
</body>
</html>
