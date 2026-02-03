<?php

declare(strict_types=1);

namespace Handlr\Pipes;

use Handlr\Core\Request;
use Handlr\Core\Response;

/**
 * Static JSON response pipe.
 *
 * Returns a fixed JSON response with a success flag and data payload.
 * This is a terminal pipe - it does NOT call `$next()`.
 *
 * ## Basic usage
 *
 * ```php
 * // Health check endpoint
 * $router->get('/health', new JsonPipe(['status' => 'ok']));
 *
 * // Static configuration endpoint
 * $router->get('/api/config', new JsonPipe([
 *     'version' => '1.0.0',
 *     'features' => ['auth', 'payments'],
 * ]));
 * ```
 *
 * ## Response format
 *
 * ```json
 * {
 *     "success": true,
 *     "data": {
 *         "status": "ok"
 *     }
 * }
 * ```
 *
 * ## Error responses
 *
 * ```php
 * // Return a failure response
 * $router->get('/maintenance', new JsonPipe(
 *     ['message' => 'System under maintenance'],
 *     success: false
 * ));
 * ```
 *
 * ```json
 * {
 *     "success": false,
 *     "data": {
 *         "message": "System under maintenance"
 *     }
 * }
 * ```
 *
 * ## When to use
 *
 * Use JsonPipe for static responses where the data doesn't depend on the request.
 * For dynamic responses, create a custom pipe:
 *
 * ```php
 * class UserListPipe implements Pipe
 * {
 *     public function __construct(private UsersTable $users) {}
 *
 *     public function handle(Request $request, Response $response, array $args, callable $next): Response
 *     {
 *         $users = $this->users->findWhere([], ['status' => 'active']);
 *
 *         return $response->withJson([
 *             'success' => true,
 *             'data' => array_map(fn($u) => $u->toArray(), $users),
 *         ]);
 *     }
 * }
 * ```
 *
 * ## Note
 *
 * This pipe is terminal - it returns the JSON response immediately.
 * It does not call `$next()`, so any pipes after it in the chain will not run.
 */
class JsonPipe implements Pipe
{
    /**
     * @param array|null $data    Data payload to include in the response
     * @param bool       $success Success flag (true = success, false = failure)
     */
    public function __construct(public ?array $data = [], public bool $success = true) {}

    /**
     * Return a JSON response with success flag and data.
     *
     * @param Request  $request  The incoming HTTP request (unused)
     * @param Response $response The response object to build upon
     * @param array    $args     Route parameters (unused)
     * @param callable $next     The next pipe (NOT called - this is terminal)
     *
     * @return Response JSON response with {success, data} structure
     */
    public function handle(Request $request, Response $response, array $args, callable $next): Response
    {
        return $response->withJson([
            'success' => $this->success,
            'data' => $this->data,
        ]);
    }
}
