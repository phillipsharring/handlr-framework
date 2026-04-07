<?php

declare(strict_types=1);

namespace Handlr\Core;

use Handlr\Config\Config;
use Handlr\Core\Container\ContainerInterface;
use Handlr\Core\Routes\Router;
use InvalidArgumentException;
use RuntimeException;

/**
 * Holds the application's ServiceProvider instances and drives their lifecycle.
 *
 * Built once during bootstrap from a flat list of provider class names. Both
 * the web Kernel and CLI scripts (migrate, seed) read from the same registry,
 * so a provider that adds migrations is automatically picked up by `migrate.php`
 * without any manual wiring.
 *
 * ## Typical use
 *
 * ```php
 * $registry = new ServiceProviderRegistry($container);
 * $registry->addMany($config->get('app.providers', []));
 *
 * // Phase 0: defaults are merged underneath app config (app values win).
 * $registry->applyConfigDefaults($config);
 *
 * // Phase 1: container bindings.
 * $registry->registerAll();
 *
 * // ... (Db singleton finalized, EventManager available, etc.)
 *
 * // Phase 2: runtime wiring.
 * $registry->bootAll();
 * $registry->applyEvents($eventManager);
 * $registry->applyRoutes($router);   // typically called by Kernel
 * ```
 *
 * The CLI scripts skip `bootAll()` / `applyRoutes()` and only need the
 * `migrationPaths()` / `seedPaths()` aggregators.
 */
final class ServiceProviderRegistry
{
    /** @var ServiceProvider[] */
    private array $providers = [];

    private bool $registered = false;

    private bool $booted = false;

    public function __construct(private readonly ContainerInterface $container) {}

    /**
     * Add a single provider by class name. Instantiated immediately.
     *
     * @param class-string<ServiceProvider> $providerClass
     */
    public function add(string $providerClass): void
    {
        if (!class_exists($providerClass)) {
            throw new InvalidArgumentException("Service provider class does not exist: {$providerClass}");
        }

        if (!is_subclass_of($providerClass, ServiceProvider::class)) {
            throw new InvalidArgumentException(
                "Class {$providerClass} must extend " . ServiceProvider::class
            );
        }

        $this->providers[] = new $providerClass($this->container);
    }

    /**
     * Add many providers at once.
     *
     * @param array<int, class-string<ServiceProvider>> $providerClasses
     */
    public function addMany(array $providerClasses): void
    {
        foreach ($providerClasses as $providerClass) {
            $this->add($providerClass);
        }
    }

    /**
     * Run `register()` on every provider. Idempotent.
     */
    public function registerAll(): void
    {
        if ($this->registered) {
            return;
        }

        foreach ($this->providers as $provider) {
            $provider->register();
        }

        $this->registered = true;
    }

    /**
     * Run `boot()` on every provider. Idempotent.
     *
     * Throws if called before `registerAll()` — boot() may resolve services
     * other providers bound during register().
     */
    public function bootAll(): void
    {
        if ($this->booted) {
            return;
        }

        if (!$this->registered) {
            throw new RuntimeException('ServiceProviderRegistry::bootAll() called before registerAll().');
        }

        foreach ($this->providers as $provider) {
            $provider->boot();
        }

        $this->booted = true;
    }

    /**
     * Merge each provider's `configDefaults()` underneath the live config.
     *
     * App config values always win on conflict; defaults only fill gaps.
     */
    public function applyConfigDefaults(Config $config): void
    {
        foreach ($this->providers as $provider) {
            $defaults = $provider->configDefaults();
            if ($defaults === []) {
                continue;
            }
            $this->mergeDefaultsUnder($config, $defaults);
        }
    }

    /**
     * Resolve and register every provider's event listeners with EventManager.
     */
    public function applyEvents(EventManager $eventManager): void
    {
        foreach ($this->providers as $provider) {
            foreach ($provider->events() as $eventName => $listenerClasses) {
                foreach ($listenerClasses as $listenerClass) {
                    $eventManager->register($eventName, $this->container->get($listenerClass));
                }
            }
        }
    }

    /**
     * Call `routes()` on every provider with the given Router.
     *
     * Each provider's class name is pushed onto the Router's origin stack
     * before its `routes()` runs and popped after, so any route the provider
     * registers is attributed to it for conflict reporting.
     */
    public function applyRoutes(Router $router): void
    {
        foreach ($this->providers as $provider) {
            $router->pushOrigin($provider::class);
            try {
                $provider->routes($router);
            } finally {
                $router->popOrigin();
            }
        }
    }

    /**
     * Aggregate all migration paths declared by every provider.
     *
     * @return array<int, string>
     */
    public function migrationPaths(): array
    {
        $paths = [];
        foreach ($this->providers as $provider) {
            foreach ($provider->migrationPaths() as $path) {
                $paths[] = $path;
            }
        }
        return $paths;
    }

    /**
     * Aggregate all seed paths declared by every provider.
     *
     * @return array<int, string>
     */
    public function seedPaths(): array
    {
        $paths = [];
        foreach ($this->providers as $provider) {
            foreach ($provider->seedPaths() as $path) {
                $paths[] = $path;
            }
        }
        return $paths;
    }

    /**
     * @return ServiceProvider[]
     */
    public function all(): array
    {
        return $this->providers;
    }

    /**
     * Recursively merge $defaults underneath the current Config values.
     * Existing config values always take precedence.
     */
    private function mergeDefaultsUnder(Config $config, array $defaults, string $prefix = ''): void
    {
        foreach ($defaults as $key => $value) {
            $fullKey = $prefix === '' ? (string)$key : "{$prefix}.{$key}";
            $existing = $config->get($fullKey);

            if ($existing === null) {
                $config->dot->set($fullKey, $value);
                continue;
            }

            if (is_array($value) && is_array($existing)) {
                $this->mergeDefaultsUnder($config, $value, $fullKey);
            }
            // Otherwise: existing scalar/array wins, leave alone.
        }
    }
}
