<?php

namespace App\Http\Controllers;

use App\Models\ApplicationActivity;
use Illuminate\Http\Request;

class ApplicationActivityController extends Controller
{
    public function index()
    {
        $activities = ApplicationActivity::orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'activities' => $activities
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'time' => 'required|string|max:30',
            'pc_name' => 'required|string|max:50',
            'student_name' => 'required|string|max:100',
            'lab' => 'required|string|max:50',
            'application_name' => 'required|string|max:100',
            'action' => 'required|string|max:50',
            'status' => 'required|string|max:50',
            'reason' => 'nullable|string',
        ]);

        $activity = ApplicationActivity::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Application activity recorded.',
            'activity' => $activity
        ], 201);
    }
        
        
public function storeStudentEvent(Request $request)
{
    $student = \Illuminate\Support\Facades\Auth::guard('student')->user();

    if (
        !$student ||
        (string) $request->session()->get('student_id') !== (string) $student->id
    ) {
        return response()->json([
            'success' => false,
            'message' => 'Student login required.',
        ], 401);
    }

    $validated = $request->validate([
        'application_name' => 'required|string|max:100',
        'action' => 'required|string|max:50',
    ]);

    $activity = ApplicationActivity::create([
        'time' => now()->format('h:i A'),
        'pc_name' => 'Student Mobile/Web',
        'student_name' => $student->fullname,
        'lab' => 'Unassigned',
        'application_name' => $validated['application_name'],
        'action' => $validated['action'],
        'status' => 'Recorded',
        'reason' => 'Student page activity recorded by LabTrack.',
    ]);

    return response()->json([
        'success' => true,
        'activity' => $activity,
    ], 201);
}

}