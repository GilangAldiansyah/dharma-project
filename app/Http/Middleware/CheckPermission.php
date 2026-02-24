<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (!$user) {
            return $this->denyAccess($request, 'Unauthenticated.');
        }

        $user->load('roles.permissions');

        if (!$user->hasPermission($permission)) {
            return $this->denyAccess($request, 'You do not have permission to access this resource.');
        }

        return $next($request);
    }

    private function denyAccess(Request $request, string $message): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403);
        }

        return redirect()->back()->with('error', $message);
    }
}
