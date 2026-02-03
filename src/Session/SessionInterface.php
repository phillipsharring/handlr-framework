<?php

declare(strict_types=1);

namespace Handlr\Session;

/**
 * Interface for session management.
 *
 * Provides a clean API for working with session data, abstracting away
 * direct `$_SESSION` access. Implementations can use different storage
 * backends (files, database, Redis, etc.).
 *
 * ## Basic usage
 *
 * ```php
 * // Start the session
 * $session->start();
 *
 * // Store data
 * $session->set('user_id', 123);
 * $session->set('cart', ['item1', 'item2']);
 *
 * // Retrieve data
 * $userId = $session->get('user_id');
 * $cart = $session->get('cart', []);  // Default to empty array
 *
 * // Check existence
 * if ($session->has('user_id')) {
 *     // User is logged in
 * }
 *
 * // Remove specific data
 * $session->remove('cart');
 *
 * // Destroy entire session (logout)
 * $session->destroy();
 * ```
 *
 * ## Implementation
 *
 * The framework provides `Session` class which uses PHP's native session
 * handling with pluggable storage drivers:
 *
 * ```php
 * // Database-backed sessions
 * $handler = new DatabaseSessionDriver($db);
 * $session = new Session($handler);
 *
 * // Or use PHP's default file-based sessions
 * $session = new Session(new \SessionHandler());
 * ```
 *
 * ## In pipes/handlers
 *
 * ```php
 * class LoginHandler implements Handler
 * {
 *     public function __construct(private SessionInterface $session) {}
 *
 *     public function handle(array|HandlerInput $input): ?HandlerResult
 *     {
 *         // ... validate credentials ...
 *
 *         $this->session->set('user_id', $user->id);
 *         $this->session->set('logged_in_at', time());
 *
 *         return HandlerResult::ok(['message' => 'Logged in']);
 *     }
 * }
 * ```
 */
interface SessionInterface
{
    /**
     * Start the session.
     *
     * Must be called before any other session operations.
     * Safe to call multiple times - will only start once.
     */
    public function start(): void;

    /**
     * Get a value from the session.
     *
     * ```php
     * $userId = $session->get('user_id');
     * $theme = $session->get('theme', 'light');  // With default
     * ```
     *
     * @param string $key     The session key
     * @param mixed  $default Value to return if key doesn't exist
     *
     * @return mixed The stored value or default
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * Store a value in the session.
     *
     * ```php
     * $session->set('user_id', 123);
     * $session->set('preferences', ['theme' => 'dark']);
     * ```
     *
     * @param string $key   The session key
     * @param mixed  $value The value to store (must be serializable)
     */
    public function set(string $key, mixed $value): void;

    /**
     * Remove a value from the session.
     *
     * ```php
     * $session->remove('cart');
     * $session->remove('flash_message');
     * ```
     *
     * @param string $key The session key to remove
     */
    public function remove(string $key): void;

    /**
     * Check if a key exists in the session.
     *
     * ```php
     * if ($session->has('user_id')) {
     *     // User is logged in
     * }
     * ```
     *
     * @param string $key The session key to check
     *
     * @return bool True if the key exists
     */
    public function has(string $key): bool;

    /**
     * Destroy the session completely.
     *
     * Removes all session data and invalidates the session ID.
     * Use for logout functionality.
     *
     * ```php
     * // Logout
     * $session->destroy();
     * ```
     */
    public function destroy(): void;
}
