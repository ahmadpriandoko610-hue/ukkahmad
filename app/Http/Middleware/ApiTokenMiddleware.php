<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class ApiTokenMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $userId = null;

        if ($token) {
            $userId = Cache::get('api_token_' . hash('sha256', $token));
            // Tokens issued before hashed cache keys remain usable until their TTL expires.
            if (!$userId) {
                $userId = Cache::get('api_token_' . $token);
            }
        }
        $user = $userId ? User::find($userId) : null;

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}
