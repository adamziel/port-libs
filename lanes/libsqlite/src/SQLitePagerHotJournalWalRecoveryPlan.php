<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerHotJournalWalRecoveryPlan
{
    /**
     * @param list<int> $pageNumbers
     * @return array{status:string,reason:string,database_path:string,journal_path:string,wal_path:string,current_reader_end_frame:int,next_reader_end_frame:int,current_reader:list<array<string,mixed>>,next_reader:list<array<string,mixed>>,current_reader_sources:list<string>,next_reader_sources:list<string>,current_reader_frame_indexes:list<int|null>,next_reader_frame_indexes:list<int|null>,current_reader_errors:list<string>,next_reader_errors:list<string>,current_images_match_next:bool,hot_recovered:bool,next_uses_hot_journal_database:bool,next_uses_wal_checkpoint_database:bool,discarded_valid_tail_frame_count:int,discarded_corrupt_tail_frame_count:int,recovery:array<string,mixed>,dependencies:list<string>}
     */
    public static function currentNextVisibility(
        SQLiteRollbackJournal $journal,
        string $databaseBytes,
        string $journalBytes,
        string $walBytes,
        string $databasePath,
        array $pageNumbers,
        ?int $databasePageSize = null,
        bool $databaseReservedLock = false,
        bool $requiresSuperJournal = false,
        ?bool $superJournalExists = null,
    ): array {
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite pager hot-journal WAL current/next visibility requires at least one page number');
        }

        $recovery = self::recover(
            $journal,
            $databaseBytes,
            $journalBytes,
            $walBytes,
            $databasePath,
            $databasePageSize,
            $databaseReservedLock,
            $requiresSuperJournal,
            $superJournalExists
        );

        $currentWalBytes = $recovery['wal_recovery']['valid_wal_bytes'];
        $currentWal = SQLiteWal::parse($currentWalBytes, $databasePageSize, false);
        $nextWal = SQLiteWal::parse($recovery['payloads'][$recovery['wal_path']], $databasePageSize, false);
        $nextDatabaseBytes = $recovery['payloads'][$databasePath . '#wal-checkpoint']
            ?? $recovery['payloads'][$databasePath . '#hot-journal']
            ?? $databaseBytes;
        $currentEndFrame = $recovery['wal_recovery']['valid_frame_count'];
        $nextEndFrame = $nextWal->frameCount();

        $current = [];
        $next = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite pager hot-journal WAL current/next page numbers must be integers');
            }
            $current[] = self::safeReaderVisibility($currentWal, $databaseBytes, $pageNumber, $currentEndFrame);
            $next[] = $nextEndFrame === 0
                ? self::databaseVisibility($nextDatabaseBytes, self::pageSize($nextWal, $databasePageSize, $nextDatabaseBytes), $pageNumber)
                : self::safeReaderVisibility($nextWal, $nextDatabaseBytes, $pageNumber, $nextEndFrame);
        }

        return [
            'status' => $recovery['status'],
            'reason' => 'current_dirty_reader_next_hot_journal_wal_recovery_visibility',
            'database_path' => $databasePath,
            'journal_path' => $recovery['journal_path'],
            'wal_path' => $recovery['wal_path'],
            'current_reader_end_frame' => $currentEndFrame,
            'next_reader_end_frame' => $nextEndFrame,
            'current_reader' => $current,
            'next_reader' => $next,
            'current_reader_sources' => self::visibilityColumn($current, 'source'),
            'next_reader_sources' => self::visibilityColumn($next, 'source'),
            'current_reader_frame_indexes' => self::visibilityColumn($current, 'frame_index'),
            'next_reader_frame_indexes' => self::visibilityColumn($next, 'frame_index'),
            'current_reader_errors' => self::visibilityErrors($current),
            'next_reader_errors' => self::visibilityErrors($next),
            'current_images_match_next' => self::visibilityImages($current) === self::visibilityImages($next),
            'hot_recovered' => $recovery['hot_recovered'],
            'next_uses_hot_journal_database' => isset($recovery['payloads'][$databasePath . '#hot-journal']),
            'next_uses_wal_checkpoint_database' => isset($recovery['payloads'][$databasePath . '#wal-checkpoint']),
            'discarded_valid_tail_frame_count' => $recovery['discarded_valid_tail_frame_count'],
            'discarded_corrupt_tail_frame_count' => $recovery['discarded_corrupt_tail_frame_count'],
            'recovery' => $recovery,
            'dependencies' => array_values(array_unique(array_merge(
                $recovery['dependencies'],
                ['sqlite-pager-hot-journal-wal-current-next-visibility']
            ))),
        ];
    }

    /**
     * @return array{status:string,database_path:string,journal_path:string,wal_path:string,hot_recovered:bool,wal_status:string,reason:string,database_bytes:int,journal_action:string,wal_bytes:int,committed_frame_count:int,discarded_valid_tail_frame_count:int,discarded_corrupt_tail_frame_count:int,last_commit_frame:int|null,operations:list<array<string,mixed>>,payloads:array<string,string>,hot_journal:array<string,mixed>,wal_recovery:array<string,mixed>,dependencies:list<string>}
     */
    public static function recover(
        SQLiteRollbackJournal $journal,
        string $databaseBytes,
        string $journalBytes,
        string $walBytes,
        string $databasePath,
        ?int $databasePageSize = null,
        bool $databaseReservedLock = false,
        bool $requiresSuperJournal = false,
        ?bool $superJournalExists = null,
        bool $readOnly = false,
        bool $immutable = false
    ): array {
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite pager hot-journal WAL recovery requires a database path');
        }
        if ($walBytes === '') {
            throw new \InvalidArgumentException('SQLite pager hot-journal WAL recovery requires WAL bytes');
        }
        if ($readOnly || $immutable) {
            throw new \LogicException('SQLite pager hot-journal WAL recovery requires a writable database handle');
        }

        $journalPath = $databasePath . '-journal';
        $walPath = $databasePath . '-wal';
        $hot = $journal->hotJournalRecoveryResult($databaseBytes, $journalBytes, $databaseReservedLock, $requiresSuperJournal, $superJournalExists);
        $baseDatabaseBytes = $hot['recovered'] ? $hot['database_bytes'] : $databaseBytes;
        $walRecovery = SQLiteWal::transactionRecoveryBoundary($walBytes, $baseDatabaseBytes, $databasePageSize);
        $checkpointDatabaseBytes = $walRecovery['checkpoint_database_bytes'];
        $finalDatabaseBytes = is_string($checkpointDatabaseBytes) ? $checkpointDatabaseBytes : $baseDatabaseBytes;
        $committedWalBytes = $walRecovery['committed_wal_bytes'];
        $operations = [];
        $payloads = [];

        if ($hot['recovered']) {
            $operations[] = [
                'op' => 'write',
                'path' => $databasePath,
                'offset' => 0,
                'bytes' => strlen($baseDatabaseBytes),
                'durable' => false,
                'reason' => 'restore_hot_journal_database_before_wal_recovery',
            ];
            $operations[] = [
                'op' => 'truncate',
                'path' => $databasePath,
                'bytes' => strlen($baseDatabaseBytes),
                'durable' => false,
                'reason' => 'trim_hot_journal_database_before_wal_recovery',
            ];
            $operations[] = [
                'op' => 'sync',
                'path' => $databasePath,
                'durable' => true,
                'reason' => 'sync_hot_journal_database_before_wal_recovery',
            ];
            $operations[] = [
                'op' => 'delete',
                'path' => $journalPath,
                'durable' => false,
                'reason' => 'delete_hot_journal_before_wal_recovery',
            ];
            $payloads[$databasePath . '#hot-journal'] = $baseDatabaseBytes;
            $operations[0]['payload_key'] = $databasePath . '#hot-journal';
        }

        if ($checkpointDatabaseBytes !== null) {
            $operations[] = [
                'op' => 'write',
                'path' => $databasePath,
                'offset' => 0,
                'bytes' => strlen($finalDatabaseBytes),
                'durable' => false,
                'reason' => 'checkpoint_committed_wal_after_hot_journal_recovery',
            ];
            $operations[] = [
                'op' => 'truncate',
                'path' => $databasePath,
                'bytes' => strlen($finalDatabaseBytes),
                'durable' => false,
                'reason' => 'trim_checkpointed_database_after_hot_journal_recovery',
            ];
            $operations[] = [
                'op' => 'sync',
                'path' => $databasePath,
                'durable' => true,
                'reason' => 'sync_checkpointed_database_after_hot_journal_recovery',
            ];
            $payloads[$databasePath . '#wal-checkpoint'] = $finalDatabaseBytes;
            $operations[count($operations) - 3]['payload_key'] = $databasePath . '#wal-checkpoint';
        }

        $operations[] = [
            'op' => 'write',
            'path' => $walPath,
            'offset' => 0,
            'bytes' => strlen($committedWalBytes),
            'durable' => false,
            'reason' => 'restore_committed_wal_prefix_after_hot_journal_recovery',
        ];
        $operations[] = [
            'op' => 'truncate',
            'path' => $walPath,
            'bytes' => strlen($committedWalBytes),
            'durable' => false,
            'reason' => 'discard_wal_tail_after_hot_journal_recovery',
        ];
        $operations[] = [
            'op' => 'sync',
            'path' => $walPath,
            'durable' => true,
            'reason' => 'sync_recovered_wal_after_hot_journal_recovery',
        ];
        $operations[] = [
            'op' => 'sync_directory',
            'path' => dirname($databasePath),
            'durable' => true,
            'reason' => 'persist_hot_journal_wal_recovery_sidecars',
        ];
        $payloads[$walPath] = $committedWalBytes;

        $status = $hot['recovered']
            ? 'hot_journal_recovered_wal_recovered'
            : 'hot_journal_skipped_wal_recovered';
        $reason = $hot['recovered']
            ? 'hot_journal_recovered_before_wal_transaction_recovery'
            : 'hot_journal_not_hot_wal_transaction_recovery';

        return [
            'status' => $status,
            'database_path' => $databasePath,
            'journal_path' => $journalPath,
            'wal_path' => $walPath,
            'hot_recovered' => $hot['recovered'],
            'wal_status' => $walRecovery['status'],
            'reason' => $reason,
            'database_bytes' => strlen($finalDatabaseBytes),
            'journal_action' => $hot['journal_action'],
            'wal_bytes' => strlen($committedWalBytes),
            'committed_frame_count' => $walRecovery['committed_frame_count'],
            'discarded_valid_tail_frame_count' => $walRecovery['discarded_valid_tail_frame_count'],
            'discarded_corrupt_tail_frame_count' => $walRecovery['discarded_corrupt_tail_frame_count'],
            'last_commit_frame' => $walRecovery['last_commit_frame'],
            'operations' => $operations,
            'payloads' => $payloads,
            'hot_journal' => $hot,
            'wal_recovery' => $walRecovery,
            'dependencies' => array_values(array_unique(array_merge(
                ['sqlite-pager-hot-journal-wal-recovery'],
                $walRecovery['dependencies'],
                ['sqlite-rollback-journal-recovery', 'sqlite-wal-transaction-recovery-boundary']
            ))),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    private static function safeReaderVisibility(SQLiteWal $wal, string $databaseBytes, int $pageNumber, ?int $snapshotEndFrame): array
    {
        try {
            return $wal->readerSnapshotPageImage($databaseBytes, $pageNumber, $snapshotEndFrame);
        } catch (\OutOfBoundsException $e) {
            return [
                'page_number' => $pageNumber,
                'source' => 'missing',
                'frame_index' => null,
                'database_offset' => null,
                'image' => null,
                'snapshot_end_frame' => $snapshotEndFrame ?? $wal->frameCount(),
                'snapshot_commit_frame' => null,
                'database_page_count' => intdiv(strlen($databaseBytes), self::pageSize($wal, null, $databaseBytes)),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * @return array<string,mixed>
     */
    private static function databaseVisibility(string $databaseBytes, int $pageSize, int $pageNumber): array
    {
        if ($pageNumber < 1) {
            throw new \InvalidArgumentException('SQLite pager hot-journal WAL current/next page numbers are one-based');
        }
        if ($pageSize < 512 || strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite pager hot-journal WAL current/next requires database bytes aligned to page size');
        }

        $databasePageCount = intdiv(strlen($databaseBytes), $pageSize);
        if ($pageNumber > $databasePageCount) {
            return [
                'page_number' => $pageNumber,
                'source' => 'missing',
                'frame_index' => null,
                'database_offset' => null,
                'image' => null,
                'snapshot_end_frame' => 0,
                'snapshot_commit_frame' => null,
                'database_page_count' => $databasePageCount,
                'error' => "SQLite WAL reader base page {$pageNumber} is missing from the database image",
            ];
        }

        $offset = ($pageNumber - 1) * $pageSize;

        return [
            'page_number' => $pageNumber,
            'source' => 'database',
            'frame_index' => null,
            'database_offset' => $offset,
            'image' => substr($databaseBytes, $offset, $pageSize),
            'snapshot_end_frame' => 0,
            'snapshot_commit_frame' => null,
            'database_page_count' => $databasePageCount,
        ];
    }

    private static function pageSize(SQLiteWal $wal, ?int $databasePageSize, string $databaseBytes): int
    {
        if ($wal->header->pageSize >= 512) {
            return $wal->header->pageSize;
        }
        if ($databasePageSize !== null) {
            return $databasePageSize;
        }

        return SQLiteHeader::parse($databaseBytes)->pageSize;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<mixed>
     */
    private static function visibilityColumn(array $rows, string $column): array
    {
        return array_map(static fn (array $row): mixed => $row[$column] ?? null, $rows);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string>
     */
    private static function visibilityErrors(array $rows): array
    {
        $errors = [];
        foreach ($rows as $row) {
            if (isset($row['error']) && is_string($row['error'])) {
                $errors[] = $row['error'];
            }
        }

        return $errors;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<string|null>
     */
    private static function visibilityImages(array $rows): array
    {
        return array_map(static fn (array $row): ?string => isset($row['image']) && is_string($row['image']) ? $row['image'] : null, $rows);
    }
}
