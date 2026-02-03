<?php

declare(strict_types=1);

namespace Handlr\Core\Routes;

/**
 * Route group for organizing routes with shared prefixes and middleware.
 *
 * Groups allow you to define a common URL prefix and shared pipes (middleware)
 * that apply to all routes within the group. Groups can be nested using
 * group() and end().
 *
 * IMPORTANT: Use end() to return to the parent group after defining nested routes.
 * Without end(), subsequent routes will be added to the nested group.
 *
 * @example Basic group with shared prefix:
 *     $router->group('/api', [AuthPipe::class])
 *         ->get('/users', [ListUsersHandler::class])      // GET /api/users
 *         ->post('/users', [CreateUserHandler::class])    // POST /api/users
 *     ->end();
 *
 * @example Nested groups - ALWAYS use end() to return to parent:
 *     $router->group('/api', [AuthPipe::class])
 *         ->get('/status', [StatusHandler::class])        // GET /api/status
 *
 *         ->group('/users', [RateLimitPipe::class])       // Nested: /api/users + RateLimit
 *             ->get('/', [ListUsersHandler::class])       // GET /api/users
 *             ->get('/{id}', [GetUserHandler::class])     // GET /api/users/{id}
 *             ->post('/', [CreateUserHandler::class])     // POST /api/users
 *         ->end()                                         // Back to /api group
 *
 *         ->group('/posts')                               // Nested: /api/posts
 *             ->get('/', [ListPostsHandler::class])       // GET /api/posts
 *         ->end()                                         // Back to /api group
 *
 *     ->end();                                            // Back to root
 *
 * @example Pipes are inherited and merged:
 *     $router->group('/admin', [AuthPipe::class, AdminPipe::class])
 *         ->group('/users', [AuditPipe::class])
 *             // Routes here have: AuthPipe + AdminPipe + AuditPipe
 *             ->delete('/{id}', [DeleteUserHandler::class])
 *         ->end()
 *     ->end();
 */
final class RouteGroup
{
    /** @var string URL prefix for all routes in this group */
    private string $prefix;

    /** @var array<class-string|object> Pipes (middleware) applied to all routes */
    private array $pipes;

    /**
     * @param Router $router The router instance to register routes with
     * @param string $prefix URL prefix for this group
     * @param array<class-string|object> $pipes Pipes to apply to all routes
     * @param RouteGroup|null $parent Parent group for nesting (used by group())
     */
    public function __construct(
        private readonly Router $router,
        string $prefix,
        array $pipes = [],
        private readonly ?RouteGroup $parent = null
    ) {
        $this->prefix = $this->router->normalizePath($prefix);
        $this->pipes = $pipes;
    }

    /**
     * Create a nested route group with additional prefix and pipes.
     *
     * The new group inherits this group's prefix and pipes, then adds its own.
     * IMPORTANT: Call end() after defining routes to return to this group.
     *
     * @param string $prefix Additional prefix (appended to current prefix)
     * @param array<class-string|object> $pipes Additional pipes (merged with current)
     * @return self New nested RouteGroup
     *
     * @example
     *     $router->group('/api')
     *         ->group('/v1')           // Creates /api/v1 group
     *             ->get('/users', [...])
     *         ->end()                  // Returns to /api group
     *         ->group('/v2')           // Creates /api/v2 group
     *             ->get('/users', [...])
     *         ->end()
     *     ->end();
     */
    public function group(string $prefix, array $pipes = []): self
    {
        $prefix = $this->joinPaths($this->prefix, $prefix);
        $pipes = array_merge($this->pipes, $pipes);
        return new self($this->router, $prefix, $pipes, $this);
    }

    /**
     * End the current group and return to the parent group.
     *
     * ALWAYS call end() after finishing routes in a nested group,
     * otherwise subsequent routes will be added to the wrong group.
     *
     * @return self Parent RouteGroup, or $this if no parent exists
     *
     * @example
     *     $router->group('/api')
     *         ->group('/users')
     *             ->get('/', [...])
     *         ->end()              // Returns to /api group
     *         ->get('/health', []) // This is now /api/health, not /api/users/health
     *     ->end();
     */
    public function end(): self
    {
        return $this->parent ?? $this;
    }

    /**
     * Register a GET route within this group.
     *
     * @param string $path Route path (appended to group prefix)
     * @param array<class-string|object> $pipes Pipes/handlers for this route (merged with group pipes)
     * @return self Fluent interface
     *
     * @example
     *     $group->get('/users', [ListUsersHandler::class]);
     *     $group->get('/users/{id}', [GetUserHandler::class]);
     */
    public function get(string $path, array $pipes): self
    {
        $this->router->get($this->joinPaths($this->prefix, $path), array_merge($this->pipes, $pipes));
        return $this;
    }

    /**
     * Register a POST route within this group.
     *
     * @param string $path Route path (appended to group prefix)
     * @param array<class-string|object> $pipes Pipes/handlers for this route (merged with group pipes)
     * @return self Fluent interface
     *
     * @example
     *     $group->post('/users', [ValidateUserPipe::class, CreateUserHandler::class]);
     */
    public function post(string $path, array $pipes): self
    {
        $this->router->post($this->joinPaths($this->prefix, $path), array_merge($this->pipes, $pipes));
        return $this;
    }

    /**
     * Register a PATCH route within this group.
     *
     * @param string $path Route path (appended to group prefix)
     * @param array<class-string|object> $pipes Pipes/handlers for this route (merged with group pipes)
     * @return self Fluent interface
     *
     * @example
     *     $group->patch('/users/{id}', [ValidateUserPipe::class, UpdateUserHandler::class]);
     */
    public function patch(string $path, array $pipes): self
    {
        $this->router->patch($this->joinPaths($this->prefix, $path), array_merge($this->pipes, $pipes));
        return $this;
    }

    /**
     * Register a DELETE route within this group.
     *
     * @param string $path Route path (appended to group prefix)
     * @param array<class-string|object> $pipes Pipes/handlers for this route (merged with group pipes)
     * @return self Fluent interface
     *
     * @example
     *     $group->delete('/users/{id}', [DeleteUserHandler::class]);
     */
    public function delete(string $path, array $pipes): self
    {
        $this->router->delete($this->joinPaths($this->prefix, $path), array_merge($this->pipes, $pipes));
        return $this;
    }

    /**
     * Join two path segments, handling slashes correctly.
     *
     * @param string $prefix First path segment
     * @param string $path Second path segment
     * @return string Combined path (e.g., '/api' + '/users' = '/api/users')
     */
    private function joinPaths(string $prefix, string $path): string
    {
        $prefix = $this->router->normalizePath($prefix);
        $path = $this->router->normalizePath($path);

        $prefix = $prefix === '/' ? '' : $prefix;
        $path = $path === '/' ? '' : $path;

        $joined = $prefix . $path;
        return $joined === '' ? '/' : $joined;
    }
}

