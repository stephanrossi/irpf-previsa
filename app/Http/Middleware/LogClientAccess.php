<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LogClientAccess
{
    /**
     * Log client info (IP/host/user-agent) for every request.
     */
    public function handle(Request $request, Closure $next)
    {
        Log::info('web_access', [
            'ip' => $request->ip(),
            'host' => $request->getHost(),
            'method' => $request->getMethod(),
            'path' => $request->path(),
            'query' => $request->query(),
            'ua' => $request->userAgent(),
        ]);

        return $next($request);
    }
}
