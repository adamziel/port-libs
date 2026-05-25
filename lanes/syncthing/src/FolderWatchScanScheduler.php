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

    /**
     * @var array<string, array{folder:string, lastError:string, errorAt:int, restartAttempt:int, restartDelaySeconds:int, restartAt:int, scanOnWatchError:bool}>
     */
    private array $watchRestarts = [];

    /**
     * @var array<string, array{folder:string, cleanupAt:int, hadState:bool, folderExists:bool, folderPaused:bool, discardedPendingEvents:bool, preservedPendingEvents:bool, clearedRestart:bool, clearedInProgress:bool, pendingEventCountBefore:int, pendingEventCountAfter:int, pendingPathsBefore:list<string>, pendingPathsAfter:list<string>, statusAfter:?array<string, mixed>}>
     */
    private array $recentCleanups = [];

    private int $effectiveNotifyTimeoutSeconds;

    public function __construct(
        private readonly FolderScanScheduler $scheduler,
        private readonly int $notifyDelaySeconds = 10,
        private readonly ?int $notifyTimeoutSeconds = null,
        private readonly int $maxFiles = 512,
        private readonly int $maxFilesPerDir = 128,
        private readonly int $watchRestartInitialDelaySeconds = 10,
        private readonly int $watchRestartMaxDelaySeconds = 60,
        private readonly int $recentCleanupTtlSeconds = 300,
        private readonly int $recentCleanupMaxEntries = 64,
    ) {
        if ($this->watchRestartInitialDelaySeconds < 0 || $this->watchRestartMaxDelaySeconds < 0) {
            throw new \InvalidArgumentException('Watch restart delays must not be negative');
        }
        if ($this->recentCleanupTtlSeconds < 0 || $this->recentCleanupMaxEntries < 1) {
            throw new \InvalidArgumentException('Recent cleanup retention must use a non-negative TTL and at least one entry');
        }
        $this->effectiveNotifyTimeoutSeconds = $this->notifyTimeoutSeconds === null
            ? FolderWatchEventAggregator::defaultNotifyTimeoutSeconds($this->notifyDelaySeconds)
            : max($this->notifyTimeoutSeconds, $this->notifyDelaySeconds);
    }

    /**
     * @return null|array{folder:string, pendingEventCount:int, pendingPaths:list<string>, pendingTypes:array<string, string>, inProgressPaths:list<string>, notifyDelaySeconds:int, notifyTimeoutSeconds:int, nextScanAt:?int, due:bool, watcherRestart:?array{folder:string, lastError:string, errorAt:int, restartAttempt:int, restartDelaySeconds:int, restartAt:int, remainingSeconds:int, due:bool, scanOnWatchError:bool}}
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

        unset($this->recentCleanups[$folderId]);
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
     * @param null|callable(FolderScanProgress): void $progressLogger
     * @param null|callable(string, \Throwable, string): void $errorLogger
     * @param null|callable(?string): bool $shouldCancel
     * @param null|callable(string, array{description:string, sub:string, error:string}): void $failureLogger
     */
    public function recordWatcherError(
        string $folderId,
        \Throwable|string $error,
        bool $scanOnWatchError = true,
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
        $message = $error instanceof \Throwable ? $error->getMessage() : trim($error);
        if ($message === '') {
            $message = 'watcher error';
        }

        if (!$this->folderExists($folderId)) {
            return new FolderScanSchedulerResult();
        }

        $previousAttempt = $this->watchRestarts[$folderId]['restartAttempt'] ?? 0;
        $attempt = $previousAttempt + 1;
        $delay = $this->restartDelaySeconds($attempt);
        $this->watchRestarts[$folderId] = [
            'folder' => $folderId,
            'lastError' => $message,
            'errorAt' => $now,
            'restartAttempt' => $attempt,
            'restartDelaySeconds' => $delay,
            'restartAt' => $now + $delay,
            'scanOnWatchError' => $scanOnWatchError,
        ];

        if (!$scanOnWatchError || !$this->folderAcceptsWatchEvents($folderId)) {
            return new FolderScanSchedulerResult();
        }

        try {
            return new FolderScanSchedulerResult([
                $folderId => $this->scheduler->scanFolder(
                    $folderId,
                    $ignoreMatcher,
                    $hashBlocks,
                    $blockSize,
                    $progressLogger,
                    $errorLogger,
                    $shouldCancel,
                    $failureLogger,
                    $now,
                ),
            ]);
        } catch (\Throwable $throwable) {
            return new FolderScanSchedulerResult([], [$folderId => $throwable]);
        }
    }

    public function markWatcherRestarted(string $folderId): bool
    {
        self::assertFolderId($folderId);
        if (!$this->folderExists($folderId)) {
            if (isset($this->watchRestarts[$folderId]) || isset($this->aggregators[$folderId])) {
                $this->cleanupWatchingFolder($folderId, discardPendingEvents: true);
            } else {
                unset($this->lastDispatchedBatches[$folderId]);
            }

            return false;
        }

        if (!isset($this->watchRestarts[$folderId])) {
            return false;
        }

        unset($this->watchRestarts[$folderId]);

        return true;
    }

    /**
     * @return array<string, array{folder:string, lastError:string, errorAt:int, restartAttempt:int, restartDelaySeconds:int, restartAt:int, remainingSeconds:int, due:bool, scanOnWatchError:bool}>
     */
    public function dueWatcherRestarts(?int $now = null): array
    {
        $now = self::clock($now);
        $due = [];
        foreach (array_keys($this->watchRestarts) as $folderId) {
            if (!$this->folderAcceptsWatchEvents($folderId)) {
                continue;
            }

            $status = $this->watchRestartStatus($folderId, $now);
            if ($status !== null && $status['due']) {
                $due[$folderId] = $status;
            }
        }
        ksort($due, SORT_STRING);

        return $due;
    }

    /**
     * @return null|array{folder:string, lastError:string, errorAt:int, restartAttempt:int, restartDelaySeconds:int, restartAt:int, remainingSeconds:int, due:bool, scanOnWatchError:bool}
     */
    public function completeDueWatcherRestart(string $folderId, ?int $now = null): ?array
    {
        self::assertFolderId($folderId);
        $status = $this->watchRestartStatus($folderId, self::clock($now));
        if ($status === null || !$status['due'] || !$this->folderAcceptsWatchEvents($folderId)) {
            return null;
        }

        unset($this->watchRestarts[$folderId]);

        return $status;
    }

    public function stopWatchingFolder(string $folderId, bool $discardPendingEvents = false): bool
    {
        return $this->cleanupWatchingFolder($folderId, $discardPendingEvents)['hadState'];
    }

    public function pauseWatchingFolder(string $folderId): bool
    {
        return $this->cleanupWatchingFolder($folderId, discardPendingEvents: false, preserveRestart: true)['hadState'];
    }

    public function removeWatchingFolder(string $folderId): bool
    {
        return $this->stopWatchingFolder($folderId, discardPendingEvents: true);
    }

    /**
     * @return array{folder:string, cleanupAt:int, hadState:bool, folderExists:bool, folderPaused:bool, discardedPendingEvents:bool, preservedPendingEvents:bool, clearedRestart:bool, clearedInProgress:bool, pendingEventCountBefore:int, pendingEventCountAfter:int, pendingPathsBefore:list<string>, pendingPathsAfter:list<string>, statusAfter:?array<string, mixed>}
     */
    public function cleanupWatchingFolder(
        string $folderId,
        bool $discardPendingEvents = false,
        bool $preserveRestart = false,
        ?int $now = null,
    ): array {
        self::assertFolderId($folderId);
        $now = self::clock($now);
        $before = $this->watchStatus($folderId, $now);
        $hadRestart = isset($this->watchRestarts[$folderId]);
        $hadAggregator = isset($this->aggregators[$folderId]);
        $hadState = $hadRestart || $hadAggregator;
        $pendingCountBefore = (int) ($before['pendingEventCount'] ?? 0);
        $pendingPathsBefore = array_values($before['pendingPaths'] ?? []);
        $clearedInProgress = $hadAggregator && (($before['inProgressPaths'] ?? []) !== []);

        if (!$preserveRestart) {
            unset($this->watchRestarts[$folderId]);
        }
        unset($this->lastDispatchedBatches[$folderId]);

        if ($discardPendingEvents) {
            unset($this->aggregators[$folderId]);
        } elseif (isset($this->aggregators[$folderId])) {
            $this->aggregators[$folderId]->clearInProgress();
        }

        $after = $this->watchStatus($folderId, $now);

        $cleanup = [
            'folder' => $folderId,
            'cleanupAt' => $now,
            'hadState' => $hadState,
            'folderExists' => $this->folderExists($folderId),
            'folderPaused' => $this->folderExists($folderId) && $this->scheduler->isPaused($folderId),
            'discardedPendingEvents' => $discardPendingEvents && $pendingCountBefore > 0,
            'preservedPendingEvents' => !$discardPendingEvents && (($after['pendingEventCount'] ?? 0) > 0),
            'clearedRestart' => $hadRestart && !$preserveRestart,
            'clearedInProgress' => $clearedInProgress,
            'pendingEventCountBefore' => $pendingCountBefore,
            'pendingEventCountAfter' => (int) ($after['pendingEventCount'] ?? 0),
            'pendingPathsBefore' => $pendingPathsBefore,
            'pendingPathsAfter' => array_values($after['pendingPaths'] ?? []),
            'statusAfter' => $after,
        ];

        $this->recentCleanups[$folderId] = $cleanup;
        $this->pruneRecentCleanups($now);

        return $cleanup;
    }

    /**
     * @return null|array{folder:string, pendingEventCount:int, pendingPaths:list<string>, pendingTypes:array<string, string>, inProgressPaths:list<string>, notifyDelaySeconds:int, notifyTimeoutSeconds:int, nextScanAt:?int, due:bool, watcherRestart:?array{folder:string, lastError:string, errorAt:int, restartAttempt:int, restartDelaySeconds:int, restartAt:int, remainingSeconds:int, due:bool, scanOnWatchError:bool}}
     */
    public function watchStatus(string $folderId, ?int $now = null): ?array
    {
        self::assertFolderId($folderId);
        if (!isset($this->aggregators[$folderId])) {
            if (!isset($this->watchRestarts[$folderId])) {
                return null;
            }

            return [
                'folder' => $folderId,
                'pendingEventCount' => 0,
                'pendingPaths' => [],
                'pendingTypes' => [],
                'inProgressPaths' => [],
                'notifyDelaySeconds' => $this->notifyDelaySeconds,
                'notifyTimeoutSeconds' => $this->effectiveNotifyTimeoutSeconds,
                'nextScanAt' => null,
                'due' => false,
                'watcherRestart' => $this->watchRestartStatus($folderId, self::clock($now)),
            ];
        }

        return ['folder' => $folderId]
            + $this->aggregators[$folderId]->status($now)
            + ['watcherRestart' => $this->watchRestartStatus($folderId, self::clock($now))];
    }

    /**
     * @return array<string, array{folder:string, pendingEventCount:int, pendingPaths:list<string>, pendingTypes:array<string, string>, inProgressPaths:list<string>, notifyDelaySeconds:int, notifyTimeoutSeconds:int, nextScanAt:?int, due:bool, watcherRestart:?array{folder:string, lastError:string, errorAt:int, restartAttempt:int, restartDelaySeconds:int, restartAt:int, remainingSeconds:int, due:bool, scanOnWatchError:bool}}>
     */
    public function watchStatuses(?int $now = null): array
    {
        $statuses = [];
        foreach ($this->aggregators as $folderId => $aggregator) {
            $statuses[$folderId] = ['folder' => $folderId]
                + $aggregator->status($now)
                + ['watcherRestart' => $this->watchRestartStatus($folderId, self::clock($now))];
        }
        foreach (array_keys($this->watchRestarts) as $folderId) {
            $statuses[$folderId] ??= $this->watchStatus($folderId, $now);
        }
        ksort($statuses, SORT_STRING);

        return $statuses;
    }

    /**
     * @return array<string, array{folder:string, cleanupAt:int, hadState:bool, folderExists:bool, folderPaused:bool, discardedPendingEvents:bool, preservedPendingEvents:bool, clearedRestart:bool, clearedInProgress:bool, pendingEventCountBefore:int, pendingEventCountAfter:int, pendingPathsBefore:list<string>, pendingPathsAfter:list<string>, statusAfter:?array<string, mixed>}>
     */
    public function recentCleanupStatuses(?int $now = null): array
    {
        if ($now !== null) {
            $this->pruneRecentCleanups(self::clock($now));
        }
        $statuses = $this->recentCleanups;
        ksort($statuses, SORT_STRING);

        return $statuses;
    }

    public function acknowledgeRecentCleanup(string $folderId, ?int $now = null): bool
    {
        self::assertFolderId($folderId);
        if ($now !== null) {
            $this->pruneRecentCleanups(self::clock($now));
        }
        if (!isset($this->recentCleanups[$folderId])) {
            return false;
        }

        unset($this->recentCleanups[$folderId]);

        return true;
    }

    public function acknowledgeRecentCleanups(?int $now = null): int
    {
        if ($now !== null) {
            $this->pruneRecentCleanups(self::clock($now));
        }

        $count = count($this->recentCleanups);
        $this->recentCleanups = [];

        return $count;
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
            if (!$this->folderExists($folderId)) {
                $this->cleanupWatchingFolder($folderId, discardPendingEvents: true, now: $now);
                continue;
            }

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

        return $this->folderExists($folderId) && !$this->scheduler->isPaused($folderId);
    }

    private function folderExists(string $folderId): bool
    {
        self::assertFolderId($folderId);

        return in_array($folderId, $this->scheduler->folderIds(), true);
    }

    private function restartDelaySeconds(int $attempt): int
    {
        if ($this->watchRestartInitialDelaySeconds === 0 || $this->watchRestartMaxDelaySeconds === 0) {
            return 0;
        }

        $delay = $this->watchRestartInitialDelaySeconds * (2 ** max(0, $attempt - 1));

        return min($delay, $this->watchRestartMaxDelaySeconds);
    }

    /**
     * @return null|array{folder:string, lastError:string, errorAt:int, restartAttempt:int, restartDelaySeconds:int, restartAt:int, remainingSeconds:int, due:bool, scanOnWatchError:bool}
     */
    private function watchRestartStatus(string $folderId, int $now): ?array
    {
        if (!isset($this->watchRestarts[$folderId])) {
            return null;
        }

        $restart = $this->watchRestarts[$folderId];
        $remainingSeconds = max(0, $restart['restartAt'] - $now);

        return $restart + [
            'remainingSeconds' => $remainingSeconds,
            'due' => $remainingSeconds === 0,
        ];
    }

    private function pruneRecentCleanups(int $now): void
    {
        if ($this->recentCleanupTtlSeconds === 0) {
            $this->recentCleanups = [];

            return;
        }

        $oldestKeptAt = $now - $this->recentCleanupTtlSeconds;
        foreach ($this->recentCleanups as $folderId => $cleanup) {
            if ($cleanup['cleanupAt'] < $oldestKeptAt) {
                unset($this->recentCleanups[$folderId]);
            }
        }

        if (count($this->recentCleanups) <= $this->recentCleanupMaxEntries) {
            return;
        }

        uasort(
            $this->recentCleanups,
            static fn (array $left, array $right): int => ($left['cleanupAt'] <=> $right['cleanupAt'])
                ?: strcmp($left['folder'], $right['folder'])
        );

        while (count($this->recentCleanups) > $this->recentCleanupMaxEntries) {
            array_shift($this->recentCleanups);
        }
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
