<?php

use App\Http\Middleware\EnsureActiveAccount;
use App\Http\Middleware\EnsureAdministrator;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trimStrings(except: ['phone']);
        $middleware->validateCsrfTokens(except: ['stripe/webhook']);
        $middleware->redirectUsersTo(fn (Request $request): string => $request->user()->isAdministrator() ? route('admin.dashboard') : route('home'));

        $middleware->alias([
            'active.account' => EnsureActiveAccount::class,
            'administrator' => EnsureAdministrator::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['code', 'token', 'password', 'password_confirmation', 'current_password']);
        $exceptions->respond(function (Response $response): Response {
            if (request()->is('admin', 'admin/*', 'segundo-factor', 'segundo-factor/*')) {
                $response->headers->set('Cache-Control', 'private, no-store');
            }
            if (request()->routeIs('cart.*', 'store.*', 'home')) {
                $response->headers->set('Cache-Control', 'private, no-store');
            }
            if (request()->is('checkout', 'checkout/*', 'pedidos', 'pedidos/*')) {
                $response->headers->set('Cache-Control', 'private, no-store');
                $response->headers->set('Referrer-Policy', 'no-referrer');
            }

            return $response;
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
