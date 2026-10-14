<?php

namespace App\Http\Controllers;

use App\Models\Computer;
use Illuminate\Http\Request;
use App\Models\Student;

class ComputerController extends Controller
{
    public function page()
    {
        $computers = Computer::orderBy('lab')
            ->orderBy('computer_name')
            ->get();

        $students = Student::orderBy('student_id')
            ->get();

        return view('admin-computer-management', compact(
            'computers',
            'students'
        ));
    }

public function index()
{
    $path = storage_path('app/device-discovery/devices.json');
    $freshnessSeconds = 90;

    if (is_file($path) && is_readable($path)) {
        $result = json_decode(file_get_contents($path), true);

        if (
            is_array($result)
            && ($result['success'] ?? false) === true
            && is_array($result['devices'] ?? null)
            && !empty($result['scanned_at'])
        ) {
            try {
                $scannedAt = \Carbon\Carbon::parse($result['scanned_at']);
                $ageSeconds = now()->diffInSeconds($scannedAt);

                $isFresh = $scannedAt->lte(now()->addSeconds(10))
                    && $ageSeconds <= $freshnessSeconds;

                if ($isFresh) {
                    foreach ($result['devices'] as $device) {
                        if (
                            !is_array($device)
                            || !filter_var(
                                $device['ip_address'] ?? null,
                                FILTER_VALIDATE_IP,
                                FILTER_FLAG_IPV4
                            )
                            || !preg_match(
                                '/^(?:[0-9A-F]{2}[:-]){5}[0-9A-F]{2}$/i',
                                $device['mac_address'] ?? ''
                            )
                        ) {
                            continue;
                        }

                        $mac = strtoupper(str_replace(
                            '-',
                            ':',
                            trim($device['mac_address'])
                        ));

                        $ip = $device['ip_address'];

                        $computer = Computer::whereRaw(
                            "UPPER(REPLACE(mac_address, '-', ':')) = ?",
                            [$mac]
                        )->first();

                        if (!$computer) {
                            $computer = Computer::where('ip_address', $ip)
                                ->first();
                        }

                        // Only synchronize already-registered computers.
                        if (!$computer) {
                            continue;
                        }

                        $updates = [
                            'last_seen' => $scannedAt,
                            'ip_address' => $ip,
                        ];

                        // Preserve manually managed states.
                        $status = strtolower((string) $computer->status);

                        if (!in_array(
                            $status,
                            ['locked', 'maintenance', 'maint', 'inuse'],
                            true
                        )) {
                            $updates['status'] = 'online';
                        }

                        $computer->update($updates);
                    }
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning(
                    'Computer discovery sync failed.',
                    ['error' => $e->getMessage()]
                );
            }
        }
    }

    // Mark previously online computers offline after the last
    // successful detection becomes too old.
    Computer::whereIn('status', ['online', 'idle'])
        ->where(function ($query) use ($freshnessSeconds) {
            $query->whereNull('last_seen')
                ->orWhere(
                    'last_seen',
                    '<',
                    now()->subSeconds($freshnessSeconds)
                );
        })
        ->update(['status' => 'offline']);

    $computers = Computer::orderBy('lab')
        ->orderBy('computer_name')
        ->get();

    return response()->json([
        'success' => true,
        'computers' => $computers,
    ]);
}



    
/* ==================== ADD COMPUTER ==================== */

public function store(Request $request)
{
    $validated = $request->validate([
        'student_id' => 'nullable|string|max:50',
        'computer_name' => 'required|string|max:50',
        'lab' => 'required|string|max:50',
        'ip_address' => 'nullable|ip',
        'mac_address' => 'nullable|string|max:100',
        'status' => 'nullable|string|max:30',
        'operating_system' => 'nullable|string|max:100',
    ]);

    // Normalize MAC address before checking duplicates.
    $mac = strtoupper(trim($validated['mac_address'] ?? ''));
    $ip = trim($validated['ip_address'] ?? '');

    $duplicate = null;

    if ($mac !== '') {
        $duplicate = Computer::where('mac_address', $mac)->first();
    } elseif ($ip !== '') {
        $duplicate = Computer::where('lab', $validated['lab'])
            ->where('ip_address', $ip)
            ->first();
    }

    if ($duplicate) {
        return response()->json([
            'success' => false,
            'message' => 'This device is already registered.',
            'computer' => $duplicate,
        ], 409);
    }

    $validated['mac_address'] = $mac !== '' ? $mac : null;

    // Registration alone does not prove a PC is online.
    $validated['status'] = 'offline';

    $computer = Computer::create($validated);

    return response()->json([
        'success' => true,
        'message' => 'Computer registered successfully.',
        'computer' => $computer,
    ], 201);
}



    /* ==================== UPDATE COMPUTER ==================== */

    public function update(Request $request, Computer $computer)
    {
        $validated = $request->validate([
            'student_id' => 'nullable|string|max:50',
            'computer_name' => 'required|string|max:50',
            'lab' => 'required|string|max:50',
            'ip_address' => 'nullable|string|max:50',
            'mac_address' => 'nullable|string|max:100',
            'status' => 'required|string|max:30',
            'operating_system' => 'nullable|string|max:100',
        ]);

        $computer->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Computer updated successfully.',
            'computer' => $computer
        ]);
    }


    /* ==================== DELETE COMPUTER ==================== */

    public function destroy(Computer $computer)
    {
        $computer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Computer deleted successfully.'
        ]);
    }

