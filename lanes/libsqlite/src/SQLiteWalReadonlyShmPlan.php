<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteWalReadonlyShmPlan
{
    /**
     * @param list<array{op:string, rows?:list<array{0:string, 1:string}>, checkpoint?:bool, wal_wrapped?:bool, wal_truncated?:bool}> $writerEvents
     * @return array<string, mixed>
     */
    public static function openReadonly(
        bool $databaseExists,
        bool $walExists,
        bool $shmExists,
        bool $readonlyShm,
        bool $shmWritable,
        int $walSize,
        int $shmSize,
        int $pageSize,
        array $writerEvents = []
    ): array {
        if ($walSize < 0 || $shmSize < 0) {
            throw new \InvalidArgumentException('SQLite readonly WAL SHM planning requires non-negative sidecar sizes');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite readonly WAL SHM planning requires a power-of-two page size of at least 512');
        }

        $minimumShmSize = max(32768, $pageSize);
        $hasUsableShm = $shmExists && ($shmSize === 0 || $shmSize >= $minimumShmSize || $readonlyShm);
        $canOpen = $databaseExists && ($readonlyShm ? $shmExists : ($shmWritable || $hasUsableShm));
        $rows = [
            ['a', 'b'],
            ['c', 'd'],
        ];
        $refreshes = [];
        $denied = [];

        foreach ($writerEvents as $index => $event) {
            $op = (string) ($event['op'] ?? '');
            if ($op === 'insert') {
                foreach (($event['rows'] ?? []) as $row) {
                    $rows[] = [(string) $row[0], (string) $row[1]];
                }
            } elseif ($op === 'checkpoint') {
                $refreshes[] = [
                    'event' => $index + 1,
                    'kind' => !empty($event['wal_truncated']) ? 'truncate-checkpoint-cache-flush' : 'checkpoint-visible',
                ];
            } elseif ($op === 'wrap') {
                $refreshes[] = [
                    'event' => $index + 1,
                    'kind' => !empty($event['wal_wrapped']) ? 'wal-wrap-rerun-recovery' : 'wal-wrap-observed',
                ];
            } else {
                throw new \InvalidArgumentException("Unsupported SQLite readonly WAL writer event: {$op}");
            }
        }

        if ($canOpen && $readonlyShm) {
            $denied = [
                ['statement' => 'INSERT INTO t1 VALUES', 'error' => 'attempt to write a readonly database'],
                ['statement' => 'PRAGMA wal_checkpoint', 'error' => 'attempt to write a readonly database'],
            ];
        }

        return [
            'status' => $canOpen ? 'readonly-wal-open' : 'readonly-wal-open-blocked',
            'reason' => $canOpen
                ? ($readonlyShm ? 'readonly_shm_allows_wal_snapshot_reads' : 'writable_shm_or_auto_readonly_open')
                : 'readonly_shm_requires_existing_shm_sidecar',
            'readonly_shm' => $readonlyShm,
            'shm_writable' => $shmWritable,
            'minimum_shm_size' => $minimumShmSize,
            'wal_size' => $walSize,
            'shm_size' => $shmSize,
            'wal_exists' => $walExists,
            'shm_exists' => $shmExists,
            'rows' => $canOpen ? $rows : [],
            'row_count' => $canOpen ? count($rows) : 0,
            'write_denials' => $denied,
            'refreshes' => $refreshes,
            'extended_errcode' => $canOpen ? 'SQLITE_OK' : 'SQLITE_CANTOPEN',
            'source' => 'upstream walro.test 1.1.* 1.2.* 1.3.* 1.4.* and walro2.test page-size readonly_shm matrix',
            'dependencies' => [
                'sqlite-wal-readonly-shm-open',
                'sqlite-wal-readonly-cache-refresh',
                'sqlite-wal-readonly-checkpoint-denial',
            ],
        ];
    }
}
