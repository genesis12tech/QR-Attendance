<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        if ($user->status === UserStatus::Suspended) {
            abort(403);
        }

        if ($user->role !== UserRole::from($role)) {
            abort(403);
        }

        return $next($request);
    }
}
