<?php

declare(strict_types=1);

namespace Handlr\Log;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class Psr3Logger implements LoggerInterface
{
    public string $file;
    public function __construct(private ?string $logFile = null) {
        $this->file = $logFile;
    }

    public function alert($message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    public function critical($message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    public function debug($message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    public function emergency($message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    public function error($message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    public function info($message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    public function notice($message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    public function warning($message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    public function log($level, $message, array $context = []): void
    {
        $logMessage = sprintf('%s: %s', strtoupper($level), $message);

        if ($this->logFile) {
            file_put_contents($this->logFile, $logMessage . PHP_EOL, FILE_APPEND);
            return;
        }

        error_log($logMessage);
    }
}
