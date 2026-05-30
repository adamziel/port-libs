<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteTenantImportSavepointPlan
{
    /**
     * @param list<array{tenant_id:int,current_rows:list<array<string,mixed>>,batches:list<array{name?:string,rows:list<array<string,mixed>>,on_conflict?:string,release?:bool>>}> $sites
     * @param array{database_path?:string,page_size?:int,journal_mode?:string,sync_mode?:string,replace_conflicts?:bool,continue_on_site_error?:bool,global_batches?:list<array{name?:string,rows:list<array<string,mixed>>,on_conflict?:string,release?:bool>>} $options
     * @return array<string,mixed>
     */
    public static function plan(array $sites, array $options = []): array
    {
        if ($sites === []) {
            throw new \InvalidArgumentException('SQLite application tenant import savepoint plan requires at least one tenant');
        }

        $databasePath = self::databasePath((string) ($options['database_path'] ?? '/tmp/app-tenant-import.sqlite'));
        $pageSize = (int) ($options['page_size'] ?? 4096);
        if ($pageSize < 512 || $pageSize > 65536 || ($pageSize & ($pageSize - 1)) !== 0) {
            throw new \InvalidArgumentException('SQLite application tenant import page size must be a power of two between 512 and 65536');
        }
        $journalMode = strtolower((string) ($options['journal_mode'] ?? 'delete'));
        if (!in_array($journalMode, ['delete', 'truncate', 'persist', 'memory', 'off'], true)) {
            throw new \InvalidArgumentException('SQLite application tenant import journal mode is unsupported');
        }
        $syncMode = strtolower((string) ($options['sync_mode'] ?? 'full'));
        if (!in_array($syncMode, ['off', 'normal', 'full'], true)) {
            throw new \InvalidArgumentException('SQLite application tenant import sync mode is unsupported');
        }
        $replaceConflicts = (bool) ($options['replace_conflicts'] ?? false);
        $continueOnSiteError = (bool) ($options['continue_on_site_error'] ?? true);
        $globalBatches = $options['global_batches'] ?? [];
        if (!is_array($globalBatches)) {
            throw new \InvalidArgumentException('SQLite application tenant global batches must be a list');
        }

        $sitePlans = [];
        $tableNames = [];
        $releasedTenants = [];
        $rolledBackTenants = [];
        $dirtyPages = [];
        $finalRowsByTable = [];
        $releasedRowsByTable = [];

        foreach (array_values($sites) as $siteIndex => $site) {
            $tenantId = self::tenantId($site);
            if (isset($sitePlans[$tenantId])) {
                throw new \InvalidArgumentException("Duplicate application tenant_id {$tenantId}");
            }

            $tableName = self::keyValueTableName($tenantId);
            $tableNames[] = $tableName;
            $batches = $site['batches'] ?? null;
            if (!is_array($batches)) {
                throw new \InvalidArgumentException('SQLite application tenant batches must be a list');
            }

            $prefixedBatches = [];
            foreach (array_values($batches) as $batchIndex => $batch) {
                $prefixedBatches[] = self::prefixedBatch($batch, "tenant{$tenantId}", $batchIndex);
            }

            try {
                $sitePlan = self::keyValueSavepointPlan($site['current_rows'], $prefixedBatches, $replaceConflicts, $pageSize);
                $status = $sitePlan['rolled_back_batches'] === [] ? 'released' : 'partial';
                if ($status === 'released') {
                    $releasedTenants[] = $tenantId;
                } else {
                    $rolledBackTenants[] = $tenantId;
                }
            } catch (\Throwable $exception) {
                if (!$continueOnSiteError) {
                    throw $exception;
                }

                $sitePlan = [
                    'status' => 'rolled_back',
                    'batch_count' => count($prefixedBatches),
                    'released_batches' => [],
                    'rolled_back_batches' => array_map(
                        static fn (array $batch, int $batchIndex): string => (string) ($batch['name'] ?? "tenant{$tenantId}_kv_bulk_" . ($batchIndex + 1)),
                        $prefixedBatches,
                        array_keys($prefixedBatches)
                    ),
                    'batches' => [],
                    'final_rows' => $site['current_rows'],
                    'released_rows' => $site['current_rows'],
                    'final_key_names' => array_column($site['current_rows'], 'key_name'),
                    'released_key_names' => array_column($site['current_rows'], 'key_name'),
                    'dirty_pages' => [],
                    'journal_bytes' => 0,
                    'error' => $exception->getMessage(),
                ];
                $rolledBackTenants[] = $tenantId;
                $status = 'rolled_back';
            }

            foreach (($sitePlan['dirty_pages'] ?? []) as $pageNumber) {
                $dirtyPages[self::tenantPageNumber($tenantId, (int) $pageNumber)] = true;
            }

            $finalRowsByTable[$tableName] = $sitePlan['final_rows'];
            $releasedRowsByTable[$tableName] = $sitePlan['released_rows'];
            $sitePlans[$tenantId] = [
                'tenant_id' => $tenantId,
                'table' => $tableName,
                'status' => $status,
                'savepoint_prefix' => "tenant{$tenantId}",
                'plan' => $sitePlan,
            ];
        }

        $globalPlan = null;
        if ($globalBatches !== []) {
            $prefixedGlobal = [];
            foreach (array_values($globalBatches) as $batchIndex => $batch) {
                $prefixedGlobal[] = self::prefixedBatch($batch, 'global', $batchIndex);
            }

            $globalPlan = self::keyValueSavepointPlan([], $prefixedGlobal, $replaceConflicts, $pageSize);
            foreach ($globalPlan['dirty_pages'] as $pageNumber) {
                $dirtyPages[self::tenantPageNumber(0, (int) $pageNumber)] = true;
            }
            $finalRowsByTable['app_tenant_settings'] = $globalPlan['final_rows'];
            $releasedRowsByTable['app_tenant_settings'] = $globalPlan['released_rows'];
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
            'table_names' => $tableNames,
            'released_tenants' => $releasedTenants,
            'rolled_back_tenants' => $rolledBackTenants,
            'tenants' => array_values($sitePlans),
            'global_plan' => $globalPlan,
            'final_rows_by_table' => $finalRowsByTable,
            'released_rows_by_table' => $releasedRowsByTable,
            'dirty_pages' => array_map('intval', array_keys($dirtyPages)),
            'journal_bytes' => $dirtyPages === [] ? 0 : 28 + (count($dirtyPages) * ($pageSize + 8)),
            'dependencies' => [
                'sqlite-application-tenant-import-savepoint-current',
                'sqlite-application-keyvalue-bulk-import-savepoint-current',
                'sqlite-savepoint-current-rollback',
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
            throw new \InvalidArgumentException('SQLite application tenant_id must be a positive integer');
        }
        $tenantId = (int) $tenantId;
        if ($tenantId <= 0) {
            throw new \InvalidArgumentException('SQLite application tenant_id must be positive');
        }
        if (!isset($site['current_rows']) || !is_array($site['current_rows'])) {
            throw new \InvalidArgumentException('SQLite application tenant current rows must be a list');
        }

        return $tenantId;
    }

    private static function keyValueTableName(int $tenantId): string
    {
        return $tenantId === 1 ? 'app_settings' : 'app_tenant_' . $tenantId . '_settings';
    }

    /**
     * @param array<string,mixed> $batch
     * @return array<string,mixed>
     */
    private static function prefixedBatch(array $batch, string $prefix, int $index): array
    {
        $copy = $batch;
        $name = (string) ($copy['name'] ?? 'kv_bulk_' . ($index + 1));
        $copy['name'] = $prefix . '_' . $name;

        return $copy;
    }

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $batches
     * @return array<string,mixed>
     */
    private static function keyValueSavepointPlan(array $currentRows, array $batches, bool $replaceConflicts, int $pageSize): array
    {
        if ($batches === []) {
            throw new \InvalidArgumentException('SQLite application key-value import savepoint plan requires at least one batch');
        }

        $visibleRows = self::rowsById($currentRows);
        $releasedRows = $visibleRows;
        $batchPlans = [];
        $releasedNames = [];
        $rolledBackNames = [];
        $dirtyPages = [];

        foreach (array_values($batches) as $batchIndex => $batch) {
            $name = self::batchName($batch, $batchIndex);
            $onConflict = strtolower((string) ($batch['on_conflict'] ?? 'rollback'));
            if (!in_array($onConflict, ['rollback', 'abort'], true)) {
                throw new \InvalidArgumentException('SQLite application key-value import conflict action must be rollback or abort');
            }

            $beforeRows = $visibleRows;
            $beforeNames = array_column(array_values($beforeRows), 'key_name');
            $updated = 0;
            $inserted = 0;
            $deleted = 0;
            $error = null;

            try {
                foreach ($batch['rows'] as $row) {
                    $row = self::keyValueRow($row, self::nextSettingId($visibleRows));
                    $existingId = self::settingIdForKey($visibleRows, (string) $row['key_name']);
                    if ($existingId !== null && $existingId !== $row['setting_id']) {
                        if (!$replaceConflicts) {
                            throw new \LogicException('unique key_name conflict');
                        }
                        unset($visibleRows[$existingId]);
                        $deleted++;
                    }

                    $isUpdate = isset($visibleRows[$row['setting_id']]);
                    $visibleRows[$row['setting_id']] = $row;
                    $dirtyPages[(int) $row['setting_id']] = true;
                    $isUpdate ? $updated++ : $inserted++;
                }
            } catch (\LogicException $exception) {
                $error = $exception->getMessage();
                if ($onConflict === 'abort') {
                    throw $exception;
                }
            }

            if ($error !== null) {
                $visibleRows = $beforeRows;
                $rolledBackNames[] = $name;
                $batchPlans[] = [
                    'name' => $name,
                    'status' => 'rolled_back',
                    'error' => $error,
                    'before_key_names' => $beforeNames,
                    'after_key_names' => array_column(array_values($visibleRows), 'key_name'),
                    'updated' => 0,
                    'inserted' => 0,
                    'deleted' => 0,
                    'dirty_pages' => [],
                    'rollback_page_numbers' => [],
                    'retained_depth' => 1,
                    'released' => false,
                ];
                continue;
            }

            $shouldRelease = (bool) ($batch['release'] ?? true);
            if ($shouldRelease) {
                $releasedRows = $visibleRows;
                $releasedNames[] = $name;
            }

            $batchPlans[] = [
                'name' => $name,
                'status' => $shouldRelease ? 'released' : 'open',
                'error' => null,
                'before_key_names' => $beforeNames,
                'after_key_names' => array_column(array_values($visibleRows), 'key_name'),
                'updated' => $updated,
                'inserted' => $inserted,
                'deleted' => $deleted,
                'dirty_pages' => array_map('intval', array_keys($dirtyPages)),
                'rollback_page_numbers' => [],
                'retained_depth' => null,
                'released' => $shouldRelease,
            ];
        }

        ksort($dirtyPages);

        return [
            'status' => 'planned',
            'batch_count' => count($batchPlans),
            'released_batches' => $releasedNames,
            'rolled_back_batches' => $rolledBackNames,
            'batches' => $batchPlans,
            'current_rows' => array_values($currentRows),
            'final_rows' => array_values($visibleRows),
            'released_rows' => array_values($releasedRows),
            'final_key_names' => array_column(array_values($visibleRows), 'key_name'),
            'released_key_names' => array_column(array_values($releasedRows), 'key_name'),
            'dirty_pages' => array_map('intval', array_keys($dirtyPages)),
            'journal_bytes' => $dirtyPages === [] ? 0 : 28 + (count($dirtyPages) * ($pageSize + 8)),
        ];
    }

    /**
     * @param array<string,mixed> $batch
     */
    private static function batchName(array $batch, int $index): string
    {
        $name = (string) ($batch['name'] ?? 'kv_bulk_' . ($index + 1));
        if ($name === '' || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
            throw new \InvalidArgumentException('SQLite application key-value import savepoint names must be SQL identifiers');
        }

        if (!isset($batch['rows']) || !is_array($batch['rows'])) {
            throw new \InvalidArgumentException('SQLite application key-value import batch rows must be a list');
        }

        return $name;
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    private static function rowsById(array $rows): array
    {
        $byId = [];
        foreach ($rows as $row) {
            $row = self::keyValueRow($row);
            $byId[$row['setting_id']] = $row;
        }
        ksort($byId);

        return $byId;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private static function keyValueRow(array $row, ?int $fallbackId = null): array
    {
        $id = $row['setting_id'] ?? $fallbackId;
        if (!is_int($id)) {
            throw new \InvalidArgumentException('SQLite application key-value import rows require integer setting_id values');
        }
        if (!array_key_exists('key_name', $row) || !is_string($row['key_name']) || $row['key_name'] === '') {
            throw new \InvalidArgumentException('SQLite application key-value import rows require non-empty key_name values');
        }
        $row['setting_id'] = $id;

        return $row;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     */
    private static function nextSettingId(array $rows): int
    {
        return $rows === [] ? 1 : max(array_keys($rows)) + 1;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     */
    private static function settingIdForKey(array $rows, string $keyName): ?int
    {
        foreach ($rows as $id => $row) {
            if (($row['key_name'] ?? null) === $keyName) {
                return $id;
            }
        }

        return null;
    }

    private static function databasePath(string $path): string
    {
        if ($path === '' || $path[0] !== '/') {
            throw new \InvalidArgumentException('SQLite application tenant import database path must be absolute');
        }

        return $path;
    }

    private static function tenantPageNumber(int $tenantId, int $pageNumber): int
    {
        return ($tenantId * 100000) + $pageNumber;
    }
}
