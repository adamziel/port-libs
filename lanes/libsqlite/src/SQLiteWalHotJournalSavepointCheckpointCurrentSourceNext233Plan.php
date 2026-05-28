<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext233Plan
{
    /**
     * @param array<string,mixed> $handlePlan
     * @param list<array<string,mixed>> $statements
     * @param array<int,string> $expectedRootPageDigests
     * @return array<string,mixed>
     */
    public static function admitStatements(array $handlePlan, array $statements, array $expectedRootPageDigests): array
    {
        if (($handlePlan['status'] ?? null) !== 'wal-hot-journal-savepoint-checkpoint-current-source-next229') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next233 requires an admitted next229 handle plan');
        }
        if (($handlePlan['current_source_admitted'] ?? null) !== true) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next233 requires admitted current source handles');
        }
        if ($statements === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next233 requires statement rows');
        }

        $sourceToken = self::token($handlePlan['source_token'] ?? null, 'source token');
        $generation = self::positiveInt($handlePlan, 'next_writer_generation');
        $databaseDigest = self::digest($handlePlan['database_digest'] ?? null, 'database digest');
        $schemaCookie = self::positiveInt($handlePlan, 'schema_cookie');
        $rootPageDigests = self::pageDigestMap($expectedRootPageDigests, 'expected root pages');
        $admittedHandles = self::stringSet($handlePlan['admitted_handle_names'] ?? null, 'admitted handles');

        $rows = [];
        foreach ($statements as $statement) {
            $rows[] = self::statementRow($statement, $sourceToken, $generation, $databaseDigest, $schemaCookie, $rootPageDigests, $admittedHandles);
        }

        $blockedRows = array_values(array_filter($rows, static fn (array $row): bool => !$row['admitted']));
        $blockedReasons = [];
        foreach ($blockedRows as $row) {
            foreach ($row['blocked_reasons'] as $reason) {
                $blockedReasons[] = $reason;
            }
        }
        $blockedReasons = array_values(array_unique($blockedReasons));

        $coveredRoots = [];
        foreach ($rows as $row) {
            if (!$row['admitted']) {
                continue;
            }
            foreach ($row['root_pages'] as $pageNumber) {
                $coveredRoots[$pageNumber] = true;
            }
        }
        ksort($coveredRoots);
        $missingRoots = array_values(array_diff(array_keys($rootPageDigests), array_keys($coveredRoots)));

        $guardRows = [
            [
                'name' => 'next229_handles_admitted',
                'matched' => true,
                'reason' => 'checkpoint current-source handles were admitted by next229',
            ],
            [
                'name' => 'all_statement_sources_current',
                'matched' => $blockedRows === [],
                'reason' => 'prepared statements must bind the published source token, generation, schema cookie, and clean handles',
            ],
            [
                'name' => 'all_root_pages_covered',
                'matched' => $missingRoots === [],
                'reason' => 'each checkpointed root page must be represented by an admitted statement before current-source reuse',
            ],
        ];
        $blockedGuards = array_values(array_column(
            array_filter($guardRows, static fn (array $row): bool => !$row['matched']),
            'name'
        ));
        $admitted = $blockedGuards === [];

        return [
            'status' => $admitted
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next233'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next233',
            'reason' => $admitted
                ? 'prepared_statements_reuse_checkpoint_current_source_after_hot_journal_savepoint'
                : 'prepared_statements_hold_checkpoint_current_source_after_hot_journal_savepoint',
            'base_status' => $handlePlan['status'],
            'database_path' => $handlePlan['database_path'] ?? null,
            'journal_path' => $handlePlan['journal_path'] ?? null,
            'wal_path' => $handlePlan['wal_path'] ?? null,
            'source_token' => $sourceToken,
            'next_writer_generation' => $generation,
            'schema_cookie' => $schemaCookie,
            'database_digest' => $databaseDigest,
            'expected_root_pages' => array_keys($rootPageDigests),
            'covered_root_pages' => array_keys($coveredRoots),
            'missing_root_pages' => $missingRoots,
            'statement_rows' => $rows,
            'admitted_statement_names' => array_values(array_column(array_filter($rows, static fn (array $row): bool => $row['admitted']), 'name')),
            'blocked_statement_names' => array_values(array_column($blockedRows, 'name')),
            'blocked_statement_reasons' => $blockedReasons,
            'statement_admission_allowed' => $admitted,
            'statement_action' => $admitted ? 'reuse_prepared_statements_on_checkpoint_current_source' : 'expire_prepared_statements_before_next_step',
            'pager_action' => $admitted ? 'serve_pages_from_reopened_checkpoint_handles' : 'force_reopen_and_schema_recheck',
            'guard_rows' => $guardRows,
            'guard_names' => array_column($guardRows, 'name'),
            'guard_matches' => array_column($guardRows, 'matched'),
            'blocked_guard_names' => $blockedGuards,
            'admission_digest' => hash('sha256', json_encode([$sourceToken, $generation, $schemaCookie, $rows, $missingRoots], JSON_THROW_ON_ERROR)),
            'operation_names' => array_values(array_unique(array_merge(
                is_array($handlePlan['operation_names'] ?? null) ? $handlePlan['operation_names'] : [],
                [
                    'verify_prepared_statement_current_source_after_checkpoint_next233',
                    $admitted ? 'admit_prepared_statement_current_source_next233' : 'expire_prepared_statement_current_source_next233',
                ]
            ))),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($handlePlan['dependencies'] ?? null) ? $handlePlan['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next233',
                    'sqlite-wal-checkpoint-prepared-statement-current-source',
                    'wordpress-import-checkpoint-statement-reuse-after-hot-journal',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses next229 reopened handle admission plus statement source-token, schema-cookie, and root-page digest metadata',
            'non_overlap' => 'next233 admits prepared statements after next229 handle visibility; it does not repeat checkpoint reset admission, publication receipts, reopened handle coverage, WAL byte truncation, VFS savepoint rollback apply, rollback-journal commit/apply, or checkpoint transaction planning',
        ];
    }

    /**
     * @param array<string,mixed> $statement
     * @param array<int,string> $rootPageDigests
     * @param array<string,true> $admittedHandles
     * @return array<string,mixed>
     */
    private static function statementRow(array $statement, string $sourceToken, int $generation, string $databaseDigest, int $schemaCookie, array $rootPageDigests, array $admittedHandles): array
    {
        $name = self::token($statement['name'] ?? null, 'statement name');
        $handleName = self::token($statement['handle_name'] ?? null, "{$name} handle name");
        $observedSource = self::token($statement['source_token'] ?? null, "{$name} source token");
        $observedGeneration = self::intField($statement, 'generation', $name);
        $observedSchemaCookie = self::intField($statement, 'schema_cookie', $name);
        $observedDatabaseDigest = self::digest($statement['database_digest'] ?? null, "{$name} database digest");
        $observedRoots = self::pageDigestMap($statement['root_page_digests'] ?? null, $name);

        $reasons = [];
        if (!isset($admittedHandles[$handleName])) {
            $reasons[] = 'statement_handle_not_admitted';
        }
        if ($observedSource !== $sourceToken) {
            $reasons[] = 'statement_source_token_mismatch';
        }
        if ($observedGeneration !== $generation) {
            $reasons[] = 'statement_generation_mismatch';
        }
        if ($observedSchemaCookie !== $schemaCookie) {
            $reasons[] = 'statement_schema_cookie_mismatch';
        }
        if (!hash_equals($databaseDigest, $observedDatabaseDigest)) {
            $reasons[] = 'statement_database_digest_mismatch';
        }
        foreach ($observedRoots as $pageNumber => $digest) {
            if (!isset($rootPageDigests[$pageNumber])) {
                $reasons[] = 'statement_root_page_not_checkpointed';
                continue;
            }
            if (!hash_equals($rootPageDigests[$pageNumber], $digest)) {
                $reasons[] = 'statement_root_page_digest_mismatch';
            }
        }
        foreach (array_keys($rootPageDigests) as $pageNumber) {
            if (($statement['require_all_root_pages'] ?? false) === true && !isset($observedRoots[$pageNumber])) {
                $reasons[] = 'statement_missing_required_root_page';
            }
        }
        if (($statement['hot_journal_present'] ?? false) === true) {
            $reasons[] = 'statement_hot_journal_still_visible';
        }
        if ((int) ($statement['savepoint_depth'] ?? 0) !== 0) {
            $reasons[] = 'statement_savepoint_scope_open';
        }
        if (($statement['dirty_cache'] ?? false) === true) {
            $reasons[] = 'statement_dirty_cache';
        }
        if (($statement['schema_reparse_receipt'] ?? false) !== true) {
            $reasons[] = 'statement_schema_reparse_receipt_missing';
        }
        if (($statement['read_lock_receipt'] ?? false) !== true) {
            $reasons[] = 'statement_read_lock_receipt_missing';
        }

        $reasons = array_values(array_unique($reasons));

        return [
            'name' => $name,
            'handle_name' => $handleName,
            'source_token' => $observedSource,
            'generation' => $observedGeneration,
            'schema_cookie' => $observedSchemaCookie,
            'database_digest' => $observedDatabaseDigest,
            'root_pages' => array_keys($observedRoots),
            'root_page_digest_count' => count($observedRoots),
            'hot_journal_present' => ($statement['hot_journal_present'] ?? false) === true,
            'savepoint_depth' => (int) ($statement['savepoint_depth'] ?? 0),
            'dirty_cache' => ($statement['dirty_cache'] ?? false) === true,
            'schema_reparse_receipt' => ($statement['schema_reparse_receipt'] ?? false) === true,
            'read_lock_receipt' => ($statement['read_lock_receipt'] ?? false) === true,
            'admitted' => $reasons === [],
            'statement_reason' => $reasons === [] ? 'statement_matches_checkpoint_current_source' : implode('|', $reasons),
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
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next233 requires positive {$key}");
        }

        return $value;
    }

    /**
     * @param array<string,mixed> $values
     */
    private static function intField(array $values, string $key, string $name): int
    {
        $value = $values[$key] ?? null;
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next233 {$name} {$key} is invalid");
        }

        return $value;
    }

    private static function token(mixed $value, string $label): string
    {
        if (!is_string($value) || !preg_match('/^[A-Za-z0-9_.:-]+$/', $value)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next233 {$label} is invalid");
        }

        return $value;
    }

    private static function digest(mixed $value, string $label): string
    {
        if (!is_string($value) || !preg_match('/^[a-f0-9]{64}$/', $value)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next233 requires {$label}");
        }

        return $value;
    }

    /**
     * @param mixed $values
     * @return array<string,true>
     */
    private static function stringSet(mixed $values, string $label): array
    {
        if (!is_array($values) || $values === []) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next233 requires {$label}");
        }
        $set = [];
        foreach ($values as $value) {
            $set[self::token($value, $label)] = true;
        }

        return $set;
    }

    /**
     * @param mixed $values
     * @return array<int,string>
     */
    private static function pageDigestMap(mixed $values, string $name): array
    {
        if (!is_array($values) || $values === []) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next233 {$name} requires page digests");
        }
        $normalized = [];
        foreach ($values as $page => $digest) {
            $pageNumber = (int) $page;
            if ($pageNumber <= 0 || !is_string($digest) || !preg_match('/^[a-f0-9]{64}$/', $digest)) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next233 {$name} page digests must map positive pages to sha256 strings");
            }
            $normalized[$pageNumber] = $digest;
        }
        ksort($normalized);

        return $normalized;
    }
}
