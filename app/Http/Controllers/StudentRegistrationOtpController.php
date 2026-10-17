<?php

namespace App\Http\Controllers;

use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class StudentRegistrationOtpController extends Controller
{
    /**
     * Start student registration and email the OTP.
     */
    public function store(Request $request)
    {
        $request->merge([
            'email' => Str::lower(trim((string) $request->input('email'))),
            'student_id' => trim((string) $request->input('student_id')),
            'full_name' => trim((string) $request->input('full_name')),
        ]);

        $validated = $request->validate([
            'full_name' => 'required|string|max:255',
            'student_id' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:6|confirmed',
        ]);

        if (Student::where('student_id', $validated['student_id'])->exists()) {
            throw ValidationException::withMessages([
                'student_id' => 'This Student ID is already registered.',
            ]);
        }

        if (Student::where('email', $validated['email'])->exists()) {
            throw ValidationException::withMessages([
                'email' => 'This email is already registered.',
            ]);
        }

        $email = $validated['email'];
        $rateKey = 'registration-otp:' . sha1($email);

        if (RateLimiter::tooManyAttempts($rateKey, 3)) {
            return back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->withErrors([
                    'email' => 'Too many registration attempts. Please try again later.',
                ]);
        }

        RateLimiter::hit($rateKey, 300);

        // Remove expired pending registrations.
        DB::table('student_registration_otps')
            ->where('otp_expires_at', '<=', now())
            ->delete();

        $existingPending = DB::table('student_registration_otps')
            ->where(function ($query) use ($validated, $email) {
                $query->where('student_id', $validated['student_id'])
                    ->orWhere('email', $email);
            })
            ->first();

        if ($existingPending) {
            return back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->withErrors([
                    'email' => 'A registration is already pending. Please verify it or wait for it to expire.',
                ]);
        }

        $otp = (string) random_int(100000, 999999);

        $pendingId = DB::table('student_registration_otps')->insertGetId([
            'student_id' => $validated['student_id'],
            'full_name' => $validated['full_name'],
            'email' => $email,
            'password' => Hash::make($validated['password']),
            'otp_hash' => Hash::make($otp),
            'otp_expires_at' => now()->addMinutes(10),
            'attempts' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            Mail::raw(
                "Hello {$validated['full_name']},\n\n"
                . "Your LabTrack registration verification code is: {$otp}\n\n"
                . "This code expires in 10 minutes. Do not share it with anyone.\n\n"
                . "If you did not request this registration, you can ignore this email.",
                function ($message) use ($email) {
                    $message->to($email)
                        ->subject('LabTrack Email Verification');
                }
            );
        } catch (Throwable $exception) {
            DB::table('student_registration_otps')
                ->where('id', $pendingId)
                ->delete();

            report($exception);

            return back()
                ->withInput($request->except('password', 'password_confirmation'))
                ->withErrors([
                    'email' => 'We could not send the verification email. Please try again later.',
                ]);
        }

        $request->session()->put('registration_email', $email);

        return redirect()
            ->route('student.register.verify.page')
            ->with('success', 'A verification code has been sent to your email.');
    }

    /**
     * Display the OTP verification page.
     */
    public function showVerification()
    {
        if (!session()->has('registration_email')) {
            return redirect()
                ->route('student.signup')
                ->withErrors([
                    'email' => 'Please register first.',
                ]);
        }

        return view('student-verify-otp');
    }

    /**
     * Verify the OTP and create the student account.
     */
    public function verify(Request $request)
    {
        $validated = $request->validate([
            'otp' => 'required|digits:6',
        ]);

        $email = session('registration_email');

        if (!$email) {
            return redirect()->route('student.signup')
                ->withErrors(['email' => 'Please register first.']);
        }

        $pending = DB::table('student_registration_otps')
            ->where('email', $email)
            ->first();

        if (!$pending) {
            return redirect()->route('student.signup')
                ->withErrors(['email' => 'Your pending registration was not found. Please register again.']);
        }

        if (now()->greaterThanOrEqualTo($pending->otp_expires_at)) {
            DB::table('student_registration_otps')
                ->where('id', $pending->id)
                ->delete();

            $request->session()->forget('registration_email');

            return redirect()->route('student.signup')
                ->withErrors(['email' => 'Your verification code expired. Please register again.']);
        }

        if ($pending->attempts >= 5) {
            return back()->withErrors([
                'otp' => 'Too many incorrect attempts. Please request a new code after the resend cooldown.',
            ]);
        }

        if (!Hash::check($validated['otp'], $pending->otp_hash)) {
            DB::table('student_registration_otps')
                ->where('id', $pending->id)
                ->increment('attempts');

            return back()->withErrors([
                'otp' => 'The verification code is incorrect.',
            ]);
        }

        try {
            DB::transaction(function () use ($pending) {
                // Recheck uniqueness in case another registration completed meanwhile.
                if (
                    Student::where('student_id', $pending->student_id)->exists()
                    || Student::where('email', $pending->email)->exists()
                ) {
                    throw new \RuntimeException(
                        'This Student ID or email has already been registered.'
                    );
                }

                $student = new Student();
                $student->student_id = $pending->student_id;
                $student->fullname = $pending->full_name;
                $student->email = $pending->email;

                // Password was hashed before being stored in the pending table.
                $student->password = $pending->password;

                $student->save();

                DB::table('student_registration_otps')
                    ->where('id', $pending->id)
                    ->delete();
            });
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'email' => 'We could not complete registration. Please contact your administrator if the issue continues.',
            ]);
        }

        $request->session()->forget('registration_email');

        RateLimiter::clear('registration-otp:' . sha1($email));

        return redirect()
            ->route('student.login')
            ->with('success', 'Your email is verified and your account has been created. You can now log in.');
    }

    /**
     * Resend an OTP, with a 60-second cooldown.
     */

