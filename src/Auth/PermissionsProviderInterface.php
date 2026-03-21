<?php

declare(strict_types=1);

namespace Handlr\Auth;

/**
 * Provides role and permission data for a user.
 *
 * Apps implement this interface to bridge their user/role/permission
 * schema with the framework's AuthorizationService.
 */
interface PermissionsProviderInterface
{
    /**
     * Get role names for a user.
     *
     * @return string[]
     */
    public function getRolesForUser(string $userId): array;

    /**
     * Get distinct permission names for a user (via roles).
     *
     * @return string[]
     */
    public function getPermissionsForUser(string $userId): array;
}
