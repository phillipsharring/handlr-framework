<?php

declare(strict_types=1);

namespace Handlr\Pipes;

use Handlr\Core\Request;
use Handlr\Core\Response;

class JsonPipe implements Pipe
{
    public function __construct(public ?array $data = [], public bool $success = true) {}

    public function handle(Request $request, Response $response, array $args, callable $next): Response
    {
        return $response->withJson([
            'success' => $this->success,
            'data' => $this->data,
        ]);
    }
}
