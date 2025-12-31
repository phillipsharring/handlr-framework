<?php

declare(strict_types=1);

namespace Handlr\Handlers;

use Handlr\Core\Request;
use Handlr\Core\Response;
use JsonException;
use Throwable;

class ErrorPipe implements Pipe
{
    public function handle(Request $request, Response $response, array $args, callable $next): Response
    {
        try {
            return $next($request, $response, $args);
        } catch (JsonException $e) {
            var_dump(get_class($e));
            return $response->withBody("Error parsing JSON: {$e->getMessage()}")
                ->withStatus(Response::HTTP_SERVER_ERROR);
        } catch (Throwable $e) {
            var_dump(get_class($e));
            var_dump($e->getMessage());
            return $response->withJson((array)$e, Response::HTTP_SERVER_ERROR);
        }
    }
}
