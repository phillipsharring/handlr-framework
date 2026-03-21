<?php

namespace Handlr\Csrf;

use Handlr\Core\Request;
use Handlr\Core\Response;
use Handlr\Pipes\Pipe;

class VerifyOriginPipe implements Pipe
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function handle(Request $request, Response $response, array $args, callable $next): Response
    {
        if (in_array($request->getMethod(), self::SAFE_METHODS, true)) {
            return $next($request, $response, $args);
        }

        $origin = $request->getHeader('Origin');
        $referer = $request->getHeader('Referer');

        // If neither header is present, allow — non-browser clients (curl, etc.)
        // won't send these. CSRF token validation is the primary defense.
        if ($origin === null && $referer === null) {
            return $next($request, $response, $args);
        }

        $expectedHost = $request->getHeader('Host');
        $sourceHost = $origin !== null
            ? parse_url($origin, PHP_URL_HOST)
            : parse_url($referer, PHP_URL_HOST);

        // Include port in comparison when present
        $sourcePort = $origin !== null
            ? parse_url($origin, PHP_URL_PORT)
            : parse_url($referer, PHP_URL_PORT);

        $source = $sourcePort ? "{$sourceHost}:{$sourcePort}" : $sourceHost;

        if ($source !== $expectedHost) {
            return $response->withStatus(Response::HTTP_FORBIDDEN)
                ->withJson([
                    'status' => 'error',
                    'message' => 'Cross-origin request rejected.',
                ]);
        }

        return $next($request, $response, $args);
    }
}
