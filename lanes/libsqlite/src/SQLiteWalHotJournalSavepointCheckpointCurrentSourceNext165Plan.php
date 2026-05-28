<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext165Plan
{
    /**
     * @param list<int> $pageNumbers
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $dirtyDatabaseBytes,
        string $journalBytes,
        SQLiteSavepointStack $savepoints,
        string $savepoint,
        SQLiteWal $wal,
        string $walBytes,
        array $pageNumbers,
        string $mode = 'restart',
        ?int $readerEndFrame = null,
        bool $reservedLock = false,
        bool $requiresSuperJournal = false,
        ?bool $superJournalExists = null,
    ): array {
        $base = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext162Plan::plan(
            $databasePath,
            $dirtyDatabaseBytes,
            $journalBytes,
            $savepoints,
            $savepoint,
            $wal,
            $walBytes,
            $pageNumbers,
            $mode,
            $readerEndFrame,
            $reservedLock,
            $requiresSuperJournal,
            $superJournalExists,
        );

        if (($base['status'] ?? '') !== 'wal-hot-journal-savepoint-checkpoint-current-source-next162') {
            return array_merge($base, [
                'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next165',
                'reason' => $base['reason'] ?? 'current_source_not_admitted',
                'publish_admitted' => false,
                'dependencies' => array_values(array_unique(array_merge(
                    $base['dependencies'] ?? [],
                    ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next165']
                ))),
            ]);
        }

        $mode = strtolower(trim($mode));
        $pageSize = (int) $base['page_size'];
        $retainedWalBytes = $savepoints->walRollbackToWalBytes($savepoint, $wal, $walBytes);
        $retainedWal = SQLiteWal::parse($retainedWalBytes, $pageSize, true);
        $readerEndFrame ??= (int) $base['reader_end_frame'];
        $currentCheckpoint = $retainedWal->durableCheckpointResult(
            self::checkpointDatabaseBytes($base, 'current_checkpoint', $dirtyDatabaseBytes),
            $mode,
            $readerEndFrame
        );
        $releasedCheckpoint = $retainedWal->durableCheckpointResult(
            self::checkpointDatabaseBytes($base, 'released_checkpoint', $dirtyDatabaseBytes),
            $mode
        );

        $currentDatabaseBytes = (string) $currentCheckpoint['database_bytes'];
        $releasedDatabaseBytes = (string) $releasedCheckpoint['database_bytes'];
        $currentWalBytes = (string) $currentCheckpoint['wal_bytes'];
        $releasedWalBytes = (string) $releasedCheckpoint['wal_bytes'];
        $currentPayloadKey = $databasePath . '#next165-current-checkpoint';
        $releasedPayloadKey = $databasePath . '#next165-released-checkpoint';
        $currentWalPayloadKey = $databasePath . '-wal#next165-current-reader';
        $releasedWalPayloadKey = $databasePath . '-wal#next165-released-reader';
        $journalPath = $databasePath . '-journal';
        $walPath = $databasePath . '-wal';

        $operations = [
            [
                'op' => 'write',
                'path' => $databasePath,
                'offset' => 0,
                'bytes' => strlen($currentDatabaseBytes),
                'payload_key' => $currentPayloadKey,
                'reason' => 'publish_hot_journal_savepoint_current_checkpoint_database_next165',
            ],
            [
                'op' => 'truncate',
                'path' => $databasePath,
                'bytes' => strlen($currentDatabaseBytes),
                'reason' => 'trim_database_after_current_checkpoint_publish_next165',
            ],
            [
                'op' => 'write',
                'path' => $walPath,
                'offset' => 0,
                'bytes' => strlen($currentWalBytes),
                'payload_key' => $currentWalPayloadKey,
                'reason' => 'preserve_retained_wal_for_pinned_reader_next165',
            ],
            [
                'op' => 'sync',
                'path' => $databasePath,
                'durable' => true,
                'reason' => 'sync_current_checkpoint_before_reader_release_next165',
            ],
            [
                'op' => 'delete',
                'path' => $journalPath,
                'reason' => 'delete_hot_journal_after_current_source_checkpoint_next165',
            ],
            [
                'op' => 'write',
                'path' => $databasePath,
                'offset' => 0,
                'bytes' => strlen($releasedDatabaseBytes),
                'payload_key' => $releasedPayloadKey,
                'reason' => 'publish_released_savepoint_checkpoint_database_next165',
            ],
            [
                'op' => $releasedWalBytes === '' ? 'truncate' : 'write',
                'path' => $walPath,
                'offset' => 0,
                'bytes' => strlen($releasedWalBytes),
                'payload_key' => $releasedWalPayloadKey,
                'reason' => $releasedWalBytes === ''
                    ? 'truncate_wal_after_savepoint_release_next165'
                    : 'restart_wal_after_savepoint_release_next165',
            ],
            [
                'op' => 'sync',
                'path' => $databasePath,
                'durable' => true,
                'reason' => 'sync_released_checkpoint_after_savepoint_publish_next165',
            ],
        ];

        $rows = [];
        foreach ($base['rows'] as $row) {
            $rows[] = [
                'page_number' => $row['page_number'],
                'dirty_label' => $row['dirty_label'],
                'current_checkpoint_label' => $row['current_checkpoint_label'],
                'released_checkpoint_label' => $row['released_checkpoint_label'],
                'current_source' => $row['current_checkpoint_source'],
                'released_source' => $row['released_checkpoint_source'],
                'reader_pinned' => $row['current_checkpoint_source'] === 'wal',
                'released_from_database' => $row['released_checkpoint_source'] === 'database',
                'stale_publish_blocked' => $row['stale_checkpoint_would_publish_dirty'],
                'publish_transition' => $row['dirty_source'] . '>' . $row['current_checkpoint_source'] . '>' . $row['released_checkpoint_source'],
            ];
        }

        $pinnedRows = array_values(array_filter($rows, static fn (array $row): bool => (bool) $row['reader_pinned']));
        $staleBlockedRows = array_values(array_filter($rows, static fn (array $row): bool => (bool) $row['stale_publish_blocked']));

        return [
            'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next165',
            'reason' => 'publish_checkpoint_uses_hot_journal_savepoint_current_source_before_wal_reset',
            'database_path' => $databasePath,
            'journal_path' => $journalPath,
            'wal_path' => $walPath,
            'savepoint' => $savepoint,
            'mode' => $mode,
            'page_size' => $pageSize,
            'page_numbers' => $base['page_numbers'],
            'publish_admitted' => true,
            'hot_recovered' => true,
            'current_checkpoint_busy' => $base['current_checkpoint_busy'],
            'released_checkpoint_busy' => $base['released_checkpoint_busy'],
            'current_checkpoint_wal_action' => $base['current_checkpoint_wal_action'],
            'released_checkpoint_wal_action' => $base['released_checkpoint_wal_action'],
            'current_database_bytes_length' => strlen($currentDatabaseBytes),
            'released_database_bytes_length' => strlen($releasedDatabaseBytes),
            'current_wal_bytes_length' => strlen($currentWalBytes),
            'released_wal_bytes_length' => strlen($releasedWalBytes),
            'current_database_sha256' => hash('sha256', $currentDatabaseBytes),
            'released_database_sha256' => hash('sha256', $releasedDatabaseBytes),
            'current_wal_sha256' => hash('sha256', $currentWalBytes),
            'released_wal_sha256' => hash('sha256', $releasedWalBytes),
            'pinned_reader_page_numbers' => array_column($pinnedRows, 'page_number'),
            'stale_publish_blocked_page_numbers' => array_column($staleBlockedRows, 'page_number'),
            'all_released_pages_from_database' => !in_array(false, array_column($rows, 'released_from_database'), true),
            'operation_reasons' => array_column($operations, 'reason'),
            'operation_ops' => array_column($operations, 'op'),
            'payload_keys' => [$currentPayloadKey, $currentWalPayloadKey, $releasedPayloadKey, $releasedWalPayloadKey],
            'rows' => $rows,
            'publish_transitions' => array_column($rows, 'publish_transition'),
            'publish_digest' => hash('sha256', implode('|', array_column($rows, 'publish_transition'))),
            'operations' => $operations,
            'payloads' => [
                $currentPayloadKey => $currentDatabaseBytes,
                $currentWalPayloadKey => $currentWalBytes,
                $releasedPayloadKey => $releasedDatabaseBytes,
                $releasedWalPayloadKey => $releasedWalBytes,
            ],
            'base_plan' => $base,
            'dependencies' => array_values(array_unique(array_merge(
                $base['dependencies'],
                $currentCheckpoint['dependencies'],
                $releasedCheckpoint['dependencies'],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next165',
                    'sqlite-wal-checkpoint-publish-sequence',
                    'wordpress-import-current-source-checkpoint-publish',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses native PHP hot-journal recovery, WAL savepoint truncation, durable checkpoint, and VFS write-plan payload primitives',
            'non_overlap' => 'extends next162 admission with publish-order payloads and release sequencing; avoids accepted WAL byte truncation, VFS writer/apply, checkpoint transaction, and hot-journal reader-restart surfaces',
        ];
    }

    /**
     * @param array<string,mixed> $base
     */
    private static function checkpointDatabaseBytes(array $base, string $prefix, string $fallback): string
    {
        $hash = (string) ($base[$prefix . '_database_sha256'] ?? '');
        $rows = $base['rows'] ?? [];
        if ($hash === '' || !is_array($rows)) {
            return $fallback;
        }

        return (string) ($base[$prefix . '_database_bytes'] ?? $fallback);
    }
}
