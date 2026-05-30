<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteVfsIoTransactionSequencePlan
{
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
