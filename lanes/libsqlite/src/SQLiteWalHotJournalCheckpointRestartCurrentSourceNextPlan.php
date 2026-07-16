<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalCheckpointRestartCurrentSourceNextPlan
{
    /**
     * @param list<int> $pageNumbers
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $databaseBytes,
        string $journalBytes,
        SQLiteWal $wal,
        string $walBytes,
        array $pageNumbers,
        ?int $readerEndFrame = null,
        bool $reservedLock = false,
        bool $requiresSuperJournal = false,
        ?bool $superJournalExists = null
    ): array {
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal checkpoint restart current-source next129 requires a database path');
        }
        if ($databaseBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal checkpoint restart current-source next129 requires database bytes');
        }
        if ($journalBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal checkpoint restart current-source next129 requires rollback journal bytes');
        }
        if ($walBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal checkpoint restart current-source next129 requires WAL bytes');
        }
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal checkpoint restart current-source next129 requires page numbers');
        }
        if ($wal->toBytes() !== $walBytes) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal checkpoint restart current-source next129 parsed WAL does not match current bytes');
        }

        $pageSize = $wal->header->pageSize;
        if (strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal checkpoint restart current-source next129 database bytes must be page-size aligned');
        }

        $readerEndFrame ??= $wal->frameCount();
        if ($readerEndFrame < 0 || $readerEndFrame > $wal->frameCount()) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal checkpoint restart current-source next129 reader frame is outside the WAL frame range');
        }

        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite WAL hot-journal checkpoint restart current-source next129 pages must be one-based integers');
            }
        }

        $journal = SQLiteRollbackJournal::parse($journalBytes, true);
        if ($journal->header->pageSize !== $pageSize) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal checkpoint restart current-source next129 journal page size does not match WAL page size');
        }

        $hot = $journal->hotJournalRecoveryResult(
            $databaseBytes,
            $journalBytes,
            $reservedLock,
            $requiresSuperJournal,
            $superJournalExists
        );
        $hotDatabaseBytes = (string) $hot['database_bytes'];

        $readerRows = self::readerRows($wal, $hotDatabaseBytes, $pageNumbers, $readerEndFrame);
        $pinnedCheckpoint = $wal->durableCheckpointResult($hotDatabaseBytes, 'restart', $readerEndFrame);
        $releasedCheckpoint = $wal->durableCheckpointResult($hotDatabaseBytes, 'restart');

        $pinnedRows = self::checkpointRows($pinnedCheckpoint, $pageSize, $pageNumbers, $readerEndFrame);
        $releasedRows = self::checkpointRows($releasedCheckpoint, $pageSize, $pageNumbers, 0);
        $dirtyRows = self::databaseRows($databaseBytes, $pageSize, $pageNumbers);
        $hotRows = self::databaseRows($hotDatabaseBytes, $pageSize, $pageNumbers);

        $operations = self::operations(
            $databasePath,
            $hot,
            $pinnedCheckpoint,
            $releasedCheckpoint
        );

        $rows = [];
        foreach ($pageNumbers as $index => $pageNumber) {
            $rows[] = [
                'page_number' => $pageNumber,
                'dirty_source' => $dirtyRows[$index]['source'],
                'hot_current_source' => $hotRows[$index]['source'],
                'reader_source' => $readerRows[$index]['source'],
                'pinned_next_source' => $pinnedRows[$index]['source'],
                'released_next_source' => $releasedRows[$index]['source'],
                'reader_frame' => $readerRows[$index]['frame_index'],
                'pinned_next_frame' => $pinnedRows[$index]['frame_index'],
                'released_next_frame' => $releasedRows[$index]['frame_index'],
                'hot_replaced_dirty_image' => $dirtyRows[$index]['image'] !== $hotRows[$index]['image'],
                'pinned_preserves_reader_image' => $readerRows[$index]['image'] === $pinnedRows[$index]['image'],
                'released_preserves_reader_image' => $readerRows[$index]['image'] === $releasedRows[$index]['image'],
                'released_uses_checkpoint_image' => $hotRows[$index]['image'] === $releasedRows[$index]['image'] || $readerRows[$index]['image'] === $releasedRows[$index]['image'],
                'source_transition' => implode('>', [
                    $dirtyRows[$index]['source'],
                    $hotRows[$index]['source'],
                    $readerRows[$index]['source'],
                    $pinnedRows[$index]['source'],
                    $releasedRows[$index]['source'],
                ]),
                'dirty_label' => self::label((string) $dirtyRows[$index]['image']),
                'hot_current_label' => self::label((string) $hotRows[$index]['image']),
                'reader_label' => self::label((string) $readerRows[$index]['image']),
                'pinned_next_label' => self::label((string) $pinnedRows[$index]['image']),
                'released_next_label' => self::label((string) $releasedRows[$index]['image']),
            ];
        }

        $hotPages = array_values(array_map(
            static fn (array $row): int => (int) $row['page_number'],
            array_filter($rows, static fn (array $row): bool => (bool) $row['hot_replaced_dirty_image'])
        ));

        return [
            'status' => $hot['recovered']
                ? 'wal-hot-journal-checkpoint-restart-current-source-next129'
                : 'wal-hot-journal-checkpoint-restart-current-source-blocked-next129',
            'reason' => $hot['recovered']
                ? 'hot_journal_recovery_precedes_restart_checkpoint_current_source_boundary'
                : $hot['reason'],
            'database_path' => $databasePath,
            'journal_path' => $databasePath . '-journal',
            'wal_path' => $databasePath . '-wal',
            'mode' => 'restart',
            'page_size' => $pageSize,
            'reader_end_frame' => $readerEndFrame,
            'hot_recovered' => (bool) $hot['recovered'],
            'hot_journal_reason' => $hot['hot_journal']['reason'],
            'journal_action' => $hot['journal_action'],
            'hot_restored_page_numbers' => $hotPages,
            'journal_page_numbers' => array_keys($journal->pageImages()),
            'pinned_checkpoint_busy' => $pinnedCheckpoint['busy'],
            'pinned_checkpoint_reason' => $pinnedCheckpoint['reason'],
            'pinned_wal_action' => $pinnedCheckpoint['wal_action'],
            'released_checkpoint_busy' => $releasedCheckpoint['busy'],
            'released_checkpoint_reason' => $releasedCheckpoint['reason'],
            'released_wal_action' => $releasedCheckpoint['wal_action'],
            'released_wal_bytes_length' => strlen((string) $releasedCheckpoint['wal_bytes']),
            'pinned_wal_bytes_length' => strlen((string) $pinnedCheckpoint['wal_bytes']),
            'released_restart_checkpoint_sequence' => $releasedCheckpoint['wal_header']['checkpoint_sequence'] ?? null,
            'reader_sources' => self::column($readerRows, 'source'),
            'pinned_next_sources' => self::column($pinnedRows, 'source'),
            'released_next_sources' => self::column($releasedRows, 'source'),
            'reader_frame_indexes' => self::column($readerRows, 'frame_index'),
            'pinned_next_frame_indexes' => self::column($pinnedRows, 'frame_index'),
            'released_next_frame_indexes' => self::column($releasedRows, 'frame_index'),
            'rows' => $rows,
            'source_transitions' => array_column($rows, 'source_transition'),
            'pinned_preserved_reader_images' => !in_array(false, array_column($rows, 'pinned_preserves_reader_image'), true),
            'released_restart_uses_checkpoint_images' => !in_array(false, array_column($rows, 'released_uses_checkpoint_image'), true),
            'reader_release_unblocked_restart' => (bool) $pinnedCheckpoint['busy'] && ! (bool) $releasedCheckpoint['busy'],
            'current_source_wal_sha256' => hash('sha256', $walBytes),
            'released_wal_sha256' => hash('sha256', (string) $releasedCheckpoint['wal_bytes']),
            'hot_database_sha256' => hash('sha256', $hotDatabaseBytes),
            'source_digest' => hash('sha256', implode('|', array_column($rows, 'source_transition'))),
            'operations' => $operations,
            'operation_reasons' => self::column($operations, 'reason'),
            'pinned_checkpoint' => $pinnedCheckpoint,
            'released_checkpoint' => $releasedCheckpoint,
            'hot_journal' => $hot,
            'dependencies' => array_values(array_unique(array_merge(
                $pinnedCheckpoint['dependencies'],
                $releasedCheckpoint['dependencies'],
                [
                    'sqlite-wal-hot-journal-checkpoint-restart-current-source-next129',
                    'sqlite-hot-journal-recovery',
                    'sqlite-wal-checkpoint-restart-current-source-boundary',
                ]
            ))),
        ];
    }

    /**
     * @param list<int> $pageNumbers
     * @return list<array<string,mixed>>
     */
    private static function readerRows(SQLiteWal $wal, string $databaseBytes, array $pageNumbers, int $readerEndFrame): array
    {
        return array_map(
            static fn (int $pageNumber): array => $wal->readerSnapshotPageImage($databaseBytes, $pageNumber, $readerEndFrame),
            $pageNumbers
        );
    }

    /**
     * @param list<int> $pageNumbers
     * @return list<array<string,mixed>>
     */
    private static function checkpointRows(array $checkpoint, int $pageSize, array $pageNumbers, int $readerEndFrame): array
    {
        $walBytes = (string) $checkpoint['wal_bytes'];
        if ($walBytes === '') {
            return self::databaseRows((string) $checkpoint['database_bytes'], $pageSize, $pageNumbers);
        }

        $wal = SQLiteWal::parse($walBytes, $pageSize, true);
        $endFrame = min($readerEndFrame, $wal->frameCount());

        return self::readerRows($wal, (string) $checkpoint['database_bytes'], $pageNumbers, $endFrame);
    }

    /**
     * @param list<int> $pageNumbers
     * @return list<array<string,mixed>>
     */
    private static function databaseRows(string $databaseBytes, int $pageSize, array $pageNumbers): array
    {
        $rows = [];
        foreach ($pageNumbers as $pageNumber) {
            $offset = ($pageNumber - 1) * $pageSize;
            $rows[] = [
                'page_number' => $pageNumber,
                'source' => 'database',
                'frame_index' => null,
                'database_offset' => $offset,
                'image' => substr($databaseBytes, $offset, $pageSize),
                'snapshot_end_frame' => 0,
                'snapshot_commit_frame' => null,
                'database_page_count' => intdiv(strlen($databaseBytes), $pageSize),
            ];
        }

        return $rows;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private static function operations(string $databasePath, array $hot, array $pinned, array $released): array
    {
        $operations = [
            [
                'op' => 'write',
                'path' => $databasePath,
                'offset' => 0,
                'bytes' => strlen((string) $hot['database_bytes']),
                'reason' => 'restore_hot_journal_database_before_restart_checkpoint_next129',
            ],
            [
                'op' => 'delete',
                'path' => $databasePath . '-journal',
                'durable' => false,
                'reason' => 'delete_hot_journal_after_recovery_next129',
            ],
        ];

        if ($pinned['busy']) {
            $operations[] = [
                'op' => 'preserve',
                'path' => $databasePath . '-wal',
                'bytes' => strlen((string) $pinned['wal_bytes']),
                'reason' => 'preserve_current_wal_while_reader_pins_restart_next129',
            ];
        }

        $operations[] = [
            'op' => 'write',
            'path' => $databasePath,
            'offset' => 0,
            'bytes' => strlen((string) $released['database_bytes']),
            'reason' => 'write_released_restart_checkpoint_database_next129',
        ];
        $operations[] = [
            'op' => 'write',
            'path' => $databasePath . '-wal',
            'offset' => 0,
            'bytes' => strlen((string) $released['wal_bytes']),
            'reason' => 'write_released_restart_header_wal_next129',
        ];

        return $operations;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<mixed>
     */
    private static function column(array $rows, string $column): array
    {
        return array_map(static fn (array $row): mixed => $row[$column] ?? null, $rows);
    }

    private static function label(string $image): string
    {
        return rtrim(substr($image, 0, 96), ".\0");
    }
}
