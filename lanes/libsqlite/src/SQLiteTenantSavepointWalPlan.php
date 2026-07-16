<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteTenantSavepointWalPlan
{
    /**
     * @param list<array{tenant_id:int,database_path:string,database_bytes:string,wal:SQLiteWal,wal_bytes:string,savepoints:SQLiteSavepointStack,savepoint:string,page_numbers:list<int>}> $tenants
     * @return array{status:string,tenant_count:int,rolled_back_tenant_count:int,stable_tenant_count:int,total_restored_pages:int,total_discarded_wal_frames:int,tenants:list<array<string,mixed>>,current_reader_matrix:array<int,list<string>>,next_reader_matrix:array<int,list<string>>,dependencies:list<string>}
     */
    public static function rollbackToAcrossTenants(array $tenants, int $pageSize): array
    {
        if ($pageSize < 1) {
            throw new \InvalidArgumentException('SQLite tenant savepoint WAL plan requires a positive page size');
        }
        if ($tenants === []) {
            throw new \InvalidArgumentException('SQLite tenant savepoint WAL plan requires at least one tenant');
        }

        $summaries = [];
        $currentReaderMatrix = [];
        $nextReaderMatrix = [];
        $dependencies = ['sqlite-application-tenant-savepoint-wal-current-next'];
        $rolledBack = 0;
        $stable = 0;
        $totalRestoredPages = 0;
        $totalDiscardedWalFrames = 0;

        foreach ($tenants as $tenant) {
            $summary = self::tenantSummary($tenant, $pageSize);
            $summaries[] = $summary;
            $currentReaderMatrix[$summary['tenant_id']] = $summary['current_reader_sources'];
            $nextReaderMatrix[$summary['tenant_id']] = $summary['next_reader_sources'];
            $dependencies = array_merge($dependencies, $summary['dependencies']);

            if ($summary['rolled_back']) {
                $rolledBack++;
            } else {
                $stable++;
            }
            $totalRestoredPages += $summary['restored_page_count'];
            $totalDiscardedWalFrames += $summary['discarded_wal_frame_count'];
        }

        return [
            'status' => $rolledBack > 0 ? 'rolled_back' : 'stable',
            'tenant_count' => count($summaries),
            'rolled_back_tenant_count' => $rolledBack,
            'stable_tenant_count' => $stable,
            'total_restored_pages' => $totalRestoredPages,
            'total_discarded_wal_frames' => $totalDiscardedWalFrames,
            'tenants' => $summaries,
            'current_reader_matrix' => $currentReaderMatrix,
            'next_reader_matrix' => $nextReaderMatrix,
            'dependencies' => array_values(array_unique($dependencies)),
        ];
    }

    /**
     * @param array{tenant_id:int,database_path:string,database_bytes:string,wal:SQLiteWal,wal_bytes:string,savepoints:SQLiteSavepointStack,savepoint:string,page_numbers:list<int>} $tenant
     * @return array<string,mixed>
     */
    private static function tenantSummary(array $tenant, int $pageSize): array
    {
        foreach (['tenant_id', 'database_path', 'database_bytes', 'wal', 'wal_bytes', 'savepoints', 'savepoint', 'page_numbers'] as $key) {
            if (!array_key_exists($key, $tenant)) {
                throw new \InvalidArgumentException("SQLite tenant savepoint WAL entry is missing {$key}");
            }
        }
        if (!is_int($tenant['tenant_id']) || $tenant['tenant_id'] < 1) {
            throw new \InvalidArgumentException('SQLite tenant savepoint WAL tenant ids must be positive integers');
        }
        if (!is_string($tenant['database_path']) || $tenant['database_path'] === '') {
            throw new \InvalidArgumentException('SQLite tenant savepoint WAL database path must not be empty');
        }
        if (!is_string($tenant['database_bytes']) || strlen($tenant['database_bytes']) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite tenant savepoint WAL database bytes must be page aligned');
        }
        if (!$tenant['wal'] instanceof SQLiteWal || !$tenant['savepoints'] instanceof SQLiteSavepointStack) {
            throw new \InvalidArgumentException('SQLite tenant savepoint WAL entry requires WAL and savepoint objects');
        }
        if (!is_string($tenant['wal_bytes']) || $tenant['wal_bytes'] === '') {
            throw new \InvalidArgumentException('SQLite tenant savepoint WAL bytes must not be empty');
        }
        if (!is_string($tenant['savepoint']) || $tenant['savepoint'] === '') {
            throw new \InvalidArgumentException('SQLite tenant savepoint WAL savepoint name must not be empty');
        }
        if (!is_array($tenant['page_numbers']) || $tenant['page_numbers'] === []) {
            throw new \InvalidArgumentException('SQLite tenant savepoint WAL page list must not be empty');
        }

        $imagePlan = $tenant['savepoints']->rollbackToImagePlan($tenant['savepoint'], $pageSize);
        $rolledBackDatabaseBytes = $tenant['savepoints']->rollbackToDatabaseImage($tenant['savepoint'], $tenant['database_bytes'], $pageSize);
        $recovery = SQLiteWalSavepointRecoveryPlan::currentNextAfterRollbackTo(
            $tenant['savepoints'],
            $tenant['savepoint'],
            $tenant['wal'],
            $tenant['wal_bytes'],
            $rolledBackDatabaseBytes,
            $tenant['page_numbers']
        );

        $rolledBack = $imagePlan['restored_page_numbers'] !== [] || $recovery['discarded_frame_count'] > 0;

        return [
            'tenant_id' => $tenant['tenant_id'],
            'database_path' => $tenant['database_path'],
            'savepoint' => $tenant['savepoint'],
            'rolled_back' => $rolledBack,
            'restored_page_numbers' => $imagePlan['restored_page_numbers'],
            'restored_page_count' => count($imagePlan['restored_page_numbers']),
            'missing_page_numbers' => $imagePlan['missing_page_numbers'],
            'rollback_to_frame' => $recovery['rollback_to_frame'],
            'retained_wal_frame_count' => $recovery['retained_frame_count'],
            'discarded_wal_frame_count' => $recovery['discarded_frame_count'],
            'current_wal_bytes_length' => $recovery['current_wal_bytes_length'],
            'current_reader_sources' => $recovery['current_reader_sources'],
            'next_reader_sources' => $recovery['next_reader_sources'],
            'current_reader_frame_indexes' => $recovery['current_reader_frame_indexes'],
            'next_reader_frame_indexes' => $recovery['next_reader_frame_indexes'],
            'current_reader_errors' => $recovery['current_reader_errors'],
            'next_reader_errors' => $recovery['next_reader_errors'],
            'images_match' => $recovery['images_match'],
            'can_checkpoint' => $recovery['can_checkpoint'],
            'checkpoint_database_page_count' => $recovery['checkpoint_database_page_count'],
            'dependencies' => array_values(array_unique(array_merge(
                ['sqlite-application-tenant-entry-savepoint-wal'],
                $recovery['dependencies']
            ))),
        ];
    }
}
