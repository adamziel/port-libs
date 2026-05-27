<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

use InvalidArgumentException;

final class SQLiteTransactionBeginLockPlan
{
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

    private static function normalizeSchema(?string $schema): ?string
    {
        if ($schema === null || trim($schema) === '') {
            return null;
        }

        return strtolower(trim($schema));
    }
}
