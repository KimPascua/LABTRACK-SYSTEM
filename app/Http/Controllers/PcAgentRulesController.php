<?php

namespace App\Http\Controllers;

use App\Models\Application;
use App\Models\Computer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PcAgentRulesController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $providedToken = $request->bearerToken();

        if (!$providedToken) {
            return response()->json([
                'success' => false,
                'message' => 'Agent authentication required.',
            ], 401);
        }

        $providedHash = hash('sha256', $providedToken);

        $testHash = (string) config(
            'services.pc_agent.test_token_hash',
            ''
        );

        $productionHash = (string) config(
            'services.pc_agent.token_hash',
            ''
        );

        if (
            $testHash !== ''
            && hash_equals($testHash, $providedHash)
        ) {
            $computerName = 'PC-TEST-LAPTOP';
            $expectedLab = 'CLAB1';
        } elseif (
            $productionHash !== ''
            && hash_equals($productionHash, $providedHash)
        ) {
            $computerName = 'PC-01';
            $expectedLab = null;
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Invalid agent credentials.',
            ], 401);
        }

        $computerQuery = Computer::where(
            'computer_name',
            $computerName
        );

        if ($expectedLab !== null) {
            $computerQuery->where('lab', $expectedLab);
        }

        $computers = $computerQuery->get();

        if ($computers->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Registered computer was not found.',
            ], 404);
        }

        if ($computers->count() !== 1) {
            return response()->json([
                'success' => false,
                'message' => 'Computer registration is ambiguous.',
            ], 409);
        }

        $computer = $computers->first();

        $rules = Application::where('status', 'Active')
            ->whereIn('scope', [
                'All Laboratories',
                $computer->lab,
            ])
            ->orderBy('application_name')
            ->get([
                'id',
                'application_name',
                'scope',
                'restriction',
                'status',
            ]);

        return response()->json([
            'success' => true,
            'lab' => $computer->lab,
            'rules' => $rules,
        ]);
    }
}
