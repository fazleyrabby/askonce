<?php

use App\Http\Middleware\RequestTelemetry;
use App\Http\Middleware\SetOrganization;
use App\Http\Middleware\UseCloudflareClientIp;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: env('TRUSTED_PROXIES'));
        $middleware->append(UseCloudflareClientIp::class);
        $middleware->append(RequestTelemetry::class);
        $middleware->alias(['organization' => SetOrganization::class]);
        $middleware->prependToPriorityList(SubstituteBindings::class, SetOrganization::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->report(function (Throwable $exception): ?bool {
            if (request()->is('r/*')) {
                Log::error('Public request failed.', ['exception_type' => get_class($exception)]);

                return false;
            }

            return null;
        });
        $exceptions->respond(function (Response $response): Response {
            if (request()->attributes->has('request_id')) {
                $response->headers->set('X-Request-ID', request()->attributes->get('request_id'));
            }
            if (request()->is('r/*')) {
                $response->headers->set('Referrer-Policy', 'no-referrer');
                $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
                $response->headers->set('Cache-Control', 'no-store, private');
            }

            return $response;
        });
        $exceptions->render(function (Throwable $exception, Request $request): ?JsonResponse {
            if ($request->is('r/*') && ! $exception instanceof HttpExceptionInterface && ! $exception instanceof ValidationException) {
                return response()->json(['message' => 'Something went wrong. Please try again.'], 500);
            }

            return null;
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
