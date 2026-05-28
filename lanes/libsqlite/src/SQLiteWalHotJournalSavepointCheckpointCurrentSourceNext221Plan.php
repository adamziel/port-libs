<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext221Plan
{
    /**
     * @param array<string,mixed> $next217Plan
     * @param list<array<string,mixed>> $sidecarReceipts
     * @return array<string,mixed>
     */
    public static function plan(array $next217Plan, array $sidecarReceipts): array
    {
        self::assertNext217Plan($next217Plan);
        if ($sidecarReceipts === []) {
            throw new \InvalidArgumentException('SQLite WAL current-source next221 requires sidecar retirement receipts');
        }

        $token = $next217Plan['current_source_token'];
        $sourceId = (string) $token['id'];
        $sourceEpoch = (int) $token['epoch'];
        $nextEpoch = (int) ($next217Plan['next_source_epoch'] ?? ($sourceEpoch + 1));
        $checkpointFrame = (int) $next217Plan['checkpoint_frame'];
        $checkpointCookie = (int) $next217Plan['checkpoint_cookie'];

        $rows = [];
        foreach ($sidecarReceipts as $receipt) {
            $rows[] = self::sidecarRow($receipt, $sourceId, $nextEpoch, $checkpointFrame, $checkpointCookie);
        }

        $blockedRows = array_values(array_filter($rows, static fn (array $row): bool => !$row['admitted']));
        $byKind = [];
        foreach ($rows as $row) {
            $byKind[$row['kind']] = ($byKind[$row['kind']] ?? 0) + 1;
        }

        $requiredKinds = ['hot-journal', 'wal', 'shm'];
        $missingKinds = array_values(array_filter($requiredKinds, static fn (string $kind): bool => !isset($byKind[$kind])));
        $guards = [
            'next217_checkpoint_admitted' => ($next217Plan['status'] ?? null) === 'wal-hot-journal-savepoint-checkpoint-current-source-next217'
                && ($next217Plan['checkpoint_admitted'] ?? false) === true,
            'next217_has_no_blocked_readers' => ($next217Plan['blocked_reader_names'] ?? []) === [],
            'required_sidecars_observed' => $missingKinds === [],
            'sidecar_receipts_match_next_source' => $blockedRows === [],
            'old_hot_journal_retired' => self::kindHasAction($rows, 'hot-journal', 'delete'),
            'next_wal_generation_durable' => self::kindHasAction($rows, 'wal', 'restart-header'),
            'shm_read_marks_reset' => self::kindHasAction($rows, 'shm', 'reset-read-marks'),
            'directory_sync_after_retirement' => self::allRowsPass($rows, 'directory_sync_durable'),
        ];
        $blocked = array_keys(array_filter($guards, static fn (bool $passed): bool => !$passed));

        return [
            'status' => $blocked === []
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next221'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next221',
            'reason' => $blocked === []
                ? 'sidecar_retirement_receipts_publish_next_current_source'
                : 'sidecar_retirement_receipts_block_next_current_source',
            'database_path' => (string) $next217Plan['database_path'],
            'wal_path' => (string) $next217Plan['wal_path'],
            'journal_path' => (string) $next217Plan['journal_path'],
            'shm_path' => (string) ($next217Plan['shm_path'] ?? ((string) $next217Plan['wal_path'] . '-shm')),
            'current_source_token' => $token,
            'next_source_token' => [
                'id' => $sourceId . ':next221',
                'epoch' => $nextEpoch,
                'checkpoint_frame' => $checkpointFrame,
                'checkpoint_cookie' => $checkpointCookie,
            ],
            'checkpoint_frame' => $checkpointFrame,
            'checkpoint_cookie' => $checkpointCookie,
            'sidecar_rows' => $rows,
            'sidecar_kinds' => array_keys($byKind),
            'missing_sidecar_kinds' => $missingKinds,
            'admitted_sidecar_names' => array_values(array_column(array_filter($rows, static fn (array $row): bool => $row['admitted']), 'name')),
            'blocked_sidecar_names' => array_values(array_column($blockedRows, 'name')),
            'guard_names' => array_keys($guards),
            'guard_matches' => array_values($guards),
            'blocked_guard_names' => $blocked,
            'checkpoint_admitted' => $blocked === [],
            'retirement_digest' => hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR)),
            'operation_names' => array_values(array_unique(array_merge(
                is_array($next217Plan['operation_names'] ?? null) ? $next217Plan['operation_names'] : [],
                [
                    'verify_hot_journal_retirement_receipt_next221',
                    'verify_restarted_wal_generation_receipt_next221',
                    'verify_shm_read_mark_reset_receipt_next221',
                    'publish_next_current_source_after_sidecar_retirement_next221',
                ]
            ))),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($next217Plan['dependencies'] ?? null) ? $next217Plan['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next221',
                    'sqlite-wal-sidecar-retirement-current-source-barrier',
                    'wordpress-import-hot-journal-checkpoint-sidecar-retirement',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses next217 durable reader receipts, WAL checkpoint metadata, VFS sidecar paths, and directory sync receipts',
            'non_overlap' => 'next221 verifies post-next217 sidecar retirement receipts before publishing the next current-source token; it does not repeat next217 reader receipt admission, next211 page digest admission, next208 reader slot validation, WAL byte truncation, VFS savepoint rollback apply, rollback-journal commit/apply, or checkpoint transaction planning',
        ];
    }

    /**
     * @param array<string,mixed> $plan
     */
    private static function assertNext217Plan(array $plan): void
    {
        foreach (['status', 'database_path', 'wal_path', 'journal_path', 'current_source_token', 'checkpoint_frame', 'checkpoint_cookie', 'blocked_reader_names'] as $key) {
            if (!array_key_exists($key, $plan)) {
                throw new \InvalidArgumentException("SQLite WAL current-source next221 missing next217 {$key}");
            }
        }
        if (!is_array($plan['current_source_token']) || (string) ($plan['current_source_token']['id'] ?? '') === '' || (int) ($plan['current_source_token']['epoch'] ?? 0) <= 0) {
            throw new \InvalidArgumentException('SQLite WAL current-source next221 token is invalid');
        }
        foreach (['checkpoint_frame', 'checkpoint_cookie'] as $key) {
            if ((int) $plan[$key] <= 0) {
                throw new \InvalidArgumentException("SQLite WAL current-source next221 {$key} must be positive");
            }
        }
    }

    /**
     * @param array<string,mixed> $receipt
     * @return array<string,mixed>
     */
    private static function sidecarRow(array $receipt, string $sourceId, int $nextEpoch, int $checkpointFrame, int $checkpointCookie): array
    {
        foreach (['name', 'kind', 'path', 'action', 'source_id', 'next_epoch', 'checkpoint_frame', 'checkpoint_cookie', 'receipt_sha256'] as $key) {
            if (!array_key_exists($key, $receipt)) {
                throw new \InvalidArgumentException("SQLite WAL current-source next221 missing sidecar {$key}");
            }
        }

        $name = (string) $receipt['name'];
        $kind = (string) $receipt['kind'];
        $action = (string) $receipt['action'];
        $path = (string) $receipt['path'];
        if ($name === '' || $path === '' || !in_array($kind, ['hot-journal', 'wal', 'shm'], true)) {
            throw new \InvalidArgumentException('SQLite WAL current-source next221 sidecar receipt identity is invalid');
        }
        if (!in_array($action, ['delete', 'restart-header', 'reset-read-marks', 'preserve'], true)) {
            throw new \InvalidArgumentException('SQLite WAL current-source next221 sidecar receipt action is invalid');
        }
        $sha = (string) $receipt['receipt_sha256'];
        if (!preg_match('/^[a-f0-9]{64}$/', $sha)) {
            throw new \InvalidArgumentException('SQLite WAL current-source next221 sidecar receipt digest must be sha256');
        }

        $checks = [
            'source_token' => (string) $receipt['source_id'] === $sourceId,
            'next_epoch' => (int) $receipt['next_epoch'] === $nextEpoch,
            'checkpoint_frame' => (int) $receipt['checkpoint_frame'] === $checkpointFrame,
            'checkpoint_cookie' => (int) $receipt['checkpoint_cookie'] === $checkpointCookie,
            'receipt_synced' => ($receipt['synced'] ?? false) === true,
            'directory_sync_durable' => ($receipt['directory_synced'] ?? false) === true,
            'savepoint_closed' => ($receipt['savepoint_closed'] ?? false) === true,
            'lock_receipt' => ($receipt['exclusive_lock_receipt'] ?? false) === true,
            'action_matches_kind' => self::actionMatchesKind($kind, $action),
        ];
        $failed = array_keys(array_filter($checks, static fn (bool $passed): bool => !$passed));

        return [
            'name' => $name,
            'kind' => $kind,
            'path' => $path,
            'action' => $action,
            'source_id' => (string) $receipt['source_id'],
            'next_epoch' => (int) $receipt['next_epoch'],
            'checkpoint_frame' => (int) $receipt['checkpoint_frame'],
            'checkpoint_cookie' => (int) $receipt['checkpoint_cookie'],
            'receipt_sha256' => $sha,
            'directory_sync_durable' => $checks['directory_sync_durable'],
            'admitted' => $failed === [],
            'failed_checks' => $failed,
            'reason' => $failed === []
                ? 'sidecar_retirement_receipt_matches_next_source'
                : 'sidecar_retirement_receipt_requires_replay',
            'transition' => $name . '>' . ($failed === [] ? 'retire-sidecar' : 'replay-sidecar') . ':next221',
        ];
    }

    private static function actionMatchesKind(string $kind, string $action): bool
    {
        return ($kind === 'hot-journal' && $action === 'delete')
            || ($kind === 'wal' && $action === 'restart-header')
            || ($kind === 'shm' && $action === 'reset-read-marks');
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function kindHasAction(array $rows, string $kind, string $action): bool
    {
        foreach ($rows as $row) {
            if ($row['kind'] === $kind && $row['action'] === $action && $row['admitted'] === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    private static function allRowsPass(array $rows, string $key): bool
    {
        foreach ($rows as $row) {
            if (($row[$key] ?? false) !== true) {
                return false;
            }
        }

        return true;
    }
}
