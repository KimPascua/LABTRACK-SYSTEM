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

        $productionHash = (string) config(
            'services.pc_agent.token_hash',
            ''
        );

        $testHash = (string) config(
            'services.pc_agent.test_token_hash',
            ''
        );

        if (
            $testHash !== ''
            && hash_equals($testHash, $providedHash)
        ) {
            $computerName = 'PC-TEST-LAPTOP';
        } elseif (
            $productionHash !== ''
            && hash_equals($productionHash, $providedHash)
        ) {
            $computerName = 'PC-01';
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Invalid agent credentials.',
            ], 401);
        }

        $computer = Computer::where(
            'computer_name',
            $computerName
        )->where('lab', 'CLAB1')->first();

        if (!$computer) {
            return response()->json([
                'success' => false,
                'message' => 'Registered computer was not found.',
            ], 404);
        }

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
