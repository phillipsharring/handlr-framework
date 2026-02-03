<?php

declare(strict_types=1);

namespace Handlr\Pipes;

use Handlr\Core\Request;
use Handlr\Core\Response;

/**
 * Interface for middleware pipes.
 *
 * Pipes are middleware components that process requests and responses in a chain.
 * Each pipe can inspect/modify the request, call the next pipe, and inspect/modify
 * the response on the way back out.
 *
 * ## How pipes work
 *
 * ```
 * Request → [Pipe1] → [Pipe2] → [Pipe3] → Handler
 *                                            ↓
 * Response ← [Pipe1] ← [Pipe2] ← [Pipe3] ← Response
 * ```
 *
 * ## Implementing a pipe
 *
 * ```php
 * class MyPipe implements Pipe
 * {
 *     public function handle(Request $request, Response $response, array $args, callable $next): Response
 *     {
 *         // 1. Do something BEFORE the request continues down the chain
 *         $startTime = microtime(true);
 *
 *         // 2. Call $next() to continue to the next pipe (REQUIRED unless short-circuiting)
 *         $response = $next($request, $response, $args);
 *
 *         // 3. Do something AFTER the response comes back up
 *         $duration = microtime(true) - $startTime;
 *         return $response->withHeader('X-Duration', (string) $duration);
 *     }
 * }
 * ```
 *
 * ## Short-circuiting (returning early)
 *
 * A pipe can return a response without calling `$next()` to stop the chain:
 *
 * ```php
 * public function handle(Request $request, Response $response, array $args, callable $next): Response
 * {
 *     if (!$this->isAuthorized($request)) {
 *         // Short-circuit: return response without calling $next()
 *         return $response->withStatus(403)->withJson(['error' => 'Forbidden']);
 *     }
 *
 *     return $next($request, $response, $args);
 * }
 * ```
 *
 * ## Modifying the request
 *
 * ```php
 * public function handle(Request $request, Response $response, array $args, callable $next): Response
 * {
 *     // Add data to request for downstream pipes/handlers
 *     $request = $request->withAttribute('user', $this->getUser());
 *
 *     return $next($request, $response, $args);
 * }
 * ```
 *
 * ## Using route arguments ($args)
 *
 * Route parameters are passed in `$args`:
 *
 * ```php
 * // Route: /users/{id}
 * public function handle(Request $request, Response $response, array $args, callable $next): Response
 * {
 *     $userId = $args['id'];  // From URL parameter
 *     // ...
 * }
 * ```
 *
 * ## Registering pipes
 *
 * Pipes are typically registered in route definitions:
 *
 * ```php
 * // Global middleware (runs on all routes)
 * $router->addGlobalPipe(new ErrorPipe($logger));
 * $router->addGlobalPipe(new LogPipe($logger));
 *
 * // Route-specific middleware
 * $router->get('/admin', AdminPipe::class)->through([
 *     AuthenticationPipe::class,
 *     AdminAuthorizationPipe::class,
 * ]);
 * ```
 *
 * ## Common pipe patterns
 *
 * - **ErrorPipe**: Wrap in try/catch to handle exceptions
 * - **LogPipe**: Log requests/responses
 * - **AuthenticationPipe**: Verify user is logged in
 * - **AuthorizationPipe**: Verify user has permission
 * - **CorsMiddleware**: Add CORS headers
 * - **RateLimitPipe**: Throttle requests
 * - **CachePipe**: Return cached responses
 */
interface Pipe
{
    /**
     * Handle the request and produce a response.
     *
     * @param Request  $request  The incoming HTTP request
     * @param Response $response The response object to build upon
     * @param array    $args     Route parameters (e.g., ['id' => '123'] from /users/{id})
     * @param callable $next     Call this to continue to the next pipe: $next($request, $response, $args)
     *
     * @return Response The HTTP response (either from $next() or short-circuited)
     */
    public function handle(Request $request, Response $response, array $args, callable $next): Response;
}
