<?php

declare(strict_types=1);

namespace Handlr\Auth\Pipes;

use Handlr\Auth\AuthContext;
use Handlr\Core\Request;
use Handlr\Core\Response;
use Handlr\Pipes\Pipe;

class RequireAuthPipe implements Pipe
{
    public function __construct(
        private readonly AuthContext $authContext
    ) {}

    public function handle(Request $request, Response $response, array $args, callable $next): Response
    {
        if (!$this->authContext->isAuthenticated()) {
            // All API routes should return JSON 401, never redirect
            // (Redirects break HTMX and cause layout issues)
            return $response->withStatus(Response::HTTP_UNAUTHORIZED)
                ->withJson(['error' => 'Unauthorized']);
        }

        return $next($request, $response, $args);
    }
}
