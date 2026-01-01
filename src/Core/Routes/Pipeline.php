<?php

declare(strict_types=1);

namespace Handlr\Core\Routes;

use Handlr\Core\Response;
use Handlr\Pipes\Pipe;

class Pipeline
{
    private array $pipes = [];

    public function lay(Pipe $pipe): self
    {
        $this->pipes[] = $pipe;
        return $this;
    }

    public function run($request, $response, $args): Response
    {
        $next = static fn($req, $res, $args) => $res;

        foreach (array_reverse($this->pipes) as $pipe) {
            $next = static fn($req, $res, $args) => $pipe->handle($req, $res, $args, $next);
        }

        return $next($request, $response, $args);
    }
}
