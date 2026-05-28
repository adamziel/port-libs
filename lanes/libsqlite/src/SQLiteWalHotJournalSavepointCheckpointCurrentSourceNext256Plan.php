<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext256Plan
{
    /**
     * @param array<string,mixed> $sealedPlan
     * @param list<array<string,mixed>> $readerReceipts
     * @return array<string,mixed>
     */
    public static function admitReopenedReaders(array $sealedPlan, array $readerReceipts): array
    {
        if (($sealedPlan['status'] ?? null) !== 'wal-hot-journal-savepoint-checkpoint-current-source-next252'
            || ($sealedPlan['post_truncate_source_sealed'] ?? null) !== true
        ) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next256 requires sealed next252 source state');
        }
        if ($readerReceipts === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next256 requires reopened reader receipts');
        }

        $databasePath = self::path($sealedPlan['database_path'] ?? null, 'database path');
        $walPath = self::path($sealedPlan['wal_path'] ?? null, 'wal path');
        $journalPath = self::path($sealedPlan['journal_path'] ?? null, 'journal path');
        $sourceToken = self::token($sealedPlan['source_token'] ?? null, 'source token');
        $sourceGeneration = self::positiveInt($sealedPlan['next_source_generation'] ?? null, 'next source generation');
        $databaseDigest = self::digest($sealedPlan['database_digest'] ?? null, 'database digest');
        $sealedReaders = self::tokenList($sealedPlan['released_reader_names'] ?? null, 'sealed reader names');
        $sealedPages = self::positiveIntList($sealedPlan['covered_page_numbers'] ?? null, 'sealed page numbers');

        $rows = [];
        foreach ($readerReceipts as $receipt) {
            $rows[] = self::readerRow(
                $receipt,
                $databasePath,
                $walPath,
                $journalPath,
                $sourceToken,
                $sourceGeneration,
                $databaseDigest,
                $sealedReaders,
                $sealedPages
            );
        }

        $acceptedRows = array_values(array_filter($rows, static fn (array $row): bool => $row['accepted']));
        $blockedRows = array_values(array_filter($rows, static fn (array $row): bool => !$row['accepted']));
        $receiptNames = array_values(array_column($rows, 'name'));
        $duplicateNames = self::duplicates($receiptNames);

        $coveredReaders = [];
        $coveredPages = [];
        $readmarkSlots = [];
        $maxCheckpointSequence = 0;
        $minChangeCounter = null;
        foreach ($acceptedRows as $row) {
            $coveredReaders[$row['reader_name']] = true;
            foreach ($row['page_numbers'] as $pageNumber) {
                $coveredPages[$pageNumber] = true;
            }
            $readmarkSlots[$row['readmark_slot']] = true;
            $maxCheckpointSequence = max($maxCheckpointSequence, $row['checkpoint_sequence']);
            $minChangeCounter = $minChangeCounter === null ? $row['database_change_counter'] : min($minChangeCounter, $row['database_change_counter']);
        }

        $coveredReaderNames = array_keys($coveredReaders);
        sort($coveredReaderNames);
        $coveredPageNumbers = array_map('intval', array_keys($coveredPages));
        sort($coveredPageNumbers);
        $readmarkSlotNumbers = array_map('intval', array_keys($readmarkSlots));
        sort($readmarkSlotNumbers);

        $missingReaders = array_values(array_diff($sealedReaders, $coveredReaderNames));
        $missingPages = array_values(array_diff($sealedPages, $coveredPageNumbers));
        $readmarkLayoutSafe = self::readmarkLayoutSafe($acceptedRows);

        $blockedReasons = [];
        foreach ($blockedRows as $row) {
            foreach ($row['blocked_reasons'] as $reason) {
                $blockedReasons[] = $reason;
            }
        }
        foreach ($duplicateNames as $name) {
            $blockedReasons[] = 'reopened_reader_receipt_name_duplicate:' . $name;
        }
        if ($missingReaders !== []) {
            $blockedReasons[] = 'reopened_reader_coverage_missing';
        }
        if ($missingPages !== []) {
            $blockedReasons[] = 'reopened_reader_page_coverage_missing';
        }
        if (!$readmarkLayoutSafe) {
            $blockedReasons[] = 'reopened_reader_readmark_layout_unsafe';
        }
        $blockedReasons = array_values(array_unique($blockedReasons));

        $guardRows = [
            ['name' => 'next252_source_sealed', 'matched' => true, 'reason' => 'WAL truncate, SHM reset, read-mark reset, journal unlink, and directory sync already reached durable seal'],
            ['name' => 'reader_receipt_names_unique', 'matched' => $duplicateNames === [], 'reason' => 'each reopened reader receipt must be attributable once'],
            ['name' => 'sealed_readers_reopened', 'matched' => $missingReaders === [], 'reason' => 'each WordPress reader released during checkpoint seal must reopen on the new source generation'],
            ['name' => 'sealed_pages_visible', 'matched' => $missingPages === [], 'reason' => 'each checkpoint-covered page must be visible from at least one reopened reader receipt'],
            ['name' => 'readmark_layout_safe', 'matched' => $readmarkLayoutSafe, 'reason' => 'readmark slots must be non-zero, unique per reader, and pinned to the restarted generation'],
            ['name' => 'all_reader_receipts_current', 'matched' => $blockedRows === [], 'reason' => 'reader receipts must match paths, token, generation, digest, checkpoint sequence, savepoint depth, hot-journal state, and sync flags'],
        ];
        $blockedGuards = array_values(array_column(array_filter($guardRows, static fn (array $row): bool => !$row['matched']), 'name'));
        $admitted = $blockedGuards === [];

        return [
            'status' => $admitted
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next256'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next256',
            'reason' => $admitted
                ? 'reopened_readers_admitted_on_sealed_checkpoint_source'
                : 'reopened_readers_held_until_sealed_checkpoint_source_matches',
            'base_status' => $sealedPlan['status'],
            'database_path' => $databasePath,
            'wal_path' => $walPath,
            'journal_path' => $journalPath,
            'source_token' => $sourceToken,
            'source_generation' => $sourceGeneration,
            'database_digest' => $databaseDigest,
            'sealed_reader_names' => $sealedReaders,
            'sealed_page_numbers' => $sealedPages,
            'reader_rows' => $rows,
            'reader_receipt_names' => $receiptNames,
            'duplicate_reader_receipt_names' => $duplicateNames,
            'accepted_reader_receipt_names' => array_values(array_column($acceptedRows, 'name')),
            'blocked_reader_receipt_names' => array_values(array_column($blockedRows, 'name')),
            'covered_reader_names' => $coveredReaderNames,
            'missing_reader_names' => $missingReaders,
            'covered_page_numbers' => $coveredPageNumbers,
            'missing_page_numbers' => $missingPages,
            'readmark_slots' => $readmarkSlotNumbers,
            'readmark_layout_safe' => $readmarkLayoutSafe,
            'max_checkpoint_sequence' => $maxCheckpointSequence,
            'min_database_change_counter' => $minChangeCounter ?? 0,
            'blocked_reasons' => $blockedReasons,
            'guard_rows' => $guardRows,
            'guard_names' => array_values(array_column($guardRows, 'name')),
            'guard_matches' => array_values(array_column($guardRows, 'matched')),
            'blocked_guard_names' => $blockedGuards,
            'reopened_readers_admitted' => $admitted,
            'reader_action' => $admitted ? 'serve_reopened_readers_from_current_source_generation_' . $sourceGeneration : 'retry_reader_open_after_checkpoint_source_refresh',
            'wal_action' => $admitted ? 'keep_empty_restarted_wal_as_current_source' : 'preserve_checkpoint_wal_restart_guard',
            'journal_action' => $admitted ? 'hot_journal_retirement_visible_to_readers' : 'block_reader_until_hot_journal_absence_confirmed',
            'current_source_digest' => hash('sha256', json_encode([$sourceToken, $sourceGeneration, $databaseDigest, $coveredReaderNames, $coveredPageNumbers, $rows, $guardRows], JSON_THROW_ON_ERROR)),
            'operation_names' => array_values(array_unique(array_merge(
                is_array($sealedPlan['operation_names'] ?? null) ? $sealedPlan['operation_names'] : [],
                [
                    'verify_reopened_readers_current_source_next256',
                    $admitted ? 'admit_reopened_readers_current_source_next256' : 'block_reopened_readers_current_source_next256',
                ]
            ))),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($sealedPlan['dependencies'] ?? null) ? $sealedPlan['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next256',
                    'sqlite-wal-reopened-reader-current-source-admission',
                    'wordpress-import-reader-reopen-after-wal-checkpoint-seal',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses next252 post-truncate seal metadata and native PHP receipt validation for reopened readers, database header counters, read-mark slots, hot-journal absence, WAL/SHM sync state, and savepoint closure',
            'non_overlap' => 'next256 admits reopened readers only after next252 post-truncate source sealing; it does not repeat WAL sidecar truncation, read-mark reset, hot-journal unlink, VFS savepoint rollback, rollback-journal apply/commit, checkpoint transaction planning, VFS sync apply, JSON table, SELECT, encoding, or B-tree surfaces',
        ];
    }

    /**
     * @param array<string,mixed> $receipt
     * @param list<string> $sealedReaders
     * @param list<int> $sealedPages
     * @return array<string,mixed>
     */
    private static function readerRow(
        array $receipt,
        string $databasePath,
        string $walPath,
        string $journalPath,
        string $sourceToken,
        int $sourceGeneration,
        string $databaseDigest,
        array $sealedReaders,
        array $sealedPages
    ): array {
        $name = self::token($receipt['name'] ?? null, 'reader receipt name');
        $readerName = self::token($receipt['reader_name'] ?? null, "{$name} reader name");
        $pageNumbers = self::positiveIntList($receipt['page_numbers'] ?? null, "{$name} page numbers");
        $readmarkSlot = self::positiveInt($receipt['readmark_slot'] ?? null, "{$name} readmark slot");
        $checkpointSequence = self::positiveInt($receipt['checkpoint_sequence'] ?? null, "{$name} checkpoint sequence");
        $databaseChangeCounter = self::positiveInt($receipt['database_change_counter'] ?? null, "{$name} database change counter");
        $schemaCookie = self::positiveInt($receipt['schema_cookie'] ?? null, "{$name} schema cookie");
        $reasons = [];

        if (!in_array($readerName, $sealedReaders, true)) {
            $reasons[] = 'reopened_reader_not_in_sealed_reader_set';
        }
        if (self::path($receipt['database_path'] ?? null, "{$name} database path") !== $databasePath) {
            $reasons[] = 'reopened_reader_database_path_mismatch';
        }
        if (self::path($receipt['wal_path'] ?? null, "{$name} wal path") !== $walPath) {
            $reasons[] = 'reopened_reader_wal_path_mismatch';
        }
        if (self::path($receipt['journal_path'] ?? null, "{$name} journal path") !== $journalPath) {
            $reasons[] = 'reopened_reader_journal_path_mismatch';
        }
        if (self::token($receipt['source_token'] ?? null, "{$name} source token") !== $sourceToken) {
            $reasons[] = 'reopened_reader_source_token_mismatch';
        }
        if (self::positiveInt($receipt['source_generation'] ?? null, "{$name} source generation") !== $sourceGeneration) {
            $reasons[] = 'reopened_reader_source_generation_mismatch';
        }
        if (self::digest($receipt['database_digest'] ?? null, "{$name} database digest") !== $databaseDigest) {
            $reasons[] = 'reopened_reader_database_digest_mismatch';
        }
        foreach ($pageNumbers as $pageNumber) {
            if (!in_array($pageNumber, $sealedPages, true)) {
                $reasons[] = 'reopened_reader_page_not_in_sealed_checkpoint';
            }
        }
        if (($receipt['wal_size'] ?? null) !== 0) {
            $reasons[] = 'reopened_reader_wal_not_empty';
        }
        if (($receipt['shm_mx_frame'] ?? null) !== 0) {
            $reasons[] = 'reopened_reader_shm_mxframe_not_reset';
        }
        if (($receipt['hot_journal_exists'] ?? null) !== false) {
            $reasons[] = 'reopened_reader_hot_journal_present';
        }
        if (($receipt['pending_savepoint_depth'] ?? null) !== 0) {
            $reasons[] = 'reopened_reader_savepoint_scope_open';
        }
        if (($receipt['database_synced'] ?? null) !== true) {
            $reasons[] = 'reopened_reader_database_not_synced';
        }
        if (($receipt['directory_synced'] ?? null) !== true) {
            $reasons[] = 'reopened_reader_directory_not_synced';
        }
        if (($receipt['read_transaction_open'] ?? null) !== true) {
            $reasons[] = 'reopened_reader_read_transaction_missing';
        }
        if (($receipt['io_error'] ?? null) !== null) {
            $reasons[] = 'reopened_reader_io_error';
        }

        $reasons = array_values(array_unique($reasons));

        return [
            'name' => $name,
            'reader_name' => $readerName,
            'database_path' => (string) $receipt['database_path'],
            'wal_path' => (string) $receipt['wal_path'],
            'journal_path' => (string) $receipt['journal_path'],
            'source_token' => (string) $receipt['source_token'],
            'source_generation' => (int) $receipt['source_generation'],
            'database_digest' => (string) $receipt['database_digest'],
            'page_numbers' => $pageNumbers,
            'readmark_slot' => $readmarkSlot,
            'checkpoint_sequence' => $checkpointSequence,
            'database_change_counter' => $databaseChangeCounter,
            'schema_cookie' => $schemaCookie,
            'wal_size' => (int) ($receipt['wal_size'] ?? -1),
            'shm_mx_frame' => (int) ($receipt['shm_mx_frame'] ?? -1),
            'hot_journal_exists' => ($receipt['hot_journal_exists'] ?? null) === true,
            'pending_savepoint_depth' => (int) ($receipt['pending_savepoint_depth'] ?? -1),
            'database_synced' => ($receipt['database_synced'] ?? null) === true,
            'directory_synced' => ($receipt['directory_synced'] ?? null) === true,
            'read_transaction_open' => ($receipt['read_transaction_open'] ?? null) === true,
            'io_error' => $receipt['io_error'] ?? null,
            'accepted' => $reasons === [],
            'acceptance_reason' => $reasons === [] ? 'reopened_reader_current_source_matches' : implode('|', $reasons),
            'blocked_reasons' => $reasons,
        ];
    }

    /** @param list<array<string,mixed>> $acceptedRows */
    private static function readmarkLayoutSafe(array $acceptedRows): bool
    {
        if ($acceptedRows === []) {
            return false;
        }
        $byReader = [];
        $bySlot = [];
        foreach ($acceptedRows as $row) {
            if (isset($byReader[$row['reader_name']]) || isset($bySlot[$row['readmark_slot']])) {
                return false;
            }
            $byReader[$row['reader_name']] = true;
            $bySlot[$row['readmark_slot']] = true;
        }

        return true;
    }

    private static function positiveInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value <= 0) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next256 {$label} must be positive");
        }

        return $value;
    }

    private static function token(mixed $value, string $label): string
    {
        if (!is_string($value) || !preg_match('/^[A-Za-z0-9_.:-]+$/', $value)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next256 {$label} is invalid");
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
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next256 requires {$label}");
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
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next256 {$label} is required");
        }

        return $value;
    }

    private static function digest(mixed $value, string $label): string
    {
        if (!is_string($value) || preg_match('/^[a-f0-9]{64}$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next256 requires {$label}");
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
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next256 requires {$label}");
        }
        $out = [];
        foreach ($values as $value) {
            if (!is_int($value) || $value <= 0) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next256 {$label} must contain positive integers");
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
