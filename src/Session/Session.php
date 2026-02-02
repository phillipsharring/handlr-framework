<?php

declare(strict_types=1);

namespace Handlr\Session;

use SessionHandlerInterface;

class Session implements SessionInterface
{
    private bool $started = false;

    public function __construct(SessionHandlerInterface $handler)
    {
        session_set_save_handler($handler, true);
    }

    public function start(): void
    {
        if (!$this->started && session_status() === PHP_SESSION_NONE) {
            session_start();
            $this->started = true;
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public function destroy(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            session_destroy();
            $this->started = false;
        }
    }
}
