<?php

declare(strict_types=1);

namespace Handlr\Pipes;

use Handlr\Core\Request;
use Handlr\Core\Response;
use Handlr\Log\Logger;

class LogPipe implements Pipe
{
    public function __construct(private Logger $log) {}

    public function handle(Request $request, Response $response, array $args, callable $next): Response
    {
        $this->log->info('{method} {uri}', [
            'method' => $request->getMethod(),
            'uri' => $request->getUri(),
        ]);

        return $next($request, $response, $args);
    }
}
