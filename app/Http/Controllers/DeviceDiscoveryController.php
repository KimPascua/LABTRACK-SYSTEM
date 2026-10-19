<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DeviceDiscoveryController extends Controller
{
    public function store(Request $request)
    {
        $expectedToken = (string) config('services.device_discovery.token');
        $providedToken = (string) $request->bearerToken();

        if (
            $expectedToken === ''
            || $providedToken === ''
            || !hash_equals($expectedToken, $providedToken)
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized discovery upload.',
            ], 401);
        }

        $validated = $request->validate([
            'success' => ['required', 'boolean', 'in:1'],
            'scanned_at' => ['required', 'date'],
            'devices' => ['present', 'array', 'max:200'],
            'devices.*.ip_address' => ['required', 'ip', 'ipv4'],
            'devices.*.mac_address' => [
                'required',
                'regex:/^(?:[0-9A-F]{2}:){5}[0-9A-F]{2}$/i',
            ],
            'devices.*.hostname' => ['nullable', 'string', 'max:255'],
            'devices.*.device_type' => ['nullable', 'string', 'max:100'],
        ]);

        $scannedAt = \Carbon\Carbon::parse($validated['scanned_at']);

        if (
            $scannedAt->isFuture()
            || $scannedAt->lt(now()->subMinutes(5))
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Scan timestamp is invalid or too old.',
            ], 422);
        }

        $devices = [];

        foreach ($validated['devices'] as $device) {
            $mac = strtoupper(str_replace(
                '-',
                ':',
                $device['mac_address']
            ));

            if (
                $mac === '00:00:00:00:00:00'
                || $mac === 'FF:FF:FF:FF:FF:FF'
            ) {
                continue;
            }

            $devices[] = [
                'ip_address' => $device['ip_address'],
                'mac_address' => $mac,
                'hostname' => $device['hostname'] ?? 'Unknown Device',
                'device_type' => $device['device_type'] ?? 'Unknown Device',
            ];
        }

        $result = [
            'success' => true,
            'count' => count($devices),
            'devices' => $devices,
            'scanned_at' => $scannedAt->toIso8601String(),
            'message' => count($devices) . ' device(s) uploaded.',
        ];

        $path = storage_path('app/device-discovery/devices.json');
        $directory = dirname($path);

        if (
            !is_dir($directory)
            && !mkdir($directory, 0755, true)
            && !is_dir($directory)
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Could not create discovery storage directory.',
            ], 500);
        }

        $json = json_encode(
            $result,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );

        if (
            $json === false
            || file_put_contents($path, $json, LOCK_EX) === false
        ) {
            return response()->json([
                'success' => false,
                'message' => 'Could not save discovery results.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'count' => $result['count'],
        ], 201);
    }
}
