<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Transcript — {{ $student->name }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 11px; color: #1a1a2e; background: #fff; }
        .page { padding: 30px 40px; }

        /* Header */
        .header { text-align: center; border-bottom: 3px solid #1e1b6e; padding-bottom: 14px; margin-bottom: 18px; }
        .header .institution { font-size: 20px; font-weight: 700; color: #1e1b6e; letter-spacing: 1px; }
        .header .doc-title { font-size: 14px; font-weight: 600; color: #3730a3; margin-top: 4px; text-transform: uppercase; letter-spacing: 2px; }
        .header .academic-year { font-size: 11px; color: #6b7280; margin-top: 3px; }

        /* Student info */
        .student-box { background: #f0f4ff; border: 1px solid #c7d2fe; border-radius: 6px; padding: 12px 16px; margin-bottom: 16px; }
        .student-box table { width: 100%; }
        .student-box td { padding: 3px 8px; vertical-align: top; }
        .student-box .label { font-weight: 700; color: #3730a3; width: 130px; }

        /* Summary */
        .summary-row { display: table; width: 100%; margin-bottom: 16px; }
        .summary-box { display: table-cell; width: 25%; text-align: center; padding: 10px; border: 1px solid #e5e7eb; }
        .summary-box .value { font-size: 18px; font-weight: 800; color: #1e1b6e; }
        .summary-box .label { font-size: 9px; color: #6b7280; text-transform: uppercase; letter-spacing: 1px; margin-top: 2px; }

        /* Results table */
        .section-title { font-size: 11px; font-weight: 700; color: #1e1b6e; border-bottom: 1px solid #e5e7eb; padding-bottom: 5px; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 1px; }
        table.results { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        table.results th { background: #1e1b6e; color: #fff; padding: 6px 10px; text-align: left; font-size: 10px; }
        table.results td { padding: 6px 10px; border-bottom: 1px solid #f0f0f0; font-size: 10px; }
        table.results tr:nth-child(even) td { background: #f8faff; }
        .badge-pass { background: #dcfce7; color: #166534; padding: 2px 6px; border-radius: 4px; font-size: 9px; font-weight: 700; }
        .badge-fail { background: #fee2e2; color: #991b1b; padding: 2px 6px; border-radius: 4px; font-size: 9px; font-weight: 700; }
        .badge-grade { background: #ede9fe; color: #3730a3; padding: 2px 6px; border-radius: 4px; font-size: 9px; font-weight: 700; }

        /* Footer */
        .footer { margin-top: 30px; border-top: 1px solid #e5e7eb; padding-top: 14px; display: table; width: 100%; }
        .sig-block { display: table-cell; width: 33%; text-align: center; }
        .sig-line { border-top: 1px solid #1a1a2e; margin: 40px 20px 4px; }
        .sig-label { font-size: 9px; color: #6b7280; }
        .generated-note { text-align: center; font-size: 9px; color: #9ca3af; margin-top: 12px; }
    </style>
</head>
<body>
<div class="page">

    {{-- Header --}}
    <div class="header">
        <div class="institution">{{ config('app.name') }}</div>
        <div class="doc-title">Official Academic Transcript</div>
        <div class="academic-year">{{ $academicYear->name }}</div>
    </div>

    {{-- Student Info --}}
    <div class="student-box">
        <table>
            <tr>
                <td class="label">Student Name:</td>
                <td><strong>{{ $student->name }}</strong></td>
                <td class="label">Email:</td>
                <td>{{ $student->email }}</td>
            </tr>
            @foreach($records as $rec)
            <tr>
                <td class="label">Year Level:</td>
                <td>{{ $rec->yearLevel->name ?? '—' }}</td>
                <td class="label">Semester:</td>
                <td>Semester {{ $rec->semester }}</td>
            </tr>
            @endforeach
        </table>
    </div>

    {{-- Summary --}}
    @if($transcript)
    <div class="summary-row">
        <div class="summary-box">
            <div class="value">{{ number_format($transcript->gpa, 2) }}</div>
            <div class="label">GPA</div>
        </div>
        <div class="summary-box">
            <div class="value">{{ $transcript->percentage }}%</div>
            <div class="label">Overall %</div>
        </div>
        <div class="summary-box">
            <div class="value">{{ $transcript->grade }}</div>
            <div class="label">Grade</div>
        </div>
        <div class="summary-box">
            <div class="value" style="color:{{ $transcript->is_passed ? '#166534' : '#991b1b' }}">
                {{ $transcript->is_passed ? 'PASSED' : 'FAILED' }}
            </div>
            <div class="label">Status</div>
        </div>
    </div>
    @endif

    {{-- Exam Results --}}
    <div class="section-title">Examination Results</div>
    <table class="results">
        <thead>
            <tr>
                <th>Exam Title</th>
                <th>Course</th>
                <th>Total</th>
                <th>Obtained</th>
                <th>%</th>
                <th>Grade</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($results as $r)
            <tr>
                <td>{{ $r->exam->title ?? '—' }}</td>
                <td>{{ $r->exam->course->title ?? '—' }}</td>
                <td style="text-align:center">{{ $r->total_marks }}</td>
                <td style="text-align:center">{{ $r->obtained_marks }}</td>
                <td style="text-align:center">{{ $r->percentage }}%</td>
                <td style="text-align:center"><span class="badge-grade">{{ $r->grade }}</span></td>
                <td style="text-align:center">
                    @if($r->is_passed)
                        <span class="badge-pass">PASS</span>
                    @else
                        <span class="badge-fail">FAIL</span>
                    @endif
                </td>
            </tr>
            @empty
            <tr><td colspan="7" style="text-align:center;color:#9ca3af;padding:16px">No results recorded for this period.</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- Signature --}}
    <div class="footer">
        <div class="sig-block">
            <div class="sig-line"></div>
            <div class="sig-label">Student Signature</div>
        </div>
        <div class="sig-block">
            <div class="sig-line"></div>
            <div class="sig-label">Academic Registrar</div>
        </div>
        <div class="sig-block">
            <div class="sig-line"></div>
            <div class="sig-label">Principal / Director</div>
        </div>
    </div>

    <div class="generated-note">
        Generated on {{ now()->format('F d, Y \a\t H:i') }} · {{ config('app.url') }}
    </div>
</div>
</body>
</html>
