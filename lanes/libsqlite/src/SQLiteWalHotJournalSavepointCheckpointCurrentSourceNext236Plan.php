<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext236Plan
{
    /**
     * @param array<string,mixed> $statementPlan
     * @param list<array<string,mixed>> $finalizers
     * @return array<string,mixed>
     */
    public static function finalizeForNextWriter(array $statementPlan, array $finalizers, int $nextWriterGeneration): array
    {
        if (($statementPlan['status'] ?? null) !== 'wal-hot-journal-savepoint-checkpoint-current-source-next233') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next236 requires an admitted next233 statement plan');
        }
        if (($statementPlan['statement_admission_allowed'] ?? null) !== true) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next236 requires admitted prepared statements');
        }
        if ($finalizers === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next236 requires statement finalizer receipts');
        }
        if ($nextWriterGeneration <= 0) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next236 requires a positive next writer generation');
        }

        $sourceToken = self::token($statementPlan['source_token'] ?? null, 'source token');
        $currentGeneration = self::positiveInt($statementPlan, 'next_writer_generation');
        if ($nextWriterGeneration <= $currentGeneration) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next236 requires a future writer generation');
        }
        $schemaCookie = self::positiveInt($statementPlan, 'schema_cookie');
        $databaseDigest = self::digest($statementPlan['database_digest'] ?? null, 'database digest');
        $admittedStatements = self::stringSet($statementPlan['admitted_statement_names'] ?? null, 'admitted statements');

        $rows = [];
        foreach ($finalizers as $finalizer) {
            $rows[] = self::finalizerRow($finalizer, $sourceToken, $currentGeneration, $schemaCookie, $databaseDigest, $admittedStatements);
        }

        $blockedRows = array_values(array_filter($rows, static fn (array $row): bool => !$row['admitted']));
        $blockedReasons = [];
        foreach ($blockedRows as $row) {
            foreach ($row['blocked_reasons'] as $reason) {
                $blockedReasons[] = $reason;
            }
        }
        $blockedReasons = array_values(array_unique($blockedReasons));

        $finalizedStatements = [];
        foreach ($rows as $row) {
            if ($row['admitted']) {
                $finalizedStatements[$row['statement_name']] = true;
            }
        }
        ksort($finalizedStatements);
        $missingStatements = array_values(array_diff(array_keys($admittedStatements), array_keys($finalizedStatements)));

        $guardRows = [
            [
                'name' => 'next233_statements_admitted',
                'matched' => true,
                'reason' => 'prepared statements were admitted against the checkpoint current source',
            ],
            [
                'name' => 'all_admitted_statements_finalized',
                'matched' => $missingStatements === [],
                'reason' => 'each admitted statement must finalize before the next WAL writer reuses the source',
            ],
            [
                'name' => 'all_finalizers_current_and_clean',
                'matched' => $blockedRows === [],
                'reason' => 'finalizers must observe matching source token, generation, schema cookie, digest, reader lease, and WAL hook receipts',
            ],
        ];
        $blockedGuards = array_values(array_column(
            array_filter($guardRows, static fn (array $row): bool => !$row['matched']),
            'name'
        ));
        $admitted = $blockedGuards === [];

        return [
            'status' => $admitted
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next236'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next236',
            'reason' => $admitted
                ? 'finalized_statements_release_checkpoint_current_source_to_next_writer'
                : 'finalized_statements_hold_checkpoint_current_source_before_next_writer',
            'base_status' => $statementPlan['status'],
            'database_path' => $statementPlan['database_path'] ?? null,
            'journal_path' => $statementPlan['journal_path'] ?? null,
            'wal_path' => $statementPlan['wal_path'] ?? null,
            'source_token' => $sourceToken,
            'current_writer_generation' => $currentGeneration,
            'next_writer_generation' => $nextWriterGeneration,
            'schema_cookie' => $schemaCookie,
            'database_digest' => $databaseDigest,
            'expected_statement_names' => array_keys($admittedStatements),
            'finalized_statement_names' => array_keys($finalizedStatements),
            'missing_statement_names' => $missingStatements,
            'finalizer_rows' => $rows,
            'admitted_finalizer_names' => array_values(array_column(array_filter($rows, static fn (array $row): bool => $row['admitted']), 'name')),
            'blocked_finalizer_names' => array_values(array_column($blockedRows, 'name')),
            'blocked_finalizer_reasons' => $blockedReasons,
            'next_writer_allowed' => $admitted,
            'writer_action' => $admitted ? 'open_next_wal_writer_generation_' . $nextWriterGeneration : 'hold_next_wal_writer_until_statement_finalizers',
            'reader_action' => $admitted ? 'release_checkpoint_reader_leases' : 'retain_checkpoint_reader_leases',
            'wal_hook_action' => $admitted ? 'publish_wal_hook_checkpoint_summary' : 'defer_wal_hook_until_clean_finalizers',
            'autocheckpoint_action' => $admitted ? 'permit_autocheckpoint_after_next_writer' : 'suppress_autocheckpoint_before_finalizers',
            'guard_rows' => $guardRows,
            'guard_names' => array_column($guardRows, 'name'),
            'guard_matches' => array_column($guardRows, 'matched'),
            'blocked_guard_names' => $blockedGuards,
            'handoff_digest' => hash('sha256', json_encode([$sourceToken, $currentGeneration, $nextWriterGeneration, $rows, $missingStatements], JSON_THROW_ON_ERROR)),
            'operation_names' => array_values(array_unique(array_merge(
                is_array($statementPlan['operation_names'] ?? null) ? $statementPlan['operation_names'] : [],
                [
                    'verify_statement_finalizers_before_next_wal_writer_next236',
                    $admitted ? 'admit_next_wal_writer_after_checkpoint_finalizers_next236' : 'hold_next_wal_writer_after_checkpoint_finalizers_next236',
                ]
            ))),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($statementPlan['dependencies'] ?? null) ? $statementPlan['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next236',
                    'sqlite-wal-checkpoint-statement-finalizer-handoff',
                    'wordpress-import-checkpoint-finalizer-before-next-writer',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses next233 statement admission, current-source metadata, reader lease receipts, and WAL hook/autocheckpoint fences',
            'non_overlap' => 'next236 finalizes admitted statements before the next WAL writer opens; it does not repeat checkpoint reset admission, publication receipts, reopened handle coverage, prepared-statement admission, WAL byte truncation, VFS savepoint rollback apply, rollback-journal commit/apply, or checkpoint transaction planning',
        ];
    }

    /**
     * @param array<string,mixed> $finalizer
     * @param array<string,true> $admittedStatements
     * @return array<string,mixed>
     */
    private static function finalizerRow(array $finalizer, string $sourceToken, int $generation, int $schemaCookie, string $databaseDigest, array $admittedStatements): array
    {
        $name = self::token($finalizer['name'] ?? null, 'finalizer name');
        $statementName = self::token($finalizer['statement_name'] ?? null, "{$name} statement name");
        $observedSource = self::token($finalizer['source_token'] ?? null, "{$name} source token");
        $observedGeneration = self::intField($finalizer, 'generation', $name);
        $observedSchemaCookie = self::intField($finalizer, 'schema_cookie', $name);
        $observedDigest = self::digest($finalizer['database_digest'] ?? null, "{$name} database digest");

        $reasons = [];
        if (!isset($admittedStatements[$statementName])) {
            $reasons[] = 'finalizer_statement_not_admitted';
        }
        if ($observedSource !== $sourceToken) {
            $reasons[] = 'finalizer_source_token_mismatch';
        }
        if ($observedGeneration !== $generation) {
            $reasons[] = 'finalizer_generation_mismatch';
        }
        if ($observedSchemaCookie !== $schemaCookie) {
            $reasons[] = 'finalizer_schema_cookie_mismatch';
        }
        if (!hash_equals($databaseDigest, $observedDigest)) {
            $reasons[] = 'finalizer_database_digest_mismatch';
        }
        if (($finalizer['sqlite_done_seen'] ?? false) !== true) {
            $reasons[] = 'finalizer_missing_sqlite_done';
        }
        if (($finalizer['reset_called'] ?? false) !== true) {
            $reasons[] = 'finalizer_missing_reset';
        }
        if (($finalizer['reader_lease_released'] ?? false) !== true) {
            $reasons[] = 'finalizer_reader_lease_not_released';
        }
        if (($finalizer['wal_hook_receipt'] ?? false) !== true) {
            $reasons[] = 'finalizer_wal_hook_receipt_missing';
        }
        if (($finalizer['autocheckpoint_receipt'] ?? false) !== true) {
            $reasons[] = 'finalizer_autocheckpoint_receipt_missing';
        }
        if (($finalizer['hot_journal_present'] ?? false) === true) {
            $reasons[] = 'finalizer_hot_journal_still_visible';
        }
        if ((int) ($finalizer['savepoint_depth'] ?? 0) !== 0) {
            $reasons[] = 'finalizer_savepoint_scope_open';
        }
        if (($finalizer['dirty_reader_cache'] ?? false) === true) {
            $reasons[] = 'finalizer_dirty_reader_cache';
        }

        $reasons = array_values(array_unique($reasons));

        return [
            'name' => $name,
            'statement_name' => $statementName,
            'source_token' => $observedSource,
            'generation' => $observedGeneration,
            'schema_cookie' => $observedSchemaCookie,
            'database_digest' => $observedDigest,
            'sqlite_done_seen' => ($finalizer['sqlite_done_seen'] ?? false) === true,
            'reset_called' => ($finalizer['reset_called'] ?? false) === true,
            'reader_lease_released' => ($finalizer['reader_lease_released'] ?? false) === true,
            'wal_hook_receipt' => ($finalizer['wal_hook_receipt'] ?? false) === true,
            'autocheckpoint_receipt' => ($finalizer['autocheckpoint_receipt'] ?? false) === true,
            'hot_journal_present' => ($finalizer['hot_journal_present'] ?? false) === true,
            'savepoint_depth' => (int) ($finalizer['savepoint_depth'] ?? 0),
            'dirty_reader_cache' => ($finalizer['dirty_reader_cache'] ?? false) === true,
            'admitted' => $reasons === [],
            'finalizer_reason' => $reasons === [] ? 'finalizer_releases_checkpoint_current_source' : implode('|', $reasons),
            'blocked_reasons' => $reasons,
        ];
    }

    /**
     * @param array<string,mixed> $values
     */
    private static function positiveInt(array $values, string $key): int
    {
        $value = $values[$key] ?? null;
        if (!is_int($value) || $value <= 0) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next236 requires positive {$key}");
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $values
     */
    private static function intField(array $values, string $key, string $name): int
    {
        $value = $values[$key] ?? null;
        if (!is_int($value) || $value <= 0) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next236 {$name} {$key} is invalid");
        }

        return $value;
    }

    private static function digest(mixed $value, string $name): string
    {
        if (!is_string($value) || !preg_match('/^[a-f0-9]{64}$/', $value)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next236 requires {$name}");
        }

        return $value;
    }

    private static function token(mixed $value, string $name): string
    {
        if (!is_string($value) || !preg_match('/^[A-Za-z0-9_.:-]+$/', $value)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next236 requires {$name}");
        }

        return $value;
    }

    /**
     * @return array<string,true>
     */
    private static function stringSet(mixed $values, string $name): array
    {
        if (!is_array($values) || $values === []) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next236 requires {$name}");
        }

        $set = [];
        foreach ($values as $value) {
            $set[self::token($value, $name)] = true;
        }

        return $set;
    }
}
