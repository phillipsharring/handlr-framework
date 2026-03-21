<?php

declare(strict_types=1);

namespace Handlr\Auth;

interface AuthSubject
{
    public function id(): string;

    public function hasRole(string $role): bool;

    public function hasPermission(string $permission): bool;
}
