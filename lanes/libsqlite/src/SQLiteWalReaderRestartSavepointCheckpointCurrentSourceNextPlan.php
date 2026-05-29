<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalReaderRestartSavepointCheckpointCurrentSourceNextPlan
{
    /**
     * @param list<int> $pageNumbers
     * @param list<array{pages:array<int,string>,database_page_count?:int|null,commit?:bool}> $nextTransactions
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $databaseBytes,
        SQLiteWal $wal,
        string $walBytes,
        SQLiteSavepointStack $savepoints,
        string $savepointName,
        array $pageNumbers,
        int $readerEndFrame,
        array $nextTransactions
    ): array {
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite WAL reader restart savepoint checkpoint next147 requires a database path');
        }
        if ($databaseBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL reader restart savepoint checkpoint next147 requires database bytes');
        }
        if ($walBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL reader restart savepoint checkpoint next147 requires WAL bytes');
        }
        if ($wal->toBytes() !== $walBytes) {
            throw new \InvalidArgumentException('SQLite WAL reader restart savepoint checkpoint next147 parsed WAL does not match current bytes');
        }
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL reader restart savepoint checkpoint next147 requires page numbers');
        }
        if ($nextTransactions === []) {
            throw new \InvalidArgumentException('SQLite WAL reader restart savepoint checkpoint next147 requires next-generation transactions');
        }
        if ($readerEndFrame < 0 || $readerEndFrame > $wal->frameCount()) {
            throw new \InvalidArgumentException('SQLite WAL reader restart savepoint checkpoint next147 reader frame is outside the original WAL frame range');
        }

        $pageSize = $wal->header->pageSize;
        if ($pageSize < 512) {
            throw new \InvalidArgumentException('SQLite WAL reader restart savepoint checkpoint next147 requires a concrete WAL page size');
        }
        if (strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite WAL reader restart savepoint checkpoint next147 database bytes must be page aligned');
        }

        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite WAL reader restart savepoint checkpoint next147 pages must be one-based integers');
            }
        }

        $rollback = $savepoints->walRollbackToByteTruncationPlan($savepointName, $wal, $walBytes);
        $truncatedWalBytes = $savepoints->walRollbackToWalBytes($savepointName, $wal, $walBytes);
        $truncatedWal = SQLiteWal::parse($truncatedWalBytes, $pageSize, true);
        if ($readerEndFrame > $truncatedWal->frameCount()) {
            throw new \InvalidArgumentException('SQLite WAL reader restart savepoint checkpoint next147 reader frame must be inside the rolled-back WAL source');
        }

        $readerBefore = self::readerRows($truncatedWal, $databaseBytes, $pageNumbers, $readerEndFrame);
        $pinnedCheckpoint = $truncatedWal->durableCheckpointResult($databaseBytes, 'restart', $readerEndFrame);
        $releasedCheckpoint = $truncatedWal->durableCheckpointResult($databaseBytes, 'restart');
        $checkpointWalBytes = (string) $releasedCheckpoint['wal_bytes'];
        if ($checkpointWalBytes === '') {
            throw new \UnexpectedValueException('SQLite WAL reader restart savepoint checkpoint next147 expected restart checkpoint WAL header bytes');
        }

        $checkpointWal = SQLiteWal::parse($checkpointWalBytes, $pageSize, true);
        $readerAfter = self::readerRows($truncatedWal, $databaseBytes, $pageNumbers, $readerEndFrame);
        $nextAppend = SQLiteWalAppendPlan::appendTransactions($checkpointWal, $databasePath, $nextTransactions);
        $nextWal = SQLiteWal::parse((string) $nextAppend['wal_bytes'], $pageSize, true);
        $nextRows = self::readerRows($nextWal, (string) $releasedCheckpoint['database_bytes'], $pageNumbers, $nextWal->frameCount());

        $rows = [];
        foreach ($pageNumbers as $index => $pageNumber) {
            $before = $readerBefore[$index];
            $after = $readerAfter[$index];
            $next = $nextRows[$index];
            $rows[] = [
                'page_number' => $pageNumber,
                'reader_before_source' => $before['source'],
                'reader_before_frame' => $before['frame_index'],
                'reader_before_label' => self::label((string) $before['image']),
                'reader_after_source' => $after['source'],
                'reader_after_frame' => $after['frame_index'],
                'reader_after_label' => self::label((string) $after['image']),
                'next_source' => $next['source'],
                'next_frame' => $next['frame_index'],
                'next_label' => self::label((string) $next['image']),
                'reader_preserved' => (string) $before['image'] === (string) $after['image']
                    && $before['source'] === $after['source']
                    && $before['frame_index'] === $after['frame_index'],
                'next_separated_from_reader' => (string) $next['image'] !== (string) $after['image']
                    || $next['source'] !== $after['source']
                    || $next['frame_index'] !== $after['frame_index'],
                'source_transition' => $before['source'] . '>' . $after['source'] . '>' . $next['source'],
            ];
        }

        $readerPreserved = !in_array(false, array_column($rows, 'reader_preserved'), true);
        $nextSeparated = in_array(true, array_column($rows, 'next_separated_from_reader'), true);
        $status = $rollback['needs_truncate']
            && $pinnedCheckpoint['busy']
            && $pinnedCheckpoint['wal_action'] === 'preserve_wal'
            && ! $releasedCheckpoint['busy']
            && $releasedCheckpoint['wal_action'] === 'restart_wal'
            && $readerPreserved
            && $nextSeparated
            ? 'wal-reader-restart-savepoint-checkpoint-current-source-next147'
            : 'wal-reader-restart-savepoint-checkpoint-current-source-blocked-next147';

        $discardedPageNumbers = array_values(array_unique(array_map(
            static fn (array $frame): int => (int) $frame['page_number'],
            $rollback['discarded_wal_frames']
        )));
        sort($discardedPageNumbers, SORT_NUMERIC);

        return [
            'status' => $status,
            'reason' => $status === 'wal-reader-restart-savepoint-checkpoint-current-source-next147'
                ? 'savepoint_rollback_truncates_wal_before_restart_checkpoint_preserves_current_reader'
                : 'savepoint_restart_checkpoint_current_reader_boundary_not_satisfied',
            'database_path' => $databasePath,
            'wal_path' => $databasePath . '-wal',
            'page_size' => $pageSize,
            'savepoint' => $savepointName,
            'reader_end_frame' => $readerEndFrame,
            'original_frame_count' => $rollback['original_frame_count'],
            'retained_frame_count' => $rollback['retained_frame_count'],
            'discarded_frame_count' => $rollback['discarded_frame_count'],
            'truncate_to_bytes' => $rollback['truncate_to_bytes'],
            'truncated_wal_bytes_length' => strlen($truncatedWalBytes),
            'pinned_checkpoint_busy' => (bool) $pinnedCheckpoint['busy'],
            'pinned_checkpoint_reason' => $pinnedCheckpoint['reason'],
            'pinned_checkpoint_wal_action' => $pinnedCheckpoint['wal_action'],
            'released_checkpoint_busy' => (bool) $releasedCheckpoint['busy'],
            'released_checkpoint_reason' => $releasedCheckpoint['reason'],
            'released_checkpoint_wal_action' => $releasedCheckpoint['wal_action'],
            'checkpoint_busy' => (bool) $pinnedCheckpoint['busy'],
            'checkpoint_reason' => $pinnedCheckpoint['reason'],
            'checkpoint_wal_action' => $releasedCheckpoint['wal_action'],
            'checkpoint_database_bytes_length' => strlen((string) $releasedCheckpoint['database_bytes']),
            'restart_checkpoint_sequence' => $releasedCheckpoint['wal_header']['checkpoint_sequence'] ?? null,
            'restart_salt' => [
                $releasedCheckpoint['wal_header']['salt1'] ?? null,
                $releasedCheckpoint['wal_header']['salt2'] ?? null,
            ],
            'next_append_start_frame' => $nextAppend['start_frame'],
            'next_append_end_frame' => $nextAppend['end_frame'],
            'next_append_frame_count' => $nextAppend['appended_frame_count'],
            'next_append_last_commit_frame' => $nextAppend['last_commit_frame'],
            'reader_before_sources' => self::column($readerBefore, 'source'),
            'reader_after_sources' => self::column($readerAfter, 'source'),
            'next_sources' => self::column($nextRows, 'source'),
            'reader_before_frame_indexes' => self::column($readerBefore, 'frame_index'),
            'reader_after_frame_indexes' => self::column($readerAfter, 'frame_index'),
            'next_frame_indexes' => self::column($nextRows, 'frame_index'),
            'reader_preserved_by_restart_checkpoint' => $readerPreserved,
            'next_generation_separated_from_reader' => $nextSeparated,
            'discarded_wal_frames' => $rollback['discarded_wal_frames'],
            'discarded_page_numbers' => $discardedPageNumbers,
            'source_transitions' => array_column($rows, 'source_transition'),
            'source_digest' => hash('sha256', implode('|', array_column($rows, 'source_transition'))),
            'original_wal_sha256' => hash('sha256', $walBytes),
            'truncated_wal_sha256' => hash('sha256', $truncatedWalBytes),
            'restart_header_wal_sha256' => hash('sha256', $checkpointWalBytes),
            'next_wal_sha256' => hash('sha256', (string) $nextAppend['wal_bytes']),
            'rows' => $rows,
            'rollback' => $rollback,
            'pinned_checkpoint' => $pinnedCheckpoint,
            'released_checkpoint' => $releasedCheckpoint,
            'checkpoint' => $releasedCheckpoint,
            'next_append' => $nextAppend,
            'operation_reasons' => array_merge(
                ['truncate_wal_to_savepoint_before_restart_checkpoint_next147'],
                [$pinnedCheckpoint['reason'], $releasedCheckpoint['reason']],
                array_column($nextAppend['operations'], 'reason')
            ),
            'dependencies' => array_values(array_unique(array_merge(
                $pinnedCheckpoint['dependencies'],
                $releasedCheckpoint['dependencies'],
                $nextAppend['dependencies'],
                [
                    'sqlite-wal-savepoint-byte-truncation',
                    'sqlite-wal-restart-checkpoint-current-reader-boundary',
                    'sqlite-wal-reader-restart-savepoint-checkpoint-current-source-next147',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses native WAL savepoint byte truncation, restart checkpoint planning, reader snapshots, and WAL append frame checksums',
            'non_overlap' => 'avoids accepted WAL byte-truncation-only, checkpoint transaction, hot-journal reader restart, and truncate-reader slices by composing savepoint rollback with a restart checkpoint current-reader boundary and a later generation append',
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
