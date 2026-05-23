<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class FolderScanCheckpointStore implements FolderScanCheckpointRepository
{
    /**
     * @var array<string, FolderScanCheckpointSnapshot>
     */
    private array $snapshots = [];

    public function load(string $folderId, ?int $now = null): ?FolderScanCheckpointSnapshot
    {
        self::assertFolderId($folderId);
        $now ??= time();
        self::assertClock($now);

        $snapshot = $this->snapshots[$folderId] ?? null;
        if ($snapshot !== null && $snapshot->isExpired($now)) {
            unset($this->snapshots[$folderId]);
            return null;
        }

        return $snapshot;
    }

    public function save(
        FolderScanCheckpoint $checkpoint,
        ?int $expectedRevision = null,
        ?int $now = null,
        ?int $ttlSeconds = null,
    ): FolderScanCheckpointSnapshot {
        $now ??= time();
        self::assertClock($now);
        self::assertExpectedRevision($expectedRevision);
        self::assertTtl($ttlSeconds);

        $current = $this->load($checkpoint->folderId(), $now);
        $currentRevision = $current?->revision ?? 0;
        if ($expectedRevision !== null && $expectedRevision !== $currentRevision) {
            throw self::conflict($checkpoint->folderId(), $expectedRevision, $currentRevision);
        }

        $snapshot = new FolderScanCheckpointSnapshot(
            $checkpoint,
            $currentRevision + 1,
            $now,
            $ttlSeconds === null ? $current?->expiresAt : $now + $ttlSeconds,
        );
        $this->snapshots[$checkpoint->folderId()] = $snapshot;

        return $snapshot;
    }

    public function mergeResult(
        string $folderId,
        FileInfoScanResult $result,
        ?int $expectedRevision = null,
        ?int $now = null,
        ?int $ttlSeconds = null,
    ): FolderScanCheckpointSnapshot {
        self::assertFolderId($folderId);
        $now ??= time();
        self::assertClock($now);
        self::assertExpectedRevision($expectedRevision);

        $current = $this->load($folderId, $now);
        $currentRevision = $current?->revision ?? 0;
        if ($expectedRevision !== null && $expectedRevision !== $currentRevision) {
            throw self::conflict($folderId, $expectedRevision, $currentRevision);
        }

        $checkpoint = $current === null
            ? FolderScanCheckpoint::fromResult($folderId, $result)
            : $current->checkpoint->withResult($result);

        return $this->save($checkpoint, $currentRevision, $now, $ttlSeconds);
    }

    public function delete(string $folderId, ?int $expectedRevision = null, ?int $now = null): bool
    {
        self::assertFolderId($folderId);
        $now ??= time();
        self::assertClock($now);
        self::assertExpectedRevision($expectedRevision);

        $current = $this->load($folderId, $now);
        $currentRevision = $current?->revision ?? 0;
        if ($expectedRevision !== null && $expectedRevision !== $currentRevision) {
            throw self::conflict($folderId, $expectedRevision, $currentRevision);
        }
        if ($current === null) {
            return false;
        }

        unset($this->snapshots[$folderId]);
        return true;
    }

    public function forgetExpired(?int $now = null): int
    {
        $now ??= time();
        self::assertClock($now);

        $removed = 0;
        foreach ($this->snapshots as $folderId => $snapshot) {
            if (!$snapshot->isExpired($now)) {
                continue;
            }

            unset($this->snapshots[$folderId]);
            $removed++;
        }

        return $removed;
    }

    /**
     * @return list<FolderScanCheckpointSnapshot>
     */
    public function snapshots(?int $now = null): array
    {
        $now ??= time();
        self::assertClock($now);
        $this->forgetExpired($now);

        return array_values($this->snapshots);
    }

    private static function assertFolderId(string $folderId): void
    {
        if ($folderId === '') {
            throw new \InvalidArgumentException('Folder scan checkpoint store requires a folder ID');
        }
    }

    private static function assertClock(int $now): void
    {
        if ($now < 0) {
            throw new \InvalidArgumentException('Folder scan checkpoint store clock must not be negative');
        }
    }

    private static function assertExpectedRevision(?int $expectedRevision): void
    {
        if ($expectedRevision !== null && $expectedRevision < 0) {
            throw new \InvalidArgumentException('Folder scan checkpoint expected revision must not be negative');
        }
    }

    private static function assertTtl(?int $ttlSeconds): void
    {
        if ($ttlSeconds !== null && $ttlSeconds < 0) {
            throw new \InvalidArgumentException('Folder scan checkpoint TTL must not be negative');
        }
    }

    private static function conflict(string $folderId, int $expectedRevision, int $actualRevision): FolderScanCheckpointConflictException
    {
        return new FolderScanCheckpointConflictException(
            sprintf(
                'Folder scan checkpoint revision conflict for %s: expected %d, actual %d',
                $folderId,
                $expectedRevision,
                $actualRevision,
            ),
        );
    }
}
