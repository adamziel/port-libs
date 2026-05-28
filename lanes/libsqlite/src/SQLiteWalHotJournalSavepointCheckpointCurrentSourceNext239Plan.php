<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext239Plan
{
    /**
     * @param array<string,mixed> $finalizerPlan
     * @param list<array<string,mixed>> $commitReceipts
     * @return array<string,mixed>
     */
    public static function admitAtomicCommitBarrier(array $finalizerPlan, array $commitReceipts): array
    {
        if (($finalizerPlan['status'] ?? null) !== 'wal-hot-journal-savepoint-checkpoint-current-source-next236'
            || ($finalizerPlan['next_writer_allowed'] ?? null) !== true
        ) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next239 requires an admitted next236 finalizer plan');
        }
        if ($commitReceipts === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next239 requires commit receipts');
        }

        $databasePath = self::path($finalizerPlan['database_path'] ?? null, 'database path');
        $walPath = self::path($finalizerPlan['wal_path'] ?? null, 'wal path');
        $journalPath = self::path($finalizerPlan['journal_path'] ?? null, 'journal path');
        $sourceToken = self::token($finalizerPlan['source_token'] ?? null, 'source token');
        $currentGeneration = self::positiveInt($finalizerPlan['current_writer_generation'] ?? null, 'current writer generation');
        $nextGeneration = self::positiveInt($finalizerPlan['next_writer_generation'] ?? null, 'next writer generation');
        if ($nextGeneration <= $currentGeneration) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next239 requires a future writer generation');
        }
        $schemaCookie = self::positiveInt($finalizerPlan['schema_cookie'] ?? null, 'schema cookie');
        $databaseDigest = self::digest($finalizerPlan['database_digest'] ?? null, 'database digest');
        $finalizedStatements = self::stringSet($finalizerPlan['finalized_statement_names'] ?? null, 'finalized statements');

        $rows = [];
        foreach ($commitReceipts as $receipt) {
            $rows[] = self::commitReceiptRow(
                $receipt,
                $databasePath,
                $walPath,
                $journalPath,
                $sourceToken,
                $currentGeneration,
                $nextGeneration,
                $schemaCookie,
                $databaseDigest,
                $finalizedStatements
            );
        }

        $blockedRows = array_values(array_filter($rows, static fn (array $row): bool => !$row['accepted']));
        $kinds = array_values(array_unique(array_column($rows, 'kind')));
        sort($kinds);
        $missingKinds = array_values(array_diff(['database', 'directory', 'journal', 'wal'], $kinds));
        $duplicateNames = self::duplicates(array_column($rows, 'name'));

        $coveredStatements = [];
        foreach ($rows as $row) {
            if ($row['accepted']) {
                foreach ($row['statement_names'] as $statement) {
                    $coveredStatements[$statement] = true;
                }
            }
        }
        ksort($coveredStatements);
        $missingStatements = array_values(array_diff(array_keys($finalizedStatements), array_keys($coveredStatements)));

        $blockedReasons = [];
        foreach ($blockedRows as $row) {
            foreach ($row['blocked_reasons'] as $reason) {
                $blockedReasons[] = $reason;
            }
        }
        if ($missingKinds !== []) {
            $blockedReasons[] = 'atomic_commit_receipt_kind_missing';
        }
        if ($duplicateNames !== []) {
            $blockedReasons[] = 'atomic_commit_receipt_name_duplicate';
        }
        if ($missingStatements !== []) {
            $blockedReasons[] = 'atomic_commit_finalized_statement_missing';
        }
        $blockedReasons = array_values(array_unique($blockedReasons));

        $guardRows = [
            [
                'name' => 'next236_finalizers_admitted',
                'matched' => true,
                'reason' => 'prepared statements were cleanly finalized before the next WAL writer',
            ],
            [
                'name' => 'database_wal_journal_directory_commit_receipts_present',
                'matched' => $missingKinds === [],
                'reason' => 'the checkpoint current source needs database, WAL, journal, and directory commit receipts',
            ],
            [
                'name' => 'commit_receipt_names_unique',
                'matched' => $duplicateNames === [],
                'reason' => 'commit receipts must be uniquely attributable to one atomic commit barrier',
            ],
            [
                'name' => 'all_finalized_statements_covered',
                'matched' => $missingStatements === [],
                'reason' => 'each finalized statement must be covered by an atomic commit receipt before source switch',
            ],
            [
                'name' => 'all_commit_receipts_match_generation_and_digest',
                'matched' => $blockedRows === [],
                'reason' => 'commit receipts must match the source token, generations, schema cookie, digest, and fsync lock evidence',
            ],
        ];
        $blockedGuards = array_values(array_column(
            array_filter($guardRows, static fn (array $row): bool => !$row['matched']),
            'name'
        ));
        $admitted = $blockedGuards === [];

        return [
            'status' => $admitted
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next239'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next239',
            'reason' => $admitted
                ? 'atomic_commit_barrier_admits_checkpoint_current_source'
                : 'atomic_commit_barrier_holds_checkpoint_current_source',
            'base_status' => $finalizerPlan['status'],
            'database_path' => $databasePath,
            'wal_path' => $walPath,
            'journal_path' => $journalPath,
            'source_token' => $sourceToken,
            'current_writer_generation' => $currentGeneration,
            'next_writer_generation' => $nextGeneration,
            'schema_cookie' => $schemaCookie,
            'database_digest' => $databaseDigest,
            'expected_statement_names' => array_keys($finalizedStatements),
            'covered_statement_names' => array_keys($coveredStatements),
            'missing_statement_names' => $missingStatements,
            'commit_rows' => $rows,
            'commit_kinds' => $kinds,
            'missing_commit_kinds' => $missingKinds,
            'duplicate_commit_names' => $duplicateNames,
            'accepted_commit_names' => array_values(array_column(array_filter($rows, static fn (array $row): bool => $row['accepted']), 'name')),
            'blocked_commit_names' => array_values(array_column($blockedRows, 'name')),
            'blocked_commit_reasons' => $blockedReasons,
            'current_source_admitted' => $admitted,
            'reader_action' => $admitted ? 'publish_atomic_current_source_to_reopened_readers' : 'retain_previous_current_source_until_atomic_commit',
            'writer_action' => $admitted ? 'start_next_writer_generation_' . $nextGeneration : 'hold_next_writer_generation_' . $nextGeneration,
            'journal_action' => $admitted ? 'forget_hot_journal_after_atomic_directory_sync' : 'keep_hot_journal_delete_receipt_pending',
            'wal_action' => $admitted ? 'reuse_restarted_wal_after_atomic_commit_barrier' : 'pin_restarted_wal_until_atomic_commit',
            'guard_rows' => $guardRows,
            'guard_names' => array_column($guardRows, 'name'),
            'guard_matches' => array_column($guardRows, 'matched'),
            'blocked_guard_names' => $blockedGuards,
            'barrier_digest' => hash('sha256', json_encode([$sourceToken, $currentGeneration, $nextGeneration, $databaseDigest, $rows, $guardRows], JSON_THROW_ON_ERROR)),
            'operation_names' => array_values(array_unique(array_merge(
                is_array($finalizerPlan['operation_names'] ?? null) ? $finalizerPlan['operation_names'] : [],
                [
                    'verify_atomic_commit_barrier_current_source_next239',
                    $admitted ? 'admit_atomic_checkpoint_current_source_next239' : 'hold_atomic_checkpoint_current_source_next239',
                ]
            ))),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($finalizerPlan['dependencies'] ?? null) ? $finalizerPlan['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next239',
                    'sqlite-wal-atomic-commit-barrier',
                    'wordpress-import-atomic-current-source-switch',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses next236 finalizer admission plus existing native VFS fsync, lock, WAL reset, hot-journal delete, and directory commit receipts',
            'non_overlap' => 'next239 validates an atomic commit barrier after next236 finalizers; it does not repeat durable publication receipts, statement finalizers, reader-slot admission, WAL byte truncation, VFS savepoint rollback, rollback-journal commit/apply, VFS sync apply, super-journal commits, or checkpoint transaction planning',
        ];
    }

    /**
     * @param array<string,mixed> $receipt
     * @param array<string,true> $finalizedStatements
     * @return array<string,mixed>
     */
    private static function commitReceiptRow(
        array $receipt,
        string $databasePath,
        string $walPath,
        string $journalPath,
        string $sourceToken,
        int $currentGeneration,
        int $nextGeneration,
        int $schemaCookie,
        string $databaseDigest,
        array $finalizedStatements
    ): array {
        $name = self::token($receipt['name'] ?? null, 'receipt name');
        $kind = $receipt['kind'] ?? null;
        if (!is_string($kind) || !in_array($kind, ['database', 'wal', 'journal', 'directory'], true)) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next239 receipt kind is invalid');
        }

        $path = self::path($receipt['path'] ?? null, "{$name} path");
        $statements = self::stringSet($receipt['statement_names'] ?? null, "{$name} statements");
        $reasons = [];

        foreach (array_keys($statements) as $statement) {
            if (!isset($finalizedStatements[$statement])) {
                $reasons[] = 'atomic_commit_statement_not_finalized';
            }
        }
        if (self::token($receipt['source_token'] ?? null, "{$name} source token") !== $sourceToken) {
            $reasons[] = 'atomic_commit_source_token_mismatch';
        }
        if (self::positiveInt($receipt['current_generation'] ?? null, "{$name} current generation") !== $currentGeneration) {
            $reasons[] = 'atomic_commit_current_generation_mismatch';
        }
        if (self::positiveInt($receipt['next_generation'] ?? null, "{$name} next generation") !== $nextGeneration) {
            $reasons[] = 'atomic_commit_next_generation_mismatch';
        }
        if (self::positiveInt($receipt['schema_cookie'] ?? null, "{$name} schema cookie") !== $schemaCookie) {
            $reasons[] = 'atomic_commit_schema_cookie_mismatch';
        }
        if (!hash_equals($databaseDigest, self::digest($receipt['database_digest'] ?? null, "{$name} database digest"))) {
            $reasons[] = 'atomic_commit_database_digest_mismatch';
        }
        if (($receipt['exclusive_lock_held'] ?? false) !== true) {
            $reasons[] = 'atomic_commit_exclusive_lock_missing';
        }
        if (($receipt['fsync_complete'] ?? false) !== true) {
            $reasons[] = 'atomic_commit_fsync_missing';
        }

        if ($kind === 'database') {
            if ($path !== $databasePath) {
                $reasons[] = 'atomic_commit_database_path_mismatch';
            }
            if (($receipt['page_images_written'] ?? false) !== true) {
                $reasons[] = 'atomic_commit_database_pages_not_written';
            }
            if (($receipt['header_cookie_persisted'] ?? false) !== true) {
                $reasons[] = 'atomic_commit_schema_cookie_not_persisted';
            }
        } elseif ($kind === 'wal') {
            if ($path !== $walPath) {
                $reasons[] = 'atomic_commit_wal_path_mismatch';
            }
            if (($receipt['mx_frame'] ?? null) !== 0) {
                $reasons[] = 'atomic_commit_wal_not_reset';
            }
            if (($receipt['readmarks_reset'] ?? false) !== true) {
                $reasons[] = 'atomic_commit_readmarks_not_reset';
            }
        } elseif ($kind === 'journal') {
            if ($path !== $journalPath) {
                $reasons[] = 'atomic_commit_journal_path_mismatch';
            }
            if (($receipt['hot_journal_deleted'] ?? false) !== true) {
                $reasons[] = 'atomic_commit_hot_journal_delete_missing';
            }
        } else {
            if ($path !== dirname($databasePath)) {
                $reasons[] = 'atomic_commit_directory_path_mismatch';
            }
            $persisted = self::pathSet($receipt['persisted_paths'] ?? null, "{$name} persisted paths");
            foreach ([$databasePath, $walPath, $journalPath] as $requiredPath) {
                if (!isset($persisted[$requiredPath])) {
                    $reasons[] = 'atomic_commit_directory_missing_path';
                }
            }
        }

        $reasons = array_values(array_unique($reasons));

        return [
            'name' => $name,
            'kind' => $kind,
            'path' => $path,
            'statement_names' => array_keys($statements),
            'source_token' => (string) ($receipt['source_token'] ?? ''),
            'current_generation' => (int) ($receipt['current_generation'] ?? 0),
            'next_generation' => (int) ($receipt['next_generation'] ?? 0),
            'schema_cookie' => (int) ($receipt['schema_cookie'] ?? 0),
            'database_digest' => (string) ($receipt['database_digest'] ?? ''),
            'exclusive_lock_held' => ($receipt['exclusive_lock_held'] ?? false) === true,
            'fsync_complete' => ($receipt['fsync_complete'] ?? false) === true,
            'accepted' => $reasons === [],
            'commit_reason' => $reasons === [] ? 'atomic_commit_receipt_matches_checkpoint_current_source' : implode('|', $reasons),
            'blocked_reasons' => $reasons,
        ];
    }

    private static function path(mixed $value, string $field): string
    {
        if (!is_string($value) || $value === '' || str_contains($value, "\0")) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next239 {$field} is invalid");
        }

        return $value;
    }

    private static function token(mixed $value, string $field): string
    {
        if (!is_string($value) || !preg_match('/^[A-Za-z0-9_.:-]+$/', $value)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next239 {$field} is invalid");
        }

        return $value;
    }

    private static function positiveInt(mixed $value, string $field): int
    {
        if (!is_int($value) || $value <= 0) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next239 {$field} is invalid");
        }

        return $value;
    }

    private static function digest(mixed $value, string $field): string
    {
        if (!is_string($value) || !preg_match('/^[a-f0-9]{64}$/', $value)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next239 {$field} is invalid");
        }

        return $value;
    }

    /**
     * @return array<string,true>
     */
    private static function stringSet(mixed $value, string $field): array
    {
        if (!is_array($value) || $value === []) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next239 {$field} are invalid");
        }

        $set = [];
        foreach ($value as $item) {
            if (!is_string($item) || !preg_match('/^[A-Za-z0-9_.:-]+$/', $item)) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next239 {$field} include an invalid item");
            }
            $set[$item] = true;
        }
        ksort($set);

        return $set;
    }

    /**
     * @return array<string,true>
     */
    private static function pathSet(mixed $value, string $field): array
    {
        if (!is_array($value) || $value === []) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next239 {$field} are invalid");
        }

        $set = [];
        foreach ($value as $item) {
            $set[self::path($item, $field)] = true;
        }
        ksort($set);

        return $set;
    }

    /**
     * @param list<string> $values
     * @return list<string>
     */
    private static function duplicates(array $values): array
    {
        $seen = [];
        $duplicates = [];
        foreach ($values as $value) {
            if (isset($seen[$value])) {
                $duplicates[$value] = true;
            }
            $seen[$value] = true;
        }
        $result = array_keys($duplicates);
        sort($result);

        return $result;
    }
}
