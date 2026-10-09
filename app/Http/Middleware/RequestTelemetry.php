<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class RequestTelemetry
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = (string) Str::uuid();
        $request->attributes->set('request_id', $requestId);
        $started = hrtime(true);
        $status = 500;
        try {
            $response = $next($request);
            $status = $response->getStatusCode();
            $response->headers->set('X-Request-ID', $requestId);

            return $response;
        } catch (Throwable $exception) {
            $status = match (true) {
                $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
                $exception instanceof ValidationException => $exception->status,
                $exception instanceof AuthenticationException => 401,
                $exception instanceof AuthorizationException => $exception->status() ?? 403,
                default => 500,
            };
            throw $exception;
        } finally {
            if (config('app.telemetry_enabled')) {
                Log::log($status >= 500 ? 'error' : 'info', 'http.request', [
                    'event' => 'http.request', 'request_id' => $requestId,
                    'route' => $request->route()?->getName() ?? 'unmatched',
                    'method' => $request->method(), 'status' => $status,
                    'duration_ms' => round((hrtime(true) - $started) / 1_000_000, 2),
                ]);
            }
        }
    }
}
