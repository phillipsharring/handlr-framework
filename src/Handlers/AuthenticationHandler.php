<?php

declare(strict_types=1);

namespace Handlr\Handlers;

use Handlr\Core\Request;
use Handlr\Core\Response;

class AuthenticationHandler implements Handler
{
    public function handle(Request $request, Response $response, array $args, callable $next): Response
    {
        if (!$request->isAuthenticated()) {
            return $response->withStatus(401)->withBody('Unauthorized');
        }

        return $next($request, $response, $args);
    }
}
