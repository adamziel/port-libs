<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext234Plan
{
    /**
     * @param array<string,mixed> $reopenPlan
     * @param list<array<string,mixed>> $syncReceipts
     * @return array<string,mixed>
     */
    public static function verifyDurableHandoff(array $reopenPlan, array $syncReceipts): array
    {
        self::assertReopenPlan($reopenPlan);
        if ($syncReceipts === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal current-source next234 requires durable handoff sync receipts');
        }

        $token = $reopenPlan['current_source_token'];
        $expectedTokenId = (string) $token['id'];
        $expectedSourceEpoch = (int) $reopenPlan['source_epoch'];
        $expectedNextEpoch = (int) $reopenPlan['next_source_epoch'];
        $expectedFrame = (int) $reopenPlan['checkpoint_frame'];
        $expectedCheckpointCookie = (int) $reopenPlan['checkpoint_cookie'];
        $expectedSchemaCookie = (int) $reopenPlan['schema_cookie'];
        $expectedWalDigest = (string) $reopenPlan['expected_wal_digest'];
        $expectedScopes = self::stringList($reopenPlan['publishable_scope_names'], 'publishable scope names');

        $rows = [];
        foreach ($syncReceipts as $receipt) {
            $rows[] = self::receiptRow(
                $receipt,
                $expectedScopes,
                $expectedTokenId,
                $expectedSourceEpoch,
                $expectedNextEpoch,
                $expectedFrame,
                $expectedCheckpointCookie,
                $expectedSchemaCookie,
                $expectedWalDigest
            );
        }

        $receiptNames = array_values(array_column($rows, 'name'));
        $duplicateNames = self::duplicates($receiptNames);
        $coveredScopes = self::coveredScopes($rows);
        $missingScopes = array_values(array_diff($expectedScopes, $coveredScopes));
        $extraScopes = array_values(array_diff($coveredScopes, $expectedScopes));
        $blockedRows = array_values(array_filter($rows, static fn (array $row): bool => !$row['durable']));
        $blockedReasons = [];
        foreach ($blockedRows as $row) {
            foreach ($row['blocked_reasons'] as $reason) {
                $blockedReasons[] = $reason;
            }
        }
        if ($duplicateNames !== []) {
            $blockedReasons[] = 'duplicate_durable_handoff_receipt';
        }
        if ($missingScopes !== []) {
            $blockedReasons[] = 'durable_handoff_scope_missing';
        }
        if ($extraScopes !== []) {
            $blockedReasons[] = 'durable_handoff_unpublished_scope';
        }
        $blockedReasons = array_values(array_unique($blockedReasons));

        $guards = [
            'next231_wal_index_reopen_admitted' => ($reopenPlan['status'] ?? null) === 'wal-hot-journal-savepoint-checkpoint-current-source-next231'
                && ($reopenPlan['can_reopen_current_source'] ?? false) === true,
            'no_duplicate_durable_handoff_receipts' => $duplicateNames === [],
            'all_publishable_scopes_have_durable_handoff_receipts' => $missingScopes === [],
            'no_unpublished_scope_durable_handoff_receipts' => $extraScopes === [],
            'all_receipts_match_reopened_wal_index_source' => $blockedRows === [],
            'sync_order_respects_sqlite_commit_boundary' => self::allOrdersRespectCommitBoundary($rows),
        ];
        $blockedGuards = array_keys(array_filter($guards, static fn (bool $matched): bool => !$matched));
        $durable = $blockedGuards === [];

        return [
            'status' => $durable
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next234'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next234',
            'reason' => $durable
                ? 'durable_handoff_receipts_match_reopened_wal_checkpoint_current_source'
                : 'durable_handoff_receipts_hold_reopened_wal_checkpoint_current_source',
            'database_path' => (string) $reopenPlan['database_path'],
            'wal_path' => (string) $reopenPlan['wal_path'],
            'journal_path' => (string) $reopenPlan['journal_path'],
            'current_source_token' => $token,
            'source_epoch' => $expectedSourceEpoch,
            'next_source_epoch' => $expectedNextEpoch,
            'checkpoint_frame' => $expectedFrame,
            'checkpoint_cookie' => $expectedCheckpointCookie,
            'schema_cookie' => $expectedSchemaCookie,
            'expected_wal_digest' => $expectedWalDigest,
            'publishable_scope_names' => $expectedScopes,
            'covered_scope_names' => $coveredScopes,
            'missing_scope_names' => $missingScopes,
            'extra_scope_names' => $extraScopes,
            'duplicate_receipt_names' => $duplicateNames,
            'receipt_names' => $receiptNames,
            'durable_receipt_names' => array_values(array_column(array_filter($rows, static fn (array $row): bool => $row['durable']), 'name')),
            'blocked_receipt_names' => array_values(array_column($blockedRows, 'name')),
            'blocked_reasons' => $blockedReasons,
            'receipt_rows' => $rows,
            'guard_names' => array_keys($guards),
            'guard_matches' => array_values($guards),
            'blocked_guard_names' => $blockedGuards,
            'can_serve_durable_current_source' => $durable,
            'durable_handoff_digest' => hash('sha256', json_encode([$token, $expectedWalDigest, $rows, $guards], JSON_THROW_ON_ERROR)),
            'operation_names' => array_values(array_unique(array_merge(
                is_array($reopenPlan['operation_names'] ?? null) ? $reopenPlan['operation_names'] : [],
                [
                    'verify_durable_handoff_sync_receipts_current_source_next234',
                    'fence_database_wal_shm_journal_directory_receipts_next234',
                    $durable ? 'serve_durable_reopened_checkpoint_source_next234' : 'hold_reopened_checkpoint_source_until_durable_next234',
                ]
            ))),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($reopenPlan['dependencies'] ?? null) ? $reopenPlan['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next234',
                    'sqlite-wal-durable-handoff-sync-receipts',
                    'wordpress-import-durable-wal-current-source-handoff',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses next231 WAL-index reopen receipts plus lane-local VFS sync/file-handle receipt metadata',
            'non_overlap' => 'next234 verifies durable database/WAL/SHM/journal/directory handoff receipts after next231 WAL-index reopen; it does not repeat next231 readmark reopen checks, next227 publish sealing, WAL byte truncation, VFS savepoint rollback, rollback-journal commit/apply, VFS sync planning/apply, or generic locked writer behavior',
        ];
    }

    /**
     * @param array<string,mixed> $plan
     */
    private static function assertReopenPlan(array $plan): void
    {
        foreach (['status', 'database_path', 'wal_path', 'journal_path', 'current_source_token', 'source_epoch', 'next_source_epoch', 'checkpoint_frame', 'checkpoint_cookie', 'schema_cookie', 'expected_wal_digest', 'publishable_scope_names', 'can_reopen_current_source'] as $key) {
            if (!array_key_exists($key, $plan)) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal current-source next234 missing reopen {$key}");
            }
        }
        if (($plan['status'] ?? null) !== 'wal-hot-journal-savepoint-checkpoint-current-source-next231' || ($plan['can_reopen_current_source'] ?? false) !== true) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal current-source next234 requires an admitted next231 WAL-index reopen plan');
        }
        if (!is_array($plan['current_source_token']) || (string) ($plan['current_source_token']['id'] ?? '') === '' || (int) ($plan['current_source_token']['epoch'] ?? 0) <= 0) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal current-source next234 token is invalid');
        }
        foreach (['source_epoch', 'next_source_epoch', 'checkpoint_frame', 'checkpoint_cookie', 'schema_cookie'] as $key) {
            if (!is_int($plan[$key]) || $plan[$key] <= 0) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal current-source next234 {$key} must be positive");
            }
        }
        if ((int) $plan['next_source_epoch'] <= (int) $plan['source_epoch']) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal current-source next234 next source epoch must advance');
        }
        if (!is_string($plan['expected_wal_digest']) || !self::isDigest($plan['expected_wal_digest'])) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal current-source next234 expected WAL digest must be a sha256 string');
        }
        foreach (['database_path', 'wal_path', 'journal_path'] as $key) {
            if (!is_string($plan[$key]) || $plan[$key] === '') {
                throw new \InvalidArgumentException("SQLite WAL hot-journal current-source next234 {$key} is required");
            }
        }
        self::stringList($plan['publishable_scope_names'], 'publishable scope names');
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
        foreach (['name', 'scope_names', 'source_token_id', 'source_epoch', 'next_source_epoch', 'checkpoint_frame', 'checkpoint_cookie', 'schema_cookie', 'wal_digest', 'database_digest', 'shm_digest', 'sync_order', 'database_synced', 'wal_synced', 'shm_synced', 'journal_unlinked', 'directory_synced', 'reader_cache_clean', 'writer_generation'] as $key) {
            if (!array_key_exists($key, $receipt)) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal current-source next234 missing durable handoff receipt {$key}");
            }
        }

        $name = (string) $receipt['name'];
        if ($name === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal current-source next234 durable handoff receipt name is required');
        }
        $scopeNames = self::stringList($receipt['scope_names'], "{$name} scope names");
        $syncOrder = self::stringList($receipt['sync_order'], "{$name} sync order");
        foreach (['source_epoch', 'next_source_epoch', 'checkpoint_frame', 'checkpoint_cookie', 'schema_cookie', 'writer_generation'] as $key) {
            if (!is_int($receipt[$key]) || $receipt[$key] < 0) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal current-source next234 {$name} {$key} must be a non-negative integer");
            }
        }
        foreach (['wal_digest', 'database_digest', 'shm_digest'] as $key) {
            if (!is_string($receipt[$key]) || !self::isDigest($receipt[$key])) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal current-source next234 {$name} {$key} must be a sha256 string");
            }
        }

        $reasons = [];
        if ((string) $receipt['source_token_id'] !== $expectedTokenId) {
            $reasons[] = 'durable_handoff_source_token_mismatch';
        }
        if ((int) $receipt['source_epoch'] !== $expectedSourceEpoch) {
            $reasons[] = 'durable_handoff_source_epoch_mismatch';
        }
        if ((int) $receipt['next_source_epoch'] !== $expectedNextEpoch) {
            $reasons[] = 'durable_handoff_next_source_epoch_mismatch';
        }
        if ((int) $receipt['checkpoint_frame'] !== $expectedFrame) {
            $reasons[] = 'durable_handoff_checkpoint_frame_mismatch';
        }
        if ((int) $receipt['checkpoint_cookie'] !== $expectedCheckpointCookie) {
            $reasons[] = 'durable_handoff_checkpoint_cookie_mismatch';
        }
        if ((int) $receipt['schema_cookie'] !== $expectedSchemaCookie) {
            $reasons[] = 'durable_handoff_schema_cookie_mismatch';
        }
        if ((string) $receipt['wal_digest'] !== $expectedWalDigest) {
            $reasons[] = 'durable_handoff_wal_digest_mismatch';
        }
        if ((int) $receipt['writer_generation'] !== $expectedNextEpoch) {
            $reasons[] = 'durable_handoff_writer_generation_mismatch';
        }
        foreach (['database_synced', 'wal_synced', 'shm_synced', 'journal_unlinked', 'directory_synced', 'reader_cache_clean'] as $key) {
            if (($receipt[$key] ?? null) !== true) {
                $reasons[] = $key . '_missing';
            }
        }
        foreach ($scopeNames as $scopeName) {
            if (!in_array($scopeName, $expectedScopes, true)) {
                $reasons[] = 'durable_handoff_unpublished_scope_row';
                break;
            }
        }
        if (!self::syncOrderRespectsCommitBoundary($syncOrder)) {
            $reasons[] = 'durable_handoff_sync_order_violation';
        }

        $durable = $reasons === [];

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
            'database_digest' => (string) $receipt['database_digest'],
            'shm_digest' => (string) $receipt['shm_digest'],
            'sync_order' => $syncOrder,
            'writer_generation' => (int) $receipt['writer_generation'],
            'database_synced' => $receipt['database_synced'] === true,
            'wal_synced' => $receipt['wal_synced'] === true,
            'shm_synced' => $receipt['shm_synced'] === true,
            'journal_unlinked' => $receipt['journal_unlinked'] === true,
            'directory_synced' => $receipt['directory_synced'] === true,
            'reader_cache_clean' => $receipt['reader_cache_clean'] === true,
            'blocked_reasons' => $reasons,
            'durable' => $durable,
            'receipt_reason' => $durable
                ? 'durable_handoff_receipt_matches_reopened_checkpoint'
                : 'durable_handoff_receipt_blocks_reopened_checkpoint',
            'receipt_digest' => hash('sha256', json_encode([$name, $scopeNames, $receipt, $reasons], JSON_THROW_ON_ERROR)),
        ];
    }

    /**
     * @param mixed $value
     * @return list<string>
     */
    private static function stringList($value, string $label): array
    {
        if (!is_array($value) || $value === []) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal current-source next234 {$label} must be a non-empty list");
        }

        $result = [];
        foreach ($value as $item) {
            if (!is_string($item) || $item === '') {
                throw new \InvalidArgumentException("SQLite WAL hot-journal current-source next234 {$label} entries must be non-empty strings");
            }
            $result[] = $item;
        }

        return array_values($result);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function coveredScopes(array $rows): array
    {
        $scopes = [];
        foreach ($rows as $row) {
            foreach ($row['scope_names'] as $scope) {
                $scopes[] = $scope;
            }
        }

        return array_values(array_unique($scopes));
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
                $duplicates[] = $value;
            }
            $seen[$value] = true;
        }

        return array_values(array_unique($duplicates));
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function allOrdersRespectCommitBoundary(array $rows): bool
    {
        foreach ($rows as $row) {
            if (!self::syncOrderRespectsCommitBoundary($row['sync_order'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string> $syncOrder
     */
    private static function syncOrderRespectsCommitBoundary(array $syncOrder): bool
    {
        $positions = [];
        foreach ($syncOrder as $index => $name) {
            $positions[$name] = $index;
        }
        foreach (['database_sync', 'wal_sync', 'shm_sync', 'journal_unlink', 'directory_sync'] as $name) {
            if (!array_key_exists($name, $positions)) {
                return false;
            }
        }

        return $positions['database_sync'] < $positions['wal_sync']
            && $positions['wal_sync'] < $positions['shm_sync']
            && $positions['shm_sync'] < $positions['journal_unlink']
            && $positions['journal_unlink'] < $positions['directory_sync'];
    }

    private static function isDigest(string $value): bool
    {
        return (bool) preg_match('/^[a-f0-9]{64}$/', $value);
    }
}
