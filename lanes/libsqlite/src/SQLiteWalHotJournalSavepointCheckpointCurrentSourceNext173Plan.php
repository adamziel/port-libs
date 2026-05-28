<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext173Plan
{
    /**
     * @param array<string,mixed> $prepared
     * @return array<string,mixed>
     */
    public static function plan(
        array $prepared,
        string $databaseBytes,
        string $journalBytes,
        string $walBytes,
        ?string $expectedDatabaseHash = null,
        ?string $expectedJournalHash = null,
        ?string $expectedWalHash = null,
        bool $readerDrained = true
    ): array {
        self::assertPrepared($prepared);
        if ($journalBytes === '' || $walBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next173 requires journal and WAL bytes');
        }

        $paths = [
            'database' => (string) $prepared['database_path'],
            'journal' => (string) $prepared['journal_path'],
            'wal' => (string) $prepared['wal_path'],
        ];
        $actualHashes = [
            'database' => hash('sha256', $databaseBytes),
            'journal' => hash('sha256', $journalBytes),
            'wal' => hash('sha256', $walBytes),
        ];
        $expectedHashes = [
            'database' => $expectedDatabaseHash ?? $actualHashes['database'],
            'journal' => $expectedJournalHash ?? $actualHashes['journal'],
            'wal' => $expectedWalHash ?? $actualHashes['wal'],
        ];

        $sourceRows = [];
        foreach (['database', 'journal', 'wal'] as $name) {
            $sourceRows[] = [
                'name' => $name,
                'path' => $paths[$name],
                'expected_hash' => $expectedHashes[$name],
                'actual_hash' => $actualHashes[$name],
                'matched' => $expectedHashes[$name] === $actualHashes[$name],
                'bytes' => strlen($name === 'database' ? $databaseBytes : ($name === 'journal' ? $journalBytes : $walBytes)),
            ];
        }

        $matched = array_values(array_filter($sourceRows, static fn (array $row): bool => (bool) $row['matched']));
        $stale = array_values(array_filter($sourceRows, static fn (array $row): bool => !(bool) $row['matched']));
        $publicationReady = $prepared['status'] === 'wal-hot-journal-savepoint-checkpoint-current-source-next167'
            && $stale === []
            && $readerDrained;

        $checkpointDatabaseBytes = self::durablePayloadLength($prepared, ['base_plan', 'current_durable', 'database_bytes'], strlen($databaseBytes));
        $checkpointWalBytes = self::durablePayloadLength($prepared, ['base_plan', 'next_durable', 'wal_bytes'], strlen($walBytes));
        $operations = $publicationReady ? [
            [
                'op' => 'write',
                'path' => $paths['database'],
                'bytes' => $checkpointDatabaseBytes,
                'durable' => false,
                'reason' => 'publish_hot_journal_savepoint_checkpoint_database_current_source_next173',
            ],
            [
                'op' => 'truncate',
                'path' => $paths['database'],
                'bytes' => $checkpointDatabaseBytes,
                'durable' => false,
                'reason' => 'trim_checkpoint_database_current_source_next173',
            ],
            [
                'op' => 'sync',
                'path' => $paths['database'],
                'bytes' => 0,
                'durable' => true,
                'reason' => 'sync_checkpoint_database_current_source_next173',
            ],
            [
                'op' => 'delete',
                'path' => $paths['journal'],
                'bytes' => 0,
                'durable' => false,
                'reason' => 'delete_hot_journal_after_current_source_match_next173',
            ],
            [
                'op' => 'write',
                'path' => $paths['wal'],
                'bytes' => $checkpointWalBytes,
                'durable' => false,
                'reason' => 'publish_next_wal_after_checkpoint_current_source_next173',
            ],
            [
                'op' => 'truncate',
                'path' => $paths['wal'],
                'bytes' => $checkpointWalBytes,
                'durable' => false,
                'reason' => 'trim_next_wal_after_checkpoint_current_source_next173',
            ],
            [
                'op' => 'sync',
                'path' => $paths['wal'],
                'bytes' => 0,
                'durable' => true,
                'reason' => 'sync_next_wal_after_checkpoint_current_source_next173',
            ],
            [
                'op' => 'sync_directory',
                'path' => dirname($paths['database']),
                'bytes' => 0,
                'durable' => true,
                'reason' => 'persist_hot_journal_savepoint_checkpoint_current_source_next173',
            ],
        ] : [];

        $blockedReasons = [];
        if ($prepared['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-next167') {
            $blockedReasons[] = 'prepared_publication_guard_not_ready';
        }
        foreach ($stale as $row) {
            $blockedReasons[] = 'stale_' . $row['name'] . '_source_hash';
        }
        if (!$readerDrained) {
            $blockedReasons[] = 'reader_still_pinned_before_checkpoint_publish';
        }

        return [
            'status' => $publicationReady
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next173'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next173',
            'reason' => $publicationReady
                ? 'filesystem_current_source_hashes_match_guarded_wal_checkpoint_publication'
                : 'filesystem_current_source_hashes_block_guarded_wal_checkpoint_publication',
            'database_path' => $paths['database'],
            'journal_path' => $paths['journal'],
            'wal_path' => $paths['wal'],
            'reader_drained' => $readerDrained,
            'prepared_status' => $prepared['status'],
            'publication_fingerprint' => $prepared['publication_fingerprint'],
            'current_source_token' => $prepared['current_source_token'],
            'next_source_token' => $prepared['next_source_token'],
            'source_rows' => $sourceRows,
            'matched_source_names' => array_column($matched, 'name'),
            'stale_source_names' => array_column($stale, 'name'),
            'blocked_reasons' => $blockedReasons,
            'can_publish' => $publicationReady,
            'operations' => $operations,
            'operation_names' => array_column($operations, 'op'),
            'operation_reasons' => array_column($operations, 'reason'),
            'durable_operation_count' => count(array_filter($operations, static fn (array $row): bool => (bool) $row['durable'])),
            'write_bytes' => array_sum(array_map(static fn (array $row): int => $row['op'] === 'write' ? (int) $row['bytes'] : 0, $operations)),
            'truncate_bytes' => array_sum(array_map(static fn (array $row): int => $row['op'] === 'truncate' ? (int) $row['bytes'] : 0, $operations)),
            'delete_count' => count(array_filter($operations, static fn (array $row): bool => $row['op'] === 'delete')),
            'dependencies' => array_values(array_unique(array_merge($prepared['dependencies'], [
                'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next173',
                'sqlite-vfs-current-source-hash-admission',
            ]))),
            'dependency_closure' => 'no new support component needed; reuses current-source WAL publication guards and existing VFS write ordering primitives',
            'non_overlap' => 'does not repeat next167 token/fingerprint admission or VFS savepoint rollback; this slice adds filesystem byte-hash admission before durable checkpoint publication',
        ];
    }

    /**
     * @param array<string,mixed> $prepared
     */
    private static function assertPrepared(array $prepared): void
    {
        foreach (['status', 'database_path', 'journal_path', 'wal_path', 'publication_fingerprint', 'current_source_token', 'next_source_token', 'dependencies'] as $key) {
            if (!array_key_exists($key, $prepared)) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next173 missing prepared {$key}");
            }
        }
        if (!is_array($prepared['dependencies']) || !is_array($prepared['current_source_token']) || !is_array($prepared['next_source_token'])) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next173 prepared plan has invalid token/dependency shape');
        }
    }

    /**
     * @param array<string,mixed> $prepared
     * @param list<string> $path
     */
    private static function durablePayloadLength(array $prepared, array $path, int $fallback): int
    {
        $value = $prepared;
        foreach ($path as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return $fallback;
            }
            $value = $value[$key];
        }

        return is_string($value) ? strlen($value) : $fallback;
    }
}
