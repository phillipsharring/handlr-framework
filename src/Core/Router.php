<?php

declare(strict_types=1);

namespace Handlr\Core;

use Handlr\Core\Container\Container;
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

        if (!isset($this->routes[$method][$path])) {
            return $response->withStatus(Response::HTTP_NOT_FOUND)->withBody('404 File Not Found');
        }

        $pipeline = new Pipeline();

        foreach ($this->globalPipes as $pipe) {
            $pipeline->lay($pipe);
        }

        foreach ($this->routes[$method][$path] as $pipeClass) {
            $pipe = (is_string($pipeClass))
                ? $this->container->get($pipeClass)
                : $pipeClass;
            $pipeline->lay($pipe);
        }

        return $pipeline->run($request, $response, []);
    }

    private function add(string $method, string $path, array $pipes): void
    {
        $path = $this->normalizePath($path);
        $this->routes[$method][$path] = $pipes;
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
}
