<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext172Plan
{
    /**
     * @param array<int,string> $hotJournalPages
     * @param array<int,string> $savepointBeforePages
     * @param array<int,array{image:string,source_id?:string,epoch?:int,pinned?:bool,dirty?:bool,label?:string}> $readerCachePages
     * @param list<int> $checkpointPages
     * @param array<string,list<int>> $releasedSavepointPages
     * @param array<int,array{page_number:int,image:string,source_id:string,epoch:int,synced?:bool,label?:string}> $databaseWriteReceipts
     * @param array<string,mixed> $walSyncReceipt
     * @return array<string,mixed>
     */
    public static function plan(
        string $databasePath,
        string $databaseBytes,
        int $pageSize,
        string $innerSavepoint,
        string $outerSavepoint,
        array $hotJournalPages,
        array $savepointBeforePages,
        SQLiteWal $currentWal,
        string $currentWalBytes,
        SQLiteWal $nextWal,
        string $nextWalBytes,
        array $readerCachePages,
        array $checkpointPages,
        array $releasedSavepointPages,
        array $databaseWriteReceipts,
        array $walSyncReceipt,
        string $mode = 'restart',
        int $readerEndFrame = 0,
        int $currentSourceEpoch = 1,
    ): array {
        self::assertWriteReceipts($databaseWriteReceipts);

        $base = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext166Plan::plan(
            $databasePath,
            $databaseBytes,
            $pageSize,
            $innerSavepoint,
            $outerSavepoint,
            $hotJournalPages,
            $savepointBeforePages,
            $currentWal,
            $currentWalBytes,
            $nextWal,
            $nextWalBytes,
            $readerCachePages,
            $checkpointPages,
            $releasedSavepointPages,
            $mode,
            $readerEndFrame,
            $currentSourceEpoch,
        );

        $currentSourceId = (string) $base['current_source_token']['id'];
        $currentSourceEpoch = (int) $base['current_source_token']['epoch'];
        $nextSourceId = (string) $base['next_source_token']['id'];
        $nextSourceEpoch = (int) $base['next_source_token']['epoch'];
        $expectedWalDigest = hash('sha256', $nextWalBytes);

        $receiptRows = [];
        $receiptPages = [];
        foreach ($databaseWriteReceipts as $receipt) {
            $pageNumber = (int) $receipt['page_number'];
            $baseRow = self::rowForPage($base, $pageNumber);
            $expectedLabel = is_array($baseRow) && is_string($baseRow['checkpoint_label'] ?? null)
                ? $baseRow['checkpoint_label']
                : null;
            $synced = (bool) ($receipt['synced'] ?? false);
            $sourceMatches = $receipt['source_id'] === $currentSourceId && (int) $receipt['epoch'] === $currentSourceEpoch;
            $imageMatches = $expectedLabel !== null && self::label($receipt['image']) === $expectedLabel;
            $admitted = $synced && $sourceMatches && $imageMatches;
            $receiptPages[] = $pageNumber;
            $receiptRows[] = [
                'page_number' => $pageNumber,
                'write_order' => count($receiptRows) + 1,
                'synced' => $synced,
                'source_matches' => $sourceMatches,
                'image_matches' => $imageMatches,
                'admitted' => $admitted,
                'expected_source_id' => $currentSourceId,
                'receipt_source_id' => $receipt['source_id'],
                'checkpoint_label' => is_array($baseRow) ? ($baseRow['checkpoint_label'] ?? null) : null,
                'label' => $receipt['label'] ?? null,
            ];
        }

        $requiredPages = $base['checkpoint_page_numbers'];
        $missingPages = array_values(array_diff($requiredPages, $receiptPages));
        sort($missingPages, SORT_NUMERIC);
        $unsyncedPages = [];
        $stalePages = [];
        $imageMismatchPages = [];
        foreach ($receiptRows as $row) {
            if (!$row['synced']) {
                $unsyncedPages[] = $row['page_number'];
            }
            if (!$row['source_matches']) {
                $stalePages[] = $row['page_number'];
            }
            if (!$row['image_matches']) {
                $imageMismatchPages[] = $row['page_number'];
            }
        }

        $walDigestMatches = ($walSyncReceipt['wal_digest'] ?? null) === $expectedWalDigest;
        $walSourceMatches = ($walSyncReceipt['source_id'] ?? null) === $nextSourceId
            && (int) ($walSyncReceipt['epoch'] ?? -1) === $nextSourceEpoch;
        $walSynced = (bool) ($walSyncReceipt['synced'] ?? false);
        $walSidecarAdmitted = $walSynced && $walDigestMatches && $walSourceMatches;
        $databaseAdmitted = $missingPages === [] && $unsyncedPages === [] && $stalePages === [] && $imageMismatchPages === [];
        $publishReady = $base['status'] === 'wal-hot-journal-savepoint-checkpoint-current-source-release-next166'
            && $databaseAdmitted
            && $walSidecarAdmitted;

        $publishOperations = [
            [
                'op' => 'validate_checkpoint_database_write_receipts_before_next_source_publish',
                'required_pages' => $requiredPages,
                'missing_pages' => $missingPages,
                'unsynced_pages' => $unsyncedPages,
                'stale_source_pages' => $stalePages,
                'image_mismatch_pages' => $imageMismatchPages,
            ],
            [
                'op' => 'validate_next_wal_sidecar_sync_receipt_before_reader_reopen',
                'expected_wal_digest' => $expectedWalDigest,
                'receipt_wal_digest' => $walSyncReceipt['wal_digest'] ?? null,
                'wal_synced' => $walSynced,
                'wal_source_matches' => $walSourceMatches,
            ],
            [
                'op' => 'publish_checkpoint_current_source_after_database_and_wal_sync',
                'publish_ready' => $publishReady,
                'current_source_id' => $currentSourceId,
                'next_source_id' => $nextSourceId,
            ],
        ];

        return array_merge($base, [
            'status' => $publishReady
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-publish-next172'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-publish-blocked-next172',
            'reason' => $publishReady
                ? 'checkpoint_database_and_next_wal_sync_receipts_admit_current_source_publish'
                : 'checkpoint_database_or_next_wal_sync_receipts_block_current_source_publish',
            'database_write_receipt_rows' => $receiptRows,
            'database_write_receipt_page_numbers' => array_values($receiptPages),
            'missing_database_write_pages' => $missingPages,
            'unsynced_database_write_pages' => $unsyncedPages,
            'stale_database_write_source_pages' => $stalePages,
            'image_mismatch_database_write_pages' => $imageMismatchPages,
            'database_write_admitted' => $databaseAdmitted,
            'wal_sync_receipt' => $walSyncReceipt,
            'expected_next_wal_digest' => $expectedWalDigest,
            'wal_sync_digest_matches' => $walDigestMatches,
            'wal_sync_source_matches' => $walSourceMatches,
            'wal_sidecar_admitted' => $walSidecarAdmitted,
            'publish_ready_next172' => $publishReady,
            'publish_operations_next172' => $publishOperations,
            'operation_names_next172' => array_merge($base['operation_names_next166'], array_column($publishOperations, 'op')),
            'source_digest_next172' => hash('sha256', $base['source_digest_next166'] . '|' . $expectedWalDigest . '|' . implode(',', $receiptPages)),
            'dependencies_next172' => [
                'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next172',
                'sqlite-checkpoint-current-source-publish-sync-receipts',
                'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next166',
            ],
            'dependency_closure_next172' => 'no new support component needed; reuses WAL parsing, next166 release lineage, VFS write/sync receipts, and checkpoint current-source fencing',
            'non_overlap_next172' => 'does not repeat accepted WAL byte truncation, VFS savepoint rollback, rollback-journal apply/commit, checkpoint transaction planning, next161 cache-token fencing, or next166 savepoint release lineage; this slice adds database/WAL sync-receipt admission before current-source publication',
        ]);
    }

    /**
     * @param array<int,array{page_number:int,image:string,source_id:string,epoch:int,synced?:bool,label?:string}> $databaseWriteReceipts
     */
    private static function assertWriteReceipts(array $databaseWriteReceipts): void
    {
        if ($databaseWriteReceipts === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next172 requires checkpoint database write receipts');
        }

        foreach ($databaseWriteReceipts as $receipt) {
            if (!isset($receipt['page_number'], $receipt['image'], $receipt['source_id'], $receipt['epoch'])) {
                throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next172 write receipts require page_number, image, source_id, and epoch');
            }
            if (!is_int($receipt['page_number']) || $receipt['page_number'] < 1) {
                throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next172 write receipt pages must be one-based integers');
            }
            if (!is_string($receipt['image']) || !is_string($receipt['source_id']) || $receipt['source_id'] === '' || !is_int($receipt['epoch'])) {
                throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next172 write receipt values are malformed');
            }
        }
    }

    /**
     * @param array<string,mixed> $base
     * @return array<string,mixed>|null
     */
    private static function rowForPage(array $base, int $pageNumber): ?array
    {
        foreach ($base['rows'] as $row) {
            if ((int) $row['page_number'] === $pageNumber) {
                return $row;
            }
        }

        return null;
    }

    private static function label(string $image): string
    {
        $label = rtrim($image, ".\0");

        return $label === '' ? '<empty>' : $label;
    }
}
