<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext248Plan
{
    /**
     * @param array<string,mixed> $readerAdmissionPlan
     * @param list<array<string,mixed>> $releaseReceipts
     * @return array<string,mixed>
     */
    public static function planCheckpointTruncation(array $readerAdmissionPlan, array $releaseReceipts): array
    {
        if (($readerAdmissionPlan['status'] ?? null) !== 'wal-hot-journal-savepoint-checkpoint-current-source-next245'
            || ($readerAdmissionPlan['readers_admitted'] ?? null) !== true
        ) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next248 requires admitted next245 reopened readers');
        }
        if ($releaseReceipts === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next248 requires reader release receipts');
        }

        $databasePath = self::path($readerAdmissionPlan['database_path'] ?? null, 'database path');
        $walPath = self::path($readerAdmissionPlan['wal_path'] ?? null, 'wal path');
        $journalPath = self::path($readerAdmissionPlan['journal_path'] ?? null, 'journal path');
        $sourceToken = self::token($readerAdmissionPlan['source_token'] ?? null, 'source token');
        $writerGeneration = self::positiveInt($readerAdmissionPlan['writer_generation'] ?? null, 'writer generation');
        $nextSourceGeneration = self::positiveInt($readerAdmissionPlan['next_source_generation'] ?? null, 'next source generation');
        $databaseDigest = self::digest($readerAdmissionPlan['database_digest'] ?? null, 'database digest');
        $expectedReaderNames = self::tokenList($readerAdmissionPlan['accepted_reader_names'] ?? null, 'accepted reader names');
        $coveredPages = self::positiveIntList($readerAdmissionPlan['covered_page_numbers'] ?? null, 'covered pages');

        $rows = [];
        foreach ($releaseReceipts as $receipt) {
            $rows[] = self::releaseRow(
                $receipt,
                $databasePath,
                $walPath,
                $journalPath,
                $sourceToken,
                $writerGeneration,
                $nextSourceGeneration,
                $databaseDigest,
                $expectedReaderNames,
                $coveredPages
            );
        }

        $receiptNames = array_values(array_column($rows, 'name'));
        $duplicateNames = self::duplicates($receiptNames);
        $missingReaderNames = array_values(array_diff($expectedReaderNames, $receiptNames));
        sort($missingReaderNames);
        $unexpectedReaderNames = array_values(array_diff($receiptNames, $expectedReaderNames));
        sort($unexpectedReaderNames);

        $blockedRows = array_values(array_filter($rows, static fn (array $row): bool => !$row['released']));
        $blockedReasons = [];
        foreach ($blockedRows as $row) {
            foreach ($row['blocked_reasons'] as $reason) {
                $blockedReasons[] = $reason;
            }
        }
        foreach ($duplicateNames as $name) {
            $blockedReasons[] = 'reader_release_name_duplicate:' . $name;
        }
        foreach ($missingReaderNames as $name) {
            $blockedReasons[] = 'reader_release_missing:' . $name;
        }
        foreach ($unexpectedReaderNames as $name) {
            $blockedReasons[] = 'reader_release_unexpected:' . $name;
        }
        $blockedReasons = array_values(array_unique($blockedReasons));

        $guards = [
            'next245_readers_admitted' => true,
            'release_receipt_names_unique' => $duplicateNames === [],
            'all_admitted_readers_released' => $missingReaderNames === [],
            'no_unexpected_reader_release' => $unexpectedReaderNames === [],
            'release_tokens_match_current_source' => self::allRowsHave($rows, 'source_token_match'),
            'release_snapshots_match_database_digest' => self::allRowsHave($rows, 'database_digest_match'),
            'release_generations_follow_committed_writer' => self::allRowsHave($rows, 'generation_safe'),
            'release_page_cache_is_checkpoint_covered' => self::allRowsHave($rows, 'page_cache_covered'),
            'release_locks_and_hot_journal_fences_clear' => self::allRowsHave($rows, 'fences_clear'),
            'all_reader_releases_current' => $blockedRows === [],
        ];
        $blockedGuards = array_keys(array_filter($guards, static fn (bool $matched): bool => !$matched));
        $canTruncate = $blockedGuards === [];

        return [
            'status' => $canTruncate
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next248'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next248',
            'reason' => $canTruncate
                ? 'checkpoint_wal_can_truncate_after_all_reopened_readers_release'
                : 'checkpoint_wal_truncation_waits_for_reopened_reader_release',
            'base_status' => $readerAdmissionPlan['status'],
            'database_path' => $databasePath,
            'wal_path' => $walPath,
            'journal_path' => $journalPath,
            'source_token' => $sourceToken,
            'writer_generation' => $writerGeneration,
            'next_source_generation' => $nextSourceGeneration,
            'database_digest' => $databaseDigest,
            'covered_page_numbers' => $coveredPages,
            'expected_reader_names' => $expectedReaderNames,
            'release_rows' => $rows,
            'release_reader_names' => $receiptNames,
            'duplicate_release_names' => $duplicateNames,
            'missing_release_names' => $missingReaderNames,
            'unexpected_release_names' => $unexpectedReaderNames,
            'released_reader_names' => array_values(array_column(array_filter($rows, static fn (array $row): bool => $row['released']), 'name')),
            'blocked_reader_names' => array_values(array_column($blockedRows, 'name')),
            'blocked_reasons' => $blockedReasons,
            'guard_names' => array_keys($guards),
            'guard_matches' => array_values($guards),
            'blocked_guard_names' => $blockedGuards,
            'checkpoint_truncation_admitted' => $canTruncate,
            'reader_action' => $canTruncate ? 'close_reopened_reader_generation_' . $nextSourceGeneration : 'wait_for_reopened_reader_release',
            'wal_action' => $canTruncate ? 'truncate_checkpoint_wal_after_reader_release' : 'retain_checkpoint_wal_for_open_readers',
            'journal_action' => $canTruncate ? 'delete_hot_journal_fence_after_release' : 'preserve_hot_journal_release_fence',
            'cache_action' => $canTruncate ? 'evict_released_checkpoint_page_cache' : 'hold_checkpoint_page_cache_for_open_readers',
            'release_digest' => hash('sha256', json_encode([$sourceToken, $nextSourceGeneration, $expectedReaderNames, $rows, $guards], JSON_THROW_ON_ERROR)),
            'operation_names' => array_values(array_unique(array_merge(
                is_array($readerAdmissionPlan['operation_names'] ?? null) ? $readerAdmissionPlan['operation_names'] : [],
                [
                    'verify_reopened_reader_release_current_source_next248',
                    $canTruncate ? 'truncate_checkpoint_wal_current_source_next248' : 'block_checkpoint_wal_truncation_current_source_next248',
                ]
            ))),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($readerAdmissionPlan['dependencies'] ?? null) ? $readerAdmissionPlan['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next248',
                    'sqlite-wal-reopened-reader-release-current-source',
                    'wordpress-import-reader-release-before-wal-truncate',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses next245 reopened-reader admission metadata with native PHP release receipts, page-cache coverage, WAL frame, lock, hot-journal, and savepoint fences',
            'non_overlap' => 'next248 gates checkpoint WAL truncation after admitted reopened readers release; it does not repeat next245 reopened-reader admission, writer commit receipt validation, WAL byte truncation, VFS savepoint rollback apply, rollback-journal commit/apply, checkpoint transaction planning, or reader checkpoint snapshots',
        ];
    }

    /**
     * @param array<string,mixed> $receipt
     * @param list<string> $expectedReaderNames
     * @param list<int> $coveredPages
     * @return array<string,mixed>
     */
    private static function releaseRow(
        array $receipt,
        string $databasePath,
        string $walPath,
        string $journalPath,
        string $sourceToken,
        int $writerGeneration,
        int $nextSourceGeneration,
        string $databaseDigest,
        array $expectedReaderNames,
        array $coveredPages
    ): array {
        $name = self::token($receipt['name'] ?? null, 'release reader name');
        $reasons = [];

        if (!in_array($name, $expectedReaderNames, true)) {
            $reasons[] = 'reader_release_not_expected';
        }
        if (self::path($receipt['database_path'] ?? null, "{$name} database path") !== $databasePath) {
            $reasons[] = 'reader_release_database_path_mismatch';
        }
        if (self::path($receipt['wal_path'] ?? null, "{$name} wal path") !== $walPath) {
            $reasons[] = 'reader_release_wal_path_mismatch';
        }
        if (self::path($receipt['journal_path'] ?? null, "{$name} journal path") !== $journalPath) {
            $reasons[] = 'reader_release_journal_path_mismatch';
        }
        if (self::token($receipt['source_token'] ?? null, "{$name} source token") !== $sourceToken) {
            $reasons[] = 'reader_release_source_token_mismatch';
        }
        if (self::positiveInt($receipt['writer_generation'] ?? null, "{$name} writer generation") !== $writerGeneration) {
            $reasons[] = 'reader_release_writer_generation_mismatch';
        }
        if (self::positiveInt($receipt['reader_generation'] ?? null, "{$name} reader generation") < $nextSourceGeneration) {
            $reasons[] = 'reader_release_generation_stale';
        }
        if (self::digest($receipt['database_digest'] ?? null, "{$name} database digest") !== $databaseDigest) {
            $reasons[] = 'reader_release_database_digest_mismatch';
        }

        $lastVisibleFrame = self::nonNegativeInt($receipt['last_visible_frame'] ?? null, "{$name} last visible frame");
        $releaseFrame = self::nonNegativeInt($receipt['release_frame'] ?? null, "{$name} release frame");
        if ($releaseFrame < $lastVisibleFrame) {
            $reasons[] = 'reader_release_frame_before_visible_frame';
        }
        if ($releaseFrame > $writerGeneration) {
            $reasons[] = 'reader_release_frame_past_writer_generation';
        }

        $pageNumbers = self::positiveIntList($receipt['page_numbers'] ?? null, "{$name} page numbers");
        foreach ($pageNumbers as $pageNumber) {
            if (!in_array($pageNumber, $coveredPages, true)) {
                $reasons[] = 'reader_release_page_not_checkpoint_covered';
            }
        }

        if (($receipt['snapshot_closed'] ?? false) !== true) {
            $reasons[] = 'reader_release_snapshot_still_open';
        }
        if (($receipt['page_cache_clean'] ?? false) !== true) {
            $reasons[] = 'reader_release_page_cache_dirty';
        }
        if (($receipt['shared_lock_released'] ?? false) !== true) {
            $reasons[] = 'reader_release_shared_lock_held';
        }
        if (($receipt['reserved_lock_held'] ?? false) === true) {
            $reasons[] = 'reader_release_reserved_lock_held';
        }
        if (($receipt['hot_journal_visible'] ?? false) === true) {
            $reasons[] = 'reader_release_hot_journal_visible';
        }
        if (($receipt['savepoint_depth'] ?? 0) !== 0) {
            $reasons[] = 'reader_release_savepoint_scope_open';
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
            'last_visible_frame' => $lastVisibleFrame,
            'release_frame' => $releaseFrame,
            'page_numbers' => $pageNumbers,
            'snapshot_closed' => ($receipt['snapshot_closed'] ?? false) === true,
            'page_cache_clean' => ($receipt['page_cache_clean'] ?? false) === true,
            'shared_lock_released' => ($receipt['shared_lock_released'] ?? false) === true,
            'reserved_lock_held' => ($receipt['reserved_lock_held'] ?? false) === true,
            'hot_journal_visible' => ($receipt['hot_journal_visible'] ?? false) === true,
            'savepoint_depth' => (int) ($receipt['savepoint_depth'] ?? 0),
            'source_token_match' => !in_array('reader_release_source_token_mismatch', $reasons, true),
            'database_digest_match' => !in_array('reader_release_database_digest_mismatch', $reasons, true),
            'generation_safe' => !in_array('reader_release_generation_stale', $reasons, true),
            'page_cache_covered' => !in_array('reader_release_page_not_checkpoint_covered', $reasons, true)
                && !in_array('reader_release_page_cache_dirty', $reasons, true),
            'fences_clear' => !in_array('reader_release_snapshot_still_open', $reasons, true)
                && !in_array('reader_release_shared_lock_held', $reasons, true)
                && !in_array('reader_release_reserved_lock_held', $reasons, true)
                && !in_array('reader_release_hot_journal_visible', $reasons, true)
                && !in_array('reader_release_savepoint_scope_open', $reasons, true),
            'released' => $reasons === [],
            'release_reason' => $reasons === [] ? 'reader_release_current' : implode('|', $reasons),
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
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next248 {$label} must be positive");
        }

        return $value;
    }

    private static function nonNegativeInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next248 {$label} must be non-negative");
        }

        return $value;
    }

    private static function token(mixed $value, string $label): string
    {
        if (!is_string($value) || !preg_match('/^[A-Za-z0-9_.:-]+$/', $value)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next248 {$label} is invalid");
        }

        return $value;
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function tokenList(mixed $values, string $label): array
    {
        if (!is_array($values) || $values === []) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next248 requires {$label}");
        }
        $out = [];
        foreach ($values as $value) {
            $out[] = self::token($value, $label);
        }
        $out = array_values(array_unique($out));
        sort($out);

        return $out;
    }

    private static function path(mixed $value, string $label): string
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next248 {$label} is required");
        }

        return $value;
    }

    private static function digest(mixed $value, string $label): string
    {
        if (!is_string($value) || preg_match('/^[a-f0-9]{64}$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next248 requires {$label}");
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
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next248 requires {$label}");
        }
        $out = [];
        foreach ($values as $value) {
            if (!is_int($value) || $value <= 0) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next248 {$label} must contain positive integers");
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
