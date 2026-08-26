<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyWebsiteChatSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $configured = (string) config('services.website_chat.webhook_secret');
        $provided = (string) $request->header('X-VVR-Website-Chat-Secret');

        abort_if(strlen($configured) < 32, 503, 'Website chat webhook is not configured.');
        abort_unless($provided !== '' && hash_equals($configured, $provided), 401, 'Invalid integration credentials.');

        return $next($request);
    }
}
