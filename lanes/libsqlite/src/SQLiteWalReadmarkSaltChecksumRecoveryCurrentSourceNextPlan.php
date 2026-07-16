<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalReadmarkSaltChecksumRecoveryCurrentSourceNextPlan
{
    /**
     * @param list<int> $pageNumbers
     * @return array<string, mixed>
     */
    public static function currentSourceNext(
        string $currentWalBytes,
        SQLiteShmIndex $currentShm,
        string $nextWalBytes,
        SQLiteShmIndex $nextShm,
        string $databaseBytes,
        array $pageNumbers,
        ?int $databasePageSize = null
    ): array {
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL readmark salt recovery requires at least one page number');
        }
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite WAL readmark salt recovery pages must be positive integers');
            }
        }

        $currentBoundary = SQLiteWal::transactionRecoveryBoundary($currentWalBytes, $databaseBytes, $databasePageSize);
        $currentWal = $currentBoundary['committed_wal'];
        $currentDatabaseBytes = $currentBoundary['checkpoint_database_bytes'] ?? $databaseBytes;
        $nextBoundary = SQLiteWal::transactionRecoveryBoundary($nextWalBytes, $currentDatabaseBytes, $databasePageSize);
        $nextWal = $nextBoundary['committed_wal'];
        $nextDatabaseBytes = $nextBoundary['checkpoint_database_bytes'] ?? $currentDatabaseBytes;
        $pageSize = self::pageSize($nextWal, $databasePageSize, $currentDatabaseBytes);

        $currentReadmarkRecovery = $currentShm->recoverReadMarksFromWal($currentWal);
        $nextReadmarkRecovery = $nextShm->recoverReadMarksFromWal($nextWal);
        $currentReaderEndFrame = self::oldestReaderFrame($currentReadmarkRecovery['current_reader_frames'])
            ?? (int) $currentBoundary['committed_frame_count'];
        $nextReaderEndFrame = self::oldestReaderFrame($nextReadmarkRecovery['current_reader_frames'])
            ?? (int) $nextBoundary['committed_frame_count'];

        $currentReader = self::readerRows($currentWal, $currentDatabaseBytes, $pageNumbers, $currentReaderEndFrame, $pageSize);
        $nextReader = self::readerRows($nextWal, $nextDatabaseBytes, $pageNumbers, $nextReaderEndFrame, $pageSize);
        $latestNextReader = self::readerRows($nextWal, $nextDatabaseBytes, $pageNumbers, (int) $nextBoundary['committed_frame_count'], $pageSize);

        $currentSalt = [$currentWal->header->salt1, $currentWal->header->salt2];
        $nextSalt = [$nextWal->header->salt1, $nextWal->header->salt2];
        $saltChanged = $currentSalt !== $nextSalt;
        $nextRebuiltForSalt = $nextReadmarkRecovery['status'] === 'rebuilt' && !$nextReadmarkRecovery['salt_matches_wal'];

        return [
            'status' => $nextRebuiltForSalt ? 'readmark_salt_rebuilt_next115' : 'readmark_salt_recovered_next115',
            'reason' => self::reason($saltChanged, $currentReadmarkRecovery, $nextReadmarkRecovery),
            'current_source' => [
                'status' => $currentBoundary['status'],
                'reason' => $currentBoundary['reason'],
                'salt' => $currentSalt,
                'committed_frame_count' => $currentBoundary['committed_frame_count'],
                'valid_frame_count' => $currentBoundary['valid_frame_count'],
                'recovery_end_offset' => $currentBoundary['recovery_end_offset'],
                'committed_end_offset' => $currentBoundary['committed_end_offset'],
                'readmark_recovery' => $currentReadmarkRecovery,
            ],
            'next_source' => [
                'status' => $nextBoundary['status'],
                'reason' => $nextBoundary['reason'],
                'salt' => $nextSalt,
                'committed_frame_count' => $nextBoundary['committed_frame_count'],
                'valid_frame_count' => $nextBoundary['valid_frame_count'],
                'total_frame_slots' => $nextBoundary['total_frame_slots'],
                'first_invalid_frame' => $nextBoundary['first_invalid_frame'],
                'discarded_valid_tail_frame_count' => $nextBoundary['discarded_valid_tail_frame_count'],
                'discarded_corrupt_tail_frame_count' => $nextBoundary['discarded_corrupt_tail_frame_count'],
                'recovery_end_offset' => $nextBoundary['recovery_end_offset'],
                'committed_end_offset' => $nextBoundary['committed_end_offset'],
                'started_from_current_checkpoint' => ($currentBoundary['checkpoint_database_bytes'] ?? null) !== null,
                'readmark_recovery' => $nextReadmarkRecovery,
            ],
            'salt_changed' => $saltChanged,
            'current_reader_end_frame' => $currentReaderEndFrame,
            'next_reader_end_frame' => $nextReaderEndFrame,
            'latest_next_reader_end_frame' => $nextBoundary['committed_frame_count'],
            'current_preserved_slots' => $currentReadmarkRecovery['preserved_slots'],
            'current_discarded_slots' => $currentReadmarkRecovery['discarded_slots'],
            'next_preserved_slots' => $nextReadmarkRecovery['preserved_slots'],
            'next_discarded_slots' => $nextReadmarkRecovery['discarded_slots'],
            'next_rebuilt_for_salt' => $nextRebuiltForSalt,
            'current_next_read_marks' => $currentReadmarkRecovery['next_read_marks'],
            'next_generation_read_marks' => $nextReadmarkRecovery['next_read_marks'],
            'current_reader' => $currentReader,
            'next_reader' => $nextReader,
            'latest_next_reader' => $latestNextReader,
            'current_reader_sources' => self::column($currentReader, 'source'),
            'next_reader_sources' => self::column($nextReader, 'source'),
            'latest_next_reader_sources' => self::column($latestNextReader, 'source'),
            'current_reader_frame_indexes' => self::column($currentReader, 'frame_index'),
            'next_reader_frame_indexes' => self::column($nextReader, 'frame_index'),
            'latest_next_reader_frame_indexes' => self::column($latestNextReader, 'frame_index'),
            'current_reader_errors' => self::errors($currentReader),
            'next_reader_errors' => self::errors($nextReader),
            'latest_next_reader_errors' => self::errors($latestNextReader),
            'current_reader_keeps_recovered_snapshot' => self::images($currentReader) !== self::images($latestNextReader),
            'next_reader_rebuilt_to_database_or_latest' => $nextRebuiltForSalt && $nextReaderEndFrame === (int) $nextBoundary['committed_frame_count'],
            'operations' => [
                [
                    'action' => 'recover-current-wal',
                    'reason' => 'recover_current_wal_before_preserving_readmarks',
                    'frame_count' => $currentBoundary['committed_frame_count'],
                ],
                [
                    'action' => 'recover-current-readmarks',
                    'reason' => $currentReadmarkRecovery['reason'],
                    'preserved_slots' => $currentReadmarkRecovery['preserved_slots'],
                ],
                [
                    'action' => 'recover-next-wal',
                    'reason' => 'recover_restarted_wal_before_next_readmarks',
                    'frame_count' => $nextBoundary['committed_frame_count'],
                ],
                [
                    'action' => 'recover-next-readmarks',
                    'reason' => $nextReadmarkRecovery['reason'],
                    'preserved_slots' => $nextReadmarkRecovery['preserved_slots'],
                    'discarded_slots' => $nextReadmarkRecovery['discarded_slots'],
                ],
            ],
            'dependencies' => array_values(array_unique(array_merge(
                $currentBoundary['dependencies'],
                $nextBoundary['dependencies'],
                $currentReadmarkRecovery['dependencies'],
                $nextReadmarkRecovery['dependencies'],
                ['sqlite-wal-readmark-salt-checksum-recovery-current-source-next115']
            ))),
        ];
    }

    private static function reason(bool $saltChanged, array $currentReadmarkRecovery, array $nextReadmarkRecovery): string
    {
        if (!$saltChanged) {
            return 'readmark_recovery_same_salt';
        }
        if ($nextReadmarkRecovery['status'] === 'rebuilt' && !$nextReadmarkRecovery['salt_matches_wal']) {
            return 'next_generation_shm_salt_rebuilt_after_checksum_recovery';
        }
        if ($currentReadmarkRecovery['preserved_slots'] !== [] && $nextReadmarkRecovery['preserved_slots'] !== []) {
            return 'current_and_next_generation_readmarks_preserved';
        }

        return 'readmarks_recovered_after_wal_salt_change';
    }

    private static function pageSize(SQLiteWal $wal, ?int $databasePageSize, string $databaseBytes): int
    {
        if ($wal->header->pageSize !== 0) {
            return $wal->header->pageSize;
        }
        if ($databasePageSize !== null) {
            return $databasePageSize;
        }

        return SQLiteHeader::parse($databaseBytes)->pageSize;
    }

    /**
     * @param list<int> $frames
     */
    private static function oldestReaderFrame(array $frames): ?int
    {
        return $frames === [] ? null : min($frames);
    }

    /**
     * @param list<int> $pageNumbers
     * @return list<array<string, mixed>>
     */
    private static function readerRows(SQLiteWal $wal, string $databaseBytes, array $pageNumbers, int $endFrame, int $pageSize): array
    {
        $rows = [];
        foreach ($pageNumbers as $pageNumber) {
            $rows[] = $endFrame === 0
                ? self::databaseRow($databaseBytes, $pageSize, $pageNumber)
                : self::walReaderRow($wal, $databaseBytes, $pageNumber, $endFrame);
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private static function walReaderRow(SQLiteWal $wal, string $databaseBytes, int $pageNumber, int $endFrame): array
    {
        try {
            return $wal->readerSnapshotPageImage($databaseBytes, $pageNumber, $endFrame);
        } catch (\OutOfBoundsException $e) {
            $snapshot = $wal->readerSnapshot($databaseBytes, $endFrame);

            return [
                'page_number' => $pageNumber,
                'source' => 'missing',
                'frame_index' => null,
                'database_offset' => null,
                'image' => null,
                'snapshot_end_frame' => $snapshot['end_frame'],
                'snapshot_commit_frame' => $snapshot['commit_frame']?->index,
                'database_page_count' => $snapshot['database_page_count'],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private static function databaseRow(string $databaseBytes, int $pageSize, int $pageNumber): array
    {
        $offset = ($pageNumber - 1) * $pageSize;
        if ($offset < 0 || $offset + $pageSize > strlen($databaseBytes)) {
            return [
                'page_number' => $pageNumber,
                'source' => 'missing',
                'frame_index' => null,
                'image' => null,
                'error' => 'database_page_missing',
            ];
        }

        return [
            'page_number' => $pageNumber,
            'source' => 'database',
            'frame_index' => null,
            'image' => substr($databaseBytes, $offset, $pageSize),
        ];
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<mixed>
     */
    private static function column(array $rows, string $column): array
    {
        return array_map(static fn (array $row): mixed => $row[$column] ?? null, $rows);
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string>
     */
    private static function errors(array $rows): array
    {
        $errors = [];
        foreach ($rows as $row) {
            if (isset($row['error'])) {
                $errors[] = (string) $row['error'];
            }
        }

        return $errors;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<string|null>
     */
    private static function images(array $rows): array
    {
        return array_map(static fn (array $row): ?string => isset($row['image']) && is_string($row['image']) ? $row['image'] : null, $rows);
    }
}
