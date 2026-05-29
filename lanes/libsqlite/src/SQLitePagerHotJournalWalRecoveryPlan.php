<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerHotJournalWalRecoveryPlan
{
    /**
     * @param list<array{database_path:string,database_bytes:string,journal:SQLiteRollbackJournal,journal_bytes:string,wal_bytes:string,page_numbers:list<int>,database_page_size?:int,reserved_lock?:bool}> $databases
     * @return array{status:string,reason:string,super_journal_path:string,super_journal_exists:bool,super_journal_members:list<string>,database_count:int,recovered_database_count:int,skipped_database_count:int,current_reader_sources:array<string,list<string>>,next_reader_sources:array<string,list<string>>,current_reader_frame_indexes:array<string,list<int|null>>,next_reader_frame_indexes:array<string,list<int|null>>,journal_actions:array<string,string>,super_journal_action:string,operations:list<array<string,mixed>>,databases:array<string,array<string,mixed>>,dependencies:list<string>}
     */
    public static function masterSuperJournalCurrentNext(
        string $superJournalPath,
        string $superJournalBytes,
        array $databases,
    ): array {
        if ($superJournalPath === '') {
            throw new \InvalidArgumentException('SQLite pager hot-journal master/super current-next recovery requires a super-journal path');
        }
        if ($databases === []) {
            throw new \InvalidArgumentException('SQLite pager hot-journal master/super current-next recovery requires at least one database');
        }

        $members = self::superJournalMembers($superJournalBytes);
        $memberSet = array_fill_keys($members, true);
        $plans = [];
        $operations = [];
        $currentSources = [];
        $nextSources = [];
        $currentFrames = [];
        $nextFrames = [];
        $journalActions = [];
        $recovered = 0;
        $skipped = 0;

        foreach ($databases as $index => $database) {
            $databasePath = isset($database['database_path']) ? (string) $database['database_path'] : '';
            if ($databasePath === '') {
                throw new \InvalidArgumentException("SQLite pager hot-journal master/super database {$index} requires a database path");
            }
            if (isset($plans[$databasePath])) {
                throw new \InvalidArgumentException("SQLite pager hot-journal master/super duplicate database path: {$databasePath}");
            }
            if (!isset($database['journal']) || !$database['journal'] instanceof SQLiteRollbackJournal) {
                throw new \InvalidArgumentException("SQLite pager hot-journal master/super database {$databasePath} requires a parsed rollback journal");
            }
            $pageNumbers = $database['page_numbers'] ?? [];
            if (!is_array($pageNumbers) || $pageNumbers === []) {
                throw new \InvalidArgumentException("SQLite pager hot-journal master/super database {$databasePath} requires page numbers");
            }

            $journalPath = $databasePath . '-journal';
            $requiresSuper = true;
            $superExistsForJournal = isset($memberSet[$journalPath]);
            $visibility = self::currentNextVisibility(
                $database['journal'],
                (string) ($database['database_bytes'] ?? ''),
                (string) ($database['journal_bytes'] ?? ''),
                (string) ($database['wal_bytes'] ?? ''),
                $databasePath,
                array_values($pageNumbers),
                isset($database['database_page_size']) ? (int) $database['database_page_size'] : null,
                (bool) ($database['reserved_lock'] ?? false),
                $requiresSuper,
                $superExistsForJournal,
            );

            if ($visibility['hot_recovered']) {
                $recovered++;
            } else {
                $skipped++;
            }
            $plans[$databasePath] = $visibility;
            $currentSources[$databasePath] = $visibility['current_reader_sources'];
            $nextSources[$databasePath] = $visibility['next_reader_sources'];
            $currentFrames[$databasePath] = $visibility['current_reader_frame_indexes'];
            $nextFrames[$databasePath] = $visibility['next_reader_frame_indexes'];
            $journalActions[$journalPath] = $visibility['recovery']['journal_action'];
            foreach ($visibility['recovery']['operations'] as $operation) {
                $operations[] = $operation + ['database_path' => $databasePath];
            }
        }

        $allMembersCleared = $members !== [];
        foreach ($members as $member) {
            if (($journalActions[$member] ?? null) !== 'delete_journal_after_recovery') {
                $allMembersCleared = false;
                break;
            }
        }

        $superAction = $allMembersCleared ? 'delete_super_journal_after_named_hot_journals' : 'preserve_super_journal_until_named_journals_clear';
        if ($allMembersCleared) {
            $operations[] = [
                'op' => 'delete',
                'path' => $superJournalPath,
                'durable' => false,
                'reason' => $superAction,
            ];
            $operations[] = [
                'op' => 'sync_directory',
                'path' => dirname($superJournalPath),
                'durable' => true,
                'reason' => 'persist_super_journal_recovery_deletion',
            ];
        }

        return [
            'status' => $recovered > 0 ? 'super_journal_hot_recovery_current_next' : 'super_journal_no_hot_recovery_current_next',
            'reason' => 'master_super_journal_members_gate_hot_journal_recovery',
            'super_journal_path' => $superJournalPath,
            'super_journal_exists' => $members !== [],
            'super_journal_members' => $members,
            'database_count' => count($databases),
            'recovered_database_count' => $recovered,
            'skipped_database_count' => $skipped,
            'current_reader_sources' => $currentSources,
            'next_reader_sources' => $nextSources,
            'current_reader_frame_indexes' => $currentFrames,
            'next_reader_frame_indexes' => $nextFrames,
            'journal_actions' => $journalActions,
            'super_journal_action' => $superAction,
            'operations' => $operations,
            'databases' => $plans,
            'dependencies' => [
                'sqlite-pager-hot-journal-master-super-current-next73',
                'sqlite-pager-hot-journal-wal-current-next-visibility',
                'sqlite-rollback-journal-recovery',
                'sqlite-wal-transaction-recovery-boundary',
            ],
        ];
    }

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
     * @param list<int> $pageNumbers
     * @return array{status:string,reason:string,database_path:string,journal_path:string,wal_path:string,savepoint:string,mode:string,hot_recovered:bool,journal_action:string,wal_recovery_status:string,base_database_bytes:int,valid_wal_bytes_length:int,before_reader_end_frame:int,current_reader_end_frame:int,next_reader_end_frame:int,retained_frame_count:int,discarded_frame_count:int,wal_action:string,checkpoint_busy:bool,before_reader:list<array<string,mixed>>,current_reader:list<array<string,mixed>>,next_reader:list<array<string,mixed>>,before_reader_sources:list<string|null>,current_reader_sources:list<string|null>,next_reader_sources:list<string|null>,before_reader_frame_indexes:list<int|null>,current_reader_frame_indexes:list<int|null>,next_reader_frame_indexes:list<int|null>,before_to_current_images_match:bool,current_to_next_images_match:bool,next_uses_checkpoint_database:bool,next_uses_preserved_wal:bool,operations:list<array<string,mixed>>,hot_recovery:array<string,mixed>,savepoint_checkpoint:array<string,mixed>,dependencies:list<string>}
     */
    public static function savepointWalRecoveryCurrentSourceNext(
        SQLiteRollbackJournal $journal,
        string $databaseBytes,
        string $journalBytes,
        string $walBytes,
        string $databasePath,
        SQLiteSavepointStack $savepoints,
        string $savepoint,
        array $pageNumbers,
        string $mode = 'restart',
        ?int $databasePageSize = null,
        bool $databaseReservedLock = false,
        bool $requiresSuperJournal = false,
        ?bool $superJournalExists = null,
    ): array {
        if ($savepoint === '') {
            throw new \InvalidArgumentException('SQLite pager hot-journal WAL savepoint current-source requires a savepoint name');
        }
        if ($pageNumbers === []) {
            throw new \InvalidArgumentException('SQLite pager hot-journal WAL savepoint current-source requires page numbers');
        }

        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['restart', 'truncate'], true)) {
            throw new \InvalidArgumentException('SQLite pager hot-journal WAL savepoint current-source requires restart or truncate mode');
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
        $baseDatabaseBytes = $recovery['payloads'][$databasePath . '#hot-journal'] ?? $databaseBytes;
        if (!is_string($baseDatabaseBytes)) {
            throw new \UnexpectedValueException('SQLite hot-journal recovery did not expose a database byte image');
        }

        $validWalBytes = (string) $recovery['wal_recovery']['valid_wal_bytes'];
        $validWal = SQLiteWal::parse($validWalBytes, $databasePageSize, true);
        $checkpoint = SQLiteWalSavepointCheckpointPlan::afterRollbackTo(
            $savepoints,
            $savepoint,
            $validWal,
            $validWalBytes,
            $baseDatabaseBytes,
            $mode
        );
        $currentWal = SQLiteWal::parse($checkpoint['current_wal_bytes'], $validWal->header->pageSize, true);
        $durable = $checkpoint['current_durable'];
        $nextWal = $durable['wal_bytes'] === ''
            ? null
            : SQLiteWal::parse($durable['wal_bytes'], $validWal->header->pageSize, true);

        $beforeEndFrame = $validWal->frameCount();
        $currentEndFrame = $currentWal->frameCount();
        $nextEndFrame = $nextWal?->frameCount() ?? 0;
        $pageSize = self::pageSize($validWal, $databasePageSize, $baseDatabaseBytes);
        $before = [];
        $current = [];
        $next = [];
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber)) {
                throw new \InvalidArgumentException('SQLite pager hot-journal WAL savepoint current-source page numbers must be integers');
            }

            $before[] = self::safeReaderVisibility($validWal, $baseDatabaseBytes, $pageNumber, $beforeEndFrame);
            $current[] = self::safeReaderVisibility($currentWal, $baseDatabaseBytes, $pageNumber, $currentEndFrame);
            $next[] = $nextWal === null
                ? self::databaseVisibility($durable['database_bytes'], $pageSize, $pageNumber)
                : self::safeReaderVisibility($nextWal, $durable['database_bytes'], $pageNumber, $nextEndFrame);
        }

        $beforeImages = self::visibilityImages($before);
        $currentImages = self::visibilityImages($current);
        $nextImages = self::visibilityImages($next);

        return [
            'status' => $checkpoint['busy'] ? 'busy' : 'ready',
            'reason' => 'hot_journal_recovery_then_wal_savepoint_current_source_checkpoint',
            'database_path' => $databasePath,
            'journal_path' => $recovery['journal_path'],
            'wal_path' => $recovery['wal_path'],
            'savepoint' => $savepoint,
            'mode' => $mode,
            'hot_recovered' => $recovery['hot_recovered'],
            'journal_action' => $recovery['journal_action'],
            'wal_recovery_status' => $recovery['wal_status'],
            'base_database_bytes' => strlen($baseDatabaseBytes),
            'valid_wal_bytes_length' => strlen($validWalBytes),
            'before_reader_end_frame' => $beforeEndFrame,
            'current_reader_end_frame' => $currentEndFrame,
            'next_reader_end_frame' => $nextEndFrame,
            'retained_frame_count' => $checkpoint['retained_frame_count'],
            'discarded_frame_count' => $checkpoint['discarded_frame_count'],
            'wal_action' => $durable['wal_action'],
            'checkpoint_busy' => $checkpoint['busy'],
            'before_reader' => $before,
            'current_reader' => $current,
            'next_reader' => $next,
            'before_reader_sources' => self::visibilityColumn($before, 'source'),
            'current_reader_sources' => self::visibilityColumn($current, 'source'),
            'next_reader_sources' => self::visibilityColumn($next, 'source'),
            'before_reader_frame_indexes' => self::visibilityColumn($before, 'frame_index'),
            'current_reader_frame_indexes' => self::visibilityColumn($current, 'frame_index'),
            'next_reader_frame_indexes' => self::visibilityColumn($next, 'frame_index'),
            'before_to_current_images_match' => $beforeImages === $currentImages,
            'current_to_next_images_match' => $currentImages === $nextImages,
            'next_uses_checkpoint_database' => !in_array('wal', self::visibilityColumn($next, 'source'), true),
            'next_uses_preserved_wal' => $durable['wal_action'] === 'preserve_wal',
            'operations' => array_values(array_merge($recovery['operations'], [[
                'op' => 'checkpoint',
                'path' => $databasePath,
                'mode' => $mode,
                'reason' => 'checkpoint_retained_wal_prefix_after_savepoint_rollback',
            ]])),
            'hot_recovery' => $recovery,
            'savepoint_checkpoint' => $checkpoint,
            'dependencies' => array_values(array_unique(array_merge(
                $recovery['dependencies'],
                $checkpoint['dependencies'],
                ['sqlite-pager-hot-journal-wal-savepoint-current-source-next85']
            ))),
        ];
    }

    /**
     * @param array<int,string> $currentPageImages
     * @return array{status:string,reason:string,database_path:string,journal_path:string,wal_path:string,current_statement:string,next_statement:string,savepoint:string,page_size:int,hot_recovered:bool,journal_action:string,wal_recovery_status:string,current_source_verified:bool,current_source_page_numbers:list<int>,current_reader:list<array<string,mixed>>,current_reader_sources:list<string|null>,current_reader_frame_indexes:list<int|null>,current_database_bytes:int,rolled_back_database_bytes:int,rollback_to_wal_frame:int,next_wal_frame_index:int,next_page_number:int,next_commit_frame:bool,rollback_restored_page_numbers:list<int>,rollback_discarded_wal_frames:list<array{frame_index:int,page_number:int,commit_frame:bool}>,statement_journals_after_rollback:list<array{name:string,savepoint:string,wal_start_frame:int,page_numbers:list<int>,wal_frame_indexes:list<int>}>,statement_journals_after_next:list<array{name:string,savepoint:string,wal_start_frame:int,page_numbers:list<int>,wal_frame_indexes:list<int>}>,pending_page_numbers_after_rollback:list<int>,pending_wal_frame_indexes_after_rollback:list<int>,pending_page_numbers_after_next:list<int>,pending_wal_frame_indexes_after_next:list<int>,current_source_prefixes:array<int,string>,next_source_prefixes:array<int,string>,operations:list<array<string,mixed>>,hot_recovery:array<string,mixed>,statement_recovery:array<string,mixed>,dependencies:list<string>}
     */
    public static function statementWalRecoveryCurrentSourceNext(
        SQLiteRollbackJournal $journal,
        string $databaseBytes,
        string $journalBytes,
        string $walBytes,
        string $databasePath,
        SQLiteSavepointStack $savepoints,
        string $currentStatementName,
        string $nextStatementName,
        array $currentPageImages,
        int $nextPageNumber,
        string $nextBeforeImage,
        ?int $databasePageSize = null,
        bool $nextCommitFrame = false,
        bool $databaseReservedLock = false,
        bool $requiresSuperJournal = false,
        ?bool $superJournalExists = null,
    ): array {
        if ($currentStatementName === '') {
            throw new \InvalidArgumentException('SQLite pager hot-journal statement current-source requires a current statement name');
        }
        if ($nextStatementName === '') {
            throw new \InvalidArgumentException('SQLite pager hot-journal statement current-source requires a next statement name');
        }
        if ($currentPageImages === []) {
            throw new \InvalidArgumentException('SQLite pager hot-journal statement current-source requires page images');
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
        $baseDatabaseBytes = $recovery['payloads'][$databasePath . '#hot-journal'] ?? $databaseBytes;
        if (!is_string($baseDatabaseBytes)) {
            throw new \UnexpectedValueException('SQLite hot-journal recovery did not expose a database byte image');
        }

        $validWalBytes = (string) $recovery['wal_recovery']['valid_wal_bytes'];
        $validWal = SQLiteWal::parse($validWalBytes, $databasePageSize, true);
        $pageSize = self::pageSize($validWal, $databasePageSize, $baseDatabaseBytes);
        $currentEndFrame = $validWal->frameCount();
        $pageNumbers = array_keys($currentPageImages);
        sort($pageNumbers, SORT_NUMERIC);

        $currentReader = [];
        $currentDatabaseBytes = $baseDatabaseBytes;
        foreach ($pageNumbers as $pageNumber) {
            if (!is_int($pageNumber) || $pageNumber < 1) {
                throw new \InvalidArgumentException('SQLite pager hot-journal statement current-source page numbers are one-based');
            }
            $expectedImage = $currentPageImages[$pageNumber];
            if (!is_string($expectedImage) || strlen($expectedImage) !== $pageSize) {
                throw new \InvalidArgumentException("SQLite pager hot-journal statement current-source image for page {$pageNumber} must match the page size");
            }

            $visibility = self::safeReaderVisibility($validWal, $baseDatabaseBytes, $pageNumber, $currentEndFrame);
            if (($visibility['image'] ?? null) !== $expectedImage) {
                throw new \RuntimeException("SQLite pager hot-journal statement current-source page {$pageNumber} is stale");
            }
            $currentReader[] = $visibility;
            $offset = ($pageNumber - 1) * $pageSize;
            if ($offset + $pageSize > strlen($currentDatabaseBytes)) {
                throw new \InvalidArgumentException("SQLite pager hot-journal statement current-source page {$pageNumber} is outside the database image");
            }
            $currentDatabaseBytes = substr_replace($currentDatabaseBytes, $expectedImage, $offset, $pageSize);
        }

        $statementRecovery = $savepoints->rollbackStatementCurrentSourceAndBeginStatementJournal(
            $currentStatementName,
            $nextStatementName,
            $currentDatabaseBytes,
            $currentPageImages,
            $nextPageNumber,
            $nextBeforeImage,
            $pageSize,
            $nextCommitFrame
        );

        return [
            'status' => 'hot_journal_statement_current_source_next',
            'reason' => 'hot_journal_recovery_then_statement_subjournal_current_source_retry',
            'database_path' => $databasePath,
            'journal_path' => $recovery['journal_path'],
            'wal_path' => $recovery['wal_path'],
            'current_statement' => $currentStatementName,
            'next_statement' => $nextStatementName,
            'savepoint' => $statementRecovery['savepoint'],
            'page_size' => $pageSize,
            'hot_recovered' => $recovery['hot_recovered'],
            'journal_action' => $recovery['journal_action'],
            'wal_recovery_status' => $recovery['wal_status'],
            'current_source_verified' => true,
            'current_source_page_numbers' => $statementRecovery['current_source_page_numbers'],
            'current_reader' => $currentReader,
            'current_reader_sources' => self::visibilityColumn($currentReader, 'source'),
            'current_reader_frame_indexes' => self::visibilityColumn($currentReader, 'frame_index'),
            'current_database_bytes' => strlen($currentDatabaseBytes),
            'rolled_back_database_bytes' => strlen($statementRecovery['rolled_back_database_bytes']),
            'rollback_to_wal_frame' => $statementRecovery['rollback_to_wal_frame'],
            'next_wal_frame_index' => $statementRecovery['next_wal_frame_index'],
            'next_page_number' => $statementRecovery['next_page_number'],
            'next_commit_frame' => $statementRecovery['next_commit_frame'],
            'rollback_restored_page_numbers' => $statementRecovery['rollback_restored_page_numbers'],
            'rollback_discarded_wal_frames' => $statementRecovery['rollback_discarded_wal_frames'],
            'statement_journals_after_rollback' => $statementRecovery['statement_journals_after_rollback'],
            'statement_journals_after_next' => $statementRecovery['statement_journals_after_next'],
            'pending_page_numbers_after_rollback' => $statementRecovery['pending_page_numbers_after_rollback'],
            'pending_wal_frame_indexes_after_rollback' => $statementRecovery['pending_wal_frame_indexes_after_rollback'],
            'pending_page_numbers_after_next' => $statementRecovery['pending_page_numbers_after_next'],
            'pending_wal_frame_indexes_after_next' => $statementRecovery['pending_wal_frame_indexes_after_next'],
            'current_source_prefixes' => $statementRecovery['current_source_prefixes'],
            'next_source_prefixes' => $statementRecovery['next_source_prefixes'],
            'operations' => array_values(array_merge($recovery['operations'], [[
                'op' => 'statement_rollback',
                'path' => $databasePath,
                'statement' => $currentStatementName,
                'next_statement' => $nextStatementName,
                'reason' => 'restore_statement_subjournal_after_hot_journal_recovery',
            ]])),
            'hot_recovery' => $recovery,
            'statement_recovery' => $statementRecovery,
            'dependencies' => array_values(array_unique(array_merge(
                $recovery['dependencies'],
                $statementRecovery['dependencies'],
                ['sqlite-pager-hot-journal-savepoint-statement-current-source-next93']
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
     * @return list<string>
     */
    private static function superJournalMembers(string $superJournalBytes): array
    {
        $members = [];
        foreach (preg_split('/\r?\n/', $superJournalBytes) ?: [] as $line) {
            $member = trim($line);
            if ($member === '') {
                continue;
            }
            if (isset($members[$member])) {
                continue;
            }
            $members[$member] = $member;
        }

        return array_values($members);
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
