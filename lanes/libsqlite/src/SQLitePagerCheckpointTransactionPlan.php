<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLitePagerCheckpointTransactionPlan
{
    /**
     * @return array{status:string,can_checkpoint:bool,connection:string,mode:string,database_path:string,lock_sequence:list<array<string, mixed>>,write_plan:array<string, mixed>|null,busy:array<string, mixed>|null,reason:string|null,dependencies:list<string>}
     */
    public static function plan(
        SQLiteLockCoordinator $locks,
        string $connection,
        SQLiteWal $wal,
        string $databaseBytes,
        string $databasePath,
        string $mode = 'passive',
        ?int $readerEndFrame = null,
        ?SQLiteBusyHandler $busyHandler = null,
        bool $readOnly = false,
        bool $immutable = false
    ): array {
        $mode = strtolower(trim($mode));
        if (!in_array($mode, ['passive', 'full', 'restart', 'truncate', 'noop'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite checkpoint transaction mode: {$mode}");
        }
        if ($databasePath === '') {
            throw new \InvalidArgumentException('SQLite checkpoint transaction requires a database path');
        }
        if ($readOnly || $immutable) {
            throw new \LogicException('SQLite checkpoint transaction requires a writable database handle');
        }

        $sequence = self::lockSequence($mode);
        $lockPlans = [];
        foreach ($sequence as $requested) {
            $lock = $locks->plan($connection, $requested, $busyHandler);
            $lockPlans[] = $lock;
            if (!$lock['can_acquire']) {
                return self::result(
                    $lock['status'],
                    false,
                    $connection,
                    $mode,
                    $databasePath,
                    $lockPlans,
                    null,
                    $lock['busy'],
                    $lock['reason'],
                    ['sqlite-pager-checkpoint-transaction']
                );
            }
        }

        $writePlan = SQLiteWalFileWritePlan::checkpoint($wal, $databaseBytes, $databasePath, $mode, $readerEndFrame, false, false);
        if ($writePlan['busy']) {
            return self::result(
                'busy',
                false,
                $connection,
                $mode,
                $databasePath,
                $lockPlans,
                $writePlan,
                null,
                $writePlan['reason'],
                $writePlan['dependencies']
            );
        }

        return self::result(
            'ready',
            true,
            $connection,
            $mode,
            $databasePath,
            $lockPlans,
            $writePlan,
            null,
            $writePlan['reason'],
            $writePlan['dependencies']
        );
    }

    /**
     * @return list<string>
     */
    private static function lockSequence(string $mode): array
    {
        return match ($mode) {
            'passive', 'noop' => ['shared'],
            'full' => ['shared', 'reserved', 'pending', 'exclusive'],
            'restart', 'truncate' => ['shared', 'reserved', 'pending', 'exclusive'],
        };
    }

    /**
     * @param list<array<string, mixed>> $lockPlans
     * @param array<string, mixed>|null $writePlan
     * @param array<string, mixed>|null $busy
     * @param list<string> $dependencies
     * @return array{status:string,can_checkpoint:bool,connection:string,mode:string,database_path:string,lock_sequence:list<array<string, mixed>>,write_plan:array<string, mixed>|null,busy:array<string, mixed>|null,reason:string|null,dependencies:list<string>}
     */
    private static function result(
        string $status,
        bool $canCheckpoint,
        string $connection,
        string $mode,
        string $databasePath,
        array $lockPlans,
        ?array $writePlan,
        ?array $busy,
        ?string $reason,
        array $dependencies
    ): array {
        $lockDependencies = [];
        foreach ($lockPlans as $lockPlan) {
            foreach ($lockPlan['dependencies'] ?? [] as $dependency) {
                $lockDependencies[] = (string) $dependency;
            }
        }

        return [
            'status' => $status,
            'can_checkpoint' => $canCheckpoint,
            'connection' => $connection,
            'mode' => $mode,
            'database_path' => $databasePath,
            'lock_sequence' => $lockPlans,
            'write_plan' => $writePlan,
            'busy' => $busy,
            'reason' => $reason,
            'dependencies' => array_values(array_unique(array_merge(
                ['sqlite-pager-checkpoint-transaction'],
                $lockDependencies,
                $dependencies
            ))),
        ];
    }
}
