<?php

declare(strict_types=1);

namespace Handlr\Core\Routes;

use Handlr\Core\Container\Container;
use Handlr\Core\Request;
use Handlr\Core\Response;
use Handlr\Pipes\Pipe;

/**
 * HTTP router for registering routes and dispatching requests.
 *
 * Routes are defined with an HTTP method, URL pattern, and array of pipes.
 * The last pipe in the array is typically the handler; earlier pipes are middleware.
 *
 * DEFINING ROUTES (in routes.php):
 *
 * @example Simple routes:
 *     $router->get('/health', [HealthCheckHandler::class]);
 *     $router->post('/users', [CreateUserHandler::class]);
 *
 * @example Route with middleware pipe(s):
 *     $router->post('/users', [
 *         ValidateUserPipe::class,    // Middleware - runs first
 *         CreateUserHandler::class,   // Handler - runs last
 *     ]);
 *
 * @example Route with URL parameters:
 *     $router->get('/users/{id}', [GetUserHandler::class]);
 *     // Access in handler: $request->getRouteParam('id')
 *
 * ROUTE PARAMETERS - Use {name} or {name:type} syntax:
 *
 * @example Parameter types:
 *     '/users/{id}'           // Any non-slash characters
 *     '/users/{id:int}'       // Integers only: 123
 *     '/users/{id:uuid}'      // UUIDs: 550e8400-e29b-41d4-a716-446655440000
 *     '/posts/{slug:slug}'    // Slugs: my-blog-post
 *     '/files/{path:[a-z/]+}' // Custom regex
 *
 * ROUTE GROUPS - Share prefix and pipes across routes:
 *
 * @example Grouped routes:
 *     $router->group('/api/v1', [AuthPipe::class])
 *         ->get('/users', [ListUsersHandler::class])      // GET /api/v1/users
 *         ->get('/users/{id}', [GetUserHandler::class])   // GET /api/v1/users/{id}
 *         ->post('/users', [CreateUserHandler::class])    // POST /api/v1/users
 *     ->end();
 *
 * @see RouteGroup For nested group documentation
 */
class Router
{
    /** @var Pipe[] Global pipes applied to ALL routes (e.g., ErrorPipe, LogPipe) */
    private array $globalPipes = [];

    /** @var array<string, array> Routes indexed by HTTP method */
    private array $routes = [];

    /**
     * @param Container $container DI container for resolving pipe classes
     */
    public function __construct(private readonly Container $container) {}

    /**
     * Add a global pipe that runs on every request.
     *
     * Global pipes run before route-specific pipes. Typically used for
     * error handling, logging, CORS, etc. Usually called by Kernel, not app code.
     *
     * @param Pipe $pipe Pipe instance to add globally
     *
     * @example In Kernel:
     *     $router->addGlobalPipe($container->get(ErrorPipe::class));
     *     $router->addGlobalPipe($container->get(LogPipe::class));
     */
    public function addGlobalPipe(Pipe $pipe): void
    {
        $this->globalPipes[] = $pipe;
    }

