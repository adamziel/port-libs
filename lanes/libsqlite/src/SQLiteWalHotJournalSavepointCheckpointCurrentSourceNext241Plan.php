<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext241Plan
{
    /**
     * @param array<string,mixed> $writerPlan
     * @param list<array<string,mixed>> $commitReceipts
     * @return array<string,mixed>
     */
    public static function admitCommittedWriter(array $writerPlan, array $commitReceipts): array
    {
        if (($writerPlan['status'] ?? null) !== 'wal-hot-journal-savepoint-checkpoint-current-source-next238'
            || ($writerPlan['writer_admitted'] ?? null) !== true
        ) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next241 requires an admitted next238 writer plan');
        }
        if ($commitReceipts === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next241 requires writer commit receipts');
        }

        $databasePath = self::path($writerPlan['database_path'] ?? null, 'database path');
        $walPath = self::path($writerPlan['wal_path'] ?? null, 'wal path');
        $journalPath = self::path($writerPlan['journal_path'] ?? null, 'journal path');
        $sourceToken = self::token($writerPlan['source_token'] ?? null, 'source token');
        $publishedGeneration = self::positiveInt($writerPlan['published_writer_generation'] ?? null, 'published generation');
        $writerGeneration = self::positiveInt($writerPlan['next_writer_generation'] ?? null, 'writer generation');
        if ($writerGeneration !== $publishedGeneration + 1) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next241 writer generation must follow publication generation');
        }
        $databaseDigest = self::digest($writerPlan['database_digest'] ?? null, 'database digest');
        $schemaCookie = self::positiveInt($writerPlan['expected_schema_cookie'] ?? null, 'schema cookie');
        $walSalt = self::walSalt($writerPlan['expected_wal_salt'] ?? null);
        $coveredPages = self::positiveIntList($writerPlan['covered_page_numbers'] ?? null, 'covered pages');

        $rows = [];
        foreach ($commitReceipts as $receipt) {
            $rows[] = self::receiptRow(
                $receipt,
                $databasePath,
                $walPath,
                $journalPath,
                $sourceToken,
                $writerGeneration,
                $databaseDigest,
                $schemaCookie,
                $walSalt,
                $coveredPages
            );
        }

        $blockedRows = array_values(array_filter($rows, static fn (array $row): bool => !$row['accepted']));
        $kinds = array_values(array_unique(array_column($rows, 'kind')));
        sort($kinds);
        $missingKinds = array_values(array_diff(['commit', 'directory', 'lock', 'wal'], $kinds));
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

        $committedFrames = self::committedFrameNumbers($rows);
        $commitReceipt = self::firstKind($rows, 'commit');
        $walReceipt = self::firstKind($rows, 'wal');
        $lockReceipt = self::firstKind($rows, 'lock');
        $directoryReceipt = self::firstKind($rows, 'directory');

        $guards = [
            'next238_writer_admitted' => true,
            'writer_commit_receipt_kinds_present' => $missingKinds === [],
            'writer_commit_receipt_names_unique' => $duplicateNames === [],
            'all_commit_receipts_match_writer_source' => $blockedRows === [],
            'commit_receipt_marks_transaction_complete' => $commitReceipt !== null && $commitReceipt['commit_marker_present'] === true,
            'wal_receipt_flushes_appended_frames' => $walReceipt !== null && $walReceipt['synced'] === true && $walReceipt['frames_synced'] === true,
            'lock_receipt_releases_reserved_lock' => $lockReceipt !== null && $lockReceipt['reserved_lock_released'] === true && $lockReceipt['shared_lock_preserved'] === true,
            'directory_receipt_persists_wal_sidecar' => $directoryReceipt !== null && $directoryReceipt['directory_synced'] === true && $directoryReceipt['persisted_wal_path'] === true,
        ];
        $blockedGuards = array_keys(array_filter($guards, static fn (bool $matched): bool => !$matched));
        $admitted = $blockedGuards === [];

        return [
            'status' => $admitted
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next241'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next241',
            'reason' => $admitted
                ? 'post_publication_writer_commit_receipts_advance_current_source'
                : 'post_publication_writer_commit_receipts_hold_current_source',
            'base_status' => $writerPlan['status'],
            'database_path' => $databasePath,
            'wal_path' => $walPath,
            'journal_path' => $journalPath,
            'source_token' => $sourceToken,
            'published_writer_generation' => $publishedGeneration,
            'committed_writer_generation' => $writerGeneration,
            'next_reader_generation' => $writerGeneration,
            'database_digest' => $databaseDigest,
            'expected_schema_cookie' => $schemaCookie,
            'expected_wal_salt' => $walSalt,
            'covered_page_numbers' => $coveredPages,
            'commit_receipt_rows' => $rows,
            'receipt_names' => array_column($rows, 'name'),
            'receipt_kinds' => $kinds,
            'missing_receipt_kinds' => $missingKinds,
            'duplicate_receipt_names' => $duplicateNames,
            'accepted_receipt_names' => array_values(array_column(array_filter($rows, static fn (array $row): bool => $row['accepted']), 'name')),
            'blocked_receipt_names' => array_values(array_column($blockedRows, 'name')),
            'blocked_reasons' => $blockedReasons,
            'committed_frame_numbers' => $committedFrames,
            'committed_frame_count' => count($committedFrames),
            'guard_names' => array_keys($guards),
            'guard_matches' => array_values($guards),
            'blocked_guard_names' => $blockedGuards,
            'current_source_advanced' => $admitted,
            'reader_action' => $admitted ? 'advance_readers_to_committed_writer_generation_241' : 'keep_readers_on_restart_checkpoint_source',
            'writer_action' => $admitted ? 'publish_committed_wal_frames_next241' : 'hold_writer_commit_until_receipts_match',
            'wal_action' => $admitted ? 'retain_committed_wal_frames_for_next_reader' : 'preserve_unpublished_writer_frames',
            'commit_digest' => hash('sha256', json_encode([$sourceToken, $writerGeneration, $databaseDigest, $rows, $guards], JSON_THROW_ON_ERROR)),
            'operation_names' => array_values(array_unique(array_merge(
                is_array($writerPlan['operation_names'] ?? null) ? $writerPlan['operation_names'] : [],
                [
                    'verify_post_publication_writer_commit_receipts_next241',
                    $admitted ? 'advance_current_source_after_writer_commit_next241' : 'hold_current_source_after_writer_commit_next241',
                ]
            ))),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($writerPlan['dependencies'] ?? null) ? $writerPlan['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next241',
                    'sqlite-wal-post-publication-writer-commit-fence',
                    'wordpress-import-committed-wal-writer-before-next-reader',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses next238 writer admission plus native WAL frame sync, commit marker, lock-release, and directory fsync receipts',
            'non_overlap' => 'next241 gates reader advancement after the first post-publication writer commit; it does not repeat next238 reader reopen admission, next235 durable publication receipts, WAL byte truncation, checkpoint transaction planning, VFS savepoint rollback, rollback-journal apply/commit, or WAL file byte materialization',
        ];
    }

    /**
     * @param array<string,mixed> $receipt
     * @param list<int> $coveredPages
     * @return array<string,mixed>
     */
    private static function receiptRow(
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
        $name = self::token($receipt['name'] ?? null, 'receipt name');
        $kind = $receipt['kind'] ?? null;
        if (!is_string($kind) || !in_array($kind, ['commit', 'wal', 'lock', 'directory'], true)) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next241 receipt kind is invalid');
        }
        $path = self::path($receipt['path'] ?? null, "{$name} path");
        $pageNumbers = self::positiveIntList($receipt['page_numbers'] ?? [1], "{$name} pages");
        $frameNumbers = self::positiveIntList($receipt['frame_numbers'] ?? [1], "{$name} frames");
        $reasons = [];

        if (self::token($receipt['source_token'] ?? null, "{$name} source token") !== $sourceToken) {
            $reasons[] = 'writer_commit_source_token_mismatch';
        }
        if (self::positiveInt($receipt['generation'] ?? null, "{$name} generation") !== $generation) {
            $reasons[] = 'writer_commit_generation_mismatch';
        }
        if (self::positiveInt($receipt['schema_cookie'] ?? null, "{$name} schema cookie") !== $schemaCookie) {
            $reasons[] = 'writer_commit_schema_cookie_mismatch';
        }
        if (self::walSalt($receipt['wal_salt'] ?? null) !== $walSalt) {
            $reasons[] = 'writer_commit_wal_salt_mismatch';
        }
        if (self::digest($receipt['database_digest'] ?? null, "{$name} database digest") !== $databaseDigest) {
            $reasons[] = 'writer_commit_database_digest_mismatch';
        }
        foreach ($pageNumbers as $page) {
            if (!in_array($page, $coveredPages, true)) {
                $reasons[] = 'writer_commit_page_not_checkpoint_covered';
            }
        }

        $persistedPaths = is_array($receipt['persisted_paths'] ?? null)
            ? array_values(array_filter($receipt['persisted_paths'], 'is_string'))
            : [];

        if ($kind === 'commit') {
            if ($path !== $walPath) {
                $reasons[] = 'commit_receipt_path_mismatch';
            }
            if (($receipt['commit_marker_present'] ?? false) !== true) {
                $reasons[] = 'writer_commit_marker_missing';
            }
            if (($receipt['transaction_complete'] ?? false) !== true) {
                $reasons[] = 'writer_transaction_incomplete';
            }
            if (!self::isDigest($receipt['commit_digest'] ?? null)) {
                $reasons[] = 'writer_commit_digest_missing';
            }
        } elseif ($kind === 'wal') {
            if ($path !== $walPath) {
                $reasons[] = 'wal_commit_path_mismatch';
            }
            if (($receipt['synced'] ?? false) !== true) {
                $reasons[] = 'wal_commit_not_synced';
            }
            if (($receipt['frames_synced'] ?? false) !== true) {
                $reasons[] = 'wal_commit_frames_not_synced';
            }
            if (($receipt['hot_journal_visible'] ?? false) === true) {
                $reasons[] = 'wal_commit_hot_journal_visible';
            }
        } elseif ($kind === 'lock') {
            if ($path !== $databasePath) {
                $reasons[] = 'lock_receipt_database_path_mismatch';
            }
            if (($receipt['reserved_lock_released'] ?? false) !== true) {
                $reasons[] = 'writer_reserved_lock_not_released';
            }
            if (($receipt['shared_lock_preserved'] ?? false) !== true) {
                $reasons[] = 'reader_shared_lock_not_preserved';
            }
        } else {
            if ($path !== dirname($databasePath)) {
                $reasons[] = 'directory_commit_path_mismatch';
            }
            if (($receipt['directory_synced'] ?? false) !== true) {
                $reasons[] = 'directory_commit_not_synced';
            }
            if (!in_array($walPath, $persistedPaths, true)) {
                $reasons[] = 'directory_commit_missing_wal_sidecar';
            }
            if (!in_array($databasePath, $persistedPaths, true)) {
                $reasons[] = 'directory_commit_missing_database_path';
            }
            if (!in_array($journalPath, $persistedPaths, true)) {
                $reasons[] = 'directory_commit_missing_journal_path';
            }
        }

        $reasons = array_values(array_unique($reasons));

        return [
            'name' => $name,
            'kind' => $kind,
            'path' => $path,
            'source_token' => (string) $receipt['source_token'],
            'generation' => (int) $receipt['generation'],
            'schema_cookie' => (int) $receipt['schema_cookie'],
            'wal_salt' => (string) $receipt['wal_salt'],
            'database_digest' => (string) $receipt['database_digest'],
            'page_numbers' => $pageNumbers,
            'frame_numbers' => $frameNumbers,
            'commit_marker_present' => ($receipt['commit_marker_present'] ?? false) === true,
            'transaction_complete' => ($receipt['transaction_complete'] ?? false) === true,
            'commit_digest' => is_string($receipt['commit_digest'] ?? null) ? (string) $receipt['commit_digest'] : null,
            'synced' => ($receipt['synced'] ?? false) === true,
            'frames_synced' => ($receipt['frames_synced'] ?? false) === true,
            'hot_journal_visible' => ($receipt['hot_journal_visible'] ?? false) === true,
            'reserved_lock_released' => ($receipt['reserved_lock_released'] ?? false) === true,
            'shared_lock_preserved' => ($receipt['shared_lock_preserved'] ?? false) === true,
            'directory_synced' => ($receipt['directory_synced'] ?? false) === true,
            'persisted_paths' => $persistedPaths,
            'persisted_wal_path' => in_array($walPath, $persistedPaths, true),
            'accepted' => $reasons === [],
            'receipt_reason' => $reasons === [] ? 'writer_commit_receipt_matches_current_source' : implode('|', $reasons),
            'blocked_reasons' => $reasons,
        ];
    }

    /** @param list<array<string,mixed>> $rows */
    private static function firstKind(array $rows, string $kind): ?array
    {
        foreach ($rows as $row) {
            if ($row['kind'] === $kind) {
                return $row;
            }
        }

        return null;
    }

    /** @param list<array<string,mixed>> $rows */
    private static function committedFrameNumbers(array $rows): array
    {
        $frames = [];
        foreach ($rows as $row) {
            if ($row['accepted']) {
                foreach ($row['frame_numbers'] as $frame) {
                    $frames[] = $frame;
                }
            }
        }
        $frames = array_values(array_unique($frames));
        sort($frames);

        return $frames;
    }

    private static function positiveInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value <= 0) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next241 {$label} must be positive");
        }

        return $value;
    }

    private static function token(mixed $value, string $label): string
    {
        if (!is_string($value) || !preg_match('/^[A-Za-z0-9_.:-]+$/', $value)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next241 {$label} is invalid");
        }

        return $value;
    }

    private static function path(mixed $value, string $label): string
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next241 {$label} is required");
        }

        return $value;
    }

    private static function digest(mixed $value, string $label): string
    {
        if (!self::isDigest($value)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next241 requires {$label}");
        }

        return $value;
    }

    private static function isDigest(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[a-f0-9]{64}$/', $value) === 1;
    }

    private static function walSalt(mixed $value): string
    {
        if (!is_string($value) || preg_match('/^[a-f0-9]{16}$/', $value) !== 1) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next241 WAL salt is invalid');
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
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next241 requires {$label}");
        }
        $out = [];
        foreach ($values as $value) {
            if (!is_int($value) || $value <= 0) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next241 {$label} must contain positive integers");
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
