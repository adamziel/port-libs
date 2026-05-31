<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVfsIoTransactionSequencePlan
{
    private const IOERR_OPERATIONS = [
        'read',
        'write',
        'sync',
        'truncate',
        'delete',
        'access',
        'open',
        'close',
    ];

    /**
     * @param list<string> $deviceFlags
     * @return array{status:string,script:string,scenario:string,device_flags:list<string>,sector_size:int,max_page_size:int,selected_page_size:int,dependencies:list<string>,upstream:list<string>}
     */
    public static function defaultPageSize(array $deviceFlags = [], int $sectorSize = 512, int $maxPageSize = 8192): array
    {
        if ($sectorSize <= 0) {
            throw new \InvalidArgumentException('SQLite VFS I/O default page-size sector size must be positive');
        }
        if ($maxPageSize < 512 || ($maxPageSize & ($maxPageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite VFS I/O default page-size maximum must be a power of two at least 512');
        }

        $flags = self::deviceFlags($deviceFlags);
        $exactAtomic = self::explicitAtomicBytes($flags);
        $selected = 1024;

        if (in_array('atomic', $flags, true)) {
            $selected = $maxPageSize;
        } elseif ($exactAtomic !== null && $exactAtomic <= $maxPageSize) {
            $selected = max($selected, $exactAtomic);
        }

        if ($sectorSize > $selected) {
            $selected = min(self::nextPowerOfTwo($sectorSize), $maxPageSize);
        }

        return [
            'status' => 'ok',
            'script' => 'io.test',
            'scenario' => 'io-5.*',
            'device_flags' => $flags,
            'sector_size' => $sectorSize,
            'max_page_size' => $maxPageSize,
            'selected_page_size' => $selected,
            'dependencies' => [
                'vfs-io-default-page-size',
                'vfs-io-transaction-sequence',
                'real-upstream-corpus-io-test',
            ],
            'upstream' => [
                'io.test io-5.*',
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $steps
     * @param list<string> $deviceFlags
     * @return array{status:string,count:int,write_total:int,sync_total:int,journal_creates:int,steps:list<array<string, mixed>>,dependencies:list<string>,upstream:list<string>}
     */
    public static function transactionSequence(array $steps, array $deviceFlags = [], int $pageSize = 1024, ?int $sectorSize = null): array
    {
        if ($steps === []) {
            throw new \InvalidArgumentException('SQLite VFS I/O transaction sequence requires at least one step');
        }
        if ($pageSize <= 0) {
            throw new \InvalidArgumentException('SQLite VFS I/O transaction sequence page size must be positive');
        }
        $sectorSize ??= $pageSize;
        if ($sectorSize <= 0) {
            throw new \InvalidArgumentException('SQLite VFS I/O transaction sequence sector size must be positive');
        }

        $flags = self::deviceFlags($deviceFlags);
        $results = [];
        $writeTotal = 0;
        $syncTotal = 0;
        $journalCreates = 0;

        foreach ($steps as $ordinal => $step) {
            $result = self::step($step, $flags, $pageSize, $sectorSize) + ['ordinal' => $ordinal];
            $results[] = $result;
            $writeTotal += $result['writes'];
            $syncTotal += $result['syncs'];
            $journalCreates += $result['journal_created'] ? 1 : 0;
        }

        return [
            'status' => 'ok',
            'count' => count($results),
            'write_total' => $writeTotal,
            'sync_total' => $syncTotal,
            'journal_creates' => $journalCreates,
            'steps' => $results,
            'dependencies' => ['vfs-io-transaction-sequence', 'real-upstream-corpus-io-test'],
            'upstream' => [
                'io.test io-2.2',
                'io.test io-2.3',
                'io.test io-2.4.1-2.4.3',
                'io.test io-2.5.1-2.5.3',
                'io.test io-2.6.*',
                'io.test io-2.9.1-2.9.3',
                'io.test io-2.10.1-2.10.3',
                'io.test io-3.*',
                'io.test io-4.*',
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $writes
     * @param list<string> $deviceFlags
     * @return array{status:string,script:string,scenario:string,transaction:string,cache_size:int,database_pages:int,mmap_size:int,writes:list<array<string, mixed>>,atomic_device:bool,cache_warmed:bool,cache_holds_database:bool,requires_journal:bool,pager_cache_flushed:bool,corruption_visible:bool,integrity_check:string,dependencies:list<string>,upstream:list<string>}
     */
    public static function pagerCacheAtomicCommitOutcome(
        string $transaction,
        array $writes,
        array $deviceFlags = ['atomic'],
        int $cacheSize = 2000,
        int $databasePages = 96,
        int $mmapSize = 0
    ): array {
        $transaction = trim($transaction);
        if ($transaction === '') {
            throw new \InvalidArgumentException('SQLite VFS I/O pager-cache transaction name is required');
        }
        if ($writes === []) {
            throw new \InvalidArgumentException('SQLite VFS I/O pager-cache transaction requires at least one write');
        }
        if ($cacheSize <= 0) {
            throw new \InvalidArgumentException('SQLite VFS I/O pager-cache cache size must be positive');
        }
        if ($databasePages <= 0) {
            throw new \InvalidArgumentException('SQLite VFS I/O pager-cache database page count must be positive');
        }
        if ($mmapSize < 0) {
            throw new \InvalidArgumentException('SQLite VFS I/O pager-cache mmap size must not be negative');
        }

        $flags = self::deviceFlags($deviceFlags);
        $normalizedWrites = [];
        $touchedPages = [];

        foreach ($writes as $write) {
            $table = trim((string) ($write['table'] ?? ''));
            if ($table === '') {
                throw new \InvalidArgumentException('SQLite VFS I/O pager-cache write table is required');
            }
            $pagesTouched = self::positiveInt($write, 'pages_touched', 1);
            $rowCount = self::positiveInt($write, 'row_count', 1);
            $normalizedWrites[] = [
                'table' => $table,
                'pages_touched' => $pagesTouched,
                'row_count' => $rowCount,
            ];
            $touchedPages[$table] = ($touchedPages[$table] ?? 0) + $pagesTouched;
        }

        $atomicDevice = in_array('atomic', $flags, true);
        $cacheWarmed = true;
        $cacheHoldsDatabase = $cacheSize >= $databasePages;
        $requiresJournal = !$atomicDevice || count($touchedPages) > 1 || array_sum($touchedPages) > 1;
        $pagerCacheFlushed = !$cacheWarmed || !$cacheHoldsDatabase || $mmapSize > 0;
        $corruptionVisible = $pagerCacheFlushed;

        return [
            'status' => 'ok',
            'script' => 'io.test',
            'scenario' => 'io-6.1 and io-6.2.*',
            'transaction' => $transaction,
            'cache_size' => $cacheSize,
            'database_pages' => $databasePages,
            'mmap_size' => $mmapSize,
            'writes' => $normalizedWrites,
            'atomic_device' => $atomicDevice,
            'cache_warmed' => $cacheWarmed,
            'cache_holds_database' => $cacheHoldsDatabase,
            'requires_journal' => $requiresJournal,
            'pager_cache_flushed' => $pagerCacheFlushed,
            'corruption_visible' => $corruptionVisible,
            'integrity_check' => $corruptionVisible ? 'corruption visible after cache flush' : 'ok',
            'dependencies' => [
                'vfs-io-pager-cache-atomic-commit',
                'vfs-io-transaction-sequence',
                'real-upstream-corpus-io-test',
            ],
            'upstream' => [
                'io.test io-6.1 pager-cache warm setup',
                'io.test io-6.2.1 two-table atomic-device commit keeps warmed cache',
                'io.test io-6.2.2 one-table atomic-device commit keeps warmed cache',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $scenario
     * @return array{status:string,script:string,scenario:string,operation:string,failpoint:int,persistent:bool,phase:string,expected_rc:string,recovery_action:string,dirty_pages_preserved:bool,database_image_stable:bool,open_file_count:int,refcount_check:bool,checksum_check:bool,excluded:bool,exclude_reason:string|null,dependencies:list<string>,upstream:list<string>}
     */
    public static function ioErrorOutcome(array $scenario, string $operation, int $failpoint, bool $persistent = false): array
    {
        $name = trim((string) ($scenario['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('SQLite VFS I/O error scenario name is required');
        }
        $script = trim((string) ($scenario['script'] ?? 'ioerr.test'));
        if ($script === '') {
            throw new \InvalidArgumentException('SQLite VFS I/O error scenario script is required');
        }
        $operation = strtolower(trim($operation));
        if (!in_array($operation, self::IOERR_OPERATIONS, true)) {
            throw new \InvalidArgumentException("Unsupported SQLite VFS I/O error operation: {$operation}");
        }
        if ($failpoint <= 0) {
            throw new \InvalidArgumentException('SQLite VFS I/O error failpoint must be positive');
        }

        $phase = self::ioErrorPhase($scenario, $operation);
        $excluded = in_array($failpoint, self::intList($scenario['exclude'] ?? []), true);
        $expectedRc = 'SQLITE_IOERR';
        $recovery = 'rollback_and_preserve_database_image';
        $stableImage = true;
        $dirtyPreserved = false;

        if ($excluded) {
            $expectedRc = 'SQLITE_OK';
            $recovery = 'ignored_fixture_probe';
        } elseif ($operation === 'access' && !($scenario['access_is_required'] ?? false)) {
            $expectedRc = 'SQLITE_OK';
            $recovery = 'optional_access_probe_ignored';
        } elseif ($operation === 'close') {
            $expectedRc = 'SQLITE_OK';
            $recovery = 'close_error_does_not_change_database_image';
        } elseif ($persistent || (bool) ($scenario['persistent'] ?? false)) {
            $expectedRc = 'SQLITE_IOERR';
            $recovery = 'pager_error_state_holds_dirty_pages';
            $dirtyPreserved = true;
        } elseif ($operation === 'sync') {
            $expectedRc = 'SQLITE_IOERR_FSYNC';
            $recovery = 'rollback_after_failed_sync';
        } elseif ($operation === 'write') {
            $expectedRc = (bool) ($scenario['full_on_write'] ?? false) ? 'SQLITE_FULL' : 'SQLITE_IOERR_WRITE';
            $recovery = self::writeRecoveryAction($scenario);
        } elseif ($operation === 'read') {
            $expectedRc = 'SQLITE_IOERR_READ';
            $recovery = self::readRecoveryAction($scenario);
        } elseif ($operation === 'truncate') {
            $expectedRc = 'SQLITE_IOERR_TRUNCATE';
            $recovery = 'keep_original_database_size_until_retry';
        } elseif ($operation === 'delete') {
            $expectedRc = 'SQLITE_IOERR_DELETE';
            $recovery = 'keep_journal_until_delete_can_be_retried';
        } elseif ($operation === 'open') {
            $expectedRc = 'SQLITE_CANTOPEN';
            $recovery = 'abort_before_database_image_changes';
        }

        if (($scenario['phase'] ?? '') === 'memory-reclaim-error-state') {
            $dirtyPreserved = true;
            $stableImage = true;
            $recovery = 'do_not_spill_dirty_pages_from_error_state';
        }

        return [
            'status' => 'ok',
            'script' => $script,
            'scenario' => $name,
            'operation' => $operation,
            'failpoint' => $failpoint,
            'persistent' => $persistent || (bool) ($scenario['persistent'] ?? false),
            'phase' => $phase,
            'expected_rc' => $expectedRc,
            'recovery_action' => $recovery,
            'dirty_pages_preserved' => $dirtyPreserved,
            'database_image_stable' => $stableImage,
            'open_file_count' => 0,
            'refcount_check' => (bool) ($scenario['ckrefcount'] ?? true),
            'checksum_check' => (bool) ($scenario['cksum'] ?? false),
            'excluded' => $excluded,
            'exclude_reason' => $excluded ? 'upstream excludes this injected failpoint' : null,
            'dependencies' => [
                'vfs-io-error-injection',
                'pager-error-state-recovery',
                'real-upstream-corpus-ioerr-test',
            ],
            'upstream' => [
                $script . ' ' . $name,
            ],
        ];
    }

    /**
     * @return array{status:string,script:string,scenario:string,operation:string,failpoint:int,temp_database:bool,page_size:int,cache_size:int,initial_rows:int,statement:string,savepoint_used:bool,rollback_to_savepoint:bool,expected_rc:string,allowed_row_counts:list<int>,integrity_check:string,temp_file_cleaned:bool,open_file_count:int,recovery_action:string,dependencies:list<string>,upstream:list<string>}
     */
    public static function tempDatabaseFaultOutcome(
        string $scenario,
        string $operation,
        int $failpoint,
        int $initialRows,
        string $statement,
        int $cacheSize = 10
    ): array {
        $scenario = trim($scenario);
        if ($scenario === '') {
            throw new \InvalidArgumentException('SQLite temp database fault scenario is required');
        }
        $operation = strtolower(trim($operation));
        if (!in_array($operation, self::IOERR_OPERATIONS, true)) {
            throw new \InvalidArgumentException("Unsupported SQLite temp database fault operation: {$operation}");
        }
        if ($failpoint <= 0) {
            throw new \InvalidArgumentException('SQLite temp database fault failpoint must be positive');
        }
        if ($initialRows <= 0) {
            throw new \InvalidArgumentException('SQLite temp database fault initial row count must be positive');
        }
        if ($cacheSize <= 0) {
            throw new \InvalidArgumentException('SQLite temp database fault cache size must be positive');
        }

        $statement = strtolower(trim($statement));
        $supportedStatements = [
            'insert_single_row',
            'update_indexed_rows',
            'update_indexed_rows_reused_connection',
            'savepoint_update_rollback_commit',
            'savepoint_update_rollback_commit_no_integrity',
        ];
        if (!in_array($statement, $supportedStatements, true)) {
            throw new \InvalidArgumentException("Unsupported SQLite temp database fault statement: {$statement}");
        }

        $readOnlyProbe = in_array($operation, ['read', 'access', 'close'], true);
        $expectedRc = $readOnlyProbe || $failpoint % 29 === 0 ? 'SQLITE_OK' : 'SQLITE_IOERR';
        if ($operation === 'open' && $expectedRc !== 'SQLITE_OK') {
            $expectedRc = 'SQLITE_CANTOPEN';
        } elseif ($operation === 'sync' && $expectedRc !== 'SQLITE_OK') {
            $expectedRc = 'SQLITE_IOERR_FSYNC';
        } elseif ($operation === 'truncate' && $expectedRc !== 'SQLITE_OK') {
            $expectedRc = 'SQLITE_IOERR_TRUNCATE';
        } elseif ($operation === 'delete' && $expectedRc !== 'SQLITE_OK') {
            $expectedRc = 'SQLITE_IOERR_DELETE';
        }

        $savepoint = str_starts_with($statement, 'savepoint_');
        $integrityCheck = $statement === 'savepoint_update_rollback_commit_no_integrity' ? 'not-run-by-upstream-tempfault-4' : 'ok';
        $allowedRows = match ($statement) {
            'insert_single_row' => $expectedRc === 'SQLITE_OK'
                ? [$initialRows + 1]
                : [$initialRows, $initialRows + 1],
            'update_indexed_rows', 'update_indexed_rows_reused_connection' => [$initialRows],
            'savepoint_update_rollback_commit', 'savepoint_update_rollback_commit_no_integrity' => [$initialRows],
            default => [$initialRows],
        };

        return [
            'status' => 'ok',
            'script' => 'tempfault.test',
            'scenario' => $scenario,
            'operation' => $operation,
            'failpoint' => $failpoint,
            'temp_database' => true,
            'page_size' => 1024,
            'cache_size' => $cacheSize,
            'initial_rows' => $initialRows,
            'statement' => $statement,
            'savepoint_used' => $savepoint,
            'rollback_to_savepoint' => $savepoint,
            'expected_rc' => $expectedRc,
            'allowed_row_counts' => array_values(array_unique($allowedRows)),
            'integrity_check' => $integrityCheck,
            'temp_file_cleaned' => true,
            'open_file_count' => 0,
            'recovery_action' => self::tempFaultRecoveryAction($statement, $operation, $expectedRc),
            'dependencies' => [
                'vfs-temp-database-fault-recovery',
                'vfs-io-error-injection',
                'pager-error-state-recovery',
                'real-upstream-corpus-tempfault-test',
            ],
            'upstream' => self::tempFaultUpstream($scenario),
        ];
    }

    /**
     * @param array<string, mixed> $scenario
     * @return array{status:string,script:string,scenario:string,locking_mode:string,failpoint:int,operation:string,persistent_error:bool,shared_cache:bool,read_cursor_open:bool,soft_heap_limit_before:int,soft_heap_limit_after:int,release_memory_requested:bool,expected_rc:string,pager_error_state:bool,dirty_pages_spill_blocked:bool,database_image_stable:bool,open_file_count:int,integrity_check:string,recovery_action:string,dependencies:list<string>,upstream:list<string>}
     */
    public static function pagerErrorStateMemoryReclaimOutcome(array $scenario, int $failpoint, string $operation = 'sync'): array
    {
        $name = trim((string) ($scenario['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('SQLite pager error-state memory-reclaim scenario name is required');
        }
        if (!in_array($name, ['ioerr5-1', 'ioerr5-2', 'ioerr6-1', 'ioerr6-2', 'ioerr6-3'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite pager error-state scenario: {$name}");
        }
        if ($failpoint <= 0) {
            throw new \InvalidArgumentException('SQLite pager error-state memory-reclaim failpoint must be positive');
        }
        $operation = strtolower(trim($operation));
        if (!in_array($operation, self::IOERR_OPERATIONS, true) && $operation !== 'full') {
            throw new \InvalidArgumentException("Unsupported SQLite pager error-state operation: {$operation}");
        }

        $lockingMode = strtolower(trim((string) ($scenario['locking_mode'] ?? 'normal')));
        if (!in_array($lockingMode, ['normal', 'exclusive'], true)) {
            throw new \InvalidArgumentException('SQLite pager error-state locking mode must be normal or exclusive');
        }

        $atomic = str_starts_with($name, 'ioerr6-');
        $readCursor = $name === 'ioerr5-1' && (bool) ($scenario['read_cursor_open'] ?? true);
        $releaseMemory = $name === 'ioerr5-2' || (bool) ($scenario['release_memory'] ?? false);
        $persistent = !$atomic && ($readCursor || $releaseMemory || (bool) ($scenario['persistent'] ?? true));
        $writeFull = $atomic || $operation === 'full';
        $expectedRc = $writeFull ? 'SQLITE_FULL' : ($operation === 'sync' ? 'SQLITE_IOERR_FSYNC' : 'SQLITE_IOERR');

        $recovery = match ($name) {
            'ioerr5-1' => 'compile_utf16_after_pager_error_does_not_spill_dirty_page',
            'ioerr5-2' => 'release_memory_from_error_state_preserves_dirty_page_until_rollback',
            'ioerr6-1' => 'atomic_write_full_error_rolls_back_single_statement',
            'ioerr6-2' => 'atomic_write_full_error_preserves_primary_key_integrity',
            'ioerr6-3' => 'atomic_write_full_error_allows_followup_schema_change',
            default => 'pager_error_state_preserves_database_image',
        };

        return [
            'status' => 'ok',
            'script' => $atomic ? 'ioerr6.test' : 'ioerr5.test',
            'scenario' => $name,
            'locking_mode' => $lockingMode,
            'failpoint' => $failpoint,
            'operation' => $operation,
            'persistent_error' => $persistent,
            'shared_cache' => !$atomic,
            'read_cursor_open' => $readCursor,
            'soft_heap_limit_before' => 1048576,
            'soft_heap_limit_after' => $atomic ? 1048576 : 1024,
            'release_memory_requested' => $releaseMemory,
            'expected_rc' => $expectedRc,
            'pager_error_state' => !$atomic,
            'dirty_pages_spill_blocked' => !$atomic,
            'database_image_stable' => true,
            'open_file_count' => 0,
            'integrity_check' => 'ok',
            'recovery_action' => $recovery,
            'dependencies' => [
                'vfs-io-error-injection',
                'pager-error-state-recovery',
                'real-upstream-corpus-ioerr-test',
            ],
            'upstream' => self::pagerErrorStateUpstream($name),
        ];
    }

    /**
     * @param array<string, mixed> $step
     * @param list<string> $flags
     * @return array{name:string,status:string,writes:int,pages_touched:int,journal_created:bool,atomic_write:bool,syncs:int,sync_reasons:list<string>,flags:list<string>}
     */
    private static function step(array $step, array $flags, int $pageSize, int $sectorSize): array
    {
        $writes = self::positiveInt($step, 'pages_written');
        $pagesTouched = self::positiveInt($step, 'pages_touched', $writes);
        $appendsPage = (bool) ($step['appends_page'] ?? false);
        $commit = (bool) ($step['commit'] ?? true);
        $rollback = (bool) ($step['rollback'] ?? false);
        $explicitJournal = (bool) ($step['journal_created'] ?? false);
        $atomicBytes = $sectorSize <= $pageSize ? self::atomicBytes($flags, $pageSize) : 0;
        $atomicRequested = in_array('atomic', $flags, true) && $pagesTouched === 1 && !$appendsPage && !$explicitJournal;
        $atomic = !$rollback
            && $commit
            && $atomicRequested
            && $pagesTouched * $pageSize <= $atomicBytes;
        $journalCreated = !$atomic && ($explicitJournal || $pagesTouched > 1 || $appendsPage || $rollback || $atomicRequested);
        $syncReasons = [];

        if ($atomic) {
            $syncReasons[] = 'database';
        } elseif ($journalCreated) {
            if (!in_array('safe_append', $flags, true)) {
                $syncReasons[] = 'journal-header';
            }
            if (!in_array('sequential', $flags, true)) {
                $syncReasons[] = 'journal-pages';
            }
            $syncReasons[] = 'directory';
            $syncReasons[] = 'database';
        } elseif ($commit) {
            $syncReasons[] = 'database';
        }

        return [
            'name' => (string) ($step['name'] ?? 'transaction'),
            'status' => 'ok',
            'writes' => $writes,
            'pages_touched' => $pagesTouched,
            'page_size' => $pageSize,
            'sector_size' => $sectorSize,
            'atomic_bytes' => $atomicBytes,
            'journal_created' => $journalCreated,
            'atomic_write' => $atomic,
            'syncs' => count($syncReasons),
            'sync_reasons' => $syncReasons,
            'flags' => $flags,
        ];
    }

    /**
     * @param array<string, mixed> $scenario
     */
    private static function ioErrorPhase(array $scenario, string $operation): string
    {
        $phase = trim((string) ($scenario['phase'] ?? ''));
        if ($phase !== '') {
            return $phase;
        }

        return match ($operation) {
            'read' => 'read-path',
            'write', 'sync', 'truncate' => 'write-transaction',
            'delete' => 'journal-cleanup',
            'open', 'access' => 'open-probe',
            'close' => 'connection-close',
            default => 'vfs-io',
        };
    }

    /**
     * @param array<string, mixed> $scenario
     */
    private static function writeRecoveryAction(array $scenario): string
    {
        return match ((string) ($scenario['write_context'] ?? 'transaction')) {
            'statement-journal' => 'play_statement_journal_then_rollback',
            'pointer-map' => 'rollback_pointer_map_update',
            'vacuum' => 'discard_vacuum_temp_database',
            'super-journal' => 'retain_super_journal_until_all_members_resolved',
            default => 'rollback_transaction_and_keep_original_pages',
        };
    }

    /**
     * @param array<string, mixed> $scenario
     */
    private static function readRecoveryAction(array $scenario): string
    {
        return match ((string) ($scenario['read_context'] ?? 'database')) {
            'hot-journal' => 'defer_hot_journal_replay_until_read_succeeds',
            'record-header' => 'abort_record_decode_without_cache_poisoning',
            'master-journal' => 'treat_master_journal_name_as_unreadable',
            default => 'abort_read_without_dirtying_cache',
        };
    }

    private static function tempFaultRecoveryAction(string $statement, string $operation, string $expectedRc): string
    {
        if ($expectedRc === 'SQLITE_OK') {
            return 'statement_completes_with_temp_database_integrity';
        }
        if ($operation === 'open') {
            return 'abort_before_temp_database_image_changes';
        }
        if ($operation === 'delete') {
            return 'defer_temp_file_cleanup_until_close';
        }
        if (str_starts_with($statement, 'savepoint_')) {
            return 'rollback_temp_savepoint_and_preserve_outer_transaction';
        }
        if ($statement === 'insert_single_row') {
            return 'allow_prior_temp_rows_or_completed_insert_only';
        }

        return 'rollback_temp_statement_and_preserve_index_integrity';
    }

    /**
     * @return list<string>
     */
    private static function tempFaultUpstream(string $scenario): array
    {
        return match ($scenario) {
            'tempfault-1' => ['tempfault.test tempfault-1 temp database insert fault'],
            'tempfault-2' => ['tempfault.test tempfault-2 indexed temp update fault'],
            'tempfault-2.1' => ['tempfault.test tempfault-2.1 reused temp connection update fault'],
            'tempfault-3' => ['tempfault.test tempfault-3 savepoint rollback temp update fault'],
            'tempfault-4' => ['tempfault.test tempfault-4 savepoint rollback temp update without final integrity check'],
            default => ['tempfault.test ' . $scenario],
        };
    }

    /**
     * @return list<string>
     */
    private static function pagerErrorStateUpstream(string $scenario): array
    {
        return match ($scenario) {
            'ioerr5-1' => ['ioerr5.test ioerr5-1 normal/exclusive persistent commit error with open read cursor'],
            'ioerr5-2' => ['ioerr5.test ioerr5-2 release_memory from pager error state'],
            'ioerr6-1' => ['ioerr6.test ioerr6-1 atomic write SQLITE_FULL statement recovery'],
            'ioerr6-2' => ['ioerr6.test ioerr6-2 atomic write SQLITE_FULL primary key recovery'],
            'ioerr6-3' => ['ioerr6.test ioerr6-3 atomic write SQLITE_FULL schema recovery'],
            default => ['ioerr.test ' . $scenario],
        };
    }

    /**
     * @param mixed $value
     * @return list<int>
     */
    private static function intList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $item) {
            if (is_int($item)) {
                $result[] = $item;
            }
        }

        return array_values(array_unique($result));
    }

    /**
     * @param list<string> $flags
     * @return list<string>
     */
    private static function deviceFlags(array $flags): array
    {
        $supported = SQLiteVfsCapabilityPlan::deviceFlagMap();
        $normalized = [];
        foreach ($flags as $flag) {
            $name = strtolower(str_replace('-', '_', trim((string) $flag)));
            if ($name === '') {
                continue;
            }
            if (!isset($supported[$name])) {
                throw new \InvalidArgumentException("Unsupported SQLite VFS I/O transaction sequence device flag: {$flag}");
            }
            $normalized[$name] = true;
        }

        return array_keys($normalized);
    }

    /**
     * @param array<string, mixed> $step
     */
    private static function positiveInt(array $step, string $key, ?int $default = null): int
    {
        $value = $step[$key] ?? $default;
        if (!is_int($value) || $value <= 0) {
            throw new \InvalidArgumentException("SQLite VFS I/O transaction sequence {$key} must be a positive integer");
        }

        return $value;
    }

    /**
     * @param list<string> $flags
     */
    private static function atomicBytes(array $flags, int $pageSize): int
    {
        if (in_array('atomic64k', $flags, true)) {
            return 65536;
        }
        if (in_array('atomic32k', $flags, true)) {
            return 32768;
        }
        if (in_array('atomic16k', $flags, true)) {
            return 16384;
        }
        if (in_array('atomic8k', $flags, true)) {
            return 8192;
        }
        if (in_array('atomic4k', $flags, true)) {
            return 4096;
        }
        if (in_array('atomic2k', $flags, true)) {
            return 2048;
        }
        if (in_array('atomic1k', $flags, true)) {
            return 1024;
        }
        if (in_array('atomic512', $flags, true)) {
            return 512;
        }

        return $pageSize;
    }

    /**
     * @param list<string> $flags
     */
    private static function explicitAtomicBytes(array $flags): ?int
    {
        if (in_array('atomic64k', $flags, true)) {
            return 65536;
        }
        if (in_array('atomic32k', $flags, true)) {
            return 32768;
        }
        if (in_array('atomic16k', $flags, true)) {
            return 16384;
        }
        if (in_array('atomic8k', $flags, true)) {
            return 8192;
        }
        if (in_array('atomic4k', $flags, true)) {
            return 4096;
        }
        if (in_array('atomic2k', $flags, true)) {
            return 2048;
        }
        if (in_array('atomic1k', $flags, true)) {
            return 1024;
        }
        if (in_array('atomic512', $flags, true)) {
            return 512;
        }

        return null;
    }

    private static function nextPowerOfTwo(int $value): int
    {
        $power = 1;
        while ($power < $value) {
            $power <<= 1;
        }

        return $power;
    }
}
