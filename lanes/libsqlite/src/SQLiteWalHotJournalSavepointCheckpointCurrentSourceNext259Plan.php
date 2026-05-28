<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext259Plan
{
    /**
     * @param array<string,mixed> $sealedPlan
     * @param list<array<string,mixed>> $writerReceipts
     * @return array<string,mixed>
     */
    public static function admitWriterAfterPostTruncateSeal(array $sealedPlan, array $writerReceipts): array
    {
        if (($sealedPlan['status'] ?? null) !== 'wal-hot-journal-savepoint-checkpoint-current-source-next252'
            || ($sealedPlan['post_truncate_source_sealed'] ?? null) !== true
        ) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next259 requires sealed next252 current source');
        }
        if ($writerReceipts === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next259 requires writer-generation receipts');
        }

        $databasePath = self::path($sealedPlan['database_path'] ?? null, 'database path');
        $walPath = self::path($sealedPlan['wal_path'] ?? null, 'wal path');
        $journalPath = self::path($sealedPlan['journal_path'] ?? null, 'journal path');
        $sourceToken = self::token($sealedPlan['source_token'] ?? null, 'source token');
        $writerGeneration = self::positiveInt($sealedPlan['writer_generation'] ?? null, 'writer generation');
        $nextSourceGeneration = self::positiveInt($sealedPlan['next_source_generation'] ?? null, 'next source generation');
        $databaseDigest = self::digest($sealedPlan['database_digest'] ?? null, 'database digest');
        $releasedReaders = self::tokenList($sealedPlan['released_reader_names'] ?? null, 'released reader names');
        $coveredPages = self::positiveIntList($sealedPlan['covered_page_numbers'] ?? null, 'covered pages');

        $rows = [];
        foreach ($writerReceipts as $receipt) {
            $rows[] = self::writerRow($receipt, $databasePath, $walPath, $journalPath, $sourceToken, $writerGeneration, $nextSourceGeneration, $databaseDigest);
        }

        $receiptNames = array_values(array_column($rows, 'name'));
        $duplicateNames = self::duplicates($receiptNames);
        $acceptedRows = array_values(array_filter($rows, static fn (array $row): bool => $row['accepted']));
        $blockedRows = array_values(array_filter($rows, static fn (array $row): bool => !$row['accepted']));

        $requiredKinds = ['database-header-cookie', 'reader-generation-reset', 'shm-generation-publish', 'wal-header-publish', 'writer-lock-release'];
        $acceptedKinds = array_values(array_unique(array_column($acceptedRows, 'kind')));
        sort($acceptedKinds);
        $missingKinds = array_values(array_diff($requiredKinds, $acceptedKinds));

        $operationOrder = array_values(array_column($acceptedRows, 'kind'));
        $orderSafe = self::operationOrderIsSafe($operationOrder);
        $headerSalts = array_values(array_filter(array_column($acceptedRows, 'wal_salt'), static fn (array $salt): bool => $salt !== []));
        $schemaCookies = array_values(array_filter(array_column($acceptedRows, 'schema_cookie_after'), static fn (int $cookie): bool => $cookie > 0));
        $maxFrames = array_values(array_unique(array_column($acceptedRows, 'shm_mx_frame_after')));
        sort($maxFrames);
        $readmarks = [];
        foreach ($acceptedRows as $row) {
            foreach ($row['readmarks_after'] as $mark) {
                $readmarks[$mark] = true;
            }
        }
        $readmarksAfter = array_map('intval', array_keys($readmarks));
        sort($readmarksAfter);

        $blockedReasons = [];
        foreach ($blockedRows as $row) {
            foreach ($row['blocked_reasons'] as $reason) {
                $blockedReasons[] = $reason;
            }
        }
        foreach ($duplicateNames as $name) {
            $blockedReasons[] = 'writer_generation_receipt_name_duplicate:' . $name;
        }
        if ($missingKinds !== []) {
            $blockedReasons[] = 'writer_generation_receipt_kind_missing';
        }
        if (!$orderSafe) {
            $blockedReasons[] = 'writer_generation_order_unsafe';
        }
        if ($headerSalts === []) {
            $blockedReasons[] = 'writer_generation_wal_salt_missing';
        }
        if ($schemaCookies === []) {
            $blockedReasons[] = 'writer_generation_schema_cookie_missing';
        }
        if ($maxFrames !== [0]) {
            $blockedReasons[] = 'writer_generation_shm_frame_not_zero';
        }
        if ($readmarksAfter !== [1]) {
            $blockedReasons[] = 'writer_generation_readmarks_not_reset';
        }
        $blockedReasons = array_values(array_unique($blockedReasons));

        $guards = [
            ['name' => 'next252_post_truncate_source_sealed', 'matched' => true, 'reason' => 'checkpoint current source is durable before accepting a new writer'],
            ['name' => 'writer_receipt_names_unique', 'matched' => $duplicateNames === [], 'reason' => 'writer-generation receipts must be attributable once'],
            ['name' => 'required_writer_receipts_present', 'matched' => $missingKinds === [], 'reason' => 'WAL header, SHM generation, readmark reset, schema cookie, and writer lock receipts are required'],
            ['name' => 'writer_receipt_order_safe', 'matched' => $orderSafe, 'reason' => 'WAL/SHM metadata must publish before schema cookie and writer-lock release'],
            ['name' => 'fresh_wal_salt_published', 'matched' => $headerSalts !== [], 'reason' => 'new writers need a fresh WAL salt after checkpoint reset'],
            ['name' => 'schema_cookie_advanced', 'matched' => $schemaCookies !== [], 'reason' => 'new readers must observe a schema cookie for the advanced source generation'],
            ['name' => 'shm_and_readmarks_reset', 'matched' => $maxFrames === [0] && $readmarksAfter === [1], 'reason' => 'mxFrame and read marks stay at the reset generation before new frames append'],
            ['name' => 'all_writer_receipts_current', 'matched' => $blockedRows === [], 'reason' => 'writer receipts must match paths, source token, generation, digest, locks, and durability'],
        ];
        $blockedGuards = array_values(array_column(array_filter($guards, static fn (array $row): bool => !$row['matched']), 'name'));
        $admitted = $blockedGuards === [];

        return [
            'status' => $admitted
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next259'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next259',
            'reason' => $admitted ? 'post_truncate_writer_generation_admitted' : 'post_truncate_writer_generation_waits_for_fences',
            'base_status' => $sealedPlan['status'],
            'database_path' => $databasePath,
            'wal_path' => $walPath,
            'journal_path' => $journalPath,
            'source_token' => $sourceToken,
            'writer_generation' => $writerGeneration,
            'next_source_generation' => $nextSourceGeneration,
            'database_digest' => $databaseDigest,
            'released_reader_names' => $releasedReaders,
            'covered_page_numbers' => $coveredPages,
            'writer_rows' => $rows,
            'writer_receipt_names' => $receiptNames,
            'duplicate_writer_receipt_names' => $duplicateNames,
            'accepted_writer_kinds' => $acceptedKinds,
            'missing_writer_kinds' => $missingKinds,
            'operation_order' => $operationOrder,
            'operation_order_safe' => $orderSafe,
            'wal_salts' => $headerSalts,
            'schema_cookies_after' => $schemaCookies,
            'shm_mx_frames_after' => $maxFrames,
            'readmarks_after' => $readmarksAfter,
            'blocked_writer_receipt_names' => array_values(array_column($blockedRows, 'name')),
            'blocked_reasons' => $blockedReasons,
            'guard_rows' => $guards,
            'guard_names' => array_values(array_column($guards, 'name')),
            'guard_matches' => array_values(array_column($guards, 'matched')),
            'blocked_guard_names' => $blockedGuards,
            'writer_generation_admitted' => $admitted,
            'writer_action' => $admitted ? 'allow_first_writer_on_source_generation_' . $nextSourceGeneration : 'hold_writer_until_post_truncate_fences_match',
            'wal_action' => $admitted ? 'append_new_frames_after_fresh_wal_header' : 'keep_wal_empty_after_checkpoint_reset',
            'reader_action' => $admitted ? 'new_readers_use_reset_readmark_generation' : 'keep_readers_on_sealed_checkpoint_generation',
            'journal_action' => $admitted ? 'hot_journal_absence_confirmed_before_writer_release' : 'retain_hot_journal_absence_guard',
            'writer_digest' => hash('sha256', json_encode([$sourceToken, $nextSourceGeneration, $databaseDigest, $rows, $guards], JSON_THROW_ON_ERROR)),
            'operation_names' => array_values(array_unique(array_merge(
                is_array($sealedPlan['operation_names'] ?? null) ? $sealedPlan['operation_names'] : [],
                [
                    'verify_post_truncate_writer_generation_current_source_next259',
                    $admitted ? 'admit_post_truncate_writer_generation_next259' : 'block_post_truncate_writer_generation_next259',
                ]
            ))),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($sealedPlan['dependencies'] ?? null) ? $sealedPlan['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next259',
                    'sqlite-wal-post-truncate-writer-generation-admission',
                    'wordpress-import-wal-checkpoint-writer-generation',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses next252 post-truncate seal metadata with native PHP WAL-header, SHM, readmark, schema-cookie, hot-journal absence, and writer-lock receipt checks',
            'non_overlap' => 'next259 admits the first writer generation after a sealed post-truncate checkpoint; it does not repeat next252 sealing, next248 release/truncate admission, durable page writes, WAL byte truncation, rollback-journal apply/commit, VFS savepoint rollback, VFS sync, SELECT, JSON, or B-tree surfaces',
        ];
    }

    /**
     * @param array<string,mixed> $receipt
     * @return array<string,mixed>
     */
    private static function writerRow(array $receipt, string $databasePath, string $walPath, string $journalPath, string $sourceToken, int $writerGeneration, int $nextSourceGeneration, string $databaseDigest): array
    {
        $name = self::token($receipt['name'] ?? null, 'writer receipt name');
        $kind = self::kind($receipt['kind'] ?? null);
        $reasons = [];

        if (self::path($receipt['database_path'] ?? null, "{$name} database path") !== $databasePath) {
            $reasons[] = 'writer_generation_database_path_mismatch';
        }
        if (self::path($receipt['wal_path'] ?? null, "{$name} wal path") !== $walPath) {
            $reasons[] = 'writer_generation_wal_path_mismatch';
        }
        if (self::path($receipt['journal_path'] ?? null, "{$name} journal path") !== $journalPath) {
            $reasons[] = 'writer_generation_journal_path_mismatch';
        }
        if (self::token($receipt['source_token'] ?? null, "{$name} source token") !== $sourceToken) {
            $reasons[] = 'writer_generation_source_token_mismatch';
        }
        if (self::positiveInt($receipt['writer_generation'] ?? null, "{$name} writer generation") !== $writerGeneration) {
            $reasons[] = 'writer_generation_writer_generation_mismatch';
        }
        if (self::positiveInt($receipt['source_generation'] ?? null, "{$name} source generation") !== $nextSourceGeneration) {
            $reasons[] = 'writer_generation_source_generation_mismatch';
        }
        if (self::digest($receipt['database_digest'] ?? null, "{$name} database digest") !== $databaseDigest) {
            $reasons[] = 'writer_generation_database_digest_mismatch';
        }

        $walSalt = self::optionalSalt($receipt['wal_salt'] ?? null, "{$name} wal salt");
        $schemaCookieAfter = self::nonNegativeInt($receipt['schema_cookie_after'] ?? 0, "{$name} schema cookie after");
        $shmMxFrameAfter = self::nonNegativeInt($receipt['shm_mx_frame_after'] ?? 0, "{$name} shm mx frame after");
        $readmarksAfter = self::optionalPositiveIntList($receipt['readmarks_after'] ?? null, "{$name} readmarks after");
        $walSizeBeforeAppend = self::nonNegativeInt($receipt['wal_size_before_append'] ?? 0, "{$name} wal size before append");

        if ($kind === 'wal-header-publish' && ($walSalt === [] || $walSizeBeforeAppend !== 32)) {
            $reasons[] = 'writer_generation_wal_header_not_fresh';
        }
        if ($kind === 'shm-generation-publish' && $shmMxFrameAfter !== 0) {
            $reasons[] = 'writer_generation_shm_mxframe_not_reset';
        }
        if ($kind === 'reader-generation-reset' && $readmarksAfter !== [1]) {
            $reasons[] = 'writer_generation_readmarks_not_reset';
        }
        if ($kind === 'database-header-cookie' && $schemaCookieAfter < $nextSourceGeneration) {
            $reasons[] = 'writer_generation_schema_cookie_not_advanced';
        }
        if ($kind === 'writer-lock-release' && (($receipt['writer_lock_released'] ?? false) !== true)) {
            $reasons[] = 'writer_generation_lock_not_released';
        }
        if (($receipt['hot_journal_exists'] ?? true) !== false) {
            $reasons[] = 'writer_generation_hot_journal_still_exists';
        }
        if (($receipt['exclusive_lock_held_until_release'] ?? false) !== true) {
            $reasons[] = 'writer_generation_exclusive_lock_missing';
        }
        if (($receipt['durable'] ?? false) !== true) {
            $reasons[] = 'writer_generation_receipt_not_durable';
        }
        if (($receipt['io_error'] ?? null) !== null) {
            $reasons[] = 'writer_generation_io_error';
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
            'wal_salt' => $walSalt,
            'schema_cookie_after' => $schemaCookieAfter,
            'shm_mx_frame_after' => $shmMxFrameAfter,
            'readmarks_after' => $readmarksAfter,
            'wal_size_before_append' => $walSizeBeforeAppend,
            'hot_journal_exists' => ($receipt['hot_journal_exists'] ?? true) === true,
            'exclusive_lock_held_until_release' => ($receipt['exclusive_lock_held_until_release'] ?? false) === true,
            'writer_lock_released' => ($receipt['writer_lock_released'] ?? false) === true,
            'durable' => ($receipt['durable'] ?? false) === true,
            'io_error' => $receipt['io_error'] ?? null,
            'accepted' => $reasons === [],
            'acceptance_reason' => $reasons === [] ? 'post_truncate_writer_generation_current' : implode('|', $reasons),
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
        foreach (['wal-header-publish', 'shm-generation-publish', 'reader-generation-reset', 'database-header-cookie', 'writer-lock-release'] as $kind) {
            if (!isset($positions[$kind])) {
                return false;
            }
        }

        return $positions['wal-header-publish'] < $positions['database-header-cookie']
            && $positions['shm-generation-publish'] < $positions['database-header-cookie']
            && $positions['reader-generation-reset'] < $positions['database-header-cookie']
            && $positions['database-header-cookie'] < $positions['writer-lock-release'];
    }

    private static function positiveInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value <= 0) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next259 {$label} must be positive");
        }
        return $value;
    }

    private static function nonNegativeInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next259 {$label} must be non-negative");
        }
        return $value;
    }

    private static function token(mixed $value, string $label): string
    {
        if (!is_string($value) || !preg_match('/^[A-Za-z0-9_.:-]+$/', $value)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next259 {$label} is invalid");
        }
        return $value;
    }

    private static function kind(mixed $value): string
    {
        if (!in_array($value, ['database-header-cookie', 'reader-generation-reset', 'shm-generation-publish', 'wal-header-publish', 'writer-lock-release'], true)) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next259 writer receipt kind is invalid');
        }
        return $value;
    }

    private static function path(mixed $value, string $label): string
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next259 {$label} is required");
        }
        return $value;
    }

    private static function digest(mixed $value, string $label): string
    {
        if (!is_string($value) || preg_match('/^[a-f0-9]{64}$/', $value) !== 1) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next259 requires {$label}");
        }
        return $value;
    }

    /** @return list<string> */
    private static function tokenList(mixed $values, string $label): array
    {
        if (!is_array($values) || $values === []) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next259 requires {$label}");
        }
        $out = [];
        foreach ($values as $value) {
            $out[] = self::token($value, $label);
        }
        $out = array_values(array_unique($out));
        sort($out);
        return $out;
    }

    /** @return list<int> */
    private static function positiveIntList(mixed $values, string $label): array
    {
        if (!is_array($values) || $values === []) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next259 requires {$label}");
        }
        return self::optionalPositiveIntList($values, $label);
    }

    /** @return list<int> */
    private static function optionalPositiveIntList(mixed $values, string $label): array
    {
        if ($values === null) {
            return [];
        }
        if (!is_array($values)) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next259 requires {$label}");
        }
        $out = [];
        foreach ($values as $value) {
            if (!is_int($value) || $value <= 0) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next259 {$label} must contain positive integers");
            }
            $out[] = $value;
        }
        $out = array_values(array_unique($out));
        sort($out);
        return $out;
    }

    /** @return list<string> */
    private static function optionalSalt(mixed $value, string $label): array
    {
        if ($value === null) {
            return [];
        }
        if (!is_array($value) || count($value) !== 2) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next259 requires {$label}");
        }
        return [self::token($value[0] ?? null, $label), self::token($value[1] ?? null, $label)];
    }

    /** @param list<string> $values @return list<string> */
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
