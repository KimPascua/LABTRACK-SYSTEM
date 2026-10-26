<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\ApplicationActivity;
use App\Models\Computer;
use App\Models\Violation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PcAgentActivityController extends Controller
{
    /**
     * Explicit executable mappings only.
     * Unknown applications are recorded as activity, not violations.
     */
    private const EXECUTABLE_RULE_MAP = [
    'discord' => ['discord.exe'],
    'steam' => ['steam.exe'],
    'notepad' => ['notepad.exe'],
    'torrent clients' => [
        'qbittorrent.exe',
        'utorrent.exe',
        'bittorrent.exe',
        'transmission-qt.exe',
        'deluge.exe',
    ],
];

    public function store(Request $request): JsonResponse
    {
        $providedToken = $request->bearerToken();

        if (!$providedToken) {
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
            return response()->json([
                'success' => false,
                'message' => 'Invalid agent credentials.',
            ], 401);
        }

        $computers = Computer::where(
            'computer_name',
            $computerName
        )->get();

        if ($computers->isEmpty()) {
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

        $activityTime = Carbon::parse($validated['time']);
        $applicationName = trim($validated['application_name']);

        $activity = ApplicationActivity::create([
            'time' => $activityTime->format('Y-m-d H:i:s'),
            'pc_name' => $computer->computer_name,
            'student_name' => 'Unassigned',
            'lab' => $computer->lab,
            'application_name' => $applicationName,
            'action' => 'Application Changed',
            'status' => 'Recorded',
            'reason' => 'Foreground application changed; executable name only.',
        ]);

        $violationCreated = $this->recordMatchingViolation(
            $computer,
            $applicationName,
            $activityTime
        );

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
            'violation_created' => $violationCreated,
        ], 201);
    }

    private function recordMatchingViolation(
        Computer $computer,
        string $applicationName,
        Carbon $activityTime
    ): bool {
        // This matching logic expects executable names such as discord.exe.
        $executable = strtolower(
            basename(str_replace('\\', '/', $applicationName))
        );

        $activeRules = Application::query()
            ->where('status', 'Active')
            ->where(function ($query) use ($computer) {
                $query->where('scope', 'All Laboratories')
                    ->orWhere('scope', $computer->lab);
            })
            ->get();

        foreach ($activeRules as $rule) {
            $ruleName = strtolower(trim($rule->application_name));
            $knownExecutables = self::EXECUTABLE_RULE_MAP[$ruleName] ?? [];

            if (!in_array($executable, $knownExecutables, true)) {
                continue;
            }

            $restriction = strtolower(trim($rule->restriction));

            if (!in_array($restriction, ['restricted', 'blocked'], true)) {
                continue;
            }

            $violationType = $rule->application_name
                . ' (' . $rule->restriction . ')';

            // Avoid duplicate unresolved entries for the same rule
            // on this computer within five minutes.
            $recentDuplicate = Violation::query()
    ->where('computer_name', $computer->computer_name)
    ->where('violation', $violationType)
    ->where('status', 'Unresolved')
    ->whereBetween('violation_time', [
        $activityTime->copy()->subMinutes(5),
        $activityTime,
    ])
    ->exists();


            if ($recentDuplicate) {
                return false;
            }

            Violation::create([
                'student_id' => $computer->student_id ?: null,
                'student_name' => 'Unassigned',
                'computer_name' => $computer->computer_name,
                'lab' => $computer->lab,
                'violation' => $violationType,
                'severity' => $restriction === 'blocked'
                    ? 'High'
                    : 'Medium',
                'violation_time' => $activityTime,
                'status' => 'Unresolved',
                'description' => sprintf(
                    'The PC agent detected executable "%s", matching the active "%s" rule. This records a detection only; the application was not blocked or terminated.',
                    $executable,
                    $rule->application_name
                ),
            ]);

            return true;
        }

        return false;
    }
}
