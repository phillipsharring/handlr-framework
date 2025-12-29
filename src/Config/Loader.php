<?php

declare(strict_types=1);

namespace Handlr\Config;

use Dotenv\Dotenv;
use Handlr\Core\Kernel;

class Loader
{
    public static function load(string $configPath): void
    {
        $dotenv = Dotenv::createImmutable(dirname($configPath) . '/../');
        $dotenv->load();

        $container = Kernel::getContainer();

        // Load configuration file
        $configData = require $configPath; // NOSONAR

        $config = $container->get(Config::class);

        // Pass the loaded config to the Config class
        $config->load($configData);
        $container->set(Config::class, static fn() => $config);
    }
}
