<?php

declare(strict_types=1);

namespace Example\Typo3ParallelSchedulerTaskProcessingTest;

use TYPO3\CMS\Scheduler\Task\AbstractTask;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class TestSchedulerTask extends AbstractTask
{
    #[\Override]
    public function execute(): bool
    {
        return GeneralUtility::makeInstance(SingleExecutionGuardService::class)->check();
    }
}