    /**
     * Dispatch a request to the matching route.
     *
     * Finds the route matching the request method and path, extracts URL
     * parameters, builds the pipeline (global pipes + route pipes), and
     * executes it. Returns 404 if no route matches.
     *
     * Typically called by the front controller, not directly by app code.
     *
     * @param Request $request The incoming HTTP request
     * @param Response $response Initial response object
     * @return Response The final response after pipeline execution
     *
     * @example In index.php:
     *     $response = $router->dispatch(Request::fromGlobals(), new Response());
     *     $response->send();
     */
    public function dispatch(Request $request, Response $response): Response
    {
        $method = $request->getMethod();
        $rawUri = $request->getUri();
        $path = parse_url($rawUri, PHP_URL_PATH) ?: '/';
        $path = $this->normalizePath($path);

        // Find matching route
        $matchedRoute = null;
        $params = [];

        if (isset($this->routes[$method])) {
            foreach ($this->routes[$method] as $route) {
                if (preg_match($route['regex'], $path, $matches)) {
                    $matchedRoute = $route;
                    // Extract named parameters
                    foreach ($route['params'] as $paramName) {
                        if (isset($matches[$paramName])) {
                            $params[$paramName] = $matches[$paramName];
                        }
                    }
                    break;
                }
            }
        }

        if ($matchedRoute === null) {
            return $response->withStatus(Response::HTTP_NOT_FOUND)->withJson(['message' => '404 File Not Found']);
        }

        // Set route params on request for easy access
        $request->setRouteParams($params);

        $pipeline = new Pipeline();

        foreach ($this->globalPipes as $pipe) {
            $pipeline->lay($pipe);
        }

        foreach ($matchedRoute['pipes'] as $pipeClass) {
            $pipe = (is_string($pipeClass))
                ? $this->container->get($pipeClass)
                : $pipeClass;
            $pipeline->lay($pipe);
        }

        return $pipeline->run($request, $response, $params);
    }

    /**
     * Register a route for a given HTTP method.
     *
     * @param string $method HTTP method (GET, POST, PATCH, DELETE)
     * @param string $path URL pattern with optional parameters
     * @param array<class-string|object> $pipes Pipes to execute for this route
     */
    private function add(string $method, string $path, array $pipes): void
    {
        $path = $this->normalizePath($path);
        $compiled = $this->compilePattern($path);

        if (!isset($this->routes[$method])) {
            $this->routes[$method] = [];
        }

        $this->routes[$method][] = [
            'pattern' => $path,
            'regex' => $compiled['regex'],
            'params' => $compiled['params'],
            'pipes' => $pipes,
        ];
    }

    /**
     * Register a GET route.
     *
     * Use for retrieving resources (read-only, idempotent).
     *
     * @param string $path URL pattern (e.g., '/users', '/users/{id}')
     * @param array<class-string|object> $pipes Middleware and handler pipes
     * @return self Fluent interface
     *
     * @example List resources:
     *     $router->get('/users', [ListUsersHandler::class]);
     *
     * @example Get single resource:
     *     $router->get('/users/{id:uuid}', [GetUserHandler::class]);
     *
     * @example With middleware:
     *     $router->get('/admin/users', [AdminAuthPipe::class, ListUsersHandler::class]);
     */
    public function get(string $path, array $pipes): self
    {
        $this->add('GET', $path, $pipes);
        return $this;
    }

    /**
     * Register a POST route.
     *
     * Use for creating new resources.
     *
     * @param string $path URL pattern (e.g., '/users')
     * @param array<class-string|object> $pipes Middleware and handler pipes
     * @return self Fluent interface
     *
     * @example Create resource:
     *     $router->post('/users', [ValidateUserPipe::class, CreateUserHandler::class]);
     *
     * @example Action endpoint:
     *     $router->post('/auth/login', [LoginHandler::class]);
     */
    public function post(string $path, array $pipes): self
    {
        $this->add('POST', $path, $pipes);
        return $this;
    }

    /**
     * Register a PATCH route.
     *
     * Use for partial updates to existing resources.
     *
     * @param string $path URL pattern (e.g., '/users/{id}')
     * @param array<class-string|object> $pipes Middleware and handler pipes
     * @return self Fluent interface
     *
     * @example Update resource:
     *     $router->patch('/users/{id:uuid}', [ValidateUserPipe::class, UpdateUserHandler::class]);
     */
    public function patch(string $path, array $pipes): self
    {
        $this->add('PATCH', $path, $pipes);
        return $this;
    }

    /**
     * Register a DELETE route.
     *
     * Use for deleting resources.
     *
     * @param string $path URL pattern (e.g., '/users/{id}')
     * @param array<class-string|object> $pipes Middleware and handler pipes
     * @return self Fluent interface
     *
     * @example Delete resource:
     *     $router->delete('/users/{id:uuid}', [DeleteUserHandler::class]);
     */
    public function delete(string $path, array $pipes): self
    {
        $this->add('DELETE', $path, $pipes);
        return $this;
    }

