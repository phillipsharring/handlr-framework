<?php

declare(strict_types=1);

namespace Handlr\Core;

use Handlr\Core\Container\Container;
use Handlr\Core\Routes\Router;
use Handlr\Database\Db;
use Handlr\Log\Logger;
use Handlr\Pipes\ErrorPipe;
use Handlr\Pipes\LogPipe;
use Handlr\Session\DatabaseSessionDriver;
use Handlr\Session\Session;
use Handlr\Session\SessionInterface;

final class Kernel
{
    private static ?Kernel $instance = null;
    private string $appRoot;
    private Container $container;
    private Router $router;

    private function __construct() {}

    public function initialize(Container $container, Router $router, string $appRoot): void
    {
        $this->container = $container;
        $this->router = $router;
        $this->appRoot = $appRoot;

        $this->loadBootstrap();
        $this->registerServices();
        $this->registerGlobalPipes();

        $this->loadRoutes();
    }

    public static function getInstance(Container $container, Router $router, string $appRoot): Kernel
    {
        if (self::$instance === null) {
            self::$instance = new self();
            self::$instance->initialize($container, $router, $appRoot);
        }

        return self::$instance;
    }

    public static function getContainer(): Container
    {
        if (self::$instance === null) {
            throw new \RuntimeException('Kernel has not been initialized yet.');
        }

        return self::$instance->container;
    }

    public function getRouter(): Router
    {
        return $this->router;
    }

    private function registerServices(): void
    {
        $this->registerLogger();
        $this->registerDatabase();
        $this->registerSession();
    }

    private function registerLogger(): void
    {
        $logFile = ($_ENV['LOG_FILE'] ?? null) ?: $this->appRoot . '/logs/app.log';
        $this->container->singleton(Logger::class, new Logger($logFile));
    }

    private function registerDatabase(): void
    {
        // Resolve lazily; the container can construct it when first requested.
        $this->container->bind(Db::class, Db::class);
    }

    private function registerSession(): void
    {
        $db = $this->container->singleton(Db::class);
        $sessionHandler = new DatabaseSessionDriver($db);
        $session = new Session($sessionHandler);
        $this->container->singleton(SessionInterface::class, $session);
    }

    private function registerGlobalPipes(): void
    {
        $this->router->addGlobalPipe($this->container->get(ErrorPipe::class));
        $this->router->addGlobalPipe($this->container->get(LogPipe::class));
    }

    private function loadBootstrap(): void
    {
        require_once $this->appRoot . '/bootstrap.php'; // NOSONAR
    }

    private function loadRoutes(): void
    {
        $router = $this->router; // NOSONAR
        require_once $this->appRoot . '/app/routes.php'; // NOSONAR
    }
}
