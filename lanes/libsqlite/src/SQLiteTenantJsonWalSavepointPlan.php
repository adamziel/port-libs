<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteTenantJsonWalSavepointPlan
{
    /**
     * @param list<array{tenant_id:int|string,table_name?:string,current_rows:list<array<string,mixed>>,json_imports:list<array{name?:string,json:mixed,path?:string,release?:bool,on_conflict?:string}>}> $sites
     * @param array{database_path?:string,page_size?:int,journal_mode?:string,sync_mode?:string,replace_conflicts?:bool,continue_on_site_error?:bool,global_table_name?:string,global_json_imports?:list<array{name?:string,json:mixed,path?:string,release?:bool,on_conflict?:string}>} $options
     * @return array<string,mixed>
     */
    public static function plan(array $sites, array $options = []): array
    {
        if ($sites === []) {
            throw new \InvalidArgumentException('SQLite tenant JSON WAL savepoint plan requires at least one tenant');
        }

        $databasePath = (string) ($options['database_path'] ?? '/tmp/sqlite-tenant-json-import.sqlite');
        $pageSize = (int) ($options['page_size'] ?? 4096);
        $journalMode = strtolower((string) ($options['journal_mode'] ?? 'wal'));
        $syncMode = strtolower((string) ($options['sync_mode'] ?? 'normal'));
        $replaceConflicts = (bool) ($options['replace_conflicts'] ?? true);
        $continueOnSiteError = (bool) ($options['continue_on_site_error'] ?? true);
        $globalTableName = self::identifier((string) ($options['global_table_name'] ?? 'kv_global'), 'global table name');
        $globalImports = $options['global_json_imports'] ?? [];

        if (!is_array($globalImports)) {
            throw new \InvalidArgumentException('SQLite tenant JSON WAL global imports must be a list');
        }

        $sitePlans = [];
        $releasedSites = [];
        $rolledBackSites = [];
        $tableNames = [];
        $finalRowsByTable = [];
        $releasedRowsByTable = [];
        $dirtyPages = [];
        $walFrames = [];
        $walFrameCount = 0;
        $walBytes = 0;

        foreach (array_values($sites) as $siteIndex => $site) {
            $tenantId = self::tenantId($site);
            if (isset($sitePlans[$tenantId])) {
                throw new \InvalidArgumentException("Duplicate SQLite tenant id {$tenantId}");
            }

            $imports = $site['json_imports'] ?? null;
            if (!is_array($imports)) {
                throw new \InvalidArgumentException('SQLite tenant JSON WAL imports must be a list');
            }

            $tableName = self::keyValueTableName($site, $tenantId);
            $tableNames[] = $tableName;
            $prefixedImports = [];
            foreach (array_values($imports) as $importIndex => $import) {
                $prefixedImports[] = self::prefixedImport($import, "tenant{$tenantId}", $importIndex);
            }

            try {
                $sitePlan = SQLiteJsonImportWalSavepointPlan::plan($site['current_rows'], $prefixedImports, [
                    'database_path' => $databasePath,
                    'page_size' => $pageSize,
                    'journal_mode' => $journalMode,
                    'sync_mode' => $syncMode,
                    'replace_conflicts' => $replaceConflicts,
                ]);
                $status = $sitePlan['rolled_back_batches'] === [] ? 'released' : 'partial';
                if ($status === 'released') {
                    $releasedSites[] = $tenantId;
                } else {
                    $rolledBackSites[] = $tenantId;
                }
            } catch (\Throwable $exception) {
                if (!$continueOnSiteError) {
                    throw $exception;
                }

                $sitePlan = self::rolledBackTenantPlan($site['current_rows'], $prefixedImports, $exception->getMessage());
                $status = 'rolled_back';
                $rolledBackSites[] = $tenantId;
            }

            foreach (($sitePlan['dirty_pages'] ?? []) as $pageNumber) {
                $dirtyPages[self::tenantPageNumber($tenantId, (int) $pageNumber)] = true;
            }

            foreach (($sitePlan['wal']['frames'] ?? []) as $frame) {
                $walFrameCount++;
                $walFrames[] = self::tenantWalFrame($tenantId, $tableName, $walFrameCount, $frame);
            }
            $walBytes += (int) ($sitePlan['wal']['bytes'] ?? 0);

            $finalRowsByTable[$tableName] = $sitePlan['final_rows'];
            $releasedRowsByTable[$tableName] = $sitePlan['released_rows'];
            $sitePlans[$tenantId] = [
                'tenant_id' => $tenantId,
                'table' => $tableName,
                'status' => $status,
                'savepoint_prefix' => "tenant{$tenantId}",
                'plan' => $sitePlan,
            ];
            unset($siteIndex);
        }

        $globalPlan = null;
        if ($globalImports !== []) {
            $prefixedGlobal = [];
            foreach (array_values($globalImports) as $importIndex => $import) {
                $prefixedGlobal[] = self::prefixedImport($import, 'network', $importIndex);
            }

            $globalPlan = SQLiteJsonImportWalSavepointPlan::plan([], $prefixedGlobal, [
                'database_path' => $databasePath,
                'page_size' => $pageSize,
                'journal_mode' => $journalMode,
                'sync_mode' => $syncMode,
                'replace_conflicts' => $replaceConflicts,
            ]);
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

        ksort($dirtyPages);
        ksort($sitePlans);
        sort($tableNames);

        return [
            'status' => 'planned',
            'database_path' => $databasePath,
            'page_size' => $pageSize,
            'journal_mode' => $journalMode,
            'sync_mode' => $syncMode,
            'tenant_count' => count($sitePlans),
            'site_count' => count($sitePlans),
            'table_names' => $tableNames,
            'released_tenants' => $releasedSites,
            'rolled_back_tenants' => $rolledBackSites,
            'tenants' => array_values($sitePlans),
            'sites' => array_values($sitePlans),
            'global_plan' => $globalPlan,
            'final_rows_by_table' => $finalRowsByTable,
            'released_rows_by_table' => $releasedRowsByTable,
            'dirty_pages' => array_map('intval', array_keys($dirtyPages)),
            'network_wal' => [
                'path' => $databasePath . '-wal',
                'frame_count' => count($walFrames),
                'bytes' => $walBytes,
                'frames' => $walFrames,
            ],
            'dependencies' => [
                'sqlite-tenant-json-wal-savepoint',
                'sqlite-json-import-wal-savepoint',
                'sqlite-savepoint-wal-rollback',
            ],
        ];
    }

    /**
     * @param array<string,mixed> $site
     */
    private static function tenantId(array $site): int
    {
        $tenantId = $site['tenant_id'] ?? null;
        if (!is_int($tenantId) && !(is_string($tenantId) && ctype_digit($tenantId))) {
            throw new \InvalidArgumentException('SQLite tenant JSON WAL tenant_id must be a positive integer');
        }
        $tenantId = (int) $tenantId;
        if ($tenantId <= 0) {
            throw new \InvalidArgumentException('SQLite tenant JSON WAL tenant_id must be positive');
        }
        if (!isset($site['current_rows']) || !is_array($site['current_rows'])) {
            throw new \InvalidArgumentException('SQLite tenant JSON WAL current rows must be a list');
        }

        return $tenantId;
    }

    /**
     * @param array<string,mixed> $site
     */
    private static function keyValueTableName(array $site, int $tenantId): string
    {
        return self::identifier((string) ($site['table_name'] ?? 'kv_tenant_' . $tenantId), 'tenant table name');
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
    private static function tenantWalFrame(int $tenantId, string $tableName, int $networkFrameIndex, array $frame): array
    {
        $pageNumber = (int) ($frame['page_number'] ?? 0);

        return $frame + [
            'network_frame_index' => $networkFrameIndex,
            'tenant_id' => $tenantId,
            'table' => $tableName,
            'network_page_number' => self::tenantPageNumber($tenantId, $pageNumber),
        ];
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
