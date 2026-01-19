<?php

declare(strict_types=1);

namespace Handlr\Core\Routes;

use Handlr\Core\Container\Container;
use Handlr\Core\Request;
use Handlr\Core\Response;
use Handlr\Pipes\Pipe;

class Router
{
    private array $globalPipes = [];
    private array $routes = [];
    public function __construct(private readonly Container $container) {}

    public function addGlobalPipe(Pipe $pipe): void
    {
        $this->globalPipes[] = $pipe;
    }

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

    public function get(string $path, array $pipes): self
    {
        $this->add('GET', $path, $pipes);
        return $this;
    }

    public function post(string $path, array $pipes): self
    {
        $this->add('POST', $path, $pipes);
        return $this;
    }

    public function patch(string $path, array $pipes): self
    {
        $this->add('PATCH', $path, $pipes);
        return $this;
    }

    public function delete(string $path, array $pipes): self
    {
        $this->add('DELETE', $path, $pipes);
        return $this;
    }

    /**
     * Create a grouped router context that applies a URL prefix and optional pipes
     * to all routes registered through it.
     *
     * Example:
     * $router->group('/api', [AuthPipe::class])
     *   ->get('/echo', [EchoPipe::class]);
     *   results in /api/echo which has AuthPipe
     *   and EchoPipe applied
     */
    public function group(string $prefix, array $pipes = []): RouteGroup
    {
        return new RouteGroup($this, $prefix, $pipes);
    }

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
     * Supports patterns like: /api/series/{id:uuid} or /api/series/{id:\d+}
     *
     * Type aliases:
     * - {id:int} -> matches integers (\d+)
     * - {id:uuid} -> matches UUIDs ([0-9a-f-]{36})
     * - {slug:slug} -> matches slugs ([a-z0-9-]+)
     * - {id} -> matches anything except / ([^/]+)
     * - {id:custom} -> custom regex passed through as-is
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
     * Map type aliases to regex patterns.
     * Returns the regex pattern for a given type, or the type itself if no alias exists.
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
