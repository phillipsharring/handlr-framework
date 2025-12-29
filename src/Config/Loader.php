<?php

declare(strict_types=1);

namespace Handlr\Config;

use Dotenv\Dotenv;
use Handlr\Core\Container\Container;

class Loader
{
    public static function load(string $configPath, ?Container $container = null): Config
    {
        $dotenv = Dotenv::createImmutable(dirname($configPath) . '/../');
        $dotenv->load();

        // Load configuration file
        $configData = require $configPath; // NOSONAR

        $config = $container
            ? $container->get(Config::class)
            : new Config(new \Adbar\Dot());

        // Pass the loaded config to the Config class
        $config->load($configData);

        if ($container) {
            // Bind the instance as a singleton for the rest of the app lifecycle.
            $container->bind(Config::class, $config);
        }

        return $config;
    }
}
