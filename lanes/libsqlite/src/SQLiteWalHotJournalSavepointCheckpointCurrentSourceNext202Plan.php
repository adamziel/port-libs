<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext202Plan
{
    /**
     * @param array<string,mixed> $sidecarPublication
     * @param list<array<string,mixed>> $handles
     * @return array<string,mixed>
     */
    public static function plan(
        array $sidecarPublication,
        string $checkpointedDatabaseBytes,
        string $hotJournalBytes,
        bool $savepointReleased,
        bool $exclusiveCheckpointLock,
        bool $databaseSyncReceipt,
        bool $walSyncReceipt,
        bool $directorySyncReceipt,
        array $handles
    ): array {
        self::assertPublication($sidecarPublication);
        if ($checkpointedDatabaseBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next202 requires checkpointed database bytes');
        }
        if ($handles === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next202 requires handle rows');
        }

        $databaseDigest = hash('sha256', $checkpointedDatabaseBytes);
        $hotJournalDigest = hash('sha256', $hotJournalBytes);
        $expectedDatabaseDigest = (string) ($sidecarPublication['checkpointed_database_digest'] ?? $databaseDigest);
        $expectedWalDigest = (string) $sidecarPublication['persisted_wal_digest'];
        $expectedSidecarDigest = (string) $sidecarPublication['sidecar_digest'];
        $expectedMode = (string) $sidecarPublication['mode'];
        $baseOperations = is_array($sidecarPublication['operation_names'] ?? null)
            ? $sidecarPublication['operation_names']
            : [];

        $rows = [];
        foreach ($handles as $index => $handle) {
            $rows[] = self::handleRow(
                $handle,
                $index,
                $expectedDatabaseDigest,
                $expectedWalDigest,
                $expectedSidecarDigest,
                $expectedMode,
                $hotJournalBytes,
                $savepointReleased,
                $exclusiveCheckpointLock,
                $databaseSyncReceipt,
                $walSyncReceipt,
                $directorySyncReceipt
            );
        }

        $admitted = array_values(array_column(array_filter($rows, static fn (array $row): bool => $row['admitted']), 'name'));
        $reopened = array_values(array_column(array_filter($rows, static fn (array $row): bool => !$row['admitted']), 'name'));
        $guards = [
            [
                'name' => 'next196_sidecar_publication',
                'matched' => ($sidecarPublication['status'] ?? '') === 'wal-hot-journal-savepoint-checkpoint-current-source-next196',
                'reason' => 'WAL sidecar publication must have matched the checkpoint mode before current handles are reused',
            ],
            [
                'name' => 'checkpointed_database_digest',
                'matched' => hash_equals($expectedDatabaseDigest, $databaseDigest),
                'reason' => 'database image digest must match the checkpointed page publication used by the handles',
            ],
            [
                'name' => 'hot_journal_removed',
                'matched' => $hotJournalBytes === '',
                'reason' => 'hot rollback journal bytes must be absent before current-source handles are admitted',
            ],
            [
                'name' => 'savepoint_released',
                'matched' => $savepointReleased,
                'reason' => 'savepoint rollback state must be closed before the checkpoint generation becomes reusable',
            ],
            [
                'name' => 'exclusive_checkpoint_lock',
                'matched' => $exclusiveCheckpointLock,
                'reason' => 'checkpoint publication requires the exclusive checkpoint lock receipt',
            ],
            [
                'name' => 'sync_receipts',
                'matched' => $databaseSyncReceipt && $walSyncReceipt && $directorySyncReceipt,
                'reason' => 'database, WAL sidecar, and containing directory sync receipts must all be present',
            ],
            [
                'name' => 'handle_mix',
                'matched' => $admitted !== [] && $reopened !== [],
                'reason' => 'the plan must retain matching handles and reopen stale handles',
            ],
        ];
        $blocked = array_values(array_column(
            array_filter($guards, static fn (array $row): bool => !$row['matched']),
            'name'
        ));
        $ready = $blocked === [];

        return [
            'status' => $ready
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next202'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next202',
            'reason' => $ready
                ? 'checkpoint_file_receipts_admit_current_source_handles_after_hot_journal_savepoint'
                : 'checkpoint_file_receipts_block_current_source_handles_after_hot_journal_savepoint',
            'database_path' => $sidecarPublication['database_path'] ?? null,
            'journal_path' => $sidecarPublication['journal_path'] ?? null,
            'wal_path' => $sidecarPublication['wal_path'] ?? null,
            'mode' => $expectedMode,
            'database_digest' => $databaseDigest,
            'expected_database_digest' => $expectedDatabaseDigest,
            'persisted_wal_digest' => $expectedWalDigest,
            'sidecar_digest' => $expectedSidecarDigest,
            'hot_journal_digest' => $hotJournalDigest,
            'hot_journal_bytes_length' => strlen($hotJournalBytes),
            'savepoint_released' => $savepointReleased,
            'exclusive_checkpoint_lock' => $exclusiveCheckpointLock,
            'database_sync_receipt' => $databaseSyncReceipt,
            'wal_sync_receipt' => $walSyncReceipt,
            'directory_sync_receipt' => $directorySyncReceipt,
            'handle_rows' => $rows,
            'admitted_handle_names' => $admitted,
            'reopen_handle_names' => $reopened,
            'guard_rows' => $guards,
            'guard_names' => array_column($guards, 'name'),
            'guard_matches' => array_column($guards, 'matched'),
            'blocked_guard_names' => $blocked,
            'operation_names' => array_values(array_unique(array_merge(
                $baseOperations,
                [
                    'verify_checkpoint_file_receipts_current_source_next202',
                    'publish_current_source_file_receipts_next202',
                    'admit_or_reopen_current_source_handles_next202',
                ]
            ))),
            'receipt_digest' => hash('sha256', json_encode([
                'database' => $databaseDigest,
                'wal' => $expectedWalDigest,
                'sidecar' => $expectedSidecarDigest,
                'hot' => $hotJournalDigest,
                'released' => $savepointReleased,
                'lock' => $exclusiveCheckpointLock,
                'sync' => [$databaseSyncReceipt, $walSyncReceipt, $directorySyncReceipt],
                'rows' => $rows,
            ], JSON_THROW_ON_ERROR)),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($sidecarPublication['dependencies'] ?? null) ? $sidecarPublication['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next202',
                    'sqlite-wal-checkpoint-file-receipt-admission',
                    'wordpress-import-hot-journal-savepoint-checkpoint-handle-reopen',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses accepted WAL sidecar publication plus lane-local file receipt, lock receipt, sync receipt, and handle cache metadata',
            'non_overlap' => 'next202 admits current-source file handles after next196 sidecar publication by checking database/WAL/hot-journal/savepoint/sync receipts; it does not repeat WAL byte truncation, rollback-journal apply, VFS savepoint rollback, checkpoint transaction planning, VFS sync apply, or next196 sidecar digest classification',
        ];
    }

    /**
     * @param array<string,mixed> $publication
     */
    private static function assertPublication(array $publication): void
    {
        foreach (['status', 'mode', 'persisted_wal_digest', 'sidecar_digest'] as $key) {
            if (!array_key_exists($key, $publication)) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next202 missing publication {$key}");
            }
        }
        foreach (['mode', 'persisted_wal_digest', 'sidecar_digest'] as $key) {
            if (!is_string($publication[$key]) || $publication[$key] === '') {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next202 publication {$key} must be a non-empty string");
            }
        }
        if (!in_array($publication['mode'], ['truncate', 'restart', 'preserve_busy'], true)) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next202 publication mode is invalid');
        }
    }

    /**
     * @param array<string,mixed> $handle
     * @return array<string,mixed>
     */
    private static function handleRow(
        array $handle,
        int $index,
        string $expectedDatabaseDigest,
        string $expectedWalDigest,
        string $expectedSidecarDigest,
        string $expectedMode,
        string $hotJournalBytes,
        bool $savepointReleased,
        bool $exclusiveCheckpointLock,
        bool $databaseSyncReceipt,
        bool $walSyncReceipt,
        bool $directorySyncReceipt
    ): array {
        foreach (['name', 'kind', 'observed_database_digest', 'observed_wal_digest', 'observed_sidecar_digest', 'observed_mode'] as $key) {
            if (!array_key_exists($key, $handle)) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next202 missing handle {$key}");
            }
        }

        $name = (string) $handle['name'];
        $kind = (string) $handle['kind'];
        if ($name === '' || !in_array($kind, ['statement', 'reader', 'writer'], true)) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next202 handle name/kind is invalid');
        }

        $checks = [
            'database_digest' => hash_equals($expectedDatabaseDigest, (string) $handle['observed_database_digest']),
            'wal_digest' => hash_equals($expectedWalDigest, (string) $handle['observed_wal_digest']),
            'sidecar_digest' => hash_equals($expectedSidecarDigest, (string) $handle['observed_sidecar_digest']),
            'checkpoint_mode' => (string) $handle['observed_mode'] === $expectedMode,
            'hot_journal_removed' => $hotJournalBytes === '',
            'savepoint_released' => $savepointReleased,
            'exclusive_checkpoint_lock' => $exclusiveCheckpointLock,
            'database_sync_receipt' => $databaseSyncReceipt,
            'wal_sync_receipt' => $walSyncReceipt,
            'directory_sync_receipt' => $directorySyncReceipt,
            'not_dirty' => ($handle['dirty'] ?? false) !== true,
            'not_closed' => ($handle['closed'] ?? false) !== true,
        ];
        if (($handle['requires_wal_sidecar'] ?? false) === true && $expectedMode === 'truncate') {
            $checks['wal_sidecar_required_after_truncate'] = false;
        }

        $failed = array_keys(array_filter($checks, static fn (bool $passed): bool => !$passed));
        $admitted = $failed === [];

        return [
            'ordinal' => $index,
            'name' => $name,
            'kind' => $kind,
            'admitted' => $admitted,
            'requires_reopen' => !$admitted,
            'failed_checks' => $failed,
            'reason' => $admitted
                ? 'handle_receipts_match_checkpoint_current_source'
                : 'handle_must_reopen_for_checkpoint_current_source',
            'transition' => $name . '>' . ($admitted ? 'admit-current-source-handle' : 'reopen-current-source-handle') . ':next202',
            'observed_database_digest' => (string) $handle['observed_database_digest'],
            'observed_wal_digest' => (string) $handle['observed_wal_digest'],
            'observed_sidecar_digest' => (string) $handle['observed_sidecar_digest'],
            'observed_mode' => (string) $handle['observed_mode'],
            'requires_wal_sidecar' => (bool) ($handle['requires_wal_sidecar'] ?? false),
        ];
    }
}
