<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext245Plan
{
    /**
     * @param array<string,mixed> $commitPlan
     * @param list<array<string,mixed>> $readerReceipts
     * @return array<string,mixed>
     */
    public static function admitReopenedReaders(array $commitPlan, array $readerReceipts): array
    {
        if (($commitPlan['status'] ?? null) !== 'wal-hot-journal-savepoint-checkpoint-current-source-next242'
            || ($commitPlan['commit_admitted'] ?? null) !== true
        ) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next245 requires an admitted next242 commit plan');
        }
        if ($readerReceipts === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next245 requires reopened reader receipts');
        }

        $databasePath = self::path($commitPlan['database_path'] ?? null, 'database path');
        $walPath = self::path($commitPlan['wal_path'] ?? null, 'wal path');
        $journalPath = self::path($commitPlan['journal_path'] ?? null, 'journal path');
        $sourceToken = self::token($commitPlan['source_token'] ?? null, 'source token');
        $writerGeneration = self::positiveInt($commitPlan['writer_generation'] ?? null, 'writer generation');
        $nextSourceGeneration = self::positiveInt($commitPlan['next_source_generation'] ?? null, 'next source generation');
        $databaseDigest = self::digest($commitPlan['database_digest'] ?? null, 'database digest');
        $schemaCookie = self::positiveInt($commitPlan['expected_schema_cookie'] ?? null, 'schema cookie');
        $walSalt = self::walSalt($commitPlan['expected_wal_salt'] ?? null);
        $coveredPages = self::positiveIntList($commitPlan['covered_page_numbers'] ?? null, 'covered pages');

        $rows = [];
        foreach ($readerReceipts as $receipt) {
            $rows[] = self::readerRow(
                $receipt,
                $databasePath,
                $walPath,
                $journalPath,
                $sourceToken,
                $writerGeneration,
                $nextSourceGeneration,
                $databaseDigest,
                $schemaCookie,
                $walSalt,
                $coveredPages
            );
        }

        $blockedRows = array_values(array_filter($rows, static fn (array $row): bool => !$row['accepted']));
        $duplicateNames = self::duplicates(array_column($rows, 'name'));
        $blockedReasons = [];
        foreach ($blockedRows as $row) {
            foreach ($row['blocked_reasons'] as $reason) {
                $blockedReasons[] = $reason;
            }
        }
        if ($duplicateNames !== []) {
            $blockedReasons[] = 'reader_receipt_name_duplicate';
        }
        $blockedReasons = array_values(array_unique($blockedReasons));

        $guards = [
            'next242_commit_admitted' => true,
            'reader_receipt_names_unique' => $duplicateNames === [],
            'reader_generations_follow_committed_writer' => self::allRowsHave($rows, 'generation_safe'),
            'reader_tokens_match_current_source' => self::allRowsHave($rows, 'source_token_match'),
            'reader_snapshots_match_database_digest' => self::allRowsHave($rows, 'database_digest_match'),
            'reader_wal_salt_and_frames_current' => self::allRowsHave($rows, 'wal_snapshot_current'),
            'reader_page_cache_is_checkpoint_covered' => self::allRowsHave($rows, 'page_cache_covered'),
            'hot_journal_and_savepoint_fences_clear' => self::allRowsHave($rows, 'fences_clear'),
            'all_reopened_readers_current' => $blockedRows === [],
        ];
        $blockedGuards = array_keys(array_filter($guards, static fn (bool $matched): bool => !$matched));
        $admitted = $blockedGuards === [];

        return [
            'status' => $admitted
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next245'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next245',
            'reason' => $admitted
                ? 'reopened_readers_are_bound_to_committed_checkpoint_source'
                : 'reopened_readers_wait_for_committed_checkpoint_source',
            'base_status' => $commitPlan['status'],
            'database_path' => $databasePath,
            'wal_path' => $walPath,
            'journal_path' => $journalPath,
            'source_token' => $sourceToken,
            'writer_generation' => $writerGeneration,
            'next_source_generation' => $nextSourceGeneration,
            'database_digest' => $databaseDigest,
            'expected_schema_cookie' => $schemaCookie,
            'expected_wal_salt' => $walSalt,
            'covered_page_numbers' => $coveredPages,
            'reader_rows' => $rows,
            'reader_names' => array_values(array_column($rows, 'name')),
            'duplicate_reader_names' => $duplicateNames,
            'accepted_reader_names' => array_values(array_column(array_filter($rows, static fn (array $row): bool => $row['accepted']), 'name')),
            'blocked_reader_names' => array_values(array_column($blockedRows, 'name')),
            'blocked_reasons' => $blockedReasons,
            'guard_names' => array_keys($guards),
            'guard_matches' => array_values($guards),
            'blocked_guard_names' => $blockedGuards,
            'readers_admitted' => $admitted,
            'reader_action' => $admitted ? 'serve_reopened_readers_from_generation_' . $nextSourceGeneration : 'reopen_reader_snapshots_before_serving',
            'wal_action' => $admitted ? 'retain_checkpoint_wal_until_reader_release' : 'preserve_wal_until_reader_snapshot_matches',
            'journal_action' => $admitted ? 'hot_journal_delete_fence_satisfied' : 'hold_hot_journal_delete_fence',
            'cache_action' => $admitted ? 'reuse_checkpoint_page_cache_for_reopened_readers' : 'discard_stale_reader_page_cache',
            'reader_digest' => hash('sha256', json_encode([$sourceToken, $nextSourceGeneration, $rows, $guards], JSON_THROW_ON_ERROR)),
            'operation_names' => array_values(array_unique(array_merge(
                is_array($commitPlan['operation_names'] ?? null) ? $commitPlan['operation_names'] : [],
                [
                    'verify_reopened_reader_cache_current_source_next245',
                    $admitted ? 'admit_reopened_reader_cache_current_source_next245' : 'block_reopened_reader_cache_current_source_next245',
                ]
            ))),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($commitPlan['dependencies'] ?? null) ? $commitPlan['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next245',
                    'sqlite-wal-reopened-reader-cache-current-source',
                    'wordpress-import-reopened-reader-after-checkpoint-commit',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses next242 committed writer receipts with native PHP reopened-reader snapshot, page-cache, WAL salt, and hot-journal/savepoint fence metadata',
            'non_overlap' => 'next245 admits reopened reader page-cache snapshots after the next242 committed writer source; it does not repeat writer commit receipt validation, durable sidecar publication, WAL byte truncation, VFS savepoint rollback apply, rollback-journal commit/apply, checkpoint transaction planning, or reader checkpoint snapshots',
        ];
    }

    /**
     * @param array<string,mixed> $receipt
     * @param list<int> $coveredPages
     * @return array<string,mixed>
     */
    private static function readerRow(
        array $receipt,
        string $databasePath,
        string $walPath,
        string $journalPath,
        string $sourceToken,
        int $writerGeneration,
        int $nextSourceGeneration,
        string $databaseDigest,
        int $schemaCookie,
        string $walSalt,
        array $coveredPages
    ): array {
        $name = self::token($receipt['name'] ?? null, 'reader name');
        $reasons = [];

        if (self::path($receipt['database_path'] ?? null, "{$name} database path") !== $databasePath) {
            $reasons[] = 'reader_database_path_mismatch';
        }
        if (self::path($receipt['wal_path'] ?? null, "{$name} wal path") !== $walPath) {
            $reasons[] = 'reader_wal_path_mismatch';
        }
        if (self::path($receipt['journal_path'] ?? null, "{$name} journal path") !== $journalPath) {
            $reasons[] = 'reader_journal_path_mismatch';
        }
        if (self::token($receipt['source_token'] ?? null, "{$name} source token") !== $sourceToken) {
            $reasons[] = 'reader_source_token_mismatch';
        }
        if (self::positiveInt($receipt['writer_generation'] ?? null, "{$name} writer generation") !== $writerGeneration) {
            $reasons[] = 'reader_writer_generation_mismatch';
        }
        if (self::positiveInt($receipt['reader_generation'] ?? null, "{$name} reader generation") < $nextSourceGeneration) {
            $reasons[] = 'reader_generation_stale';
        }
        if (self::digest($receipt['database_digest'] ?? null, "{$name} database digest") !== $databaseDigest) {
            $reasons[] = 'reader_database_digest_mismatch';
        }
        if (self::positiveInt($receipt['schema_cookie'] ?? null, "{$name} schema cookie") !== $schemaCookie) {
            $reasons[] = 'reader_schema_cookie_mismatch';
        }
        if (self::walSalt($receipt['wal_salt'] ?? null) !== $walSalt) {
            $reasons[] = 'reader_wal_salt_mismatch';
        }

        $readmarkFrame = self::nonNegativeInt($receipt['readmark_frame'] ?? null, "{$name} readmark frame");
        $lastVisibleFrame = self::nonNegativeInt($receipt['last_visible_frame'] ?? null, "{$name} last visible frame");
        if ($lastVisibleFrame < $readmarkFrame) {
            $reasons[] = 'reader_visible_frame_before_readmark';
        }
        if ($lastVisibleFrame > $writerGeneration) {
            $reasons[] = 'reader_visible_frame_past_writer_generation';
        }

        $pageNumbers = self::positiveIntList($receipt['page_numbers'] ?? null, "{$name} page numbers");
        foreach ($pageNumbers as $pageNumber) {
            if (!in_array($pageNumber, $coveredPages, true)) {
                $reasons[] = 'reader_page_not_checkpoint_covered';
            }
        }

        if (($receipt['page_cache_clean'] ?? false) !== true) {
            $reasons[] = 'reader_page_cache_dirty';
        }
        if (($receipt['snapshot_open'] ?? false) !== true) {
            $reasons[] = 'reader_snapshot_not_open';
        }
        if (($receipt['hot_journal_visible'] ?? false) === true) {
            $reasons[] = 'reader_hot_journal_visible';
        }
        if (($receipt['savepoint_depth'] ?? 0) !== 0) {
            $reasons[] = 'reader_savepoint_scope_open';
        }
        if (($receipt['reserved_lock_held'] ?? false) === true) {
            $reasons[] = 'reader_reserved_lock_held';
        }

        $reasons = array_values(array_unique($reasons));

        return [
            'name' => $name,
            'database_path' => (string) $receipt['database_path'],
            'wal_path' => (string) $receipt['wal_path'],
            'journal_path' => (string) $receipt['journal_path'],
            'source_token' => (string) $receipt['source_token'],
            'writer_generation' => (int) $receipt['writer_generation'],
            'reader_generation' => (int) $receipt['reader_generation'],
            'database_digest' => (string) $receipt['database_digest'],
            'schema_cookie' => (int) $receipt['schema_cookie'],
            'wal_salt' => (string) $receipt['wal_salt'],
            'readmark_frame' => $readmarkFrame,
            'last_visible_frame' => $lastVisibleFrame,
            'page_numbers' => $pageNumbers,
            'page_cache_clean' => ($receipt['page_cache_clean'] ?? false) === true,
            'snapshot_open' => ($receipt['snapshot_open'] ?? false) === true,
            'hot_journal_visible' => ($receipt['hot_journal_visible'] ?? false) === true,
            'savepoint_depth' => (int) ($receipt['savepoint_depth'] ?? 0),
            'reserved_lock_held' => ($receipt['reserved_lock_held'] ?? false) === true,
            'generation_safe' => !in_array('reader_generation_stale', $reasons, true),
            'source_token_match' => !in_array('reader_source_token_mismatch', $reasons, true),
            'database_digest_match' => !in_array('reader_database_digest_mismatch', $reasons, true),
            'wal_snapshot_current' => !in_array('reader_wal_salt_mismatch', $reasons, true)
                && !in_array('reader_visible_frame_before_readmark', $reasons, true)
                && !in_array('reader_visible_frame_past_writer_generation', $reasons, true),
            'page_cache_covered' => !in_array('reader_page_not_checkpoint_covered', $reasons, true)
                && !in_array('reader_page_cache_dirty', $reasons, true),
            'fences_clear' => !in_array('reader_hot_journal_visible', $reasons, true)
                && !in_array('reader_savepoint_scope_open', $reasons, true)
                && !in_array('reader_reserved_lock_held', $reasons, true)
                && !in_array('reader_snapshot_not_open', $reasons, true),
            'accepted' => $reasons === [],
            'receipt_reason' => $reasons === [] ? 'reader_receipt_current' : implode('|', $reasons),
            'blocked_reasons' => $reasons,
        ];
    }

    /** @param list<array<string,mixed>> $rows */
    private static function allRowsHave(array $rows, string $field): bool
    {
        foreach ($rows as $row) {
            if (($row[$field] ?? false) !== true) {
                return false;
            }
        }

        return $rows !== [];
    }

    private static function positiveInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value <= 0) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next245 {$label} must be positive");
        }

        return $value;
    }

    private static function nonNegativeInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next245 {$label} must be non-negative");
        }

        return $value;
    }

    private static function token(mixed $value, string $label): string
    {
        if (!is_string($value) || !preg_match('/^[A-Za-z0-9_.:-]+$/', $value)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next245 {$label} is invalid");
        }

        return $value;
    }

    private static function path(mixed $value, string $label): string
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next245 {$label} is required");
        }

        return $value;
    }

    private static function digest(mixed $value, string $label): string
    {
        if (!is_string($value) || preg_match('/^[a-f0-9]{64}$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next245 requires {$label}");
        }

        return $value;
    }

    private static function walSalt(mixed $value): string
    {
        if (!is_string($value) || preg_match('/^[a-f0-9]{16}$/', $value) !== 1) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next245 WAL salt is invalid');
        }

        return $value;
    }

    /**
     * @param mixed $values
     * @return list<int>
     */
    private static function positiveIntList(mixed $values, string $label): array
    {
        if (!is_array($values) || $values === []) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next245 requires {$label}");
        }
        $out = [];
        foreach ($values as $value) {
            if (!is_int($value) || $value <= 0) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next245 {$label} must contain positive integers");
            }
            $out[] = $value;
        }
        $out = array_values(array_unique($out));
        sort($out);

        return $out;
    }

    /**
     * @param list<string> $values
     * @return list<string>
     */
    private static function duplicates(array $values): array
    {
        $seen = [];
        $dupes = [];
        foreach ($values as $value) {
            if (isset($seen[$value])) {
                $dupes[$value] = true;
            }
            $seen[$value] = true;
        }

        return array_keys($dupes);
    }
}
