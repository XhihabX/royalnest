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
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->is_active === false) {
            auth()->logout();

            return redirect()->route('login')->withErrors(['email' => 'Your account has been deactivated.']);
        }

        // Superadmin/owner bypasses all checks
        if (in_array($user->primary_role, ['owner', 'admin'])) {
            return $next($request);
        }

        if (! empty($roles) && ! $user->hasRole($roles)) {
            abort(403, 'Unauthorized access: your role does not permit access to this module.');
        }

        return $next($request);
    }
}
