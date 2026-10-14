<?php

namespace App\Http\Controllers;

use App\Models\DetectedDevice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DetectedDeviceController extends Controller
{
    public function register(Request $request)
    {
        // Require an authenticated student session.
        $student = Auth::guard('student')->user();

        if (
            !$student ||
            (string) $request->session()->get('student_id') !==
            (string) $student->id
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Student login required.',
            ], 401);
        }

        $validated = $request->validate([
            'device_token' => ['required', 'uuid'],
            'device_type' => [
                'required',
                Rule::in(['phone', 'tablet', 'desktop']),
            ],
            'browser' => ['nullable', 'string', 'max:100'],
            'operating_system' => [
                'nullable',
                'string',
                'max:100',
            ],
        ]);

        $device = DetectedDevice::where(
            'device_token',
            $validated['device_token']
        )->first();

        // Do not allow a token already assigned to another student
        // to be silently reassigned.
        if (
            $device &&
            (string) $device->student_id !== (string) $student->id
        ) {
            return response()->json([
                'success' => false,
                'message' => 'This device registration is unavailable.',
            ], 409);
        }

        if (!$device) {
            $device = new DetectedDevice();
            $device->device_token = $validated['device_token'];
        }

        $device->student_id = $student->id;
        $device->device_type = $validated['device_type'];
        $device->browser = $validated['browser'] ?? null;
        $device->operating_system =
            $validated['operating_system'] ?? null;
        $device->status = 'active';
        $device->last_seen = now();
        $device->save();

        return response()->json([
            'success' => true,
            'message' => 'Device activity recorded.',
        ]);
    }
}