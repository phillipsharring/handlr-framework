<?php

declare(strict_types=1);

namespace Handlr\Auth;

class AuthContext
{
    private ?string $userId = null;

    public function setUserId(string $userId): void
    {
        $this->userId = $userId;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function isAuthenticated(): bool
    {
        return $this->userId !== null;
    }
}
