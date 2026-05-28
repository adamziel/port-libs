<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext242Plan
{
    /**
     * @param array<string,mixed> $admissionPlan
     * @param list<array<string,mixed>> $commitReceipts
     * @return array<string,mixed>
     */
    public static function admitCommittedWriter(array $admissionPlan, array $commitReceipts): array
    {
        if (($admissionPlan['status'] ?? null) !== 'wal-hot-journal-savepoint-checkpoint-current-source-next238'
            || ($admissionPlan['writer_admitted'] ?? null) !== true
        ) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next242 requires an admitted next238 writer plan');
        }
        if ($commitReceipts === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next242 requires commit receipts');
        }

        $databasePath = self::path($admissionPlan['database_path'] ?? null, 'database path');
        $walPath = self::path($admissionPlan['wal_path'] ?? null, 'wal path');
        $journalPath = self::path($admissionPlan['journal_path'] ?? null, 'journal path');
        $sourceToken = self::token($admissionPlan['source_token'] ?? null, 'source token');
        $writerGeneration = self::positiveInt($admissionPlan['next_writer_generation'] ?? null, 'writer generation');
        $publishedGeneration = self::positiveInt($admissionPlan['published_writer_generation'] ?? null, 'published generation');
        $databaseDigest = self::digest($admissionPlan['database_digest'] ?? null, 'database digest');
        $schemaCookie = self::positiveInt($admissionPlan['expected_schema_cookie'] ?? null, 'schema cookie');
        $walSalt = self::walSalt($admissionPlan['expected_wal_salt'] ?? null);
        $coveredPages = self::positiveIntList($admissionPlan['covered_page_numbers'] ?? null, 'covered pages');

        $rows = [];
        foreach ($commitReceipts as $receipt) {
            $rows[] = self::commitRow(
                $receipt,
                $databasePath,
                $walPath,
                $journalPath,
                $sourceToken,
                $writerGeneration,
                $publishedGeneration,
                $databaseDigest,
                $schemaCookie,
                $walSalt,
                $coveredPages
            );
        }

        $blockedRows = array_values(array_filter($rows, static fn (array $row): bool => !$row['accepted']));
        $requiredKinds = ['database-backfill', 'directory-sync', 'reader-generation', 'wal-commit'];
        $kinds = array_values(array_unique(array_column($rows, 'kind')));
        sort($kinds);
        $missingKinds = array_values(array_diff($requiredKinds, $kinds));
        $duplicateNames = self::duplicates(array_column($rows, 'name'));

        $blockedReasons = [];
        foreach ($blockedRows as $row) {
            foreach ($row['blocked_reasons'] as $reason) {
                $blockedReasons[] = $reason;
            }
        }
        if ($missingKinds !== []) {
            $blockedReasons[] = 'writer_commit_receipt_kind_missing';
        }
        if ($duplicateNames !== []) {
            $blockedReasons[] = 'writer_commit_receipt_name_duplicate';
        }
        $blockedReasons = array_values(array_unique($blockedReasons));

        $guards = [
            'next238_writer_admitted' => true,
            'commit_receipt_names_unique' => $duplicateNames === [],
            'required_commit_receipt_kinds_present' => $missingKinds === [],
            'commit_receipts_use_next_writer_generation' => self::allRowsHave($rows, 'generation_match'),
            'commit_receipts_preserve_current_source_token' => self::allRowsHave($rows, 'source_token_match'),
            'wal_commit_frames_follow_restart' => self::allRowsHave($rows, 'wal_frame_sequence_valid'),
            'database_backfill_matches_checkpoint_digest' => self::allRowsHave($rows, 'database_digest_match'),
            'hot_journal_and_savepoint_fences_clear' => self::allRowsHave($rows, 'fences_clear'),
            'reader_generations_advanced_past_publication' => self::allRowsHave($rows, 'reader_generation_safe'),
            'all_commit_receipts_current' => $blockedRows === [],
        ];
        $blockedGuards = array_keys(array_filter($guards, static fn (bool $matched): bool => !$matched));
        $admitted = $blockedGuards === [];

        return [
            'status' => $admitted
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next242'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next242',
            'reason' => $admitted
                ? 'post_checkpoint_writer_commit_receipts_publish_next_current_source'
                : 'post_checkpoint_writer_commit_waits_for_current_source_receipts',
            'base_status' => $admissionPlan['status'],
            'database_path' => $databasePath,
            'wal_path' => $walPath,
            'journal_path' => $journalPath,
            'source_token' => $sourceToken,
            'published_writer_generation' => $publishedGeneration,
            'writer_generation' => $writerGeneration,
            'next_source_generation' => $writerGeneration + 1,
            'database_digest' => $databaseDigest,
            'expected_schema_cookie' => $schemaCookie,
            'expected_wal_salt' => $walSalt,
            'covered_page_numbers' => $coveredPages,
            'receipt_rows' => $rows,
            'receipt_kinds' => $kinds,
            'required_receipt_kinds' => $requiredKinds,
            'missing_receipt_kinds' => $missingKinds,
            'duplicate_receipt_names' => $duplicateNames,
            'accepted_receipt_names' => array_values(array_column(array_filter($rows, static fn (array $row): bool => $row['accepted']), 'name')),
            'blocked_receipt_names' => array_values(array_column($blockedRows, 'name')),
            'blocked_reasons' => $blockedReasons,
            'guard_names' => array_keys($guards),
            'guard_matches' => array_values($guards),
            'blocked_guard_names' => $blockedGuards,
            'commit_admitted' => $admitted,
            'current_source_action' => $admitted ? 'publish_writer_generation_' . ($writerGeneration + 1) : 'hold_current_source_at_generation_' . $writerGeneration,
            'wal_action' => $admitted ? 'retain_committed_wal_frames_after_restart' : 'block_committed_wal_publication',
            'reader_action' => $admitted ? 'allow_reopened_readers_to_advance_generation' : 'force_reader_generation_recheck',
            'journal_action' => $admitted ? 'keep_hot_journal_deleted_after_commit' : 'retain_hot_journal_delete_fence',
            'commit_digest' => hash('sha256', json_encode([$sourceToken, $writerGeneration, $rows, $guards], JSON_THROW_ON_ERROR)),
            'operation_names' => array_values(array_unique(array_merge(
                is_array($admissionPlan['operation_names'] ?? null) ? $admissionPlan['operation_names'] : [],
                [
                    'verify_post_checkpoint_writer_commit_receipts_next242',
                    $admitted ? 'publish_post_checkpoint_writer_current_source_next242' : 'hold_post_checkpoint_writer_current_source_next242',
                ]
            ))),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($admissionPlan['dependencies'] ?? null) ? $admissionPlan['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next242',
                    'sqlite-wal-post-checkpoint-writer-commit-generation',
                    'wordpress-import-post-checkpoint-writer-current-source',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses next238 writer admission, WAL frame receipts, database backfill receipts, directory sync receipts, and reader generation receipts',
            'non_overlap' => 'next242 validates first post-publication writer commit receipts after next238 writer admission; it does not repeat restart/truncate reset admission, durable publication receipt validation, reader reopen admission, WAL byte truncation, rollback-journal apply/commit, VFS writer apply, or checkpoint transaction planning',
        ];
    }

    /**
     * @param array<string,mixed> $receipt
     * @param list<int> $coveredPages
     * @return array<string,mixed>
     */
    private static function commitRow(
        array $receipt,
        string $databasePath,
        string $walPath,
        string $journalPath,
        string $sourceToken,
        int $writerGeneration,
        int $publishedGeneration,
        string $databaseDigest,
        int $schemaCookie,
        string $walSalt,
        array $coveredPages
    ): array {
        $name = self::token($receipt['name'] ?? null, 'receipt name');
        $kind = self::kind($receipt['kind'] ?? null);
        $reasons = [];

        if (self::path($receipt['database_path'] ?? null, "{$name} database path") !== $databasePath) {
            $reasons[] = 'commit_database_path_mismatch';
        }
        if (self::path($receipt['wal_path'] ?? null, "{$name} wal path") !== $walPath) {
            $reasons[] = 'commit_wal_path_mismatch';
        }
        if (self::path($receipt['journal_path'] ?? null, "{$name} journal path") !== $journalPath) {
            $reasons[] = 'commit_journal_path_mismatch';
        }
        if (self::token($receipt['source_token'] ?? null, "{$name} source token") !== $sourceToken) {
            $reasons[] = 'commit_source_token_mismatch';
        }
        if (self::positiveInt($receipt['writer_generation'] ?? null, "{$name} writer generation") !== $writerGeneration) {
            $reasons[] = 'commit_writer_generation_mismatch';
        }
        if (self::positiveInt($receipt['published_generation'] ?? null, "{$name} published generation") !== $publishedGeneration) {
            $reasons[] = 'commit_published_generation_mismatch';
        }
        if (self::digest($receipt['observed_database_digest'] ?? null, "{$name} database digest") !== $databaseDigest) {
            $reasons[] = 'commit_database_digest_mismatch';
        }
        if (self::positiveInt($receipt['schema_cookie'] ?? null, "{$name} schema cookie") !== $schemaCookie) {
            $reasons[] = 'commit_schema_cookie_mismatch';
        }
        if (self::walSalt($receipt['wal_salt'] ?? null) !== $walSalt) {
            $reasons[] = 'commit_wal_salt_mismatch';
        }

        $firstFrame = self::nonNegativeInt($receipt['first_wal_frame'] ?? null, "{$name} first WAL frame");
        $lastFrame = self::nonNegativeInt($receipt['last_wal_frame'] ?? null, "{$name} last WAL frame");
        if ($firstFrame < 1 || $lastFrame < $firstFrame) {
            $reasons[] = 'commit_wal_frame_sequence_invalid';
        }
        $pageNumbers = self::positiveIntList($receipt['page_numbers'] ?? null, "{$name} page numbers");
        foreach ($pageNumbers as $pageNumber) {
            if (!in_array($pageNumber, $coveredPages, true)) {
                $reasons[] = 'commit_page_not_checkpoint_covered';
            }
        }

        if (($receipt['database_backfilled'] ?? false) !== true) {
            $reasons[] = 'commit_database_backfill_missing';
        }
        if (($receipt['wal_synced'] ?? false) !== true) {
            $reasons[] = 'commit_wal_sync_missing';
        }
        if (($receipt['directory_synced'] ?? false) !== true) {
            $reasons[] = 'commit_directory_sync_missing';
        }
        if (($receipt['hot_journal_visible'] ?? false) === true) {
            $reasons[] = 'commit_hot_journal_visible';
        }
        if (($receipt['savepoint_depth'] ?? 0) !== 0) {
            $reasons[] = 'commit_savepoint_scope_open';
        }
        if (($receipt['reader_cache_dirty'] ?? false) === true) {
            $reasons[] = 'commit_reader_cache_dirty';
        }
        if (($receipt['reader_generation'] ?? null) !== null
            && self::positiveInt($receipt['reader_generation'], "{$name} reader generation") < $writerGeneration
        ) {
            $reasons[] = 'commit_reader_generation_stale';
        }

        $reasons = array_values(array_unique($reasons));

        return [
            'name' => $name,
            'kind' => $kind,
            'database_path' => (string) $receipt['database_path'],
            'wal_path' => (string) $receipt['wal_path'],
            'journal_path' => (string) $receipt['journal_path'],
            'source_token' => (string) $receipt['source_token'],
            'writer_generation' => (int) $receipt['writer_generation'],
            'published_generation' => (int) $receipt['published_generation'],
            'observed_database_digest' => (string) $receipt['observed_database_digest'],
            'schema_cookie' => (int) $receipt['schema_cookie'],
            'wal_salt' => (string) $receipt['wal_salt'],
            'first_wal_frame' => $firstFrame,
            'last_wal_frame' => $lastFrame,
            'page_numbers' => $pageNumbers,
            'database_backfilled' => ($receipt['database_backfilled'] ?? false) === true,
            'wal_synced' => ($receipt['wal_synced'] ?? false) === true,
            'directory_synced' => ($receipt['directory_synced'] ?? false) === true,
            'hot_journal_visible' => ($receipt['hot_journal_visible'] ?? false) === true,
            'savepoint_depth' => (int) ($receipt['savepoint_depth'] ?? 0),
            'reader_cache_dirty' => ($receipt['reader_cache_dirty'] ?? false) === true,
            'reader_generation' => $receipt['reader_generation'] ?? null,
            'generation_match' => !in_array('commit_writer_generation_mismatch', $reasons, true),
            'source_token_match' => !in_array('commit_source_token_mismatch', $reasons, true),
            'wal_frame_sequence_valid' => !in_array('commit_wal_frame_sequence_invalid', $reasons, true),
            'database_digest_match' => !in_array('commit_database_digest_mismatch', $reasons, true)
                && !in_array('commit_database_backfill_missing', $reasons, true),
            'fences_clear' => !in_array('commit_hot_journal_visible', $reasons, true)
                && !in_array('commit_savepoint_scope_open', $reasons, true)
                && !in_array('commit_reader_cache_dirty', $reasons, true),
            'reader_generation_safe' => !in_array('commit_reader_generation_stale', $reasons, true),
            'accepted' => $reasons === [],
            'receipt_reason' => $reasons === [] ? 'writer_commit_receipt_current' : implode('|', $reasons),
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

    private static function kind(mixed $value): string
    {
        if (!is_string($value) || !in_array($value, ['database-backfill', 'directory-sync', 'reader-generation', 'wal-commit'], true)) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next242 receipt kind is invalid');
        }

        return $value;
    }

    private static function positiveInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value <= 0) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next242 {$label} must be positive");
        }

        return $value;
    }

    private static function nonNegativeInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next242 {$label} must be non-negative");
        }

        return $value;
    }

    private static function token(mixed $value, string $label): string
    {
        if (!is_string($value) || !preg_match('/^[A-Za-z0-9_.:-]+$/', $value)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next242 {$label} is invalid");
        }

        return $value;
    }

    private static function path(mixed $value, string $label): string
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next242 {$label} is required");
        }

        return $value;
    }

    private static function digest(mixed $value, string $label): string
    {
        if (!is_string($value) || preg_match('/^[a-f0-9]{64}$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next242 requires {$label}");
        }

        return $value;
    }

    private static function walSalt(mixed $value): string
    {
        if (!is_string($value) || preg_match('/^[a-f0-9]{16}$/', $value) !== 1) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next242 WAL salt is invalid');
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
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next242 requires {$label}");
        }
        $out = [];
        foreach ($values as $value) {
            if (!is_int($value) || $value <= 0) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next242 {$label} must contain positive integers");
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
