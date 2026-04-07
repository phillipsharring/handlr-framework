<?php

declare(strict_types=1);

namespace Handlr\Core;

use Handlr\Core\Container\Container;
use Handlr\Core\Routes\Router;
use Handlr\Database\Db;
use Handlr\Log\Logger;
use Handlr\Mail\Mailer;
use Handlr\Pipes\ErrorPipe;
use Handlr\Pipes\LogPipe;
use Handlr\Session\DatabaseSessionDriver;
use Handlr\Session\Session;
use Handlr\Session\SessionInterface;

/**
 * Application kernel that bootstraps and wires together core services.
 *
 * Singleton that initializes the application by loading the bootstrap file,
 * registering core services (logger, database, session), adding global pipes,
 * and loading route definitions.
 *
 * @example
 *     $kernel = Kernel::getInstance($container, $router, '/var/www/app');
 *     $router = $kernel->getRouter();
 *     $container = Kernel::getContainer();
 */
final class Kernel
{
    /** @var Kernel|null Singleton instance */
    private static ?Kernel $instance = null;

    /** @var string Absolute path to the application root directory */
    private string $appRoot;

    /** @var Container Dependency injection container */
    private Container $container;

    /** @var Router HTTP router instance */
    private Router $router;

    /** Private constructor to enforce singleton pattern. */
    private function __construct() {}

    /**
     * Initialize the kernel with dependencies and boot the application.
     *
     * Loads bootstrap file, registers core services, adds global pipes,
     * and loads route definitions. Called automatically by getInstance().
     *
     * @param Container $container The DI container instance
     * @param Router $router The router instance
     * @param string $appRoot Absolute path to the application root
     */
    public function initialize(Container $container, Router $router, string $appRoot): void
    {
        $this->container = $container;
        $this->router = $router;
        $this->appRoot = $appRoot;

        $this->loadBootstrap();
        $this->registerServices();
        $this->registerGlobalPipes();

        // Bind the Router as a singleton so providers (and anything else) can
        // resolve it from the container if they need to.
        $this->container->singleton(Router::class, $this->router);

        // Order matters here:
        //   1. boot() lets providers do general runtime wiring.
        //   2. App routes load next so they can declare junctions that
        //      providers will fill in step 3.
        //   3. Provider routes() run last and may attach to any junction the
        //      app declared in step 2.
        $this->bootProviders();
        $this->loadRoutes();
        $this->applyProviderRoutes();
    }

    /**
     * Get or create the singleton kernel instance.
     *
     * On first call, creates and initializes the kernel. Subsequent calls
     * return the existing instance (parameters are ignored after first call).
     *
     * @param Container $container The DI container instance
     * @param Router $router The router instance
     * @param string $appRoot Absolute path to the application root
     * @return Kernel The singleton kernel instance
     */
    public static function getInstance(Container $container, Router $router, string $appRoot): Kernel
    {
        if (self::$instance === null) {
            self::$instance = new self();
            self::$instance->initialize($container, $router, $appRoot);
        }

        return self::$instance;
    }

    /**
     * Get the DI container from the kernel singleton.
     *
     * @return Container The container instance
     * @throws \RuntimeException If the kernel has not been initialized
     */
    public static function getContainer(): Container
    {
        if (self::$instance === null) {
            throw new \RuntimeException('Kernel has not been initialized yet.');
        }

        return self::$instance->container;
    }

    /**
     * Get the router instance.
     *
     * @return Router The router instance
     */
    public function getRouter(): Router
    {
        return $this->router;
    }

    /**
     * Register all core services with the container.
     */
    private function registerServices(): void
    {
        $this->registerLogger();
        $this->registerDatabase();
        $this->registerSession();
        $this->registerMailer();
    }

    /**
     * Register the Logger singleton.
     *
     * Uses LOG_FILE env var if set, otherwise defaults to {appRoot}/logs/app.log.
     */
    private function registerLogger(): void
    {
        $logFile = ($_ENV['LOG_FILE'] ?? null) ?: $this->appRoot . '/logs/app.log';
        $this->container->singleton(Logger::class, new Logger($logFile));
    }

    /**
     * Register the database binding for lazy resolution.
     */
    private function registerDatabase(): void
    {
        // Resolve lazily; the container can construct it when first requested.
        $this->container->bind(Db::class, Db::class);
    }

    /**
     * Register the session singleton with database-backed storage.
     */
    private function registerSession(): void
    {
        $db = $this->container->singleton(Db::class);
        $sessionHandler = new DatabaseSessionDriver($db);
        $session = new Session($sessionHandler);
        $this->container->singleton(SessionInterface::class, $session);
    }

    /**
     * Register the Mailer singleton (lazy — resolved on first use).
     */
    private function registerMailer(): void
    {
        $this->container->bind(Mailer::class, Mailer::class);
    }

    /**
     * Add global pipes (ErrorPipe, LogPipe) to the router.
     */
    private function registerGlobalPipes(): void
    {
        $this->router->addGlobalPipe($this->container->get(ErrorPipe::class));
        $this->router->addGlobalPipe($this->container->get(LogPipe::class));
    }

    /**
     * Load the application bootstrap file.
     */
    private function loadBootstrap(): void
    {
        require_once $this->appRoot . '/bootstrap.php'; // NOSONAR
    }

    /**
     * Run the providers' boot() phase.
     *
     * The app's `handlr_app()` is responsible for building the
     * `ServiceProviderRegistry` and calling `registerAll()` /
     * `applyEvents()` / `applyConfigDefaults()`. The Kernel handles the
     * runtime side: `bootAll()` first, then `applyRoutes()` AFTER the app's
     * own routes file has loaded so junctions exist for providers to fill.
     *
     * If the app hasn't bound a registry (older apps, or apps with no
     * providers), both phases are no-ops.
     */
    private function bootProviders(): void
    {
        if (!$this->container->has(ServiceProviderRegistry::class)) {
            return;
        }

        /** @var ServiceProviderRegistry $registry */
        $registry = $this->container->get(ServiceProviderRegistry::class);
        $registry->bootAll();
    }

    /**
     * Let providers register their routes, AFTER the app's `routes.php` has
     * declared any junctions they're meant to fill.
     */
    private function applyProviderRoutes(): void
    {
        if (!$this->container->has(ServiceProviderRegistry::class)) {
            return;
        }

        /** @var ServiceProviderRegistry $registry */
        $registry = $this->container->get(ServiceProviderRegistry::class);
        $registry->applyRoutes($this->router);
    }

    /**
     * Load the application route definitions.
     */
    private function loadRoutes(): void
    {
        $router = $this->router; // NOSONAR
        require_once $this->appRoot . '/app/routes.php'; // NOSONAR
    }
}
