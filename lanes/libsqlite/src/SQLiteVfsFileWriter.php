<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVfsFileWriter
{
    public function __construct(
        private readonly string $rootDirectory,
        private readonly bool $readOnly = false,
        private readonly bool $immutable = false,
    ) {
        if ($rootDirectory === '') {
            throw new \InvalidArgumentException('SQLite VFS file writer requires a root directory');
        }
    }

    /**
     * @return array{status:string,root:string,applied:int,bytes_written:int,bytes_truncated:int,files_deleted:int,durable_syncs:int,directory_syncs:int,operations:list<array<string, mixed>>,dependencies:list<string>}
     */
    public function applyWalCheckpoint(SQLiteWal $wal, string $databaseBytes, string $databasePath, string $mode = 'passive', ?int $readerEndFrame = null): array
    {
        $plan = SQLiteWalFileWritePlan::checkpoint($wal, $databaseBytes, $databasePath, $mode, $readerEndFrame, $this->readOnly, $this->immutable);
        $result = $wal->durableCheckpointResult($databaseBytes, $mode, $readerEndFrame);
        $payloads = [
            $plan['database_path'] => $result['database_bytes'],
            $plan['wal_path'] => $result['wal_bytes'],
        ];

        return $this->applyOperations($plan['operations'], $payloads, $plan['dependencies']);
    }

    /**
     * @return array{status:string,root:string,applied:int,bytes_written:int,bytes_truncated:int,files_deleted:int,durable_syncs:int,directory_syncs:int,operations:list<array<string, mixed>>,dependencies:list<string>,transaction:array<string, mixed>,locks_released:bool,atomic:bool}
     */
    public function applyPagerCheckpointTransaction(
        SQLiteLockCoordinator $locks,
        string $connection,
        SQLiteWal $wal,
        string $databaseBytes,
        string $databasePath,
        string $mode = 'passive',
        ?int $readerEndFrame = null,
        ?SQLiteBusyHandler $busyHandler = null
    ): array {
        $plan = SQLitePagerCheckpointTransactionPlan::plan(
            $locks,
            $connection,
            $wal,
            $databaseBytes,
            $databasePath,
            $mode,
            $readerEndFrame,
            $busyHandler,
            $this->readOnly,
            $this->immutable
        );
        if (!$plan['can_checkpoint'] || $plan['write_plan'] === null) {
            return [
                'status' => $plan['status'],
                'root' => $this->rootDirectory,
                'applied' => 0,
                'bytes_written' => 0,
                'bytes_truncated' => 0,
                'files_deleted' => 0,
                'durable_syncs' => 0,
                'directory_syncs' => 0,
                'operations' => [],
                'dependencies' => array_values(array_unique(array_merge($plan['dependencies'], ['sqlite-pager-atomic-checkpoint-apply']))),
                'transaction' => $plan,
                'locks_released' => false,
                'atomic' => true,
            ];
        }

        foreach ($plan['lock_sequence'] as $lockPlan) {
            $locks->set($connection, (string) $lockPlan['requested']);
        }

        try {
            $result = $wal->durableCheckpointResult($databaseBytes, $mode, $readerEndFrame);
            $payloads = [
                $plan['write_plan']['database_path'] => $result['database_bytes'],
                $plan['write_plan']['wal_path'] => $result['wal_bytes'],
            ];
            $applied = $this->applyAtomicOperations(
                $plan['write_plan']['operations'],
                $payloads,
                array_values(array_unique(array_merge($plan['dependencies'], ['sqlite-pager-atomic-checkpoint-apply'])))
            );
        } finally {
            $locks->release($connection);
        }

        $applied['transaction'] = $plan;
        $applied['locks_released'] = true;
        $applied['atomic'] = true;

        return $applied;
    }

    /**
     * @return array{status:string,root:string,applied:int,bytes_written:int,bytes_truncated:int,files_deleted:int,durable_syncs:int,directory_syncs:int,operations:list<array<string, mixed>>,dependencies:list<string>,recovery:array<string, mixed>}
     */
    public function applyWalRecovery(SQLiteWal $wal, string $databaseBytes, string $databasePath): array
    {
        $plan = SQLiteWalRecoveryPlan::recover($wal, $databaseBytes, $databasePath, $this->readOnly, $this->immutable);
        if ($plan['status'] === 'skipped') {
            return [
                'status' => 'skipped',
                'root' => $this->rootDirectory,
                'applied' => 0,
                'bytes_written' => 0,
                'bytes_truncated' => 0,
                'files_deleted' => 0,
                'durable_syncs' => 0,
                'directory_syncs' => 0,
                'operations' => [],
                'dependencies' => array_values(array_unique(array_merge($plan['dependencies'], ['vfs-file-handle-write-application']))),
                'recovery' => $plan,
            ];
        }

        $applied = $this->applyOperations(
            $plan['operations'],
            SQLiteWalRecoveryPlan::payloads($wal, $databaseBytes, $databasePath),
            $plan['dependencies']
        );
        $applied['recovery'] = $plan;

        return $applied;
    }

    /**
     * @return array{status:string,root:string,applied:int,bytes_written:int,bytes_truncated:int,files_deleted:int,durable_syncs:int,directory_syncs:int,operations:list<array<string, mixed>>,dependencies:list<string>,recovery:array<string, mixed>,atomic:bool}
     */
    public function applyWalChecksumRecoveryBoundary(
        string $walBytes,
        string $databaseBytes,
        string $databasePath,
        ?int $databasePageSize = null
    ): array {
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite WAL checksum recovery requires a database path');
        }

        $boundary = SQLiteWal::checksumRecoveryBoundary($walBytes, $databaseBytes, $databasePageSize);
        $walPath = $databasePath . '-wal';
        $operations = [];
        $payloads = [];

        if ($boundary['checkpoint_database_bytes'] !== null) {
            $operations[] = [
                'op' => 'write',
                'path' => $databasePath,
                'offset' => 0,
                'bytes' => strlen($boundary['checkpoint_database_bytes']),
                'durable' => false,
                'reason' => 'checkpoint_valid_wal_recovery_prefix',
            ];
            $operations[] = [
                'op' => 'truncate',
                'path' => $databasePath,
                'bytes' => strlen($boundary['checkpoint_database_bytes']),
                'durable' => false,
                'reason' => 'trim_checkpointed_recovery_database_image',
            ];
            $operations[] = [
                'op' => 'sync',
                'path' => $databasePath,
                'durable' => true,
                'reason' => 'sync_checkpointed_recovery_database',
            ];
            $payloads[$databasePath] = $boundary['checkpoint_database_bytes'];
        }

        $operations[] = [
            'op' => 'write',
            'path' => $walPath,
            'offset' => 0,
            'bytes' => strlen($boundary['valid_wal_bytes']),
            'durable' => false,
            'reason' => 'restore_valid_wal_recovery_prefix',
        ];
        $operations[] = [
            'op' => 'truncate',
            'path' => $walPath,
            'bytes' => strlen($boundary['valid_wal_bytes']),
            'durable' => false,
            'reason' => 'discard_corrupt_wal_tail',
        ];
        $operations[] = [
            'op' => 'sync',
            'path' => $walPath,
            'durable' => true,
            'reason' => 'sync_recovered_wal_prefix',
        ];
        $operations[] = [
            'op' => 'sync_directory',
            'path' => dirname($databasePath),
            'durable' => true,
            'reason' => 'persist_wal_recovery_boundary_sidecars',
        ];
        $payloads[$walPath] = $boundary['valid_wal_bytes'];

        $applied = $this->applyAtomicOperations(
            $operations,
            $payloads,
            array_values(array_unique(array_merge($boundary['dependencies'], ['sqlite-wal-checksum-boundary-vfs-apply'])))
        );
        $applied['recovery'] = $boundary;
        $applied['atomic'] = true;

        return $applied;
    }

    /**
     * @param list<array{pages:array<int,string>,database_page_count?:int|null,commit?:bool}> $transactions
     * @return array{status:string,root:string,applied:int,bytes_written:int,bytes_truncated:int,files_deleted:int,durable_syncs:int,directory_syncs:int,operations:list<array<string, mixed>>,dependencies:list<string>,append:array<string, mixed>}
     */
    public function applyWalAppendTransactions(
        SQLiteWal $wal,
        string $databasePath,
        array $transactions,
        bool $syncWal = true,
        bool $syncDirectory = true,
    ): array {
        $plan = SQLiteWalAppendPlan::appendTransactions($wal, $databasePath, $transactions, $syncWal, $syncDirectory);
        $applied = $this->applyOperations(
            $plan['operations'],
            [$plan['wal_path'] => $plan['append_bytes']],
            $plan['dependencies']
        );
        $applied['append'] = $plan;

        return $applied;
    }

    /**
     * @return array{status:string,root:string,applied:int,bytes_written:int,bytes_truncated:int,files_deleted:int,durable_syncs:int,directory_syncs:int,operations:list<array<string, mixed>>,dependencies:list<string>,recovery:array<string, mixed>}
     */
    public function applyHotRollbackJournal(
        SQLiteRollbackJournal $journal,
        string $databaseBytes,
        string $journalBytes,
        string $databasePath,
        bool $databaseReservedLock = false,
        bool $requiresSuperJournal = false,
        ?bool $superJournalExists = null,
    ): array {
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite rollback journal VFS recovery requires a database path');
        }

        $result = $journal->hotJournalRecoveryResult($databaseBytes, $journalBytes, $databaseReservedLock, $requiresSuperJournal, $superJournalExists);
        if (!$result['recovered']) {
            return [
                'status' => 'skipped',
                'root' => $this->rootDirectory,
                'applied' => 0,
                'bytes_written' => 0,
                'bytes_truncated' => 0,
                'files_deleted' => 0,
                'durable_syncs' => 0,
                'directory_syncs' => 0,
                'operations' => [],
                'dependencies' => ['sqlite-rollback-journal-recovery', 'vfs-file-handle-write-application'],
                'recovery' => $result,
            ];
        }

        $journalPath = $databasePath . '-journal';
        $operations = [
            [
                'op' => 'write',
                'path' => $databasePath,
                'offset' => 0,
                'bytes' => strlen($result['database_bytes']),
                'durable' => false,
                'reason' => 'restore_database_pages_from_hot_journal',
            ],
            [
                'op' => 'truncate',
                'path' => $databasePath,
                'bytes' => strlen($result['database_bytes']),
                'durable' => false,
                'reason' => 'truncate_database_to_pretransaction_size',
            ],
            [
                'op' => 'sync',
                'path' => $databasePath,
                'durable' => true,
                'reason' => 'sync_rollback_recovered_database',
            ],
            [
                'op' => 'delete',
                'path' => $journalPath,
                'durable' => false,
                'reason' => 'delete_hot_rollback_journal',
            ],
            [
                'op' => 'sync_directory',
                'path' => dirname($databasePath),
                'durable' => true,
                'reason' => 'persist_rollback_journal_deletion',
            ],
        ];

        $applied = $this->applyOperations(
            $operations,
            [$databasePath => $result['database_bytes']],
            ['sqlite-rollback-journal-recovery', 'hot-journal-delete', 'vfs-file-write-coordination']
        );
        $applied['recovery'] = $result;

        return $applied;
    }

    /**
     * @return array{status:string,root:string,applied:int,bytes_written:int,bytes_truncated:int,files_deleted:int,durable_syncs:int,directory_syncs:int,operations:list<array<string, mixed>>,dependencies:list<string>,recovery:array<string, mixed>,atomic:bool}
     */
    public function applyHotJournalWalRecovery(
        SQLiteRollbackJournal $journal,
        string $databaseBytes,
        string $journalBytes,
        string $walBytes,
        string $databasePath,
        ?int $databasePageSize = null,
        bool $databaseReservedLock = false,
        bool $requiresSuperJournal = false,
        ?bool $superJournalExists = null,
    ): array {
        $plan = SQLitePagerHotJournalWalRecoveryPlan::recover(
            $journal,
            $databaseBytes,
            $journalBytes,
            $walBytes,
            $databasePath,
            $databasePageSize,
            $databaseReservedLock,
            $requiresSuperJournal,
            $superJournalExists,
            $this->readOnly,
            $this->immutable
        );

        $applied = $this->applyAtomicOperations(
            $plan['operations'],
            $plan['payloads'],
            $plan['dependencies']
        );
        $applied['recovery'] = $plan;
        $applied['atomic'] = true;

        return $applied;
    }

    /**
     * @param list<array{database_path:string,database_bytes:string,journal_bytes:string,journal?:SQLiteRollbackJournal,reserved_lock?:bool}> $databases
     * @return array{status:string,root:string,applied:int,bytes_written:int,bytes_truncated:int,files_deleted:int,durable_syncs:int,directory_syncs:int,operations:list<array<string, mixed>>,dependencies:list<string>,recovery:array<string, mixed>,atomic:bool}
     */
    public function applyHotJournalSuperRecovery(
        string $superJournalPath,
        ?string $superJournalBytes,
        array $databases,
        int $pageSize,
    ): array {
        $plan = SQLitePagerHotJournalSuperCurrentNextPlan::currentNext(
            $superJournalPath,
            $superJournalBytes,
            $databases,
            $pageSize,
            $this->readOnly,
            $this->immutable
        );

        if ($plan['recovered_count'] === 0) {
            return [
                'status' => 'skipped',
                'root' => $this->rootDirectory,
                'applied' => 0,
                'bytes_written' => 0,
                'bytes_truncated' => 0,
                'files_deleted' => 0,
                'durable_syncs' => 0,
                'directory_syncs' => 0,
                'operations' => [],
                'dependencies' => $plan['dependencies'],
                'recovery' => $plan,
                'atomic' => true,
            ];
        }

        $applied = $this->applyAtomicOperations($plan['operations'], $plan['payloads'], $plan['dependencies']);
        $applied['recovery'] = $plan;
        $applied['atomic'] = true;

        return $applied;
    }

    /**
     * @param list<array{database_path:string,stale_database_bytes?:string,stale_journal_bytes?:string,reserved_lock?:bool}> $databases
     * @return array{status:string,root:string,applied:int,bytes_written:int,bytes_truncated:int,files_deleted:int,durable_syncs:int,directory_syncs:int,operations:list<array<string, mixed>>,dependencies:list<string>,recovery:array<string, mixed>,atomic:bool,current_source:array{master_journal_path:string,master_journal_exists:bool,database_paths:list<string>,database_bytes:array<string,int>,journal_bytes:array<string,int>}}
     */
    public function applyMasterJournalHotRollbackCurrentSource89(
        string $masterJournalPath,
        array $databases,
        int $pageSize,
    ): array {
        if ($masterJournalPath === '') {
            throw new \InvalidArgumentException('SQLite pager current-source master hot rollback requires a master-journal path');
        }
        if ($databases === []) {
            throw new \InvalidArgumentException('SQLite pager current-source master hot rollback requires at least one attached database');
        }

        $masterLocalPath = $this->localPath($masterJournalPath);
        $masterJournalBytes = is_file($masterLocalPath) ? (string) file_get_contents($masterLocalPath) : null;
        $hydrated = [];
        $sourceDatabasePaths = [];
        $sourceDatabaseBytes = [];
        $sourceJournalBytes = [];

        foreach ($databases as $index => $database) {
            $databasePath = isset($database['database_path']) ? (string) $database['database_path'] : '';
            if ($databasePath === '') {
                throw new \InvalidArgumentException("SQLite pager current-source master hot rollback database {$index} requires a database path");
            }

            $databaseLocalPath = $this->localPath($databasePath);
            $journalLocalPath = $this->localPath($databasePath . '-journal');
            if (!is_file($databaseLocalPath)) {
                throw new \RuntimeException("SQLite pager current-source master hot rollback database is missing: {$databasePath}");
            }
            if (!is_file($journalLocalPath)) {
                throw new \RuntimeException("SQLite pager current-source master hot rollback journal is missing: {$databasePath}-journal");
            }

            $databaseBytes = (string) file_get_contents($databaseLocalPath);
            $journalBytes = (string) file_get_contents($journalLocalPath);
            $hydrated[] = array_merge($database, [
                'current_database_bytes' => $databaseBytes,
                'current_journal_bytes' => $journalBytes,
            ]);
            $sourceDatabasePaths[] = $databasePath;
            $sourceDatabaseBytes[$databasePath] = strlen($databaseBytes);
            $sourceJournalBytes[$databasePath . '-journal'] = strlen($journalBytes);
        }

        $plan = SQLitePagerMasterJournalHotRollbackCurrentSourceNext89Plan::currentSourceNext(
            $masterJournalPath,
            $masterJournalBytes,
            $hydrated,
            $pageSize,
            $this->readOnly,
            $this->immutable
        );
        $source = [
            'master_journal_path' => $masterJournalPath,
            'master_journal_exists' => $masterJournalBytes !== null,
            'database_paths' => $sourceDatabasePaths,
            'database_bytes' => $sourceDatabaseBytes,
            'journal_bytes' => $sourceJournalBytes,
        ];

        if ($plan['recovered_database_count'] === 0) {
            return [
                'status' => 'skipped',
                'root' => $this->rootDirectory,
                'applied' => 0,
                'bytes_written' => 0,
                'bytes_truncated' => 0,
                'files_deleted' => 0,
                'durable_syncs' => 0,
                'directory_syncs' => 0,
                'operations' => [],
                'dependencies' => $plan['dependencies'],
                'recovery' => $plan,
                'atomic' => true,
                'current_source' => $source,
            ];
        }

        $applied = $this->applyAtomicOperations($plan['operations'], $plan['payloads'], $plan['dependencies']);
        $applied['recovery'] = $plan;
        $applied['atomic'] = true;
        $applied['current_source'] = $source;

        return $applied;
    }

    /**
     * @param list<array{database_path:string,stale_database_bytes?:string,stale_journal_bytes?:string,reserved_lock?:bool}> $databases
     * @param array<int,string> $retryPageWrites
     * @return array{status:string,root:string,applied:int,bytes_written:int,bytes_truncated:int,files_deleted:int,durable_syncs:int,directory_syncs:int,operations:list<array<string, mixed>>,dependencies:list<string>,recovery:array<string, mixed>,atomic:bool,current_source:array{master_journal_path:string,master_journal_exists:bool,database_paths:list<string>,database_bytes:array<string,int>,journal_bytes:array<string,int>}}
     */
    public function applySavepointMasterJournalCurrentSourceNext92(
        string $masterJournalPath,
        array $databases,
        int $pageSize,
        string $primaryDatabasePath,
        string $savepointName,
        array $retryPageWrites,
    ): array {
        if ($masterJournalPath === '') {
            throw new \InvalidArgumentException('SQLite pager savepoint master-journal current-source requires a master-journal path');
        }
        if ($databases === []) {
            throw new \InvalidArgumentException('SQLite pager savepoint master-journal current-source requires at least one attached database');
        }

        $masterLocalPath = $this->localPath($masterJournalPath);
        $masterJournalBytes = is_file($masterLocalPath) ? (string) file_get_contents($masterLocalPath) : null;
        $hydrated = [];
        $sourceDatabasePaths = [];
        $sourceDatabaseBytes = [];
        $sourceJournalBytes = [];

        foreach ($databases as $index => $database) {
            $databasePath = isset($database['database_path']) ? (string) $database['database_path'] : '';
            if ($databasePath === '') {
                throw new \InvalidArgumentException("SQLite pager savepoint master-journal current-source database {$index} requires a database path");
            }

            $databaseLocalPath = $this->localPath($databasePath);
            $journalLocalPath = $this->localPath($databasePath . '-journal');
            if (!is_file($databaseLocalPath)) {
                throw new \RuntimeException("SQLite pager savepoint master-journal current-source database is missing: {$databasePath}");
            }
            if (!is_file($journalLocalPath)) {
                throw new \RuntimeException("SQLite pager savepoint master-journal current-source journal is missing: {$databasePath}-journal");
            }

            $databaseBytes = (string) file_get_contents($databaseLocalPath);
            $journalBytes = (string) file_get_contents($journalLocalPath);
            $hydrated[] = array_merge($database, [
                'current_database_bytes' => $databaseBytes,
                'current_journal_bytes' => $journalBytes,
            ]);
            $sourceDatabasePaths[] = $databasePath;
            $sourceDatabaseBytes[$databasePath] = strlen($databaseBytes);
            $sourceJournalBytes[$databasePath . '-journal'] = strlen($journalBytes);
        }

        $plan = SQLitePagerSavepointMasterJournalCurrentSourceNext92Plan::currentSourceNext(
            $masterJournalPath,
            $masterJournalBytes,
            $hydrated,
            $pageSize,
            $primaryDatabasePath,
            $savepointName,
            $retryPageWrites,
            $this->readOnly,
            $this->immutable
        );
        $source = [
            'master_journal_path' => $masterJournalPath,
            'master_journal_exists' => $masterJournalBytes !== null,
            'database_paths' => $sourceDatabasePaths,
            'database_bytes' => $sourceDatabaseBytes,
            'journal_bytes' => $sourceJournalBytes,
        ];

        if ($plan['status'] === 'master_journal_recovery_blocked_before_retry_savepoint') {
            return [
                'status' => 'skipped',
                'root' => $this->rootDirectory,
                'applied' => 0,
                'bytes_written' => 0,
                'bytes_truncated' => 0,
                'files_deleted' => 0,
                'durable_syncs' => 0,
                'directory_syncs' => 0,
                'operations' => [],
                'dependencies' => $plan['dependencies'],
                'recovery' => $plan,
                'atomic' => true,
                'current_source' => $source,
            ];
        }

        $applied = $this->applyAtomicOperations($plan['apply_operations'], $plan['payloads'], $plan['dependencies']);
        $applied['recovery'] = $plan;
        $applied['atomic'] = true;
        $applied['current_source'] = $source;

        return $applied;
    }

    /**
     * @param list<array{database_path:string,database_bytes:string,journal:SQLiteRollbackJournal,journal_bytes:string,wal_bytes:string,page_numbers:list<int>,database_page_size?:int,reserved_lock?:bool}> $databases
     * @return array{status:string,root:string,applied:int,bytes_written:int,bytes_truncated:int,files_deleted:int,durable_syncs:int,directory_syncs:int,operations:list<array<string, mixed>>,dependencies:list<string>,recovery:array<string, mixed>,atomic:bool}
     */
    public function applyMasterSuperJournalHotRecovery74(
        string $superJournalPath,
        string $superJournalBytes,
        array $databases,
    ): array {
        $plan = SQLitePagerHotJournalWalRecoveryPlan::masterSuperJournalCurrentNext73(
            $superJournalPath,
            $superJournalBytes,
            $databases
        );

        $payloads = [];
        foreach ($plan['databases'] as $databasePlan) {
            foreach (($databasePlan['recovery']['payloads'] ?? []) as $path => $bytes) {
                if (is_string($path) && is_string($bytes)) {
                    $payloads[$path] = $bytes;
                }
            }
        }

        $applied = $this->applyAtomicOperations(
            $plan['operations'],
            $payloads,
            array_values(array_unique(array_merge(
                $plan['dependencies'],
                ['sqlite-pager-hot-journal-master-super-vfs-apply74']
            )))
        );
        $applied['recovery'] = $plan;
        $applied['atomic'] = true;

        return $applied;
    }

    /**
     * @param list<array{database_path:string,database_bytes:string,journal_bytes:string,journal?:SQLiteRollbackJournal,reserved_lock?:bool}> $databases
     * @return array{status:string,root:string,applied:int,bytes_written:int,bytes_truncated:int,files_deleted:int,durable_syncs:int,directory_syncs:int,operations:list<array<string, mixed>>,dependencies:list<string>,recovery:array<string, mixed>,atomic:bool}
     */
    public function applyMasterJournalStatementRecovery(
        string $superJournalPath,
        ?string $superJournalBytes,
        array $databases,
        int $pageSize,
        SQLiteSavepointStack $savepoints,
        string $statementName,
        string $nextStatementName,
        int $nextPageNumber,
        string $nextBeforeImage,
        bool $nextCommitFrame = false,
        ?string $primaryDatabasePath = null,
    ): array {
        $plan = SQLitePagerMasterJournalStatementRecoveryPlan::currentNext(
            $superJournalPath,
            $superJournalBytes,
            $databases,
            $pageSize,
            $savepoints,
            $statementName,
            $nextStatementName,
            $nextPageNumber,
            $nextBeforeImage,
            $nextCommitFrame,
            $primaryDatabasePath,
            $this->readOnly,
            $this->immutable
        );

        if ($plan['status'] === 'blocked') {
            return [
                'status' => 'skipped',
                'root' => $this->rootDirectory,
                'applied' => 0,
                'bytes_written' => 0,
                'bytes_truncated' => 0,
                'files_deleted' => 0,
                'durable_syncs' => 0,
                'directory_syncs' => 0,
                'operations' => [],
                'dependencies' => $plan['dependencies'],
                'recovery' => $plan,
                'atomic' => true,
            ];
        }

        $applied = $this->applyAtomicOperations($plan['operations'], $plan['payloads'], $plan['dependencies']);
        $applied['recovery'] = $plan;
        $applied['atomic'] = true;

        return $applied;
    }

    /**
     * @param array<int, string> $databasePages 1-indexed page numbers to page images.
     * @return array{status:string,root:string,applied:int,bytes_written:int,bytes_truncated:int,files_deleted:int,durable_syncs:int,directory_syncs:int,operations:list<array<string, mixed>>,dependencies:list<string>,commit:array<string, mixed>}
     */
    public function applyRollbackJournalCommit(
        string $databasePath,
        string $journalBytes,
        array $databasePages,
        int $pageSize,
        string $syncMode = 'full',
        string $journalMode = 'delete',
    ): array {
        $plan = SQLiteRollbackJournalCommitPlan::commit(
            $databasePath,
            $journalBytes,
            $databasePages,
            $pageSize,
            $syncMode,
            $journalMode,
            $this->readOnly,
            $this->immutable
        );

        $payloads = [$plan['journal_path'] => $journalBytes];
        foreach ($databasePages as $pageNumber => $pageImage) {
            $payloads[$databasePath . '#page:' . $pageNumber] = $pageImage;
        }
        if ($plan['journal_mode'] === 'persist') {
            $payloads[$plan['journal_path'] . '#persist-header'] = str_repeat("\0", min(28, strlen($journalBytes)));
        }

        $applied = $this->applyOperations($plan['operations'], $payloads, $plan['dependencies']);
        $applied['commit'] = $plan;

        return $applied;
    }

    /**
     * @param array<int, string> $databasePages 1-indexed page numbers to page images.
     * @return array{status:string,root:string,applied:int,bytes_written:int,bytes_truncated:int,files_deleted:int,durable_syncs:int,directory_syncs:int,operations:list<array<string, mixed>>,dependencies:list<string>,commit:array<string, mixed>}
     */
    public function applyTemporaryRollbackJournalCommit(
        string $databasePath,
        string $journalPath,
        string $journalBytes,
        array $databasePages,
        int $pageSize,
        string $syncMode = 'full',
        string $requestedJournalMode = 'delete',
    ): array {
        $plan = SQLiteRollbackJournalCommitPlan::commitTemporary(
            $databasePath,
            $journalPath,
            $journalBytes,
            $databasePages,
            $pageSize,
            $syncMode,
            $requestedJournalMode,
            $this->readOnly,
            $this->immutable
        );

        $payloads = [$plan['journal_path'] => $journalBytes];
        foreach ($databasePages as $pageNumber => $pageImage) {
            $payloads[$databasePath . '#page:' . $pageNumber] = $pageImage;
        }

        $applied = $this->applyOperations($plan['operations'], $payloads, $plan['dependencies']);
        $applied['commit'] = $plan;

        return $applied;
    }

    /**
     * @param list<array{database_path:string,journal_bytes:string,database_pages:array<int,string>}> $databaseCommits
     * @return array{status:string,root:string,applied:int,bytes_written:int,bytes_truncated:int,files_deleted:int,durable_syncs:int,directory_syncs:int,operations:list<array<string, mixed>>,dependencies:list<string>,commit:array<string, mixed>}
     */
    public function applySuperJournalCommit(
        string $superJournalPath,
        array $databaseCommits,
        int $pageSize,
        string $syncMode = 'full',
        string $journalMode = 'delete',
    ): array {
        $plan = SQLiteSuperJournalCommitPlan::commit(
            $superJournalPath,
            $databaseCommits,
            $pageSize,
            $syncMode,
            $journalMode,
            $this->readOnly,
            $this->immutable
        );

        $applied = $this->applyOperations(
            $plan['operations'],
            SQLiteSuperJournalCommitPlan::payloads($superJournalPath, $databaseCommits),
            $plan['dependencies']
        );
        $applied['commit'] = $plan;

        return $applied;
    }

    /**
     * @param list<array{database_path:string,database_bytes:string,statement_journal_path?:string,statement_pages:array<int,string>,outer_journal_bytes?:string,reserved_lock?:bool}> $databases
     * @return array{status:string,root:string,applied:int,bytes_written:int,bytes_truncated:int,files_deleted:int,durable_syncs:int,directory_syncs:int,operations:list<array<string, mixed>>,dependencies:list<string>,recovery:array<string, mixed>,atomic:bool}
     */
    public function applyMasterJournalStatementPageRecovery(
        string $masterJournalPath,
        ?string $masterJournalBytes,
        array $databases,
        int $pageSize,
    ): array {
        $plan = SQLitePagerStatementRecoveryPlan::masterJournalStatementRecoveryCurrentNext(
            $masterJournalPath,
            $masterJournalBytes,
            $databases,
            $pageSize,
            $this->readOnly,
            $this->immutable
        );

        if ($plan['recovered_database_count'] === 0) {
            return [
                'status' => 'skipped',
                'root' => $this->rootDirectory,
                'applied' => 0,
                'bytes_written' => 0,
                'bytes_truncated' => 0,
                'files_deleted' => 0,
                'durable_syncs' => 0,
                'directory_syncs' => 0,
                'operations' => [],
                'dependencies' => $plan['dependencies'],
                'recovery' => $plan,
                'atomic' => true,
            ];
        }

        $applied = $this->applyAtomicOperations($plan['operations'], $plan['payloads'], $plan['dependencies']);
        $applied['recovery'] = $plan;
        $applied['atomic'] = true;

        return $applied;
    }

    /**
     * @param list<array{database_path:string,statement_journal_path?:string,statement_pages:array<int,string>,outer_journal_bytes?:string,reserved_lock?:bool}> $databases
     * @return array{status:string,root:string,applied:int,bytes_written:int,bytes_truncated:int,files_deleted:int,durable_syncs:int,directory_syncs:int,operations:list<array<string, mixed>>,dependencies:list<string>,recovery:array<string, mixed>,atomic:bool,current_source:array{master_journal_path:string,master_journal_exists:bool,database_paths:list<string>,database_bytes:array<string,int>,statement_journal_paths:array<string,string>}}
     */
    public function applyMasterJournalStatementPageRecoveryFromCurrentSource84(
        string $masterJournalPath,
        array $databases,
        int $pageSize,
    ): array {
        if ($masterJournalPath === '') {
            throw new \InvalidArgumentException('SQLite pager current-source statement recovery requires a master-journal path');
        }
        if ($databases === []) {
            throw new \InvalidArgumentException('SQLite pager current-source statement recovery requires at least one attached database');
        }

        $masterLocalPath = $this->localPath($masterJournalPath);
        $masterJournalBytes = is_file($masterLocalPath) ? (string) file_get_contents($masterLocalPath) : null;
        $hydrated = [];
        $sourceDatabasePaths = [];
        $sourceDatabaseBytes = [];
        $sourceStatementPaths = [];

        foreach ($databases as $index => $database) {
            $databasePath = isset($database['database_path']) ? (string) $database['database_path'] : '';
            if ($databasePath === '') {
                throw new \InvalidArgumentException("SQLite pager current-source statement recovery database {$index} requires a database path");
            }

            $databaseLocalPath = $this->localPath($databasePath);
            if (!is_file($databaseLocalPath)) {
                throw new \RuntimeException("SQLite pager current-source statement recovery database is missing: {$databasePath}");
            }

            $databaseBytes = (string) file_get_contents($databaseLocalPath);
            $statementJournalPath = isset($database['statement_journal_path']) && (string) $database['statement_journal_path'] !== ''
                ? (string) $database['statement_journal_path']
                : $databasePath . '-stmt-journal';

            $hydrated[] = array_merge($database, [
                'database_bytes' => $databaseBytes,
                'statement_journal_path' => $statementJournalPath,
            ]);
            $sourceDatabasePaths[] = $databasePath;
            $sourceDatabaseBytes[$databasePath] = strlen($databaseBytes);
            $sourceStatementPaths[$databasePath] = $statementJournalPath;
        }

        $plan = SQLitePagerStatementRecoveryPlan::masterJournalStatementRecoveryCurrentNext(
            $masterJournalPath,
            $masterJournalBytes,
            $hydrated,
            $pageSize,
            $this->readOnly,
            $this->immutable
        );
        $source = [
            'master_journal_path' => $masterJournalPath,
            'master_journal_exists' => $masterJournalBytes !== null,
            'database_paths' => $sourceDatabasePaths,
            'database_bytes' => $sourceDatabaseBytes,
            'statement_journal_paths' => $sourceStatementPaths,
        ];

        if ($plan['recovered_database_count'] === 0) {
            return [
                'status' => 'skipped',
                'root' => $this->rootDirectory,
                'applied' => 0,
                'bytes_written' => 0,
                'bytes_truncated' => 0,
                'files_deleted' => 0,
                'durable_syncs' => 0,
                'directory_syncs' => 0,
                'operations' => [],
                'dependencies' => array_values(array_unique(array_merge(
                    $plan['dependencies'],
                    ['sqlite-pager-statement-current-source-next84']
                ))),
                'recovery' => $plan,
                'atomic' => true,
                'current_source' => $source,
            ];
        }

        $applied = $this->applyAtomicOperations(
            $plan['operations'],
            $plan['payloads'],
            array_values(array_unique(array_merge(
                $plan['dependencies'],
                ['sqlite-pager-statement-current-source-next84']
            )))
        );
        $applied['recovery'] = $plan;
        $applied['atomic'] = true;
        $applied['current_source'] = $source;

        return $applied;
    }

    /**
     * @return array{status:string,root:string,applied:int,bytes_written:int,bytes_truncated:int,files_deleted:int,durable_syncs:int,directory_syncs:int,operations:list<array<string, mixed>>,dependencies:list<string>,savepoint:string,database_image:array<string, mixed>,wal_truncation:array<string, mixed>|null}
     */
    public function applySavepointRollback(
        SQLiteSavepointStack $savepoints,
        string $savepoint,
        string $databaseBytes,
        int $pageSize,
        string $databasePath,
        ?SQLiteWal $wal = null,
        ?string $walBytes = null,
    ): array {
        if ($savepoint === '') {
            throw new \InvalidArgumentException('SQLite savepoint VFS rollback requires a savepoint name');
        }
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite savepoint VFS rollback requires a database path');
        }

        $databaseImage = $savepoints->rollbackToDatabaseImage($savepoint, $databaseBytes, $pageSize);
        $imagePlan = $savepoints->rollbackToImagePlan($savepoint, $pageSize);
        $operations = [
            [
                'op' => 'write',
                'path' => $databasePath,
                'offset' => 0,
                'bytes' => strlen($databaseImage),
                'durable' => false,
                'reason' => 'restore_savepoint_database_page_images',
            ],
            [
                'op' => 'truncate',
                'path' => $databasePath,
                'bytes' => strlen($databaseImage),
                'durable' => false,
                'reason' => 'trim_savepoint_database_image',
            ],
            [
                'op' => 'sync',
                'path' => $databasePath,
                'durable' => true,
                'reason' => 'sync_savepoint_database_rollback',
            ],
        ];
        $payloads = [$databasePath => $databaseImage];
        $walPlan = null;

        if (($wal === null) !== ($walBytes === null)) {
            throw new \InvalidArgumentException('SQLite savepoint VFS rollback requires both WAL object and WAL bytes');
        }
        if ($wal !== null && $walBytes !== null) {
            $walPath = $databasePath . '-wal';
            $walImage = $savepoints->walRollbackToWalBytes($savepoint, $wal, $walBytes);
            $walPlan = $savepoints->walRollbackToByteTruncationPlan($savepoint, $wal, $walBytes);
            $operations[] = [
                'op' => 'write',
                'path' => $walPath,
                'offset' => 0,
                'bytes' => strlen($walImage),
                'durable' => false,
                'reason' => 'restore_savepoint_wal_prefix',
            ];
            $operations[] = [
                'op' => 'truncate',
                'path' => $walPath,
                'bytes' => strlen($walImage),
                'durable' => false,
                'reason' => 'truncate_savepoint_wal_frames',
            ];
            $operations[] = [
                'op' => 'sync',
                'path' => $walPath,
                'durable' => true,
                'reason' => 'sync_savepoint_wal_rollback',
            ];
            $payloads[$walPath] = $walImage;
        }

        $operations[] = [
            'op' => 'sync_directory',
            'path' => dirname($databasePath),
            'durable' => true,
            'reason' => 'persist_savepoint_rollback_sidecars',
        ];

        $applied = $this->applyOperations(
            $operations,
            $payloads,
            ['sqlite-savepoint-page-image-rollback', 'sqlite-savepoint-wal-rollback', 'vfs-file-write-coordination']
        );
        $applied['savepoint'] = $savepoint;
        $applied['database_image'] = $imagePlan;
        $applied['wal_truncation'] = $walPlan;

        return $applied;
    }

    /**
     * @param array<string,string> $statementJournalPaths statement journal name to VFS path.
     * @return array{status:string,root:string,applied:int,bytes_written:int,bytes_truncated:int,files_deleted:int,durable_syncs:int,directory_syncs:int,operations:list<array<string, mixed>>,dependencies:list<string>,savepoint:string,database_image:array<string, mixed>,wal_truncation:array<string, mixed>|null,current_source:array<string, mixed>,statement_journals:array<string, mixed>,atomic:bool}
     */
    public function applySavepointRollbackFromCurrentSourceNext94(
        SQLiteSavepointStack $savepoints,
        string $savepoint,
        string $databasePath,
        int $pageSize,
        array $statementJournalPaths = [],
        ?string $walPath = null,
    ): array {
        if ($savepoint === '') {
            throw new \InvalidArgumentException('SQLite savepoint current-source rollback requires a savepoint name');
        }
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite savepoint current-source rollback requires a database path');
        }
        if ($pageSize < 1) {
            throw new \InvalidArgumentException('SQLite savepoint current-source rollback requires a positive page size');
        }

        $databaseLocalPath = $this->localPath($databasePath);
        if (!is_file($databaseLocalPath)) {
            throw new \RuntimeException("SQLite savepoint current-source database is missing: {$databasePath}");
        }

        $databaseBytes = (string) file_get_contents($databaseLocalPath);
        if (strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite savepoint current-source database bytes must be aligned to the page size');
        }

        $beforeStatementNames = array_column($savepoints->statementJournalState(), 'name');
        $after = clone $savepoints;
        $rollbackTransition = $after->rollbackToWithPlan($savepoint);
        $afterStatementNames = array_column($after->statementJournalState(), 'name');
        $discardedStatementNames = array_values(array_diff($beforeStatementNames, $afterStatementNames));
        sort($discardedStatementNames, SORT_STRING);

        $wal = null;
        $walBytes = null;
        $walPath = $walPath !== null && $walPath !== '' ? $walPath : $databasePath . '-wal';
        $walLocalPath = $this->localPath($walPath);
        if (is_file($walLocalPath)) {
            $walBytes = (string) file_get_contents($walLocalPath);
            $wal = SQLiteWal::parse($walBytes, null, true);
        }

        $databaseImage = $savepoints->rollbackToDatabaseImage($savepoint, $databaseBytes, $pageSize);
        $imagePlan = $savepoints->rollbackToImagePlan($savepoint, $pageSize);
        $operations = [
            [
                'op' => 'write',
                'path' => $databasePath,
                'offset' => 0,
                'bytes' => strlen($databaseImage),
                'durable' => false,
                'reason' => 'restore_current_source_savepoint_database_page_images',
            ],
            [
                'op' => 'truncate',
                'path' => $databasePath,
                'bytes' => strlen($databaseImage),
                'durable' => false,
                'reason' => 'trim_current_source_savepoint_database_image',
            ],
            [
                'op' => 'sync',
                'path' => $databasePath,
                'durable' => true,
                'reason' => 'sync_current_source_savepoint_database_rollback',
            ],
        ];
        $payloads = [$databasePath => $databaseImage];
        $walPlan = null;
        $walImage = null;

        if ($wal !== null && $walBytes !== null) {
            $walImage = $savepoints->walRollbackToWalBytes($savepoint, $wal, $walBytes);
            $walPlan = $savepoints->walRollbackToByteTruncationPlan($savepoint, $wal, $walBytes);
            $operations[] = [
                'op' => 'write',
                'path' => $walPath,
                'offset' => 0,
                'bytes' => strlen($walImage),
                'durable' => false,
                'reason' => 'restore_current_source_savepoint_wal_prefix',
            ];
            $operations[] = [
                'op' => 'truncate',
                'path' => $walPath,
                'bytes' => strlen($walImage),
                'durable' => false,
                'reason' => 'truncate_current_source_savepoint_wal_frames',
            ];
            $operations[] = [
                'op' => 'sync',
                'path' => $walPath,
                'durable' => true,
                'reason' => 'sync_current_source_savepoint_wal_rollback',
            ];
            $payloads[$walPath] = $walImage;
        }

        $discardedJournalPaths = [];
        $preservedJournalPaths = [];
        foreach ($statementJournalPaths as $statementName => $statementPath) {
            $statementName = (string) $statementName;
            $statementPath = (string) $statementPath;
            if ($statementName === '' || $statementPath === '') {
                throw new \InvalidArgumentException('SQLite savepoint current-source statement journal paths require non-empty names and paths');
            }
            if (in_array($statementName, $discardedStatementNames, true)) {
                $discardedJournalPaths[$statementName] = $statementPath;
                $operations[] = [
                    'op' => 'delete',
                    'path' => $statementPath,
                    'durable' => false,
                    'reason' => 'delete_discarded_statement_journal_after_savepoint_rollback',
                    'statement_journal' => $statementName,
                ];
            } else {
                $preservedJournalPaths[$statementName] = $statementPath;
            }
        }

        $operations[] = [
            'op' => 'sync_directory',
            'path' => dirname($databasePath),
            'durable' => true,
            'reason' => 'persist_current_source_savepoint_rollback_sidecars',
        ];

        $applied = $this->applyAtomicOperations(
            $operations,
            $payloads,
            [
                'sqlite-savepoint-page-image-rollback',
                'sqlite-savepoint-journal-filehandle-current-source-next94',
                'sqlite-savepoint-wal-rollback',
                'vfs-file-write-coordination',
            ]
        );
        $applied['savepoint'] = $savepoint;
        $applied['database_image'] = $imagePlan;
        $applied['wal_truncation'] = $walPlan;
        $applied['current_source'] = [
            'database_path' => $databasePath,
            'database_bytes_before' => strlen($databaseBytes),
            'database_bytes_after' => strlen($databaseImage),
            'database_page_count_before' => intdiv(strlen($databaseBytes), $pageSize),
            'wal_path' => $walPath,
            'wal_exists' => $walBytes !== null,
            'wal_bytes_before' => $walBytes !== null ? strlen($walBytes) : 0,
            'wal_bytes_after' => $walImage !== null ? strlen($walImage) : 0,
            'rollback_transition' => $rollbackTransition,
        ];
        $applied['statement_journals'] = [
            'before' => $beforeStatementNames,
            'after' => $afterStatementNames,
            'discarded' => $discardedStatementNames,
            'discarded_paths' => $discardedJournalPaths,
            'preserved_paths' => $preservedJournalPaths,
        ];
        $applied['atomic'] = true;

        return $applied;
    }

    /**
     * @param list<int> $visiblePages
     * @return array{status:string,root:string,applied:int,bytes_written:int,bytes_truncated:int,files_deleted:int,durable_syncs:int,directory_syncs:int,operations:list<array<string, mixed>>,dependencies:list<string>,savepoint_checkpoint:array<string, mixed>,reader_boundary:array<string, mixed>,atomic:bool}
     */
    public function applySavepointCheckpointVisibility(
        SQLiteSavepointStack $savepoints,
        string $savepoint,
        SQLiteWal $wal,
        string $walBytes,
        string $databaseBytes,
        string $databasePath,
        array $visiblePages,
        string $mode = 'truncate',
        ?int $currentReaderEndFrame = null,
        ?int $nextReaderEndFrame = null,
    ): array {
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite WAL savepoint checkpoint VFS apply requires a database path');
        }

        $boundary = SQLiteWalSavepointCheckpointPlan::readerBoundaryAfterRollbackTo(
            $savepoints,
            $savepoint,
            $wal,
            $walBytes,
            $databaseBytes,
            $visiblePages,
            $mode,
            $currentReaderEndFrame,
            $nextReaderEndFrame
        );
        $checkpoint = SQLiteWalSavepointCheckpointPlan::afterRollbackTo(
            $savepoints,
            $savepoint,
            $wal,
            $walBytes,
            $databaseBytes,
            $mode,
            $currentReaderEndFrame
        );
        $durable = $checkpoint['current_durable'];
        $walPath = $databasePath . '-wal';
        $operations = [
            [
                'op' => 'write',
                'path' => $databasePath,
                'offset' => 0,
                'bytes' => strlen($durable['database_bytes']),
                'durable' => false,
                'reason' => 'apply_savepoint_checkpoint_database_image',
            ],
            [
                'op' => 'truncate',
                'path' => $databasePath,
                'bytes' => strlen($durable['database_bytes']),
                'durable' => false,
                'reason' => 'trim_savepoint_checkpoint_database_image',
            ],
            [
                'op' => 'sync',
                'path' => $databasePath,
                'durable' => true,
                'reason' => 'sync_savepoint_checkpoint_database',
            ],
            [
                'op' => 'write',
                'path' => $walPath,
                'offset' => 0,
                'bytes' => strlen($durable['wal_bytes']),
                'durable' => false,
                'reason' => 'apply_savepoint_checkpoint_wal_state',
            ],
            [
                'op' => 'truncate',
                'path' => $walPath,
                'bytes' => strlen($durable['wal_bytes']),
                'durable' => false,
                'reason' => 'trim_savepoint_checkpoint_wal_state',
            ],
            [
                'op' => 'sync',
                'path' => $walPath,
                'durable' => true,
                'reason' => 'sync_savepoint_checkpoint_wal',
            ],
            [
                'op' => 'sync_directory',
                'path' => dirname($databasePath),
                'durable' => true,
                'reason' => 'persist_savepoint_checkpoint_visibility',
            ],
        ];

        $applied = $this->applyAtomicOperations(
            $operations,
            [
                $databasePath => $durable['database_bytes'],
                $walPath => $durable['wal_bytes'],
            ],
            array_values(array_unique(array_merge(
                $checkpoint['dependencies'],
                $boundary['dependencies'],
                ['sqlite-wal-savepoint-checkpoint-vfs-visibility-apply']
            )))
        );
        $applied['savepoint_checkpoint'] = $checkpoint;
        $applied['reader_boundary'] = $boundary;
        $applied['atomic'] = true;

        return $applied;
    }

    /**
     * @param list<array{pages:array<int,string>,database_page_count?:int|null,commit?:bool}> $transactions
     * @param list<int> $visiblePages
     * @return array{status:string,root:string,applied:int,bytes_written:int,bytes_truncated:int,files_deleted:int,durable_syncs:int,directory_syncs:int,operations:list<array<string, mixed>>,dependencies:list<string>,savepoint_checkpoint_append:array<string, mixed>,atomic:bool}
     */
    public function applySavepointRestartCheckpointAppend(
        SQLiteSavepointStack $savepoints,
        string $savepoint,
        SQLiteWal $wal,
        string $walBytes,
        string $databaseBytes,
        string $databasePath,
        array $transactions,
        array $visiblePages,
        string $mode = 'restart',
        ?int $readerEndFrame = null,
        bool $syncWal = true,
        bool $syncDirectory = true,
    ): array {
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite WAL savepoint restart checkpoint VFS apply requires a database path');
        }

        $plan = SQLiteWalAppendPlan::savepointRestartCheckpointCurrentNext(
            $savepoints,
            $savepoint,
            $wal,
            $walBytes,
            $databaseBytes,
            $databasePath,
            $transactions,
            $visiblePages,
            $mode,
            $readerEndFrame,
            $syncWal,
            $syncDirectory
        );
        if ($plan['status'] === 'busy') {
            return [
                'status' => 'busy',
                'root' => $this->rootDirectory,
                'applied' => 0,
                'bytes_written' => 0,
                'bytes_truncated' => 0,
                'files_deleted' => 0,
                'durable_syncs' => 0,
                'directory_syncs' => 0,
                'operations' => [],
                'dependencies' => array_values(array_unique(array_merge(
                    $plan['dependencies'],
                    ['sqlite-wal-savepoint-restart-checkpoint-vfs-apply74']
                ))),
                'savepoint_checkpoint_append' => $plan,
                'atomic' => true,
            ];
        }

        $databaseImage = (string) $plan['checkpoint']['database_bytes'];
        $walImage = (string) $plan['append']['wal_bytes'];
        $walPath = $databasePath . '-wal';
        $operations = [
            [
                'op' => 'write',
                'path' => $databasePath,
                'offset' => 0,
                'bytes' => strlen($databaseImage),
                'durable' => false,
                'reason' => 'apply_savepoint_restart_checkpoint_database_image',
            ],
            [
                'op' => 'truncate',
                'path' => $databasePath,
                'bytes' => strlen($databaseImage),
                'durable' => false,
                'reason' => 'trim_savepoint_restart_checkpoint_database_image',
            ],
            [
                'op' => 'sync',
                'path' => $databasePath,
                'durable' => true,
                'reason' => 'sync_savepoint_restart_checkpoint_database',
            ],
            [
                'op' => 'write',
                'path' => $walPath,
                'offset' => 0,
                'bytes' => strlen($walImage),
                'durable' => false,
                'reason' => 'apply_savepoint_restart_checkpoint_appended_wal',
            ],
            [
                'op' => 'truncate',
                'path' => $walPath,
                'bytes' => strlen($walImage),
                'durable' => false,
                'reason' => 'trim_savepoint_restart_checkpoint_appended_wal',
            ],
        ];
        if ($syncWal) {
            $operations[] = [
                'op' => 'sync',
                'path' => $walPath,
                'durable' => true,
                'reason' => 'sync_savepoint_restart_checkpoint_appended_wal',
            ];
        }
        if ($syncDirectory) {
            $operations[] = [
                'op' => 'sync_directory',
                'path' => dirname($databasePath),
                'durable' => true,
                'reason' => 'persist_savepoint_restart_checkpoint_append_sidecars',
            ];
        }

        $applied = $this->applyAtomicOperations(
            $operations,
            [
                $databasePath => $databaseImage,
                $walPath => $walImage,
            ],
            array_values(array_unique(array_merge(
                $plan['dependencies'],
                ['sqlite-wal-savepoint-restart-checkpoint-vfs-apply74']
            )))
        );
        $applied['savepoint_checkpoint_append'] = $plan;
        $applied['atomic'] = true;

        return $applied;
    }

    /**
     * @param list<int> $visiblePages
     * @return array{status:string,root:string,applied:int,bytes_written:int,bytes_truncated:int,files_deleted:int,durable_syncs:int,directory_syncs:int,operations:list<array<string, mixed>>,dependencies:list<string>,commit_current:array<string, mixed>,atomic:bool}
     */
    public function applySavepointCommitCurrent(
        SQLiteSavepointStack $savepoints,
        string $savepoint,
        SQLiteWal $wal,
        string $walBytes,
        string $databaseBytes,
        string $databasePath,
        array $visiblePages,
        string $mode = 'restart',
        ?int $currentReaderEndFrame = null,
        ?int $nextReaderEndFrame = null,
    ): array {
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite WAL savepoint commit-current VFS apply requires a database path');
        }

        $plan = SQLiteWalSavepointCheckpointPlan::commitCurrentAfterRollbackTo(
            $savepoints,
            $savepoint,
            $wal,
            $walBytes,
            $databaseBytes,
            $visiblePages,
            $mode,
            $currentReaderEndFrame,
            $nextReaderEndFrame
        );
        $durable = $plan['current_durable'];
        $walPath = $databasePath . '-wal';
        $operations = [
            [
                'op' => 'write',
                'path' => $databasePath,
                'offset' => 0,
                'bytes' => strlen($durable['database_bytes']),
                'durable' => false,
                'reason' => 'apply_savepoint_commit_current_database_image',
            ],
            [
                'op' => 'truncate',
                'path' => $databasePath,
                'bytes' => strlen($durable['database_bytes']),
                'durable' => false,
                'reason' => 'trim_savepoint_commit_current_database_image',
            ],
            [
                'op' => 'sync',
                'path' => $databasePath,
                'durable' => true,
                'reason' => 'sync_savepoint_commit_current_database',
            ],
            [
                'op' => 'write',
                'path' => $walPath,
                'offset' => 0,
                'bytes' => strlen($durable['wal_bytes']),
                'durable' => false,
                'reason' => 'apply_savepoint_commit_current_wal_state',
            ],
            [
                'op' => 'truncate',
                'path' => $walPath,
                'bytes' => strlen($durable['wal_bytes']),
                'durable' => false,
                'reason' => 'trim_savepoint_commit_current_wal_state',
            ],
            [
                'op' => 'sync',
                'path' => $walPath,
                'durable' => true,
                'reason' => 'sync_savepoint_commit_current_wal',
            ],
            [
                'op' => 'sync_directory',
                'path' => dirname($databasePath),
                'durable' => true,
                'reason' => 'persist_savepoint_commit_current_sidecars',
            ],
        ];

        $applied = $this->applyAtomicOperations(
            $operations,
            [
                $databasePath => $durable['database_bytes'],
                $walPath => $durable['wal_bytes'],
            ],
            array_values(array_unique(array_merge(
                $plan['dependencies'],
                ['sqlite-wal-savepoint-commit-current-vfs-apply72']
            )))
        );
        $applied['commit_current'] = $plan;
        $applied['atomic'] = true;

        return $applied;
    }

    /**
     * @param list<array<string, mixed>> $operations
     * @param array<string, string> $payloads
     * @param list<string> $dependencies
     * @return array{status:string,root:string,applied:int,bytes_written:int,bytes_truncated:int,files_deleted:int,durable_syncs:int,directory_syncs:int,operations:list<array<string, mixed>>,dependencies:list<string>}
     */
    public function applyOperations(array $operations, array $payloads = [], array $dependencies = []): array
    {
        if ($this->readOnly || $this->immutable) {
            throw new \LogicException('SQLite VFS file writer requires a writable handle');
        }

        $applied = [];
        $bytesWritten = 0;
        $bytesTruncated = 0;
        $filesDeleted = 0;
        $durableSyncs = 0;
        $directorySyncs = 0;

        foreach ($operations as $index => $operation) {
            $op = isset($operation['op']) ? (string) $operation['op'] : '';
            $path = isset($operation['path']) ? (string) $operation['path'] : '';
            if ($path === '') {
                throw new \InvalidArgumentException('SQLite VFS operation requires a path');
            }

            $localPath = $this->localPath($path);
            if ($op === 'write') {
                $offset = $this->nonNegativeInt($operation['offset'] ?? 0, 'SQLite VFS write offset');
                $payloadKey = isset($operation['payload_key']) ? (string) $operation['payload_key'] : $path;
                if (!array_key_exists($payloadKey, $payloads)) {
                    throw new \InvalidArgumentException("SQLite VFS write payload is missing for {$path}");
                }
                $data = $payloads[$payloadKey];
                $expected = $this->nonNegativeInt($operation['bytes'] ?? strlen($data), 'SQLite VFS write byte count');
                if ($expected !== strlen($data)) {
                    throw new \InvalidArgumentException("SQLite VFS write payload length mismatch for {$path}");
                }
                $this->writeAt($localPath, $offset, $data);
                $bytesWritten += strlen($data);
                $applied[] = $this->applied($index, $operation, $localPath, strlen($data));
            } elseif ($op === 'truncate') {
                $size = $this->nonNegativeInt($operation['bytes'] ?? 0, 'SQLite VFS truncate size');
                $this->truncate($localPath, $size);
                $bytesTruncated += $size;
                $applied[] = $this->applied($index, $operation, $localPath, $size);
            } elseif ($op === 'delete') {
                if (is_file($localPath) && !unlink($localPath)) {
                    throw new \RuntimeException("SQLite VFS could not delete file: {$path}");
                }
                $filesDeleted++;
                $applied[] = $this->applied($index, $operation, $localPath, 0);
            } elseif ($op === 'sync') {
                if (!is_file($localPath)) {
                    throw new \RuntimeException("SQLite VFS sync target does not exist: {$path}");
                }
                $handle = @fopen($localPath, 'c+b');
                if (!is_resource($handle)) {
                    throw new \RuntimeException("SQLite VFS sync target is not writable: {$path}");
                }
                fflush($handle);
                fclose($handle);
                $durableSyncs++;
                $applied[] = $this->applied($index, $operation, $localPath, 0);
            } elseif ($op === 'sync_directory') {
                if (!is_dir($localPath)) {
                    throw new \RuntimeException("SQLite VFS directory sync target does not exist: {$path}");
                }
                $directorySyncs++;
                $applied[] = $this->applied($index, $operation, $localPath, 0);
            } else {
                throw new \InvalidArgumentException("Unsupported SQLite VFS operation: {$op}");
            }
        }

        return [
            'status' => 'applied',
            'root' => $this->rootDirectory,
            'applied' => count($applied),
            'bytes_written' => $bytesWritten,
            'bytes_truncated' => $bytesTruncated,
            'files_deleted' => $filesDeleted,
            'durable_syncs' => $durableSyncs,
            'directory_syncs' => $directorySyncs,
            'operations' => $applied,
            'dependencies' => array_values(array_unique(array_merge($dependencies, ['vfs-file-handle-write-application']))),
        ];
    }

    /**
     * @param list<array<string, mixed>> $operations
     * @param array<string, string> $payloads
     * @param list<string> $dependencies
     * @return array{status:string,root:string,applied:int,bytes_written:int,bytes_truncated:int,files_deleted:int,durable_syncs:int,directory_syncs:int,operations:list<array<string, mixed>>,dependencies:list<string>}
     */
    public function applyAtomicOperations(array $operations, array $payloads = [], array $dependencies = []): array
    {
        $snapshots = $this->snapshotsForOperations($operations);

        try {
            return $this->applyOperations(
                $operations,
                $payloads,
                array_values(array_unique(array_merge($dependencies, ['vfs-atomic-rollback-on-write-failure'])))
            );
        } catch (\Throwable $throwable) {
            $this->restoreSnapshots($snapshots);

            throw $throwable;
        }
    }

    /**
     * @param list<array{status:string,path:string,target:string,mode:string,flags:int,flag_names:list<string>,durable:bool,data_only:bool,directory:bool,allowed:bool,reason:string|null,dependencies:list<string>}> $plans
     * @return array{status:string,root:string,applied:int,skipped:int,durable_syncs:int,directory_syncs:int,operations:list<array<string, mixed>>,dependencies:list<string>}
     */
    public function applySyncPlans(array $plans, array $dependencies = []): array
    {
        if ($this->readOnly || $this->immutable) {
            throw new \LogicException('SQLite VFS sync application requires a writable handle');
        }
        if ($plans === []) {
            throw new \InvalidArgumentException('SQLite VFS sync application requires at least one sync plan');
        }

        $operations = [];
        $allDependencies = $dependencies;
        $durableSyncs = 0;
        $directorySyncs = 0;
        $skipped = 0;

        foreach ($plans as $index => $plan) {
            $path = isset($plan['path']) ? (string) $plan['path'] : '';
            if ($path === '') {
                throw new \InvalidArgumentException('SQLite VFS sync plan requires a path');
            }

            $allowed = (bool) ($plan['allowed'] ?? false);
            $status = isset($plan['status']) ? (string) $plan['status'] : '';
            $directory = (bool) ($plan['directory'] ?? false);
            $localPath = $this->localPath($path);
            foreach (($plan['dependencies'] ?? []) as $dependency) {
                $dependency = (string) $dependency;
                if ($dependency !== '') {
                    $allDependencies[] = $dependency;
                }
            }

            if ($status === 'skipped' || !$allowed) {
                $skipped++;
                $operations[] = $this->appliedSyncPlan($index, $plan, $localPath, 'skipped', 0);
                continue;
            }
            if ($status !== 'planned') {
                throw new \InvalidArgumentException("Unsupported SQLite VFS sync plan status: {$status}");
            }

            if ($directory) {
                if (!is_dir($localPath)) {
                    throw new \RuntimeException("SQLite VFS directory sync target does not exist: {$path}");
                }
                $directorySyncs++;
                $operations[] = $this->appliedSyncPlan($index, $plan, $localPath, 'sync_directory', 0);
                continue;
            }

            if (!is_file($localPath)) {
                throw new \RuntimeException("SQLite VFS sync target does not exist: {$path}");
            }
            $handle = @fopen($localPath, 'c+b');
            if (!is_resource($handle)) {
                throw new \RuntimeException("SQLite VFS sync target is not writable: {$path}");
            }
            fflush($handle);
            fclose($handle);
            $durableSyncs++;
            $operations[] = $this->appliedSyncPlan($index, $plan, $localPath, 'sync', filesize($localPath) ?: 0);
        }

        return [
            'status' => 'applied',
            'root' => $this->rootDirectory,
            'applied' => count($operations) - $skipped,
            'skipped' => $skipped,
            'durable_syncs' => $durableSyncs,
            'directory_syncs' => $directorySyncs,
            'operations' => $operations,
            'dependencies' => array_values(array_unique(array_merge($allDependencies, ['vfs-sync-plan-application', 'vfs-file-handle-sync']))),
        ];
    }

    private function localPath(string $path): string
    {
        if (str_contains($path, "\0")) {
            throw new \InvalidArgumentException('SQLite VFS path must not contain NUL bytes');
        }

        $root = rtrim($this->rootDirectory, DIRECTORY_SEPARATOR);
        $normalized = str_replace('\\', '/', $path);
        $relative = ltrim($normalized, '/');
        if ($relative === '' || str_contains($relative, '../') || str_starts_with($relative, '..')) {
            throw new \InvalidArgumentException("SQLite VFS path escapes writer root: {$path}");
        }

        return $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
    }

    /**
     * @param list<array<string, mixed>> $operations
     * @return array<string, array{exists:bool,bytes:string|null,is_dir:bool}>
     */
    private function snapshotsForOperations(array $operations): array
    {
        $snapshots = [];
        foreach ($operations as $operation) {
            $op = isset($operation['op']) ? (string) $operation['op'] : '';
            if (!in_array($op, ['write', 'truncate', 'delete', 'sync'], true)) {
                continue;
            }
            $path = isset($operation['path']) ? (string) $operation['path'] : '';
            if ($path === '') {
                throw new \InvalidArgumentException('SQLite VFS operation requires a path');
            }
            $localPath = $this->localPath($path);
            if (array_key_exists($localPath, $snapshots)) {
                continue;
            }
            $snapshots[$localPath] = [
                'exists' => is_file($localPath),
                'bytes' => is_file($localPath) ? file_get_contents($localPath) : null,
                'is_dir' => is_dir($localPath),
            ];
        }

        return $snapshots;
    }

    /**
     * @param array<string, array{exists:bool,bytes:string|null,is_dir:bool}> $snapshots
     */
    private function restoreSnapshots(array $snapshots): void
    {
        foreach ($snapshots as $localPath => $snapshot) {
            if ($snapshot['is_dir']) {
                continue;
            }
            if (!$snapshot['exists']) {
                if (is_file($localPath) && !unlink($localPath)) {
                    throw new \RuntimeException("SQLite VFS could not restore absent file: {$localPath}");
                }
                continue;
            }

            $directory = dirname($localPath);
            if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
                throw new \RuntimeException("SQLite VFS could not recreate directory while restoring: {$directory}");
            }
            if (file_put_contents($localPath, $snapshot['bytes']) === false) {
                throw new \RuntimeException("SQLite VFS could not restore file snapshot: {$localPath}");
            }
        }
    }

    private function writeAt(string $path, int $offset, string $data): void
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException("SQLite VFS could not create directory: {$directory}");
        }

        $handle = @fopen($path, 'c+b');
        if (!is_resource($handle)) {
            throw new \RuntimeException("SQLite VFS could not open file for writing: {$path}");
        }
        if (fseek($handle, $offset) !== 0) {
            fclose($handle);
            throw new \RuntimeException("SQLite VFS could not seek to offset {$offset}: {$path}");
        }
        $written = fwrite($handle, $data);
        fflush($handle);
        fclose($handle);
        if ($written !== strlen($data)) {
            throw new \RuntimeException("SQLite VFS short write: {$path}");
        }
    }

    private function truncate(string $path, int $size): void
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
            throw new \RuntimeException("SQLite VFS could not create directory: {$directory}");
        }

        $handle = @fopen($path, 'c+b');
        if (!is_resource($handle)) {
            throw new \RuntimeException("SQLite VFS could not open file for truncation: {$path}");
        }
        if (!ftruncate($handle, $size)) {
            fclose($handle);
            throw new \RuntimeException("SQLite VFS truncate failed: {$path}");
        }
        fflush($handle);
        fclose($handle);
    }

    private function nonNegativeInt(mixed $value, string $label): int
    {
        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException("{$label} must be a non-negative integer");
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $operation
     * @return array<string, mixed>
     */
    private function applied(int $index, array $operation, string $localPath, int $bytes): array
    {
        return [
            'index' => $index,
            'op' => (string) $operation['op'],
            'path' => (string) $operation['path'],
            'local_path' => $localPath,
            'bytes' => $bytes,
            'durable' => (bool) ($operation['durable'] ?? false),
            'reason' => isset($operation['reason']) ? (string) $operation['reason'] : null,
        ];
    }

    /**
     * @param array<string, mixed> $plan
     * @return array<string, mixed>
     */
    private function appliedSyncPlan(int $index, array $plan, string $localPath, string $op, int $bytes): array
    {
        return [
            'index' => $index,
            'op' => $op,
            'path' => (string) $plan['path'],
            'local_path' => $localPath,
            'bytes' => $bytes,
            'target' => (string) ($plan['target'] ?? ''),
            'mode' => (string) ($plan['mode'] ?? ''),
            'flags' => (int) ($plan['flags'] ?? 0),
            'flag_names' => array_values(array_map('strval', $plan['flag_names'] ?? [])),
            'data_only' => (bool) ($plan['data_only'] ?? false),
            'directory' => (bool) ($plan['directory'] ?? false),
            'durable' => (bool) ($plan['durable'] ?? false),
            'reason' => isset($plan['reason']) ? (string) $plan['reason'] : null,
        ];
    }
}
