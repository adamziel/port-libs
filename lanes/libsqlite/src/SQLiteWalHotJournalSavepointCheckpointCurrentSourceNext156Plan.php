<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext156Plan
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
        int $currentSourceEpoch = 1,
        bool $reservedLock = false,
        bool $superJournalRequired = false,
        bool $superJournalExists = false,
    ): array {
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
            $superJournalExists
        );

        $walPath = $databasePath . '-wal';
        $payloads = $base['base_plan']['payloads'];
        $payloads[$walPath . '#current-source-next156'] = $currentWalBytes;
        $payloads[$walPath . '#next-source-next156'] = $nextWalBytes;

        $operations = array_values(array_filter(
            $base['base_plan']['operations'],
            static fn (array $operation): bool => isset($operation['path']) && (string) $operation['path'] !== ''
        ));
        $operations[] = [
            'op' => 'sync',
            'path' => $databasePath,
            'durable' => true,
            'reason' => 'sync_hot_journal_savepoint_retry_database_before_wal_source_switch_next156',
        ];
        $operations[] = [
            'op' => 'write',
            'path' => $walPath,
            'offset' => 0,
            'bytes' => strlen($currentWalBytes),
            'payload_key' => $walPath . '#current-source-next156',
            'reason' => 'preserve_current_wal_source_for_pinned_reader_before_next_savepoint_retry_next156',
        ];
        $operations[] = [
            'op' => 'truncate',
            'path' => $walPath,
            'bytes' => strlen($currentWalBytes),
            'reason' => 'trim_wal_to_current_source_before_next_savepoint_retry_next156',
        ];
        $operations[] = [
            'op' => 'sync',
            'path' => $walPath,
            'durable' => true,
            'reason' => 'sync_current_wal_source_before_next_savepoint_retry_next156',
        ];
        $operations[] = [
            'op' => 'write',
            'path' => $walPath,
            'offset' => 0,
            'bytes' => strlen($nextWalBytes),
            'payload_key' => $walPath . '#next-source-next156',
            'reason' => 'install_next_wal_source_after_hot_journal_savepoint_retry_next156',
        ];
        $operations[] = [
            'op' => 'truncate',
            'path' => $walPath,
            'bytes' => strlen($nextWalBytes),
            'reason' => 'trim_wal_to_next_source_after_hot_journal_savepoint_retry_next156',
        ];
        $operations[] = [
            'op' => 'sync',
            'path' => $walPath,
            'durable' => true,
            'reason' => 'sync_next_wal_source_after_hot_journal_savepoint_retry_next156',
        ];
        $operations[] = [
            'op' => 'sync_directory',
            'path' => dirname($databasePath),
            'durable' => true,
            'reason' => 'persist_hot_journal_savepoint_checkpoint_current_source_sidecars_next156',
        ];

        $applyBytes = 0;
        foreach ($operations as $operation) {
            if (($operation['op'] ?? '') === 'write' || ($operation['op'] ?? '') === 'truncate') {
                $applyBytes += (int) ($operation['bytes'] ?? 0);
            }
        }

        return [
            'status' => $base['status'] === 'pager-savepoint-wal-hot-journal-current-source-next148'
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next156'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next156',
            'reason' => $base['status'] === 'pager-savepoint-wal-hot-journal-current-source-next148'
                ? 'hot_journal_savepoint_retry_database_synced_current_wal_preserved_next_wal_installed'
                : 'base_current_source_not_ready_for_vfs_apply',
            'database_path' => $databasePath,
            'journal_path' => $base['journal_path'],
            'wal_path' => $walPath,
            'savepoint' => $savepoint,
            'reader_end_frame' => $readerEndFrame,
            'operation_count' => count($operations),
            'apply_byte_count' => $applyBytes,
            'database_sync_count' => count(array_filter(
                $operations,
                static fn (array $operation): bool => ($operation['op'] ?? '') === 'sync' && ($operation['path'] ?? '') === $databasePath
            )),
            'wal_sync_count' => count(array_filter(
                $operations,
                static fn (array $operation): bool => ($operation['op'] ?? '') === 'sync' && ($operation['path'] ?? '') === $walPath
            )),
            'directory_sync_count' => count(array_filter(
                $operations,
                static fn (array $operation): bool => ($operation['op'] ?? '') === 'sync_directory'
            )),
            'current_wal_bytes_length' => strlen($currentWalBytes),
            'next_wal_bytes_length' => strlen($nextWalBytes),
            'current_wal_sha256' => hash('sha256', $currentWalBytes),
            'next_wal_sha256' => hash('sha256', $nextWalBytes),
            'next_replaces_current_wal' => hash('sha256', $currentWalBytes) !== hash('sha256', $nextWalBytes),
            'base_status' => $base['status'],
            'base_reason' => $base['reason'],
            'base_reader_retry_match' => $base['retry_matches_current_reader'],
            'base_next_source_separated' => $base['next_source_separated'],
            'next_separated_page_numbers' => $base['next_separated_page_numbers'],
            'operation_reasons' => array_column($operations, 'reason'),
            'operations' => $operations,
            'payloads' => $payloads,
            'base_plan' => $base,
            'dependencies' => array_values(array_unique(array_merge(
                $base['dependencies'],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next156',
                    'vfs-atomic-wal-source-switch',
                    'sqlite-wal-current-source-durable-apply',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses next148 pager/WAL source validation and existing native PHP VFS atomic operation application',
            'non_overlap' => 'avoids accepted VFS rollback-journal apply, VFS savepoint rollback apply, WAL byte truncation, checkpoint transaction, and next148 reader-source diagnostics by applying the validated current/next WAL source switch as a durable VFS operation sequence',
        ];
    }
}
