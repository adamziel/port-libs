<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointReplayPlan
{
    /**
     * @param list<int> $pageNumbers
     * @return array{status:string,reason:string,database_path:string,journal_path:string,wal_path:string,savepoint:string,hot_recovered:bool,journal_action:string,rollback_to_frame:int,original_frame_count:int,retained_frame_count:int,discarded_frame_count:int,current_wal_bytes:string,current_wal_bytes_length:int,current_reader_end_frame:int,next_reader_end_frame:int,current_reader:list<array<string,mixed>>,next_reader:list<array<string,mixed>>,current_reader_sources:list<string>,next_reader_sources:list<string>,current_reader_frame_indexes:list<int|null>,next_reader_frame_indexes:list<int|null>,current_reader_errors:list<string>,next_reader_errors:list<string>,images_match:bool,next_uses_checkpoint_database:bool,can_checkpoint:bool,checkpoint_database_page_count:int|null,discarded_valid_tail_frame_count:int,discarded_corrupt_tail_frame_count:int,operations:list<array<string,mixed>>,payloads:array<string,string>,hot_journal:array<string,mixed>,savepoint_truncation:array<string,mixed>,wal_recovery:array<string,mixed>,dependencies:list<string>}
     */
    public static function replayCurrentNext(
        SQLiteRollbackJournal $journal,
        string $databaseBytes,
        string $journalBytes,
        SQLiteSavepointStack $savepoints,
        string $savepoint,
        SQLiteWal $wal,
        string $walBytes,
        string $databasePath,
        array $pageNumbers,
        bool $databaseReservedLock = false,
        bool $requiresSuperJournal = false,
        ?bool $superJournalExists = null,
    ): array {
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint replay requires a database path');
        }
        if ($savepoint === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint replay requires a savepoint name');
        }
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint replay requires at least one page number');
        }
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint replay page numbers must be one-based integers');
            }
        }
        if ($walBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint replay requires WAL bytes');
        }

        $hot = $journal->hotJournalRecoveryResult(
            $databaseBytes,
            $journalBytes,
            $databaseReservedLock,
            $requiresSuperJournal,
            $superJournalExists
        );
        $recoveredDatabaseBytes = $hot['recovered'] ? $hot['database_bytes'] : $databaseBytes;
        $truncation = $savepoints->walRollbackToByteTruncationPlan($savepoint, $wal, $walBytes);
        $currentWalBytes = $savepoints->walRollbackToWalBytes($savepoint, $wal, $walBytes);
        $boundary = SQLiteWal::corruptRecoveryCurrentNextBoundary(
            $currentWalBytes,
            $recoveredDatabaseBytes,
            $pageNumbers,
            $wal->header->pageSize
        );
        $recovery = SQLiteWal::transactionRecoveryBoundary(
            $currentWalBytes,
            $recoveredDatabaseBytes,
            $wal->header->pageSize
        );

        $journalPath = $databasePath . '-journal';
        $walPath = $databasePath . '-wal';
        $operations = [];
        $payloads = [];

        if ($hot['recovered']) {
            $payloads[$databasePath . '#hot-journal'] = $recoveredDatabaseBytes;
            $operations[] = [
                'op' => 'write',
                'path' => $databasePath,
                'offset' => 0,
                'bytes' => strlen($recoveredDatabaseBytes),
                'payload_key' => $databasePath . '#hot-journal',
                'reason' => 'restore_hot_journal_database_before_savepoint_wal_replay',
            ];
            $operations[] = [
                'op' => 'truncate',
                'path' => $databasePath,
                'bytes' => strlen($recoveredDatabaseBytes),
                'reason' => 'trim_hot_journal_database_before_savepoint_wal_replay',
            ];
            $operations[] = [
                'op' => 'delete',
                'path' => $journalPath,
                'reason' => 'delete_hot_journal_before_savepoint_wal_replay',
            ];
        }

        if (is_string($recovery['checkpoint_database_bytes'])) {
            $payloads[$databasePath . '#savepoint-wal-checkpoint'] = $recovery['checkpoint_database_bytes'];
            $operations[] = [
                'op' => 'write',
                'path' => $databasePath,
                'offset' => 0,
                'bytes' => strlen($recovery['checkpoint_database_bytes']),
                'payload_key' => $databasePath . '#savepoint-wal-checkpoint',
                'reason' => 'checkpoint_retained_wal_prefix_after_hot_journal',
            ];
            $operations[] = [
                'op' => 'truncate',
                'path' => $databasePath,
                'bytes' => strlen($recovery['checkpoint_database_bytes']),
                'reason' => 'trim_checkpointed_database_after_retained_wal_prefix',
            ];
        }

        $payloads[$walPath] = $currentWalBytes;
        $operations[] = [
            'op' => 'write',
            'path' => $walPath,
            'offset' => 0,
            'bytes' => strlen($currentWalBytes),
            'payload_key' => $walPath,
            'reason' => 'restore_savepoint_retained_wal_prefix',
        ];
        $operations[] = [
            'op' => 'truncate',
            'path' => $walPath,
            'bytes' => strlen($currentWalBytes),
            'reason' => 'discard_savepoint_wal_frames_before_next_open',
        ];

        $status = $hot['recovered']
            ? 'hot_journal_recovered_savepoint_wal_replayed'
            : 'hot_journal_skipped_savepoint_wal_replayed';
        $reason = $hot['recovered']
            ? 'hot_journal_recovered_before_savepoint_wal_replay'
            : 'hot_journal_not_hot_before_savepoint_wal_replay';

        return [
            'status' => $status,
            'reason' => $reason,
            'database_path' => $databasePath,
            'journal_path' => $journalPath,
            'wal_path' => $walPath,
            'savepoint' => $savepoint,
            'hot_recovered' => $hot['recovered'],
            'journal_action' => $hot['journal_action'],
            'rollback_to_frame' => $truncation['rollback_to_frame'],
            'original_frame_count' => $truncation['original_frame_count'],
            'retained_frame_count' => $truncation['retained_frame_count'],
            'discarded_frame_count' => $truncation['discarded_frame_count'],
            'current_wal_bytes' => $currentWalBytes,
            'current_wal_bytes_length' => strlen($currentWalBytes),
            'current_reader_end_frame' => $boundary['current_reader_end_frame'],
            'next_reader_end_frame' => $boundary['next_reader_end_frame'],
            'current_reader' => $boundary['current_reader'],
            'next_reader' => $boundary['next_reader'],
            'current_reader_sources' => $boundary['current_reader_sources'],
            'next_reader_sources' => $boundary['next_reader_sources'],
            'current_reader_frame_indexes' => $boundary['current_reader_frame_indexes'],
            'next_reader_frame_indexes' => $boundary['next_reader_frame_indexes'],
            'current_reader_errors' => $boundary['current_reader_errors'],
            'next_reader_errors' => $boundary['next_reader_errors'],
            'images_match' => $boundary['images_match'],
            'next_uses_checkpoint_database' => $boundary['next_uses_checkpoint_database'],
            'can_checkpoint' => $recovery['can_checkpoint'],
            'checkpoint_database_page_count' => $recovery['checkpoint_database_page_count'],
            'discarded_valid_tail_frame_count' => $boundary['discarded_valid_tail_frame_count'],
            'discarded_corrupt_tail_frame_count' => $boundary['discarded_corrupt_tail_frame_count'],
            'operations' => $operations,
            'payloads' => $payloads,
            'hot_journal' => $hot,
            'savepoint_truncation' => $truncation,
            'wal_recovery' => $recovery,
            'dependencies' => array_values(array_unique(array_merge(
                ['sqlite-hot-journal-savepoint-wal-replay-current-next'],
                $hot['recovered'] ? ['sqlite-rollback-journal-recovery'] : [],
                $truncation['discarded_frame_count'] > 0 ? ['sqlite-savepoint-wal-current-prefix'] : [],
                $boundary['dependencies'],
                ['sqlite-wal-transaction-recovery-boundary']
            ))),
        ];
    }
}
