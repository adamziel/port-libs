<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVfsIoTrafficPlan
{
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
