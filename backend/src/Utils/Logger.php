<?php

namespace App\Utils;

use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;

class Logger
{
    private MonologLogger $logger;
    
    public function __construct()
    {
        $this->logger = new MonologLogger('isitdoneyet');
        
        // Add stream handler for console output
        $this->logger->pushHandler(new StreamHandler('php://stdout', MonologLogger::DEBUG));
        
        // Add file handler for persistent logging
        $this->logger->pushHandler(
            new RotatingFileHandler(__DIR__ . '/../../storage/logs/app.log', 0, MonologLogger::INFO)
        );
    }
    
    public function info(string $message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }
    
    public function error(string $message, array $context = []): void
    {
        $this->logger->error($message, $context);
    }
    
    public function debug(string $message, array $context = []): void
    {
        $this->logger->debug($message, $context);
    }
    
    public function warning(string $message, array $context = []): void
    {
        $this->logger->warning($message, $context);
    }
}
