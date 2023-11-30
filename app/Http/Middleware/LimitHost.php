<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LimitHost
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $trustedIps = [
            '127.0.0.1'
        ];
        $host = $request->getClientIp();

        if (!in_array($host, $trustedIps))
        {
            return response($host, 400);
        }

        return $next($request);
    }
}
