<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext159Plan
{
    /**
     * @param array<int,string> $hotJournalPages
     * @param array<int,string> $currentSourcePages
     * @param array<int,string> $currentSavepointWrites
     * @param array<int,string> $nextSavepointWrites
     * @param list<int> $readerPageNumbers
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $databaseBytes,
        int $pageSize,
        string $savepoint,
        array $hotJournalPages,
        array $currentSourcePages,
        array $currentSavepointWrites,
        array $nextSavepointWrites,
        SQLiteWal $currentWal,
        string $currentWalBytes,
        SQLiteWal $nextWal,
        string $nextWalBytes,
        array $readerPageNumbers,
        int $readerEndFrame,
        string $mode = 'restart',
        int $currentSourceEpoch = 1,
        bool $reservedLock = false,
        bool $superJournalRequired = false,
        bool $superJournalExists = false,
    ): array {
        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['full', 'restart', 'truncate'], true)) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next159 requires full, restart, or truncate mode');
        }
        if ($readerPageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next159 requires reader pages');
        }
        if ($readerEndFrame < 0 || $readerEndFrame > $currentWal->frameCount()) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next159 reader frame is outside the current WAL range');
        }

        $base = SQLitePagerSavepointWalHotJournalCurrentSourceNext148Plan::plan(
            $databasePath,
            $databaseBytes,
            $pageSize,
            $savepoint,
            $hotJournalPages,
            $currentSourcePages,
            $currentSavepointWrites,
            $nextSavepointWrites,
            $currentWal,
            $currentWalBytes,
            $nextWal,
            $nextWalBytes,
            $readerPageNumbers,
            $readerEndFrame,
            $currentSourceEpoch,
            $reservedLock,
            $superJournalRequired,
            $superJournalExists,
        );

        $rolledBackDatabaseBytes = (string) $base['base_plan']['rolled_back_database_bytes'];
        $checkpoint = $currentWal->checkpointModePlan($rolledBackDatabaseBytes, $mode, $readerEndFrame);
        $durable = $currentWal->durableCheckpointResult($rolledBackDatabaseBytes, $mode, $readerEndFrame);
        $nextDurable = $nextWal->durableCheckpointResult($rolledBackDatabaseBytes, 'passive', $nextWal->frameCount());

        $rows = [];
        foreach ($readerPageNumbers as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next159 reader pages must be one-based integers');
            }

            $current = $currentWal->readerSnapshotPageImage($rolledBackDatabaseBytes, $pageNumber, $readerEndFrame);
            $afterCheckpoint = $durable['wal_bytes'] === ''
                ? self::databasePageVisibility((string) $durable['database_bytes'], $pageSize, $pageNumber)
                : SQLiteWal::parse((string) $durable['wal_bytes'], $pageSize, true)->readerSnapshotPageImage((string) $durable['database_bytes'], $pageNumber, $readerEndFrame);
            $next = $nextWal->readerSnapshotPageImage($rolledBackDatabaseBytes, $pageNumber, $nextWal->frameCount());
            $nextCheckpoint = self::databasePageVisibility((string) $nextDurable['database_bytes'], $pageSize, $pageNumber);

            $rows[] = [
                'page_number' => $pageNumber,
                'current_source' => $current['source'],
                'current_frame' => $current['frame_index'],
                'current_label' => self::label((string) $current['image']),
                'checkpoint_source' => $afterCheckpoint['source'],
                'checkpoint_frame' => $afterCheckpoint['frame_index'],
                'checkpoint_label' => self::label((string) $afterCheckpoint['image']),
                'next_source' => $next['source'],
                'next_frame' => $next['frame_index'],
                'next_label' => self::label((string) $next['image']),
                'next_checkpoint_source' => $nextCheckpoint['source'],
                'next_checkpoint_label' => self::label((string) $nextCheckpoint['image']),
                'checkpoint_matches_current' => $afterCheckpoint['image'] === $current['image'],
                'next_checkpoint_matches_next' => $nextCheckpoint['image'] === $next['image'],
                'source_transition' => $current['source'] . '>checkpoint>' . $afterCheckpoint['source'] . '>next-wal>' . $next['source'] . '>next-checkpoint>' . $nextCheckpoint['source'],
            ];
        }

        $currentSources = array_column($rows, 'current_source');
        $checkpointSources = array_column($rows, 'checkpoint_source');
        $nextSources = array_column($rows, 'next_source');
        $status = (bool) $base['hot_recovered']
            && !(bool) $checkpoint['busy']
            && $base['next_source_separated']
            && !in_array(false, array_column($rows, 'checkpoint_matches_current'), true)
            && !in_array(false, array_column($rows, 'next_checkpoint_matches_next'), true)
            ? 'wal-hot-journal-savepoint-checkpoint-current-source-next159'
            : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next159';

        $payloads = [
            $databasePath . '#next159-checkpoint-current' => (string) $durable['database_bytes'],
            $databasePath . '-wal#next159-checkpoint-current' => (string) $durable['wal_bytes'],
            $databasePath . '#next159-checkpoint-next' => (string) $nextDurable['database_bytes'],
            $databasePath . '-wal#next159-checkpoint-next' => (string) $nextDurable['wal_bytes'],
        ];
        $operations = self::operations($databasePath, $mode, $durable, $nextDurable, $payloads);

        return [
            'status' => $status,
            'reason' => $status === 'wal-hot-journal-savepoint-checkpoint-current-source-next159'
                ? 'hot_journal_recovered_savepoint_retry_checkpointed_before_next_wal_source'
                : 'hot_journal_savepoint_checkpoint_current_source_not_ready',
            'database_path' => $databasePath,
            'journal_path' => $base['journal_path'],
            'wal_path' => $databasePath . '-wal',
            'page_size' => $pageSize,
            'savepoint' => $savepoint,
            'mode' => $mode,
            'reader_end_frame' => $readerEndFrame,
            'hot_recovered' => (bool) $base['hot_recovered'],
            'checkpoint_busy' => (bool) $checkpoint['busy'],
            'checkpoint_reason' => $checkpoint['reason'],
            'checkpoint_wal_action' => $durable['wal_action'],
            'next_checkpoint_wal_action' => $nextDurable['wal_action'],
            'current_wal_source' => $base['current_wal_source'],
            'next_wal_source' => $base['next_wal_source'],
            'current_sources' => $currentSources,
            'checkpoint_sources' => $checkpointSources,
            'next_sources' => $nextSources,
            'next_checkpoint_sources' => array_column($rows, 'next_checkpoint_source'),
            'current_frame_indexes' => array_column($rows, 'current_frame'),
            'checkpoint_frame_indexes' => array_column($rows, 'checkpoint_frame'),
            'next_frame_indexes' => array_column($rows, 'next_frame'),
            'current_labels' => array_column($rows, 'current_label'),
            'checkpoint_labels' => array_column($rows, 'checkpoint_label'),
            'next_labels' => array_column($rows, 'next_label'),
            'next_checkpoint_labels' => array_column($rows, 'next_checkpoint_label'),
            'checkpoint_matches_current_reader' => !in_array(false, array_column($rows, 'checkpoint_matches_current'), true),
            'next_checkpoint_matches_next_reader' => !in_array(false, array_column($rows, 'next_checkpoint_matches_next'), true),
            'checkpoint_uses_database_pages' => !in_array('wal', $checkpointSources, true),
            'next_uses_separate_wal_source' => $base['next_source_separated'],
            'next_checkpoint_uses_database_pages' => !in_array('wal', array_column($rows, 'next_checkpoint_source'), true),
            'source_transitions' => array_column($rows, 'source_transition'),
            'source_digest' => hash('sha256', implode('|', array_column($rows, 'source_transition'))),
            'current_checkpoint' => $checkpoint,
            'current_durable' => $durable,
            'next_durable' => $nextDurable,
            'rows' => $rows,
            'operations' => $operations,
            'operation_reasons' => array_column($operations, 'reason'),
            'payload_keys' => array_keys($payloads),
            'payloads' => $payloads,
            'base_plan' => $base,
            'dependencies' => array_values(array_unique(array_merge(
                $base['dependencies'],
                $durable['dependencies'],
                $nextDurable['dependencies'],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next159',
                    'wordpress-import-hot-journal-savepoint-checkpoint-current-source',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses hot-journal current-source recovery, WAL reader snapshots, savepoint page images, and durable checkpoint helpers',
            'non_overlap' => 'avoids accepted WAL byte truncation, rollback-journal apply, checkpoint transaction, and next148 WAL source separation by asserting checkpoint materialization on the rolled-back hot-journal current source before the next WAL generation is read',
        ];
    }

    /**
     * @param array<string,string> $payloads
     * @return list<array<string,mixed>>
     */
    private static function operations(string $databasePath, string $mode, array $current, array $next, array $payloads): array
    {
        $operations = [
            [
                'op' => 'write',
                'path' => $databasePath,
                'offset' => 0,
                'bytes' => strlen($payloads[$databasePath . '#next159-checkpoint-current']),
                'payload_key' => $databasePath . '#next159-checkpoint-current',
                'reason' => 'checkpoint_hot_journal_savepoint_current_source_database',
            ],
        ];

        if ((string) $current['wal_bytes'] === '') {
            $operations[] = [
                'op' => $mode === 'truncate' ? 'truncate' : 'restart',
                'path' => $databasePath . '-wal',
                'bytes' => strlen((string) $current['wal_bytes']),
                'payload_key' => $databasePath . '-wal#next159-checkpoint-current',
                'reason' => $mode === 'truncate'
                    ? 'truncate_current_wal_after_hot_journal_savepoint_checkpoint'
                    : 'restart_current_wal_after_hot_journal_savepoint_checkpoint',
            ];
        } else {
            $operations[] = [
                'op' => 'write',
                'path' => $databasePath . '-wal',
                'offset' => 0,
                'bytes' => strlen((string) $current['wal_bytes']),
                'payload_key' => $databasePath . '-wal#next159-checkpoint-current',
                'reason' => 'preserve_current_wal_after_reader_blocked_checkpoint',
            ];
        }

        $operations[] = [
            'op' => 'write',
            'path' => $databasePath . '-wal',
            'offset' => 0,
            'bytes' => strlen($payloads[$databasePath . '-wal#next159-checkpoint-next']),
            'payload_key' => $databasePath . '-wal#next159-checkpoint-next',
            'reason' => 'install_next_wal_generation_after_current_checkpoint',
        ];
        $operations[] = [
            'op' => 'sync',
            'path' => $databasePath,
            'durable' => true,
            'reason' => 'sync_database_after_hot_journal_savepoint_checkpoint',
        ];
        $operations[] = [
            'op' => 'sync',
            'path' => $databasePath . '-wal',
            'durable' => true,
            'reason' => 'sync_next_wal_generation_after_checkpoint',
        ];

        if ((string) $next['wal_bytes'] === '') {
            $operations[] = [
                'op' => 'delete',
                'path' => $databasePath . '-wal',
                'reason' => 'delete_empty_next_wal_after_checkpoint',
            ];
        }

        return $operations;
    }

    /**
     * @return array{page_number:int,source:string,frame_index:null,database_offset:int,image:string,snapshot_end_frame:int,snapshot_commit_frame:null,database_page_count:int}
     */
    private static function databasePageVisibility(string $databaseBytes, int $pageSize, int $pageNumber): array
    {
        if ($pageNumber < 1) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next159 page numbers are one-based');
        }

        return [
            'page_number' => $pageNumber,
            'source' => 'database',
            'frame_index' => null,
            'database_offset' => ($pageNumber - 1) * $pageSize,
            'image' => substr($databaseBytes, ($pageNumber - 1) * $pageSize, $pageSize),
            'snapshot_end_frame' => 0,
            'snapshot_commit_frame' => null,
            'database_page_count' => intdiv(strlen($databaseBytes), $pageSize),
        ];
    }

    private static function label(string $image): string
    {
        return rtrim(substr($image, 0, 96), ".\0");
    }
}
