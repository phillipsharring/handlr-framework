<?php

declare(strict_types=1);

namespace Handlr\Pipes;

use Handlr\Core\Request;
use Handlr\Core\Response;
use Handlr\Core\RequestException;
use JsonException;
use Throwable;

class ErrorPipe implements Pipe
{
    public function handle(Request $request, Response $response, array $args, callable $next): Response
    {
        try {
            return $next($request, $response, $args);
        } catch (RequestException $e) {
            return $response->withJson([
                'error' => $e->getMessage(),
            ], $e->getStatusCode());
        } catch (JsonException $e) {
            return $response->withBody("Error parsing JSON: {$e->getMessage()}")
                ->withStatus(Response::HTTP_SERVER_ERROR);
        } catch (Throwable $e) {
            error_log("Uncaught exception: " . get_class($e) . ": " . $e->getMessage());
            error_log($e->getTraceAsString());

            return $response->withJson([
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], Response::HTTP_SERVER_ERROR);
        }
    }
}