/* ==================== SCAN DEVICES ==================== */

public function scanDevices()
{
    $path = storage_path('app/device-discovery/devices.json');

    if (!is_file($path) || !is_readable($path)) {
        return response()->json([
            'success' => false,
            'message' => 'No discovery results found. Run the Windows discovery helper first.',
            'devices' => [],
        ], 503);
    }

    $result = json_decode(file_get_contents($path), true);

    if (
        !is_array($result)
        || ($result['success'] ?? false) !== true
        || !is_array($result['devices'] ?? null)
    ) {
        return response()->json([
            'success' => false,
            'message' => 'Discovery results are invalid or the scan failed.',
            'devices' => [],
        ], 502);
    }

    $devices = [];

    foreach ($result['devices'] as $device) {
        if (
            !is_array($device)
            || !filter_var(
                $device['ip_address'] ?? null,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_IPV4
            )
            || !preg_match(
                '/^(?:[0-9A-F]{2}:){5}[0-9A-F]{2}$/i',
                $device['mac_address'] ?? ''
            )
        ) {
            continue;
        }

        $devices[] = [
            'ip_address' => $device['ip_address'],
            'mac_address' => strtoupper($device['mac_address']),
            'hostname' => $device['hostname'] ?? 'Unknown Device',
            'device_type' => $device['device_type'] ?? 'Unknown Device',
        ];
    }

    return response()->json([
        'success' => true,
        'message' => count($devices) . ' device(s) found.',
        'devices' => $devices,
    ]);
}



/* ==================== RESTART ALL ==================== */
public function restartAll(Request $request)
{
    $lab = $request->input('lab');

    $computers = Computer::where('lab', $lab)
        ->whereIn('status', [
            'online',
            'inuse',
            'idle'
        ])
        ->get();

    $count = $computers->count();

    return response()->json([
        'success' => true,
        'message' =>
            "Restart command sent to {$count} computer(s) in {$lab}."
    ]);
}

function restartAllComputers() {
    console.log('RESTART ALL BUTTON CLICKED');

    openActionConfirm(
        'Restart All Computers',
        `Are you sure you want to restart all online computers in ${activeLab}?`,
        'restart'
    );
}


/* ==================== LOCK ALL ==================== */
public function lockAll(Request $request)
{
    $lab = $request->input('lab');

    $computers = Computer::where('lab', $lab)
        ->whereIn('status', [
            'online',
            'inuse',
            'idle'
        ])
        ->get();

    $count = $computers->count();

    foreach ($computers as $computer) {

        $computer->update([
            'status' => 'locked'
        ]);

    }

    return response()->json([
        'success' => true,
        'message' =>
            "{$count} computer(s) in {$lab} have been locked."
    ]);
}
}

function lockAllComputers() {
    console.log('LOCK ALL BUTTON CLICKED');

    openActionConfirm(
        'Lock All Computers',
        `Are you sure you want to lock all computers in ${activeLab}?`,
        'lock'
    );
}