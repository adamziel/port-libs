<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalPersistPlan
{
    /**
     * @return array{status:string,file_control:array{previous:bool,current:bool,result:list<int>,changed:bool},files:array{database:bool,wal:bool,shm:bool,wal_size:int},close:array{wal_retained:bool,shm_retained:bool,wal_size:int},source:string,dependencies:list<string>}
     */
    public static function closePlan(
        bool $databaseExists,
        bool $walExists,
        bool $shmExists,
        int $walSize,
        bool $persistWalBefore,
        ?bool $persistWalSet,
        ?int $journalSizeLimit,
        string $journalMode = 'wal'
    ): array {
        if ($walSize < 0) {
            throw new \InvalidArgumentException('SQLite WAL persistent close plan requires a non-negative WAL size');
        }
        if ($journalSizeLimit !== null && $journalSizeLimit < -1) {
            throw new \InvalidArgumentException('SQLite journal_size_limit must be -1 or non-negative');
        }

        $mode = self::journalMode($journalMode);
        $persistWal = $mode === 'wal' && ($persistWalSet ?? $persistWalBefore);
        $limitTruncates = $persistWal && $journalSizeLimit !== null && $journalSizeLimit >= 0;
        $walRetained = $databaseExists && $walExists && $persistWal;
        $shmRetained = $databaseExists && $shmExists && $persistWal;
        $closedWalSize = $walRetained ? ($limitTruncates ? 0 : $walSize) : 0;

        return [
            'status' => $mode === 'wal'
                ? ($persistWal ? 'persistent-wal-close' : 'delete-wal-close')
                : 'journal-mode-leaves-wal',
            'file_control' => [
                'previous' => $persistWalBefore,
                'current' => $persistWal,
                'result' => [0, $persistWal ? 1 : 0],
                'changed' => $persistWalSet !== null && $persistWalSet !== $persistWalBefore,
            ],
            'files' => [
                'database' => $databaseExists,
                'wal' => $walExists,
                'shm' => $shmExists,
                'wal_size' => $walSize,
            ],
            'close' => [
                'wal_retained' => $walRetained,
                'shm_retained' => $shmRetained,
                'wal_size' => $closedWalSize,
            ],
            'source' => 'upstream walpersist.test 1.* 2.* 3.*',
            'dependencies' => [
                'sqlite-wal-persistent-file-control',
                'sqlite-wal-close-sidecar-retention',
                'sqlite-journal-size-limit-wal-truncation',
            ],
        ];
    }

    /**
     * @param list<string> $modes
     * @return array{results:list<string>,final_mode:string,persist_wal:bool,source:string}
     */
    public static function journalModeSequence(array $modes, bool $persistWal): array
    {
        $results = [];
        $final = 'delete';
        foreach ($modes as $mode) {
            $final = self::journalMode($mode);
            $results[] = $final;
            if ($final !== 'wal') {
                $persistWal = false;
            }
        }

        return [
            'results' => $results,
            'final_mode' => $final,
            'persist_wal' => $persistWal && $final === 'wal',
            'source' => 'upstream walpersist.test 4.1',
        ];
    }

    private static function journalMode(string $journalMode): string
    {
        $mode = strtolower(trim($journalMode));
        if (!in_array($mode, ['delete', 'truncate', 'persist', 'memory', 'wal', 'off'], true)) {
            throw new \InvalidArgumentException("Unsupported SQLite journal mode: {$journalMode}");
        }

        return $mode;
    }
}
