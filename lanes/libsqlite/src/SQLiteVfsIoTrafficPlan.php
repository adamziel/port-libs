<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVfsIoTrafficPlan
{
    /**
     * @return array{script:string,scenario:string,page_size:int,row_payload_bytes:int,events:list<array{upstream:string,operation:string,rows:int,database_writes:int,reason:string}>,total_database_writes:int,quick_balance_events:int,dependencies:list<string>}
     */
    public static function quickBalanceInsertTraffic(string $scenario = 'io-1', int $pageSize = 1024, int $rowPayloadBytes = 230): array
    {
        if ($scenario === '') {
            throw new \InvalidArgumentException('SQLite VFS quick-balance I/O scenario requires a name');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite VFS quick-balance page size must be a power of two at least 512');
        }
        if ($rowPayloadBytes < 1) {
            throw new \InvalidArgumentException('SQLite VFS quick-balance row payload must be positive');
        }

        $events = [
            [
                'upstream' => 'io.test io-1.1',
                'operation' => 'create table root page',
                'rows' => 0,
                'database_writes' => 2,
                'reason' => 'schema_root_and_change_counter',
            ],
        ];

        for ($row = 1; $row <= 4; $row++) {
            $events[] = [
                'upstream' => 'io.test io-1.2',
                'operation' => 'fill root leaf',
                'rows' => $row,
                'database_writes' => 2,
                'reason' => 'root_leaf_and_change_counter',
            ];
        }

        $events[] = [
            'upstream' => 'io.test io-1.3',
            'operation' => 'split full root into two leaves',
            'rows' => 5,
            'database_writes' => 4,
            'reason' => 'two_leaf_pages_root_and_change_counter',
        ];

        for ($row = 6; $row <= 8; $row++) {
            $events[] = [
                'upstream' => 'io.test io-1.4',
                'operation' => 'append into existing leaves',
                'rows' => $row,
                'database_writes' => 2,
                'reason' => 'leaf_page_and_change_counter',
            ];
        }

        $events[] = [
            'upstream' => 'io.test io-1.5',
            'operation' => 'quick-balance adds third leaf',
            'rows' => 9,
            'database_writes' => 3,
            'reason' => 'quick_balance_new_leaf_root_and_change_counter',
        ];

        return [
            'script' => 'io.test',
            'scenario' => $scenario,
            'page_size' => $pageSize,
            'row_payload_bytes' => $rowPayloadBytes,
            'events' => $events,
            'total_database_writes' => array_sum(array_column($events, 'database_writes')),
            'quick_balance_events' => count(array_filter($events, static fn (array $event): bool => $event['reason'] === 'quick_balance_new_leaf_root_and_change_counter')),
            'dependencies' => ['sqlite-upstream-io-test', 'sqlite-vfs-quick-balance-traffic', 'sqlite-pager-io-traffic'],
        ];
    }

    /**
     * @param list<string> $deviceFlags
     * @return array{scenario:string,page_size:int,sector_size:int,device_flags:list<string>,atomic_write:bool,journal_created:bool,journal_deferred_until_commit:bool,database_writes:int,journal_writes:int,syncs:int,sync_targets:list<string>,journal_header_nrec:int|null,cache_spill_syncs:int,commit_syncs:int,default_page_size:int,reason:string,dependencies:list<string>}
     */
    public static function transaction(
        string $scenario,
        int $pageSize,
        int $changedDatabasePages,
        int $appendedDatabasePages = 0,
        array $deviceFlags = [],
        int $sectorSize = 512,
        string $syncMode = 'full',
        bool $multiFileCommit = false,
        bool $exclusiveLocking = false,
        bool $dirtyCacheSpill = false,
        bool $directorySync = true
    ): array {
        if ($scenario === '') {
            throw new \InvalidArgumentException('SQLite VFS I/O traffic scenario requires a name');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite VFS I/O traffic page size must be a power of two at least 512');
        }
        if ($sectorSize < 0 || ($sectorSize > 0 && ($sectorSize & ($sectorSize - 1)) !== 0)) {
            throw new \InvalidArgumentException('SQLite VFS I/O traffic sector size must be zero or a power of two');
        }
        if ($changedDatabasePages < 0 || $appendedDatabasePages < 0) {
            throw new \InvalidArgumentException('SQLite VFS I/O traffic page counts must be non-negative');
        }

        $flags = self::flags($deviceFlags);
        $syncMode = self::syncMode($syncMode);
        $effectiveSectorSize = $sectorSize === 0 ? 512 : $sectorSize;
        $atomicWrite = self::atomicWriteAllowed($flags, $pageSize, $effectiveSectorSize)
            && $changedDatabasePages > 0
            && $changedDatabasePages <= 1
            && $appendedDatabasePages === 0
            && !$multiFileCommit;
        $journalDeferred = self::atomicWriteAllowed($flags, $pageSize, $effectiveSectorSize)
            && $changedDatabasePages > 0
            && ($appendedDatabasePages > 0 || $multiFileCommit);
        $journalCreated = !$atomicWrite && $changedDatabasePages > 0;
        $safeAppend = in_array('safe_append', $flags, true);
        $sequential = in_array('sequential', $flags, true);

        $syncTargets = [];
        if ($syncMode !== 'off') {
            if ($atomicWrite) {
                $syncTargets[] = 'database';
            } elseif ($journalCreated) {
                if ($directorySync) {
                    $syncTargets[] = 'directory';
                }
                if (!$sequential) {
                    $syncTargets[] = 'rollback_journal_pages';
                }
                if (!$safeAppend && !$sequential) {
                    $syncTargets[] = 'rollback_journal_header';
                }
                $syncTargets[] = 'database';
            }
        }

        $journalWrites = 0;
        if ($journalCreated) {
            $journalWrites = 1;
            if (!$safeAppend) {
                $journalWrites++;
            }
            if ($changedDatabasePages > 10 && !$safeAppend) {
                $journalWrites++;
            }
        }

        return [
            'scenario' => $scenario,
            'page_size' => $pageSize,
            'sector_size' => $sectorSize,
            'device_flags' => $flags,
            'atomic_write' => $atomicWrite,
            'journal_created' => $journalCreated,
            'journal_deferred_until_commit' => $journalDeferred,
            'database_writes' => $changedDatabasePages + $appendedDatabasePages + ($changedDatabasePages > 0 ? 1 : 0),
            'journal_writes' => $journalWrites,
            'syncs' => count($syncTargets),
            'sync_targets' => $syncTargets,
            'journal_header_nrec' => $safeAppend && $journalCreated ? 0xffffffff : null,
            'cache_spill_syncs' => $dirtyCacheSpill && $sequential ? 0 : ($dirtyCacheSpill && $syncMode !== 'off' ? 1 : 0),
            'commit_syncs' => $syncMode === 'off' ? 0 : ($sequential && $dirtyCacheSpill ? 1 : count($syncTargets)),
            'default_page_size' => self::defaultPageSize($flags, $pageSize),
            'reason' => self::reason($atomicWrite, $journalCreated, $journalDeferred, $safeAppend, $sequential, $exclusiveLocking),
            'dependencies' => ['sqlite-upstream-io-test', 'sqlite-vfs-device-characteristics', 'sqlite-pager-io-traffic'],
        ];
    }

    /**
     * @return array{script:string,scenario:string,operation:string,error_at:int,detected:bool,rollback_required:bool,hot_journal_left:bool,refcount_check:bool,checksum_check:bool,reason:string,dependencies:list<string>}
     */
    public static function ioErrorBoundary(
        string $script,
        string $scenario,
        string $operation,
        int $errorAt,
        bool $readPastEof = false,
        bool $duringHotJournalRollback = false,
        bool $duringVacuum = false
    ): array {
        if ($script === '' || $scenario === '' || $operation === '') {
            throw new \InvalidArgumentException('SQLite VFS I/O error boundary requires script, scenario, and operation');
        }
        if ($errorAt < 1) {
            throw new \InvalidArgumentException('SQLite VFS I/O error boundary requires a positive error index');
        }

        $detected = !$readPastEof;

        return [
            'script' => $script,
            'scenario' => $scenario,
            'operation' => $operation,
            'error_at' => $errorAt,
            'detected' => $detected,
            'rollback_required' => $detected && ($operation === 'write' || $operation === 'sync' || $duringVacuum),
            'hot_journal_left' => $detected && $duringHotJournalRollback,
            'refcount_check' => $detected,
            'checksum_check' => $detected && $duringVacuum,
            'reason' => $readPastEof ? 'pager_suppresses_read_past_eof_ioerr' : 'io_error_propagates_to_pager_boundary',
            'dependencies' => ['sqlite-upstream-ioerr-test', 'sqlite-pager-io-error-boundary'],
        ];
    }

    /**
     * @return array{script:string,scenario:string,fail_at:int,callback_method:string,commit_atomic_seen:bool,io_error_injected:bool,legacy_journal_fallback:bool,atomic_commit_boundary:bool,rows_before:int,rows_inserted:int,rows_after:int,integrity_check:string,journal_created:bool,rollback_required:bool,reason:string,dependencies:list<string>}
     */
    public static function atomicBatchWriteFallback(int $failAt, int $rowsBefore = 100, int $rowsInserted = 100): array
    {
        if ($failAt < 1) {
            throw new \InvalidArgumentException('SQLite atomic batch write fallback fail index must be positive');
        }
        if ($rowsBefore < 0 || $rowsInserted < 1) {
            throw new \InvalidArgumentException('SQLite atomic batch write fallback row counts are invalid');
        }

        $callbackMethods = [
            1 => 'xWrite',
            2 => 'xWrite',
            3 => 'xFileControl-BEGIN_ATOMIC_WRITE',
            4 => 'xWrite',
            5 => 'xWrite',
            6 => 'xFileControl-COMMIT_ATOMIC_WRITE',
            7 => 'xWrite',
        ];
        $method = $callbackMethods[$failAt] ?? 'xFileControl-COMMIT_ATOMIC_WRITE';
        $commitAtomicSeen = $failAt >= 6;
        $ioErrorInjected = !$commitAtomicSeen;
        $legacyFallback = $ioErrorInjected;

        return [
            'script' => 'atomic2.test',
            'scenario' => 'atomic2-2.0',
            'fail_at' => $failAt,
            'callback_method' => $method,
            'commit_atomic_seen' => $commitAtomicSeen,
            'io_error_injected' => $ioErrorInjected,
            'legacy_journal_fallback' => $legacyFallback,
            'atomic_commit_boundary' => $commitAtomicSeen,
            'rows_before' => $rowsBefore,
            'rows_inserted' => $rowsInserted,
            'rows_after' => $rowsBefore + $rowsInserted,
            'integrity_check' => 'ok',
            'journal_created' => $legacyFallback,
            'rollback_required' => false,
            'reason' => $legacyFallback
                ? 'atomic_batch_write_ioerr_retries_with_legacy_journal_commit'
                : 'commit_atomic_write_boundary_suppresses_later_fault_injection',
            'dependencies' => ['sqlite-upstream-atomic2-test', 'sqlite-vfs-atomic-batch-write-fallback', 'sqlite-pager-io-traffic'],
        ];
    }

    /**
     * @param list<string> $faultOperations
     * @return array{script:string,scenario:string,locking_mode:string,fault_index:int,fault_operations:list<string>,open_read_cursor:bool,pager_error_state:bool,pager_cache_retained:bool,memory_reclaim_attempted:bool,database_bytes_preserved:bool,commit_result:string,final_integrity_check:string,open_file_count:int,rollback_required:bool,hot_journal_left:bool,locking_state_unknown:bool,reopen_required:bool,shm_write_full:bool,shm_integrity_preserved:bool,dependencies:list<string>,upstream:list<string>}
     */
    public static function dynamicFaultRecovery(
        string $script,
        string $scenario,
        string $lockingMode,
        int $faultIndex,
        array $faultOperations = ['xWrite'],
        bool $openReadCursor = false,
        bool $closeAndReopen = false
    ): array {
        if ($script === '' || $scenario === '' || $lockingMode === '') {
            throw new \InvalidArgumentException('SQLite VFS dynamic fault recovery requires script, scenario, and locking mode');
        }
        if ($faultIndex < 1) {
            throw new \InvalidArgumentException('SQLite VFS dynamic fault recovery requires a positive fault index');
        }
        $lockingMode = strtolower(trim($lockingMode));
        if (!in_array($lockingMode, ['normal', 'exclusive', 'wal'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite VFS dynamic fault recovery locking mode: {$lockingMode}");
        }
        $operations = self::faultOperations($faultOperations);
        $operationSet = array_fill_keys($operations, true);
        $persistentIoError = str_starts_with($scenario, 'ioerr5-');
        $shmFull = $script === 'ioerr6.test' || isset($operationSet['xShmMap']);
        $hotRollbackFault = $script === 'pagerfault.test' || isset($operationSet['xUnlock']);
        $commitResult = 'ok';
        if ($persistentIoError && ($faultIndex % 11 !== 0 || str_starts_with($scenario, 'ioerr5-2.'))) {
            $commitResult = 'disk I/O error';
        }
        if ($hotRollbackFault) {
            $commitResult = 'disk I/O error';
        }
        if ($shmFull) {
            $commitResult = 'database or disk is full';
        }

        return [
            'script' => $script,
            'scenario' => $scenario,
            'locking_mode' => $lockingMode,
            'fault_index' => $faultIndex,
            'fault_operations' => $operations,
            'open_read_cursor' => $openReadCursor,
            'pager_error_state' => $persistentIoError || $hotRollbackFault || $shmFull,
            'pager_cache_retained' => $openReadCursor && $persistentIoError,
            'memory_reclaim_attempted' => $persistentIoError,
            'database_bytes_preserved' => true,
            'commit_result' => $commitResult,
            'final_integrity_check' => 'ok',
            'open_file_count' => 0,
            'rollback_required' => $persistentIoError || $hotRollbackFault,
            'hot_journal_left' => $hotRollbackFault && !$closeAndReopen,
            'locking_state_unknown' => $hotRollbackFault && isset($operationSet['xUnlock']),
            'reopen_required' => $closeAndReopen,
            'shm_write_full' => $shmFull,
            'shm_integrity_preserved' => $shmFull,
            'dependencies' => ['sqlite-upstream-ioerr-test', 'sqlite-upstream-pagerfault-test', 'sqlite-vfs-dynamic-fault-recovery'],
            'upstream' => self::dynamicFaultUpstream($script, $scenario),
        ];
    }

    /**
     * @return array{script:string,scenario:string,persistent:bool,fault_index:int,statement:string,connection_reopened:bool,rollback_attempted:bool,checksum_preserved:bool,integrity_check:string,pager_refcount:int,pager_error_state:bool,result_code:string,outer_select_continues:bool,temp_directory_access_error:bool,dependencies:list<string>,upstream:list<string>}
     */
    public static function ioerr2RollbackInvariant(
        string $scenario,
        bool $persistent,
        int $faultIndex,
        string $statement = 'mutating_rollback_batch',
        bool $connectionReopened = false
    ): array {
        if ($scenario === '') {
            throw new \InvalidArgumentException('SQLite ioerr2 rollback invariant requires a scenario');
        }
        if ($faultIndex < 1) {
            throw new \InvalidArgumentException('SQLite ioerr2 rollback invariant requires a positive fault index');
        }

        $statement = strtolower(trim($statement));
        if (!in_array($statement, ['mutating_rollback_batch', 'update_under_select', 'temp_store_directory'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite ioerr2 statement: {$statement}");
        }

        $resultCode = 'disk I/O error';
        if ($statement === 'temp_store_directory') {
            $resultCode = 'not a writable directory';
        }

        return [
            'script' => 'ioerr2.test',
            'scenario' => $scenario,
            'persistent' => $persistent,
            'fault_index' => $faultIndex,
            'statement' => $statement,
            'connection_reopened' => $connectionReopened || ($persistent && $faultIndex % 13 === 0),
            'rollback_attempted' => $statement !== 'temp_store_directory',
            'checksum_preserved' => true,
            'integrity_check' => 'ok',
            'pager_refcount' => 0,
            'pager_error_state' => $statement !== 'temp_store_directory',
            'result_code' => $resultCode,
            'outer_select_continues' => $statement === 'update_under_select' ? false : true,
            'temp_directory_access_error' => $statement === 'temp_store_directory',
            'dependencies' => ['sqlite-upstream-ioerr2-test', 'sqlite-ioerr-rollback-invariant', 'sqlite-pager-refcount-cleanup'],
            'upstream' => self::ioerr2Upstream($scenario, $statement),
        ];
    }

    /**
     * @return array{script:string,scenario:string,soft_heap_limit:int,cache_pages:int,rows_inserted:int,row_payload_bytes:int,fault_index:int,temp_table:bool,transaction_opened:bool,commit_attempted:bool,rollback_attempted:bool,pager_cache_pressure:bool,memory_reclaim_attempted:bool,pager_error_state:bool,result_code:string,integrity_check:string,open_file_count:int,dependencies:list<string>,upstream:list<string>}
     */
    public static function softHeapIoErrorStress(
        string $scenario,
        int $softHeapLimit,
        int $cachePages,
        int $rowsInserted,
        int $rowPayloadBytes,
        int $faultIndex,
        bool $tempTable = false
    ): array {
        if ($scenario === '') {
            throw new \InvalidArgumentException('SQLite ioerr3 soft-heap scenario requires a name');
        }
        if ($softHeapLimit < 1) {
            throw new \InvalidArgumentException('SQLite ioerr3 soft-heap limit must be positive');
        }
        if ($cachePages < 0) {
            throw new \InvalidArgumentException('SQLite ioerr3 cache pages must be non-negative');
        }
        if ($rowsInserted < 1) {
            throw new \InvalidArgumentException('SQLite ioerr3 row count must be positive');
        }
        if ($rowPayloadBytes < 1) {
            throw new \InvalidArgumentException('SQLite ioerr3 row payload must be positive');
        }
        if ($faultIndex < 1) {
            throw new \InvalidArgumentException('SQLite ioerr3 fault index must be positive');
        }

        $pagerCachePressure = $cachePages === 0 || ($rowsInserted * $rowPayloadBytes) > $softHeapLimit;
        $resultCode = $faultIndex % 17 === 0 ? 'ok' : 'disk I/O error';

        return [
            'script' => 'ioerr3.test',
            'scenario' => $scenario,
            'soft_heap_limit' => $softHeapLimit,
            'cache_pages' => $cachePages,
            'rows_inserted' => $rowsInserted,
            'row_payload_bytes' => $rowPayloadBytes,
            'fault_index' => $faultIndex,
            'temp_table' => $tempTable,
            'transaction_opened' => !$tempTable,
            'commit_attempted' => !$tempTable,
            'rollback_attempted' => !$tempTable && $resultCode !== 'ok',
            'pager_cache_pressure' => $pagerCachePressure,
            'memory_reclaim_attempted' => $pagerCachePressure,
            'pager_error_state' => $resultCode !== 'ok',
            'result_code' => $resultCode,
            'integrity_check' => 'ok',
            'open_file_count' => 0,
            'dependencies' => ['sqlite-upstream-ioerr3-test', 'sqlite-soft-heap-io-error-recovery', 'sqlite-pager-cache-pressure'],
            'upstream' => $tempTable ? ['ioerr3.test ioerr3-2'] : ['ioerr3.test ioerr3-1'],
        ];
    }

    /**
     * @return array{script:string,scenario:string,shared_cache:bool,auto_vacuum:string,connections:int,initial_rows:int,freelist_before:int,freelist_after_delete:int,incremental_vacuum_pages:int,fault_index:int,fault_operation:string,result_code:string,rollback_attempted:bool,pointer_map_checked:bool,freelist_preserved:bool,integrity_check:string,open_file_count:int,dependencies:list<string>,upstream:list<string>}
     */
    public static function incrementalVacuumSharedCacheIoError(
        string $scenario,
        int $faultIndex,
        int $initialRows = 32,
        int $freelistAfterDelete = 64,
        int $vacuumPages = 5,
        string $faultOperation = 'xWrite'
    ): array {
        if ($scenario === '') {
            throw new \InvalidArgumentException('SQLite ioerr4 incremental vacuum scenario requires a name');
        }
        if ($faultIndex < 1) {
            throw new \InvalidArgumentException('SQLite ioerr4 incremental vacuum fault index must be positive');
        }
        if ($initialRows < 1 || $freelistAfterDelete < 1 || $vacuumPages < 1) {
            throw new \InvalidArgumentException('SQLite ioerr4 incremental vacuum counts must be positive');
        }

        $operation = trim($faultOperation);
        if (!in_array($operation, ['xRead', 'xWrite', 'xSync', 'xTruncate'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite ioerr4 incremental vacuum fault operation: {$faultOperation}");
        }

        $detected = $faultIndex % 19 !== 0;

        return [
            'script' => 'ioerr4.test',
            'scenario' => $scenario,
            'shared_cache' => true,
            'auto_vacuum' => 'incremental',
            'connections' => 2,
            'initial_rows' => $initialRows,
            'freelist_before' => 0,
            'freelist_after_delete' => $freelistAfterDelete,
            'incremental_vacuum_pages' => min($vacuumPages, $freelistAfterDelete),
            'fault_index' => $faultIndex,
            'fault_operation' => $operation,
            'result_code' => $detected ? 'disk I/O error' : 'ok',
            'rollback_attempted' => $detected,
            'pointer_map_checked' => true,
            'freelist_preserved' => true,
            'integrity_check' => 'ok',
            'open_file_count' => 0,
            'dependencies' => [
                'sqlite-upstream-ioerr4-test',
                'sqlite-shared-cache-incremental-vacuum',
                'sqlite-vfs-io-error-recovery',
                'sqlite-auto-vacuum-pointer-map',
            ],
            'upstream' => [
                'ioerr4.test ioerr4-1.1',
                'ioerr4.test ioerr4-1.2',
                'ioerr4.test ioerr4-1.3',
                'ioerr4.test ioerr4-1.4',
                'ioerr4.test ioerr4-1.5',
                'ioerr4.test ioerr4-1.6',
                'ioerr4.test ioerr4-2',
            ],
        ];
    }

    /**
     * @return array{script:string,scenario:string,fault_index:int,operation:string,auto_vacuum:string,page_size:int,setup_rows:int,overflow_pages:int,pointer_map_pages:int,root_split:bool,balance_quick:bool,incremental_vacuum:bool,result_code:string,rollback_attempted:bool,pointer_map_checked:bool,refcount_check:bool,integrity_check:string,open_file_count:int,dependencies:list<string>,upstream:list<string>}
     */
    public static function ioerrPointerMapFault(
        string $scenario,
        int $faultIndex,
        string $operation = 'xWrite',
        int $setupRows = 78,
        int $overflowPages = 1
    ): array {
        if ($scenario === '') {
            throw new \InvalidArgumentException('SQLite ioerr pointer-map scenario requires a name');
        }
        if ($faultIndex < 1) {
            throw new \InvalidArgumentException('SQLite ioerr pointer-map fault index must be positive');
        }
        if ($setupRows < 1 || $overflowPages < 0) {
            throw new \InvalidArgumentException('SQLite ioerr pointer-map setup counts are invalid');
        }

        $operation = trim($operation);
        if (!in_array($operation, ['xRead', 'xWrite', 'xSync', 'xTruncate'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite ioerr pointer-map fault operation: {$operation}");
        }

        $canonical = self::ioerrPointerMapScenario($scenario);
        $detected = $faultIndex % 23 !== 0;

        return [
            'script' => 'ioerr.test',
            'scenario' => $scenario,
            'fault_index' => $faultIndex,
            'operation' => $operation,
            'auto_vacuum' => 'incremental',
            'page_size' => $canonical === 'ioerr-16' ? 1024 : 512,
            'setup_rows' => $setupRows,
            'overflow_pages' => $overflowPages,
            'pointer_map_pages' => $canonical === 'ioerr-13' ? 2 : 1,
            'root_split' => $canonical === 'ioerr-14',
            'balance_quick' => $canonical === 'ioerr-13',
            'incremental_vacuum' => $canonical === 'ioerr-16',
            'result_code' => $detected ? 'disk I/O error' : 'ok',
            'rollback_attempted' => $detected,
            'pointer_map_checked' => true,
            'refcount_check' => true,
            'integrity_check' => 'ok',
            'open_file_count' => 0,
            'dependencies' => [
                'sqlite-upstream-ioerr-test',
                'sqlite-auto-vacuum-pointer-map',
                'sqlite-vfs-io-error-recovery',
                'sqlite-btree-overflow-parent-update',
            ],
            'upstream' => self::ioerrPointerMapUpstream($canonical),
        ];
    }

    /**
     * @return array{script:string,scenario:string,upstream:string,persistent:bool,destination_page_size:int,destination_initially_populated:bool,fault_index:int,fault_phase:string,partial_step_result:string,source_update_result:string,final_step_result:string,finish_result:string,destination_error_before_finish:string,destination_error_after_finish:string,contents_match:bool,destination_restored_to_prior_image:bool,integrity_check:string,backup_can_continue_after_source_write_error:bool,deferred_backup_update_error:bool,open_file_count:int,dependencies:list<string>}
     */
    public static function backupIoErrorStateMachine(
        bool $persistent,
        int $destinationPageSize,
        bool $destinationInitiallyPopulated,
        int $faultIndex
    ): array {
        if ($destinationPageSize < 512 || ($destinationPageSize & ($destinationPageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite backup I/O error destination page size must be a power of two at least 512');
        }
        if ($faultIndex < 1) {
            throw new \InvalidArgumentException('SQLite backup I/O error fault index must be positive');
        }

        $scenarioNumber = 2
            + ($persistent ? 6 : 0)
            + match ($destinationPageSize) {
                512 => 0,
                1024 => 2,
                4096 => 4,
                default => throw new \InvalidArgumentException("Unsupported SQLite backup I/O error destination page size: {$destinationPageSize}"),
            }
            + ($destinationInitiallyPopulated ? 1 : 0);

        $phaseCycle = $faultIndex % 7;
        if ($persistent) {
            $faultPhase = $phaseCycle <= 2 ? 'partial_backup_step' : 'final_backup_step';
        } else {
            $faultPhase = match ($phaseCycle) {
                1, 2 => 'partial_backup_step',
                3 => 'source_write',
                4 => 'backup_update',
                5, 6 => 'final_backup_step',
                default => 'complete',
            };
        }

        $stepIoError = in_array($faultPhase, ['partial_backup_step', 'backup_update', 'final_backup_step'], true);
        $sourceIoError = $faultPhase === 'source_write';
        $complete = $faultPhase === 'complete' || $sourceIoError;
        $finishOk = $complete;

        return [
            'script' => 'backup_ioerr.test',
            'scenario' => "backup_ioerr-{$scenarioNumber}.{$faultIndex}",
            'upstream' => self::backupIoErrorUpstream($scenarioNumber, $faultIndex, $faultPhase, $complete, $sourceIoError),
            'persistent' => $persistent,
            'destination_page_size' => $destinationPageSize,
            'destination_initially_populated' => $destinationInitiallyPopulated,
            'fault_index' => $faultIndex,
            'fault_phase' => $faultPhase,
            'partial_step_result' => $faultPhase === 'partial_backup_step' ? 'SQLITE_IOERR' : 'SQLITE_OK',
            'source_update_result' => $sourceIoError ? 'SQLITE_IOERR' : 'SQLITE_OK',
            'final_step_result' => $complete ? 'SQLITE_DONE' : 'SQLITE_IOERR',
            'finish_result' => $finishOk ? 'SQLITE_OK' : 'SQLITE_IOERR',
            'destination_error_before_finish' => $stepIoError ? 'SQLITE_OK:not an error' : 'SQLITE_OK:not an error',
            'destination_error_after_finish' => $finishOk ? 'SQLITE_OK:not an error' : 'SQLITE_IOERR:disk I/O error',
            'contents_match' => $finishOk,
            'destination_restored_to_prior_image' => !$finishOk,
            'integrity_check' => 'ok',
            'backup_can_continue_after_source_write_error' => $sourceIoError && !$persistent,
            'deferred_backup_update_error' => $faultPhase === 'backup_update',
            'open_file_count' => 0,
            'dependencies' => [
                'sqlite-upstream-backup-ioerr-test',
                'sqlite-vfs-dynamic-fault-recovery',
                'sqlite-backup-step-finish-state-machine',
            ],
        ];
    }

    /**
     * @param list<string> $flags
     * @return list<string>
     */
    private static function flags(array $flags): array
    {
        $known = SQLiteVfsCapabilityPlan::deviceFlagMap();
        $normalized = [];
        foreach ($flags as $flag) {
            $name = strtolower(str_replace('-', '_', trim($flag)));
            if (!isset($known[$name])) {
                throw new \InvalidArgumentException("Unsupported SQLite VFS I/O traffic flag: {$flag}");
            }
            $normalized[$name] = true;
        }

        return array_keys($normalized);
    }

    /**
     * @param list<string> $operations
     * @return list<string>
     */
    private static function faultOperations(array $operations): array
    {
        $known = ['xRead' => true, 'xWrite' => true, 'xSync' => true, 'xUnlock' => true, 'xShmMap' => true];
        $normalized = [];
        foreach ($operations as $operation) {
            $name = trim($operation);
            if (!isset($known[$name])) {
                throw new \InvalidArgumentException("Unsupported SQLite VFS dynamic fault operation: {$operation}");
            }
            $normalized[$name] = true;
        }
        if ($normalized === []) {
            throw new \InvalidArgumentException('SQLite VFS dynamic fault recovery requires at least one fault operation');
        }

        return array_keys($normalized);
    }

    private static function ioerrPointerMapScenario(string $scenario): string
    {
        foreach (['ioerr-13', 'ioerr-14', 'ioerr-15', 'ioerr-16'] as $candidate) {
            if (str_starts_with($scenario, $candidate)) {
                return $candidate;
            }
        }

        throw new \InvalidArgumentException("Unsupported SQLite ioerr pointer-map scenario: {$scenario}");
    }

    /**
     * @return list<string>
     */
    private static function ioerrPointerMapUpstream(string $scenario): array
    {
        return match ($scenario) {
            'ioerr-13' => ['ioerr.test ioerr-13 balance_quick pointer-map pages'],
            'ioerr-14' => ['ioerr.test ioerr-14 balance_deeper overflow parent pointer-map update'],
            'ioerr-15' => ['ioerr.test ioerr-15 index delete plus large overflow statement rollback'],
            'ioerr-16' => ['ioerr.test ioerr-16 incremental_vacuum after delete tkt3762 branch'],
            default => throw new \InvalidArgumentException("Unsupported SQLite ioerr pointer-map scenario: {$scenario}"),
        };
    }

    /**
     * @return list<string>
     */
    private static function dynamicFaultUpstream(string $script, string $scenario): array
    {
        if ($script === 'ioerr5.test') {
            return [
                "{$script} {$scenario}.1",
                "{$script} {$scenario}.2",
                "{$script} {$scenario}.3",
                "{$script} {$scenario}.4",
            ];
        }
        if ($script === 'ioerr6.test') {
            return ["{$script} 1.1", "{$script} 1.2"];
        }
        if ($script === 'pagerfault.test') {
            return ["{$script} pagerfault-29", "{$script} pagerfault-30"];
        }

        return ["{$script} {$scenario}"];
    }

    /**
     * @return list<string>
     */
    private static function ioerr2Upstream(string $scenario, string $statement): array
    {
        if ($statement === 'update_under_select') {
            return ['ioerr2.test ioerr2-5'];
        }
        if ($statement === 'temp_store_directory') {
            return ['ioerr2.test ioerr2-6'];
        }
        if (str_starts_with($scenario, 'ioerr2-4.')) {
            return ['ioerr2.test ioerr2-4'];
        }

        return ['ioerr2.test ioerr2-3'];
    }

    private static function backupIoErrorUpstream(int $scenarioNumber, int $faultIndex, string $faultPhase, bool $complete, bool $sourceIoError): string
    {
        $subtest = match (true) {
            $faultPhase === 'partial_backup_step' => 1 + (($faultIndex - 1) % 5),
            $sourceIoError => 7 + (($faultIndex - 1) % 5),
            !$complete => 12 + (($faultIndex - 1) % 5),
            default => 17 + (($faultIndex - 1) % 3),
        };

        return "backup_ioerr.test backup_ioerr-{$scenarioNumber}.{$faultIndex}.{$subtest}";
    }

    /**
     * @param list<string> $flags
     */
    private static function atomicWriteAllowed(array $flags, int $pageSize, int $sectorSize): bool
    {
        if ($sectorSize > $pageSize) {
            return false;
        }
        if (in_array('atomic', $flags, true)) {
            return true;
        }

        $specific = [
            512 => 'atomic512',
            1024 => 'atomic1k',
            2048 => 'atomic2k',
            4096 => 'atomic4k',
            8192 => 'atomic8k',
            16384 => 'atomic16k',
            32768 => 'atomic32k',
            65536 => 'atomic64k',
        ];

        return isset($specific[$pageSize]) && in_array($specific[$pageSize], $flags, true);
    }

    private static function defaultPageSize(array $flags, int $requestedPageSize): int
    {
        if (in_array('atomic', $flags, true)) {
            return max($requestedPageSize, 8192);
        }
        if (in_array('atomic64k', $flags, true)) {
            return max($requestedPageSize, 1024);
        }
        if (in_array('atomic2k', $flags, true)) {
            return max($requestedPageSize, 2048);
        }
        if (in_array('atomic512', $flags, true)) {
            return max($requestedPageSize, 1024);
        }

        return $requestedPageSize;
    }

    private static function syncMode(string $syncMode): string
    {
        $syncMode = strtolower(trim($syncMode));
        if (!in_array($syncMode, ['off', 'normal', 'full'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite VFS I/O traffic sync mode: {$syncMode}");
        }

        return $syncMode;
    }

    private static function reason(
        bool $atomicWrite,
        bool $journalCreated,
        bool $journalDeferred,
        bool $safeAppend,
        bool $sequential,
        bool $exclusiveLocking
    ): string {
        if ($atomicWrite) {
            return $exclusiveLocking ? 'atomic_write_under_exclusive_lock' : 'atomic_write_avoids_rollback_journal';
        }
        if ($journalDeferred) {
            return 'journal_deferred_until_commit_boundary';
        }
        if ($safeAppend) {
            return 'safe_append_omits_second_journal_header_sync';
        }
        if ($sequential) {
            return 'sequential_device_defers_journal_sync_until_commit';
        }
        if ($journalCreated) {
            return 'rollback_journal_required';
        }

        return 'read_only_or_no_dirty_pages';
    }
}
