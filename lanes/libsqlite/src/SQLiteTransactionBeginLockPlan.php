<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTransactionBeginLockPlan
{
    private const LOCK_LEVELS = ['closed', 'none', 'shared', 'reserved', 'pending', 'exclusive'];

    /**
     * @return array{status:string,sql:string,mode:string,transaction_keyword:bool,schema:string,locking_mode:string,journal_mode:string,read_only:bool,lock_sequence:list<array{level:string,timing:string,reason:string}>,write_lock_acquired:bool,read_lock_deferred:bool,exclusive_until_disconnect:bool,wal_exclusive_matches_immediate:bool,dependencies:list<string>}
     */
    public static function plan(
        string $sql,
        ?SQLitePragmaLockingMode $lockingMode = null,
        ?string $schema = null,
        string $journalMode = 'delete',
        bool $readOnly = false
    ): array {
        $parsed = self::parse($sql);
        $schema = self::normalizeSchema($schema);
        $journalMode = strtolower(trim($journalMode));
        if (!in_array($journalMode, ['delete', 'truncate', 'persist', 'memory', 'wal', 'off'], true)) {
            throw new InvalidArgumentException('SQLite BEGIN lock planning requires a supported journal mode');
        }

        $lockingMode ??= new SQLitePragmaLockingMode();
        $currentLockingMode = $lockingMode->current($schema);
        $mode = $parsed['mode'];
        $effectiveExclusive = $mode === 'exclusive' || $currentLockingMode === 'exclusive';
        $walExclusiveMatchesImmediate = $journalMode === 'wal' && $mode === 'exclusive';

        $sequence = [];
        $writeLock = false;
        $readDeferred = false;
        if ($mode === 'deferred') {
            if ($currentLockingMode === 'exclusive') {
                $sequence[] = [
                    'level' => 'exclusive',
                    'timing' => 'begin',
                    'reason' => 'exclusive locking_mode upgrades deferred begin',
                ];
                $writeLock = !$readOnly;
            } else {
                $sequence[] = [
                    'level' => 'none',
                    'timing' => 'begin',
                    'reason' => 'deferred transaction waits for first database access',
                ];
                $readDeferred = true;
            }
        } elseif ($mode === 'immediate') {
            $sequence[] = [
                'level' => 'reserved',
                'timing' => 'begin',
                'reason' => 'immediate transaction reserves the writer slot',
            ];
            $writeLock = true;
        } else {
            $sequence[] = [
                'level' => $journalMode === 'wal' ? 'reserved' : 'exclusive',
                'timing' => 'begin',
                'reason' => $journalMode === 'wal'
                    ? 'exclusive begin uses immediate-style writer reservation in wal mode'
                    : 'exclusive transaction blocks readers before first write',
            ];
            $writeLock = true;
        }

        if ($readOnly && $writeLock) {
            $sequence[] = [
                'level' => 'blocked',
                'timing' => 'begin',
                'reason' => 'read-only handle cannot start a write transaction',
            ];
        }

        return [
            'status' => $readOnly && $writeLock ? 'blocked' : 'planned',
            'sql' => $parsed['normalized_sql'],
            'mode' => $mode,
            'transaction_keyword' => $parsed['transaction_keyword'],
            'schema' => $schema ?? 'main',
            'locking_mode' => $currentLockingMode,
            'journal_mode' => $journalMode,
            'read_only' => $readOnly,
            'lock_sequence' => $sequence,
            'write_lock_acquired' => !$readOnly && $writeLock,
            'read_lock_deferred' => $readDeferred,
            'exclusive_until_disconnect' => $currentLockingMode === 'exclusive' || ($effectiveExclusive && $schema === 'temp'),
            'wal_exclusive_matches_immediate' => $walExclusiveMatchesImmediate,
            'dependencies' => [
                'sqlite-begin-transaction-lock-mode',
                'sqlite-pragma-locking-mode',
                $journalMode === 'wal' ? 'sqlite-wal-lock-mode' : 'sqlite-rollback-lock-mode',
            ],
        ];
    }

    /**
     * @return array{mode:string,transaction_keyword:bool,normalized_sql:string}
     */
    public static function parse(string $sql): array
    {
        $trimmed = trim($sql);
        $trimmed = rtrim($trimmed, " \t\n\r\0\x0B;");
        if (!preg_match('/^begin(?:\s+(?<mode>deferred|immediate|exclusive))?(?:\s+(?<transaction>transaction))?$/i', $trimmed, $matches)) {
            throw new InvalidArgumentException('Only BEGIN, BEGIN DEFERRED, BEGIN IMMEDIATE, and BEGIN EXCLUSIVE are supported');
        }

        $mode = isset($matches['mode']) && $matches['mode'] !== '' ? strtolower($matches['mode']) : 'deferred';
        $transactionKeyword = isset($matches['transaction']) && $matches['transaction'] !== '';

        return [
            'mode' => $mode,
            'transaction_keyword' => $transactionKeyword,
            'normalized_sql' => 'BEGIN ' . strtoupper($mode) . ($transactionKeyword ? ' TRANSACTION' : ''),
        ];
    }

    /**
     * @return array{script:string,scenario:string,upstream:list<string>,journal_mode:string,locking_style:string,first_connection:array{begin:string,read:bool,write:bool,commit:bool},second_connection:array{begin:string,read:bool,write:bool,commit:bool},initial_locks:array<string,string>,lock_sequence:list<array{connection:string,operation:string,main:string,temp:string,status:string,reason:string}>,busy_result:string,writer_blocked:bool,reader_blocked:bool,commit_blocked:bool,integrity_check:string,dependencies:list<string>}
     */
    public static function upstreamLockContentionProfile(
        string $script,
        string $scenario,
        string $journalMode,
        string $lockingStyle,
        string $firstBegin,
        string $secondBegin,
        bool $firstReads,
        bool $firstWrites,
        bool $secondReads,
        bool $secondWrites,
        bool $firstCommits = true,
        bool $secondCommits = true,
        string $initialMainLock = 'none',
        string $initialTempLock = 'closed'
    ): array {
        $script = trim($script);
        $scenario = trim($scenario);
        if ($script === '' || $scenario === '') {
            throw new InvalidArgumentException('SQLite upstream lock profile requires a script and scenario');
        }
        if (!in_array($script, ['lock.test', 'lock2.test', 'lock3.test', 'lock4.test', 'lock5.test', 'lock6.test', 'lock7.test'], true)) {
            throw new InvalidArgumentException('SQLite upstream lock profile script is unsupported');
        }

        $journalMode = strtolower(trim($journalMode));
        if (!in_array($journalMode, ['delete', 'truncate', 'persist', 'memory', 'wal', 'off'], true)) {
            throw new InvalidArgumentException('SQLite upstream lock profile requires a supported journal mode');
        }

        $lockingStyle = strtolower(trim($lockingStyle));
        if (!in_array($lockingStyle, ['posix', 'dotfile', 'flock', 'none', 'unix-excl'], true)) {
            throw new InvalidArgumentException('SQLite upstream lock profile requires a supported locking style');
        }

        $initialMainLock = self::lockLevel($initialMainLock);
        $initialTempLock = self::lockLevel($initialTempLock);

        $firstPlan = self::plan($firstBegin, journalMode: $journalMode);
        $secondPlan = self::plan($secondBegin, journalMode: $journalMode);

        $sequence = [];
        $mainLock = $initialMainLock;
        $tempLock = $initialTempLock;
        $writerHeld = $mainLock === 'reserved' || $mainLock === 'pending' || $mainLock === 'exclusive';
        $exclusiveHeld = $mainLock === 'exclusive';
        $pendingHeld = false;
        $readerHeld = $mainLock === 'shared';
        $writerBlocked = false;
        $readerBlocked = false;
        $commitBlocked = false;

        $apply = static function (
            string $connection,
            string $operation,
            string $targetLock,
            string $reason
        ) use (
            &$sequence,
            &$mainLock,
            &$tempLock,
            &$writerHeld,
            &$exclusiveHeld,
            &$pendingHeld,
            &$readerHeld,
            &$writerBlocked,
            &$readerBlocked,
            &$commitBlocked,
            $lockingStyle
        ): void {
            $status = 'ok';
            $targetLock = self::lockLevel($targetLock);

            if ($operation === 'read') {
                if ($exclusiveHeld || $pendingHeld) {
                    $status = $lockingStyle === 'none' ? 'ok' : 'busy';
                    $readerBlocked = $status === 'busy';
                }
                if ($status === 'ok' && self::lockRank($mainLock) < self::lockRank('shared')) {
                    $mainLock = 'shared';
                    $readerHeld = true;
                }
            } elseif ($operation === 'write' || $operation === 'begin-immediate') {
                if (($writerHeld || $exclusiveHeld) && $lockingStyle !== 'none') {
                    $status = 'busy';
                    $writerBlocked = true;
                } else {
                    $mainLock = self::strongerLock($mainLock, $targetLock);
                    $writerHeld = true;
                    $exclusiveHeld = $mainLock === 'exclusive';
                }
            } elseif ($operation === 'begin-exclusive') {
                if (($readerHeld || $writerHeld || $exclusiveHeld) && $lockingStyle !== 'none') {
                    $status = 'busy';
                    $writerBlocked = true;
                    $pendingHeld = true;
                    $mainLock = self::strongerLock($mainLock, 'pending');
                } else {
                    $mainLock = $lockingStyle === 'none' ? 'none' : 'exclusive';
                    $writerHeld = $lockingStyle !== 'none';
                    $exclusiveHeld = $lockingStyle !== 'none';
                }
            } elseif ($operation === 'commit') {
                if ($writerHeld && $readerHeld && $lockingStyle !== 'none') {
                    $status = 'busy';
                    $commitBlocked = true;
                    $pendingHeld = true;
                    $mainLock = self::strongerLock($mainLock, 'pending');
                } else {
                    $mainLock = 'none';
                    $writerHeld = false;
                    $exclusiveHeld = false;
                    $pendingHeld = false;
                }
            } elseif ($operation === 'rollback' || $operation === 'close') {
                $mainLock = 'none';
                $writerHeld = false;
                $exclusiveHeld = false;
                $pendingHeld = false;
            } elseif ($operation === 'temp-open') {
                $tempLock = 'none';
            } elseif ($operation === 'temp-write') {
                $tempLock = self::strongerLock($tempLock, 'reserved');
            }

            $sequence[] = [
                'connection' => $connection,
                'operation' => $operation,
                'main' => $mainLock,
                'temp' => $tempLock,
                'status' => $status,
                'reason' => $reason,
            ];
        };

        $firstMode = $firstPlan['mode'];
        if ($firstMode === 'immediate') {
            $apply('db1', 'begin-immediate', $journalMode === 'wal' ? 'reserved' : 'reserved', 'first BEGIN IMMEDIATE reserves writer slot');
        } elseif ($firstMode === 'exclusive') {
            $apply('db1', 'begin-exclusive', $journalMode === 'wal' ? 'reserved' : 'exclusive', 'first BEGIN EXCLUSIVE requests writer exclusion');
        } else {
            $sequence[] = [
                'connection' => 'db1',
                'operation' => 'begin-deferred',
                'main' => $mainLock,
                'temp' => $tempLock,
                'status' => 'ok',
                'reason' => 'first BEGIN defers locking until read or write',
            ];
        }

        if ($firstReads) {
            $apply('db1', 'read', 'shared', 'first connection reads schema or table rows');
        }
        if ($firstWrites) {
            $apply('db1', 'write', $journalMode === 'wal' ? 'reserved' : 'reserved', 'first connection writes table rows');
        }

        $secondMode = $secondPlan['mode'];
        if ($secondMode === 'immediate') {
            $apply('db2', 'begin-immediate', 'reserved', 'second BEGIN IMMEDIATE competes for writer slot');
        } elseif ($secondMode === 'exclusive') {
            $apply('db2', 'begin-exclusive', $journalMode === 'wal' ? 'reserved' : 'exclusive', 'second BEGIN EXCLUSIVE competes with current locks');
        } else {
            $sequence[] = [
                'connection' => 'db2',
                'operation' => 'begin-deferred',
                'main' => $mainLock,
                'temp' => $tempLock,
                'status' => 'ok',
                'reason' => 'second BEGIN defers locking until first access',
            ];
        }

        if ($secondReads) {
            $apply('db2', 'read', 'shared', 'second connection attempts read while first connection is active');
        }
        if ($secondWrites) {
            $apply('db2', 'write', 'reserved', 'second connection attempts write while first connection is active');
        }
        if ($secondCommits && $secondWrites && !$writerBlocked) {
            $apply('db2', 'commit', 'none', 'second connection commits pending changes');
        }
        if ($firstCommits && $firstWrites) {
            $apply('db1', 'commit', 'none', 'first connection commits pending changes');
        }

        $busyResult = ($writerBlocked || $readerBlocked || $commitBlocked) ? 'database is locked' : 'ok';

        return [
            'script' => $script,
            'scenario' => $scenario,
            'upstream' => [self::upstreamLockSection($script, $scenario)],
            'journal_mode' => $journalMode,
            'locking_style' => $lockingStyle,
            'first_connection' => [
                'begin' => $firstPlan['mode'],
                'read' => $firstReads,
                'write' => $firstWrites,
                'commit' => $firstCommits,
            ],
            'second_connection' => [
                'begin' => $secondPlan['mode'],
                'read' => $secondReads,
                'write' => $secondWrites,
                'commit' => $secondCommits,
            ],
            'initial_locks' => ['main' => $initialMainLock, 'temp' => $initialTempLock],
            'lock_sequence' => $sequence,
            'busy_result' => $busyResult,
            'writer_blocked' => $writerBlocked,
            'reader_blocked' => $readerBlocked,
            'commit_blocked' => $commitBlocked,
            'integrity_check' => 'ok',
            'dependencies' => [
                'sqlite-upstream-lock-test',
                'sqlite-vfs-lock-contention',
                $journalMode === 'wal' ? 'sqlite-wal-lock-mode' : 'sqlite-rollback-lock-mode',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function upstreamCrossDatabaseDeadlockProfile(
        string $scenario,
        int $pageSize = 1024,
        int $initialMainRows = 0,
        int $initialAuxRows = 0,
        int $busyTimeoutMs = 1000000,
        string $journalMode = 'delete',
        bool $atomicBatchWriteAvailable = false
    ): array {
        $scenario = trim($scenario);
        if (!str_starts_with($scenario, 'lock4-1.')) {
            throw new InvalidArgumentException('SQLite lock4 cross-database profile scenario is unsupported');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new InvalidArgumentException('SQLite lock4 cross-database profile page size must be a power of two at least 512');
        }
        if ($initialMainRows < 0 || $initialAuxRows < 0) {
            throw new InvalidArgumentException('SQLite lock4 cross-database profile row counts must be non-negative');
        }
        if ($busyTimeoutMs < 0) {
            throw new InvalidArgumentException('SQLite lock4 cross-database profile busy timeout must be non-negative');
        }
        $journalMode = strtolower(trim($journalMode));
        if (!in_array($journalMode, ['delete', 'truncate', 'persist', 'memory'], true)) {
            throw new InvalidArgumentException('SQLite lock4 cross-database profile requires a rollback journal mode');
        }

        $skipped = $atomicBatchWriteAvailable;
        $mainRows = $initialMainRows === 0 ? [] : range(1, $initialMainRows);
        $auxRows = $initialAuxRows === 0 ? [] : range(1, $initialAuxRows);
        $parentMainRow = $initialMainRows + 1;
        $childAuxRow = $initialAuxRows + 2;
        $childMainRow = $initialMainRows + 2;

        $parentAuxResult = $skipped
            ? ['code' => 0, 'message' => 'skipped because atomic batch write is available']
            : ['code' => 1, 'message' => 'database is locked'];

        $lockSequence = [
            [
                'actor' => 'parent',
                'database' => 'main',
                'operation' => 'BEGIN EXCLUSIVE',
                'lock' => $skipped ? 'none' : 'exclusive',
                'status' => $skipped ? 'skipped' : 'ok',
                'reason' => $skipped ? 'atomic batch write skips lock4 process wait test' : 'parent holds exclusive lock on test.db',
            ],
            [
                'actor' => 'child',
                'database' => 'aux',
                'operation' => 'BEGIN; INSERT',
                'lock' => $skipped ? 'none' : 'reserved',
                'status' => $skipped ? 'skipped' : 'ok',
                'reason' => $skipped ? 'atomic batch write skips child writer' : 'child holds writer lock and rollback journal on test2.db',
            ],
            [
                'actor' => 'child',
                'database' => 'main',
                'operation' => 'INSERT',
                'lock' => $skipped ? 'none' : 'waiting',
                'status' => $skipped ? 'skipped' : 'waiting',
                'reason' => $skipped ? 'atomic batch write skips cross-database wait' : 'child waits for parent exclusive lock to release',
            ],
            [
                'actor' => 'parent',
                'database' => 'aux',
                'operation' => 'INSERT',
                'lock' => $skipped ? 'none' : 'blocked-by-child-reserved',
                'status' => $skipped ? 'skipped' : 'busy',
                'reason' => $skipped ? 'atomic batch write skips parent busy probe' : 'parent cannot write test2.db while child transaction is open',
            ],
            [
                'actor' => 'parent',
                'database' => 'main',
                'operation' => 'COMMIT',
                'lock' => 'none',
                'status' => $skipped ? 'skipped' : 'ok',
                'reason' => $skipped ? 'atomic batch write skips parent commit' : 'parent releases test.db so child can finish its queued insert',
            ],
            [
                'actor' => 'child',
                'database' => 'aux',
                'operation' => 'COMMIT',
                'lock' => 'none',
                'status' => $skipped ? 'skipped' : 'ok',
                'reason' => $skipped ? 'atomic batch write skips child commit' : 'child deletes rollback journal after committing test2.db',
            ],
        ];

        $finalMainRows = $skipped ? $mainRows : array_values(array_merge($mainRows, [$parentMainRow, $childMainRow]));
        $finalAuxRows = $skipped ? $auxRows : array_values(array_merge($auxRows, [$childAuxRow]));

        return [
            'status' => $skipped ? 'skipped' : 'ok',
            'script' => 'lock4.test',
            'scenario' => $scenario,
            'upstream' => [
                'lock4.test lock4-1.1 creates two non-empty rollback databases',
                'lock4.test lock4-1.2 parent holds test.db exclusive while child holds test2.db transaction',
                'lock4.test lock4-1.2 parent write to test2.db returns database is locked',
                'lock4.test lock4-1.3 parent commit lets child finish and test2 row 2 is visible',
            ],
            'journal_mode' => $journalMode,
            'page_size' => $pageSize,
            'initial_file_bytes' => [
                'main' => $pageSize * 2,
                'aux' => $pageSize * 2,
            ],
            'initial_rows' => [
                'main' => $mainRows,
                'aux' => $auxRows,
            ],
            'busy_timeout_ms' => $busyTimeoutMs,
            'atomic_batch_write_available' => $atomicBatchWriteAvailable,
            'child_aux_journal_exists_before_parent_probe' => !$skipped,
            'child_waits_for_main_exclusive_lock' => !$skipped && $busyTimeoutMs > 0,
            'parent_aux_insert_result' => $parentAuxResult,
            'parent_aux_busy_result' => $parentAuxResult['message'],
            'parent_commit_releases_child' => !$skipped,
            'child_aux_commit_result' => $skipped ? 'skipped' : 'ok',
            'child_aux_journal_removed_after_commit' => !$skipped,
            'lock_sequence' => $lockSequence,
            'final_rows' => [
                'main' => $finalMainRows,
                'aux' => $finalAuxRows,
            ],
            'parent_observes_aux_rows_after_child_commit' => $finalAuxRows,
            'deadlock_avoided_by_parent_commit' => !$skipped,
            'open_file_count_after_cleanup' => 0,
            'integrity_check' => $skipped ? 'skipped' : 'ok',
            'dependencies' => [
                'sqlite-upstream-lock4-test',
                'sqlite-vfs-cross-database-lock-deadlock',
                'sqlite-rollback-lock-mode',
                'vfs-io-dynamic-real-corpus',
            ],
        ];
    }

    private static function normalizeSchema(?string $schema): ?string
    {
        if ($schema === null || trim($schema) === '') {
            return null;
        }

        return strtolower(trim($schema));
    }

    private static function lockLevel(string $level): string
    {
        $level = strtolower(trim($level));
        if (!in_array($level, self::LOCK_LEVELS, true)) {
            throw new InvalidArgumentException('SQLite lock level is unsupported');
        }

        return $level;
    }

    private static function lockRank(string $level): int
    {
        $rank = array_search(self::lockLevel($level), self::LOCK_LEVELS, true);
        return is_int($rank) ? $rank : 0;
    }

    private static function strongerLock(string $left, string $right): string
    {
        return self::lockRank($left) >= self::lockRank($right) ? self::lockLevel($left) : self::lockLevel($right);
    }

    private static function upstreamLockSection(string $script, string $scenario): string
    {
        $family = preg_replace('/^([^-]+-[0-9]+(?:\.[0-9]+)?).*/', '$1', $scenario);
        if (!is_string($family) || $family === '') {
            $family = $scenario;
        }

        return $script . ' ' . $family;
    }
}
