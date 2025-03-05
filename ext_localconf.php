<?php

declare(strict_types=1);

if (!defined('TYPO3')) {
    die('Access denied.');
}

use Example\Typo3ParallelSchedulerTaskProcessingTest\TestSchedulerTask;

/** @psalm-suppress MixedArrayAssignment */
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['scheduler']['tasks'][TestSchedulerTask::class] = [
    'extension' => 'example_typo3_parallel_scheduler_task_processing_test',
    'title' => 'Test Task',
    'description' => '',
];
