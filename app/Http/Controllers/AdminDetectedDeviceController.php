<?php

namespace App\Http\Controllers;

use App\Models\DetectedDevice;
use Illuminate\Http\Request;

class AdminDetectedDeviceController extends Controller
{
    public function index(Request $request)
    {
        if (!$request->session()->get('admin_logged_in')) {
            return response()->json([
                'success' => false,
                'message' => 'Admin login required.',
            ], 401);
        }

        $devices = DetectedDevice::with([
            'student:id,student_id,fullname',
        ])
            ->orderByDesc('last_seen')
            ->get()
            ->map(function ($device) {
                return [
                    'id' => $device->id,
                    'student_id' => $device->student?->student_id,
                    'student_name' => $device->student?->fullname,
                    'device_type' => $device->device_type,
                    'browser' => $device->browser,
                    'operating_system' => $device->operating_system,
                    'status' => $device->status,
                    'last_seen' => $device->last_seen?->toIso8601String(),
                ];
            });

        return response()->json([
            'success' => true,
            'devices' => $devices,
        ]);
    }
}
