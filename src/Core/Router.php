<?php

declare(strict_types=1);

namespace Handlr\Core;

use Handlr\Core\Container\Container;
use Handlr\Handlers\Handler;

class Router
{
    private array $globalHandlers = [];
    private array $routes = [];
    public function __construct(private readonly Container $container) {}

    public function addGlobalHandler(Handler $handler): void
    {
        $this->globalHandlers[] = $handler;
    }

    public function dispatch(Request $request, Response $response): Response
    {
        $method = $request->getMethod();
        $path = $request->getUri();

        if (!isset($this->routes[$method][$path])) {
            return $response->withStatus(Response::HTTP_NOT_FOUND)->withBody('404 File Not Found');
        }

        $pipeline = new Pipeline();

        foreach ($this->globalHandlers as $handler) {
            $pipeline->pipe($handler);
        }

        foreach ($this->routes[$method][$path] as $handlerClass) {
            $handler = (is_string($handlerClass))
                ? $this->container->get($handlerClass)
                : $handlerClass;
            $pipeline->pipe($handler);
        }

        return $pipeline->run($request, $response, []);
    }

    private function add(string $method, string $path, array $handlers): void
    {
        $this->routes[$method][$path] = $handlers;
    }

    public function get(string $path, array $handlers): void
    {
        $this->add('GET', $path, $handlers);
    }

    public function post(string $path, array $handlers): void
    {
        $this->add('POST', $path, $handlers);
    }

    public function patch(string $path, array $handlers): void
    {
        $this->add('PATCH', $path, $handlers);
    }

    public function delete(string $path, array $handlers): void
    {
        $this->add('DELETE', $path, $handlers);
    }
}
