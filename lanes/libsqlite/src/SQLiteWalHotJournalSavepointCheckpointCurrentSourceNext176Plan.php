<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext176Plan
{
    /**
     * @param array<int,string> $hotJournalPages
     * @param array<int,string> $savepointBeforePages
     * @param array<int,array{image:string,source_id?:string,epoch?:int,pinned?:bool,dirty?:bool,label?:string}> $readerCachePages
     * @param list<int> $checkpointPages
     * @param array<string,list<int>> $releasedSavepointPages
     * @param array<int,array{page_number:int,image:string,source_id:string,epoch:int,synced?:bool,label?:string}> $databaseWriteReceipts
     * @param array<string,mixed> $walSyncReceipt
     * @param array<string,mixed> $journalDeleteReceipt
     * @param list<array{reader_id:string,source_id:string,epoch:int,wal_digest:string,hot_journal_digest?:string|null,savepoint_closed?:bool,reopened?:bool,label?:string}> $readerTickets
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
        array $journalDeleteReceipt,
        array $readerTickets,
        string $mode = 'restart',
        int $readerEndFrame = 0,
        int $currentSourceEpoch = 1,
    ): array {
        self::assertReaderTickets($readerTickets);

        $base = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext172Plan::plan(
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
            $databaseWriteReceipts,
            $walSyncReceipt,
            $mode,
            $readerEndFrame,
            $currentSourceEpoch,
        );

        $expectedJournalPath = $databasePath . '-journal';
        $expectedJournalDigest = self::journalDigest($hotJournalPages);
        $journalDeleted = ($journalDeleteReceipt['path'] ?? null) === $expectedJournalPath
            && ($journalDeleteReceipt['journal_digest'] ?? null) === $expectedJournalDigest
            && ($journalDeleteReceipt['source_id'] ?? null) === $base['current_source_token']['id']
            && (int) ($journalDeleteReceipt['epoch'] ?? -1) === (int) $base['current_source_token']['epoch']
            && (bool) ($journalDeleteReceipt['deleted'] ?? false)
            && (bool) ($journalDeleteReceipt['synced'] ?? false);

        $expectedWalDigest = (string) $base['expected_next_wal_digest'];
        $expectedSourceId = (string) $base['next_source_token']['id'];
        $expectedEpoch = (int) $base['next_source_token']['epoch'];
        $ticketRows = [];
        foreach ($readerTickets as $ticket) {
            $sourceMatches = $ticket['source_id'] === $expectedSourceId && $ticket['epoch'] === $expectedEpoch;
            $walMatches = $ticket['wal_digest'] === $expectedWalDigest;
            $journalCleared = !isset($ticket['hot_journal_digest']) || $ticket['hot_journal_digest'] === null || $ticket['hot_journal_digest'] === '';
            $savepointClosed = (bool) ($ticket['savepoint_closed'] ?? false);
            $reopened = (bool) ($ticket['reopened'] ?? false);
            $admitted = $sourceMatches && $walMatches && $journalCleared && $savepointClosed && $reopened;
            $ticketRows[] = [
                'reader_id' => $ticket['reader_id'],
                'source_matches' => $sourceMatches,
                'wal_digest_matches' => $walMatches,
                'hot_journal_cleared' => $journalCleared,
                'savepoint_closed' => $savepointClosed,
                'reopened' => $reopened,
                'admitted' => $admitted,
                'label' => $ticket['label'] ?? null,
            ];
        }

        $blockedReaders = array_values(array_map(
            static fn (array $row): string => (string) $row['reader_id'],
            array_filter($ticketRows, static fn (array $row): bool => ! (bool) $row['admitted'])
        ));
        $readerAdmissionReady = $blockedReaders === [];
        $ready = $base['publish_ready_next172'] === true && $journalDeleted && $readerAdmissionReady;

        $operations = [
            [
                'op' => 'validate_hot_journal_delete_receipt_before_next_reader_reopen',
                'expected_path' => $expectedJournalPath,
                'expected_digest' => $expectedJournalDigest,
                'deleted_and_synced' => $journalDeleted,
            ],
            [
                'op' => 'validate_reopened_readers_use_next_wal_source_after_savepoint_checkpoint',
                'expected_source_id' => $expectedSourceId,
                'expected_epoch' => $expectedEpoch,
                'expected_wal_digest' => $expectedWalDigest,
                'blocked_readers' => $blockedReaders,
            ],
        ];

        return array_merge($base, [
            'status' => $ready
                ? 'wal-hot-journal-savepoint-checkpoint-current-source-reader-admit-next176'
                : 'wal-hot-journal-savepoint-checkpoint-current-source-reader-blocked-next176',
            'reason' => $ready
                ? 'hot_journal_delete_and_next_source_reader_tickets_admit_reopen_after_checkpoint'
                : 'hot_journal_delete_or_next_source_reader_tickets_block_reopen_after_checkpoint',
            'expected_hot_journal_path_next176' => $expectedJournalPath,
            'expected_hot_journal_digest_next176' => $expectedJournalDigest,
            'journal_delete_receipt_next176' => $journalDeleteReceipt,
            'hot_journal_delete_admitted_next176' => $journalDeleted,
            'reader_ticket_rows_next176' => $ticketRows,
            'blocked_reader_ids_next176' => $blockedReaders,
            'reader_reopen_admitted_next176' => $readerAdmissionReady,
            'publish_ready_next176' => $ready,
            'operations_next176' => $operations,
            'operation_names_next176' => array_merge($base['operation_names_next172'], array_column($operations, 'op')),
            'source_digest_next176' => hash('sha256', $base['source_digest_next172'] . '|' . $expectedJournalDigest . '|' . implode(',', $blockedReaders)),
            'dependencies_next176' => [
                'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next176',
                'sqlite-hot-journal-delete-before-next-reader-reopen',
                'sqlite-next-wal-source-reader-ticket',
                'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next172',
            ],
            'dependency_closure_next176' => 'no new support component needed; reuses next172 checkpoint publish receipts, WAL digests, savepoint release lineage, and lane-local reader-ticket modeling',
            'non_overlap_next176' => 'does not repeat WAL byte truncation, VFS savepoint rollback, rollback-journal apply/commit, checkpoint transaction planning, next166 release lineage, or next172 database/WAL sync receipt admission; this slice gates reader reopen on hot-journal deletion and next-source reader tickets',
        ]);
    }

    /**
     * @param list<array{reader_id:string,source_id:string,epoch:int,wal_digest:string}> $readerTickets
     */
    private static function assertReaderTickets(array $readerTickets): void
    {
        if ($readerTickets === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next176 requires reader reopen tickets');
        }
        foreach ($readerTickets as $ticket) {
            if (($ticket['reader_id'] ?? '') === '' || ($ticket['source_id'] ?? '') === '' || ($ticket['wal_digest'] ?? '') === '' || !isset($ticket['epoch'])) {
                throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next176 reader tickets require reader_id, source_id, epoch, and wal_digest');
            }
            if (!is_int($ticket['epoch'])) {
                throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint checkpoint current-source next176 reader ticket epoch must be an integer');
            }
        }
    }

    /**
     * @param array<int,string> $hotJournalPages
     */
    private static function journalDigest(array $hotJournalPages): string
    {
        ksort($hotJournalPages, SORT_NUMERIC);
        $parts = [];
        foreach ($hotJournalPages as $pageNumber => $image) {
            $parts[] = $pageNumber . ':' . hash('sha256', $image);
        }

        return hash('sha256', implode('|', $parts));
    }
}
