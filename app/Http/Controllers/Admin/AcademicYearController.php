<?php

namespace App\Http\Controllers\Admin;

use App\Enums\RoleSlug;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\StudentYearRecord;
use App\Models\User;
use App\Models\YearLevel;
use Illuminate\Http\Request;

class AcademicYearController extends Controller
{
    /* ── CRUD ─────────────────────────────────────────────────── */

    public function index()
    {
        $years = AcademicYear::withCount('studentYearRecords')->latest()->paginate(15);
        return view('admin.academic.years.index', compact('years'));
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
        $data = $request->validate([
            'student_ids'   => 'required|array|min:1',
            'student_ids.*' => 'exists:users,id',
            'year_level_id' => 'required|exists:year_levels,id',
            'semester'      => 'required|in:1,2',
            'department'    => 'nullable|string|max:100',
            'major'         => 'nullable|string|max:100',
        ]);

        $created = 0;
        $skipped = 0;

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

            StudentYearRecord::create([
                'student_id'       => $studentId,
                'academic_year_id' => $year->id,
                'year_level_id'    => $data['year_level_id'],
                'semester'         => $data['semester'],
                'department'       => $data['department'] ?? null,
                'major'            => $data['major'] ?? null,
                'status'           => 'active',
            ]);
            $created++;
        }

        $msg = "{$created} student(s) assigned to {$year->name}.";
        if ($skipped > 0) {
            $msg .= " {$skipped} already enrolled (skipped).";
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
