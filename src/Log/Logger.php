<?php

declare(strict_types=1);

namespace Handlr\Log;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Stringable;

/**
 * PSR-3 compliant logger implementation.
 *
 * A simple logger that writes to a file or falls back to PHP's error_log().
 * Supports PSR-3 message interpolation with context placeholders.
 *
 * ## Basic usage
 *
 * ```php
 * // Log to a file
 * $logger = new Logger('/var/log/app.log');
 *
 * // Or use default error_log()
 * $logger = new Logger();
 * ```
 *
 * ## Log levels (RFC 5424 severity)
 *
 * ```php
 * $logger->emergency('System is unusable');           // Highest severity
 * $logger->alert('Action must be taken immediately');
 * $logger->critical('Critical conditions');
 * $logger->error('Error conditions');
 * $logger->warning('Warning conditions');
 * $logger->notice('Normal but significant');
 * $logger->info('Informational messages');
 * $logger->debug('Debug-level messages');            // Lowest severity
 * ```
 *
 * ## Context interpolation (PSR-3 style)
 *
 * Use `{placeholder}` in messages and provide values in the context array:
 *
 * ```php
 * $logger->info('User {username} logged in from {ip}', [
 *     'username' => 'phil',
 *     'ip' => '192.168.1.1',
 * ]);
 * // Output: [2025-01-15 10:30:00] INFO: User phil logged in from 192.168.1.1
 *
 * $logger->error('Failed to process order {order_id}', [
 *     'order_id' => 12345,
 *     'error' => $exception->getMessage(),
 * ]);
 * ```
 *
 * ## Context value handling
 *
 * - Strings and Stringable objects: used directly
 * - Scalars (int, float, bool) and null: converted via var_export
 * - Arrays and objects: JSON encoded
 *
 * ```php
 * $logger->debug('Request data: {data}', [
 *     'data' => ['user_id' => 1, 'action' => 'update'],
 * ]);
 * // Output includes: {"user_id":1,"action":"update"}
 * ```
 *
 * ## Output format
 *
 * ```
 * [YYYY-MM-DD HH:MM:SS] LEVEL: Message
 * [2025-01-15 10:30:00] INFO: User phil logged in
 * [2025-01-15 10:30:01] ERROR: Database connection failed
 * ```
 *
 * ## Dependency injection
 *
 * ```php
 * // In container/bootstrap
 * $container->set(LoggerInterface::class, new Logger('/var/log/app.log'));
 *
 * // In classes
 * class UserService
 * {
 *     public function __construct(private LoggerInterface $logger) {}
 *
 *     public function createUser(array $data): User
 *     {
 *         $this->logger->info('Creating user {email}', ['email' => $data['email']]);
 *         // ...
 *     }
 * }
 * ```
 */
class Logger implements LoggerInterface
{
    /**
     * Create a new logger instance.
     *
     * @param string|null $logFile Path to log file, or null to use error_log()
     */
    public function __construct(private ?string $logFile = null) {}

    /**
     * System is unusable.
     *
     * @param string|Stringable $message Log message (may contain {placeholders})
     * @param array             $context Placeholder values and additional data
     */
    public function emergency(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc.
     *
     * @param string|Stringable $message Log message (may contain {placeholders})
     * @param array             $context Placeholder values and additional data
     */
    public function alert(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param string|Stringable $message Log message (may contain {placeholders})
     * @param array             $context Placeholder values and additional data
     */
    public function critical(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    /**
     * Runtime errors that do not require immediate action.
     *
     * Should typically be logged and monitored.
     *
     * @param string|Stringable $message Log message (may contain {placeholders})
     * @param array             $context Placeholder values and additional data
     */
    public function error(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param string|Stringable $message Log message (may contain {placeholders})
     * @param array             $context Placeholder values and additional data
     */
    public function warning(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    /**
     * Normal but significant events.
     *
     * @param string|Stringable $message Log message (may contain {placeholders})
     * @param array             $context Placeholder values and additional data
     */
    public function notice(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param string|Stringable $message Log message (may contain {placeholders})
     * @param array             $context Placeholder values and additional data
     */
    public function info(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    /**
     * Detailed debug information.
     *
     * @param string|Stringable $message Log message (may contain {placeholders})
     * @param array             $context Placeholder values and additional data
     */
    public function debug(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    /**
     * Log with an arbitrary level.
     *
     * @param mixed             $level   Log level (use LogLevel constants)
     * @param string|Stringable $message Log message (may contain {placeholders})
     * @param array             $context Placeholder values and additional data
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $message = $this->interpolate((string) $message, $context);
        $logMessage = sprintf('[%s] %s: %s', date('Y-m-d H:i:s'), strtoupper($level), $message);

        if ($this->logFile) {
            file_put_contents($this->logFile, $logMessage . PHP_EOL, FILE_APPEND);
            return;
        }

        error_log($logMessage);
    }

    /**
     * Interpolate context values into message placeholders.
     *
     * PSR-3 style: "User {username} logged in" with ['username' => 'phil']
     * becomes "User phil logged in".
     *
     * @param string $message Message with {placeholder} tokens
     * @param array  $context Values to substitute
     *
     * @return string Interpolated message
     */
    private function interpolate(string $message, array $context): string
    {
        $replace = [];

        foreach ($context as $key => $value) {
            if (is_string($value) || (is_object($value) && method_exists($value, '__toString'))) {
                $replace['{' . $key . '}'] = (string) $value;
            } elseif (is_scalar($value) || is_null($value)) {
                $replace['{' . $key . '}'] = var_export($value, true);
            } else {
                $replace['{' . $key . '}'] = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }
        }

        return strtr($message, $replace);
    }
}
