<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            if ($request->is('api/*')) {
                return response()->json(['success' => false, 'message' => 'Unauthenticated.'], 401);
            }
            return redirect()->route('login');
        }

        if (!in_array($user->role, $roles, true)) {
            if ($request->is('api/*')) {
                return response()->json(['success' => false, 'message' => 'Anda tidak memiliki hak akses.'], 403);
            }
            abort(403, 'Anda tidak memiliki hak akses.');
        }

        return $next($request);
    }
}
