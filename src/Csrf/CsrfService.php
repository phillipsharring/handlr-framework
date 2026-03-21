<?php

namespace Handlr\Csrf;

use Handlr\Session\SessionInterface;

class CsrfService
{
    private const SESSION_KEY = 'csrf_token';

    public function __construct(private readonly SessionInterface $session)
    {
    }

    public function ensureToken(): string
    {
        $token = $this->session->get(self::SESSION_KEY);

        if ($token === null) {
            $token = bin2hex(random_bytes(32));
            $this->session->set(self::SESSION_KEY, $token);
        }

        return $token;
    }

    public function getToken(): ?string
    {
        return $this->session->get(self::SESSION_KEY);
    }

    public function validateToken(?string $token): bool
    {
        $stored = $this->getToken();

        if ($stored === null || $token === null) {
            return false;
        }

        return hash_equals($stored, $token);
    }

    public function rotateToken(): string
    {
        $token = bin2hex(random_bytes(32));
        $this->session->set(self::SESSION_KEY, $token);

        return $token;
    }
}
