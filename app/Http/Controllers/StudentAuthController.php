<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Student;
use App\Models\Subject;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class StudentAuthController extends Controller
{
    // =========================
    // STUDENT LOGIN
    // =========================
    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required',
            'password' => 'required',
        ]);

        $student = Student::where(
            'student_id',
            $request->username
        )->first();

        // Student ID doesn't exist
        if (!$student) {
            return back()
                ->withInput()
                ->with('error', 'Invalid Student ID or Password.');
        }

        // Password is incorrect
        if (!Hash::check($request->password, $student->password)) {
            return back()
                ->withInput()
                ->with('error', 'Invalid Student ID or Password.');
        }

        // ==========================================
        // IMPORTANT:
        // LOGIN THE STUDENT USING LARAVEL GUARD
        // ==========================================
        Auth::guard('student')->login($student);

        $request->session()->regenerate();

        session([
            'student_id' => $student->id,
            'student_name' => $student->fullname,
        ]);

        return redirect()->route('student.dashboard');
    }


    // =========================
    // STUDENT LOGOUT
    // =========================
    public function logout(Request $request)
    {
        // IMPORTANT:
        // Logout from the student guard
        Auth::guard('student')->logout();

        // Destroy the session
        $request->session()->invalidate();

        // Generate a new CSRF token
        $request->session()->regenerateToken();

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully.',
            'redirect' => route('student.login')
        ]);
    }


    // =========================
    // QR CODE LOGIN
    // =========================
  

