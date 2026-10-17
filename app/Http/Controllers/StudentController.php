<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;


class StudentController extends Controller
{
    // DISPLAY ALL STUDENTS
    public function index()
    {
        $students = Student::latest()->get();

        return view('Student.index', compact('students'));
    }


    // REALTIME STUDENT DATA
    public function data()
    {
        $students = Student::latest()->get();

        return response()->json([
            'success' => true,
            'students' => $students
        ]);
    }


    // SHOW ADD STUDENT FORM
    public function create()
    {
        return view('Student.create');
    }


    // SAVE NEW STUDENT

// SAVE NEW STUDENT
        public function store(Request $request)
        {
            $validated = $request->validate([
                'full_name' => 'required|string|max:255',
                'student_id' => 'required|string|max:255|unique:students,student_id',
                'email' => 'required|email|max:255|unique:students,email',
                'password' => 'required|string|min:6|confirmed',

                'profile_picture' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            ]);

            $student = new Student();

            $student->student_id = $validated['student_id'];
            $student->fullname = $validated['full_name'];
            $student->email = $validated['email'];
            $student->password = Hash::make($validated['password']);

            if ($request->hasFile('profile_picture')) {
                $path = $request->file('profile_picture')
                    ->store('profile_pictures', 'public');

                $student->profile_picture = $path;
            }

            $student->save();

            return redirect()
                ->route('student.login')
                ->with(
                    'success',
                    'Account created successfully. You can now login.'
                );
        }



    // VIEW ONE STUDENT
    public function show(Student $student)
    {
        return view('Student.show', compact('student'));
    }


    // SHOW EDIT FORM
    public function edit(Student $student)
    {
        return view('Student.edit', compact('student'));
    }


    // ADMIN UPDATE STUDENT
    public function update(Request $request, Student $student)
    {
        $request->validate([
            'student_id' => 'required|unique:students,student_id,' . $student->id,
            'fullname' => 'required|string|max:255',
            'date_of_birth' => 'nullable|date',
            'gender' => 'nullable|string',
            'contact' => 'nullable|string|max:20',
            'email' => 'required|email|unique:students,email,' . $student->id,
            'address' => 'nullable|string',
            'course' => 'nullable|string',
            'year_level' => 'nullable|string',
            'section' => 'nullable|string',
            'password' => 'nullable|min:6|confirmed',
        ]);

        $student->student_id = $request->student_id;
        $student->fullname = $request->fullname;
        $student->date_of_birth = $request->date_of_birth;
        $student->gender = $request->gender;
        $student->contact = $request->contact;
        $student->email = $request->email;
        $student->address = $request->address;
        $student->course = $request->course;
        $student->year_level = $request->year_level;
        $student->section = $request->section;

        if ($request->filled('password')) {
            $student->password = Hash::make($request->password);
        }

        $student->save();

        return redirect()
            ->route('students.index')
            ->with('success', 'Student updated successfully.');
    }


    // DELETE STUDENT
    public function destroy(Student $student)
    {
        $student->delete();

        return redirect()
            ->route('students.index')
            ->with('success', 'Student deleted successfully.');
    }


    // UPDATE PROFILE PICTURE
    // UPDATE PROFILE PICTURE
public function updateProfilePicture(Request $request)
{
    $request->validate([
        'profile_picture' =>
            'required|image|mimes:jpg,jpeg,png,webp|max:2048',
    ]);

    $student = Student::find(session('student_id'));

    if (!$student) {
        return redirect()
            ->route('student.login')
            ->with('error', 'Student account not found.');
    }

    // Delete old picture
    if ($student->profile_picture) {

        $oldPath = storage_path(
            'app/public/' . $student->profile_picture
        );

        if (file_exists($oldPath)) {
            unlink($oldPath);
        }
    }

    // Save new picture
    $path = $request->file('profile_picture')
        ->store('profile_pictures', 'public');

    $student->profile_picture = $path;

    $student->save();

    // STAY ON MY PROFILE
    return redirect()
        ->route('student.dashboard', [
            'page' => 'profile',
            'saved' => 'profile'
        ]);
}


    // UPDATE STUDENT OWN PROFILE
    // UPDATE STUDENT OWN PROFILE
public function updateProfile(Request $request)
{
    $student = Student::find(session('student_id'));

    if (!$student) {
        return redirect()
            ->route('student.login')
            ->with('error', 'Student account not found.');
    }

    $request->validate([
        'fullname' => 'required|string|max:255',
        'student_id' => 'required|string|max:255|unique:students,student_id,' . $student->id,
        'date_of_birth' => 'nullable|date',
        'gender' => 'nullable|string|max:20',
        'email' => 'nullable|email|unique:students,email,' . $student->id,
        'contact' => 'nullable|string|max:20',
        'address' => 'nullable|string|max:255',
        'course' => 'nullable|string|max:100',
        'year_level' => 'nullable|string|max:50',
        'section' => 'nullable|string|max:50',
    ]);

    // UPDATE DATA
    $student->fullname = $request->fullname;
    $student->student_id = $request->student_id;
    $student->date_of_birth = $request->date_of_birth;
    $student->gender = $request->gender;
    $student->email = $request->email;
    $student->contact = $request->contact;
    $student->address = $request->address;
    $student->course = $request->course;
    $student->year_level = $request->year_level;
    $student->section = $request->section;

    // SAVE TO DATABASE
    $student->save();

    // UPDATE SESSION NAME
    session([
        'student_name' => $student->fullname
    ]);

    // STAY ON MY PROFILE
    return redirect()
    ->route('student.dashboard', [
        'page' => 'profile',
        'saved' => 'profile'
    ]);
}
public function updatePassword(Request $request)
{
    // Get the currently logged-in student from the STUDENT GUARD
    $student = Auth::guard('student')->user();

    // Fallback to session in case the guard is not available
    if (!$student && session()->has('student_id')) {
        $student = Student::find(session('student_id'));
    }

    // Student is not logged in
    if (!$student) {
        return response()->json([
            'success' => false,
            'message' => 'Student is not logged in.'
        ], 401);
    }

    // Validate request
    $validated = $request->validate([
        'current_password' => [
            'required',
            'string'
        ],

        'new_password' => [
            'required',
            'string',
            'min:8',
            'confirmed'
        ],
    ]);

    // Check current password
    if (!Hash::check(
        $validated['current_password'],
        $student->password
    )) {
        return response()->json([
            'success' => false,
            'message' => 'Current password is incorrect.'
        ], 422);
    }

    // Make sure new password is different
    if (Hash::check(
        $validated['new_password'],
        $student->password
    )) {
        return response()->json([
            'success' => false,
            'message' => 'New password must be different from your current password.'
        ], 422);
    }

    // Update password
    $student->password = Hash::make(
        $validated['new_password']
    );

    $student->save();

    return response()->json([
        'success' => true,
        'message' => 'Password updated successfully.'
    ], 200);
}
}