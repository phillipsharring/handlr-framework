<?php

declare(strict_types=1);

namespace Handlr\Pipes;

use Handlr\Core\Request;
use Handlr\Core\Response;

/**
 * Authentication guard pipe.
 *
 * Checks if the request is authenticated and returns 401 Unauthorized if not.
 * Use this pipe to protect routes that require a logged-in user.
 *
 * ## Usage
 *
 * Apply to specific routes that require authentication:
 *
 * ```php
 * // Single route
 * $router->get('/profile', ProfilePipe::class)->through([
 *     AuthenticationPipe::class,
 * ]);
 *
 * // Route group
 * $router->group('/account', function ($router) {
 *     $router->get('/settings', SettingsPipe::class);
 *     $router->post('/settings', UpdateSettingsPipe::class);
 * })->through([AuthenticationPipe::class]);
 * ```
 *
 * ## How authentication is checked
 *
 * This pipe calls `$request->isAuthenticated()` which should be implemented
 * in your Request class or set by an earlier pipe that validates tokens/sessions.
 *
 * Example session-based authentication setup:
 *
 * ```php
 * class SessionPipe implements Pipe
 * {
 *     public function handle(Request $request, Response $response, array $args, callable $next): Response
 *     {
 *         session_start();
 *
 *         if (isset($_SESSION['user_id'])) {
 *             $request = $request->withAttribute('user_id', $_SESSION['user_id']);
 *             $request = $request->withAttribute('authenticated', true);
 *         }
 *
 *         return $next($request, $response, $args);
 *     }
 * }
 * ```
 *
 * ## Response
 *
 * Returns 401 Unauthorized with body "Unauthorized" if not authenticated.
 *
 * ## Customizing the response
 *
 * Extend or replace this pipe for custom behavior:
 *
 * ```php
 * class JsonAuthenticationPipe implements Pipe
 * {
 *     public function handle(Request $request, Response $response, array $args, callable $next): Response
 *     {
 *         if (!$request->isAuthenticated()) {
 *             return $response->withJson([
 *                 'error' => 'Authentication required',
 *                 'code' => 'UNAUTHENTICATED',
 *             ], 401);
 *         }
 *
 *         return $next($request, $response, $args);
 *     }
 * }
 * ```
 *
 * ## Combining with authorization
 *
 * Authentication (who you are) and authorization (what you can do) are separate:
 *
 * ```php
 * $router->get('/admin', AdminDashboardPipe::class)->through([
 *     AuthenticationPipe::class,     // Must be logged in
 *     AdminAuthorizationPipe::class, // Must be an admin
 * ]);
 * ```
 */
class AuthenticationPipe implements Pipe
{
    /**
     * Check authentication and continue or return 401.
     *
     * @param Request  $request  The incoming HTTP request
     * @param Response $response The response object to build upon
     * @param array    $args     Route parameters
     * @param callable $next     The next pipe in the chain
     *
     * @return Response 401 if not authenticated, otherwise continues the chain
     */
    public function handle(Request $request, Response $response, array $args, callable $next): Response
    {
        if (!$request->isAuthenticated()) {
            return $response->withStatus(401)->withBody('Unauthorized');
        }

        return $next($request, $response, $args);
    }
}
