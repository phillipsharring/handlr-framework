<?php

declare(strict_types=1);

namespace Handlr\Session;

use SessionHandlerInterface;

/**
 * Session management using PHP's native session handling.
 *
 * Wraps PHP's `$_SESSION` superglobal with a clean interface and supports
 * pluggable storage backends via `SessionHandlerInterface`.
 *
 * ## Setup with database storage
 *
 * ```php
 * // In bootstrap/container setup
 * $db = new Db($config);
 * $handler = new DatabaseSessionDriver($db, 'sessions');
 * $session = new Session($handler);
 *
 * // Register in container
 * $container->set(SessionInterface::class, $session);
 * ```
 *
 * ## Setup with default file storage
 *
 * ```php
 * $session = new Session(new \SessionHandler());
 * ```
 *
 * ## Starting the session
 *
 * Sessions must be started before use, typically in a middleware pipe:
 *
 * ```php
 * class SessionPipe implements Pipe
 * {
 *     public function __construct(private SessionInterface $session) {}
 *
 *     public function handle(Request $request, Response $response, array $args, callable $next): Response
 *     {
 *         $this->session->start();
 *         return $next($request, $response, $args);
 *     }
 * }
 * ```
 *
 * ## Working with session data
 *
 * ```php
 * // Store user info after login
 * $session->set('user_id', $user->id);
 * $session->set('user_role', $user->role);
 *
 * // Retrieve later
 * $userId = $session->get('user_id');
 * $role = $session->get('user_role', 'guest');  // Default if not set
 *
 * // Check if logged in
 * if ($session->has('user_id')) {
 *     // Authenticated
 * }
 *
 * // Flash messages
 * $session->set('flash', 'Profile updated successfully');
 * // ... after displaying ...
 * $session->remove('flash');
 * ```
 *
 * ## Logout
 *
 * ```php
 * class LogoutHandler implements Handler
 * {
 *     public function __construct(private SessionInterface $session) {}
 *
 *     public function handle(array|HandlerInput $input): ?HandlerResult
 *     {
 *         $this->session->destroy();
 *         return HandlerResult::ok(['message' => 'Logged out']);
 *     }
 * }
 * ```
 *
 * ## Session configuration
 *
 * Configure PHP session settings in php.ini or at runtime before starting:
 *
 * ```php
 * // Before $session->start()
 * ini_set('session.cookie_lifetime', '86400');  // 24 hours
 * ini_set('session.gc_maxlifetime', '86400');
 * ini_set('session.cookie_httponly', '1');
 * ini_set('session.cookie_secure', '1');        // HTTPS only
 * ini_set('session.cookie_samesite', 'Lax');
 * ```
 */
class Session implements SessionInterface
{
    /**
     * Whether the session has been started.
     */
    private bool $started = false;

    /**
     * Create a new session instance.
     *
     * @param SessionHandlerInterface $handler Storage backend (database, files, Redis, etc.)
     */
    public function __construct(SessionHandlerInterface $handler)
    {
        session_set_save_handler($handler, true);
    }

    /**
     * Start the session.
     *
     * Safe to call multiple times - only starts if not already started
     * and no session is currently active.
     */
    public function start(): void
    {
        if (!$this->started && session_status() === PHP_SESSION_NONE) {
            session_start();
            $this->started = true;
        }
    }

    /**
     * Get a value from the session.
     *
     * @param string $key     The session key
     * @param mixed  $default Value to return if key doesn't exist
     *
     * @return mixed The stored value or default
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Store a value in the session.
     *
     * @param string $key   The session key
     * @param mixed  $value The value to store (must be serializable)
     */
    public function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * Remove a value from the session.
     *
     * @param string $key The session key to remove
     */
    public function remove(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Check if a key exists in the session.
     *
     * @param string $key The session key to check
     *
     * @return bool True if the key exists and is not null
     */
    public function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    /**
     * Destroy the session completely.
     *
     * Removes all data and invalidates the session.
     * The session cookie remains but the server-side data is gone.
     */
    public function destroy(): void
    {
        if (session_status() !== PHP_SESSION_NONE) {
            session_destroy();
            $this->started = false;
        }
    }
}
