<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->session()->get('admin_logged_in')) {
            return response()->json([
                'success' => false,
                'message' => 'Administrator authentication required.',
            ], 401);
        }

        return $next($request);
    }
}
