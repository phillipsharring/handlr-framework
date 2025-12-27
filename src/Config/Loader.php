<?php

declare(strict_types=1);

namespace Handlr\Config;

use Dotenv\Dotenv;
use Handlr\Core\Container;

class Loader
{
    public static function load(string $configPath): void
    {
        $dotenv = Dotenv::createImmutable(dirname($configPath) . '/../');
        $dotenv->load();

        $container = new Container();

        // Load configuration file
        $configData = require $configPath; // NOSONAR

        $config = $container->get(Config::class);

        // Pass the loaded config to the Config class
        $config->load($configData);
        $container->set(Config::class, fn() => $config);
    }
}
