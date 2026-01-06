<?php

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;

class ApiTokenAuth
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->header('X-API-Token') ?? $request->query('api_token');
        if (!$token || !ApiToken::where('token', $token)->exists()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }
        ApiToken::where('token', $token)->update(['last_used_at' => now()]);
        return $next($request);
    }
}
