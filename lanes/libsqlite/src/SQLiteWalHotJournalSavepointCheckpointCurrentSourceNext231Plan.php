<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext231Plan
{
    /**
     * @param array<string,mixed> $publishPlan
     * @param list<array<string,mixed>> $walIndexReceipts
     * @return array<string,mixed>
     */
    public static function verifyWalIndexReopen(array $publishPlan, array $walIndexReceipts, string $expectedWalDigest): array
    {
        self::assertPublishPlan($publishPlan);
        if ($walIndexReceipts === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal current-source next231 requires WAL-index reopen receipts');
        }
        if (!self::isDigest($expectedWalDigest)) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal current-source next231 expected WAL digest must be a sha256 string');
        }

        $token = $publishPlan['current_source_token'];
        $expectedTokenId = (string) $token['id'];
        $expectedSourceEpoch = (int) $token['epoch'];
        $expectedNextEpoch = (int) $publishPlan['next_source_epoch'];
        $expectedFrame = (int) $publishPlan['checkpoint_frame'];
        $expectedCheckpointCookie = (int) $publishPlan['checkpoint_cookie'];
        $expectedSchemaCookie = (int) $publishPlan['schema_cookie'];
        $expectedScopeNames = self::stringList($publishPlan['publishable_scope_names'], 'publishable scope names');

        $rows = [];
        foreach ($walIndexReceipts as $receipt) {
            $rows[] = self::receiptRow(
                $receipt,
                $expectedScopeNames,
                $expectedTokenId,
                $expectedSourceEpoch,
                $expectedNextEpoch,
                $expectedFrame,
                $expectedCheckpointCookie,
                $expectedSchemaCookie,
                $expectedWalDigest
            );
        }

        $blockedRows = array_values(array_filter($rows, static fn (array $row): bool => !$row['reopenable']));
        $receiptNames = array_values(array_column($rows, 'name'));
        $duplicateNames = self::duplicates($receiptNames);
        $scopeCoverage = self::coveredScopes($rows);
        $missingScopes = array_values(array_diff($expectedScopeNames, $scopeCoverage));
        $extraScopes = array_values(array_diff($scopeCoverage, $expectedScopeNames));
        $blockedReasons = [];
        foreach ($blockedRows as $row) {
            foreach ($row['blocked_reasons'] as $reason) {
                $blockedReasons[] = $reason;
            }
        }
        if ($duplicateNames !== []) {
            $blockedReasons[] = 'duplicate_wal_index_reopen_receipt';
        }
        if ($missingScopes !== []) {
            $blockedReasons[] = 'wal_index_scope_reopen_receipt_missing';
        }
        if ($extraScopes !== []) {
            $blockedReasons[] = 'wal_index_receipt_for_unpublished_scope';
        }
        $blockedReasons = array_values(array_unique($blockedReasons));

        $guards = [
            'next227_publish_receipts_admitted' => ($publishPlan['status'] ?? null) === 'wal-hot-journal-savepoint-checkpoint-current-source-next227'
                && ($publishPlan['checkpoint_publish_allowed'] ?? false) === true,
            'no_duplicate_wal_index_receipts' => $duplicateNames === [],
            'all_publishable_scopes_have_wal_index_reopen_receipts' => $missingScopes === [],
            'no_unpublished_scope_wal_index_receipts' => $extraScopes === [],
            'all_wal_index_receipts_reopenable' => $blockedRows === [],
            'next_source_epoch_matches_publish_epoch' => self::allNextEpochsMatch($rows, $expectedNextEpoch),
            'checkpoint_frame_matches_publish_frame' => self::allCheckpointFramesMatch($rows, $expectedFrame),
        ];
        $blockedGuards = array_keys(array_filter($guards, static fn (bool $matched): bool => !$matched));
        $reopenable = $blockedGuards === [];

        return [
            'status' => $reopenable
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next231'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next231',
            'reason' => $reopenable
                ? 'wal_index_reopen_receipts_match_published_checkpoint_current_source'
                : 'wal_index_reopen_receipts_hold_published_checkpoint_current_source',
            'database_path' => (string) $publishPlan['database_path'],
            'wal_path' => (string) $publishPlan['wal_path'],
            'journal_path' => (string) $publishPlan['journal_path'],
            'current_source_token' => $token,
            'source_epoch' => $expectedSourceEpoch,
            'next_source_epoch' => $expectedNextEpoch,
            'checkpoint_frame' => $expectedFrame,
            'checkpoint_cookie' => $expectedCheckpointCookie,
            'schema_cookie' => $expectedSchemaCookie,
            'expected_wal_digest' => $expectedWalDigest,
            'publishable_scope_names' => $expectedScopeNames,
            'covered_scope_names' => $scopeCoverage,
            'missing_scope_names' => $missingScopes,
            'extra_scope_names' => $extraScopes,
            'duplicate_receipt_names' => $duplicateNames,
            'receipt_names' => $receiptNames,
            'receipt_rows' => $rows,
            'reopenable_receipt_names' => array_values(array_column(array_filter($rows, static fn (array $row): bool => $row['reopenable']), 'name')),
            'blocked_receipt_names' => array_values(array_column($blockedRows, 'name')),
            'blocked_reasons' => $blockedReasons,
            'guard_names' => array_keys($guards),
            'guard_matches' => array_values($guards),
            'blocked_guard_names' => $blockedGuards,
            'can_reopen_current_source' => $reopenable,
            'reopen_digest' => hash('sha256', json_encode([$token, $expectedWalDigest, $rows, $guards], JSON_THROW_ON_ERROR)),
            'operation_names' => array_values(array_unique(array_merge(
                is_array($publishPlan['operation_names'] ?? null) ? $publishPlan['operation_names'] : [],
                [
                    'verify_wal_index_reopen_receipts_current_source_next231',
                    'validate_reader_readmarks_after_checkpoint_publish_next231',
                    $reopenable ? 'advance_reopened_current_source_after_wal_index_next231' : 'hold_reopened_current_source_after_wal_index_next231',
                ]
            ))),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($publishPlan['dependencies'] ?? null) ? $publishPlan['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next231',
                    'sqlite-wal-index-reopen-readmark-fence',
                    'wordpress-import-wal-index-reopen-current-source',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses next227 publish receipts plus WAL-index salt/checksum/readmark metadata already modeled in lane-local WAL primitives',
            'non_overlap' => 'next231 verifies reopened WAL-index/readmark receipts after next227 publish receipts; it does not repeat next227 publish sealing, next226 file-state receipts, next218 reset admission, WAL byte truncation, VFS savepoint rollback, rollback-journal commit/apply, or standalone SHM read-mark diagnostics',
        ];
    }

    /**
     * @param array<string,mixed> $plan
     */
    private static function assertPublishPlan(array $plan): void
    {
        foreach (['status', 'database_path', 'wal_path', 'journal_path', 'current_source_token', 'checkpoint_frame', 'checkpoint_cookie', 'schema_cookie', 'next_source_epoch', 'publishable_scope_names', 'checkpoint_publish_allowed'] as $key) {
            if (!array_key_exists($key, $plan)) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal current-source next231 missing publish {$key}");
            }
        }
        if (($plan['status'] ?? null) !== 'wal-hot-journal-savepoint-checkpoint-current-source-next227' || ($plan['checkpoint_publish_allowed'] ?? false) !== true) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal current-source next231 requires an admitted next227 publish plan');
        }
        if (!is_array($plan['current_source_token']) || (string) ($plan['current_source_token']['id'] ?? '') === '' || (int) ($plan['current_source_token']['epoch'] ?? 0) <= 0) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal current-source next231 token is invalid');
        }
        foreach (['checkpoint_frame', 'checkpoint_cookie', 'schema_cookie', 'next_source_epoch'] as $key) {
            if (!is_int($plan[$key]) || $plan[$key] <= 0) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal current-source next231 {$key} must be positive");
            }
        }
        if ((int) $plan['next_source_epoch'] <= (int) $plan['current_source_token']['epoch']) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal current-source next231 next source epoch must advance');
        }
        foreach (['database_path', 'wal_path', 'journal_path'] as $key) {
            if (!is_string($plan[$key]) || $plan[$key] === '') {
                throw new \InvalidArgumentException("SQLite WAL hot-journal current-source next231 {$key} is required");
            }
        }
    }

    /**
     * @param array<string,mixed> $receipt
     * @param list<string> $expectedScopes
     * @return array<string,mixed>
     */
    private static function receiptRow(
        array $receipt,
        array $expectedScopes,
        string $expectedTokenId,
        int $expectedSourceEpoch,
        int $expectedNextEpoch,
        int $expectedFrame,
        int $expectedCheckpointCookie,
        int $expectedSchemaCookie,
        string $expectedWalDigest
    ): array {
        foreach (['name', 'scope_names', 'source_token_id', 'source_epoch', 'next_source_epoch', 'checkpoint_frame', 'checkpoint_cookie', 'schema_cookie', 'wal_digest', 'salt_1', 'salt_2', 'checksum_digest', 'mx_frame', 'backfill_frame', 'readmark_frames', 'readers_reopened', 'shm_synced'] as $key) {
            if (!array_key_exists($key, $receipt)) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal current-source next231 missing WAL-index receipt {$key}");
            }
        }
        $name = (string) $receipt['name'];
        if ($name === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal current-source next231 WAL-index receipt name is required');
        }
        foreach (['source_epoch', 'next_source_epoch', 'checkpoint_frame', 'checkpoint_cookie', 'schema_cookie', 'salt_1', 'salt_2', 'mx_frame', 'backfill_frame'] as $key) {
            if (!is_int($receipt[$key]) || $receipt[$key] < 0) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal current-source next231 {$name} {$key} must be a non-negative integer");
            }
        }
        foreach (['wal_digest', 'checksum_digest'] as $key) {
            if (!is_string($receipt[$key]) || !self::isDigest($receipt[$key])) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal current-source next231 {$name} {$key} must be a sha256 string");
            }
        }

        $scopeNames = self::stringList($receipt['scope_names'], "{$name} scope names");
        $readmarks = self::readmarkFrames($receipt['readmark_frames'], $name);
        $reasons = [];
        if ((string) $receipt['source_token_id'] !== $expectedTokenId) {
            $reasons[] = 'wal_index_source_token_mismatch';
        }
        if ((int) $receipt['source_epoch'] !== $expectedSourceEpoch) {
            $reasons[] = 'wal_index_source_epoch_mismatch';
        }
        if ((int) $receipt['next_source_epoch'] !== $expectedNextEpoch) {
            $reasons[] = 'wal_index_next_source_epoch_mismatch';
        }
        if ((int) $receipt['checkpoint_frame'] !== $expectedFrame) {
            $reasons[] = 'wal_index_checkpoint_frame_mismatch';
        }
        if ((int) $receipt['checkpoint_cookie'] !== $expectedCheckpointCookie) {
            $reasons[] = 'wal_index_checkpoint_cookie_mismatch';
        }
        if ((int) $receipt['schema_cookie'] !== $expectedSchemaCookie) {
            $reasons[] = 'wal_index_schema_cookie_mismatch';
        }
        if (!hash_equals($expectedWalDigest, (string) $receipt['wal_digest'])) {
            $reasons[] = 'wal_index_wal_digest_mismatch';
        }
        if ((int) $receipt['salt_1'] === 0 || (int) $receipt['salt_2'] === 0) {
            $reasons[] = 'wal_index_salt_missing';
        }
        if ((int) $receipt['mx_frame'] !== $expectedFrame) {
            $reasons[] = 'wal_index_mx_frame_mismatch';
        }
        if ((int) $receipt['backfill_frame'] < $expectedFrame) {
            $reasons[] = 'wal_index_backfill_before_checkpoint';
        }
        if (($receipt['readers_reopened'] ?? false) !== true) {
            $reasons[] = 'wal_index_readers_reopened_receipt_missing';
        }
        if (($receipt['shm_synced'] ?? false) !== true) {
            $reasons[] = 'wal_index_shm_sync_missing';
        }
        foreach ($scopeNames as $scopeName) {
            if (!in_array($scopeName, $expectedScopes, true)) {
                $reasons[] = 'wal_index_unpublished_scope';
                break;
            }
        }
        foreach ($readmarks as $reader => $frame) {
            if ($frame !== $expectedFrame) {
                $reasons[] = 'wal_index_readmark_frame_mismatch';
                break;
            }
        }

        $checksumInput = json_encode([
            (int) $receipt['salt_1'],
            (int) $receipt['salt_2'],
            (int) $receipt['mx_frame'],
            (int) $receipt['backfill_frame'],
            $readmarks,
            $expectedWalDigest,
        ], JSON_THROW_ON_ERROR);
        $expectedChecksumDigest = hash('sha256', $checksumInput);
        if (!hash_equals($expectedChecksumDigest, (string) $receipt['checksum_digest'])) {
            $reasons[] = 'wal_index_checksum_digest_mismatch';
        }

        return [
            'name' => $name,
            'scope_names' => $scopeNames,
            'source_token_id' => (string) $receipt['source_token_id'],
            'source_epoch' => (int) $receipt['source_epoch'],
            'next_source_epoch' => (int) $receipt['next_source_epoch'],
            'checkpoint_frame' => (int) $receipt['checkpoint_frame'],
            'checkpoint_cookie' => (int) $receipt['checkpoint_cookie'],
            'schema_cookie' => (int) $receipt['schema_cookie'],
            'wal_digest' => (string) $receipt['wal_digest'],
            'salt_1' => (int) $receipt['salt_1'],
            'salt_2' => (int) $receipt['salt_2'],
            'mx_frame' => (int) $receipt['mx_frame'],
            'backfill_frame' => (int) $receipt['backfill_frame'],
            'readmark_frames' => $readmarks,
            'readmark_reader_names' => array_keys($readmarks),
            'readers_reopened' => ($receipt['readers_reopened'] ?? false) === true,
            'shm_synced' => ($receipt['shm_synced'] ?? false) === true,
            'expected_checksum_digest' => $expectedChecksumDigest,
            'checksum_digest' => (string) $receipt['checksum_digest'],
            'reopenable' => $reasons === [],
            'blocked_reasons' => array_values(array_unique($reasons)),
            'receipt_reason' => $reasons === [] ? 'wal_index_reopen_receipt_matches_published_checkpoint' : $reasons[0],
            'receipt_digest' => hash('sha256', json_encode([$name, $scopeNames, $readmarks, $receipt], JSON_THROW_ON_ERROR)),
        ];
    }

    /**
     * @param mixed $values
     * @return list<string>
     */
    private static function stringList($values, string $label): array
    {
        if (!is_array($values) || $values === []) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal current-source next231 {$label} must be a non-empty list");
        }
        $list = [];
        foreach ($values as $value) {
            if (!is_string($value) || $value === '') {
                throw new \InvalidArgumentException("SQLite WAL hot-journal current-source next231 {$label} must contain non-empty strings");
            }
            $list[] = $value;
        }

        return array_values(array_unique($list));
    }

    /**
     * @param mixed $values
     * @return array<string,int>
     */
    private static function readmarkFrames($values, string $receiptName): array
    {
        if (!is_array($values) || $values === []) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal current-source next231 {$receiptName} requires readmark frames");
        }
        $frames = [];
        foreach ($values as $reader => $frame) {
            if (!is_string($reader) || $reader === '' || !is_int($frame) || $frame < 0) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal current-source next231 {$receiptName} readmarks must map reader names to non-negative frames");
            }
            $frames[$reader] = $frame;
        }
        ksort($frames);

        return $frames;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function coveredScopes(array $rows): array
    {
        $covered = [];
        foreach ($rows as $row) {
            foreach ($row['scope_names'] as $scopeName) {
                $covered[$scopeName] = true;
            }
        }

        return array_keys($covered);
    }

    /**
     * @param list<string> $values
     * @return list<string>
     */
    private static function duplicates(array $values): array
    {
        $seen = [];
        $duplicates = [];
        foreach ($values as $value) {
            if (isset($seen[$value])) {
                $duplicates[$value] = true;
                continue;
            }
            $seen[$value] = true;
        }

        return array_keys($duplicates);
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function allNextEpochsMatch(array $rows, int $expectedNextEpoch): bool
    {
        foreach ($rows as $row) {
            if (($row['next_source_epoch'] ?? null) !== $expectedNextEpoch) {
                return false;
            }
        }

        return $rows !== [];
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function allCheckpointFramesMatch(array $rows, int $expectedFrame): bool
    {
        foreach ($rows as $row) {
            if (($row['checkpoint_frame'] ?? null) !== $expectedFrame || ($row['mx_frame'] ?? null) !== $expectedFrame) {
                return false;
            }
        }

        return $rows !== [];
    }

    private static function isDigest(string $value): bool
    {
        return preg_match('/^[a-f0-9]{64}$/', $value) === 1;
    }
}
