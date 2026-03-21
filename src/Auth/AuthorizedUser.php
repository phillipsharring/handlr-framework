<?php

declare(strict_types=1);

namespace Handlr\Auth;

class AuthorizedUser implements AuthSubject
{
    public function __construct(
        private readonly string $id,
        private readonly array $roles,
        private readonly array $permissions
    ) {}

    public function id(): string
    {
        return $this->id;
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }

    public function getPermissions(): array
    {
        return $this->permissions;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }
}
