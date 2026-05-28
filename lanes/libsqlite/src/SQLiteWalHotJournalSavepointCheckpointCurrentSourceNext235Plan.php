<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext235Plan
{
    /**
     * @param array<string,mixed> $readerSlotPlan
     * @param list<array<string,mixed>> $publicationReceipts
     * @return array<string,mixed>
     */
    public static function admitDurablePublication(array $readerSlotPlan, array $publicationReceipts): array
    {
        if (($readerSlotPlan['status'] ?? null) !== 'wal-hot-journal-savepoint-checkpoint-current-source-next232'
            || ($readerSlotPlan['current_source_readable'] ?? null) !== true
        ) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next235 requires an admitted next232 reader-slot plan');
        }
        if ($publicationReceipts === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next235 requires publication receipts');
        }

        $databasePath = self::path($readerSlotPlan['database_path'] ?? null, 'database path');
        $walPath = self::path($readerSlotPlan['wal_path'] ?? null, 'wal path');
        $journalPath = self::path($readerSlotPlan['journal_path'] ?? null, 'journal path');
        $sourceToken = self::token($readerSlotPlan['source_token'] ?? null, 'source token');
        $generation = self::positiveInt($readerSlotPlan['next_writer_generation'] ?? null, 'writer generation');
        $databaseDigest = self::digest($readerSlotPlan['database_digest'] ?? null, 'database digest');
        $expectedWalSalt = self::walSalt($readerSlotPlan['expected_wal_salt'] ?? null);
        $schemaCookie = self::positiveInt($readerSlotPlan['expected_schema_cookie'] ?? null, 'schema cookie');
        $coveredPages = self::positiveIntList($readerSlotPlan['covered_page_numbers'] ?? null, 'covered pages');

        $rows = [];
        foreach ($publicationReceipts as $receipt) {
            $rows[] = self::receiptRow(
                $receipt,
                $databasePath,
                $walPath,
                $journalPath,
                $sourceToken,
                $generation,
                $databaseDigest,
                $schemaCookie,
                $expectedWalSalt,
                $coveredPages
            );
        }

        $blockedRows = array_values(array_filter($rows, static fn (array $row): bool => !$row['accepted']));
        $kinds = array_values(array_unique(array_column($rows, 'kind')));
        sort($kinds);
        $missingKinds = array_values(array_diff(['database', 'directory', 'journal', 'wal'], $kinds));
        $duplicateNames = self::duplicates(array_column($rows, 'name'));
        $blockedReasons = [];
        foreach ($blockedRows as $row) {
            foreach ($row['blocked_reasons'] as $reason) {
                $blockedReasons[] = $reason;
            }
        }
        if ($missingKinds !== []) {
            $blockedReasons[] = 'publication_receipt_kind_missing';
        }
        if ($duplicateNames !== []) {
            $blockedReasons[] = 'publication_receipt_name_duplicate';
        }
        $blockedReasons = array_values(array_unique($blockedReasons));

        $guards = [
            'next232_reader_slots_admitted' => true,
            'database_wal_journal_directory_receipts_present' => $missingKinds === [],
            'publication_receipt_names_unique' => $duplicateNames === [],
            'all_publication_receipts_match_current_source' => $blockedRows === [],
            'database_receipt_covers_checkpoint_pages' => self::databaseReceiptCoversPages($rows, $coveredPages),
            'wal_receipt_has_reset_readmarks' => self::walReceiptHasResetReadmarks($rows),
            'journal_receipt_deletes_hot_journal' => self::journalReceiptDeletesHotJournal($rows),
            'directory_receipt_fsyncs_sidecars' => self::directoryReceiptFsyncsSidecars($rows),
        ];
        $blockedGuards = array_keys(array_filter($guards, static fn (bool $matched): bool => !$matched));
        $admitted = $blockedGuards === [];

        return [
            'status' => $admitted
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next235'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next235',
            'reason' => $admitted
                ? 'durable_publication_receipts_admit_reopened_current_source'
                : 'durable_publication_receipts_hold_reopened_current_source',
            'base_status' => $readerSlotPlan['status'],
            'database_path' => $databasePath,
            'wal_path' => $walPath,
            'journal_path' => $journalPath,
            'source_token' => $sourceToken,
            'next_writer_generation' => $generation,
            'database_digest' => $databaseDigest,
            'expected_schema_cookie' => $schemaCookie,
            'expected_wal_salt' => $expectedWalSalt,
            'covered_page_numbers' => $coveredPages,
            'receipt_rows' => $rows,
            'receipt_names' => array_column($rows, 'name'),
            'receipt_kinds' => $kinds,
            'missing_receipt_kinds' => $missingKinds,
            'duplicate_receipt_names' => $duplicateNames,
            'accepted_receipt_names' => array_values(array_column(array_filter($rows, static fn (array $row): bool => $row['accepted']), 'name')),
            'blocked_receipt_names' => array_values(array_column($blockedRows, 'name')),
            'blocked_reasons' => $blockedReasons,
            'guard_names' => array_keys($guards),
            'guard_matches' => array_values($guards),
            'blocked_guard_names' => $blockedGuards,
            'publication_admitted' => $admitted,
            'reader_action' => $admitted ? 'keep_reopened_readers_on_durable_current_source' : 'force_reopen_after_durable_publication',
            'wal_action' => $admitted ? 'allow_restarted_wal_after_directory_sync' : 'preserve_previous_wal_until_receipts_match',
            'publication_digest' => hash('sha256', json_encode([$sourceToken, $generation, $databaseDigest, $expectedWalSalt, $rows, $guards], JSON_THROW_ON_ERROR)),
            'operation_names' => array_values(array_unique(array_merge(
                is_array($readerSlotPlan['operation_names'] ?? null) ? $readerSlotPlan['operation_names'] : [],
                [
                    'verify_durable_publication_receipts_current_source_next235',
                    'validate_hot_journal_delete_before_reopened_readers_next235',
                    $admitted ? 'admit_durable_reopened_current_source_next235' : 'hold_durable_reopened_current_source_next235',
                ]
            ))),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($readerSlotPlan['dependencies'] ?? null) ? $readerSlotPlan['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next235',
                    'sqlite-wal-durable-publication-receipts',
                    'wordpress-import-hot-journal-checkpoint-durable-current-source',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses next232 reader-slot admission plus native VFS file, journal-delete, WAL reset, and directory-sync receipts',
            'non_overlap' => 'next235 validates durable publication receipts after next232 reader-slot admission; it does not repeat WAL byte truncation, VFS savepoint rollback, rollback-journal commit/apply, checkpoint transaction planning, file-writer byte application, WAL-index reopen receipts, or reader-slot admission',
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
        if (!is_string($kind) || !in_array($kind, ['database', 'wal', 'journal', 'directory'], true)) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next235 receipt kind is invalid');
        }
        $path = self::path($receipt['path'] ?? null, "{$name} path");
        $reasons = [];

        if (self::token($receipt['source_token'] ?? null, "{$name} source token") !== $sourceToken) {
            $reasons[] = 'publication_source_token_mismatch';
        }
        if (self::positiveInt($receipt['generation'] ?? null, "{$name} generation") !== $generation) {
            $reasons[] = 'publication_generation_mismatch';
        }
        if (self::positiveInt($receipt['schema_cookie'] ?? null, "{$name} schema cookie") !== $schemaCookie) {
            $reasons[] = 'publication_schema_cookie_mismatch';
        }
        if (self::walSalt($receipt['wal_salt'] ?? null) !== $walSalt) {
            $reasons[] = 'publication_wal_salt_mismatch';
        }
        if (($receipt['lock_receipt'] ?? false) !== true) {
            $reasons[] = 'publication_lock_receipt_missing';
        }

        $pageNumbers = self::positiveIntList($receipt['page_numbers'] ?? [1], "{$name} pages");
        foreach ($pageNumbers as $page) {
            if (!in_array($page, $coveredPages, true)) {
                $reasons[] = 'publication_page_not_checkpointed';
            }
        }

        if ($kind === 'database') {
            if ($path !== $databasePath) {
                $reasons[] = 'database_publication_path_mismatch';
            }
            if (self::digest($receipt['digest'] ?? null, "{$name} digest") !== $databaseDigest) {
                $reasons[] = 'database_publication_digest_mismatch';
            }
            if (($receipt['synced'] ?? false) !== true) {
                $reasons[] = 'database_publication_not_synced';
            }
            if (($receipt['truncated'] ?? false) !== true) {
                $reasons[] = 'database_publication_not_truncated';
            }
        } elseif ($kind === 'wal') {
            if ($path !== $walPath) {
                $reasons[] = 'wal_publication_path_mismatch';
            }
            if (!self::isDigest($receipt['digest'] ?? null)) {
                $reasons[] = 'wal_publication_digest_missing';
            }
            if (($receipt['synced'] ?? false) !== true) {
                $reasons[] = 'wal_publication_not_synced';
            }
            if (($receipt['read_mark_frame'] ?? null) !== 0) {
                $reasons[] = 'wal_publication_readmark_not_reset';
            }
            if (($receipt['checkpoint_backfill_complete'] ?? false) !== true) {
                $reasons[] = 'wal_publication_backfill_incomplete';
            }
        } elseif ($kind === 'journal') {
            if ($path !== $journalPath) {
                $reasons[] = 'journal_publication_path_mismatch';
            }
            if (($receipt['deleted'] ?? false) !== true) {
                $reasons[] = 'hot_journal_delete_receipt_missing';
            }
            if (($receipt['hot_journal_visible'] ?? false) === true) {
                $reasons[] = 'hot_journal_still_visible';
            }
        } else {
            if ($path !== dirname($databasePath)) {
                $reasons[] = 'directory_publication_path_mismatch';
            }
            if (($receipt['directory_synced'] ?? false) !== true) {
                $reasons[] = 'directory_publication_not_synced';
            }
            $persisted = self::stringList($receipt['persisted_paths'] ?? null, "{$name} persisted paths");
            foreach ([$databasePath, $walPath, $journalPath] as $requiredPath) {
                if (!in_array($requiredPath, $persisted, true)) {
                    $reasons[] = 'directory_publication_missing_sidecar';
                }
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
            'digest' => is_string($receipt['digest'] ?? null) ? (string) $receipt['digest'] : null,
            'page_numbers' => $pageNumbers,
            'lock_receipt' => ($receipt['lock_receipt'] ?? false) === true,
            'synced' => ($receipt['synced'] ?? false) === true,
            'truncated' => ($receipt['truncated'] ?? false) === true,
            'deleted' => ($receipt['deleted'] ?? false) === true,
            'directory_synced' => ($receipt['directory_synced'] ?? false) === true,
            'read_mark_frame' => is_int($receipt['read_mark_frame'] ?? null) ? (int) $receipt['read_mark_frame'] : null,
            'checkpoint_backfill_complete' => ($receipt['checkpoint_backfill_complete'] ?? false) === true,
            'persisted_paths' => is_array($receipt['persisted_paths'] ?? null) ? array_values($receipt['persisted_paths']) : [],
            'accepted' => $reasons === [],
            'receipt_reason' => $reasons === [] ? 'publication_receipt_matches_durable_current_source' : implode('|', $reasons),
            'blocked_reasons' => $reasons,
        ];
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param list<int> $coveredPages
     */
    private static function databaseReceiptCoversPages(array $rows, array $coveredPages): bool
    {
        foreach ($rows as $row) {
            if ($row['kind'] === 'database' && $row['accepted'] === true) {
                return array_values(array_diff($coveredPages, $row['page_numbers'])) === [];
            }
        }

        return false;
    }

    /** @param list<array<string,mixed>> $rows */
    private static function walReceiptHasResetReadmarks(array $rows): bool
    {
        foreach ($rows as $row) {
            if ($row['kind'] === 'wal' && $row['accepted'] === true && $row['read_mark_frame'] === 0) {
                return true;
            }
        }

        return false;
    }

    /** @param list<array<string,mixed>> $rows */
    private static function journalReceiptDeletesHotJournal(array $rows): bool
    {
        foreach ($rows as $row) {
            if ($row['kind'] === 'journal' && $row['accepted'] === true && $row['deleted'] === true) {
                return true;
            }
        }

        return false;
    }

    /** @param list<array<string,mixed>> $rows */
    private static function directoryReceiptFsyncsSidecars(array $rows): bool
    {
        foreach ($rows as $row) {
            if ($row['kind'] === 'directory' && $row['accepted'] === true && $row['directory_synced'] === true) {
                return true;
            }
        }

        return false;
    }

    private static function positiveInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value <= 0) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next235 {$label} must be positive");
        }

        return $value;
    }

    private static function token(mixed $value, string $label): string
    {
        if (!is_string($value) || !preg_match('/^[A-Za-z0-9_.:-]+$/', $value)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next235 {$label} is invalid");
        }

        return $value;
    }

    private static function path(mixed $value, string $label): string
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next235 {$label} is required");
        }

        return $value;
    }

    private static function digest(mixed $value, string $label): string
    {
        if (!self::isDigest($value)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next235 requires {$label}");
        }

        return (string) $value;
    }

    private static function isDigest(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[a-f0-9]{64}$/', $value) === 1;
    }

    private static function walSalt(mixed $value): string
    {
        if (!is_string($value) || !preg_match('/^[a-f0-9]{16}$/', $value)) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next235 WAL salt is invalid');
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
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next235 requires {$label}");
        }
        $out = [];
        foreach ($values as $value) {
            if (!is_int($value) || $value <= 0) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next235 {$label} must contain positive integers");
            }
            $out[] = $value;
        }
        $out = array_values(array_unique($out));
        sort($out);

        return $out;
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function stringList(mixed $values, string $label): array
    {
        if (!is_array($values) || $values === []) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next235 requires {$label}");
        }
        $out = [];
        foreach ($values as $value) {
            if (!is_string($value) || $value === '') {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next235 {$label} must contain strings");
            }
            $out[] = $value;
        }

        return array_values(array_unique($out));
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
