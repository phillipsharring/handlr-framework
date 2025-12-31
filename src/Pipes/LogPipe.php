<?php

declare(strict_types=1);

namespace Handlr\Pipes;

use Handlr\Core\Request;
use Handlr\Core\Response;
use Handlr\Log\Log;

readonly class LogPipe implements Pipe
{
    public function __construct(private Log $logger) {}

    public function handle(Request $request, Response $response, array $args, callable $next): Response
    {
        $message = '{date} {method} {uri}';
        $context = [
            'date' => date('c'),
            'method' => $request->getMethod(),
            'uri' => $request->getUri(),
        ];
        $this->logger::info($message, $context);

        return $next($request, $response, $args);
    }
}
