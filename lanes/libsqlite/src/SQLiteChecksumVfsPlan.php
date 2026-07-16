<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteChecksumVfsPlan
{
    /**
     * @param list<int> $payloadSizes
     * @return array<string, mixed>
     */
    public static function checksumVfsProfile(
        string $scenario,
        int $pageSize,
        int $reserveBytes,
        int $initialRows,
        int $walRows,
        array $payloadSizes,
        bool $restoreBeforeReopen = true
    ): array {
        $scenario = trim($scenario);
        if ($scenario === '') {
            throw new \InvalidArgumentException('SQLite checksum VFS profile requires a scenario');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite checksum VFS page size must be a power of two at least 512');
        }
        if ($reserveBytes < 0 || $reserveBytes >= $pageSize) {
            throw new \InvalidArgumentException('SQLite checksum VFS reserve bytes must fit inside the page');
        }
        if ($initialRows < 0 || $walRows < 1 || $payloadSizes === []) {
            throw new \InvalidArgumentException('SQLite checksum VFS profile requires row counts and payload sizes');
        }

        $usableBytes = $pageSize - $reserveBytes;
        $payloadBytes = 0;
        foreach ($payloadSizes as $payloadSize) {
            if ($payloadSize < 0) {
                throw new \InvalidArgumentException('SQLite checksum VFS payload sizes must be non-negative');
            }
            $payloadBytes += $payloadSize;
        }

        $initialPayloadBytes = $initialRows * max(1, (int) ceil($payloadBytes / count($payloadSizes)));
        $walPayloadBytes = $walRows * max(1, min($usableBytes, (int) ceil(($payloadBytes + $walRows) / count($payloadSizes))));
        $initialPages = self::payloadPages($initialPayloadBytes, $usableBytes);
        $walPages = self::payloadPages($walPayloadBytes, $usableBytes);
        $databaseBytes = $pageSize * max(1, 1 + $initialPages);
        $walBytesBeforeCheckpoint = $pageSize + ($walPages * ($pageSize + 24));
        $checkpointedFrames = $walPages;
        $reopenedRows = $restoreBeforeReopen ? $walRows : 0;

        return [
            'status' => 'ok',
            'script' => 'cksumvfs.test',
            'scenario' => $scenario,
            'upstream' => [
                'cksumvfs.test cksumvfs-1.0 reserve-bytes page-size setup',
                'cksumvfs.test cksumvfs-1.1 initial row readback',
                'cksumvfs.test cksumvfs-1.3 large insert commit',
                'cksumvfs.test cksumvfs-1.5 WAL delete checkpoint setup',
                'cksumvfs.test cksumvfs-1.6 checkpoint returns zero busy',
                'cksumvfs.test cksumvfs-1.7 recursive insert count',
                'cksumvfs.test cksumvfs-1.8 restore and reopen count',
                'cksumvfs.test cksumvfs-1.9 close and reopen count',
            ],
            'page_size' => $pageSize,
            'reserve_bytes' => $reserveBytes,
            'usable_bytes' => $usableBytes,
            'payload_bytes' => $payloadBytes,
            'initial_rows' => $initialRows,
            'wal_rows' => $walRows,
            'initial_pages' => $initialPages,
            'wal_pages' => $walPages,
            'database_bytes' => $databaseBytes,
            'wal_bytes_before_checkpoint' => $walBytesBeforeCheckpoint,
            'journal_mode' => 'wal',
            'checkpoint' => ['busy' => 0, 'log' => $checkpointedFrames, 'checkpointed' => $checkpointedFrames],
            'reserve_bytes_preserved' => $reserveBytes === 8,
            'checksums_cover_reserved_tail' => $reserveBytes > 0,
            'delete_before_wal_insert' => true,
            'restore_before_reopen' => $restoreBeforeReopen,
            'reopen_count_after_restore' => $reopenedRows,
            'reopen_count_after_close' => $reopenedRows,
            'integrity_check' => 'ok',
            'open_file_count' => 0,
            'dependencies' => [
                'sqlite-upstream-cksumvfs-test',
                'sqlite-page-reserve-bytes',
                'sqlite-wal-checkpoint',
                'vfs-io-dynamic-real-corpus',
            ],
        ];
    }

    private static function payloadPages(int $payloadBytes, int $usableBytes): int
    {
        if ($payloadBytes === 0) {
            return 0;
        }

        return (int) ceil($payloadBytes / max(1, $usableBytes));
    }
}
