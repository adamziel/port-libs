<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext251Plan
{
    /**
     * @param array<string,mixed> $handoffPlan
     * @param list<array<string,mixed>> $resetReceipts
     * @return array<string,mixed>
     */
    public static function admitWalSidecarReset(array $handoffPlan, array $resetReceipts): array
    {
        if (($handoffPlan['status'] ?? null) !== 'wal-hot-journal-savepoint-checkpoint-current-source-next246'
            || ($handoffPlan['durable_handoff_admitted'] ?? null) !== true
        ) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next251 requires an admitted next246 durable handoff');
        }
        if ($resetReceipts === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next251 requires WAL reset receipts');
        }

        $databasePath = self::path($handoffPlan['database_path'] ?? null, 'database path');
        $walPath = self::path($handoffPlan['wal_path'] ?? null, 'wal path');
        $sourceToken = self::token($handoffPlan['source_token'] ?? null, 'source token');
        $commitGeneration = self::positiveInt($handoffPlan['commit_generation'] ?? null, 'commit generation');
        $checkpointFrame = self::nonNegativeInt($handoffPlan['checkpoint_frame'] ?? null, 'checkpoint frame');
        $commitFrames = self::positiveIntSet($handoffPlan['commit_frames'] ?? null, 'commit frames');
        $readerNames = self::tokenSet($handoffPlan['accepted_reader_names'] ?? null, 'accepted reader names');
        $databaseDigest = self::digest($handoffPlan['database_digest'] ?? null, 'database digest');
        $pageCacheDigest = self::digest($handoffPlan['page_cache_digest'] ?? null, 'page cache digest');

        $rows = [];
        foreach ($resetReceipts as $receipt) {
            $rows[] = self::resetRow($receipt, $walPath, $sourceToken, $commitGeneration, $checkpointFrame, $commitFrames, $readerNames);
        }

        $acceptedRows = array_values(array_filter($rows, static fn (array $row): bool => $row['accepted']));
        $blockedRows = array_values(array_filter($rows, static fn (array $row): bool => !$row['accepted']));
        $duplicateNames = self::duplicates(array_column($rows, 'name'));

        $operations = [];
        $readerReleaseNames = [];
        $headerSalt = [];
        $restartFrame = null;
        $truncateBytes = null;
        $syncSeen = false;
        foreach ($acceptedRows as $row) {
            $operations[] = $row['operation'];
            foreach ($row['released_reader_names'] as $readerName) {
                $readerReleaseNames[$readerName] = true;
            }
            if ($row['operation'] === 'rewrite_wal_header') {
                $headerSalt = $row['next_wal_salt'];
                $restartFrame = $row['restart_frame'];
            }
            if ($row['operation'] === 'truncate_wal') {
                $truncateBytes = $row['truncate_bytes'];
            }
            if ($row['operation'] === 'sync_wal') {
                $syncSeen = true;
            }
        }
        ksort($readerReleaseNames);
        $releasedReaders = array_values(array_keys($readerReleaseNames));
        $missingReaders = array_values(array_diff($readerNames, $releasedReaders));
        $orderSafe = self::operationOrderIsSafe($operations);

        $blockedReasons = [];
        foreach ($blockedRows as $row) {
            foreach ($row['blocked_reasons'] as $reason) {
                $blockedReasons[] = $reason;
            }
        }
        if ($duplicateNames !== []) {
            $blockedReasons[] = 'wal_reset_receipt_name_duplicate';
        }
        if ($missingReaders !== []) {
            $blockedReasons[] = 'wal_reset_reader_release_missing';
        }
        if ($headerSalt === []) {
            $blockedReasons[] = 'wal_reset_header_rewrite_missing';
        }
        if ($truncateBytes !== 0) {
            $blockedReasons[] = 'wal_reset_truncate_missing';
        }
        if (!$syncSeen) {
            $blockedReasons[] = 'wal_reset_sync_missing';
        }
        if (!$orderSafe) {
            $blockedReasons[] = 'wal_reset_operation_order_unsafe';
        }
        $blockedReasons = array_values(array_unique($blockedReasons));

        $guards = [
            ['name' => 'next246_durable_handoff_admitted', 'matched' => true, 'reason' => 'database image and hot-journal delete already reached durable storage'],
            ['name' => 'wal_reset_receipt_names_unique', 'matched' => $duplicateNames === [], 'reason' => 'WAL reset receipts must be attributable exactly once'],
            ['name' => 'all_checkpoint_readers_released', 'matched' => $missingReaders === [], 'reason' => 'WAL sidecar may reset only after all checkpoint readers release their read marks'],
            ['name' => 'wal_header_rewritten_with_new_salt', 'matched' => $headerSalt !== [], 'reason' => 'restart must publish a fresh WAL salt before old frames are reusable'],
            ['name' => 'wal_sidecar_truncated_to_empty', 'matched' => $truncateBytes === 0, 'reason' => 'hot-journal checkpoint reset retires stale committed frames from the WAL sidecar'],
            ['name' => 'wal_reset_synced_after_truncate', 'matched' => $syncSeen && $orderSafe, 'reason' => 'WAL reset bytes must be synced after header rewrite and truncate'],
            ['name' => 'all_wal_reset_receipts_accepted', 'matched' => $blockedRows === [], 'reason' => 'receipt token, generation, lock, salt, frame, and error metadata must match'],
        ];
        $blockedGuards = array_values(array_column(array_filter($guards, static fn (array $row): bool => !$row['matched']), 'name'));
        $admitted = $blockedGuards === [];

        return [
            'status' => $admitted
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next251'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next251',
            'reason' => $admitted
                ? 'wal_sidecar_reset_admitted_after_durable_checkpoint_handoff'
                : 'wal_sidecar_reset_held_after_durable_checkpoint_handoff',
            'base_status' => $handoffPlan['status'],
            'database_path' => $databasePath,
            'wal_path' => $walPath,
            'source_token' => $sourceToken,
            'commit_generation' => $commitGeneration,
            'checkpoint_frame' => $checkpointFrame,
            'commit_frames' => $commitFrames,
            'database_digest' => $databaseDigest,
            'page_cache_digest' => $pageCacheDigest,
            'accepted_reader_names' => $readerNames,
            'released_reader_names' => $releasedReaders,
            'missing_reader_releases' => $missingReaders,
            'reset_rows' => $rows,
            'reset_receipt_names' => array_column($rows, 'name'),
            'accepted_reset_receipt_names' => array_values(array_column($acceptedRows, 'name')),
            'blocked_reset_receipt_names' => array_values(array_column($blockedRows, 'name')),
            'duplicate_reset_receipt_names' => $duplicateNames,
            'operation_order' => $operations,
            'operation_order_safe' => $orderSafe,
            'next_wal_salt' => $headerSalt,
            'restart_frame' => $restartFrame,
            'truncate_bytes' => $truncateBytes,
            'wal_sync_seen' => $syncSeen,
            'blocked_reset_reasons' => $blockedReasons,
            'wal_reset_admitted' => $admitted,
            'wal_action' => $admitted ? 'publish_empty_restarted_wal_after_reader_release' : 'preserve_wal_sidecar_until_reset_fences_match',
            'reader_action' => $admitted ? 'allow_new_readers_on_restarted_wal_generation_' . $commitGeneration : 'keep_readers_on_checkpoint_generation_until_release',
            'journal_action' => $admitted ? 'hot_journal_retirement_remains_durable' : 'retain_hot_journal_recovery_metadata',
            'guard_rows' => $guards,
            'guard_names' => array_column($guards, 'name'),
            'guard_matches' => array_column($guards, 'matched'),
            'blocked_guard_names' => $blockedGuards,
            'reset_digest' => hash('sha256', json_encode([$sourceToken, $commitGeneration, $checkpointFrame, $databaseDigest, $pageCacheDigest, $rows, $guards], JSON_THROW_ON_ERROR)),
            'operation_names' => array_values(array_unique(array_merge(
                is_array($handoffPlan['operation_names'] ?? null) ? $handoffPlan['operation_names'] : [],
                [
                    'verify_wal_sidecar_reset_after_durable_handoff_next251',
                    $admitted ? 'admit_wal_sidecar_reset_next251' : 'hold_wal_sidecar_reset_next251',
                ]
            ))),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($handoffPlan['dependencies'] ?? null) ? $handoffPlan['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next251',
                    'sqlite-wal-reset-after-durable-checkpoint-handoff',
                    'wordpress-import-hot-journal-checkpoint-wal-reset',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses next246 durable handoff metadata with native PHP reader-release, WAL salt/header, truncate, sync, and exclusive-lock receipts',
            'non_overlap' => 'next251 admits WAL sidecar reset/truncate only after next246 durable checkpoint handoff and reader release; it does not repeat durable page writes, reader snapshot matching, checkpoint transaction planning, WAL byte truncation, VFS savepoint rollback, rollback-journal apply/commit, VFS sync planning/apply, file locking, SELECT, JSON, or B-tree surfaces',
        ];
    }

    /**
     * @param array<string,mixed> $receipt
     * @param list<int> $commitFrames
     * @param list<string> $readerNames
     * @return array<string,mixed>
     */
    private static function resetRow(array $receipt, string $walPath, string $sourceToken, int $commitGeneration, int $checkpointFrame, array $commitFrames, array $readerNames): array
    {
        $name = self::token($receipt['name'] ?? null, 'reset receipt name');
        $operation = self::operation($receipt['operation'] ?? null, "{$name} operation");
        $path = self::path($receipt['path'] ?? null, "{$name} path");
        $releasedReaders = self::optionalTokenSet($receipt['released_reader_names'] ?? null, "{$name} released reader names");
        $nextWalSalt = self::optionalSalt($receipt['next_wal_salt'] ?? null, "{$name} next WAL salt");
        $restartFrame = self::nonNegativeInt($receipt['restart_frame'] ?? 0, "{$name} restart frame");
        $truncateBytes = self::nonNegativeInt($receipt['truncate_bytes'] ?? 0, "{$name} truncate bytes");
        $retiredFrames = self::optionalPositiveIntSet($receipt['retired_commit_frames'] ?? null, "{$name} retired commit frames");
        $reasons = [];

        if ($path !== $walPath) {
            $reasons[] = 'wal_reset_path_mismatch';
        }
        if (self::token($receipt['source_token'] ?? null, "{$name} source token") !== $sourceToken) {
            $reasons[] = 'wal_reset_source_token_mismatch';
        }
        if (self::positiveInt($receipt['commit_generation'] ?? null, "{$name} commit generation") !== $commitGeneration) {
            $reasons[] = 'wal_reset_commit_generation_mismatch';
        }
        if (self::nonNegativeInt($receipt['checkpoint_frame'] ?? null, "{$name} checkpoint frame") !== $checkpointFrame) {
            $reasons[] = 'wal_reset_checkpoint_frame_mismatch';
        }
        if (($receipt['exclusive_lock_held'] ?? null) !== true) {
            $reasons[] = 'wal_reset_exclusive_lock_missing';
        }
        if (($receipt['io_error'] ?? null) !== null) {
            $reasons[] = 'wal_reset_io_error';
        }
        foreach ($releasedReaders as $reader) {
            if (!in_array($reader, $readerNames, true)) {
                $reasons[] = 'wal_reset_unknown_reader_release';
            }
        }
        foreach ($retiredFrames as $frame) {
            if (!in_array($frame, $commitFrames, true)) {
                $reasons[] = 'wal_reset_unknown_retired_frame';
            }
        }
        if ($operation === 'release_readmark' && $releasedReaders === []) {
            $reasons[] = 'wal_reset_reader_release_empty';
        }
        if ($operation === 'rewrite_wal_header' && ($nextWalSalt === [] || $restartFrame !== 0)) {
            $reasons[] = 'wal_reset_header_rewrite_invalid';
        }
        if ($operation === 'truncate_wal' && ($truncateBytes !== 0 || $retiredFrames === [])) {
            $reasons[] = 'wal_reset_truncate_invalid';
        }
        if ($operation === 'sync_wal' && (($receipt['synced'] ?? null) !== true)) {
            $reasons[] = 'wal_reset_sync_missing';
        }

        $reasons = array_values(array_unique($reasons));

        return [
            'name' => $name,
            'operation' => $operation,
            'path' => $path,
            'source_token' => $receipt['source_token'],
            'commit_generation' => $receipt['commit_generation'],
            'checkpoint_frame' => $receipt['checkpoint_frame'],
            'exclusive_lock_held' => $receipt['exclusive_lock_held'] ?? null,
            'released_reader_names' => $releasedReaders,
            'next_wal_salt' => $nextWalSalt,
            'restart_frame' => $restartFrame,
            'truncate_bytes' => $truncateBytes,
            'retired_commit_frames' => $retiredFrames,
            'synced' => ($receipt['synced'] ?? null) === true,
            'io_error' => $receipt['io_error'] ?? null,
            'accepted' => $reasons === [],
            'blocked_reasons' => $reasons,
            'receipt_reason' => $reasons === [] ? 'wal_reset_receipt_matches_durable_checkpoint_handoff' : 'wal_reset_receipt_blocks_durable_checkpoint_handoff',
        ];
    }

    /** @param list<string> $operations */
    private static function operationOrderIsSafe(array $operations): bool
    {
        $release = array_search('release_readmark', $operations, true);
        $header = array_search('rewrite_wal_header', $operations, true);
        $truncate = array_search('truncate_wal', $operations, true);
        $sync = array_search('sync_wal', $operations, true);

        return $release !== false
            && $header !== false
            && $truncate !== false
            && $sync !== false
            && $release < $header
            && $header < $truncate
            && $truncate < $sync;
    }

    private static function path(mixed $value, string $label): string
    {
        if (!is_string($value) || $value === '') {
            throw new \InvalidArgumentException("Invalid {$label}");
        }
        return $value;
    }

    private static function token(mixed $value, string $label): string
    {
        if (!is_string($value) || !preg_match('/^[a-z0-9][a-z0-9._:-]*$/i', $value)) {
            throw new \InvalidArgumentException("Invalid {$label}");
        }
        return $value;
    }

    /** @return list<string> */
    private static function tokenSet(mixed $value, string $label): array
    {
        $tokens = self::optionalTokenSet($value, $label);
        if ($tokens === []) {
            throw new \InvalidArgumentException("Invalid {$label}");
        }
        return $tokens;
    }

    /** @return list<string> */
    private static function optionalTokenSet(mixed $value, string $label): array
    {
        if ($value === null) {
            return [];
        }
        if (!is_array($value)) {
            throw new \InvalidArgumentException("Invalid {$label}");
        }
        $set = [];
        foreach ($value as $item) {
            $set[self::token($item, $label)] = true;
        }
        return array_values(array_keys($set));
    }

    private static function operation(mixed $value, string $label): string
    {
        $operation = self::token($value, $label);
        if (!in_array($operation, ['release_readmark', 'rewrite_wal_header', 'truncate_wal', 'sync_wal'], true)) {
            throw new \InvalidArgumentException("Invalid {$label}");
        }
        return $operation;
    }

    private static function digest(mixed $value, string $label): string
    {
        if (!is_string($value) || !preg_match('/^[a-f0-9]{64}$/', $value)) {
            throw new \InvalidArgumentException("Invalid {$label}");
        }
        return $value;
    }

    /** @return list<string> */
    private static function optionalSalt(mixed $value, string $label): array
    {
        if ($value === null) {
            return [];
        }
        if (!is_array($value) || count($value) !== 2) {
            throw new \InvalidArgumentException("Invalid {$label}");
        }
        return [self::token($value[0] ?? null, $label), self::token($value[1] ?? null, $label)];
    }

    private static function positiveInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value < 1) {
            throw new \InvalidArgumentException("Invalid {$label}");
        }
        return $value;
    }

    private static function nonNegativeInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException("Invalid {$label}");
        }
        return $value;
    }

    /** @return list<int> */
    private static function positiveIntSet(mixed $value, string $label): array
    {
        $values = self::optionalPositiveIntSet($value, $label);
        if ($values === []) {
            throw new \InvalidArgumentException("Invalid {$label}");
        }
        return $values;
    }

    /** @return list<int> */
    private static function optionalPositiveIntSet(mixed $value, string $label): array
    {
        if ($value === null) {
            return [];
        }
        if (!is_array($value)) {
            throw new \InvalidArgumentException("Invalid {$label}");
        }
        $set = [];
        foreach ($value as $item) {
            if (!is_int($item) || $item < 1) {
                throw new \InvalidArgumentException("Invalid {$label}");
            }
            $set[$item] = true;
        }
        $values = array_map('intval', array_keys($set));
        sort($values);
        return $values;
    }

    /** @param list<mixed> $values @return list<mixed> */
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
        return array_values(array_keys($duplicates));
    }
}
