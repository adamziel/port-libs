<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext252Plan
{
    /**
     * @param array<string,mixed> $truncationPlan
     * @param list<array<string,mixed>> $sealReceipts
     * @return array<string,mixed>
     */
    public static function sealPostTruncateSource(array $truncationPlan, array $sealReceipts): array
    {
        if (($truncationPlan['status'] ?? null) !== 'wal-hot-journal-savepoint-checkpoint-current-source-next248'
            || ($truncationPlan['checkpoint_truncation_admitted'] ?? null) !== true
        ) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next252 requires admitted next248 WAL truncation');
        }
        if ($sealReceipts === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next252 requires post-truncate seal receipts');
        }

        $databasePath = self::path($truncationPlan['database_path'] ?? null, 'database path');
        $walPath = self::path($truncationPlan['wal_path'] ?? null, 'wal path');
        $journalPath = self::path($truncationPlan['journal_path'] ?? null, 'journal path');
        $sourceToken = self::token($truncationPlan['source_token'] ?? null, 'source token');
        $writerGeneration = self::positiveInt($truncationPlan['writer_generation'] ?? null, 'writer generation');
        $nextSourceGeneration = self::positiveInt($truncationPlan['next_source_generation'] ?? null, 'next source generation');
        $databaseDigest = self::digest($truncationPlan['database_digest'] ?? null, 'database digest');
        $readerNames = self::tokenList($truncationPlan['released_reader_names'] ?? null, 'released reader names');
        $coveredPages = self::positiveIntList($truncationPlan['covered_page_numbers'] ?? null, 'covered pages');

        $rows = [];
        foreach ($sealReceipts as $receipt) {
            $rows[] = self::sealRow(
                $receipt,
                $databasePath,
                $walPath,
                $journalPath,
                $sourceToken,
                $writerGeneration,
                $nextSourceGeneration,
                $databaseDigest,
                $readerNames,
                $coveredPages
            );
        }

        $receiptNames = array_values(array_column($rows, 'name'));
        $duplicateNames = self::duplicates($receiptNames);
        $acceptedRows = array_values(array_filter($rows, static fn (array $row): bool => $row['accepted']));
        $blockedRows = array_values(array_filter($rows, static fn (array $row): bool => !$row['accepted']));

        $requiredKinds = ['directory-sync', 'journal-unlink', 'readmark-reset', 'shm-reset', 'wal-truncate'];
        $acceptedKinds = array_values(array_unique(array_column($acceptedRows, 'kind')));
        sort($acceptedKinds);
        $missingKinds = array_values(array_diff($requiredKinds, $acceptedKinds));

        $coveredReaders = [];
        $sealedPages = [];
        $operationOrder = [];
        foreach ($acceptedRows as $row) {
            $operationOrder[] = $row['kind'];
            foreach ($row['reader_names'] as $readerName) {
                $coveredReaders[$readerName] = true;
            }
            foreach ($row['page_numbers'] as $pageNumber) {
                $sealedPages[$pageNumber] = true;
            }
        }

        $coveredReaderNames = array_keys($coveredReaders);
        sort($coveredReaderNames);
        $sealedPageNumbers = array_map('intval', array_keys($sealedPages));
        sort($sealedPageNumbers);
        $missingReaders = array_values(array_diff($readerNames, $coveredReaderNames));
        $missingPages = array_values(array_diff($coveredPages, $sealedPageNumbers));
        $orderSafe = self::operationOrderIsSafe($operationOrder);

        $blockedReasons = [];
        foreach ($blockedRows as $row) {
            foreach ($row['blocked_reasons'] as $reason) {
                $blockedReasons[] = $reason;
            }
        }
        foreach ($duplicateNames as $name) {
            $blockedReasons[] = 'post_truncate_seal_name_duplicate:' . $name;
        }
        if ($missingKinds !== []) {
            $blockedReasons[] = 'post_truncate_seal_kind_missing';
        }
        if ($missingReaders !== []) {
            $blockedReasons[] = 'post_truncate_reader_seal_missing';
        }
        if ($missingPages !== []) {
            $blockedReasons[] = 'post_truncate_page_seal_missing';
        }
        if (!$orderSafe) {
            $blockedReasons[] = 'post_truncate_seal_order_unsafe';
        }
        $blockedReasons = array_values(array_unique($blockedReasons));

        $guardRows = [
            ['name' => 'next248_truncation_admitted', 'matched' => true, 'reason' => 'all reopened readers released before this seal phase'],
            ['name' => 'seal_receipt_names_unique', 'matched' => $duplicateNames === [], 'reason' => 'each post-truncate receipt must be attributable once'],
            ['name' => 'required_seal_kinds_present', 'matched' => $missingKinds === [], 'reason' => 'WAL truncate, SHM reset, read-mark reset, journal unlink, and directory sync receipts are required'],
            ['name' => 'released_readers_sealed', 'matched' => $missingReaders === [], 'reason' => 'every released WordPress reader must be included in the seal receipts'],
            ['name' => 'checkpoint_pages_sealed', 'matched' => $missingPages === [], 'reason' => 'every checkpoint-covered page must be represented in the sealed source'],
            ['name' => 'seal_order_is_durable', 'matched' => $orderSafe, 'reason' => 'WAL truncate and SHM/read-mark resets must precede journal unlink and directory sync'],
            ['name' => 'all_seal_receipts_current', 'matched' => $blockedRows === [], 'reason' => 'post-truncate receipts must match paths, generations, digest, pages, readers, and durable flags'],
        ];

        $blockedGuards = array_values(array_map(
            static fn (array $row): string => $row['name'],
            array_filter($guardRows, static fn (array $row): bool => !$row['matched'])
        ));
        $sealed = $blockedGuards === [];

        return [
            'status' => $sealed
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next252'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next252',
            'reason' => $sealed
                ? 'post_truncate_current_source_sealed_after_reader_release'
                : 'post_truncate_current_source_waits_for_durable_seal',
            'base_status' => $truncationPlan['status'],
            'database_path' => $databasePath,
            'wal_path' => $walPath,
            'journal_path' => $journalPath,
            'source_token' => $sourceToken,
            'writer_generation' => $writerGeneration,
            'next_source_generation' => $nextSourceGeneration,
            'database_digest' => $databaseDigest,
            'released_reader_names' => $readerNames,
            'covered_page_numbers' => $coveredPages,
            'seal_rows' => $rows,
            'seal_receipt_names' => $receiptNames,
            'duplicate_seal_names' => $duplicateNames,
            'accepted_seal_kinds' => $acceptedKinds,
            'missing_seal_kinds' => $missingKinds,
            'covered_reader_names' => $coveredReaderNames,
            'missing_reader_names' => $missingReaders,
            'sealed_page_numbers' => $sealedPageNumbers,
            'missing_page_numbers' => $missingPages,
            'operation_order' => $operationOrder,
            'blocked_seal_names' => array_values(array_column($blockedRows, 'name')),
            'blocked_reasons' => $blockedReasons,
            'guard_rows' => $guardRows,
            'guard_names' => array_values(array_column($guardRows, 'name')),
            'guard_matches' => array_values(array_column($guardRows, 'matched')),
            'blocked_guard_names' => $blockedGuards,
            'post_truncate_source_sealed' => $sealed,
            'source_action' => $sealed ? 'advance_current_source_generation_' . $nextSourceGeneration : 'retain_previous_current_source_until_sealed',
            'wal_action' => $sealed ? 'keep_checkpoint_wal_truncated' : 'retain_truncated_wal_guard_until_sealed',
            'journal_action' => $sealed ? 'hot_journal_unlink_committed' : 'hold_hot_journal_unlink_receipt',
            'shm_action' => $sealed ? 'publish_zeroed_checkpoint_shm_readmarks' : 'preserve_prior_shm_readmarks',
            'seal_digest' => hash('sha256', json_encode([$sourceToken, $nextSourceGeneration, $readerNames, $coveredPages, $rows, $guardRows], JSON_THROW_ON_ERROR)),
            'operation_names' => array_values(array_unique(array_merge(
                is_array($truncationPlan['operation_names'] ?? null) ? $truncationPlan['operation_names'] : [],
                [
                    'verify_post_truncate_source_seal_current_source_next252',
                    $sealed ? 'advance_checkpoint_current_source_next252' : 'block_checkpoint_current_source_next252',
                ]
            ))),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($truncationPlan['dependencies'] ?? null) ? $truncationPlan['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next252',
                    'sqlite-wal-post-truncate-source-seal-current-source',
                    'wordpress-import-current-source-after-wal-truncate',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses next248 truncation admission with native PHP receipt checks for WAL truncation, SHM/read-mark reset, hot-journal unlink, directory sync, released reader coverage, and checkpoint page coverage',
            'non_overlap' => 'next252 verifies the durable post-truncate current-source seal after next248 reader release; it does not repeat next248 release/truncate admission, next245 reader admission, WAL byte truncation, VFS savepoint rollback apply, rollback-journal commit/apply, or checkpoint transaction planning',
        ];
    }

    /**
     * @param array<string,mixed> $receipt
     * @param list<string> $readerNames
     * @param list<int> $coveredPages
     * @return array<string,mixed>
     */
    private static function sealRow(
        array $receipt,
        string $databasePath,
        string $walPath,
        string $journalPath,
        string $sourceToken,
        int $writerGeneration,
        int $nextSourceGeneration,
        string $databaseDigest,
        array $readerNames,
        array $coveredPages
    ): array {
        $name = self::token($receipt['name'] ?? null, 'seal receipt name');
        $kind = self::kind($receipt['kind'] ?? null);
        $reasons = [];

        if (self::path($receipt['database_path'] ?? null, "{$name} database path") !== $databasePath) {
            $reasons[] = 'post_truncate_database_path_mismatch';
        }
        if (self::path($receipt['wal_path'] ?? null, "{$name} wal path") !== $walPath) {
            $reasons[] = 'post_truncate_wal_path_mismatch';
        }
        if (self::path($receipt['journal_path'] ?? null, "{$name} journal path") !== $journalPath) {
            $reasons[] = 'post_truncate_journal_path_mismatch';
        }
        if (self::token($receipt['source_token'] ?? null, "{$name} source token") !== $sourceToken) {
            $reasons[] = 'post_truncate_source_token_mismatch';
        }
        if (self::positiveInt($receipt['writer_generation'] ?? null, "{$name} writer generation") !== $writerGeneration) {
            $reasons[] = 'post_truncate_writer_generation_mismatch';
        }
        if (self::positiveInt($receipt['source_generation'] ?? null, "{$name} source generation") !== $nextSourceGeneration) {
            $reasons[] = 'post_truncate_source_generation_mismatch';
        }
        if (self::digest($receipt['database_digest'] ?? null, "{$name} database digest") !== $databaseDigest) {
            $reasons[] = 'post_truncate_database_digest_mismatch';
        }

        $receiptReaders = self::tokenList($receipt['reader_names'] ?? null, "{$name} reader names");
        foreach ($receiptReaders as $readerName) {
            if (!in_array($readerName, $readerNames, true)) {
                $reasons[] = 'post_truncate_reader_not_released';
            }
        }

        $pageNumbers = self::positiveIntList($receipt['page_numbers'] ?? null, "{$name} page numbers");
        foreach ($pageNumbers as $pageNumber) {
            if (!in_array($pageNumber, $coveredPages, true)) {
                $reasons[] = 'post_truncate_page_not_checkpoint_covered';
            }
        }

        $walSizeAfter = self::nonNegativeInt($receipt['wal_size_after'] ?? null, "{$name} wal size after");
        $shmMxFrameAfter = self::nonNegativeInt($receipt['shm_mx_frame_after'] ?? null, "{$name} shm mx frame after");
        if ($kind === 'wal-truncate' && $walSizeAfter !== 0) {
            $reasons[] = 'post_truncate_wal_not_empty';
        }
        if ($kind === 'shm-reset' && $shmMxFrameAfter !== 0) {
            $reasons[] = 'post_truncate_shm_mxframe_not_reset';
        }
        if ($kind === 'readmark-reset' && self::positiveIntList($receipt['readmarks_after'] ?? null, "{$name} readmarks after") !== [1]) {
            $reasons[] = 'post_truncate_readmarks_not_reset';
        }
        if ($kind === 'journal-unlink' && ($receipt['journal_exists_after'] ?? true) !== false) {
            $reasons[] = 'post_truncate_hot_journal_still_exists';
        }
        if ($kind === 'directory-sync' && ($receipt['directory_synced'] ?? false) !== true) {
            $reasons[] = 'post_truncate_directory_not_synced';
        }
        if (($receipt['durable'] ?? false) !== true) {
            $reasons[] = 'post_truncate_receipt_not_durable';
        }
        if (($receipt['exclusive_lock_held'] ?? false) !== true) {
            $reasons[] = 'post_truncate_exclusive_lock_missing';
        }
        if (($receipt['pending_savepoint_depth'] ?? 0) !== 0) {
            $reasons[] = 'post_truncate_savepoint_scope_open';
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
            'source_generation' => (int) $receipt['source_generation'],
            'database_digest' => (string) $receipt['database_digest'],
            'reader_names' => $receiptReaders,
            'page_numbers' => $pageNumbers,
            'wal_size_after' => $walSizeAfter,
            'shm_mx_frame_after' => $shmMxFrameAfter,
            'durable' => ($receipt['durable'] ?? false) === true,
            'exclusive_lock_held' => ($receipt['exclusive_lock_held'] ?? false) === true,
            'pending_savepoint_depth' => (int) ($receipt['pending_savepoint_depth'] ?? 0),
            'accepted' => $reasons === [],
            'acceptance_reason' => $reasons === [] ? 'post_truncate_seal_current' : implode('|', $reasons),
            'blocked_reasons' => $reasons,
        ];
    }

    /** @param list<string> $operationOrder */
    private static function operationOrderIsSafe(array $operationOrder): bool
    {
        $positions = [];
        foreach ($operationOrder as $index => $kind) {
            $positions[$kind] ??= $index;
        }
        foreach (['wal-truncate', 'shm-reset', 'readmark-reset', 'journal-unlink', 'directory-sync'] as $kind) {
            if (!isset($positions[$kind])) {
                return false;
            }
        }

        return $positions['wal-truncate'] < $positions['journal-unlink']
            && $positions['shm-reset'] < $positions['journal-unlink']
            && $positions['readmark-reset'] < $positions['journal-unlink']
            && $positions['journal-unlink'] < $positions['directory-sync'];
    }

    private static function positiveInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value <= 0) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next252 {$label} must be positive");
        }

        return $value;
    }

    private static function nonNegativeInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next252 {$label} must be non-negative");
        }

        return $value;
    }

    private static function token(mixed $value, string $label): string
    {
        if (!is_string($value) || !preg_match('/^[A-Za-z0-9_.:-]+$/', $value)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next252 {$label} is invalid");
        }

        return $value;
    }

    private static function kind(mixed $value): string
    {
        if (!in_array($value, ['directory-sync', 'journal-unlink', 'readmark-reset', 'shm-reset', 'wal-truncate'], true)) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next252 seal receipt kind is invalid');
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
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next252 requires {$label}");
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
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next252 {$label} is required");
        }

        return $value;
    }

    private static function digest(mixed $value, string $label): string
    {
        if (!is_string($value) || preg_match('/^[a-f0-9]{64}$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next252 requires {$label}");
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
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next252 requires {$label}");
        }
        $out = [];
        foreach ($values as $value) {
            if (!is_int($value) || $value <= 0) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next252 {$label} must contain positive integers");
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
