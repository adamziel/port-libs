<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteJsonImportRollbackWalPlan
{
    /**
     * @param list<array{option_id:int,option_name:string,option_value:mixed,autoload?:string,page_number?:int}> $currentRows
     * @param list<array{option_name:string,function?:string,path:string,value:mixed,page_number?:int,wal_frame_index?:int,statement?:string}> $mutations
     * @param array{database_bytes:string,page_size?:int,wal_bytes?:string,rollback_on_error?:bool,savepoint?:string,transaction?:string} $options
     * @return array<string,mixed>
     */
    public static function plan(array $currentRows, array $mutations, array $options): array
    {
        $pageSize = (int) ($options['page_size'] ?? 512);
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite Application JSON import rollback page size must be a power of two at least 512');
        }

        $databaseBytes = $options['database_bytes'] ?? null;
        if (!is_string($databaseBytes) || $databaseBytes === '' || strlen($databaseBytes) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite Application JSON import rollback requires a page-aligned database image');
        }

        $walBytes = $options['wal_bytes'] ?? self::emptyWalBytes($pageSize);
        if ($walBytes === null) {
            $walBytes = self::emptyWalBytes($pageSize);
        }
        if (!is_string($walBytes)) {
            throw new \InvalidArgumentException('SQLite Application JSON import rollback WAL bytes must be a string');
        }
        $walState = self::walState($walBytes, $pageSize);

        $importPlan = SQLiteJsonImportSavepointPlan::plan(
            $currentRows,
            $mutations,
            [
                'database_bytes' => $databaseBytes,
                'page_size' => $pageSize,
                'savepoint' => $options['savepoint'] ?? 'current_json_batch',
                'transaction' => $options['transaction'] ?? 'application_json_import',
            ]
        );

        $rollbackRequired = (bool) ($options['rollback_on_error'] ?? true) && $importPlan['failed'] !== [];
        $rollbackToFrame = (int) $importPlan['wal_rollback_to_savepoint']['rollback_to_frame'];
        if ($rollbackToFrame > $walState['frame_count']) {
            throw new \InvalidArgumentException('SQLite Application JSON import rollback frame is beyond the WAL byte stream');
        }

        $truncateToBytes = 32 + ($rollbackToFrame * (24 + $pageSize));
        $rolledBackWalBytes = $rollbackRequired ? substr($walBytes, 0, $truncateToBytes) : $walBytes;
        $rolledBackDatabaseBytes = $rollbackRequired ? $databaseBytes : (string) $importPlan['database_bytes'];
        $failedStatements = array_map(
            static fn (array $failure): string => (string) $failure['statement'],
            $importPlan['failed']
        );

        return [
            'status' => $rollbackRequired ? 'rolled_back_current_json_batch' : $importPlan['status'],
            'rollback_required' => $rollbackRequired,
            'transaction' => $importPlan['transaction'],
            'savepoint' => $importPlan['savepoint'],
            'page_size' => $pageSize,
            'failed_statements' => $failedStatements,
            'applied_statement_count' => count($importPlan['applied']),
            'failed_statement_count' => count($importPlan['failed']),
            'restored_database_bytes' => $rolledBackDatabaseBytes,
            'database_bytes_before' => $databaseBytes,
            'database_bytes_after_import' => $importPlan['database_bytes'],
            'database_restored_to_before' => $rolledBackDatabaseBytes === $databaseBytes,
            'database_changed_before_rollback' => $importPlan['database_bytes'] !== $databaseBytes,
            'wal_bytes_before' => $walBytes,
            'wal_bytes_after' => $rolledBackWalBytes,
            'wal_frame_count_before' => $walState['frame_count'],
            'wal_frame_count_after' => self::walState($rolledBackWalBytes, $pageSize)['frame_count'],
            'wal_truncate_to_bytes' => $truncateToBytes,
            'wal_truncated' => $rollbackRequired && strlen($rolledBackWalBytes) < strlen($walBytes),
            'discarded_wal_frame_count' => $rollbackRequired ? $walState['frame_count'] - $rollbackToFrame : 0,
            'rollback_to_savepoint' => $importPlan['rollback_to_savepoint'],
            'wal_rollback_to_savepoint' => $importPlan['wal_rollback_to_savepoint'],
            'import_plan' => $importPlan,
            'dependencies' => [
                'sqlite-application-json-import-savepoint-current',
                'sqlite-savepoint-wal-rollback-current',
                'sqlite-wal-current-batch-byte-truncation',
            ],
        ];
    }

    private static function emptyWalBytes(int $pageSize): string
    {
        return pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 0, 0x51, 0x52, 0, 0);
    }

    /**
     * @return array{frame_count:int,frame_size:int}
     */
    private static function walState(string $walBytes, int $pageSize): array
    {
        if (strlen($walBytes) < 32) {
            throw new \InvalidArgumentException('SQLite Application JSON import rollback WAL bytes require a 32 byte header');
        }

        $frameSize = 24 + $pageSize;
        $frameBytes = strlen($walBytes) - 32;
        if ($frameBytes % $frameSize !== 0) {
            throw new \InvalidArgumentException('SQLite Application JSON import rollback WAL bytes have a partial frame tail');
        }

        return [
            'frame_count' => intdiv($frameBytes, $frameSize),
            'frame_size' => $frameSize,
        ];
    }
}
