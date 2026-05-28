<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext168Plan
{
    /**
     * @param array<int,string> $hotJournalPages
     * @param array<int,string> $savepointBeforePages
     * @param array<int,array{image:string,source_id?:string,epoch?:int,pinned?:bool,dirty?:bool,label?:string}> $readerCachePages
     * @param list<int> $checkpointPages
     * @param list<array{name:string,source_id?:string,epoch?:int,pinned?:bool,dirty?:bool,closed?:bool}> $readers
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $databaseBytes,
        int $pageSize,
        string $savepoint,
        array $hotJournalPages,
        array $savepointBeforePages,
        SQLiteWal $currentWal,
        string $currentWalBytes,
        SQLiteWal $nextWal,
        string $nextWalBytes,
        array $readerCachePages,
        array $checkpointPages,
        array $readers,
        string $mode = 'restart',
        int $readerEndFrame = 0,
        int $currentSourceEpoch = 1,
        bool $hotJournalExists = true,
        bool $walSidecarExists = true,
        bool $directorySyncRequested = true,
    ): array {
        $base = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext164Plan::plan(
            $databasePath,
            $databaseBytes,
            $pageSize,
            $savepoint,
            $hotJournalPages,
            $savepointBeforePages,
            $currentWal,
            $currentWalBytes,
            $nextWal,
            $nextWalBytes,
            $readerCachePages,
            $checkpointPages,
            $readers,
            $mode,
            $readerEndFrame,
            $currentSourceEpoch
        );

        $currentToken = $base['current_source_token'];
        $nextToken = $base['next_source_token'];
        $currentEpoch = (int) $currentToken['epoch'];
        $nextEpoch = (int) $nextToken['epoch'];
        $walAction = (string) ($base['current_durable']['wal_action'] ?? '');
        $blocked = [];

        if ($base['base_status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-next161') {
            $blocked[] = 'checkpoint_cache_rebase_not_ready';
        }
        if ($base['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-next164') {
            $blocked[] = 'reader_admission_not_ready';
        }
        if ($nextEpoch <= $currentEpoch) {
            $blocked[] = 'next_wal_source_epoch_not_after_checkpoint';
        }
        if (!$hotJournalExists) {
            $blocked[] = 'hot_journal_already_missing_before_publish';
        }
        if (!$walSidecarExists && $walAction === 'preserve_wal') {
            $blocked[] = 'wal_sidecar_missing_for_preserved_reader_snapshot';
        }
        if (!$directorySyncRequested) {
            $blocked[] = 'directory_sync_required_for_hot_journal_checkpoint_publish';
        }

        $publishAllowed = $blocked === [];
        $deleteJournalAllowed = $publishAllowed && $hotJournalExists;
        $resetWalAllowed = $publishAllowed && in_array($walAction, ['restart_wal', 'truncate_wal'], true);
        $preserveWalForReaders = $publishAllowed && $walAction === 'preserve_wal';

        $operations = [];
        $operations[] = [
            'op' => $publishAllowed ? 'publish_checkpoint_current_source_next168' : 'defer_checkpoint_current_source_publish_next168',
            'source_id' => $currentToken['id'],
            'epoch' => $currentEpoch,
            'reason' => $publishAllowed ? 'checkpoint_source_ready_after_hot_journal_savepoint_reader_gate' : implode(',', $blocked),
        ];
        $operations[] = [
            'op' => $deleteJournalAllowed ? 'delete_hot_journal_after_checkpoint_publish_next168' : 'retain_hot_journal_until_checkpoint_publish_next168',
            'path' => $base['journal_path'],
            'reason' => $deleteJournalAllowed ? 'hot_journal_recovery_is_durable_in_checkpoint_source' : 'checkpoint_publish_not_durable',
        ];
        $operations[] = [
            'op' => $resetWalAllowed ? 'reset_wal_sidecar_after_checkpoint_publish_next168' : 'preserve_wal_sidecar_for_readers_next168',
            'path' => $base['wal_path'],
            'wal_action' => $walAction,
            'reason' => $resetWalAllowed ? 'no_reader_blocks_wal_reset_after_publish' : 'wal_frames_remain_visible_or_publish_deferred',
        ];
        $operations[] = [
            'op' => $directorySyncRequested ? 'sync_checkpoint_directory_after_source_publish_next168' : 'defer_directory_sync_after_source_publish_next168',
            'path' => dirname($databasePath),
            'reason' => $directorySyncRequested ? 'directory_entry_changes_must_survive_crash' : 'directory_sync_was_not_requested',
        ];
        $operations[] = [
            'op' => 'publish_next_wal_generation_after_checkpoint_next168',
            'source_id' => $nextToken['id'],
            'epoch' => $nextEpoch,
            'reason' => $publishAllowed ? 'next_generation_follows_checkpoint_source' : 'next_generation_waits_for_checkpoint_publish',
        ];

        $readerRows = [];
        foreach ((array) $base['reader_rows'] as $row) {
            $readerRows[] = [
                'name' => $row['name'],
                'admitted' => $row['admitted'],
                'publish_source' => $row['admitted'] ? $currentToken['id'] : $nextToken['id'],
                'publish_epoch' => $row['admitted'] ? $currentEpoch : $nextEpoch,
                'needs_reopen' => !$row['admitted'],
                'reason' => $row['reason'],
            ];
        }

        return [
            'status' => $publishAllowed
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next168'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next168',
            'reason' => $publishAllowed
                ? 'hot_journal_savepoint_checkpoint_source_publish_is_crash_safe'
                : 'hot_journal_savepoint_checkpoint_source_publish_deferred',
            'database_path' => $databasePath,
            'journal_path' => $base['journal_path'],
            'wal_path' => $base['wal_path'],
            'page_size' => $pageSize,
            'savepoint' => $savepoint,
            'mode' => $base['mode'],
            'reader_end_frame' => $base['reader_end_frame'],
            'base_status' => $base['status'],
            'base_reason' => $base['reason'],
            'checkpoint_current_source_token' => $currentToken,
            'next_wal_source_token' => $nextToken,
            'source_epoch_order_valid' => $nextEpoch > $currentEpoch,
            'publish_allowed' => $publishAllowed,
            'delete_hot_journal_allowed' => $deleteJournalAllowed,
            'reset_wal_allowed' => $resetWalAllowed,
            'preserve_wal_for_readers' => $preserveWalForReaders,
            'requires_directory_sync' => true,
            'directory_sync_requested' => $directorySyncRequested,
            'hot_journal_exists' => $hotJournalExists,
            'wal_sidecar_exists' => $walSidecarExists,
            'blocked_reasons' => $blocked,
            'reader_publish_rows' => $readerRows,
            'reader_publish_sources' => array_column($readerRows, 'publish_source'),
            'reader_publish_epochs' => array_column($readerRows, 'publish_epoch'),
            'reader_reopen_names' => $base['reopen_reader_names'],
            'reader_admitted_names' => $base['admitted_reader_names'],
            'operation_names' => array_column($operations, 'op'),
            'operations' => $operations,
            'base_plan' => $base,
            'source_digest' => hash('sha256', $base['source_digest'] . '|' . implode('|', array_column($operations, 'op')) . '|' . implode('|', $blocked)),
            'dependencies' => array_values(array_unique(array_merge($base['dependencies'], [
                'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next168',
                'sqlite-hot-journal-delete-after-checkpoint-source-publish',
                'sqlite-wal-generation-publish-after-savepoint-checkpoint',
            ]))),
            'dependency_closure' => 'no new support component needed; composes WAL parsing, hot-journal recovery, savepoint rollback, checkpoint source-token fencing, and reader admission gates',
            'non_overlap' => 'does not repeat next161 cache rebasing, next164 reader admission, or VFS byte writes; this slice gates hot-journal deletion and WAL generation publication after the checkpoint current-source is crash-safe',
        ];
    }
}
