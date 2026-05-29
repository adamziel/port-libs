<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalHotJournalSavepointReplayPlan
{
    /**
     * @param list<int> $pageNumbers
     * @param array<int,string> $currentStatementSourcePages
     * @return array<string,mixed>
     */
    public static function statementHotJournalRollbackPlan(
        SQLiteRollbackJournal $journal,
        string $databaseBytes,
        string $journalBytes,
        SQLiteSavepointStack $savepoints,
        string $savepoint,
        string $currentStatementName,
        string $nextStatementName,
        int $nextPageNumber,
        string $nextBeforeImage,
        SQLiteWal $wal,
        string $walBytes,
        string $databasePath,
        array $pageNumbers,
        array $currentStatementSourcePages,
        bool $nextCommitFrame = false,
        bool $databaseReservedLock = false,
        bool $requiresSuperJournal = false,
        ?bool $superJournalExists = null,
    ): array {
        if ($currentStatementName === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal statement current-source replay requires a current statement name');
        }
        if ($nextStatementName === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal statement current-source replay requires a next statement name');
        }
        if ($nextPageNumber < 1) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal statement current-source replay next page numbers are one-based');
        }
        if ($nextBeforeImage === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal statement current-source replay requires a next statement before image');
        }
        if (strlen($nextBeforeImage) !== $wal->header->pageSize) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal statement current-source replay next before image must match WAL page size');
        }
        if ($currentStatementSourcePages === []) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal statement current-source replay requires current statement source pages');
        }

        if ($journalBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal statement current-source replay requires rollback-journal bytes');
        }
        if ($journal->toBytes() !== $journalBytes) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal statement current-source replay rollback journal bytes do not match the parsed journal');
        }
        if ($wal->toBytes() !== $walBytes) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal statement current-source replay WAL bytes do not match the parsed WAL');
        }

        $hot = $journal->hotJournalRecoveryResult(
            $databaseBytes,
            $journalBytes,
            $databaseReservedLock,
            $requiresSuperJournal,
            $superJournalExists
        );
        $recoveredDatabaseBytes = $hot['recovered'] ? $hot['database_bytes'] : $databaseBytes;
        $walRecovery = SQLiteWal::transactionRecoveryBoundary($walBytes, $recoveredDatabaseBytes, $wal->header->pageSize);
        $checkpointBytes = $walRecovery['checkpoint_database_bytes'] ?? null;
        if (!is_string($checkpointBytes)) {
            throw new \LogicException('SQLite WAL hot-journal statement current-source replay requires a checkpointable current WAL image');
        }

        $statement = $savepoints->rollbackStatementCurrentSourceAndBeginStatementJournal(
            $currentStatementName,
            $nextStatementName,
            $checkpointBytes,
            $currentStatementSourcePages,
            $nextPageNumber,
            $nextBeforeImage,
            $wal->header->pageSize,
            $nextCommitFrame
        );

        $statementPayloadKey = $databasePath . '#statement-statement-rollback';
        $walPayloadKey = $databasePath . '-wal#statement-statement-rollback';
        $nextWalBytes = substr($walBytes, 0, 32 + ($statement['rollback_to_wal_frame'] * (24 + $wal->header->pageSize)));
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
                'reason' => 'restore_hot_journal_database_before_statement_wal_current_source',
            ];
            $operations[] = [
                'op' => 'truncate',
                'path' => $databasePath,
                'bytes' => strlen($recoveredDatabaseBytes),
                'reason' => 'trim_hot_journal_database_before_statement_wal_current_source',
            ];
            $operations[] = [
                'op' => 'delete',
                'path' => $journalPath,
                'reason' => 'delete_hot_journal_before_statement_wal_current_source',
            ];
        }

        $payloads[$databasePath . '#current-wal-checkpoint91'] = $checkpointBytes;
        $operations[] = [
            'op' => 'write',
            'path' => $databasePath,
            'offset' => 0,
            'bytes' => strlen($checkpointBytes),
            'payload_key' => $databasePath . '#current-wal-checkpoint91',
            'reason' => 'checkpoint_current_wal_before_statement_rollback',
        ];
        $operations[] = [
            'op' => 'truncate',
            'path' => $databasePath,
            'bytes' => strlen($checkpointBytes),
            'reason' => 'trim_checkpointed_current_wal_before_statement_rollback',
        ];
        $operations[] = [
            'op' => 'write',
            'path' => $databasePath,
            'offset' => 0,
            'bytes' => strlen($statement['rolled_back_database_bytes']),
            'payload_key' => $statementPayloadKey,
            'reason' => 'restore_statement_subjournal_after_hot_journal_wal_current_source',
        ];
        $operations[] = [
            'op' => 'truncate',
            'path' => $databasePath,
            'bytes' => strlen($statement['rolled_back_database_bytes']),
            'reason' => 'trim_statement_recovered_current_source_before_next_statement',
        ];
        $operations[] = [
            'op' => 'write',
            'path' => $databasePath . '-wal',
            'offset' => 0,
            'bytes' => strlen($nextWalBytes),
            'payload_key' => $walPayloadKey,
            'reason' => 'restore_statement_rollback_wal_prefix_before_next_statement',
        ];
        $operations[] = [
            'op' => 'truncate',
            'path' => $databasePath . '-wal',
            'bytes' => strlen($nextWalBytes),
            'reason' => 'discard_statement_wal_frames_before_next_statement',
        ];
        $operations[] = [
            'op' => 'sync',
            'path' => $databasePath,
            'durable' => true,
            'reason' => 'sync_statement_current_source_after_hot_journal_wal_replay',
        ];

        $payloads[$statementPayloadKey] = $statement['rolled_back_database_bytes'];
        $payloads[$walPayloadKey] = $nextWalBytes;

        return [
            'status' => $hot['recovered'] ? 'hot_journal_wal_statement_current_source_recovered_statement-rollback' : 'hot_journal_wal_statement_current_source_skipped_statement-rollback',
            'reason' => $hot['recovered'] ? 'hot_journal_and_current_wal_precede_statement_rollback' : 'statement_rollback_uses_current_wal_without_hot_journal_recovery',
            'database_path' => $databasePath,
            'journal_path' => $journalPath,
            'wal_path' => $walPath,
            'savepoint' => $savepoint,
            'current_statement' => $currentStatementName,
            'next_statement' => $nextStatementName,
            'hot_recovered' => $hot['recovered'],
            'statement_database_bytes' => $statement['rolled_back_database_bytes'],
            'statement_wal_bytes' => $nextWalBytes,
            'statement_wal_bytes_length' => strlen($nextWalBytes),
            'checkpoint_database_bytes' => $checkpointBytes,
            'current_source' => [
                'journal_bytes_match' => true,
                'wal_bytes_match' => true,
                'journal_checksum_validated' => $journal->checksumsValidated,
                'wal_checksum_validated' => $wal->checksumsValidated,
                'journal_page_count' => $journal->pageCount(),
                'wal_frame_count' => $wal->frameCount(),
                'hot_journal_reason' => SQLiteRollbackJournal::hotJournalCandidate($journalBytes, $databaseReservedLock, $requiresSuperJournal, $superJournalExists)['reason'],
                'database_reserved_lock' => $databaseReservedLock,
                'requires_super_journal' => $requiresSuperJournal,
                'super_journal_exists' => $superJournalExists,
            ],
            'current_source_pages' => array_keys($currentStatementSourcePages),
            'current_source_prefixes' => $statement['current_source_prefixes'],
            'next_source_prefixes' => $statement['next_source_prefixes'],
            'rollback_to_frame' => $statement['rollback_to_wal_frame'],
            'next_wal_frame_index' => $statement['next_wal_frame_index'],
            'next_page_number' => $statement['next_page_number'],
            'next_commit_frame' => $statement['next_commit_frame'],
            'rollback_restored_page_numbers' => $statement['rollback_restored_page_numbers'],
            'rollback_discarded_wal_frames' => $statement['rollback_discarded_wal_frames'],
            'statement_journals_after_rollback' => $statement['statement_journals_after_rollback'],
            'statement_journals_after_next' => $statement['statement_journals_after_next'],
            'pending_page_numbers_after_next' => $statement['pending_page_numbers_after_next'],
            'pending_wal_frame_indexes_after_next' => $statement['pending_wal_frame_indexes_after_next'],
            'hot_journal' => $hot,
            'wal_recovery' => $walRecovery,
            'statement_recovery' => $statement,
            'operations' => $operations,
            'payloads' => $payloads,
            'dependencies' => array_values(array_unique(array_merge(
                $hot['recovered'] ? ['sqlite-rollback-journal-recovery'] : [],
                $walRecovery['dependencies'],
                $statement['dependencies'],
                [
                    'sqlite-wal-hot-journal-statement-statement-rollback',
                    'sqlite-statement-subjournal-after-hot-journal-wal-current-source',
                ]
            ))),
        ];
    }

    /**
     * @param list<int> $pageNumbers
     * @return array{status:string,reason:string,database_path:string,journal_path:string,wal_path:string,savepoint:string,hot_recovered:bool,journal_action:string,rollback_to_frame:int,original_frame_count:int,retained_frame_count:int,discarded_frame_count:int,current_wal_bytes:string,current_wal_bytes_length:int,current_reader_end_frame:int,next_reader_end_frame:int,current_reader:list<array<string,mixed>>,next_reader:list<array<string,mixed>>,current_reader_sources:list<string>,next_reader_sources:list<string>,current_reader_frame_indexes:list<int|null>,next_reader_frame_indexes:list<int|null>,current_reader_errors:list<string>,next_reader_errors:list<string>,images_match:bool,next_uses_checkpoint_database:bool,can_checkpoint:bool,checkpoint_database_page_count:int|null,discarded_valid_tail_frame_count:int,discarded_corrupt_tail_frame_count:int,operations:list<array<string,mixed>>,payloads:array<string,string>,hot_journal:array<string,mixed>,savepoint_truncation:array<string,mixed>,wal_recovery:array<string,mixed>,current_source:array<string,mixed>,dependencies:list<string>}
     */
    public static function replayCurrentSourceNext(
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
        if ($journalBytes === '') {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint current-source replay requires rollback-journal bytes');
        }
        if ($journal->toBytes() !== $journalBytes) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint current-source replay rollback journal bytes do not match the parsed journal');
        }
        if ($wal->toBytes() !== $walBytes) {
            throw new \InvalidArgumentException('SQLite WAL hot-journal savepoint current-source replay WAL bytes do not match the parsed WAL');
        }

        $journalCandidate = SQLiteRollbackJournal::hotJournalCandidate(
            $journalBytes,
            $databaseReservedLock,
            $requiresSuperJournal,
            $superJournalExists
        );
        $plan = self::replayCurrentNext(
            $journal,
            $databaseBytes,
            $journalBytes,
            $savepoints,
            $savepoint,
            $wal,
            $walBytes,
            $databasePath,
            $pageNumbers,
            $databaseReservedLock,
            $requiresSuperJournal,
            $superJournalExists
        );

        $plan['current_source'] = [
            'journal_bytes_match' => true,
            'wal_bytes_match' => true,
            'journal_checksum_validated' => $journal->checksumsValidated,
            'wal_checksum_validated' => $wal->checksumsValidated,
            'journal_page_count' => $journal->pageCount(),
            'wal_frame_count' => $wal->frameCount(),
            'hot_journal_reason' => $journalCandidate['reason'],
            'database_reserved_lock' => $databaseReservedLock,
            'requires_super_journal' => $requiresSuperJournal,
            'super_journal_exists' => $superJournalExists,
        ];
        $plan['dependencies'] = array_values(array_unique(array_merge(
            $plan['dependencies'],
            ['sqlite-wal-hot-journal-savepoint-current-source-next87']
        )));

        return $plan;
    }

    /**
     * @param list<int> $pageNumbers
     * @return array{status:string,reason:string,master_journal_path:string,master_cache:array<string,mixed>,replay:array<string,mixed>,next_master_member:bool,stale_current_member:bool,operations:list<array<string,mixed>>,payloads:array<string,string>,dependencies:list<string>}
     */
    public static function masterJournalCurrentSourceNext(
        string $masterJournalPath,
        ?string $currentMasterJournalBytes,
        ?string $nextMasterJournalBytes,
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
    ): array {
        if ($masterJournalPath === '') {
            throw new \InvalidArgumentException('SQLite WAL savepoint master-journal current-source replay requires a master-journal path');
        }
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite WAL savepoint master-journal current-source replay requires a database path');
        }

        $journalPath = $databasePath . '-journal';
        $cache = SQLitePagerMasterJournalCacheCurrentNextPlan::currentNext(
            $masterJournalPath,
            $currentMasterJournalBytes,
            $nextMasterJournalBytes,
            [[
                'database_path' => $databasePath,
                'journal_path' => $journalPath,
                'current_journal_bytes' => $journalBytes,
                'next_journal_bytes' => $journalBytes,
                'current_reserved_lock' => $databaseReservedLock,
                'next_reserved_lock' => $databaseReservedLock,
            ]]
        );

        $recheck = $cache['journal_rechecks'][$journalPath] ?? null;
        if (!is_array($recheck)) {
            throw new \LogicException("SQLite WAL savepoint master-journal current-source replay did not recheck {$journalPath}");
        }

        $nextMasterMember = (bool) ($recheck['next_member'] ?? false);
        $currentMasterMember = (bool) ($recheck['current_member'] ?? false);
        $replay = self::replayCurrentNext(
            $journal,
            $databaseBytes,
            $journalBytes,
            $savepoints,
            $savepoint,
            $wal,
            $walBytes,
            $databasePath,
            $pageNumbers,
            $databaseReservedLock,
            true,
            $nextMasterMember
        );

        $status = $replay['hot_recovered']
            ? 'master_journal_current_source_savepoint_wal_recovered'
            : 'master_journal_current_source_savepoint_wal_skipped';
        $reason = $nextMasterMember
            ? 'next_master_journal_member_allows_savepoint_wal_replay'
            : ($currentMasterMember ? 'stale_current_master_journal_member_rechecked_before_replay' : 'next_master_journal_missing_blocks_savepoint_wal_replay');

        return [
            'status' => $status,
            'reason' => $reason,
            'master_journal_path' => $masterJournalPath,
            'master_cache' => $cache,
            'replay' => $replay,
            'next_master_member' => $nextMasterMember,
            'stale_current_member' => $currentMasterMember && !$nextMasterMember,
            'operations' => array_values(array_merge($cache['operations'], $replay['operations'])),
            'payloads' => $replay['payloads'],
            'dependencies' => array_values(array_unique(array_merge(
                $cache['dependencies'],
                $replay['dependencies'],
                ['sqlite-wal-savepoint-master-journal-current-source-next82']
            ))),
        ];
    }

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
