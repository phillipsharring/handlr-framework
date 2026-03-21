<?php

declare(strict_types=1);

namespace Handlr\Auth\Pipes;

use Handlr\Auth\AuthorizationService;
use Handlr\Core\Kernel;
use Handlr\Core\Request;
use Handlr\Core\Response;
use Handlr\Pipes\Pipe;

/**
 * Requires ANY one of the listed permissions. Denies if none match.
 *
 *     ->group('/admin', [new RequirePermissionPipe(['design.access', 'story.access'])])
 *     ->group('/design', [new RequirePermissionPipe('design.access')])
 *
 * Must run AFTER SessionAuthPipe (needs AuthContext populated).
 */
class RequirePermissionPipe implements Pipe
{
    /** @var string[] */
    private readonly array $permissions;

    private ?AuthorizationService $authService = null;

    public function __construct(string|array $permissions, ?AuthorizationService $authService = null)
    {
        $this->setAuthorizationService($authService);

        $this->permissions = is_array($permissions) ? $permissions : [$permissions];
    }

    public function handle(Request $request, Response $response, array $args, callable $next): Response
    {
        /** @var AuthorizationService $authService */
        $authService = $this->getAuthorizationService();
        $subject = $authService->subject();

        if ($subject === null) {
            return $response->withStatus(Response::HTTP_UNAUTHORIZED)
                ->withJson(['error' => 'Unauthorized']);
        }

        if (!array_any($this->permissions, static fn(string $permission) => $subject->hasPermission($permission))) {
            return $response->withStatus(Response::HTTP_FORBIDDEN)
                ->withJson(['error' => 'Forbidden']);
        }

        return $next($request, $response, $args);
    }

    public function setAuthorizationService(?AuthorizationService $authService): void
    {
        $this->authService = $authService;
    }

    public function getAuthorizationService(): ?AuthorizationService
    {
        if ($this->authService === null) {
            $this->authService = Kernel::getContainer()->get(AuthorizationService::class);
        }

        return $this->authService;
    }
}
