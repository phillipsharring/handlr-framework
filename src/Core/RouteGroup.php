<?php

declare(strict_types=1);

namespace Handlr\Core;

final class RouteGroup
{
    private string $prefix;
    private array $pipes;

    public function __construct(
        private readonly Router $router,
        string $prefix,
        array $pipes = []
    ) {
        $this->prefix = $this->router->normalizePath($prefix);
        $this->pipes = $pipes;
    }

    public function group(string $prefix, array $pipes = []): self
    {
        $prefix = $this->joinPaths($this->prefix, $prefix);
        $pipes = array_merge($this->pipes, $pipes);
        return new self($this->router, $prefix, $pipes);
    }

    public function get(string $path, array $pipes): self
    {
        $this->router->get($this->joinPaths($this->prefix, $path), array_merge($this->pipes, $pipes));
        return $this;
    }

    public function post(string $path, array $pipes): self
    {
        $this->router->post($this->joinPaths($this->prefix, $path), array_merge($this->pipes, $pipes));
        return $this;
    }

    public function patch(string $path, array $pipes): self
    {
        $this->router->patch($this->joinPaths($this->prefix, $path), array_merge($this->pipes, $pipes));
        return $this;
    }

    public function delete(string $path, array $pipes): self
    {
        $this->router->delete($this->joinPaths($this->prefix, $path), array_merge($this->pipes, $pipes));
        return $this;
    }

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

