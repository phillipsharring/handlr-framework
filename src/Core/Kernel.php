<?php

declare(strict_types=1);

namespace Handlr\Core;

use Handlr\Database\Db;
use Handlr\Handlers\ErrorHandler;
use Handlr\Handlers\LogHandler;
use Handlr\Log\Log;
use Handlr\Log\Psr3Logger;
use Handlr\Session\DatabaseSessionDriver;
use Handlr\Session\Session;

final class Kernel
{
    private static ?Kernel $instance = null;
    private string $appRoot;
    private Container $container;
    private Router $router {
        get {
            return $this->router;
        }
    }

    private function __construct() {}

    public function initialize(Container $container, Router $router, string $appRoot): void
    {
        $this->container = $container;
        $this->router = $router;
        $this->appRoot = $appRoot;

        $this->loadBootstrap();
        $this->registerServices();
        $this->registerGlobalHandlers();

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

    private function registerServices(): void
    {
        $this->registerLogger();
        $this->registerDatabase();
        $this->registerSession();
    }

    private function registerLogger(): void
    {
        $logFile = $this->appRoot . '/logs/app.log';
        $this->container->set(LogHandler::class, static function () use ($logFile) {
            $logger = new Log();
            $logger::setLogger(new Psr3Logger($logFile));
            return new LogHandler($logger);
        });
    }

    private function registerDatabase(): void
    {
        $this->container->set(Db::class, static function () {
            return new Db();
        });
    }

    private function registerSession(): void
    {
        $db = $this->container->get(Db::class);
        $sessionHandler = new DatabaseSessionDriver($db);
        Session::useHandler($sessionHandler);
    }

    private function registerGlobalHandlers(): void
    {
        $this->router->addGlobalHandler(new ErrorHandler());
        $logHandler = $this->container->get(LogHandler::class);
        $this->router->addGlobalHandler($logHandler);
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
