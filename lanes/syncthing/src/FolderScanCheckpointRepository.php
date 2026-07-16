<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

interface FolderScanCheckpointRepository
{
    public function load(string $folderId, ?int $now = null): ?FolderScanCheckpointSnapshot;

    public function save(
        FolderScanCheckpoint $checkpoint,
        ?int $expectedRevision = null,
        ?int $now = null,
        ?int $ttlSeconds = null,
    ): FolderScanCheckpointSnapshot;

    public function mergeResult(
        string $folderId,
        FileInfoScanResult $result,
        ?int $expectedRevision = null,
        ?int $now = null,
        ?int $ttlSeconds = null,
    ): FolderScanCheckpointSnapshot;

    public function delete(string $folderId, ?int $expectedRevision = null, ?int $now = null): bool;
}
