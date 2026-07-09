<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string ...$roles
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!$request->user() || !$request->user()->role) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthorized. Role is missing.'], 403);
            }
            abort(403, 'Unauthorized. Role is missing.');
        }

        $userRole = $request->user()->role->role_name;

        if (!in_array($userRole, $roles)) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Forbidden. You do not have permission to access this resource.'], 403);
            }
            abort(403, 'Forbidden. You do not have permission to access this resource.');
        }

        return $next($request);
    }
}
