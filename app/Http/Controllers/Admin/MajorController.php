<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Major;
use Illuminate\Http\Request;

class MajorController extends Controller
{
    public function index(Request $request)
    {
        Major::ensureDefaults();

        $search = $request->string('search')->trim()->limit(100)->value();
        $status = $request->filled('status') ? $request->status : null;

        $majors = Major::withCount('courses')
            ->when($search, fn ($q) =>
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
            )
            ->when($status === 'active',   fn ($q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.majors.index', compact('majors'));
    }

    public function show(Major $major)
    {
        $currentYear = \App\Models\AcademicYear::where('is_current', true)->first();

        // Courses for this major in the active academic year
        $query = $major->courses()
            ->with(['teacher', 'academicYear'])
            ->where('is_active', true);

        if ($currentYear) {
            $query->where('academic_year_id', $currentYear->id);
        }

        $courses = $query->orderBy('year_level')->orderBy('semester')->orderBy('title')->get();

        return view('admin.majors.show', compact('major', 'courses', 'currentYear'));
    }

    public function create()
    {
        return view('admin.majors.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'code'        => 'required|string|max:20|unique:majors,code',
            'description' => 'nullable|string',
            'is_active'   => 'boolean',
        ]);

        Major::create($data);

        return redirect()->route('admin.majors.index')->with('success', 'Major created.');
    }

    public function edit(Major $major)
    {
        return view('admin.majors.edit', compact('major'));
    }

    public function update(Request $request, Major $major)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'code'        => 'required|string|max:20|unique:majors,code,' . $major->id,
            'description' => 'nullable|string',
            'is_active'   => 'boolean',
        ]);

        $major->update([
            'name'        => $data['name'],
            'code'        => $data['code'],
            'description' => $data['description'] ?? null,
            'is_active'   => $request->boolean('is_active', true),
        ]);

        return redirect()->route('admin.majors.index')->with('success', 'Major updated.');
    }

    public function destroy(Major $major)
    {
        // Prevent deletion if courses are assigned
        if ($major->courses()->count() > 0) {
            return back()->withErrors(['error' => 'Cannot delete a major that has courses assigned to it. Reassign those courses first.']);
        }

        $major->delete();
        return redirect()->route('admin.majors.index')->with('success', 'Major deleted.');
    }
}
