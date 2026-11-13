<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'role' => $request->user()->primary_role,
                    'is_super_admin' => (bool) $request->user()->is_super_admin,
                    'is_visitor' => $request->user()->primary_role === 'visitor' || $request->user()->hasRole('visitor'),
                ] : null,
            ],
            'unmarked_staff_count' => $request->user() ? count(app(\App\Services\EmployeeService::class)->getMissingAttendanceAlerts(now()->toDateString())) : 0,
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'info' => $request->session()->get('info'),
                'warning' => $request->session()->get('warning'),
            ],
        ];
    }
}
