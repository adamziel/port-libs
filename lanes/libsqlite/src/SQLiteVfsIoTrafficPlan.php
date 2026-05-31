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
     * @param list<string> $deviceFlags
     * @return array{script:string,scenario:string,device_flags:list<string>,sector_size:int,requested_page_size:int,max_page_size:int,selected_page_size:int,database_file_bytes_after_create:int,atomic_family:string|null,sector_driven:bool,atomic_driven:bool,clamped_to_max:bool,dependencies:list<string>,upstream:list<string>}
     */
    public static function defaultPageSizeSelection(
        string $scenario,
        array $deviceFlags,
        int $sectorSize,
        int $requestedPageSize = 1024,
        int $maxPageSize = 8192
    ): array {
        if ($scenario === '') {
            throw new \InvalidArgumentException('SQLite VFS default page-size scenario requires a name');
        }
        if ($sectorSize < 0 || ($sectorSize > 0 && ($sectorSize & ($sectorSize - 1)) !== 0)) {
            throw new \InvalidArgumentException('SQLite VFS default page-size sector size must be zero or a power of two');
        }
        if ($requestedPageSize < 512 || ($requestedPageSize & ($requestedPageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite VFS default page-size request must be a power of two at least 512');
        }
        if ($maxPageSize < 512 || ($maxPageSize & ($maxPageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite VFS default page-size max must be a power of two at least 512');
        }

        $flags = self::flags($deviceFlags);
        $atomicFloor = self::atomicPageSizeFloor($flags);
        $sectorFloor = $sectorSize === 0 ? 512 : $sectorSize;
        $selected = max($requestedPageSize, min($sectorFloor, $maxPageSize));
        if ($atomicFloor !== null) {
            $selected = max($selected, min($atomicFloor, $maxPageSize));
        }
        $selected = min($selected, $maxPageSize);

        return [
            'script' => 'io.test',
            'scenario' => $scenario,
            'device_flags' => $flags,
            'sector_size' => $sectorSize,
            'requested_page_size' => $requestedPageSize,
            'max_page_size' => $maxPageSize,
            'selected_page_size' => $selected,
            'database_file_bytes_after_create' => $selected * 2,
            'atomic_family' => $atomicFloor === null ? null : 'atomic' . $atomicFloor,
            'sector_driven' => $selected > $requestedPageSize && $selected === min($sectorFloor, $maxPageSize),
            'atomic_driven' => $atomicFloor !== null && $selected === min(max($atomicFloor, $requestedPageSize), $maxPageSize),
            'clamped_to_max' => $sectorSize > $maxPageSize || ($atomicFloor !== null && $atomicFloor > $maxPageSize),
            'dependencies' => [
                'sqlite-upstream-io-test',
                'sqlite-vfs-device-characteristics',
                'sqlite-default-page-size-selection',
            ],
            'upstream' => ['io.test io-5.* default page size selected from sector size and atomic capability'],
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
     * @return array{script:string,scenario:string,writer_connection:string,reader_connection:string,writer_holds_reserved:bool,requester_holds_read_lock:bool,operation:string,busy_handler_registered:bool,busy_callback_invoked:bool,busy_callback_counts:list<int>,busy_break_count:int|null,result_code:string,result_message:string,reader_can_select:bool,writer_transaction_open:bool,rollback_required:bool,dependencies:list<string>,upstream:list<string>}
     */
    public static function lockBusyCallbackProfile(
        string $scenario,
        bool $requesterHoldsReadLock,
        int $busyBreakCount = 0,
        string $operation = 'update'
    ): array {
        if ($scenario === '') {
            throw new \InvalidArgumentException('SQLite lock busy callback scenario requires a name');
        }
        if ($busyBreakCount < 0) {
            throw new \InvalidArgumentException('SQLite lock busy callback break count must be non-negative');
        }

        $operation = strtolower(trim($operation));
        if (!in_array($operation, ['update', 'insert', 'delete'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite lock busy callback operation: {$operation}");
        }

        $callbackInvoked = !$requesterHoldsReadLock;
        $counts = $callbackInvoked ? range(0, $busyBreakCount) : [];

        return [
            'script' => 'lock.test',
            'scenario' => $scenario,
            'writer_connection' => 'db',
            'reader_connection' => 'db2',
            'writer_holds_reserved' => true,
            'requester_holds_read_lock' => $requesterHoldsReadLock,
            'operation' => $operation,
            'busy_handler_registered' => true,
            'busy_callback_invoked' => $callbackInvoked,
            'busy_callback_counts' => $counts,
            'busy_break_count' => $callbackInvoked ? $busyBreakCount : null,
            'result_code' => 'SQLITE_BUSY',
            'result_message' => 'database is locked',
            'reader_can_select' => true,
            'writer_transaction_open' => true,
            'rollback_required' => true,
            'dependencies' => [
                'sqlite-upstream-lock-test',
                'sqlite-vfs-reserved-lock-contention',
                'sqlite-busy-handler-callback-sequence',
            ],
            'upstream' => [
                'lock.test lock-2.1 writer obtains RESERVED lock',
                'lock.test lock-2.2 reader can SELECT while writer holds RESERVED',
                'lock.test lock-2.3 busy callback skipped when requester already holds read lock',
                'lock.test lock-2.4 busy callback repeats until callback break',
            ],
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
     * @param list<string> $deviceFlags
     * @return array{script:string,scenario:string,page_size:int,sector_size:int,device_flags:list<string>,changed_pages:int,appended_pages:int,multi_file_commit:bool,exclusive_locking:bool,atomic_write:bool,journal_exists_before_commit:bool,journal_created_at_commit:bool,commit_result:string,rows_visible_before_commit:bool,rows_visible_after_commit:bool,rollback_restores_prior_rows:bool,write_count:int,sync_count:int,change_counter_written_out_of_band:bool,reason:string,dependencies:list<string>,upstream:list<string>}
     */
    public static function atomicWriteJournalDecision(
        string $scenario,
        int $pageSize,
        int $sectorSize,
        array $deviceFlags,
        int $changedPages = 1,
        int $appendedPages = 0,
        bool $multiFileCommit = false,
        bool $exclusiveLocking = false,
        bool $commitJournalOpenBlocked = false
    ): array {
        if ($scenario === '') {
            throw new \InvalidArgumentException('SQLite VFS atomic-write scenario requires a name');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite VFS atomic-write page size must be a power of two at least 512');
        }
        if ($sectorSize < 0 || ($sectorSize > 0 && ($sectorSize & ($sectorSize - 1)) !== 0)) {
            throw new \InvalidArgumentException('SQLite VFS atomic-write sector size must be zero or a power of two');
        }
        if ($changedPages < 0 || $appendedPages < 0) {
            throw new \InvalidArgumentException('SQLite VFS atomic-write page counts must be non-negative');
        }

        $flags = self::flags($deviceFlags);
        $effectiveSectorSize = $sectorSize === 0 ? 512 : $sectorSize;
        $atomicWrite = self::atomicWriteAllowed($flags, $pageSize, $effectiveSectorSize)
            && $changedPages === 1
            && $appendedPages === 0
            && !$multiFileCommit;
        $journalBeforeCommit = !$atomicWrite
            && $changedPages > 0
            && ($appendedPages > 0 || $changedPages > 1 || $effectiveSectorSize > $pageSize);
        $journalAtCommit = !$atomicWrite
            && $changedPages > 0
            && !$journalBeforeCommit;
        $commitResult = $commitJournalOpenBlocked && ($journalBeforeCommit || $journalAtCommit)
            ? 'unable to open database file'
            : 'ok';

        return [
            'script' => 'io.test',
            'scenario' => $scenario,
            'page_size' => $pageSize,
            'sector_size' => $sectorSize,
            'device_flags' => $flags,
            'changed_pages' => $changedPages,
            'appended_pages' => $appendedPages,
            'multi_file_commit' => $multiFileCommit,
            'exclusive_locking' => $exclusiveLocking,
            'atomic_write' => $atomicWrite,
            'journal_exists_before_commit' => $journalBeforeCommit,
            'journal_created_at_commit' => $journalAtCommit,
            'commit_result' => $commitResult,
            'rows_visible_before_commit' => false,
            'rows_visible_after_commit' => $commitResult === 'ok',
            'rollback_restores_prior_rows' => $commitResult !== 'ok' || str_contains($scenario, '2.8'),
            'write_count' => $atomicWrite ? 2 : max(2, $changedPages + $appendedPages + 1),
            'sync_count' => $atomicWrite ? 1 : (($changedPages === 0) ? 0 : 4),
            'change_counter_written_out_of_band' => $atomicWrite,
            'reason' => $atomicWrite
                ? 'io_test_atomic_write_avoids_journal_until_commit_visibility'
                : ($journalAtCommit ? 'io_test_atomic_deferred_journal_commit_boundary' : 'io_test_rollback_journal_required'),
            'dependencies' => [
                'sqlite-upstream-io-test',
                'sqlite-vfs-device-characteristics',
                'sqlite-atomic-write-commit-visibility',
            ],
            'upstream' => self::atomicWriteUpstream($scenario),
        ];
    }

    /**
     * @param list<string> $deviceFlags
     * @return array{script:string,scenario:string,sql_kind:string,crash_file:string,delay:int,device_flags:list<string>,initial_rows:int,expected_rows_after_success:int|null,integrity_check:string,content_either_prior_or_success:bool,journal_sync_may_be_absent:bool,database_sync_crash_boundary:bool,journal_sync_crash_boundary:bool,safe_append_header_valid:bool,sequential_order_preserved:bool,atomic_write_short_circuits_journal_crash:bool,dependencies:list<string>,upstream:list<string>}
     */
    public static function crashRecoveryDeviceProfile(
        string $scenario,
        string $sqlKind,
        string $crashFile,
        int $delay,
        array $deviceFlags,
        int $iteration
    ): array {
        if ($scenario === '' || $sqlKind === '' || $crashFile === '') {
            throw new \InvalidArgumentException('SQLite crash recovery profile requires scenario, SQL kind, and crash file');
        }
        if ($delay < 1 || $iteration < 0) {
            throw new \InvalidArgumentException('SQLite crash recovery delay and iteration are invalid');
        }

        $sqlKind = strtolower(trim($sqlKind));
        if (!in_array($sqlKind, ['insert', 'delete', 'insert_select', 'update', 'large_insert', 'create_table', 'mixed_delete_insert'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite crash recovery SQL kind: {$sqlKind}");
        }
        $crashFile = strtolower(trim($crashFile));
        if (!in_array($crashFile, ['database', 'journal'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite crash recovery crash file: {$crashFile}");
        }

        $flags = self::flags($deviceFlags);
        $atomic = in_array('atomic', $flags, true) || in_array('atomic1k', $flags, true) || in_array('atomic2k', $flags, true);
        $safeAppend = in_array('safe_append', $flags, true);
        $sequential = in_array('sequential', $flags, true);
        $successRows = match ($sqlKind) {
            'insert' => 2,
            'delete' => 0,
            'insert_select' => 2,
            'update' => 1,
            'mixed_delete_insert' => 32 + ($iteration % 97),
            default => null,
        };

        return [
            'script' => 'crash3.test',
            'scenario' => $scenario,
            'sql_kind' => $sqlKind,
            'crash_file' => $crashFile,
            'delay' => $delay,
            'device_flags' => $flags,
            'initial_rows' => $sqlKind === 'mixed_delete_insert' ? 64 : 1,
            'expected_rows_after_success' => $successRows,
            'integrity_check' => 'ok',
            'content_either_prior_or_success' => true,
            'journal_sync_may_be_absent' => $atomic && $crashFile === 'journal',
            'database_sync_crash_boundary' => $crashFile === 'database',
            'journal_sync_crash_boundary' => $crashFile === 'journal',
            'safe_append_header_valid' => !$safeAppend || $delay >= 1,
            'sequential_order_preserved' => !$sequential || $delay >= 1,
            'atomic_write_short_circuits_journal_crash' => $atomic && $crashFile === 'journal',
            'dependencies' => [
                'sqlite-upstream-crash3-test',
                'sqlite-vfs-device-characteristics',
                'sqlite-crash-recovery-content-boundary',
            ],
            'upstream' => self::crashRecoveryUpstream($scenario),
        ];
    }

    /**
     * @return array{script:string,scenario:string,syscall:string,errno:string,fault_index:int,persistent:bool,result_code:string,transient_retry:bool,readonly_possible:bool,large_file_possible:bool,lock_error_possible:bool,wal_rows_visible:bool,attached_rows_visible:bool,temp_rows_visible:bool,open_file_count:int,integrity_check:string,dependencies:list<string>,upstream:list<string>}
     */
    public static function syscallFaultProfile(
        string $scenario,
        string $syscall,
        string $errno,
        int $faultIndex,
        bool $persistent = false
    ): array {
        if ($scenario === '' || $syscall === '' || $errno === '') {
            throw new \InvalidArgumentException('SQLite sysfault profile requires scenario, syscall, and errno');
        }
        if ($faultIndex < 1) {
            throw new \InvalidArgumentException('SQLite sysfault profile requires a positive fault index');
        }

        $syscall = strtolower(trim($syscall));
        $errno = strtoupper(trim($errno));
        $supported = [
            'open' => true,
            'getcwd' => true,
            'fstat' => true,
            'fcntl' => true,
            'ftruncate' => true,
            'close' => true,
            'read' => true,
            'pread' => true,
            'pread64' => true,
            'write' => true,
            'fallocate' => true,
            'mmap' => true,
        ];
        if (!isset($supported[$syscall])) {
            throw new \InvalidArgumentException("Unsupported SQLite sysfault syscall: {$syscall}");
        }

        $transientRetry = $errno === 'EINTR';
        $lockErrnos = ['EAGAIN' => true, 'ETIMEDOUT' => true, 'EBUSY' => true, 'EINTR' => true, 'ENOLCK' => true, 'EACCES' => true, 'EPERM' => true, 'EDEADLK' => true, 'ENOMEM' => true];
        $lockFault = $syscall === 'fcntl';
        $largeFile = $syscall === 'fstat' && $errno === 'EOVERFLOW';
        $readonly = in_array($syscall, ['open', 'getcwd'], true) && $persistent && ($faultIndex % 2) === 0;
        $mmapFault = $syscall === 'mmap';

        $resultCode = 'ok';
        if (!$transientRetry || $persistent) {
            if ($readonly) {
                $resultCode = 'attempt to write a readonly database';
            } elseif ($largeFile) {
                $resultCode = 'large file support is disabled';
            } elseif ($lockFault && isset($lockErrnos[$errno])) {
                $resultCode = $errno === 'EPERM' ? 'access permission denied' : (($errno === 'EDEADLK' || $errno === 'ENOMEM') ? 'disk I/O error' : 'database is locked');
            } elseif ($mmapFault) {
                $resultCode = 'disk I/O error';
            } else {
                $resultCode = in_array($syscall, ['open', 'getcwd'], true) ? 'unable to open database file' : 'disk I/O error';
            }
        }

        $body = self::sysfaultBody($scenario);
        $rowsVisible = $resultCode === 'ok';

        return [
            'script' => 'sysfault.test',
            'scenario' => $scenario,
            'syscall' => $syscall,
            'errno' => $errno,
            'fault_index' => $faultIndex,
            'persistent' => $persistent,
            'result_code' => $resultCode,
            'transient_retry' => $transientRetry && !$persistent,
            'readonly_possible' => $readonly,
            'large_file_possible' => $largeFile,
            'lock_error_possible' => $lockFault && isset($lockErrnos[$errno]),
            'wal_rows_visible' => $rowsVisible && $body === 'wal_open_write',
            'attached_rows_visible' => $rowsVisible && $body === 'attached_commit',
            'temp_rows_visible' => $rowsVisible && $body === 'attached_commit',
            'open_file_count' => 0,
            'integrity_check' => $resultCode === 'ok' || $mmapFault ? 'ok' : 'not-run-after-fault',
            'dependencies' => [
                'sqlite-upstream-sysfault-test',
                'sqlite-vfs-syscall-faultsim',
                'sqlite-vfs-dynamic-fault-recovery',
            ],
            'upstream' => self::sysfaultUpstream($scenario, $syscall),
        ];
    }

    /**
     * @return array{script:string,scenario:string,blob_rowid:int,reopen_rowid:int|null,read_bytes:int,fault_index:int,fault_operation:string,opened_blob:bool,reopen_attempted:bool,reopen_result:string,read_attempted:bool,read_result:string,result_payload:string|null,handle_must_close:bool,connection_error:string,integrity_check:string,open_file_count:int,dependencies:list<string>,upstream:list<string>}
     */
    public static function incrementalBlobFaultProfile(
        string $scenario,
        int $faultIndex,
        int $blobRowid = 1,
        ?int $reopenRowid = null,
        int $readBytes = 11,
        string $faultOperation = 'xRead'
    ): array {
        if ($scenario === '') {
            throw new \InvalidArgumentException('SQLite incremental BLOB fault scenario requires a name');
        }
        if ($faultIndex < 1) {
            throw new \InvalidArgumentException('SQLite incremental BLOB fault profile requires a positive fault index');
        }
        if ($blobRowid < 1 || $readBytes < 1) {
            throw new \InvalidArgumentException('SQLite incremental BLOB fault rowid and read length must be positive');
        }

        $operation = trim($faultOperation);
        if (!in_array($operation, ['xRead', 'xWrite', 'xSync', 'xTruncate'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite incremental BLOB fault operation: {$faultOperation}");
        }

        $canonical = self::incrementalBlobFaultScenario($scenario);
        $detected = $faultIndex % 31 !== 0;
        $reopenAttempted = $canonical === 'incrblobfault-1' || $canonical === 'incrblobfault-2';
        $targetRowid = $reopenRowid ?? ($canonical === 'incrblobfault-2' ? -1 : 1000);
        $missingRowid = $targetRowid < 1 || $targetRowid > 1024;
        $readAttempted = $canonical === 'incrblobfault-3';

        $reopenResult = 'not_attempted';
        if ($reopenAttempted) {
            if ($detected && $operation === 'xRead') {
                $reopenResult = 'disk I/O error';
            } elseif ($missingRowid) {
                $reopenResult = "no such rowid: {$targetRowid}";
            } else {
                $reopenResult = 'ok';
            }
        }

        $readResult = 'not_attempted';
        $payload = null;
        if ($readAttempted) {
            $readResult = ($detected && $operation === 'xRead') ? 'disk I/O error' : 'ok';
            $payload = $readResult === 'ok' ? substr('hello world', 0, $readBytes) : null;
        }

        return [
            'script' => 'incrblobfault.test',
            'scenario' => $scenario,
            'blob_rowid' => $blobRowid,
            'reopen_rowid' => $reopenAttempted ? $targetRowid : null,
            'read_bytes' => $readBytes,
            'fault_index' => $faultIndex,
            'fault_operation' => $operation,
            'opened_blob' => true,
            'reopen_attempted' => $reopenAttempted,
            'reopen_result' => $reopenResult,
            'read_attempted' => $readAttempted,
            'read_result' => $readResult,
            'result_payload' => $payload,
            'handle_must_close' => true,
            'connection_error' => ($reopenResult === 'disk I/O error' || $readResult === 'disk I/O error') ? 'disk I/O error' : 'not an error',
            'integrity_check' => 'ok',
            'open_file_count' => 0,
            'dependencies' => [
                'sqlite-upstream-incrblobfault-test',
                'sqlite-incremental-blob-reopen',
                'sqlite-vfs-dynamic-fault-recovery',
            ],
            'upstream' => self::incrementalBlobFaultUpstream($canonical),
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

    private static function sysfaultBody(string $scenario): string
    {
        if (str_starts_with($scenario, 'sysfault-1')) {
            return 'wal_open_write';
        }
        if (str_starts_with($scenario, 'sysfault-2')) {
            return 'attached_commit';
        }
        if (str_starts_with($scenario, 'sysfault-3')) {
            return 'large_insert';
        }
        if (str_starts_with($scenario, 'sysfault-4')) {
            return 'mmap_read';
        }

        throw new \InvalidArgumentException("Unsupported SQLite sysfault scenario: {$scenario}");
    }

    private static function incrementalBlobFaultScenario(string $scenario): string
    {
        foreach (['incrblobfault-1', 'incrblobfault-2', 'incrblobfault-3'] as $candidate) {
            if (str_starts_with($scenario, $candidate)) {
                return $candidate;
            }
        }

        throw new \InvalidArgumentException("Unsupported SQLite incremental BLOB fault scenario: {$scenario}");
    }

    /**
     * @return list<string>
     */
    private static function incrementalBlobFaultUpstream(string $scenario): array
    {
        return match ($scenario) {
            'incrblobfault-1' => ['incrblobfault.test 1 sqlite3_blob_reopen high rowid faultsim returns ok or connection error'],
            'incrblobfault-2' => ['incrblobfault.test 2 sqlite3_blob_reopen negative rowid returns no such rowid or disk I/O error'],
            'incrblobfault-3' => ['incrblobfault.test 3 incremental blob open/read returns hello world under faultsim'],
            default => throw new \InvalidArgumentException("Unsupported SQLite incremental BLOB fault scenario: {$scenario}"),
        };
    }

    /**
     * @return list<string>
     */
    private static function sysfaultUpstream(string $scenario, string $syscall): array
    {
        if (str_starts_with($scenario, 'sysfault-1.3')) {
            return ["sysfault.test {$scenario} fcntl lock fault"];
        }
        if (str_starts_with($scenario, 'sysfault-1.2')) {
            return ["sysfault.test {$scenario} fstat open/write fault"];
        }
        if (str_starts_with($scenario, 'sysfault-1')) {
            return ["sysfault.test {$scenario} open/getcwd WAL open fault"];
        }
        if (str_starts_with($scenario, 'sysfault-2.1')) {
            return ["sysfault.test {$scenario} transient EINTR retry for {$syscall}"];
        }
        if (str_starts_with($scenario, 'sysfault-2.2')) {
            return ["sysfault.test {$scenario} persistent syscall fault during attached commit"];
        }
        if (str_starts_with($scenario, 'sysfault-3')) {
            return ["sysfault.test {$scenario} fstat/fallocate large insert fault"];
        }
        if (str_starts_with($scenario, 'sysfault-4')) {
            return ["sysfault.test {$scenario} mmap EACCES read fault"];
        }

        return ["sysfault.test {$scenario}"];
    }

    /**
     * @return list<string>
     */
    private static function atomicWriteUpstream(string $scenario): array
    {
        if (str_starts_with($scenario, 'io-2.4')) {
            return ['io.test io-2.4 atomic write journal absence and second-connection visibility'];
        }
        if (str_starts_with($scenario, 'io-2.5')) {
            return ['io.test io-2.5 multi-page transaction forces rollback journal'];
        }
        if (str_starts_with($scenario, 'io-2.6')) {
            return ['io.test io-2.6 append-page commit opens deferred journal and rolls back on open failure'];
        }
        if (str_starts_with($scenario, 'io-2.7')) {
            return ['io.test io-2.7 multi-file commit opens journals at commit boundary'];
        }
        if (str_starts_with($scenario, 'io-2.8')) {
            return ['io.test io-2.8 rollback before deferred journal creation restores rows'];
        }
        if (str_starts_with($scenario, 'io-2.9')) {
            return ['io.test io-2.9 sector-size larger than page-size disables atomic write'];
        }
        if (str_starts_with($scenario, 'io-2.10')) {
            return ['io.test io-2.10 specific IOCAP_ATOMIC1K/2K flags gate journal creation'];
        }
        if (str_starts_with($scenario, 'io-2.11')) {
            return ['io.test io-2.11 exclusive locking keeps atomic write journal-free'];
        }

        return ['io.test io-2 atomic-write optimization'];
    }

    /**
     * @return list<string>
     */
    private static function crashRecoveryUpstream(string $scenario): array
    {
        if (str_starts_with($scenario, 'crash3-1.')) {
            return ['crash3.test crash3-1 atomic IOCAP crash recovery keeps prior or completed content'];
        }
        if (str_starts_with($scenario, 'crash3-2.')) {
            return ['crash3.test crash3-2 sequential/safe_append crash recovery preserves integrity'];
        }
        if (str_starts_with($scenario, 'crash3-3.')) {
            return ['crash3.test crash3-3 sequential atomic journal corner case'];
        }

        return ['crash3.test'];
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

    /**
     * @param list<string> $flags
     */
    private static function atomicPageSizeFloor(array $flags): ?int
    {
        $floors = [
            'atomic' => 8192,
            'atomic512' => 1024,
            'atomic1k' => 1024,
            'atomic2k' => 2048,
            'atomic4k' => 4096,
            'atomic8k' => 8192,
            'atomic16k' => 16384,
            'atomic32k' => 32768,
            'atomic64k' => 65536,
        ];

        $floor = null;
        foreach ($flags as $flag) {
            if (isset($floors[$flag])) {
                $floor = max($floor ?? 0, $floors[$flag]);
            }
        }

        return $floor;
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
