<?php

namespace App\Http\Controllers;

use App\Models\ApplicationActivity;
use App\Models\Computer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PcAgentActivityController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $providedToken = $request->bearerToken();

        if ($providedToken === null || $providedToken === '') {
            return response()->json([
                'success' => false,
                'message' => 'Agent authentication required.',
            ], 401);
        }

      
$providedHash = hash('sha256', $providedToken);

$productionHash = (string) config(
    'services.pc_agent.token_hash',
    ''
);

$testHash = (string) config(
    'services.pc_agent.test_token_hash',
    ''
);

if (
    $productionHash !== ''
    && hash_equals($productionHash, $providedHash)
) {
    $computerName = 'PC-01';
} elseif (
    $testHash !== ''
    && hash_equals($testHash, $providedHash)
) {
    $computerName = 'PC-TEST-LAPTOP';
} else {
    logger()->warning('PC agent authentication diagnostic', [
        'production_hash_loaded' => $productionHash !== '',
        'test_hash_loaded' => $testHash !== '',
        'test_hash_length' => strlen($testHash),
        'test_hash_format_valid' => preg_match(
            '/^[a-f0-9]{64}$/i',
            $testHash
        ) === 1,
        'token_matches_test_hash' => $testHash !== ''
            && hash_equals($testHash, $providedHash),
    ]);

    return response()->json([
        'success' => false,
        'message' => 'Invalid agent credentials.',
    ], 401);
}


        $productionHash = (string) config(
            'services.pc_agent.token_hash',
            ''
        );

        $testHash = (string) config(
            'services.pc_agent.test_token_hash',
            ''
        );

        // Resolve the computer identity from the matching token.
        // Never trust a computer name supplied by the client.
        if (
            $productionHash !== ''
            && hash_equals($productionHash, $providedHash)
        ) {
            $computerName = 'PC-01';
        } elseif (
            $testHash !== ''
            && hash_equals($testHash, $providedHash)
        ) {
            $computerName = 'PC-TEST-LAPTOP';
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Invalid agent credentials.',
            ], 401);
        }

        $computers = Computer::where(
            'computer_name',
            $computerName
        )->get();

        if ($computers->count() === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Registered computer was not found.',
            ], 404);
        }

        if ($computers->count() > 1) {
            return response()->json([
                'success' => false,
                'message' => 'Duplicate computer registration detected.',
            ], 409);
        }

        $computer = $computers->first();

        $validated = $request->validate([
            'time' => ['required', 'date'],
            'application_name' => ['required', 'string', 'max:100'],
        ]);

        $activityTime = Carbon::parse($validated['time'])
            ->format('Y-m-d H:i:s');

        $activity = ApplicationActivity::create([
            'time' => $activityTime,
            'pc_name' => $computer->computer_name,
            'student_name' => 'Unassigned',
            'lab' => $computer->lab,
            'application_name' => $validated['application_name'],
            'action' => 'Application Changed',
            'status' => 'Recorded',
            'reason' => 'Foreground application changed; executable name only.',
        ]);

        $computer->last_seen = now();

            $status = strtolower((string) $computer->status);

            if (!in_array(
                $status,
                ['locked', 'maintenance', 'maint', 'inuse'],
                true
            )) {
                $computer->status = 'online';
            }

            $computer->save();

        return response()->json([
            'success' => true,
            'message' => 'Application activity recorded.',
            'activity_id' => $activity->id,
        ], 201);
    }
}
