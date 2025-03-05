<?php

declare(strict_types=1);

namespace Example\Typo3ParallelSchedulerTaskProcessingTest;

use DateTimeImmutable;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Throwable;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Scheduler\Scheduler;
use TYPO3\CMS\Scheduler\Task\AbstractTask;
use TYPO3\CMS\Scheduler\Domain\Repository\SchedulerTaskRepository;

final class CreateSchedulerTaskCommand extends Command
{
    public function __construct(
        private readonly Scheduler $scheduler,
        string|null $name = null,
    ) {
        parent::__construct($name);
    }

    #[\Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $stderr = $output instanceof ConsoleOutputInterface
            ? $output->getErrorOutput()
            : $output;
        
        try {
            $task = new TestSchedulerTask();
            $task->setDisabled(false);
            /** @psalm-suppress InternalMethod */
            $task->registerRecurringExecution(
                start: (new DateTimeImmutable())->getTimestamp(),
                interval: 1,
                multiple: false,
            );

            /** @psalm-suppress UndefinedMethod method exists on different classes in different TYPO3 versions */
            $taskSuccessfullyPersisted =
                GeneralUtility::makeInstance(Typo3Version::class)->getMajorVersion() >= 12
                    ? !!GeneralUtility::makeInstance(SchedulerTaskRepository::class)->add($task)
                    : !!$this->scheduler->addTask($task);

            if (!$taskSuccessfullyPersisted) {
                $stderr->writeln('Newly created task could not be persisted.');
                return Command::FAILURE;
            }

            $output->writeln((string)$task->getTaskUid());
            return Command::SUCCESS;
        } catch (Throwable $exception) {
            $stderr->writeln("Failed due to uncaught exception: {$exception}");
            return Command::FAILURE;
        }
    }
}
