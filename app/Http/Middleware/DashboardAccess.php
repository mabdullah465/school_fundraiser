<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DashboardAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $password = config('dashboard.password');

        // No password configured: the page is open. Setting DASHBOARD_PASSWORD turns on Basic auth.
        if (! is_string($password) || $password === '') {
            return $next($request);
        }

        $validUser = hash_equals((string) config('dashboard.user'), (string) $request->getUser());
        $validPassword = hash_equals($password, (string) $request->getPassword());

        if (! ($validUser && $validPassword)) {
            return response('Authentication required.', 401, ['WWW-Authenticate' => 'Basic realm="Dashboard"']);
        }

        return $next($request);
    }
}
