<?php

declare(strict_types=1);

namespace Handlr\Core;

use Handlr\Core\Container\ContainerInterface;
use Handlr\Core\Routes\Router;

/**
 * Base class for service providers.
 *
 * A service provider is the unit of self-registration in Handlr. It declares
 * what container bindings, routes, event listeners, migrations, seeds, and
 * config defaults a chunk of the application owns. Apps list providers in
 * `app/config.php` under `app.providers`; the framework instantiates them and
 * runs them through a two-phase lifecycle.
 *
 * ## Lifecycle
 *
 * 1. **Construction** — provider receives the DI container.
 * 2. **`register()`** — bind services into the container. Runs for ALL providers
 *    before any `boot()` runs, so providers can depend on each other's bindings
 *    without ordering games. Do NOT resolve services here, do NOT touch the DB,
 *    do NOT do I/O.
 * 3. **`boot()`** — everything is registered. Safe to resolve services and do
 *    runtime wiring beyond what `routes()` / `events()` cover.
 * 4. **`routes(Router $router)`** — declarative route registration.
 * 5. **`events()`** — declarative event listener registration. Returns a map
 *    of `event.name => [ListenerClass::class, ...]`. The Kernel resolves
 *    listeners through the container and registers them with EventManager.
 *
 * Static metadata methods (`migrationPaths()`, `seedPaths()`, `configDefaults()`)
 * may be called at any time after construction without going through the
 * lifecycle — they must not depend on bindings or runtime state.
 *
 * @example A minimal provider
 *     class BlogServiceProvider extends ServiceProvider
 *     {
 *         public function register(): void
 *         {
 *             $this->container->bind(BlogRepository::class, MysqlBlogRepository::class);
 *         }
 *
 *         public function routes(Router $router): void
 *         {
 *             $router->group('/api/blog')
 *                 ->get('/posts', [ListPosts::class])
 *                 ->post('/posts', [CreatePost::class])
 *             ->end();
 *         }
 *
 *         public function events(): array
 *         {
 *             return [
 *                 'blog.post.published' => [SendNewPostNotificationListener::class],
 *             ];
 *         }
 *
 *         public function migrationPaths(): array
 *         {
 *             return [__DIR__ . '/Migrations'];
 *         }
 *
 *         public function configDefaults(): array
 *         {
 *             return ['blog' => ['posts_per_page' => 10]];
 *         }
 *     }
 */
abstract class ServiceProvider
{
    public function __construct(protected ContainerInterface $container) {}

    /**
     * Phase 1: bind services into the container.
     *
     * Runs for every provider before any `boot()` is called. Do not resolve
     * services, do not touch the database, do not do I/O.
     */
    public function register(): void
    {
        // No-op by default.
    }

    /**
     * Phase 2: runtime wiring after all providers have registered.
     *
     * Safe to resolve container services here. Use this for any wiring that
     * doesn't fit into the declarative `routes()` / `events()` methods.
     */
    public function boot(): void
    {
        // No-op by default.
    }

    /**
     * Declare routes owned by this provider.
     *
     * Called by the Kernel after global pipes are installed. Receives the
     * application Router; register routes/groups normally.
     */
    public function routes(Router $router): void
    {
        // No-op by default.
    }

    /**
     * Declare event listeners owned by this provider.
     *
     * Returns a map of event name to a list of Listener (Handler) class names.
     * The Kernel resolves each listener through the container and registers
     * it with EventManager.
     *
     * @return array<string, array<int, class-string>>
     */
    public function events(): array
    {
        return [];
    }

    /**
     * Filesystem paths the migration runner should also scan.
     *
     * Each path must contain migration files following the framework naming
     * convention (`{timestamp}_{description}.php`).
     *
     * @return array<int, string>
     */
    public function migrationPaths(): array
    {
        return [];
    }

    /**
     * Filesystem paths the seeder should also scan.
     *
     * @return array<int, string>
     */
    public function seedPaths(): array
    {
        return [];
    }

    /**
     * Default config values to merge underneath the app config.
     *
     * Defaults are filled in for keys the app config does not set; the app
     * config always wins on conflict. Typically scoped under a namespace key:
     *
     *     return ['blog' => ['posts_per_page' => 10]];
     *
     * @return array<string, mixed>
     */
    public function configDefaults(): array
    {
        return [];
    }
}
