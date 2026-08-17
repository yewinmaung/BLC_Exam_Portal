<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RecordType;
use App\Enums\RoleSlug;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\Major;
use App\Models\StudentYearRecord;
use App\Models\User;
use App\Models\YearLevel;
use App\Services\AcademicService;
use App\Services\StudentMajorLockService;
use App\Services\YearLevelProgressionValidator;
use Illuminate\Http\Request;

class AcademicYearController extends Controller
{
    public function __construct(
        private AcademicService $academicService,
        private YearLevelProgressionValidator $progressionValidator,
        private StudentMajorLockService $majorLockService
    ) {}

    /* ── CRUD ─────────────────────────────────────────────────── */

    public function index(Request $request)
    {
        $search = $request->string('search')->trim()->limit(100)->value();
        $yearFilter = $request->filled('year') ? $request->year : null;
        $statusFilter = $request->filled('status') ? $request->status : null;

        $years = AcademicYear::withCount('studentYearRecords')
            ->when($search, fn ($q) =>
                $q->where('name', 'like', "%{$search}%")
            )
            ->when($yearFilter, fn ($q) =>
                $q->where('start_year', $yearFilter)
            )
            ->when($statusFilter === 'current', fn ($q) => $q->where('is_current', true))
            ->when($statusFilter === 'past', fn ($q) => $q->where('is_current', false))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        // Get unique years for filter dropdown
        $availableYears = AcademicYear::distinct()
            ->orderByDesc('start_year')
            ->pluck('start_year');

        return view('admin.academic.years.index', compact('years', 'availableYears'));
    }

    public function create()
    {
        return view('admin.academic.years.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:50|unique:academic_years,name',
            'start_year' => 'required|digits:4|integer',
            'end_year'   => 'required|digits:4|integer|gte:start_year',
            'is_current' => 'nullable|boolean',
        ]);

        $data['is_current'] = $request->boolean('is_current');

        if ($data['is_current']) {
            AcademicYear::where('is_current', true)->update(['is_current' => false]);
        }

        AcademicYear::create($data);

