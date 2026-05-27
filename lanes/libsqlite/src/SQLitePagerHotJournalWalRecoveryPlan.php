<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerHotJournalWalRecoveryPlan
{
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
}
