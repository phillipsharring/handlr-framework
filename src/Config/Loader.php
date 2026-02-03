<?php

declare(strict_types=1);

namespace Handlr\Config;

use Dotenv\Dotenv;
use Handlr\Core\Container\Container;

/**
 * Configuration loader that initializes environment and config files.
 *
 * Handles loading .env files and PHP configuration files, optionally
 * registering the Config instance with the DI container.
 */
class Loader
{
    /**
     * Load environment variables and configuration from disk.
     *
     * Loads the .env file from the parent directory of the config file,
     * then requires the PHP config file and populates a Config instance.
     * If a container is provided, the Config is registered as a singleton.
     *
     * @param string $configPath Absolute path to the PHP config file
     * @param Container|null $container Optional DI container for singleton registration
     * @return Config The populated configuration instance
     *
     * @example Without container:
     *     $config = Loader::load('/app/config/app.php');
     *     $debug = $config->get('app.debug');
     *
     * @example With container:
     *     Loader::load('/app/config/app.php', $container);
     *     // Config is now available via $container->get(Config::class)
     */
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