public function qrLogin(Request $request)
{
    $validated = $request->validate([
        'date_of_birth' => 'required|date_format:Y-m-d',
        'contact'       => 'required|string|max:30',
        'address'       => 'required|string|max:255',
    ]);
// Find the student using the scanned details.

$students = Student::where(
    'date_of_birth',
    $validated['date_of_birth']
)
->where('contact', $validated['contact'])
->where('address', $validated['address'])
->get();


        // Find the student using the scanned details.
        $students = Student::where(
            'date_of_birth',
            $validated['date_of_birth']
        )
        ->where('contact', $validated['contact'])
        ->where('address', $validated['address'])
        ->get();

        // Check if exactly one student matches.
        if ($students->count() !== 1) {
            return response()->json([
                'success' => false,
                'message' => 'Student record not found or details are ambiguous.',
            ], 422);
        }

        $student = $students->first();


       

    // Prevent repeated OTP requests within 60 seconds.
    $previousOtp = DB::table('student_qr_login_otps')
        ->where('student_id', $student->id)
        ->orderByDesc('id')
        ->first();

    if ($previousOtp && $previousOtp->last_sent_at) {
        $lastSent = \Illuminate\Support\Carbon::parse(
            $previousOtp->last_sent_at
        );

        if ($lastSent->gt(now()->subSeconds(60))) {
            return response()->json([
                'success' => false,
                'message' => 'Please wait before requesting another OTP.',
            ], 429);
        }
    }

    // Invalidate previous OTPs for this student.
    DB::table('student_qr_login_otps')
        ->where('student_id', $student->id)
        ->delete();

    $otp = (string) random_int(100000, 999999);

    $otpId = DB::table('student_qr_login_otps')->insertGetId([
        'student_id' => $student->id,
        'otp_hash' => Hash::make($otp),
        'otp_expires_at' => now()->addMinutes(5),
        'attempts' => 0,
        'last_sent_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    try {
        Mail::raw(
            "Your LabTrack QR login verification code is: {$otp}\n\n"
            . "This code expires in 5 minutes. Do not share it with anyone.",
            function ($message) use ($student) {
                $message->to($student->email)
                    ->subject('LabTrack QR Login Verification');
            }
        );
    } catch (\Throwable $e) {
        DB::table('student_qr_login_otps')
            ->where('id', $otpId)
            ->delete();

        report($e);

        return response()->json([
            'success' => false,
            'message' => 'Unable to send the verification email. Please try again later.',
        ], 503);
    }

    // Store only pending verification state, not an authenticated login.
    $request->session()->put(
        'pending_qr_student_id',
        $student->id
    );

    return response()->json([
        'success' => true,
        'message' => 'A verification code has been sent to your registered email.',
        'redirect' => route('student.qr.otp.page'),
    ]);
}



    public function showQrOtp(Request $request)
    {
        if (!$request->session()->has('pending_qr_student_id')) {
            return redirect()->route('student.qr')
                ->with('error', 'Please scan your Student ID QR code first.');
        }

        return view('student-qr-verify-otp');
    }

    public function verifyQrOtp(Request $request)
    {
        $request->validate([
            'otp' => ['required', 'digits:6'],
        ]);

        $studentId = $request->session()->get('pending_qr_student_id');

        if (!$studentId) {
            return redirect()->route('student.qr')
                ->with('error', 'Your verification session has expired. Please scan again.');
        }

        $otpRecord = DB::table('student_qr_login_otps')
            ->where('student_id', $studentId)
            ->orderByDesc('id')
            ->first();

        if (!$otpRecord) {
            return back()->withErrors([
                'otp' => 'No active OTP found. Please request a new code.',
            ]);
        }

        if (now()->greaterThan(
            \Illuminate\Support\Carbon::parse($otpRecord->otp_expires_at)
        )) {
            DB::table('student_qr_login_otps')
                ->where('id', $otpRecord->id)
                ->delete();

            $request->session()->forget('pending_qr_student_id');

            return redirect()->route('student.qr')
                ->with('error', 'Your OTP has expired. Please scan your QR code again.');
        }

        if ($otpRecord->attempts >= 5) {
            DB::table('student_qr_login_otps')
                ->where('id', $otpRecord->id)
                ->delete();

            $request->session()->forget('pending_qr_student_id');

            return redirect()->route('student.qr')
                ->with('error', 'Too many incorrect attempts. Please scan your QR code again.');
        }

        if (!Hash::check($request->otp, $otpRecord->otp_hash)) {
            $attempts = $otpRecord->attempts + 1;

            DB::table('student_qr_login_otps')
                ->where('id', $otpRecord->id)
                ->update([
                    'attempts' => $attempts,
                    'updated_at' => now(),
                ]);

            if ($attempts >= 5) {
                DB::table('student_qr_login_otps')
                    ->where('id', $otpRecord->id)
                    ->delete();

                $request->session()->forget('pending_qr_student_id');

                return redirect()->route('student.qr')
                    ->with('error', 'Too many incorrect attempts. Please scan your QR code again.');
            }

            return back()->withErrors([
                'otp' => 'Incorrect OTP. Please try again.',
            ]);
        }

        $student = Student::find($studentId);

        if (!$student) {
            DB::table('student_qr_login_otps')
                ->where('id', $otpRecord->id)
                ->delete();

            $request->session()->forget('pending_qr_student_id');

            return redirect()->route('student.qr')
                ->with('error', 'Student record not found. Please try again.');
        }

        // Consume the OTP so it cannot be reused.
        DB::table('student_qr_login_otps')
            ->where('id', $otpRecord->id)
            ->delete();

        Auth::guard('student')->login($student);

        $request->session()->regenerate();
        $request->session()->forget('pending_qr_student_id');

        $request->session()->put([
            'student_id' => $student->id,
            'student_name' => $student->fullname,
        ]);

        return redirect()->route('student.dashboard');
    }

    public function resendQrOtp(Request $request)
    {
        $studentId = $request->session()->get('pending_qr_student_id');

        if (!$studentId) {
            return redirect()->route('student.qr')
                ->with('error', 'Your verification session has expired. Please scan again.');
        }

        $student = Student::find($studentId);

        if (!$student || empty($student->email) ||
            !filter_var($student->email, FILTER_VALIDATE_EMAIL)) {
            return redirect()->route('student.qr')
                ->with('error', 'A valid registered email is not available.');
        }

        $previousOtp = DB::table('student_qr_login_otps')
            ->where('student_id', $studentId)
            ->orderByDesc('id')
            ->first();

        if ($previousOtp && $previousOtp->last_sent_at) {
            $lastSent = \Illuminate\Support\Carbon::parse(
                $previousOtp->last_sent_at
            );

            if ($lastSent->gt(now()->subSeconds(60))) {
                return back()->with(
                    'error',
                    'Please wait 60 seconds before requesting another OTP.'
                );
            }
        }

        DB::table('student_qr_login_otps')
            ->where('student_id', $studentId)
            ->delete();

        $otp = (string) random_int(100000, 999999);

        $otpId = DB::table('student_qr_login_otps')->insertGetId([
            'student_id' => $studentId,
            'otp_hash' => Hash::make($otp),
            'otp_expires_at' => now()->addMinutes(5),
            'attempts' => 0,
            'last_sent_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            Mail::raw(
                "Your LabTrack QR login verification code is: {$otp}\n\n"
                . "This code expires in 5 minutes. Do not share it with anyone.",
                function ($message) use ($student) {
                    $message->to($student->email)
                        ->subject('LabTrack QR Login Verification');
                }
            );
        } catch (\Throwable $e) {
            DB::table('student_qr_login_otps')
                ->where('id', $otpId)
                ->delete();

            report($e);

            return back()->with(
                'error',
                'Unable to send the OTP. Please try again later.'
            );
        }

        return back()->with(
            'success',
            'A new OTP has been sent to your registered email.'
        );
    }

}