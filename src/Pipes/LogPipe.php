<?php

declare(strict_types=1);

namespace Handlr\Pipes;

use Handlr\Core\Request;
use Handlr\Core\Response;
use Handlr\Log\Logger;

/**
 * Request logging pipe.
 *
 * Logs incoming HTTP requests with method and URI. Useful for debugging,
 * auditing, and monitoring traffic patterns.
 *
 * ## Usage
 *
 * Register as a global pipe (typically after ErrorPipe):
 *
 * ```php
 * $router->addGlobalPipe(new ErrorPipe($logger));
 * $router->addGlobalPipe(new LogPipe($logger));  // Logs all requests
 * ```
 *
 * ## Log output
 *
 * ```
 * [2025-01-15 10:30:00] INFO: GET /api/users
 * [2025-01-15 10:30:01] INFO: POST /api/users
 * [2025-01-15 10:30:02] INFO: GET /api/users/123
 * ```
 *
 * ## Extending for more detail
 *
 * Create a custom pipe for additional logging:
 *
 * ```php
 * class DetailedLogPipe implements Pipe
 * {
 *     public function __construct(private Logger $log) {}
 *
 *     public function handle(Request $request, Response $response, array $args, callable $next): Response
 *     {
 *         $startTime = microtime(true);
 *
 *         $this->log->info('{method} {uri}', [
 *             'method' => $request->getMethod(),
 *             'uri' => $request->getUri(),
 *         ]);
 *
 *         $response = $next($request, $response, $args);
 *
 *         $duration = round((microtime(true) - $startTime) * 1000, 2);
 *         $this->log->info('Response {status} in {duration}ms', [
 *             'status' => $response->getStatusCode(),
 *             'duration' => $duration,
 *         ]);
 *
 *         return $response;
 *     }
 * }
 * ```
 *
 * ## Conditional logging
 *
 * Log only certain requests:
 *
 * ```php
 * class ApiLogPipe implements Pipe
 * {
 *     public function handle(Request $request, Response $response, array $args, callable $next): Response
 *     {
 *         if (str_starts_with($request->getUri(), '/api/')) {
 *             $this->log->info('{method} {uri}', [...]);
 *         }
 *
 *         return $next($request, $response, $args);
 *     }
 * }
 * ```
 */
class LogPipe implements Pipe
{
    /**
     * @param Logger $log Logger instance for recording requests
     */
    public function __construct(private Logger $log) {}

    /**
     * Log the request and continue to the next pipe.
     *
     * @param Request  $request  The incoming HTTP request
     * @param Response $response The response object to build upon
     * @param array    $args     Route parameters
     * @param callable $next     The next pipe in the chain
     *
     * @return Response The response from downstream pipes
     */
    public function handle(Request $request, Response $response, array $args, callable $next): Response
    {
        $this->log->info('{method} {uri}', [
            'method' => $request->getMethod(),
            'uri' => $request->getUri(),
        ]);

        return $next($request, $response, $args);
    }
}
