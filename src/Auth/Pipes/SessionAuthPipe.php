<?php

declare(strict_types=1);

namespace Handlr\Auth\Pipes;

use Handlr\Auth\AuthContext;
use Handlr\Core\Request;
use Handlr\Core\Response;
use Handlr\Pipes\Pipe;
use Handlr\Session\SessionInterface;

class SessionAuthPipe implements Pipe
{
    public function __construct(
        private readonly AuthContext $authContext,
        private readonly SessionInterface $session
    ) {}

    public function handle(Request $request, Response $response, array $args, callable $next): Response
    {
        $userId = $this->session->get('user_id');

        if (is_string($userId)) {
            $this->authContext->setUserId($userId);
        }

        return $next($request, $response, $args);
    }
}
