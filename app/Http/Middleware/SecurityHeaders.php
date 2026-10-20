<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds a conservative set of browser security headers to every response.
 */
class SecurityHeaders
{
    /**
     * Customer-facing pages that integrators may embed in their own pages.
     * @var string[]
     */
    protected $embeddable = [
        'order/*',
        'topup/*',
        'topup',
    ];

    /**
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle(Request $request, Closure $next)
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        if (!$request->is(...$this->embeddable)) {
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        }

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
