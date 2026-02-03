<?php

declare(strict_types=1);

namespace Handlr\Pipes;

use Handlr\Core\Request;
use Handlr\Core\Response;
use Handlr\Core\RequestException;
use Handlr\Log\Logger;
use JsonException;
use Throwable;

/**
 * Global error handling pipe.
 *
 * Wraps the entire request pipeline in a try/catch to handle exceptions
 * gracefully and return appropriate error responses. Should be one of
 * the first pipes in the chain (outermost layer).
 *
 * ## Exception handling
 *
 * - **RequestException**: Returns JSON with the exception's status code and message
 * - **JsonException**: Returns 500 with JSON parsing error details
 * - **Throwable** (anything else): Logs the error and returns 500 with details
 *
 * ## Usage
 *
 * Register as a global pipe early in the chain:
 *
 * ```php
 * $router->addGlobalPipe(new ErrorPipe($logger));  // First!
 * $router->addGlobalPipe(new LogPipe($logger));
 * // ... other pipes
 * ```
 *
 * ## Throwing errors from handlers
 *
 * Use RequestException to return specific HTTP error codes:
 *
 * ```php
 * // In a handler or pipe
 * throw new RequestException('User not found', 404);
 * throw new RequestException('Validation failed', 422);
 * throw new RequestException('Unauthorized', 401);
 * ```
 *
 * ## Response format
 *
 * All errors return JSON:
 *
 * ```json
 * // RequestException
 * {"error": "User not found"}
 *
 * // Other exceptions (includes debug info - consider hiding in production)
 * {
 *     "error": "Call to undefined method...",
 *     "exception": "Error",
 *     "file": "/app/src/Handler.php",
 *     "line": 42
 * }
 * ```
 *
 * ## Production considerations
 *
 * In production, you may want to hide exception details:
 *
 * ```php
 * class ProductionErrorPipe extends ErrorPipe
 * {
 *     public function handle(Request $request, Response $response, array $args, callable $next): Response
 *     {
 *         try {
 *             return $next($request, $response, $args);
 *         } catch (RequestException $e) {
 *             return $response->withJson(['error' => $e->getMessage()], $e->getStatusCode());
 *         } catch (Throwable $e) {
 *             $this->log->error(...);
 *             return $response->withJson(['error' => 'Internal server error'], 500);
 *         }
 *     }
 * }
 * ```
 */
class ErrorPipe implements Pipe
{
    /**
     * @param Logger $log Logger for recording uncaught exceptions
     */
    public function __construct(private Logger $log) {}

    /**
     * Handle the request, catching any exceptions from downstream pipes.
     *
     * @param Request  $request  The incoming HTTP request
     * @param Response $response The response object to build upon
     * @param array    $args     Route parameters
     * @param callable $next     The next pipe in the chain
     *
     * @return Response Error response if an exception was caught, otherwise the normal response
     */
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
            $this->log->error("Uncaught exception: {class}: {message}", [
                'class' => get_class($e),
                'message' => $e->getMessage(),
            ]);
            $this->log->debug($e->getTraceAsString());

            return $response->withJson([
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], Response::HTTP_SERVER_ERROR);
        }
    }
}
