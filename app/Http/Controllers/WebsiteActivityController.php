<?php

namespace App\Http\Controllers;

use App\Models\Computer;
use App\Models\Violation;
use App\Models\WebsiteActivity;
use App\Models\WebsiteRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WebsiteActivityController extends Controller
{
    private function authorized(Request $request): bool
    {
        $expectedHash = (string) config('services.website_agent.token_hash', '');
        $providedToken = (string) $request->bearerToken();

        if ($expectedHash === '' || $providedToken === '') {
            return false;
        }

        return hash_equals(strtolower($expectedHash), hash('sha256', $providedToken));
    }

    private function denyUnlessAuthorized(Request $request)
    {
        if (!$this->authorized($request)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
        }

        return null;
    }

    public function rules(Request $request)
    {
        if ($denied = $this->denyUnlessAuthorized($request)) {
            return $denied;
        }

        $lab = trim((string) $request->query('lab', ''));
        $query = WebsiteRule::query()->where('status', 'Active');

        if ($lab !== '') {
            $query->where(function ($q) use ($lab) {
                $q->where('scope', 'All')
                  ->orWhere(function ($specific) use ($lab) {
                      $specific->where('scope', 'Lab')->where('lab', $lab);
                  });
            });
        } else {
            $query->where('scope', 'All');
        }

        return response()->json([
            'success' => true,
            'rules' => $query->orderBy('website_domain')->get([
                'id', 'website_domain', 'scope', 'lab', 'restriction', 'status', 'reason',
            ]),
        ]);
    }

    public function store(Request $request)
    {
        if ($denied = $this->denyUnlessAuthorized($request)) {
            return $denied;
        }


       $validator = Validator::make($request->all(), [
    'website_domain' => [
    'required',
    'string',
    'max:253',
    'regex:/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i',
    ],
    'pc_name' => ['required', 'string', 'max:50'],
    'lab' => ['required', 'string', 'max:50'],
    'student_id' => ['nullable', 'string', 'max:50'],
    'student_name' => ['nullable', 'string', 'max:100'],
    'visited_at' => ['nullable', 'date'],
]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid website activity payload.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $domain = strtolower(rtrim(trim($request->input('website_domain')), '.'));
        $pcName = trim($request->input('pc_name'));
        $lab = trim($request->input('lab'));
        $studentId = $request->input('student_id') ?: null;
        $studentName = trim((string) $request->input('student_name', '')) ?: 'Unassigned';

        // Ensure the reported computer is registered before accepting events.
        $computer = Computer::query()->where('computer_name', $pcName)->first();
        if (!$computer) {
            return response()->json([
                'success' => false,
                'message' => 'Computer is not registered in LabTrack.',
            ], 422);
        }

        // Use the registered lab as the source of truth rather than trusting the agent payload.
        $lab = (string) $computer->lab;

        $matchingRules = WebsiteRule::query()
            ->where('status', 'Active')
            ->whereNotNull('website_domain')
            ->where(function ($q) use ($domain) {
                $q->whereRaw('LOWER(website_domain) = ?', [$domain])
                  ->orWhereRaw('? LIKE CONCAT("%.", LOWER(website_domain))', [$domain]);
            })
            ->where(function ($q) use ($lab) {
                $q->where('scope', 'All')
                  ->orWhere(function ($specific) use ($lab) {
                      $specific->where('scope', 'Lab')->where('lab', $lab);
                  });
            })
            ->get();

        // Prefer the most specific domain match, then lab-specific rule over All.
        $rule = $matchingRules->sort(function ($a, $b) use ($lab) {
            $domainCompare = strlen((string) $b->website_domain) <=> strlen((string) $a->website_domain);
            if ($domainCompare !== 0) return $domainCompare;
            $aSpecific = $a->scope === 'Lab' && $a->lab === $lab;
            $bSpecific = $b->scope === 'Lab' && $b->lab === $lab;
            return ($bSpecific <=> $aSpecific);
        })->first();

        $isRestricted = $rule && in_array(strtolower((string) $rule->restriction), ['blocked', 'block', 'restricted', 'deny'], true);
        $status = $isRestricted ? 'Violation' : 'Allowed';
        $reason = $isRestricted ? ($rule->reason ?: 'Website matches an active restricted-domain rule.') : null;
        $visitedAt = $request->input('visited_at') ? now()->parse($request->input('visited_at')) : now();

        $activity = WebsiteActivity::create([
            'pc_name' => $pcName,
            'student_id' => $studentId,
            'student_name' => $studentName,
            'lab' => $lab,
            'website_domain' => $domain,
            'action' => 'Visit',
            'status' => $status,
            'reason' => $reason,
            'visited_at' => $visitedAt,
        ]);

        if ($isRestricted) {
            Violation::create([
                'student_id' => $studentId,
                'student_name' => $studentName,
                'computer_name' => $pcName,
                'lab' => $lab,
                'violation' => 'Restricted Website',
                'severity' => 'Medium',
                'violation_time' => $visitedAt,
                'status' => 'Unresolved',
                'description' => 'Detected visit to ' . $domain . '. ' . $reason,
            ]);
        }

        return response()->json([
            'success' => true,
            'activity_id' => $activity->id,
            'website_domain' => $domain,
            'status' => $status,
            'restricted' => (bool) $isRestricted,
            'message' => $isRestricted
                ? 'Restricted website visit recorded. This endpoint logs violations; it does not block browser navigation.'
                : 'Website visit recorded.',
        ], 201);
    }
}
