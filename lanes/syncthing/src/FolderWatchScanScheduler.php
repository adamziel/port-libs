<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class FolderWatchScanScheduler
{
    /**
     * @var array<string, FolderWatchEventAggregator>
     */
    private array $aggregators = [];

    /**
     * @var array<string, list<array{eventType:string, paths:list<string>, count:int}>>
     */
    private array $lastDispatchedBatches = [];

    public function __construct(
        private readonly FolderScanScheduler $scheduler,
        private readonly int $notifyDelaySeconds = 10,
        private readonly ?int $notifyTimeoutSeconds = null,
        private readonly int $maxFiles = 512,
        private readonly int $maxFilesPerDir = 128,
    ) {
    }

    /**
     * @return null|array{folder:string, pendingEventCount:int, pendingPaths:list<string>, pendingTypes:array<string, string>, inProgressPaths:list<string>, notifyDelaySeconds:int, notifyTimeoutSeconds:int, nextScanAt:?int, due:bool}
     */
    public function recordEvent(
        string $folderId,
        string $path,
        string $eventType = FolderWatchEventAggregator::EVENT_NON_REMOVE,
        ?int $now = null,
    ): ?array {
        $now = self::clock($now);
        if (!$this->folderAcceptsWatchEvents($folderId)) {
            return null;
        }

        $aggregator = $this->aggregator($folderId);
        $aggregator->recordEvent($path, $eventType, $now);

        return $this->watchStatus($folderId, $now);
    }

    public function markItemStarted(string $folderId, string $path): void
    {
        if (!$this->folderAcceptsWatchEvents($folderId)) {
            return;
        }

        $this->aggregator($folderId)->markItemStarted($path);
    }

    public function markItemFinished(string $folderId, string $path): void
    {
        if (!$this->folderAcceptsWatchEvents($folderId)) {
            return;
        }

        $this->aggregator($folderId)->markItemFinished($path);
    }

    /**
     * @return null|array{folder:string, pendingEventCount:int, pendingPaths:list<string>, pendingTypes:array<string, string>, inProgressPaths:list<string>, notifyDelaySeconds:int, notifyTimeoutSeconds:int, nextScanAt:?int, due:bool}
     */
    public function watchStatus(string $folderId, ?int $now = null): ?array
    {
        self::assertFolderId($folderId);
        if (!isset($this->aggregators[$folderId])) {
            return null;
        }

        return ['folder' => $folderId] + $this->aggregators[$folderId]->status($now);
    }

    /**
     * @return array<string, array{folder:string, pendingEventCount:int, pendingPaths:list<string>, pendingTypes:array<string, string>, inProgressPaths:list<string>, notifyDelaySeconds:int, notifyTimeoutSeconds:int, nextScanAt:?int, due:bool}>
     */
    public function watchStatuses(?int $now = null): array
    {
        $statuses = [];
        foreach ($this->aggregators as $folderId => $aggregator) {
            $statuses[$folderId] = ['folder' => $folderId] + $aggregator->status($now);
        }
        ksort($statuses, SORT_STRING);

        return $statuses;
    }

    /**
     * @param null|callable(FolderScanProgress): void $progressLogger
     * @param null|callable(string, \Throwable, string): void $errorLogger
     * @param null|callable(?string): bool $shouldCancel
     * @param null|callable(string, array{description:string, sub:string, error:string}): void $failureLogger
     */
    public function scanDueWatchEvents(
        ?IgnoreMatcher $ignoreMatcher = null,
        bool $hashBlocks = false,
        ?int $blockSize = null,
        ?callable $progressLogger = null,
        ?callable $errorLogger = null,
        ?callable $shouldCancel = null,
        ?callable $failureLogger = null,
        ?int $now = null,
    ): FolderScanSchedulerResult {
        $now = self::clock($now);
        $snapshots = [];
        $errors = [];
        $this->lastDispatchedBatches = [];

        ksort($this->aggregators, SORT_STRING);
        foreach ($this->aggregators as $folderId => $aggregator) {
            if (!$this->folderAcceptsWatchEvents($folderId)) {
                continue;
            }

            $batches = $aggregator->dueBatches($now);
            if ($batches === []) {
                continue;
            }
            $this->lastDispatchedBatches[$folderId] = $batches;

            foreach ($batches as $batch) {
                try {
                    $snapshots[$folderId] = $this->scheduler->scanFolderSubdirs(
                        $folderId,
                        self::scanSubs($batch['paths']),
                        $ignoreMatcher,
                        $hashBlocks,
                        $blockSize,
                        $progressLogger,
                        $errorLogger,
                        $shouldCancel,
                        $failureLogger,
                        $now,
                    );
                } catch (\Throwable $throwable) {
                    $errors[$folderId] = $throwable;
                    break;
                }
            }
        }

        return new FolderScanSchedulerResult($snapshots, $errors);
    }

    /**
     * @return array<string, list<array{eventType:string, paths:list<string>, count:int}>>
     */
    public function lastDispatchedBatches(): array
    {
        return $this->lastDispatchedBatches;
    }

    private function aggregator(string $folderId): FolderWatchEventAggregator
    {
        self::assertFolderId($folderId);
        if (!isset($this->aggregators[$folderId])) {
            $this->aggregators[$folderId] = new FolderWatchEventAggregator(
                $this->notifyDelaySeconds,
                $this->notifyTimeoutSeconds,
                $this->maxFiles,
                $this->maxFilesPerDir,
            );
        }

        return $this->aggregators[$folderId];
    }

    private function folderAcceptsWatchEvents(string $folderId): bool
    {
        self::assertFolderId($folderId);

        return in_array($folderId, $this->scheduler->folderIds(), true)
            && !$this->scheduler->isPaused($folderId);
    }

    /**
     * @param list<string> $paths
     * @return list<string>
     */
    private static function scanSubs(array $paths): array
    {
        if (in_array('.', $paths, true)) {
            return [''];
        }

        return $paths;
    }

    private static function assertFolderId(string $folderId): void
    {
        if ($folderId === '') {
            throw new \InvalidArgumentException('Watch scan scheduler requires a folder ID');
        }
    }

    private static function clock(?int $now): int
    {
        $now ??= time();
        if ($now < 0) {
            throw new \InvalidArgumentException('Watch scan scheduler clock must not be negative');
        }

        return $now;
    }
}
