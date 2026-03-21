<?php

namespace Handlr\Csrf;

use Handlr\Core\Request;
use Handlr\Core\Response;
use Handlr\Pipes\Pipe;

class CorsPipe implements Pipe
{
    private const ALLOWED_METHODS = 'GET, POST, PATCH, DELETE, OPTIONS';
    private const ALLOWED_HEADERS = 'Content-Type, Accept, X-CSRF-Token';
    private const MAX_AGE = '7200';

    public function handle(Request $request, Response $response, array $args, callable $next): Response
    {
        $origin = $request->getHeader('Origin');

        // No Origin header — not a cross-origin request, nothing to do.
        if ($origin === null) {
            return $next($request, $response, $args);
        }

        // Only allow same-origin: derive expected origin from Host header.
        $host = $request->getHeader('Host');
        $scheme = $this->getScheme($request);
        $allowed = "{$scheme}://{$host}";

        if ($origin !== $allowed) {
            // Don't add CORS headers — browser will block the response.
            return $next($request, $response, $args);
        }

        // Preflight — short-circuit with 204 and CORS headers.
        if ($request->getMethod() === 'OPTIONS') {
            return $response->withStatus(Response::HTTP_NO_CONTENT)
                ->withHeader('Access-Control-Allow-Origin', $origin)
                ->withHeader('Access-Control-Allow-Methods', self::ALLOWED_METHODS)
                ->withHeader('Access-Control-Allow-Headers', self::ALLOWED_HEADERS)
                ->withHeader('Access-Control-Allow-Credentials', 'true')
                ->withHeader('Access-Control-Max-Age', self::MAX_AGE);
        }

        // Actual request — add CORS headers to the response on the way back.
        $response = $next($request, $response, $args);

        return $response
            ->withHeader('Access-Control-Allow-Origin', $origin)
            ->withHeader('Access-Control-Allow-Credentials', 'true');
    }

    private function getScheme(Request $request): string
    {
        // CloudFront/load balancers forward the original scheme
        $proto = $request->getHeader('X-Forwarded-Proto');
        if ($proto !== null) {
            return strtolower($proto);
        }

        // Local dev: PHP built-in server is plain HTTP
        $host = $request->getHeader('Host') ?? '';
        if (str_starts_with($host, 'localhost') || str_starts_with($host, '127.0.0.1')) {
            return 'http';
        }

        return 'https';
    }
}
