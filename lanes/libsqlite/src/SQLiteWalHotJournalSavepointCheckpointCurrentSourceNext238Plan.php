<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext238Plan
{
    /**
     * @param array<string,mixed> $publicationPlan
     * @param list<array<string,mixed>> $readerReceipts
     * @return array<string,mixed>
     */
    public static function admitNextWriter(array $publicationPlan, array $readerReceipts): array
    {
        if (($publicationPlan['status'] ?? null) !== 'wal-hot-journal-savepoint-checkpoint-current-source-next235'
            || ($publicationPlan['publication_admitted'] ?? null) !== true
        ) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next238 requires an admitted next235 durable-publication plan');
        }
        if ($readerReceipts === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next238 requires reader reopen receipts');
        }

        $databasePath = self::path($publicationPlan['database_path'] ?? null, 'database path');
        $walPath = self::path($publicationPlan['wal_path'] ?? null, 'wal path');
        $journalPath = self::path($publicationPlan['journal_path'] ?? null, 'journal path');
        $sourceToken = self::token($publicationPlan['source_token'] ?? null, 'source token');
        $publishedGeneration = self::positiveInt($publicationPlan['next_writer_generation'] ?? null, 'published writer generation');
        $nextGeneration = $publishedGeneration + 1;
        $databaseDigest = self::digest($publicationPlan['database_digest'] ?? null, 'database digest');
        $schemaCookie = self::positiveInt($publicationPlan['expected_schema_cookie'] ?? null, 'schema cookie');
        $walSalt = self::walSalt($publicationPlan['expected_wal_salt'] ?? null);
        $coveredPages = self::positiveIntList($publicationPlan['covered_page_numbers'] ?? null, 'covered pages');

        $rows = [];
        foreach ($readerReceipts as $receipt) {
            $rows[] = self::readerRow(
                $receipt,
                $databasePath,
                $walPath,
                $journalPath,
                $sourceToken,
                $publishedGeneration,
                $databaseDigest,
                $schemaCookie,
                $walSalt,
                $coveredPages
            );
        }

        $blockedRows = array_values(array_filter($rows, static fn (array $row): bool => !$row['accepted']));
        $readerNames = array_values(array_unique(array_column($rows, 'name')));
        sort($readerNames);
        $duplicateNames = self::duplicates(array_column($rows, 'name'));
        $blockedReasons = [];
        foreach ($blockedRows as $row) {
            foreach ($row['blocked_reasons'] as $reason) {
                $blockedReasons[] = $reason;
            }
        }
        if ($duplicateNames !== []) {
            $blockedReasons[] = 'reader_reopen_receipt_name_duplicate';
        }
        $blockedReasons = array_values(array_unique($blockedReasons));

        $guards = [
            'next235_durable_publication_admitted' => true,
            'reader_reopen_receipts_unique' => $duplicateNames === [],
            'readers_observe_published_database_digest' => self::allRowsHaveReasonlessField($rows, 'database_digest_match'),
            'readers_start_at_restarted_wal_frame_zero' => self::allRowsHaveReasonlessField($rows, 'wal_frame_zero'),
            'readers_do_not_observe_hot_journal' => self::allRowsHaveReasonlessField($rows, 'hot_journal_absent'),
            'readers_hold_shared_lock_after_reopen' => self::allRowsHaveReasonlessField($rows, 'shared_lock_held'),
            'reader_pages_are_checkpoint_covered' => self::allRowsHaveReasonlessField($rows, 'pages_checkpoint_covered'),
            'all_reopened_readers_match_current_source' => $blockedRows === [],
        ];
        $blockedGuards = array_keys(array_filter($guards, static fn (bool $matched): bool => !$matched));
        $admitted = $blockedGuards === [];

        return [
            'status' => $admitted
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next238'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next238',
            'reason' => $admitted
                ? 'post_publication_writer_admitted_after_reopened_readers_observe_clean_current_source'
                : 'post_publication_writer_waits_for_reopened_reader_current_source_receipts',
            'base_status' => $publicationPlan['status'],
            'database_path' => $databasePath,
            'wal_path' => $walPath,
            'journal_path' => $journalPath,
            'source_token' => $sourceToken,
            'published_writer_generation' => $publishedGeneration,
            'next_writer_generation' => $nextGeneration,
            'database_digest' => $databaseDigest,
            'expected_schema_cookie' => $schemaCookie,
            'expected_wal_salt' => $walSalt,
            'covered_page_numbers' => $coveredPages,
            'reader_rows' => $rows,
            'reader_names' => $readerNames,
            'duplicate_reader_names' => $duplicateNames,
            'accepted_reader_names' => array_values(array_column(array_filter($rows, static fn (array $row): bool => $row['accepted']), 'name')),
            'blocked_reader_names' => array_values(array_column($blockedRows, 'name')),
            'blocked_reasons' => $blockedReasons,
            'guard_names' => array_keys($guards),
            'guard_matches' => array_values($guards),
            'blocked_guard_names' => $blockedGuards,
            'writer_admitted' => $admitted,
            'writer_action' => $admitted ? 'start_writer_generation_' . $nextGeneration : 'hold_writer_until_reopen_receipts_match',
            'reader_action' => $admitted ? 'keep_readers_on_restarted_wal_zero_frame' : 'force_reader_reopen_before_writer',
            'wal_action' => $admitted ? 'append_new_frames_after_clean_restart' : 'preserve_restarted_wal_without_new_frames',
            'admission_digest' => hash('sha256', json_encode([$sourceToken, $publishedGeneration, $nextGeneration, $rows, $guards], JSON_THROW_ON_ERROR)),
            'operation_names' => array_values(array_unique(array_merge(
                is_array($publicationPlan['operation_names'] ?? null) ? $publicationPlan['operation_names'] : [],
                [
                    'verify_reopened_readers_observe_clean_current_source_next238',
                    $admitted ? 'admit_next_writer_after_restart_checkpoint_next238' : 'hold_next_writer_after_restart_checkpoint_next238',
                ]
            ))),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($publicationPlan['dependencies'] ?? null) ? $publicationPlan['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next238',
                    'sqlite-wal-post-publication-writer-generation-fence',
                    'wordpress-import-reopened-readers-before-next-writer',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses next235 durable publication, reader reopen receipts, WAL read-mark zero, shared-lock, and hot-journal absence evidence',
            'non_overlap' => 'next238 gates the first post-publication writer after next235 durable publication; it does not repeat checkpoint byte materialization, savepoint byte truncation, VFS writer application, rollback-journal apply/commit, reader-slot admission, or durable publication receipt validation',
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
        int $generation,
        string $databaseDigest,
        int $schemaCookie,
        string $walSalt,
        array $coveredPages
    ): array {
        $name = self::token($receipt['name'] ?? null, 'reader name');
        $reasons = [];

        $observedPages = self::positiveIntList($receipt['observed_page_numbers'] ?? null, "{$name} observed pages");
        foreach ($observedPages as $page) {
            if (!in_array($page, $coveredPages, true)) {
                $reasons[] = 'reader_page_not_checkpoint_covered';
            }
        }

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
        if (self::positiveInt($receipt['generation'] ?? null, "{$name} generation") !== $generation) {
            $reasons[] = 'reader_generation_mismatch';
        }
        if (self::digest($receipt['observed_database_digest'] ?? null, "{$name} database digest") !== $databaseDigest) {
            $reasons[] = 'reader_database_digest_mismatch';
        }
        if (self::positiveInt($receipt['observed_schema_cookie'] ?? null, "{$name} schema cookie") !== $schemaCookie) {
            $reasons[] = 'reader_schema_cookie_mismatch';
        }
        if (self::walSalt($receipt['observed_wal_salt'] ?? null) !== $walSalt) {
            $reasons[] = 'reader_wal_salt_mismatch';
        }

        $walFrame = $receipt['observed_wal_frame'] ?? null;
        if (!is_int($walFrame) || $walFrame !== 0) {
            $reasons[] = 'reader_wal_frame_not_zero';
        }
        if (($receipt['hot_journal_visible'] ?? false) === true) {
            $reasons[] = 'reader_hot_journal_visible';
        }
        if (($receipt['shared_lock'] ?? false) !== true) {
            $reasons[] = 'reader_shared_lock_missing';
        }
        if (($receipt['dirty_page_cache'] ?? false) === true) {
            $reasons[] = 'reader_dirty_page_cache_visible';
        }
        if (($receipt['wal_header_restarted'] ?? false) !== true) {
            $reasons[] = 'reader_wal_header_not_restarted';
        }

        $reasons = array_values(array_unique($reasons));

        return [
            'name' => $name,
            'database_path' => (string) $receipt['database_path'],
            'wal_path' => (string) $receipt['wal_path'],
            'journal_path' => (string) $receipt['journal_path'],
            'source_token' => (string) $receipt['source_token'],
            'generation' => (int) $receipt['generation'],
            'observed_database_digest' => (string) $receipt['observed_database_digest'],
            'observed_schema_cookie' => (int) $receipt['observed_schema_cookie'],
            'observed_wal_salt' => (string) $receipt['observed_wal_salt'],
            'observed_wal_frame' => is_int($walFrame) ? $walFrame : null,
            'observed_page_numbers' => $observedPages,
            'hot_journal_visible' => ($receipt['hot_journal_visible'] ?? false) === true,
            'shared_lock' => ($receipt['shared_lock'] ?? false) === true,
            'dirty_page_cache' => ($receipt['dirty_page_cache'] ?? false) === true,
            'wal_header_restarted' => ($receipt['wal_header_restarted'] ?? false) === true,
            'database_digest_match' => !in_array('reader_database_digest_mismatch', $reasons, true),
            'wal_frame_zero' => !in_array('reader_wal_frame_not_zero', $reasons, true),
            'hot_journal_absent' => !in_array('reader_hot_journal_visible', $reasons, true),
            'shared_lock_held' => !in_array('reader_shared_lock_missing', $reasons, true),
            'pages_checkpoint_covered' => !in_array('reader_page_not_checkpoint_covered', $reasons, true),
            'accepted' => $reasons === [],
            'receipt_reason' => $reasons === [] ? 'reader_reopened_on_clean_current_source' : implode('|', $reasons),
            'blocked_reasons' => $reasons,
        ];
    }

    /** @param list<array<string,mixed>> $rows */
    private static function allRowsHaveReasonlessField(array $rows, string $field): bool
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
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next238 {$label} must be positive");
        }

        return $value;
    }

    private static function token(mixed $value, string $label): string
    {
        if (!is_string($value) || !preg_match('/^[A-Za-z0-9_.:-]+$/', $value)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next238 {$label} is invalid");
        }

        return $value;
    }

    private static function path(mixed $value, string $label): string
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next238 {$label} is required");
        }

        return $value;
    }

    private static function digest(mixed $value, string $label): string
    {
        if (!is_string($value) || preg_match('/^[a-f0-9]{64}$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next238 requires {$label}");
        }

        return $value;
    }

    private static function walSalt(mixed $value): string
    {
        if (!is_string($value) || preg_match('/^[a-f0-9]{16}$/', $value) !== 1) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next238 WAL salt is invalid');
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
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next238 requires {$label}");
        }
        $out = [];
        foreach ($values as $value) {
            if (!is_int($value) || $value <= 0) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next238 {$label} must contain positive integers");
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
