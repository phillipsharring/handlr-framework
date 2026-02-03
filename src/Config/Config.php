<?php

declare(strict_types=1);

namespace Handlr\Config;

use Adbar\Dot;

/**
 * Configuration store with dot-notation access.
 *
 * Wraps Adbar\Dot to provide simple key access using dot notation
 * (e.g., 'database.host', 'app.debug').
 *
 * @example
 *     $config->get('database.host');           // 'localhost'
 *     $config->get('app.debug', false);        // false (default)
 *     $config->get('cache.driver');            // 'redis'
 */
final class Config
{
    /**
     * @param Dot $dot The underlying dot-notation array store
     */
    public function __construct(public Dot $dot) {}

    /**
     * Replace the entire configuration with a new array.
     *
     * @param array<string, mixed> $config The configuration array to load
     */
    public function load(array $config): void
    {
        // Replace the entire config payload.
        $this->dot->setArray($config);
    }

    /**
     * Get a configuration value by dot-notation key.
     *
     * @param string $key Dot-notation key (e.g., 'database.host', 'app.name')
     * @param mixed $default Value to return if key doesn't exist
     * @return mixed The configuration value or default
     *
     * @example
     *     $config->get('database.host');           // 'localhost'
     *     $config->get('features.enabled', []);    // [] if not set
     */
    public function get(string $key, $default = null)
    {
        return $this->dot->get($key, $default);
    }
}
