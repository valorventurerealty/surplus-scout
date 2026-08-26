<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\VerifyBesideWebhookSecret;
use App\Http\Middleware\VerifyWebsiteChatSecret;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->alias([
            'beside.webhook' => VerifyBesideWebhookSecret::class,
            'website-chat.webhook' => VerifyWebsiteChatSecret::class,
        ]);
        $middleware->validateCsrfTokens(except: ['integrations/beside/events', 'integrations/website-chat']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
