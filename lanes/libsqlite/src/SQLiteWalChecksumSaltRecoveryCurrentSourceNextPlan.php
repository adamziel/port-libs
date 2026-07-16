<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalChecksumSaltRecoveryCurrentSourceNextPlan
{
    /**
     * @param list<int> $pageNumbers
     * @return array<string, mixed>
     */
    public static function currentSourceNext(
        string $currentWalBytes,
        string $nextWalBytes,
        string $databaseBytes,
        array $pageNumbers,
        ?int $databasePageSize = null
    ): array {
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL current-source salt recovery requires at least one page number');
        }
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite WAL current-source salt recovery pages must be positive integers');
            }
        }

        $currentBoundary = SQLiteWal::transactionRecoveryBoundary($currentWalBytes, $databaseBytes, $databasePageSize);
        $currentWal = $currentBoundary['committed_wal'];
        $currentDatabaseBytes = $currentBoundary['checkpoint_database_bytes'] ?? $databaseBytes;
        $nextBoundary = SQLiteWal::transactionRecoveryBoundary($nextWalBytes, $currentDatabaseBytes, $databasePageSize);
        $nextWal = $nextBoundary['committed_wal'];
        $nextDatabaseBytes = $nextBoundary['checkpoint_database_bytes'] ?? $currentDatabaseBytes;

        $currentPageSize = self::pageSize($currentWal, $databasePageSize, $databaseBytes);
        $nextPageSize = self::pageSize($nextWal, $databasePageSize, $currentDatabaseBytes);
        if ($currentPageSize !== $nextPageSize) {
            throw new \InvalidArgumentException('SQLite WAL current-source salt recovery requires matching page sizes');
        }

        $currentReader = self::readerRows($currentWal, $currentDatabaseBytes, $pageNumbers, (int) $currentBoundary['committed_frame_count'], $currentPageSize);
        $nextReader = self::readerRows($nextWal, $nextDatabaseBytes, $pageNumbers, (int) $nextBoundary['committed_frame_count'], $nextPageSize);
        $currentSalt = [$currentWal->header->salt1, $currentWal->header->salt2];
        $nextSalt = [$nextWal->header->salt1, $nextWal->header->salt2];
        $saltChanged = $currentSalt !== $nextSalt;
        $staleSaltTail = self::staleSaltTailRows($nextWalBytes, $nextWal->header, $nextPageSize, (int) $nextBoundary['valid_frame_count'], (int) $nextBoundary['total_frame_slots']);
        $nextStartedFromCurrentCheckpoint = ($currentBoundary['checkpoint_database_bytes'] ?? null) !== null;
        $nextCheckpointed = ($nextBoundary['checkpoint_database_bytes'] ?? null) !== null;

        return [
            'status' => $saltChanged ? 'current_source_salt_recovered_next106' : 'current_source_same_salt_next106',
            'reason' => self::reason($saltChanged, $staleSaltTail, $nextBoundary),
            'current_source' => [
                'status' => $currentBoundary['status'],
                'reason' => $currentBoundary['reason'],
                'salt' => $currentSalt,
                'checkpoint_sequence' => $currentWal->header->checkpointSequence,
                'valid_frame_count' => $currentBoundary['valid_frame_count'],
                'committed_frame_count' => $currentBoundary['committed_frame_count'],
                'recovery_end_offset' => $currentBoundary['recovery_end_offset'],
                'committed_end_offset' => $currentBoundary['committed_end_offset'],
                'database_bytes_source' => $nextStartedFromCurrentCheckpoint ? 'current_checkpoint_database' : 'input_database',
            ],
            'next_source' => [
                'status' => $nextBoundary['status'],
                'reason' => $nextBoundary['reason'],
                'salt' => $nextSalt,
                'checkpoint_sequence' => $nextWal->header->checkpointSequence,
                'valid_frame_count' => $nextBoundary['valid_frame_count'],
                'committed_frame_count' => $nextBoundary['committed_frame_count'],
                'total_frame_slots' => $nextBoundary['total_frame_slots'],
                'first_invalid_frame' => $nextBoundary['first_invalid_frame'],
                'discarded_valid_tail_frame_count' => $nextBoundary['discarded_valid_tail_frame_count'],
                'discarded_corrupt_tail_frame_count' => $nextBoundary['discarded_corrupt_tail_frame_count'],
                'recovery_end_offset' => $nextBoundary['recovery_end_offset'],
                'committed_end_offset' => $nextBoundary['committed_end_offset'],
                'started_from_current_checkpoint' => $nextStartedFromCurrentCheckpoint,
                'checkpointed_database' => $nextCheckpointed,
            ],
            'salt_changed' => $saltChanged,
            'stale_salt_tail_frame_count' => count($staleSaltTail),
            'stale_salt_tail_frames' => $staleSaltTail,
            'current_reader' => $currentReader,
            'next_reader' => $nextReader,
            'current_reader_sources' => self::column($currentReader, 'source'),
            'next_reader_sources' => self::column($nextReader, 'source'),
            'current_reader_frame_indexes' => self::column($currentReader, 'frame_index'),
            'next_reader_frame_indexes' => self::column($nextReader, 'frame_index'),
            'current_reader_errors' => self::errors($currentReader),
            'next_reader_errors' => self::errors($nextReader),
            'images_changed' => self::images($currentReader) !== self::images($nextReader),
            'operations' => [
                [
                    'action' => 'recover-current-source',
                    'reason' => 'recover_current_wal_committed_prefix_before_next_source',
                    'frame_count' => $currentBoundary['committed_frame_count'],
                ],
                [
                    'action' => 'recover-next-source',
                    'reason' => $staleSaltTail === [] ? 'recover_next_wal_committed_prefix' : 'discard_stale_salt_tail_after_restart',
                    'frame_count' => $nextBoundary['committed_frame_count'],
                ],
                [
                    'action' => 'compare-reader-images',
                    'reason' => 'verify_current_reader_to_next_reader_sources',
                    'page_count' => count($pageNumbers),
                ],
            ],
            'dependencies' => array_values(array_unique(array_merge(
                $currentBoundary['dependencies'],
                $nextBoundary['dependencies'],
                ['sqlite-wal-checksum-salt-recovery-current-source-next106']
            ))),
        ];
    }

    private static function reason(bool $saltChanged, array $staleSaltTail, array $nextBoundary): string
    {
        if (!$saltChanged) {
            return 'wal_salt_unchanged_current_source';
        }
        if ($staleSaltTail !== []) {
            return 'next_restarted_wal_discarded_stale_current_source_salt_tail';
        }
        if (($nextBoundary['discarded_corrupt_tail_frame_count'] ?? 0) > 0) {
            return 'next_restarted_wal_discarded_corrupt_tail';
        }

        return 'next_restarted_wal_new_salt_from_current_source';
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
     * @return list<array{frame_index:int,offset:int,frame_salt:array{0:int,1:int},expected_salt:array{0:int,1:int},reason:string}>
     */
    private static function staleSaltTailRows(string $walBytes, SQLiteWalHeader $header, int $pageSize, int $validFrameCount, int $totalFrameSlots): array
    {
        $rows = [];
        $frameSize = 24 + $pageSize;
        for ($frameIndex = $validFrameCount + 1; $frameIndex <= $totalFrameSlots; $frameIndex++) {
            $offset = 32 + (($frameIndex - 1) * $frameSize);
            if (strlen($walBytes) < $offset + 16) {
                continue;
            }
            /** @var array{salt1:int,salt2:int} $salt */
            $salt = unpack('Nsalt1/Nsalt2', substr($walBytes, $offset + 8, 8));
            if ($salt['salt1'] !== $header->salt1 || $salt['salt2'] !== $header->salt2) {
                $rows[] = [
                    'frame_index' => $frameIndex,
                    'offset' => $offset,
                    'frame_salt' => [$salt['salt1'], $salt['salt2']],
                    'expected_salt' => [$header->salt1, $header->salt2],
                    'reason' => 'stale_current_source_salt',
                ];
            }
        }

        return $rows;
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
