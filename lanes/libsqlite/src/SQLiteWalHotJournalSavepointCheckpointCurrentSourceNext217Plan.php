<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext217Plan
{
    /**
     * @param array<string,mixed> $admissionPlan
     * @param array<string,array<string,mixed>> $receipts
     * @return array<string,mixed>
     */
    public static function plan(array $admissionPlan, array $receipts): array
    {
        self::assertAdmissionPlan($admissionPlan);
        if ($receipts === []) {
            throw new \InvalidArgumentException('SQLite WAL current-source next217 requires reader receipt rows');
        }

        $token = $admissionPlan['current_source_token'];
        $sourceId = (string) $token['id'];
        $epoch = (int) $token['epoch'];
        $checkpointFrame = (int) $admissionPlan['checkpoint_frame'];
        $checkpointCookie = (int) $admissionPlan['checkpoint_cookie'];
        $schemaCookie = (int) $admissionPlan['schema_cookie'];
        $readerRows = $admissionPlan['reader_admission_rows'];

        $rows = [];
        foreach ($readerRows as $reader) {
            if (!is_array($reader)) {
                throw new \InvalidArgumentException('SQLite WAL current-source next217 reader row must be an array');
            }
            $name = (string) ($reader['reader'] ?? '');
            $receipt = $receipts[$name] ?? null;
            $rows[] = self::receiptRow(
                $reader,
                is_array($receipt) ? $receipt : null,
                $sourceId,
                $epoch,
                $checkpointFrame,
                $checkpointCookie,
                $schemaCookie
            );
        }

        $orphanReceipts = array_values(array_diff(array_keys($receipts), array_column($rows, 'reader')));
        $blockedRows = array_values(array_filter($rows, static fn (array $row): bool => $row['admitted'] === false));
        $retainedRows = array_values(array_filter($rows, static fn (array $row): bool => $row['expected_action'] === 'retain-reader-cache'));
        $reopenedRows = array_values(array_filter($rows, static fn (array $row): bool => $row['expected_action'] === 'reopen-reader-cache'));

        $guards = [
            'next211_checkpoint_admitted' => $admissionPlan['status'] === 'wal-hot-journal-savepoint-checkpoint-current-source-next211'
                && ($admissionPlan['checkpoint_admitted'] ?? false) === true,
            'all_receipts_match_current_source' => $blockedRows === [],
            'no_orphan_receipts' => $orphanReceipts === [],
            'retained_readers_acknowledged' => self::allRowsPass($retainedRows, 'ack_receipt_matches'),
            'reopened_readers_fenced' => self::allRowsPass($reopenedRows, 'reopen_receipt_matches'),
            'hot_journal_delete_durable' => self::allRowsPass($rows, 'journal_delete_durable'),
            'wal_generation_synced' => self::allRowsPass($rows, 'wal_sync_durable'),
            'directory_entry_synced' => self::allRowsPass($rows, 'directory_sync_durable'),
        ];
        $blocked = array_keys(array_filter($guards, static fn (bool $passed): bool => !$passed));

        return [
            'status' => $blocked === []
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-next217'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-blocked-next217',
            'reason' => $blocked === []
                ? 'durable_reader_receipts_admit_checkpoint_next_source'
                : 'durable_reader_receipts_block_checkpoint_next_source',
            'database_path' => (string) $admissionPlan['database_path'],
            'wal_path' => (string) $admissionPlan['wal_path'],
            'journal_path' => (string) $admissionPlan['journal_path'],
            'current_source_token' => $token,
            'checkpoint_frame' => $checkpointFrame,
            'checkpoint_cookie' => $checkpointCookie,
            'schema_cookie' => $schemaCookie,
            'reader_receipt_rows' => $rows,
            'retained_reader_names' => array_values(array_column($retainedRows, 'reader')),
            'reopened_reader_names' => array_values(array_column($reopenedRows, 'reader')),
            'admitted_reader_names' => array_values(array_column(array_filter($rows, static fn (array $row): bool => $row['admitted']), 'reader')),
            'blocked_reader_names' => array_values(array_column($blockedRows, 'reader')),
            'orphan_receipts' => $orphanReceipts,
            'guard_names' => array_keys($guards),
            'guard_matches' => array_values($guards),
            'blocked_guard_names' => $blocked,
            'checkpoint_admitted' => $blocked === [],
            'next_source_epoch' => $epoch + 1,
            'receipt_digest' => hash('sha256', json_encode($rows, JSON_THROW_ON_ERROR)),
            'operation_names' => array_values(array_unique(array_merge(
                is_array($admissionPlan['operation_names'] ?? null) ? $admissionPlan['operation_names'] : [],
                [
                    'verify_durable_reader_ack_receipts_next217',
                    'verify_reopen_fence_receipts_next217',
                    'admit_checkpoint_next_source_after_durable_receipts_next217',
                ]
            ))),
            'dependencies' => array_values(array_unique(array_merge(
                is_array($admissionPlan['dependencies'] ?? null) ? $admissionPlan['dependencies'] : [],
                [
                    'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next217',
                    'sqlite-wal-reader-durable-receipt-fence',
                    'wordpress-import-hot-journal-checkpoint-reopen-receipts',
                ]
            ))),
            'dependency_closure' => 'no new support component needed; reuses next211 reader acknowledgement rows, hot-journal delete receipts, WAL sync receipts, and directory sync receipts',
            'non_overlap' => 'next217 validates durable receipt fencing after next211 acknowledgement admission; it does not repeat next211 page digest admission, next208 reader slot validation, WAL byte truncation, VFS savepoint rollback apply, rollback-journal commit/apply, or checkpoint transaction planning',
        ];
    }

    /**
     * @param array<string,mixed> $admissionPlan
     */
    private static function assertAdmissionPlan(array $admissionPlan): void
    {
        foreach (['status', 'database_path', 'wal_path', 'journal_path', 'current_source_token', 'checkpoint_frame', 'checkpoint_cookie', 'schema_cookie', 'reader_admission_rows'] as $key) {
            if (!array_key_exists($key, $admissionPlan)) {
                throw new \InvalidArgumentException("SQLite WAL current-source next217 missing admission {$key}");
            }
        }
        if (!is_array($admissionPlan['current_source_token']) || (string) ($admissionPlan['current_source_token']['id'] ?? '') === '' || (int) ($admissionPlan['current_source_token']['epoch'] ?? 0) <= 0) {
            throw new \InvalidArgumentException('SQLite WAL current-source next217 token is invalid');
        }
        if (!is_array($admissionPlan['reader_admission_rows']) || $admissionPlan['reader_admission_rows'] === []) {
            throw new \InvalidArgumentException('SQLite WAL current-source next217 requires reader admission rows');
        }
        foreach (['checkpoint_frame', 'checkpoint_cookie', 'schema_cookie'] as $key) {
            if ((int) $admissionPlan[$key] <= 0) {
                throw new \InvalidArgumentException("SQLite WAL current-source next217 {$key} must be positive");
            }
        }
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

    /**
     * @param array<string,mixed> $reader
     * @param array<string,mixed>|null $receipt
     * @return array<string,mixed>
     */
    private static function receiptRow(
        array $reader,
        ?array $receipt,
        string $sourceId,
        int $epoch,
        int $checkpointFrame,
        int $checkpointCookie,
        int $schemaCookie
    ): array {
        foreach (['reader', 'page', 'expected_action', 'acknowledged_image_sha256', 'observed_image_sha256', 'checkpoint_admitted'] as $key) {
            if (!array_key_exists($key, $reader)) {
                throw new \InvalidArgumentException("SQLite WAL current-source next217 missing reader {$key}");
            }
        }

        $name = (string) $reader['reader'];
        $page = (int) $reader['page'];
        $expectedAction = (string) $reader['expected_action'];
        if ($name === '' || $page <= 0 || !in_array($expectedAction, ['retain-reader-cache', 'reopen-reader-cache'], true)) {
            throw new \InvalidArgumentException('SQLite WAL current-source next217 reader row is invalid');
        }

        $receiptSource = $receipt === null ? null : (string) ($receipt['source_id'] ?? '');
        $receiptEpoch = $receipt === null ? null : (int) ($receipt['epoch'] ?? 0);
        $receiptFrame = $receipt === null ? null : (int) ($receipt['checkpoint_frame'] ?? 0);
        $receiptCookie = $receipt === null ? null : (int) ($receipt['checkpoint_cookie'] ?? 0);
        $receiptSchema = $receipt === null ? null : (int) ($receipt['schema_cookie'] ?? 0);
        $receiptDigest = $receipt === null ? null : (string) ($receipt['image_sha256'] ?? '');
        $fenceToken = $receipt === null ? null : (string) ($receipt['reopen_fence_token'] ?? '');
        $ackReceiptMatches = $receipt !== null
            && ($receipt['acknowledged'] ?? false) === true
            && $receiptSource === $sourceId
            && $receiptEpoch === $epoch
            && $receiptFrame === $checkpointFrame
            && $receiptCookie === $checkpointCookie
            && $receiptSchema === $schemaCookie
            && hash_equals((string) $reader['observed_image_sha256'], $receiptDigest);
        $reopenReceiptMatches = $receipt !== null
            && ($receipt['reopen_fenced'] ?? false) === true
            && $receiptSource === $sourceId
            && $receiptEpoch === $epoch
            && $receiptFrame === $checkpointFrame
            && $receiptCookie === $checkpointCookie
            && $receiptSchema === $schemaCookie
            && $fenceToken === 'reopen:' . $name . ':' . $sourceId . ':' . $checkpointFrame;
        $journalDeleteDurable = $receipt !== null && ($receipt['journal_deleted'] ?? false) === true;
        $walSyncDurable = $receipt !== null && ($receipt['wal_synced'] ?? false) === true;
        $directorySyncDurable = $receipt !== null && ($receipt['directory_synced'] ?? false) === true;
        $expectedMatches = $expectedAction === 'retain-reader-cache'
            ? $ackReceiptMatches && ($reader['checkpoint_admitted'] ?? false) === true
            : $reopenReceiptMatches;
        $admitted = $expectedMatches && $journalDeleteDurable && $walSyncDurable && $directorySyncDurable;

        return [
            'reader' => $name,
            'page' => $page,
            'expected_action' => $expectedAction,
            'source_id' => $receiptSource,
            'epoch' => $receiptEpoch,
            'checkpoint_frame' => $receiptFrame,
            'checkpoint_cookie' => $receiptCookie,
            'schema_cookie' => $receiptSchema,
            'ack_receipt_matches' => $ackReceiptMatches,
            'reopen_receipt_matches' => $reopenReceiptMatches,
            'journal_delete_durable' => $journalDeleteDurable,
            'wal_sync_durable' => $walSyncDurable,
            'directory_sync_durable' => $directorySyncDurable,
            'admitted' => $admitted,
            'acknowledged_image_sha256' => $receiptDigest,
            'expected_image_sha256' => (string) ($reader['expected_image_sha256'] ?? ''),
            'observed_image_sha256' => (string) $reader['observed_image_sha256'],
            'reopen_fence_token' => $fenceToken,
            'reason' => $admitted
                ? 'durable_reader_receipt_matches_current_source'
                : ($expectedAction === 'retain-reader-cache' ? 'durable_reader_ack_receipt_required' : 'durable_reopen_fence_receipt_required'),
            'transition' => $name . '>' . ($admitted ? 'admit-next-source' : 'block-next-source') . ':next217',
        ];
    }
}
