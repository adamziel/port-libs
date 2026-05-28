<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext219Plan
{
    /**
     * @param array<string,mixed> $admissionPlan
     * @param list<array<string,mixed>> $savepointScopes
     * @return array<string,mixed>
     */
    public static function plan(array $admissionPlan, array $savepointScopes): array
    {
        self::assertAdmissionPlan($admissionPlan);
        if ($savepointScopes === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal current-source next219 requires savepoint scope rows');
        }

        $token = $admissionPlan['current_source_token'];
        $epoch = (int) $token['epoch'];
        $checkpointFrame = (int) $admissionPlan['checkpoint_frame'];
        $checkpointCookie = (int) $admissionPlan['checkpoint_cookie'];
        $schemaCookie = (int) $admissionPlan['schema_cookie'];
        $admittedReaders = self::stringSet($admissionPlan['admitted_reader_names']);
        $reopenReaders = self::stringSet($admissionPlan['reopen_reader_names']);

        $rows = [];
        foreach ($savepointScopes as $scope) {
            $rows[] = self::scopeRow($scope, $epoch, $checkpointFrame, $checkpointCookie, $schemaCookie, $admittedReaders, $reopenReaders);
        }

        $finalized = array_values(array_column(array_filter($rows, static fn (array $row): bool => $row['finalized']), 'name'));
        $blocked = array_values(array_column(array_filter($rows, static fn (array $row): bool => !$row['finalized']), 'name'));
        $blockedReasons = [];
        foreach ($rows as $row) {
            foreach ($row['blocked_reasons'] as $reason) {
                $blockedReasons[] = $reason;
            }
        }
        $blockedReasons = array_values(array_unique($blockedReasons));

        $guards = [
            'next211_checkpoint_admitted' => $admissionPlan['status'] === 'wal-hot-journal-savepoint-checkpoint-current-source-next211'
                && ($admissionPlan['checkpoint_admitted'] ?? false) === true,
            'all_savepoint_scopes_finalized' => $blocked === [],
            'at_least_one_scope_finalized' => $finalized !== [],
            'no_reader_reopen_overlap' => !self::hasReaderOverlap($rows, $reopenReaders),
        ];
        $guardBlocks = array_keys(array_filter($guards, static fn (bool $passed): bool => !$passed));

        return [
            'status' => $guardBlocks === []
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next219'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next219',
            'reason' => $guardBlocks === []
                ? 'savepoint_scopes_finalized_before_checkpoint_next_source'
                : 'savepoint_scopes_block_checkpoint_next_source',
            'database_path' => (string) $admissionPlan['database_path'],
            'wal_path' => (string) $admissionPlan['wal_path'],
            'journal_path' => (string) $admissionPlan['journal_path'],
            'current_source_token' => $token,
            'checkpoint_frame' => $checkpointFrame,
            'checkpoint_cookie' => $checkpointCookie,
            'schema_cookie' => $schemaCookie,
            'next_source_epoch' => (int) $admissionPlan['next_source_epoch'],
            'scope_rows' => $rows,
            'finalized_scope_names' => $finalized,
            'blocked_scope_names' => $blocked,
            'blocked_scope_reasons' => $blockedReasons,
            'guard_names' => array_keys($guards),
            'guard_matches' => array_values($guards),
            'blocked_guard_names' => $guardBlocks,
            'checkpoint_next_source_published' => $guardBlocks === [],
            'savepoint_scope_digest' => hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR)),
            'operation_names' => array_values(array_unique(array_merge(
                is_array($admissionPlan['operation_names'] ?? null) ? $admissionPlan['operation_names'] : [],
                [
                    'finalize_hot_journal_savepoint_scope_next219',
                    'verify_checkpoint_cookie_after_savepoint_finalization_next219',
                    'publish_checkpoint_next_source_after_savepoint_finalization_next219',
                ]
            ))),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($admissionPlan['dependencies'] ?? null) ? $admissionPlan['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next219',
                    'sqlite-savepoint-scope-finalization-before-wal-current-source',
                    'wordpress-import-hot-journal-savepoint-finalization',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses next211 reader acknowledgements plus savepoint scope generation, journal delete receipts, and checkpoint cookies',
            'non_overlap' => 'next219 finalizes savepoint scopes before publishing checkpoint next-source state; it does not repeat next211 reader acknowledgements, next208 reader-slot digest validation, WAL byte truncation, VFS savepoint rollback apply, rollback-journal commit/apply, or checkpoint transaction planning',
        ];
    }

    /**
     * @param array<string,mixed> $admissionPlan
     */
    private static function assertAdmissionPlan(array $admissionPlan): void
    {
        foreach (['status', 'database_path', 'wal_path', 'journal_path', 'current_source_token', 'checkpoint_frame', 'checkpoint_cookie', 'schema_cookie', 'next_source_epoch', 'admitted_reader_names', 'reopen_reader_names'] as $key) {
            if (!array_key_exists($key, $admissionPlan)) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal current-source next219 missing admission {$key}");
            }
        }
        if (!is_array($admissionPlan['current_source_token']) || (string) ($admissionPlan['current_source_token']['id'] ?? '') === '' || (int) ($admissionPlan['current_source_token']['epoch'] ?? 0) <= 0) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal current-source next219 token is invalid');
        }
        foreach (['checkpoint_frame', 'checkpoint_cookie', 'schema_cookie', 'next_source_epoch'] as $key) {
            if ((int) $admissionPlan[$key] <= 0) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal current-source next219 {$key} must be positive");
            }
        }
        if ((int) $admissionPlan['next_source_epoch'] <= (int) $admissionPlan['current_source_token']['epoch']) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal current-source next219 next source epoch must advance');
        }
    }

    /**
     * @param mixed $values
     * @return array<string,true>
     */
    private static function stringSet($values): array
    {
        if (!is_array($values)) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal current-source next219 reader names must be arrays');
        }
        $set = [];
        foreach ($values as $value) {
            if (!is_string($value) || $value === '') {
                throw new \InvalidArgumentException('SQLite WAL hot-journal current-source next219 reader names must be non-empty strings');
            }
            $set[$value] = true;
        }

        return $set;
    }

    /**
     * @param array<string,mixed> $scope
     * @param array<string,true> $admittedReaders
     * @param array<string,true> $reopenReaders
     * @return array<string,mixed>
     */
    private static function scopeRow(array $scope, int $epoch, int $checkpointFrame, int $checkpointCookie, int $schemaCookie, array $admittedReaders, array $reopenReaders): array
    {
        foreach (['name', 'savepoint_depth', 'released', 'rollback_generation', 'checkpoint_frame', 'checkpoint_cookie', 'schema_cookie', 'journal_delete_receipt', 'wal_reset_frame', 'reader_names', 'page_digests'] as $key) {
            if (!array_key_exists($key, $scope)) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal current-source next219 missing scope {$key}");
            }
        }
        $name = (string) $scope['name'];
        if ($name === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal current-source next219 scope name is required');
        }
        foreach (['savepoint_depth', 'rollback_generation', 'checkpoint_frame', 'checkpoint_cookie', 'schema_cookie', 'wal_reset_frame'] as $key) {
            if (!is_int($scope[$key]) || $scope[$key] < 0) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal current-source next219 {$name} {$key} must be a non-negative integer");
            }
        }
        $readerNames = self::stringSet($scope['reader_names']);
        $pageDigests = self::pageDigestMap($scope['page_digests'], $name);

        $reasons = [];
        if ((int) $scope['savepoint_depth'] !== 0) {
            $reasons[] = 'savepoint_scope_depth_open';
        }
        if (($scope['released'] ?? false) !== true) {
            $reasons[] = 'savepoint_scope_not_released';
        }
        if ((int) $scope['rollback_generation'] > $epoch) {
            $reasons[] = 'savepoint_rollback_generation_after_current_source';
        }
        if ((int) $scope['checkpoint_frame'] !== $checkpointFrame) {
            $reasons[] = 'savepoint_checkpoint_frame_mismatch';
        }
        if ((int) $scope['checkpoint_cookie'] !== $checkpointCookie) {
            $reasons[] = 'savepoint_checkpoint_cookie_mismatch';
        }
        if ((int) $scope['schema_cookie'] !== $schemaCookie) {
            $reasons[] = 'savepoint_schema_cookie_mismatch';
        }
        if (($scope['journal_delete_receipt'] ?? false) !== true) {
            $reasons[] = 'savepoint_hot_journal_delete_receipt_missing';
        }
        if ((int) $scope['wal_reset_frame'] < $checkpointFrame) {
            $reasons[] = 'savepoint_wal_reset_before_checkpoint_frame';
        }
        if (($scope['hot_journal_present'] ?? false) === true) {
            $reasons[] = 'savepoint_hot_journal_still_present';
        }

        $unknownReaders = array_values(array_diff(array_keys($readerNames), array_keys($admittedReaders), array_keys($reopenReaders)));
        if ($unknownReaders !== []) {
            $reasons[] = 'savepoint_reader_not_in_checkpoint_admission';
        }
        $reopenOverlap = array_values(array_intersect(array_keys($readerNames), array_keys($reopenReaders)));
        if ($reopenOverlap !== []) {
            $reasons[] = 'savepoint_reader_waits_for_reopen_fence';
        }

        $finalized = $reasons === [];

        return [
            'name' => $name,
            'savepoint_depth' => (int) $scope['savepoint_depth'],
            'released' => (bool) $scope['released'],
            'rollback_generation' => (int) $scope['rollback_generation'],
            'checkpoint_frame' => (int) $scope['checkpoint_frame'],
            'checkpoint_cookie' => (int) $scope['checkpoint_cookie'],
            'schema_cookie' => (int) $scope['schema_cookie'],
            'journal_delete_receipt' => (bool) $scope['journal_delete_receipt'],
            'wal_reset_frame' => (int) $scope['wal_reset_frame'],
            'reader_names' => array_keys($readerNames),
            'page_numbers' => array_keys($pageDigests),
            'page_digest_count' => count($pageDigests),
            'hot_journal_present' => ($scope['hot_journal_present'] ?? false) === true,
            'unknown_readers' => $unknownReaders,
            'reopen_reader_overlap' => $reopenOverlap,
            'finalized' => $finalized,
            'blocked_reasons' => $reasons,
            'scope_reason' => $finalized ? 'savepoint_scope_finalized_for_checkpoint_next_source' : $reasons[0],
            'scope_transition' => $name . '>' . ($finalized ? 'publish-checkpoint-next-source' : 'hold-current-source') . ':next219',
        ];
    }

    /**
     * @param mixed $values
     * @return array<int,string>
     */
    private static function pageDigestMap($values, string $scopeName): array
    {
        if (!is_array($values) || $values === []) {
            throw new \InvalidArgumentException("SQLite WAL hot-journal current-source next219 {$scopeName} requires page digests");
        }
        $normalized = [];
        foreach ($values as $page => $digest) {
            $pageNumber = (int) $page;
            if ($pageNumber <= 0 || !is_string($digest) || !preg_match('/^[a-f0-9]{64}$/', $digest)) {
                throw new \InvalidArgumentException("SQLite WAL hot-journal current-source next219 {$scopeName} page digests must map positive pages to sha256 strings");
            }
            $normalized[$pageNumber] = $digest;
        }
        ksort($normalized);

        return $normalized;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @param array<string,true> $reopenReaders
     */
    private static function hasReaderOverlap(array $rows, array $reopenReaders): bool
    {
        foreach ($rows as $row) {
            foreach ($row['reader_names'] as $readerName) {
                if (isset($reopenReaders[$readerName])) {
                    return true;
                }
            }
        }

        return false;
    }
}
