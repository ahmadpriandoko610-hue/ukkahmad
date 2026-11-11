<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Api\AuthController as ApiAuthController;
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
            $tokenHash = hash('sha256', $token);
            $userId = Cache::get(ApiAuthController::TOKEN_CACHE_PREFIX . $tokenHash);

            if ($userId) {
                $userEpoch = Cache::get(ApiAuthController::USER_TOKEN_EPOCH_PREFIX . $userId);
                if ($userEpoch !== null) {
                    $tokenEpoch = Cache::get(ApiAuthController::TOKEN_EPOCH_CACHE_PREFIX . $tokenHash);
                    if ($tokenEpoch === null || (int) $tokenEpoch !== (int) $userEpoch) {
                        Cache::forget(ApiAuthController::TOKEN_CACHE_PREFIX . $tokenHash);
                        Cache::forget(ApiAuthController::TOKEN_EPOCH_CACHE_PREFIX . $tokenHash);
                        $userId = null;
                    }
                }
            } else {
                // Tokens issued before hashed cache keys remain usable until their TTL expires,
                // unless the user's token epoch has been rotated (e.g., after password reset).
                $legacyUserId = Cache::get(ApiAuthController::TOKEN_CACHE_PREFIX . $token);
                if ($legacyUserId && Cache::get(ApiAuthController::USER_TOKEN_EPOCH_PREFIX . $legacyUserId) === null) {
                    $userId = $legacyUserId;
                }
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
