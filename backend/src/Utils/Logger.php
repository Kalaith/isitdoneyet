<?php

declare(strict_types=1);

namespace App\Utils;

use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger as MonologLogger;

class Logger
{
    private MonologLogger $logger;

    public function __construct()
    {
        $this->logger = new MonologLogger('isitdoneyet');
        $this->logger->pushHandler(
            new RotatingFileHandler(__DIR__ . '/../../storage/logs/app.log', 0, MonologLogger::INFO)
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    public function info(string $message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function error(string $message, array $context = []): void
    {
        $this->logger->error($message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function debug(string $message, array $context = []): void
    {
        $this->logger->debug($message, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function warning(string $message, array $context = []): void
    {
        $this->logger->warning($message, $context);
    }
}
