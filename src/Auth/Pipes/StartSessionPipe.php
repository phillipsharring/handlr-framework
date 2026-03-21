<?php

declare(strict_types=1);

namespace Handlr\Auth\Pipes;

use Handlr\Core\Request;
use Handlr\Core\Response;
use Handlr\Pipes\Pipe;
use Handlr\Session\SessionInterface;

class StartSessionPipe implements Pipe
{
    public function __construct(
        private readonly SessionInterface $session
    ) {}

    public function handle(Request $request, Response $response, array $args, callable $next): Response
    {
        $this->session->start();

        return $next($request, $response, $args);
    }
}
