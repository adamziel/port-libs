<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext225Plan
{
    /**
     * @param array<string,mixed> $publishPlan
     * @param list<array<string,mixed>> $headerReceipts
     * @return array<string,mixed>
     */
    public static function plan(array $publishPlan, array $headerReceipts): array
    {
        self::assertPublishPlan($publishPlan);
        if ($headerReceipts === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next225 requires database header receipts');
        }

        $token = $publishPlan['current_source_token'];
        $sourceId = (string) $token['id'];
        $epoch = (int) $token['epoch'];
        $checkpointFrame = (int) $publishPlan['checkpoint_frame'];
        $checkpointCookie = (int) $publishPlan['checkpoint_cookie'];
        $schemaCookie = (int) $publishPlan['schema_cookie'];
        $nextSourceEpoch = (int) $publishPlan['next_source_epoch'];
        $scopeDigest = (string) $publishPlan['savepoint_scope_digest'];

        $rows = [];
        foreach ($headerReceipts as $receipt) {
            $rows[] = self::receiptRow(
                $receipt,
                $sourceId,
                $epoch,
                $checkpointFrame,
                $checkpointCookie,
                $schemaCookie,
                $nextSourceEpoch,
                $scopeDigest
            );
        }

        $publishedRows = array_values(array_filter($rows, static fn (array $row): bool => $row['published']));
        $blockedRows = array_values(array_filter($rows, static fn (array $row): bool => !$row['published']));
        $blockedReasons = [];
        foreach ($blockedRows as $row) {
            foreach ($row['blocked_reasons'] as $reason) {
                $blockedReasons[] = $reason;
            }
        }
        $requiredRegions = ['database-header', 'wal-index-header', 'change-counter'];
        $publishedRegions = array_values(array_unique(array_column($publishedRows, 'header_region')));
        sort($publishedRegions);
        sort($requiredRegions);

        $guards = [
            'next219_checkpoint_source_published' => $publishPlan['status'] === 'wal-hot-journal-savepoint-checkpoint-current-source-next219'
                && ($publishPlan['checkpoint_next_source_published'] ?? false) === true,
            'database_header_receipts_current' => $blockedRows === [],
            'required_header_regions_written' => $publishedRegions === $requiredRegions,
            'next_source_epoch_advanced' => $nextSourceEpoch > $epoch,
        ];
        $blockedGuards = array_keys(array_filter($guards, static fn (bool $passed): bool => !$passed));

        return [
            'status' => $blockedGuards === []
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next225'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next225',
            'reason' => $blockedGuards === []
                ? 'database_header_receipts_publish_checkpoint_current_source'
                : 'database_header_receipts_block_checkpoint_current_source',
            'database_path' => (string) $publishPlan['database_path'],
            'wal_path' => (string) $publishPlan['wal_path'],
            'journal_path' => (string) $publishPlan['journal_path'],
            'current_source_token' => $token,
            'checkpoint_frame' => $checkpointFrame,
            'checkpoint_cookie' => $checkpointCookie,
            'schema_cookie' => $schemaCookie,
            'next_source_epoch' => $nextSourceEpoch,
            'savepoint_scope_digest' => $scopeDigest,
            'receipt_rows' => $rows,
            'published_receipt_names' => array_values(array_column($publishedRows, 'name')),
            'blocked_receipt_names' => array_values(array_column($blockedRows, 'name')),
            'blocked_receipt_reasons' => array_values(array_unique($blockedReasons)),
            'published_header_regions' => $publishedRegions,
            'required_header_regions' => $requiredRegions,
            'guard_names' => array_keys($guards),
            'guard_matches' => array_values($guards),
            'blocked_guard_names' => $blockedGuards,
            'checkpoint_current_source_header_published' => $blockedGuards === [],
            'header_receipt_digest' => hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR)),
            'operation_names' => array_values(array_unique(array_merge(
                is_array($publishPlan['operation_names'] ?? null) ? $publishPlan['operation_names'] : [],
                [
                    'verify_checkpoint_database_header_receipts_next225',
                    'publish_database_header_current_source_after_hot_journal_next225',
                    'fence_stale_hot_journal_header_after_savepoint_checkpoint_next225',
                ]
            ))),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($publishPlan['dependencies'] ?? null) ? $publishPlan['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next225',
                    'sqlite-database-header-current-source-after-wal-checkpoint',
                    'wordpress-import-checkpoint-header-receipts',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses next219 savepoint-scope publication, checkpoint cookies, WAL-index header metadata, and native database header write receipts',
            'non_overlap' => 'next225 admits database-header current-source publication after savepoint finalization; it does not repeat next219 savepoint-scope finalization, next212 passive reader pins, next172 sync receipts, VFS writer/sync apply, rollback-journal commit/apply, or WAL byte truncation',
        ];
    }

    /**
     * @param array<string,mixed> $publishPlan
     */
    private static function assertPublishPlan(array $publishPlan): void
    {
        foreach (['status', 'database_path', 'wal_path', 'journal_path', 'current_source_token', 'checkpoint_frame', 'checkpoint_cookie', 'schema_cookie', 'next_source_epoch', 'savepoint_scope_digest'] as $key) {
            if (!array_key_exists($key, $publishPlan)) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next225 missing publish {$key}");
            }
        }
        if (!is_array($publishPlan['current_source_token']) || (string) ($publishPlan['current_source_token']['id'] ?? '') === '' || (int) ($publishPlan['current_source_token']['epoch'] ?? 0) <= 0) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next225 token is invalid');
        }
        foreach (['checkpoint_frame', 'checkpoint_cookie', 'schema_cookie', 'next_source_epoch'] as $key) {
            if (!is_int($publishPlan[$key]) || $publishPlan[$key] <= 0) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next225 {$key} must be a positive integer");
            }
        }
        if (!is_string($publishPlan['savepoint_scope_digest']) || !preg_match('/^[a-f0-9]{64}$/', $publishPlan['savepoint_scope_digest'])) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next225 savepoint scope digest is invalid');
        }
    }

    /**
     * @param array<string,mixed> $receipt
     * @return array<string,mixed>
     */
    private static function receiptRow(
        array $receipt,
        string $sourceId,
        int $epoch,
        int $checkpointFrame,
        int $checkpointCookie,
        int $schemaCookie,
        int $nextSourceEpoch,
        string $scopeDigest
    ): array {
        foreach (['name', 'header_region', 'source_id', 'source_epoch', 'checkpoint_frame', 'checkpoint_cookie', 'schema_cookie', 'next_source_epoch', 'savepoint_scope_digest', 'header_digest', 'write_synced'] as $key) {
            if (!array_key_exists($key, $receipt)) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next225 missing receipt {$key}");
            }
        }
        $name = (string) $receipt['name'];
        $region = (string) $receipt['header_region'];
        if ($name === '' || $region === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next225 receipt name and region are required');
        }
        foreach (['source_epoch', 'checkpoint_frame', 'checkpoint_cookie', 'schema_cookie', 'next_source_epoch'] as $key) {
            if (!is_int($receipt[$key]) || $receipt[$key] < 0) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next225 {$name} {$key} must be a non-negative integer");
            }
        }
        if (!is_string($receipt['header_digest']) || !preg_match('/^[a-f0-9]{64}$/', $receipt['header_digest'])) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next225 {$name} header digest is invalid");
        }
        if (!is_string($receipt['savepoint_scope_digest']) || !preg_match('/^[a-f0-9]{64}$/', $receipt['savepoint_scope_digest'])) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal savepoint checkpoint current-source next225 {$name} scope digest is invalid");
        }

        $reasons = [];
        if ((string) $receipt['source_id'] !== $sourceId) {
            $reasons[] = 'header_source_id_mismatch';
        }
        if ((int) $receipt['source_epoch'] !== $epoch) {
            $reasons[] = 'header_source_epoch_mismatch';
        }
        if ((int) $receipt['checkpoint_frame'] !== $checkpointFrame) {
            $reasons[] = 'header_checkpoint_frame_mismatch';
        }
        if ((int) $receipt['checkpoint_cookie'] !== $checkpointCookie) {
            $reasons[] = 'header_checkpoint_cookie_mismatch';
        }
        if ((int) $receipt['schema_cookie'] !== $schemaCookie) {
            $reasons[] = 'header_schema_cookie_mismatch';
        }
        if ((int) $receipt['next_source_epoch'] !== $nextSourceEpoch) {
            $reasons[] = 'header_next_source_epoch_mismatch';
        }
        if ((string) $receipt['savepoint_scope_digest'] !== $scopeDigest) {
            $reasons[] = 'header_savepoint_scope_digest_mismatch';
        }
        if (($receipt['write_synced'] ?? false) !== true) {
            $reasons[] = 'header_write_not_synced';
        }
        if (($receipt['hot_journal_present'] ?? false) === true) {
            $reasons[] = 'header_hot_journal_still_present';
        }
        if (($receipt['stale_header_bytes'] ?? false) === true) {
            $reasons[] = 'header_stale_bytes_observed';
        }

        $published = $reasons === [];

        return [
            'name' => $name,
            'header_region' => $region,
            'source_id' => (string) $receipt['source_id'],
            'source_epoch' => (int) $receipt['source_epoch'],
            'checkpoint_frame' => (int) $receipt['checkpoint_frame'],
            'checkpoint_cookie' => (int) $receipt['checkpoint_cookie'],
            'schema_cookie' => (int) $receipt['schema_cookie'],
            'next_source_epoch' => (int) $receipt['next_source_epoch'],
            'savepoint_scope_digest' => (string) $receipt['savepoint_scope_digest'],
            'header_digest' => (string) $receipt['header_digest'],
            'write_synced' => (bool) $receipt['write_synced'],
            'hot_journal_present' => ($receipt['hot_journal_present'] ?? false) === true,
            'stale_header_bytes' => ($receipt['stale_header_bytes'] ?? false) === true,
            'published' => $published,
            'blocked_reasons' => $reasons,
            'receipt_reason' => $published ? 'header_receipt_published_current_source' : $reasons[0],
            'receipt_transition' => $name . '>' . ($published ? 'publish-header-current-source' : 'hold-header-current-source') . ':next225',
        ];
    }
}
