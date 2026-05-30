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