        return redirect()->route('admin.academic.years.index')
            ->with('success', "Academic year \"{$data['name']}\" created.");
    }

    public function show(AcademicYear $year)
    {
        $year->loadCount('studentYearRecords');
        $records = StudentYearRecord::with(['student', 'yearLevel'])
            ->where('academic_year_id', $year->id)
            ->latest()
            ->paginate(20);

        return view('admin.academic.years.show', compact('year', 'records'));
    }

    public function edit(AcademicYear $year)
    {
        return view('admin.academic.years.edit', compact('year'));
    }

    public function update(Request $request, AcademicYear $year)
    {
        $data = $request->validate([
            'name'       => 'required|string|max:50|unique:academic_years,name,' . $year->id,
            'start_year' => 'required|digits:4|integer',
            'end_year'   => 'required|digits:4|integer|gte:start_year',
            'is_current' => 'nullable|boolean',
        ]);

        $data['is_current'] = $request->boolean('is_current');

        if ($data['is_current']) {
            AcademicYear::where('is_current', true)
                ->where('id', '!=', $year->id)
                ->update(['is_current' => false]);
        }

        $year->update($data);

        return redirect()->route('admin.academic.years.index')
            ->with('success', "Academic year updated.");
    }

    public function destroy(AcademicYear $year)
    {
        if ($year->studentYearRecords()->count() > 0) {
            return back()->withErrors(['error' => 'Cannot delete an academic year that has enrolled students.']);
        }
        $year->delete();
        return redirect()->route('admin.academic.years.index')
            ->with('success', 'Academic year deleted.');
    }

    /* ── Student Assignment ───────────────────────────────────── */

    public function students(AcademicYear $year)
    {
        $yearLevels = YearLevel::orderBy('level')->get();

        // All students not yet enrolled in this academic year
        $enrolledIds = StudentYearRecord::where('academic_year_id', $year->id)
            ->pluck('student_id');

        $availableStudents = User::whereHas('role', fn ($q) => $q->where('slug', RoleSlug::STUDENT))
            ->where('is_active', true)
            ->whereNotIn('id', $enrolledIds)
            ->orderBy('name')
            ->get();

        // Already enrolled records
        $records = StudentYearRecord::with(['student', 'yearLevel'])
            ->where('academic_year_id', $year->id)
            ->latest()
            ->paginate(20);

        return view('admin.academic.years.students', compact(
            'year', 'yearLevels', 'availableStudents', 'records'
        ));
    }

    public function assignStudents(Request $request, AcademicYear $year)
    {
        $recordType = $request->input('record_type') ?? RecordType::NORMAL;
        $requiresRemark = in_array($recordType, [RecordType::TRANSFER, RecordType::READMISSION], true);

        $data = $request->validate([
            'student_ids'   => 'required|array|min:1',
            'student_ids.*' => 'exists:users,id',
            'year_level_id' => 'required|exists:year_levels,id',
            'semester'      => 'required|in:1,2',
            'department'    => 'nullable|string|max:100',
            'major'         => 'nullable|string|max:100',
            'major_id'      => 'nullable|exists:majors,id',
            'record_type'   => 'nullable|in:' . implode(',', RecordType::ALL),
            'remark'        => $requiresRemark
                ? ['required', 'string', 'max:1000']
                : ['nullable', 'string', 'max:1000'],
        ]);

        $newYearLevel = YearLevel::find($data['year_level_id']);
        $recordType   = $data['record_type'] ?? null;
        $resolvedMajorId = !empty($data['major_id'])
            ? (int) $data['major_id']
            : Major::resolveIdFromLabel($data['major'] ?? null);

        $created  = 0;
        $skipped  = 0;
        $rejected = [];

        foreach ($data['student_ids'] as $studentId) {
            $exists = StudentYearRecord::where([
                'student_id'       => $studentId,
                'academic_year_id' => $year->id,
                'year_level_id'    => $data['year_level_id'],
                'semester'         => $data['semester'],
            ])->exists();

            if ($exists) {
                $skipped++;
                continue;
            }

            // Validate year-level progression
            if ($newYearLevel) {
                $progressionError = $this->progressionValidator->validate(
                    (int) $studentId,
                    $newYearLevel->level,
                    $year->id,
                    $recordType
                );
                if ($progressionError) {
                    $student = User::find($studentId);
                    $rejected[] = ($student?->name ?? "Student #{$studentId}") . ': ' . $progressionError;
                    continue;
                }

                $submittedMajorId = !empty($data['major_id'])
                    ? (int) $data['major_id']
                    : Major::resolveIdFromLabel($data['major'] ?? null);

                $majorError = $this->majorLockService->validateMajor(
                    (int) $studentId,
                    $newYearLevel->level,
                    $submittedMajorId
                );
                if ($majorError) {
                    $student = User::find($studentId);
                    $rejected[] = ($student?->name ?? "Student #{$studentId}") . ': ' . $majorError;
                    continue;
                }

                $resolvedMajorId = $this->majorLockService->resolveMajorIdForSave(
                    (int) $studentId,
                    $newYearLevel->level,
                    $submittedMajorId
                );
            }

            $majorName = $resolvedMajorId
                ? Major::find($resolvedMajorId)?->name
                : ($data['major'] ?? null);

            StudentYearRecord::create([
                'student_id'       => $studentId,
                'academic_year_id' => $year->id,
                'year_level_id'    => $data['year_level_id'],
                'semester'         => $data['semester'],
                'department'       => $data['department'] ?? null,
                'major'            => $majorName,
                'status'           => 'active',
                'record_type'      => $recordType,
                'remark'           => $data['remark'] ?? null,
            ]);
            $created++;
        }

        $msg = "{$created} student(s) assigned to {$year->name}.";
        if ($skipped > 0) {
            $msg .= " {$skipped} already enrolled (skipped).";
        }

        if (!empty($rejected)) {
            return back()->with('success', $msg)
                ->withErrors(['year_level_id' => implode(' | ', $rejected)]);
        }

        return back()->with('success', $msg);
    }

    public function removeStudent(AcademicYear $year, User $student)
    {
        StudentYearRecord::where([
            'academic_year_id' => $year->id,
            'student_id'       => $student->id,
        ])->delete();

        return back()->with('success', "{$student->name} removed from {$year->name}.");
    }
}
