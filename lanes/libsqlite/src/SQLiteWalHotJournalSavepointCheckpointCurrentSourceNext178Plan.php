<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext178Plan
{
    /**
     * @param array<string,mixed> $prepared
     * @param array<string,mixed> $applied
     * @return array<string,mixed>
     */
    public static function plan(array $prepared, array $applied, string $databaseBytes, ?string $journalBytes, string $walBytes): array
    {
        self::assertPrepared($prepared);
        self::assertApplied($applied);

        $databasePath = (string) $prepared['database_path'];
        $journalPath = (string) $prepared['journal_path'];
        $walPath = (string) $prepared['wal_path'];
        $expectedDatabase = self::preparedDurableString($prepared, ['base_plan', 'current_durable', 'database_bytes']);
        $expectedWal = self::preparedDurableString($prepared, ['base_plan', 'next_durable', 'wal_bytes']);
        $databaseMatches = hash_equals(hash('sha256', $expectedDatabase), hash('sha256', $databaseBytes));
        $walMatches = hash_equals(hash('sha256', $expectedWal), hash('sha256', $walBytes));
        $journalRemoved = $journalBytes === null;
        $publication = is_array($applied['publication']) ? $applied['publication'] : [];
        $operations = is_array($applied['operations']) ? $applied['operations'] : [];
        $operationNames = array_values(array_map(static fn (array $row): string => (string) ($row['op'] ?? ''), $operations));
        $requiredOrder = ['write', 'truncate', 'sync', 'delete', 'write', 'truncate', 'sync', 'sync_directory'];
        $orderMatches = $operationNames === $requiredOrder;
        $sourceRows = [
            [
                'name' => 'database',
                'path' => $databasePath,
                'expected_sha256' => hash('sha256', $expectedDatabase),
                'actual_sha256' => hash('sha256', $databaseBytes),
                'expected_length' => strlen($expectedDatabase),
                'actual_length' => strlen($databaseBytes),
                'matches' => $databaseMatches,
            ],
            [
                'name' => 'journal',
                'path' => $journalPath,
                'expected_sha256' => null,
                'actual_sha256' => $journalBytes === null ? null : hash('sha256', $journalBytes),
                'expected_length' => null,
                'actual_length' => $journalBytes === null ? null : strlen($journalBytes),
                'matches' => $journalRemoved,
            ],
            [
                'name' => 'wal',
                'path' => $walPath,
                'expected_sha256' => hash('sha256', $expectedWal),
                'actual_sha256' => hash('sha256', $walBytes),
                'expected_length' => strlen($expectedWal),
                'actual_length' => strlen($walBytes),
                'matches' => $walMatches,
            ],
        ];
        $stale = array_values(array_filter($sourceRows, static fn (array $row): bool => !(bool) $row['matches']));
        $publishable = ($applied['status'] ?? '') === 'applied'
            && ($publication['can_publish'] ?? false) === true
            && $databaseMatches
            && $journalRemoved
            && $walMatches
            && $orderMatches
            && (int) ($applied['durable_syncs'] ?? 0) >= 2
            && (int) ($applied['directory_syncs'] ?? 0) >= 1;

        $blockedReasons = [];
        if (($applied['status'] ?? '') !== 'applied') {
            $blockedReasons[] = 'vfs_publication_not_applied';
        }
        if (($publication['can_publish'] ?? false) !== true) {
            $blockedReasons[] = 'publication_guard_not_satisfied';
        }
        foreach ($stale as $row) {
            $blockedReasons[] = 'stale_' . $row['name'] . '_after_apply';
        }
        if (!$orderMatches) {
            $blockedReasons[] = 'durable_operation_order_mismatch';
        }
        if ((int) ($applied['durable_syncs'] ?? 0) < 2) {
            $blockedReasons[] = 'missing_database_or_wal_sync';
        }
        if ((int) ($applied['directory_syncs'] ?? 0) < 1) {
            $blockedReasons[] = 'missing_directory_sync_after_journal_delete';
        }

        return [
            'status' => $publishable
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next178'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next178',
            'reason' => $publishable
                ? 'post_apply_files_match_guarded_checkpoint_receipt'
                : 'post_apply_files_do_not_match_guarded_checkpoint_receipt',
            'database_path' => $databasePath,
            'journal_path' => $journalPath,
            'wal_path' => $walPath,
            'can_publish_receipt' => $publishable,
            'source_rows' => $sourceRows,
            'matched_source_names' => array_values(array_column(array_filter($sourceRows, static fn (array $row): bool => (bool) $row['matches']), 'name')),
            'stale_source_names' => array_column($stale, 'name'),
            'blocked_reasons' => $blockedReasons,
            'operation_names' => $operationNames,
            'operation_order_matches' => $orderMatches,
            'durable_syncs' => (int) ($applied['durable_syncs'] ?? 0),
            'directory_syncs' => (int) ($applied['directory_syncs'] ?? 0),
            'database_sha256' => hash('sha256', $databaseBytes),
            'wal_sha256' => hash('sha256', $walBytes),
            'receipt_digest' => hash('sha256', implode('|', [
                $databasePath,
                hash('sha256', $databaseBytes),
                $journalRemoved ? 'journal-removed' : hash('sha256', (string) $journalBytes),
                $walPath,
                hash('sha256', $walBytes),
                implode(',', $operationNames),
            ])),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($applied['dependencies'] ?? null) ? $applied['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next178',
                    'sqlite-wal-hot-journal-post-apply-receipt',
                    'wordpress-import-hot-journal-checkpoint-reopen-receipt',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses native WAL checkpoint payloads, hot-journal deletion state, and VFS writer operation receipts',
            'non_overlap' => 'adds post-apply receipt validation after next175 VFS publication; does not repeat next173 hash admission, next174 file replay admission, or next175 file writes',
        ];
    }

    /**
     * @param array<string,mixed> $prepared
     */
    private static function assertPrepared(array $prepared): void
    {
        foreach (['database_path', 'journal_path', 'wal_path', 'base_plan'] as $key) {
            if (!array_key_exists($key, $prepared)) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next178 missing prepared {$key}");
            }
        }
    }

    /**
     * @param array<string,mixed> $applied
     */
    private static function assertApplied(array $applied): void
    {
        foreach (['status', 'publication', 'operations', 'durable_syncs', 'directory_syncs'] as $key) {
            if (!array_key_exists($key, $applied)) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next178 missing applied {$key}");
            }
        }
        if (!is_array($applied['publication']) || !is_array($applied['operations'])) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next178 applied receipt has invalid shape');
        }
    }

    /**
     * @param array<string,mixed> $prepared
     * @param list<string> $path
     */
    private static function preparedDurableString(array $prepared, array $path): string
    {
        $value = $prepared;
        foreach ($path as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next178 prepared durable payload is missing');
            }
            $value = $value[$key];
        }
        if (!is_string($value)) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next178 durable payload must be a string');
        }

        return $value;
    }
}
