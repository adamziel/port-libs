<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext226Plan
{
    /**
     * @param array<string,mixed> $resetPlan
     * @param array<string,string> $files
     * @param list<array<string,mixed>> $receipts
     * @return array<string,mixed>
     */
    public static function verifyResetFiles(array $resetPlan, array $files, array $receipts, string $expectedWalBytes, string $expectedDatabaseDigest): array
    {
        self::assertResetPlan($resetPlan);
        if ($files === [] || $receipts === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next226 requires files and reset receipts');
        }
        if ($expectedWalBytes === '' || !self::isDigest($expectedDatabaseDigest)) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next226 requires expected WAL bytes and database digest');
        }

        $databasePath = (string) $resetPlan['database_path'];
        $walPath = (string) $resetPlan['wal_path'];
        $journalPath = (string) $resetPlan['journal_path'];
        $mode = (string) $resetPlan['mode'];
        $expectedWalDigest = hash('sha256', $expectedWalBytes);
        $fileRows = self::fileRows($files, $databasePath, $walPath, $journalPath, $expectedWalBytes, $expectedDatabaseDigest);
        $receiptRows = [];
        foreach ($receipts as $receipt) {
            $receiptRows[] = self::receiptRow($receipt, $mode, $expectedWalDigest, $expectedDatabaseDigest, (int) $resetPlan['next_writer_generation']);
        }

        $blockedReasons = [];
        foreach (array_merge($fileRows, $receiptRows) as $row) {
            foreach ($row['blocked_reasons'] as $reason) {
                $blockedReasons[] = $reason;
            }
        }
        $blockedReasons = array_values(array_unique($blockedReasons));

        $guards = [
            'next218_reset_admitted' => ($resetPlan['status'] ?? null) === 'wal-hot-journal-savepoint-checkpoint-current-source-next218'
                && ($resetPlan['can_reset_wal'] ?? false) === true,
            'database_bytes_match_checkpoint_digest' => $fileRows[0]['matched'],
            'wal_bytes_match_reset_mode' => $fileRows[1]['matched'],
            'hot_journal_absent_after_reset' => $fileRows[2]['matched'],
            'all_reset_receipts_match_current_source' => $blockedReasons === [],
        ];
        $blockedGuards = array_keys(array_filter($guards, static fn (bool $matched): bool => !$matched));
        $admitted = $blockedGuards === [];

        return [
            'status' => $admitted
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next226'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next226',
            'reason' => $admitted
                ? 'restart_truncate_reset_files_match_checkpoint_current_source'
                : 'restart_truncate_reset_files_do_not_match_checkpoint_current_source',
            'base_status' => $resetPlan['status'],
            'mode' => $mode,
            'database_path' => $databasePath,
            'wal_path' => $walPath,
            'journal_path' => $journalPath,
            'expected_database_digest' => $expectedDatabaseDigest,
            'observed_database_digest' => hash('sha256', $files[$databasePath] ?? ''),
            'expected_wal_digest' => $expectedWalDigest,
            'observed_wal_digest' => hash('sha256', $files[$walPath] ?? ''),
            'expected_wal_length' => strlen($expectedWalBytes),
            'observed_wal_length' => strlen($files[$walPath] ?? ''),
            'journal_present' => array_key_exists($journalPath, $files),
            'next_writer_generation' => (int) $resetPlan['next_writer_generation'],
            'file_rows' => $fileRows,
            'receipt_rows' => $receiptRows,
            'receipt_names' => array_values(array_column($receiptRows, 'name')),
            'receipt_count' => count($receiptRows),
            'blocked_reasons' => $blockedReasons,
            'guard_names' => array_keys($guards),
            'guard_matches' => array_values($guards),
            'blocked_guard_names' => $blockedGuards,
            'can_publish_reopened_current_source' => $admitted,
            'operation_names' => array_values(array_unique(array_merge(
                is_array($resetPlan['operation_names'] ?? null) ? $resetPlan['operation_names'] : [],
                [
                    'verify_reset_file_state_current_source_next226',
                    $admitted ? 'publish_reopened_current_source_after_reset_next226' : 'block_reopened_current_source_after_reset_next226',
                ]
            ))),
            'publication_digest' => hash('sha256', json_encode([$mode, $fileRows, $receiptRows], JSON_THROW_ON_ERROR)),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($resetPlan['dependencies'] ?? null) ? $resetPlan['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next226',
                    'sqlite-wal-reset-file-state-reopen-fence',
                    'wordpress-import-wal-reset-file-state-reopen',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses next218 restart/truncate admission, file-state digests, hot-journal absence checks, and durable reset receipts',
            'non_overlap' => 'next226 verifies post-reset file-state receipts after accepted next218 restart/truncate admission; it does not repeat next218 reset admission, next219 checkpoint publication, WAL byte truncation, rollback-journal commit/apply, VFS savepoint rollback, or reader-slot validation',
        ];
    }

    /**
     * @param array<string,mixed> $resetPlan
     */
    private static function assertResetPlan(array $resetPlan): void
    {
        foreach (['status', 'mode', 'database_path', 'wal_path', 'journal_path', 'can_reset_wal', 'next_writer_generation'] as $key) {
            if (!array_key_exists($key, $resetPlan)) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next226 missing reset {$key}");
            }
        }
        if (($resetPlan['status'] ?? null) !== 'wal-hot-journal-savepoint-checkpoint-current-source-next218' || ($resetPlan['can_reset_wal'] ?? false) !== true) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next226 requires an admitted next218 reset plan');
        }
        if (!in_array($resetPlan['mode'], ['restart', 'truncate'], true)) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next226 mode must be restart or truncate');
        }
        foreach (['database_path', 'wal_path', 'journal_path'] as $key) {
            if (!is_string($resetPlan[$key]) || $resetPlan[$key] === '') {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next226 {$key} is required");
            }
        }
        if (!is_int($resetPlan['next_writer_generation']) || $resetPlan['next_writer_generation'] <= 0) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next226 next writer generation must be positive');
        }
    }

    /**
     * @param array<string,string> $files
     * @return list<array<string,mixed>>
     */
    private static function fileRows(array $files, string $databasePath, string $walPath, string $journalPath, string $expectedWalBytes, string $expectedDatabaseDigest): array
    {
        foreach ($files as $path => $bytes) {
            if (!is_string($path) || $path === '' || !is_string($bytes)) {
                throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next226 files must map non-empty paths to byte strings');
            }
        }

        $databaseBytes = $files[$databasePath] ?? null;
        $walBytes = $files[$walPath] ?? null;
        $journalPresent = array_key_exists($journalPath, $files);

        return [
            self::fileRow('database', $databasePath, $databaseBytes, $expectedDatabaseDigest, 'database_digest_mismatch'),
            self::fileRow('wal', $walPath, $walBytes, hash('sha256', $expectedWalBytes), 'wal_reset_bytes_mismatch', strlen($expectedWalBytes)),
            [
                'name' => 'hot-journal',
                'path' => $journalPath,
                'matched' => !$journalPresent,
                'observed_digest' => $journalPresent ? hash('sha256', (string) $files[$journalPath]) : null,
                'expected_digest' => null,
                'observed_length' => $journalPresent ? strlen((string) $files[$journalPath]) : 0,
                'expected_length' => 0,
                'blocked_reasons' => $journalPresent ? ['hot_journal_still_present_after_reset'] : [],
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function fileRow(string $name, string $path, ?string $bytes, string $expectedDigest, string $blockedReason, ?int $expectedLength = null): array
    {
        $observedDigest = $bytes === null ? null : hash('sha256', $bytes);
        $observedLength = $bytes === null ? 0 : strlen($bytes);
        $matched = $bytes !== null && hash_equals($expectedDigest, (string) $observedDigest) && ($expectedLength === null || $observedLength === $expectedLength);

        return [
            'name' => $name,
            'path' => $path,
            'matched' => $matched,
            'observed_digest' => $observedDigest,
            'expected_digest' => $expectedDigest,
            'observed_length' => $observedLength,
            'expected_length' => $expectedLength,
            'blocked_reasons' => $matched ? [] : [$bytes === null ? $name . '_file_missing_after_reset' : $blockedReason],
        ];
    }

    /**
     * @param array<string,mixed> $receipt
     * @return array<string,mixed>
     */
    private static function receiptRow(array $receipt, string $mode, string $expectedWalDigest, string $expectedDatabaseDigest, int $expectedGeneration): array
    {
        $name = $receipt['name'] ?? null;
        if (!is_string($name) || $name === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next226 receipt name is required');
        }
        $generation = $receipt['writer_generation'] ?? null;
        if (!is_int($generation) || $generation <= 0) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next226 {$name} writer generation is required");
        }
        foreach (['database_digest', 'wal_digest'] as $key) {
            if (!is_string($receipt[$key] ?? null) || !self::isDigest((string) $receipt[$key])) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next226 {$name} {$key} is required");
            }
        }

        $reasons = [];
        if (($receipt['mode'] ?? null) !== $mode) {
            $reasons[] = 'reset_mode_mismatch';
        }
        if ($generation !== $expectedGeneration) {
            $reasons[] = 'writer_generation_mismatch';
        }
        if (!hash_equals($expectedDatabaseDigest, (string) $receipt['database_digest'])) {
            $reasons[] = 'receipt_database_digest_mismatch';
        }
        if (!hash_equals($expectedWalDigest, (string) $receipt['wal_digest'])) {
            $reasons[] = 'receipt_wal_digest_mismatch';
        }
        foreach ([
            'database_synced' => 'database_sync_missing',
            'wal_synced' => 'wal_sync_missing',
            'directory_synced' => 'directory_sync_missing',
            'hot_journal_absent' => 'hot_journal_absence_receipt_missing',
            'savepoint_closed' => 'savepoint_closed_receipt_missing',
            'readers_reopened' => 'readers_reopened_receipt_missing',
        ] as $key => $reason) {
            if (($receipt[$key] ?? false) !== true) {
                $reasons[] = $reason;
            }
        }

        return [
            'name' => $name,
            'mode' => $receipt['mode'] ?? null,
            'writer_generation' => $generation,
            'database_digest' => $receipt['database_digest'],
            'wal_digest' => $receipt['wal_digest'],
            'database_digest_matches' => hash_equals($expectedDatabaseDigest, (string) $receipt['database_digest']),
            'wal_digest_matches' => hash_equals($expectedWalDigest, (string) $receipt['wal_digest']),
            'database_synced' => ($receipt['database_synced'] ?? false) === true,
            'wal_synced' => ($receipt['wal_synced'] ?? false) === true,
            'directory_synced' => ($receipt['directory_synced'] ?? false) === true,
            'hot_journal_absent' => ($receipt['hot_journal_absent'] ?? false) === true,
            'savepoint_closed' => ($receipt['savepoint_closed'] ?? false) === true,
            'readers_reopened' => ($receipt['readers_reopened'] ?? false) === true,
            'admitted' => $reasons === [],
            'blocked_reasons' => $reasons,
            'receipt_reason' => $reasons === [] ? 'reset_file_state_receipt_matches_current_source' : implode(',', $reasons),
        ];
    }

    private static function isDigest(string $value): bool
    {
        return preg_match('/^[a-f0-9]{64}$/', $value) === 1;
    }
}
