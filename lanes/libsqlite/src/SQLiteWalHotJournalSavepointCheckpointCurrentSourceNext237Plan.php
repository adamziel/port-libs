<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext237Plan
{
    /**
     * @param array<string,mixed> $durablePlan
     * @param list<array<string,mixed>> $sidecars
     * @param array<string,int> $readerPins
     * @return array<string,mixed>
     */
    public static function verifySidecarBoundary(array $durablePlan, array $sidecars, array $readerPins, int $pageSize): array
    {
        self::assertDurablePlan($durablePlan);
        if ($sidecars === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal current-source next237 requires WAL sidecar receipts');
        }
        if ($pageSize <= 0) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal current-source next237 page size must be positive');
        }

        $checkpointFrame = (int) $durablePlan['checkpoint_frame'];
        $expectedLength = 32 + ($checkpointFrame * (24 + $pageSize));
        $expectedWalDigest = (string) $durablePlan['expected_wal_digest'];
        $token = $durablePlan['current_source_token'];
        $tokenId = (string) $token['id'];
        $sourceEpoch = (int) $durablePlan['source_epoch'];
        $nextSourceEpoch = (int) $durablePlan['next_source_epoch'];
        $checkpointCookie = (int) $durablePlan['checkpoint_cookie'];
        $schemaCookie = (int) $durablePlan['schema_cookie'];

        $rows = [];
        foreach ($sidecars as $sidecar) {
            $rows[] = self::sidecarRow(
                $sidecar,
                $tokenId,
                $sourceEpoch,
                $nextSourceEpoch,
                $checkpointFrame,
                $checkpointCookie,
                $schemaCookie,
                $expectedWalDigest,
                $expectedLength,
                $pageSize
            );
        }

        $readerRows = [];
        foreach ($readerPins as $name => $frame) {
            if (!is_string($name) || $name === '' || !is_int($frame) || $frame < 0) {
                throw new \InvalidArgumentException('SQLite WAL hot-journal current-source next237 reader pins must map names to non-negative frames');
            }
            $readerRows[] = [
                'name' => $name,
                'readmark_frame' => $frame,
                'within_checkpoint' => $frame <= $checkpointFrame,
            ];
        }

        $blockedRows = array_values(array_filter($rows, static fn (array $row): bool => !$row['admitted']));
        $readerBlocks = array_values(array_filter($readerRows, static fn (array $row): bool => !$row['within_checkpoint']));
        $duplicateNames = self::duplicates(array_column($rows, 'name'));
        $blockedReasons = [];
        foreach ($blockedRows as $row) {
            foreach ($row['blocked_reasons'] as $reason) {
                $blockedReasons[] = $reason;
            }
        }
        if ($duplicateNames !== []) {
            $blockedReasons[] = 'duplicate_wal_sidecar_receipt';
        }
        if ($readerBlocks !== []) {
            $blockedReasons[] = 'reader_pin_beyond_checkpoint_frame';
        }
        $blockedReasons = array_values(array_unique($blockedReasons));

        $guards = [
            'next234_durable_handoff_admitted' => true,
            'wal_sidecar_length_matches_checkpoint_frame' => $blockedRows === [],
            'no_duplicate_wal_sidecar_receipts' => $duplicateNames === [],
            'reader_pins_do_not_cross_checkpoint_frame' => $readerBlocks === [],
            'at_least_one_sidecar_receipt_admitted' => array_filter($rows, static fn (array $row): bool => $row['admitted']) !== [],
        ];
        $blockedGuards = array_keys(array_filter($guards, static fn (bool $matched): bool => !$matched));
        $admitted = $blockedGuards === [];

        return [
            'status' => $admitted
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next237'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next237',
            'reason' => $admitted
                ? 'wal_sidecar_boundary_matches_durable_hot_journal_checkpoint_source'
                : 'wal_sidecar_boundary_blocks_durable_hot_journal_checkpoint_source',
            'database_path' => (string) $durablePlan['database_path'],
            'wal_path' => (string) $durablePlan['wal_path'],
            'journal_path' => (string) $durablePlan['journal_path'],
            'current_source_token' => $token,
            'source_epoch' => $sourceEpoch,
            'next_source_epoch' => $nextSourceEpoch,
            'checkpoint_frame' => $checkpointFrame,
            'checkpoint_cookie' => $checkpointCookie,
            'schema_cookie' => $schemaCookie,
            'page_size' => $pageSize,
            'expected_wal_digest' => $expectedWalDigest,
            'expected_wal_sidecar_length' => $expectedLength,
            'sidecar_rows' => $rows,
            'sidecar_names' => array_values(array_column($rows, 'name')),
            'admitted_sidecar_names' => array_values(array_column(array_filter($rows, static fn (array $row): bool => $row['admitted']), 'name')),
            'blocked_sidecar_names' => array_values(array_column($blockedRows, 'name')),
            'duplicate_sidecar_names' => $duplicateNames,
            'reader_pin_rows' => $readerRows,
            'blocked_reader_pin_names' => array_values(array_column($readerBlocks, 'name')),
            'blocked_reasons' => $blockedReasons,
            'guard_names' => array_keys($guards),
            'guard_matches' => array_values($guards),
            'blocked_guard_names' => $blockedGuards,
            'can_reuse_wal_sidecar' => $admitted,
            'wal_action' => $admitted ? 'reuse_durable_wal_sidecar_boundary' : 'truncate_or_reopen_wal_sidecar_before_reuse',
            'pager_action' => $admitted ? 'serve_current_source_after_sidecar_boundary_check' : 'hold_current_source_until_sidecar_boundary_matches',
            'sidecar_boundary_digest' => hash('sha256', json_encode([$token, $expectedWalDigest, $expectedLength, $rows, $readerRows], JSON_THROW_ON_ERROR)),
            'operation_names' => array_values(array_unique(array_merge(
                is_array($durablePlan['operation_names'] ?? null) ? $durablePlan['operation_names'] : [],
                [
                    'verify_wal_sidecar_boundary_current_source_next237',
                    $admitted ? 'reuse_checkpoint_wal_sidecar_current_source_next237' : 'block_checkpoint_wal_sidecar_current_source_next237',
                ]
            ))),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($durablePlan['dependencies'] ?? null) ? $durablePlan['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next237',
                    'sqlite-wal-sidecar-boundary-current-source',
                    'wordpress-import-wal-sidecar-boundary-after-hot-journal',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses next234 durable handoff receipts plus native PHP WAL sidecar length, checksum, salt, and reader-pin metadata',
            'non_overlap' => 'next237 verifies WAL sidecar boundary reuse after next234 durable handoff; it does not repeat durable sync receipt admission, WAL-index reopen readmarks, savepoint byte truncation, VFS savepoint rollback apply, rollback-journal commit/apply, or checkpoint transaction planning',
        ];
    }

    /**
     * @param array<string,mixed> $plan
     */
    private static function assertDurablePlan(array $plan): void
    {
        foreach (['status', 'database_path', 'wal_path', 'journal_path', 'current_source_token', 'source_epoch', 'next_source_epoch', 'checkpoint_frame', 'checkpoint_cookie', 'schema_cookie', 'expected_wal_digest', 'can_serve_durable_current_source'] as $key) {
            if (!array_key_exists($key, $plan)) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal current-source next237 missing durable {$key}");
            }
        }
        if (($plan['status'] ?? null) !== 'wal-hot-journal-savepoint-checkpoint-current-source-next234' || ($plan['can_serve_durable_current_source'] ?? false) !== true) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal current-source next237 requires admitted next234 durable handoff');
        }
        if (!is_array($plan['current_source_token']) || (string) ($plan['current_source_token']['id'] ?? '') === '' || (int) ($plan['current_source_token']['epoch'] ?? 0) <= 0) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal current-source next237 token is invalid');
        }
        foreach (['source_epoch', 'next_source_epoch', 'checkpoint_frame', 'checkpoint_cookie', 'schema_cookie'] as $key) {
            if (!is_int($plan[$key]) || $plan[$key] <= 0) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal current-source next237 {$key} must be positive");
            }
        }
        if (!is_string($plan['expected_wal_digest']) || !preg_match('/^[a-f0-9]{64}$/', $plan['expected_wal_digest'])) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal current-source next237 expected WAL digest must be a sha256 string');
        }
    }

    /**
     * @param array<string,mixed> $sidecar
     * @return array<string,mixed>
     */
    private static function sidecarRow(
        array $sidecar,
        string $tokenId,
        int $sourceEpoch,
        int $nextSourceEpoch,
        int $checkpointFrame,
        int $checkpointCookie,
        int $schemaCookie,
        string $walDigest,
        int $expectedLength,
        int $pageSize
    ): array {
        foreach (['name', 'source_token_id', 'source_epoch', 'next_source_epoch', 'checkpoint_frame', 'checkpoint_cookie', 'schema_cookie', 'wal_digest', 'salt_1', 'salt_2', 'page_size', 'frame_count', 'byte_length', 'last_commit_frame', 'checksum_digest', 'hot_journal_visible', 'savepoint_depth', 'writer_generation', 'directory_synced'] as $key) {
            if (!array_key_exists($key, $sidecar)) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal current-source next237 missing WAL sidecar {$key}");
            }
        }
        $name = (string) $sidecar['name'];
        if ($name === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal current-source next237 WAL sidecar name is required');
        }
        foreach (['source_epoch', 'next_source_epoch', 'checkpoint_frame', 'checkpoint_cookie', 'schema_cookie', 'salt_1', 'salt_2', 'page_size', 'frame_count', 'byte_length', 'last_commit_frame', 'savepoint_depth', 'writer_generation'] as $key) {
            if (!is_int($sidecar[$key]) || $sidecar[$key] < 0) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal current-source next237 {$name} {$key} must be a non-negative integer");
            }
        }
        foreach (['wal_digest', 'checksum_digest'] as $key) {
            if (!is_string($sidecar[$key]) || !preg_match('/^[a-f0-9]{64}$/', $sidecar[$key])) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal current-source next237 {$name} {$key} must be a sha256 string");
            }
        }

        $reasons = [];
        if ((string) $sidecar['source_token_id'] !== $tokenId) {
            $reasons[] = 'sidecar_source_token_mismatch';
        }
        if ((int) $sidecar['source_epoch'] !== $sourceEpoch) {
            $reasons[] = 'sidecar_source_epoch_mismatch';
        }
        if ((int) $sidecar['next_source_epoch'] !== $nextSourceEpoch) {
            $reasons[] = 'sidecar_next_source_epoch_mismatch';
        }
        if ((int) $sidecar['checkpoint_frame'] !== $checkpointFrame || (int) $sidecar['frame_count'] !== $checkpointFrame) {
            $reasons[] = 'sidecar_checkpoint_frame_mismatch';
        }
        if ((int) $sidecar['checkpoint_cookie'] !== $checkpointCookie) {
            $reasons[] = 'sidecar_checkpoint_cookie_mismatch';
        }
        if ((int) $sidecar['schema_cookie'] !== $schemaCookie) {
            $reasons[] = 'sidecar_schema_cookie_mismatch';
        }
        if ((string) $sidecar['wal_digest'] !== $walDigest) {
            $reasons[] = 'sidecar_wal_digest_mismatch';
        }
        if ((int) $sidecar['page_size'] !== $pageSize) {
            $reasons[] = 'sidecar_page_size_mismatch';
        }
        if ((int) $sidecar['byte_length'] !== $expectedLength) {
            $reasons[] = 'sidecar_byte_length_mismatch';
        }
        if ((int) $sidecar['last_commit_frame'] !== $checkpointFrame) {
            $reasons[] = 'sidecar_last_commit_frame_mismatch';
        }
        if ((int) $sidecar['writer_generation'] !== $nextSourceEpoch) {
            $reasons[] = 'sidecar_writer_generation_mismatch';
        }
        if ((int) $sidecar['salt_1'] <= 0 || (int) $sidecar['salt_2'] <= 0 || (int) $sidecar['salt_1'] === (int) $sidecar['salt_2']) {
            $reasons[] = 'sidecar_salt_pair_invalid';
        }
        if (($sidecar['hot_journal_visible'] ?? false) === true) {
            $reasons[] = 'sidecar_hot_journal_visible';
        }
        if ((int) $sidecar['savepoint_depth'] !== 0) {
            $reasons[] = 'sidecar_savepoint_scope_open';
        }
        if (($sidecar['directory_synced'] ?? null) !== true) {
            $reasons[] = 'sidecar_directory_sync_missing';
        }

        $reasons = array_values(array_unique($reasons));

        return [
            'name' => $name,
            'source_token_id' => (string) $sidecar['source_token_id'],
            'source_epoch' => (int) $sidecar['source_epoch'],
            'next_source_epoch' => (int) $sidecar['next_source_epoch'],
            'checkpoint_frame' => (int) $sidecar['checkpoint_frame'],
            'checkpoint_cookie' => (int) $sidecar['checkpoint_cookie'],
            'schema_cookie' => (int) $sidecar['schema_cookie'],
            'wal_digest' => (string) $sidecar['wal_digest'],
            'salt_1' => (int) $sidecar['salt_1'],
            'salt_2' => (int) $sidecar['salt_2'],
            'page_size' => (int) $sidecar['page_size'],
            'frame_count' => (int) $sidecar['frame_count'],
            'byte_length' => (int) $sidecar['byte_length'],
            'last_commit_frame' => (int) $sidecar['last_commit_frame'],
            'checksum_digest' => (string) $sidecar['checksum_digest'],
            'hot_journal_visible' => ($sidecar['hot_journal_visible'] ?? false) === true,
            'savepoint_depth' => (int) $sidecar['savepoint_depth'],
            'writer_generation' => (int) $sidecar['writer_generation'],
            'directory_synced' => $sidecar['directory_synced'] === true,
            'admitted' => $reasons === [],
            'sidecar_reason' => $reasons === [] ? 'wal_sidecar_boundary_matches_checkpoint_current_source' : implode('|', $reasons),
            'blocked_reasons' => $reasons,
        ];
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
}
