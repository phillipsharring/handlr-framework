<?php

namespace Handlr\Csrf;

use Handlr\Core\Request;
use Handlr\Core\Response;
use Handlr\Pipes\Pipe;

class VerifyCsrfTokenPipe implements Pipe
{
    private const SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];
    private const HEADER = 'X-CSRF-Token';

    public function __construct(private readonly CsrfService $csrf)
    {
    }

    public function handle(Request $request, Response $response, array $args, callable $next): Response
    {
        if (in_array($request->getMethod(), self::SAFE_METHODS, true)) {
            return $next($request, $response, $args);
        }

        $token = $request->getHeader(self::HEADER);

        if (!$this->csrf->validateToken($token)) {
            return $response->withStatus(Response::HTTP_FORBIDDEN)
                ->withJson([
                    'status' => 'error',
                    'message' => 'CSRF token invalid or missing.',
                ]);
        }

        $response = $next($request, $response, $args);

        $this->csrf->rotateToken();

        return $response;
    }
}
