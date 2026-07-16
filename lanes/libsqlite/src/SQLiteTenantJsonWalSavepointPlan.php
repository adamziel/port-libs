<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteTenantJsonWalSavepointPlan
{
    /**
     * @param list<array{tenant_id:int|string,table_name?:string,current_rows:list<array<string,mixed>>,json_imports:list<array{name?:string,json:mixed,path?:string,release?:bool,on_conflict?:string}>}> $tenants
     * @param array{database_path?:string,page_size?:int,journal_mode?:string,sync_mode?:string,replace_conflicts?:bool,continue_on_tenant_error?:bool,continue_on_global_error?:bool,rollback_all_on_error?:bool,global_table_name?:string,global_json_imports?:list<array{name?:string,json:mixed,path?:string,release?:bool,on_conflict?:string}>} $options
     * @return array<string,mixed>
     */
    public static function plan(array $tenants, array $options = []): array
    {
        if ($tenants === []) {
            throw new \InvalidArgumentException('SQLite tenant JSON WAL savepoint plan requires at least one tenant');
        }

        $databasePath = (string) ($options['database_path'] ?? '/tmp/sqlite-tenant-json-import.sqlite');
        $pageSize = (int) ($options['page_size'] ?? 4096);
        $journalMode = strtolower((string) ($options['journal_mode'] ?? 'wal'));
        $syncMode = strtolower((string) ($options['sync_mode'] ?? 'normal'));
        $replaceConflicts = (bool) ($options['replace_conflicts'] ?? true);
        $continueOnTenantError = (bool) ($options['continue_on_tenant_error'] ?? true);
        $continueOnGlobalError = (bool) ($options['continue_on_global_error'] ?? true);
        $rollbackAllOnError = (bool) ($options['rollback_all_on_error'] ?? false);
        $globalTableName = self::identifier((string) ($options['global_table_name'] ?? 'kv_global'), 'global table name');
        $globalImports = $options['global_json_imports'] ?? [];

        if (!is_array($globalImports)) {
            throw new \InvalidArgumentException('SQLite tenant JSON WAL global imports must be a list');
        }
        if ($databasePath === '' || $databasePath[0] !== '/' || str_contains($databasePath, "\0") || str_contains($databasePath, '..')) {
            throw new \InvalidArgumentException('SQLite tenant JSON WAL plan requires a safe absolute database path');
        }
        if ($pageSize < 512 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite tenant JSON WAL page size must be a power of two at least 512');
        }
        if (!in_array($journalMode, ['wal', 'delete', 'truncate', 'persist'], true)) {
            throw new \InvalidArgumentException('SQLite tenant JSON WAL journal mode must be wal, delete, truncate, or persist');
        }
        if (!in_array($syncMode, ['off', 'normal', 'full'], true)) {
            throw new \InvalidArgumentException('SQLite tenant JSON WAL sync mode must be off, normal, or full');
        }

        $tenantPlans = [];
        $releasedTenants = [];
        $rolledBackTenants = [];
        $tableNames = [];
        $finalRowsByTable = [];
        $releasedRowsByTable = [];
        $dirtyPages = [];
        $walFrames = [];
        $walFrameCount = 0;
        $walBytes = 0;

        foreach (array_values($tenants) as $tenantIndex => $tenant) {
            $tenantId = self::tenantId($tenant);
            if (isset($tenantPlans[$tenantId])) {
                throw new \InvalidArgumentException("Duplicate SQLite tenant id {$tenantId}");
            }

            $imports = $tenant['json_imports'] ?? null;
            if (!is_array($imports)) {
                throw new \InvalidArgumentException('SQLite tenant JSON WAL imports must be a list');
            }

            $tableName = self::keyValueTableName($tenant, $tenantId);
            $tableNames[] = $tableName;
            $prefixedImports = [];
            foreach (array_values($imports) as $importIndex => $import) {
                $prefixedImports[] = self::prefixedImport($import, "tenant{$tenantId}", $importIndex);
            }

            try {
                $tenantPlan = SQLiteJsonImportWalSavepointPlan::plan($tenant['current_rows'], $prefixedImports, [
                    'database_path' => $databasePath,
                    'page_size' => $pageSize,
                    'journal_mode' => $journalMode,
                    'sync_mode' => $syncMode,
                    'replace_conflicts' => $replaceConflicts,
                ]);
                $status = $tenantPlan['rolled_back_batches'] === [] ? 'released' : 'partial';
                if ($status === 'released') {
                    $releasedTenants[] = $tenantId;
                } else {
                    $rolledBackTenants[] = $tenantId;
                }
            } catch (\Throwable $exception) {
                if (!$continueOnTenantError) {
                    throw $exception;
                }

                $tenantPlan = self::rolledBackTenantPlan($tenant['current_rows'], $prefixedImports, $exception->getMessage());
                $status = 'rolled_back';
                $rolledBackTenants[] = $tenantId;
            }

            foreach (($tenantPlan['dirty_pages'] ?? []) as $pageNumber) {
                $dirtyPages[self::tenantPageNumber($tenantId, (int) $pageNumber)] = true;
            }

            foreach (($tenantPlan['wal']['frames'] ?? []) as $frame) {
                $walFrameCount++;
                $walFrames[] = self::tenantWalFrame($tenantId, $tableName, $walFrameCount, $frame);
            }
            $walBytes += (int) ($tenantPlan['wal']['bytes'] ?? 0);

            $finalRowsByTable[$tableName] = $tenantPlan['final_rows'];
            $releasedRowsByTable[$tableName] = $tenantPlan['released_rows'];
            $tenantPlans[$tenantId] = [
                'tenant_id' => $tenantId,
                'table' => $tableName,
                'status' => $status,
                'savepoint_prefix' => "tenant{$tenantId}",
                'plan' => $tenantPlan,
            ];
            unset($tenantIndex);
        }

        $globalPlan = null;
        if ($globalImports !== []) {
            $prefixedGlobal = [];
            foreach (array_values($globalImports) as $importIndex => $import) {
                $prefixedGlobal[] = self::prefixedImport($import, 'global', $importIndex);
            }

            try {
                $globalPlan = SQLiteJsonImportWalSavepointPlan::plan([], $prefixedGlobal, [
                    'database_path' => $databasePath,
                    'page_size' => $pageSize,
                    'journal_mode' => $journalMode,
                    'sync_mode' => $syncMode,
                    'replace_conflicts' => $replaceConflicts,
                ]);
            } catch (\Throwable $exception) {
                if (!$continueOnGlobalError) {
                    throw $exception;
                }

                $globalPlan = self::rolledBackTenantPlan([], $prefixedGlobal, $exception->getMessage());
            }
            foreach ($globalPlan['dirty_pages'] as $pageNumber) {
                $dirtyPages[self::tenantPageNumber(0, (int) $pageNumber)] = true;
            }
            foreach ($globalPlan['wal']['frames'] as $frame) {
                $walFrameCount++;
                $walFrames[] = self::tenantWalFrame(0, $globalTableName, $walFrameCount, $frame);
            }
            $walBytes += (int) $globalPlan['wal']['bytes'];
            $finalRowsByTable[$globalTableName] = $globalPlan['final_rows'];
            $releasedRowsByTable[$globalTableName] = $globalPlan['released_rows'];
        }

        $aggregateRolledBack = $rollbackAllOnError && ($rolledBackTenants !== [] || self::planHasRollback($globalPlan));
        $publishedWalFrames = $aggregateRolledBack ? [] : $walFrames;
        $publishedWalBytes = $aggregateRolledBack ? 32 : $walBytes;

        ksort($dirtyPages);
        ksort($tenantPlans);
        sort($tableNames);

        return [
            'status' => 'planned',
            'database_path' => $databasePath,
            'page_size' => $pageSize,
            'journal_mode' => $journalMode,
            'sync_mode' => $syncMode,
            'tenant_count' => count($tenantPlans),
            'table_names' => $tableNames,
            'released_tenants' => $releasedTenants,
            'rolled_back_tenants' => $rolledBackTenants,
            'tenants' => array_values($tenantPlans),
            'global_plan' => $globalPlan,
            'aggregate_rollback' => [
                'enabled' => $rollbackAllOnError,
                'required' => $rolledBackTenants !== [] || self::planHasRollback($globalPlan),
                'applied' => $aggregateRolledBack,
                'reason' => $aggregateRolledBack ? 'tenant_json_import_error' : null,
                'frame_count_before' => count($walFrames),
                'frame_count_after' => count($publishedWalFrames),
                'wal_bytes_before' => $walBytes,
                'wal_bytes_after' => $publishedWalBytes,
                'discarded_frame_count' => $aggregateRolledBack ? count($walFrames) : 0,
                'discarded_tables' => $aggregateRolledBack ? array_values(array_unique(array_column($walFrames, 'table'))) : [],
            ],
            'final_rows_by_table' => $finalRowsByTable,
            'released_rows_by_table' => $releasedRowsByTable,
            'dirty_pages' => array_map('intval', array_keys($dirtyPages)),
            'aggregate_wal' => [
                'path' => $databasePath . '-wal',
                'frame_count' => count($publishedWalFrames),
                'bytes' => $publishedWalBytes,
                'frames' => $publishedWalFrames,
            ],
            'dependencies' => [
                'sqlite-tenant-json-wal-savepoint',
                'sqlite-json-import-wal-savepoint',
                'sqlite-savepoint-wal-rollback',
            ],
        ];
    }

    /**
     * @param array<string,mixed> $tenant
     */
    private static function tenantId(array $tenant): int
    {
        $tenantId = $tenant['tenant_id'] ?? null;
        if (!is_int($tenantId) && !(is_string($tenantId) && ctype_digit($tenantId))) {
            throw new \InvalidArgumentException('SQLite tenant JSON WAL tenant_id must be a positive integer');
        }
        $tenantId = (int) $tenantId;
        if ($tenantId <= 0) {
            throw new \InvalidArgumentException('SQLite tenant JSON WAL tenant_id must be positive');
        }
        if (!isset($tenant['current_rows']) || !is_array($tenant['current_rows'])) {
            throw new \InvalidArgumentException('SQLite tenant JSON WAL current rows must be a list');
        }

        return $tenantId;
    }

    /**
     * @param array<string,mixed> $tenant
     */
    private static function keyValueTableName(array $tenant, int $tenantId): string
    {
        return self::identifier((string) ($tenant['table_name'] ?? 'kv_tenant_' . $tenantId), 'tenant table name');
    }

    /**
     * @param array<string,mixed> $import
     * @return array<string,mixed>
     */
    private static function prefixedImport(array $import, string $prefix, int $index): array
    {
        $copy = $import;
        $name = (string) ($copy['name'] ?? 'json_import_' . ($index + 1));
        $copy['name'] = $prefix . '_' . $name;

        return $copy;
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $imports
     * @return array<string,mixed>
     */
    private static function rolledBackTenantPlan(array $currentRows, array $imports, string $error): array
    {
        return [
            'status' => 'rolled_back',
            'batch_count' => count($imports),
            'released_batches' => [],
            'rolled_back_batches' => array_map(
                static fn (array $import, int $index): string => (string) ($import['name'] ?? 'json_import_' . ($index + 1)),
                $imports,
                array_keys($imports)
            ),
            'batches' => [],
            'current_rows' => $currentRows,
            'final_rows' => $currentRows,
            'released_rows' => $currentRows,
            'final_key_names' => array_column($currentRows, 'key_name'),
            'released_key_names' => array_column($currentRows, 'key_name'),
            'dirty_pages' => [],
            'wal' => [
                'path' => null,
                'frame_count' => 0,
                'current_frame' => 0,
                'frames' => [],
                'bytes' => 0,
            ],
            'error' => $error,
        ];
    }

    /**
     * @param array<string,mixed> $frame
     * @return array<string,mixed>
     */
    private static function tenantWalFrame(int $tenantId, string $tableName, int $aggregateFrameIndex, array $frame): array
    {
        $pageNumber = (int) ($frame['page_number'] ?? 0);

        return $frame + [
            'aggregate_frame_index' => $aggregateFrameIndex,
            'tenant_id' => $tenantId,
            'table' => $tableName,
            'aggregate_page_number' => self::tenantPageNumber($tenantId, $pageNumber),
        ];
    }

    /**
     * @param array<string,mixed>|null $plan
     */
    private static function planHasRollback(?array $plan): bool
    {
        return $plan !== null && (
            ($plan['status'] ?? null) === 'rolled_back'
            || (($plan['rolled_back_batches'] ?? []) !== [])
        );
    }

    private static function tenantPageNumber(int $tenantId, int $pageNumber): int
    {
        return ($tenantId * 100000) + $pageNumber;
    }

    private static function identifier(string $identifier, string $label): string
    {
        if ($identifier === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier) !== 1) {
            throw new \InvalidArgumentException("SQLite tenant JSON WAL {$label} must be a SQL identifier");
        }

        return $identifier;
    }
}
