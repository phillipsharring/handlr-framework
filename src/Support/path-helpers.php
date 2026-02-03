<?php

declare(strict_types=1);

/**
 * Path helper functions for early bootstrap.
 *
 * This file contains standalone functions that can be `require_once`'d directly
 * WITHOUT autoloading. It's designed for use during early bootstrap before
 * the Composer autoloader is available.
 *
 * Usage:
 *   require_once __DIR__ . '/path/to/src/Support/path-helpers.php';
 *   $dir = findInParentDirectories(__DIR__, fn($d) => file_exists("$d/composer.json"));
 */

if (!function_exists('findInParentDirectories')) {
    /**
     * Walk up the directory tree looking for a match.
     *
     * Starts from the given directory and walks up to parent directories,
     * calling the matcher function at each level until it returns true
     * or the filesystem root is reached.
     *
     * ```php
     * // Find directory containing vendor/autoload.php
     * $dir = findInParentDirectories(__DIR__, fn($d) => file_exists("$d/vendor/autoload.php"));
     *
     * // Find directory containing a specific file
     * $dir = findInParentDirectories(__DIR__, fn($d) => is_file("$d/.env"));
     *
     * // Find directory with custom validation
     * $dir = findInParentDirectories(getcwd(), function($d) {
     *     return is_file("$d/bootstrap.php") && is_dir("$d/app");
     * });
     * ```
     *
     * @param string   $startDir Directory to start searching from
     * @param callable $matcher  fn(string $dir): bool - return true when the target is found
     *
     * @return string|null The directory where matcher returned true, or null if not found
     */
    function findInParentDirectories(string $startDir, callable $matcher): ?string
    {
        $dir = realpath($startDir) ?: $startDir;

        while (true) {
            if ($matcher($dir)) {
                return $dir;
            }

            $parent = dirname($dir);

            // Reached filesystem root
            if ($parent === $dir) {
                return null;
            }

            $dir = $parent;
        }
    }
}

if (!function_exists('findFileInParents')) {
    /**
     * Find a file by walking up the directory tree.
     *
     * Convenience wrapper around findInParentDirectories() for the common
     * case of looking for a specific file.
     *
     * ```php
     * // Find vendor/autoload.php
     * $path = findFileInParents(__DIR__, 'vendor/autoload.php');
     *
     * // Find .env file
     * $path = findFileInParents(__DIR__, '.env');
     * ```
     *
     * @param string $startDir     Directory to start searching from
     * @param string $relativePath File path relative to each searched directory
     *
     * @return string|null Absolute path to the file if found, or null
     */
    function findFileInParents(string $startDir, string $relativePath): ?string
    {
        $dir = findInParentDirectories($startDir, fn($d) => file_exists("$d/$relativePath"));

        return $dir !== null ? "$dir/$relativePath" : null;
    }
}
