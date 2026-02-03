<?php

declare(strict_types=1);

namespace Handlr\Core\Routes;

use Handlr\Core\Response;
use Handlr\Pipes\Pipe;

/**
 * Middleware pipeline that executes pipes in sequence (onion pattern).
 *
 * Pipes are executed in the order they are added. Each pipe can:
 * - Modify the request/response before passing to the next pipe
 * - Short-circuit by returning a response without calling $next
 * - Modify the response after the inner pipes complete
 *
 * Execution flows like an onion: first pipe's "before" logic runs first,
 * but its "after" logic runs last.
 *
 * @example Building a pipeline:
 *     $pipeline = new Pipeline();
 *     $pipeline
 *         ->lay(new AuthPipe())      // Runs 1st on way in, last on way out
 *         ->lay(new LogPipe())       // Runs 2nd on way in, 2nd-to-last out
 *         ->lay(new ValidationPipe()); // Runs 3rd on way in, first on way out
 *
 * @example Execution order for 3 pipes (A, B, C):
 *     A before → B before → C before → [handler] → C after → B after → A after
 */
class Pipeline
{
    /** @var Pipe[] Pipes to execute in order */
    private array $pipes = [];

    /**
     * Add a pipe to the pipeline.
     *
     * Pipes are executed in the order they are added (first in, first to run).
     * Returns $this for method chaining.
     *
     * @param Pipe $pipe The pipe (middleware) to add
     * @return self Fluent interface
     *
     * @example Chain multiple pipes:
     *     $pipeline
     *         ->lay(new AuthPipe())
     *         ->lay(new RateLimitPipe())
     *         ->lay(new CachePipe());
     */
    public function lay(Pipe $pipe): self
    {
        $this->pipes[] = $pipe;
        return $this;
    }

    /**
     * Execute all pipes in the pipeline.
     *
     * Builds a nested closure chain and executes it. If no pipes are added,
     * simply returns the response unchanged.
     *
     * @param mixed $request The HTTP request object
     * @param Response $response The initial response object
     * @param array<string, mixed> $args Route arguments and other context
     * @return Response The final response after all pipes have executed
     *
     * @example
     *     $response = $pipeline->run($request, new Response(), ['id' => '123']);
     */
    public function run($request, $response, $args): Response
    {
        $next = static fn($req, $res, $args) => $res;

        foreach (array_reverse($this->pipes) as $pipe) {
            $next = static fn($req, $res, $args) => $pipe->handle($req, $res, $args, $next);
        }

        return $next($request, $response, $args);
    }
}
