<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class FolderScanScheduler
{
    public const ERR_FOLDER_MISSING = 'folder missing';
    public const ERR_FOLDER_PAUSED = 'folder paused';

    private ServiceMap $folderServices;

    /**
     * @var array<string, array{folder:string, requestedAt:int, delaySeconds:int, effectiveDelaySeconds:int, scheduledAt:int}>
     */
    private array $delayedScans = [];

    public function __construct(?ServiceMap $folderServices = null)
    {
        $this->folderServices = $folderServices ?? new ServiceMap();
    }

    public function addFolder(string $folderId, FolderScanService $service, bool $running = true): void
    {
        self::assertFolderId($folderId);

        $this->folderServices->add($folderId, $service);
        if (!$running) {
            $this->folderServices->stop($folderId);
        }
    }

    public function pauseFolder(string $folderId): void
    {
        $this->requireFolder($folderId);
        $this->folderServices->stop($folderId);
        unset($this->delayedScans[$folderId]);
    }

    public function resumeFolder(string $folderId): void
    {
        $service = $this->requireFolder($folderId);
        if ($this->folderServices->isRunning($folderId)) {
            return;
        }

        $this->folderServices->add($folderId, $service);
    }

    public function removeFolder(string $folderId): bool
    {
        self::assertFolderId($folderId);
        unset($this->delayedScans[$folderId]);

        return $this->folderServices->remove($folderId);
    }

    /**
     * @return list<string>
     */
    public function folderIds(): array
    {
        return array_map(
            static fn (string|int $folderId): string => (string) $folderId,
            $this->folderServices->keys(),
        );
    }

    /**
     * @return list<string>
     */
    public function runningFolderIds(): array
    {
        return array_map(
            static fn (string|int $folderId): string => (string) $folderId,
            $this->folderServices->runningKeys(),
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{folders:array<string, array<string, mixed>>, page:array{offset:int, limit:int, total:int, returned:int, nextOffset:null|int}}
     */
    public function folderStatusPage(array $payload = [], ?int $now = null): array
    {
        $now = self::clock($now);
        $folderFilter = isset($payload['folder']) && trim((string) $payload['folder']) !== ''
            ? trim((string) $payload['folder'])
            : null;
        $stateFilter = isset($payload['state']) && trim((string) $payload['state']) !== ''
            ? strtolower(trim((string) $payload['state']))
            : null;

        $folderIds = $folderFilter === null ? $this->folderIds() : [$folderFilter];
        $statuses = [];
        foreach ($folderIds as $folderId) {
            if (!$this->folderServices->has($folderId)) {
                continue;
            }

            $state = $this->folderServices->isRunning($folderId) ? 'running' : 'paused';
            if ($stateFilter !== null && $stateFilter !== $state) {
                continue;
            }

            $service = $this->requireFolder($folderId);
            $snapshot = $service->checkpoint($now);
            $statuses[$folderId] = [
                'folder' => $folderId,
                'state' => $state,
                'running' => $state === 'running',
                'paused' => $state === 'paused',
                'scheduledScan' => $this->scheduledScanStatus($folderId, $now),
                'checkpoint' => $snapshot?->toRestStatus(),
            ];
        }
        ksort($statuses, SORT_STRING);

        $total = count($statuses);
        $offset = self::boundedInt($payload['offset'] ?? 0, min: 0, max: $total);
        $limit = self::boundedInt($payload['limit'] ?? 50, min: 1, max: 100);
        $paged = array_slice($statuses, $offset, $limit, preserve_keys: true);
        $nextOffset = $offset + count($paged);

        return [
            'folders' => $paged,
            'page' => [
                'offset' => $offset,
                'limit' => $limit,
                'total' => $total,
                'returned' => count($paged),
                'nextOffset' => $nextOffset < $total ? $nextOffset : null,
            ],
        ];
    }

    public function isPaused(string $folderId): bool
    {
        self::assertFolderId($folderId);

        return $this->folderServices->has($folderId) && !$this->folderServices->isRunning($folderId);
    }

    public function delayScan(string $folderId, int $nextSeconds, ?int $now = null): bool
    {
        self::assertFolderId($folderId);
        $now = self::clock($now);
        if (!$this->folderServices->isRunning($folderId)) {
            return false;
        }

        $effectiveDelaySeconds = max(0, $nextSeconds);
        $this->delayedScans[$folderId] = [
            'folder' => $folderId,
            'requestedAt' => $now,
            'delaySeconds' => $nextSeconds,
            'effectiveDelaySeconds' => $effectiveDelaySeconds,
            'scheduledAt' => $now + $effectiveDelaySeconds,
        ];

        return true;
    }

    /**
     * @return null|array{folder:string, requestedAt:int, delaySeconds:int, effectiveDelaySeconds:int, scheduledAt:int, remainingSeconds:int, due:bool}
     */
    public function scheduledScanStatus(string $folderId, ?int $now = null): ?array
    {
        self::assertFolderId($folderId);
        if (!isset($this->delayedScans[$folderId])) {
            return null;
        }

        return self::delayedScanStatus($this->delayedScans[$folderId], self::clock($now));
    }

    /**
     * @return array<string, array{folder:string, requestedAt:int, delaySeconds:int, effectiveDelaySeconds:int, scheduledAt:int, remainingSeconds:int, due:bool}>
     */
    public function scheduledScanStatuses(?int $now = null): array
    {
        $now = self::clock($now);
        $statuses = [];
        foreach ($this->delayedScans as $folderId => $record) {
            $statuses[$folderId] = self::delayedScanStatus($record, $now);
        }
        ksort($statuses, SORT_STRING);

        return $statuses;
    }

    /**
     * @return list<string>
     */
    public function dueDelayedFolderIds(?int $now = null): array
    {
        $now = self::clock($now);
        $due = [];
        foreach ($this->delayedScans as $folderId => $record) {
            if ($this->folderServices->isRunning($folderId) && $record['scheduledAt'] <= $now) {
                $due[] = $folderId;
            }
        }
        sort($due, SORT_STRING);

        return $due;
    }

    /**
     * @param null|callable(FolderScanProgress): void $progressLogger
     * @param null|callable(string, \Throwable, string): void $errorLogger
     * @param null|callable(?string): bool $shouldCancel
     * @param null|callable(string, array{description:string, sub:string, error:string}): void $failureLogger
     */
    public function scanDueDelayedFolders(
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

        foreach ($this->dueDelayedFolderIds($now) as $folderId) {
            unset($this->delayedScans[$folderId]);
            try {
                $snapshots[$folderId] = $this->scanFolder(
                    $folderId,
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
            }
        }

        return new FolderScanSchedulerResult($snapshots, $errors);
    }

    /**
     * @param array<string, list<string>> $subdirsByFolder
     * @param null|callable(FolderScanProgress): void $progressLogger
     * @param null|callable(string, \Throwable, string): void $errorLogger
     * @param null|callable(?string): bool $shouldCancel
     * @param null|callable(string, array{description:string, sub:string, error:string}): void $failureLogger
     */
    public function scanFolders(
        array $subdirsByFolder = [],
        ?IgnoreMatcher $ignoreMatcher = null,
        bool $hashBlocks = false,
        ?int $blockSize = null,
        ?callable $progressLogger = null,
        ?callable $errorLogger = null,
        ?callable $shouldCancel = null,
        ?callable $failureLogger = null,
        ?int $now = null,
    ): FolderScanSchedulerResult {
        $folderIds = $this->folderIds();
        $snapshots = [];
        $errors = [];

        foreach ($folderIds as $folderId) {
            try {
                $snapshots[$folderId] = $this->scanFolderSubdirs(
                    $folderId,
                    self::subdirsForFolder($subdirsByFolder, $folderId),
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
            }
        }

        return new FolderScanSchedulerResult($snapshots, $errors);
    }

    /**
     * @param null|callable(FolderScanProgress): void $progressLogger
     * @param null|callable(string, \Throwable, string): void $errorLogger
     * @param null|callable(?string): bool $shouldCancel
     * @param null|callable(string, array{description:string, sub:string, error:string}): void $failureLogger
     */
    public function scanFolder(
        string $folderId,
        ?IgnoreMatcher $ignoreMatcher = null,
        bool $hashBlocks = false,
        ?int $blockSize = null,
        ?callable $progressLogger = null,
        ?callable $errorLogger = null,
        ?callable $shouldCancel = null,
        ?callable $failureLogger = null,
        ?int $now = null,
    ): FolderScanCheckpointSnapshot {
        return $this->scanFolderSubdirs(
            $folderId,
            [],
            $ignoreMatcher,
            $hashBlocks,
            $blockSize,
            $progressLogger,
            $errorLogger,
            $shouldCancel,
            $failureLogger,
            $now,
        );
    }

    /**
     * @param list<string> $subs
     * @param null|callable(FolderScanProgress): void $progressLogger
     * @param null|callable(string, \Throwable, string): void $errorLogger
     * @param null|callable(?string): bool $shouldCancel
     * @param null|callable(string, array{description:string, sub:string, error:string}): void $failureLogger
     */
    public function scanFolderSubdirs(
        string $folderId,
        array $subs = [],
        ?IgnoreMatcher $ignoreMatcher = null,
        bool $hashBlocks = false,
        ?int $blockSize = null,
        ?callable $progressLogger = null,
        ?callable $errorLogger = null,
        ?callable $shouldCancel = null,
        ?callable $failureLogger = null,
        ?int $now = null,
    ): FolderScanCheckpointSnapshot {
        $service = $this->requireRunningFolder($folderId);

        return $service->scan(
            $subs,
            $ignoreMatcher,
            $hashBlocks,
            $blockSize,
            $progressLogger,
            $errorLogger,
            $shouldCancel,
            $failureLogger,
            $now,
        );
    }

    private function requireFolder(string $folderId): FolderScanService
    {
        self::assertFolderId($folderId);
        if (!$this->folderServices->has($folderId)) {
            throw self::folderMissing($folderId);
        }

        $service = $this->folderServices->get($folderId);
        if (!$service instanceof FolderScanService) {
            throw new \RuntimeException('folder scan service registry contains an invalid service for ' . $folderId);
        }

        return $service;
    }

    private function requireRunningFolder(string $folderId): FolderScanService
    {
        $service = $this->requireFolder($folderId);
        if (!$this->folderServices->isRunning($folderId)) {
            throw self::folderPaused($folderId);
        }

        return $service;
    }

    /**
     * @param array<string, list<string>> $subdirsByFolder
     * @return list<string>
     */
    private static function subdirsForFolder(array $subdirsByFolder, string $folderId): array
    {
        if (!array_key_exists($folderId, $subdirsByFolder)) {
            return [];
        }

        $subs = $subdirsByFolder[$folderId];
        if (!is_array($subs)) {
            throw new \InvalidArgumentException('Folder scan scheduler subdirs must be arrays keyed by folder ID');
        }

        return array_values($subs);
    }

    private static function assertFolderId(string $folderId): void
    {
        if ($folderId === '') {
            throw new \InvalidArgumentException('Folder scan scheduler requires a folder ID');
        }
    }

    private static function folderMissing(string $folderId): \RuntimeException
    {
        return new \RuntimeException(self::ERR_FOLDER_MISSING . ': ' . $folderId);
    }

    private static function folderPaused(string $folderId): \RuntimeException
    {
        return new \RuntimeException(self::ERR_FOLDER_PAUSED . ': ' . $folderId);
    }

    private static function clock(?int $now): int
    {
        $now ??= time();
        if ($now < 0) {
            throw new \InvalidArgumentException('Folder scan scheduler clock must not be negative');
        }

        return $now;
    }

    private static function boundedInt(mixed $value, int $min, int $max): int
    {
        $int = is_numeric($value) ? (int) $value : $min;
        return max($min, min($max, $int));
    }

    /**
     * @param array{folder:string, requestedAt:int, delaySeconds:int, effectiveDelaySeconds:int, scheduledAt:int} $record
     * @return array{folder:string, requestedAt:int, delaySeconds:int, effectiveDelaySeconds:int, scheduledAt:int, remainingSeconds:int, due:bool}
     */
    private static function delayedScanStatus(array $record, int $now): array
    {
        $remainingSeconds = max(0, $record['scheduledAt'] - $now);

        return [
            'folder' => $record['folder'],
            'requestedAt' => $record['requestedAt'],
            'delaySeconds' => $record['delaySeconds'],
            'effectiveDelaySeconds' => $record['effectiveDelaySeconds'],
            'scheduledAt' => $record['scheduledAt'],
            'remainingSeconds' => $remainingSeconds,
            'due' => $remainingSeconds === 0,
        ];
    }
}