    /**
     * Create a route group with shared prefix and pipes.
     *
     * Groups allow you to apply a common URL prefix and middleware to
     * multiple routes. Groups can be nested. Always call end() when done.
     *
     * @param string $prefix URL prefix for all routes in the group
     * @param array<class-string|object> $pipes Pipes applied to all routes in group
     * @return RouteGroup The new route group
     *
     * @example API group with auth:
     *     $router->group('/api', [AuthPipe::class])
     *         ->get('/users', [ListUsersHandler::class])
     *         ->post('/users', [CreateUserHandler::class])
     *     ->end();
     *
     * @example Nested groups:
     *     $router->group('/api')
     *         ->group('/v1')
     *             ->get('/users', [V1ListUsersHandler::class])
     *         ->end()
     *         ->group('/v2')
     *             ->get('/users', [V2ListUsersHandler::class])
     *         ->end()
     *     ->end();
     *
     * @see RouteGroup For full nesting documentation
     */
    public function group(string $prefix, array $pipes = []): RouteGroup
    {
        return new RouteGroup($this, $prefix, $pipes);
    }

    /**
     * Normalize a URL path for consistent matching.
     *
     * Ensures path starts with /, removes trailing slashes (except for root),
     * and collapses multiple slashes.
     *
     * @param string $path The path to normalize
     * @return string Normalized path (e.g., '/api/users')
     */
    public function normalizePath(string $path): string
    {
        $path = trim($path);
        if ($path === '') {
            return '/';
        }

        $parsed = parse_url($path, PHP_URL_PATH);
        if (is_string($parsed) && $parsed !== '') {
            $path = $parsed;
        }

        if ($path === '') {
            return '/';
        }

        if ($path[0] !== '/') {
            $path = '/' . $path;
        }

        $path = preg_replace('#/+#', '/', $path) ?? $path;

        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        return $path === '' ? '/' : $path;
    }

    /**
     * Compile a route pattern into a regex and extract parameter names.
     *
     * Supports patterns like: /api/series/{id:uuid} or /api/series/{id:\d+}
     *
     * PARAMETER TYPE ALIASES:
     * - {id}        -> matches anything except / ([^/]+)
     * - {id:int}    -> matches integers (\d+)
     * - {id:uuid}   -> matches UUIDs ([0-9a-f-]{36})
     * - {slug:slug} -> matches slugs ([a-z0-9-]+)
     * - {id:custom} -> custom regex passed through as-is
     *
     * @param string $pattern Route pattern with {param} placeholders
     * @return array{regex: string, params: string[]} Compiled regex and param names
     */
    private function compilePattern(string $pattern): array
    {
        $params = [];

        // Replace {param} or {param:type} with named capture groups
        $regex = preg_replace_callback(
            '/\{(\w+)(?::([^{}]+(?:\{[^}]*\})*))?\}/',
            function ($matches) use (&$params) {
                $paramName = $matches[1];
                $paramType = $matches[2] ?? null;
                $paramPattern = $this->getTypePattern($paramType);
                $params[] = $paramName;
                return "(?<{$paramName}>{$paramPattern})";
            },
            $pattern
        );

        // Escape forward slashes and make it an exact match
        $regex = '#^' . str_replace('/', '\/', $regex) . '$#';

        return [
            'regex' => $regex,
            'params' => $params,
        ];
    }

    /**
     * Map parameter type aliases to regex patterns.
     *
     * @param string|null $type Type alias (int, uuid, slug) or custom regex
     * @return string Regex pattern for the type
     */
    private function getTypePattern(?string $type): string
    {
        if ($type === null) {
            return '[^/]+'; // Default: match anything except /
        }

        return match($type) {
            'int' => '\d+',
            'uuid' => '[0-9a-f-]{36}',
            'slug' => '[a-z0-9-]+',
            default => $type  // Pass through custom regex as-is
        };
    }
}
