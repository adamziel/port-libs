<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class FolderScanService
{
    public function __construct(
        private readonly string $folderId,
        private readonly FileInfoScanner $scanner,
        private readonly FolderScanCheckpointRepository $store,
        private readonly ?int $ttlSeconds = 86400,
    ) {
        if ($this->folderId === '') {
            throw new \InvalidArgumentException('Folder scan service requires a folder ID');
        }
        if ($this->ttlSeconds !== null && $this->ttlSeconds < 0) {
            throw new \InvalidArgumentException('Folder scan checkpoint TTL must not be negative');
        }
    }

    /**
     * @param list<string> $subs
     * @param null|callable(FolderScanProgress): void $progressLogger
     * @param null|callable(string, \Throwable, string): void $errorLogger
     * @param null|callable(?string): bool $shouldCancel
     * @param null|callable(string, array{description:string, sub:string, error:string}): void $failureLogger
     */
    public function scan(
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
        $now ??= time();
        if ($now < 0) {
            throw new \InvalidArgumentException('Folder scan service clock must not be negative');
        }

        $snapshot = $this->store->load($this->folderId, $now);
        $expectedRevision = $snapshot?->revision ?? 0;
        $checkpoint = $snapshot?->checkpoint;
        $scanSubs = $subs === [] && $checkpoint?->cancelled()
            ? $checkpoint->resumeSubs()
            : $subs;

        $result = $this->scanner->walkWithCheckpoint(
            $scanSubs,
            $ignoreMatcher,
            $hashBlocks,
            $blockSize,
            $checkpoint?->resumeCurrentFiles() ?? [],
            $progressLogger,
            $this->folderId,
            $errorLogger,
            $shouldCancel,
            $failureLogger,
            new FolderScanEventCollector($this->folderId),
        );

        $merged = $checkpoint === null
            ? FolderScanCheckpoint::fromResult($this->folderId, $result)
            : $checkpoint->withResult($result);

        return $this->store->save($merged, $expectedRevision, $now, $this->ttlSeconds);
    }

    public function checkpoint(?int $now = null): ?FolderScanCheckpointSnapshot
    {
        return $this->store->load($this->folderId, $now);
    }

    public function clear(?int $expectedRevision = null, ?int $now = null): bool
    {
        return $this->store->delete($this->folderId, $expectedRevision, $now);
    }
}
