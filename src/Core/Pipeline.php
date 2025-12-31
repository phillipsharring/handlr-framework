<?php

declare(strict_types=1);

namespace Handlr\Core;

use Handlr\Handlers\Pipe;

class Pipeline
{
    private array $handlers = [];

    public function pipe(Pipe $handler): self
    {
        $this->handlers[] = $handler;
        return $this;
    }

    public function run($request, $response, $args): Response
    {
        $next = static fn ($req, $res, $args) => $res;

        foreach (array_reverse($this->handlers) as $handler) {
            $next = static fn ($req, $res, $args) => $handler->handle($req, $res, $args, $next);
        }

        return $next($request, $response, $args);
    }
}