public function resend(Request $request)
{
    $email = session('registration_email');

    if (!$email) {
        return redirect()->route('student.signup')
            ->withErrors(['email' => 'Please register first.']);
    }

    $pending = DB::table('student_registration_otps')
        ->where('email', $email)
        ->first();

    if (!$pending) {
        $request->session()->forget('registration_email');

        return redirect()->route('student.signup')
            ->withErrors([
                'email' => 'Pending registration not found. Please register again.',
            ]);
    }

    if (now()->greaterThanOrEqualTo($pending->otp_expires_at)) {
        DB::table('student_registration_otps')
            ->where('id', $pending->id)
            ->delete();

        $request->session()->forget('registration_email');

        return redirect()->route('student.signup')
            ->withErrors([
                'email' => 'Your registration expired. Please register again.',
            ]);
    }

    $rateKey = 'registration-otp-resend:' . sha1($email);

    if (RateLimiter::tooManyAttempts($rateKey, 1)) {
        $seconds = RateLimiter::availableIn($rateKey);

        return back()->withErrors([
            'otp' => "Please wait {$seconds} seconds before requesting another code.",
        ]);
    }

    $otp = (string) random_int(100000, 999999);

    // Send the email first so a mail failure does not invalidate
    // the previous verification code.
    try {
        Mail::raw(
            "Your new LabTrack verification code is: {$otp}\n\n"
            . "This code expires in 10 minutes. Do not share it with anyone.",
            function ($message) use ($email) {
                $message->to($email)
                    ->subject('Your New LabTrack Verification Code');
            }
        );
    } catch (Throwable $exception) {
        report($exception);

        return back()->withErrors([
            'otp' => 'We could not send a new code. Please try again later.',
        ]);
    }

    DB::table('student_registration_otps')
        ->where('id', $pending->id)
        ->update([
            'otp_hash' => Hash::make($otp),
            'otp_expires_at' => now()->addMinutes(10),
            'attempts' => 0,
            'updated_at' => now(),
        ]);

    RateLimiter::hit($rateKey, 60);

    return back()->with(
        'success',
        'A new verification code has been sent. It expires in 10 minutes.'
    );
}
}