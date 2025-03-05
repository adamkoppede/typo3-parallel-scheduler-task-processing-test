<?php

declare(strict_types=1);

namespace Example\Typo3ParallelSchedulerTaskProcessingTest;

use RuntimeException;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Core\Environment;

final readonly class SingleExecutionGuardService
{
    public function __construct(
        private LoggerInterface $logger, 
    ) {}

    public function check(): bool
    {
        $filename = Environment::getProjectPath() . '/single-execution-guard';
        $file = @\fopen($filename, 'xb');
        if ($file === false) {
            $error = \error_get_last();
            $this->logger->critical(
                'Failed to create single execution guard: {message}',
                [
                    'message' => $error !== null ? $error['message'] : 'unknown',
                ]
            );
            return false;
        }

        if (!@\fclose($file)) {
            $error = \error_get_last();
            $this->logger->critical(
                'Failed to close single execution guard file descriptor: {message}',
                [
                    'message' => $error !== null ? $error['message'] : 'unknown',
                ]
            );
            return false;
        };
    
        usleep(800000);

        if (!@\unlink($filename)) {
            $error = \error_get_last();
            $this->logger->critical(
                'Failed to unlink single execution guard file after pause: {message}',
                [
                    'message' => $error !== null ? $error['message'] : 'unknown',
                ]
            );
            return false;
        }

        return true;
    }
}
