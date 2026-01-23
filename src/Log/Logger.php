<?php

declare(strict_types=1);

namespace Handlr\Log;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Stringable;

class Logger implements LoggerInterface
{
    public function __construct(private ?string $logFile = null) {}

    public function emergency(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    public function alert(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    public function critical(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    public function error(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    public function warning(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    public function notice(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    public function info(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    public function debug(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

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
     * PSR-3 style: "User {username} logged in" with ['username' => 'phil']
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
