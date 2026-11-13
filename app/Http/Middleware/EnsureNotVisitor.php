<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureNotVisitor
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ($user->isVisitor() || $user->primary_role === 'visitor')) {
            // Whitelist safe session actions such as logout
            if ($request->is('logout') || $request->routeIs('logout')) {
                return $next($request);
            }

            // Block any state-changing HTTP methods
            if (in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'])) {
                if ($request->expectsJson() || $request->header('X-Inertia')) {
                    return response()->json([
                        'message' => 'Visitor accounts have read-only permissions and cannot modify or save data. (ভিজিটর একাউন্ট শুধুমাত্র দেখার জন্য, কোন তথ্য পরিবর্তন বা সংরক্ষণ করা সম্ভব নয়।)',
                    ], 403);
                }

                abort(403, 'Visitor accounts have read-only permissions and cannot modify or save data. (ভিজিটর একাউন্ট শুধুমাত্র দেখার জন্য, কোন তথ্য পরিবর্তন বা সংরক্ষণ করা সম্ভব নয়।)');
            }
        }

        return $next($request);
    }
}
