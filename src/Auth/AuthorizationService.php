<?php

declare(strict_types=1);

namespace Handlr\Auth;

class AuthorizationService
{
    private ?AuthSubject $cachedSubject = null;

    public function __construct(
        private readonly AuthContext $context,
        private readonly PermissionsProviderInterface $permissionsProvider,
    ) {}

    public function subject(): ?AuthSubject
    {
        if (!$this->context->isAuthenticated()) {
            return null;
        }

        if ($this->cachedSubject !== null) {
            return $this->cachedSubject;
        }

        $userId = $this->context->getUserId();
        $roles = $this->permissionsProvider->getRolesForUser($userId);
        $permissions = $this->permissionsProvider->getPermissionsForUser($userId);

        $this->cachedSubject = new AuthorizedUser($userId, $roles, $permissions);

        return $this->cachedSubject;
    }

    /**
     * @throws UnauthorizedException
     */
    public function require(): AuthSubject
    {
        $subject = $this->subject();

        if ($subject === null) {
            throw new UnauthorizedException();
        }

        return $subject;
    }
}
