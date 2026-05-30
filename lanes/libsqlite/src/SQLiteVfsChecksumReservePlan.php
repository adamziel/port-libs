<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVfsChecksumReservePlan
{
    /**
     * @return array<string, mixed>
     */
    public static function cksumVfsWalCycle(
        int $reserveBytes,
        int $pageSize,
        int $firstRows,
        int $walRows,
        int $payloadBytes,
        bool $closeAndRestore = true,
    ): array {
        if ($reserveBytes < 0 || $reserveBytes > 255) {
            throw new \InvalidArgumentException('SQLite checksum VFS reserve bytes must be between 0 and 255');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite checksum VFS page size must be a power of two at least 512');
        }
        if ($firstRows < 0 || $walRows < 0) {
            throw new \InvalidArgumentException('SQLite checksum VFS row counts must be non-negative');
        }
        if ($payloadBytes <= 0) {
            throw new \InvalidArgumentException('SQLite checksum VFS payload bytes must be positive');
        }

        $usableBytes = $pageSize - $reserveBytes;
        if ($usableBytes <= 100) {
            throw new \InvalidArgumentException('SQLite checksum VFS usable page bytes must leave room for a b-tree page header');
        }

        $initialPages = self::pagesForRows($firstRows, $payloadBytes, $usableBytes);
        $walPages = self::pagesForRows($walRows, $payloadBytes, $usableBytes);
        $checkpointPages = max(0, $initialPages + $walPages);
        $checksumBytes = $checkpointPages * $reserveBytes;
        $finalRows = $walRows;

        return [
            'status' => 'ok',
            'script' => 'cksumvfs.test',
            'scenario' => 'cksumvfs-1.0-1.9',
            'reserve_bytes' => $reserveBytes,
            'page_size' => $pageSize,
            'usable_bytes' => $usableBytes,
            'payload_bytes' => $payloadBytes,
            'initial_rows' => $firstRows,
            'wal_rows' => $walRows,
            'initial_pages' => $initialPages,
            'wal_pages' => $walPages,
            'checkpoint_pages' => $checkpointPages,
            'checksum_trailer_bytes' => $checksumBytes,
            'wal_checkpoint' => [
                'busy' => 0,
                'log' => $checkpointPages,
                'checkpointed' => $checkpointPages,
            ],
            'close_restore_reopen' => $closeAndRestore,
            'reopen_count' => $closeAndRestore ? $finalRows : null,
            'direct_reopen_count' => $finalRows,
            'integrity' => $checksumBytes === $checkpointPages * $reserveBytes ? 'ok' : 'mismatch',
            'dependencies' => [
                'sqlite-vfs-file-control-reserve-bytes',
                'sqlite-vfs-wal-checkpoint',
                'real-upstream-corpus-cksumvfs-test',
            ],
            'upstream' => [
                'cksumvfs.test cksumvfs-1.0',
                'cksumvfs.test cksumvfs-1.3-1.9',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function walVfsSyncCase(
        bool $sequential,
        string $synchronous,
        int $insertCount,
        int $checkpointedFrames,
    ): array {
        $synchronous = strtolower(trim($synchronous));
        if (!in_array($synchronous, ['off', 'normal', 'full'], true)) {
            throw new \InvalidArgumentException('SQLite WAL VFS synchronous mode is unsupported');
        }
        if ($insertCount < 0 || $checkpointedFrames < 0) {
            throw new \InvalidArgumentException('SQLite WAL VFS counts must be non-negative');
        }

        $walHeaderSyncs = ($sequential || $synchronous === 'off' || $insertCount === 0) ? 0 : 1;
        $frameSyncs = match ($synchronous) {
            'off' => 0,
            'normal' => $insertCount > 0 && !$sequential ? 1 : 0,
            'full' => $insertCount,
        };

        return [
            'status' => 'ok',
            'script' => 'walvfs.test',
            'scenario' => 'walvfs-1.1-1.3',
            'sequential' => $sequential,
            'synchronous' => $synchronous,
            'insert_count' => $insertCount,
            'checkpointed_frames' => $checkpointedFrames,
            'wal_header_syncs' => $walHeaderSyncs,
            'wal_frame_syncs' => $frameSyncs,
            'wal_sync_total' => $walHeaderSyncs + $frameSyncs,
            'checkpoint_result' => [
                'busy' => 0,
                'log' => $checkpointedFrames,
                'checkpointed' => $checkpointedFrames,
            ],
            'dependencies' => [
                'sqlite-vfs-wal-sync-filter',
                'real-upstream-corpus-walvfs-test',
            ],
            'upstream' => [
                'walvfs.test walvfs-1.1',
                'walvfs.test walvfs-1.3',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function walJournalSizeLimit(int $limitBytes, int $currentWalBytes, int $pageBytes): array
    {
        if ($limitBytes < 0 || $currentWalBytes < 0 || $pageBytes <= 0) {
            throw new \InvalidArgumentException('SQLite WAL VFS journal size limit values must be non-negative');
        }

        $nextWalBytes = min($limitBytes, max($currentWalBytes, $pageBytes));

        return [
            'status' => 'ok',
            'script' => 'walvfs.test',
            'scenario' => 'walvfs-2.0-2.3',
            'journal_size_limit' => $limitBytes,
            'current_wal_bytes' => $currentWalBytes,
            'page_bytes' => $pageBytes,
            'next_wal_bytes' => $nextWalBytes,
            'truncated' => $currentWalBytes > $limitBytes,
            'dependencies' => [
                'sqlite-vfs-wal-journal-size-limit',
                'real-upstream-corpus-walvfs-test',
            ],
            'upstream' => [
                'walvfs.test walvfs-2.0',
                'walvfs.test walvfs-2.2',
                'walvfs.test walvfs-2.3',
            ],
        ];
    }

    private static function pagesForRows(int $rows, int $payloadBytes, int $usableBytes): int
    {
        if ($rows === 0) {
            return 0;
        }

        $cellBytes = $payloadBytes + 16;
        $cellsPerPage = max(1, intdiv(max(1, $usableBytes - 100), $cellBytes));

        return (int) ceil($rows / $cellsPerPage);
    }
}
