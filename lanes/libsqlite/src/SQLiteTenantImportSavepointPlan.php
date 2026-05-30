<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteTenantImportSavepointPlan
{
    /**
     * @param list<array{blog_id:int,current_rows:list<array<string,mixed>>,batches:list<array{name?:string,rows:list<array<string,mixed>>,on_conflict?:string,release?:bool>>}> $sites
     * @param array{database_path?:string,page_size?:int,journal_mode?:string,sync_mode?:string,replace_conflicts?:bool,continue_on_site_error?:bool,global_batches?:list<array{name?:string,rows:list<array<string,mixed>>,on_conflict?:string,release?:bool>>} $options
     * @return array<string,mixed>
     */
    public static function plan(array $sites, array $options = []): array
    {
        if ($sites === []) {
            throw new \InvalidArgumentException('SQLite Application multisite import savepoint plan requires at least one site');
        }

        $databasePath = (string) ($options['database_path'] ?? '/tmp/wp-multisite-import.sqlite');
        $pageSize = (int) ($options['page_size'] ?? 4096);
        $journalMode = strtolower((string) ($options['journal_mode'] ?? 'delete'));
        $syncMode = strtolower((string) ($options['sync_mode'] ?? 'full'));
        $replaceConflicts = (bool) ($options['replace_conflicts'] ?? false);
        $continueOnSiteError = (bool) ($options['continue_on_site_error'] ?? true);
        $globalBatches = $options['global_batches'] ?? [];
        if (!is_array($globalBatches)) {
            throw new \InvalidArgumentException('SQLite Application multisite global batches must be a list');
        }

        $sitePlans = [];
        $tableNames = [];
        $releasedSites = [];
        $rolledBackSites = [];
        $dirtyPages = [];
        $finalRowsByTable = [];
        $releasedRowsByTable = [];

        foreach (array_values($sites) as $siteIndex => $site) {
            $blogId = self::tenantId($site);
            if (isset($sitePlans[$blogId])) {
                throw new \InvalidArgumentException("Duplicate Application multisite blog_id {$blogId}");
            }

            $tableName = self::optionsTableName($blogId);
            $tableNames[] = $tableName;
            $batches = $site['batches'] ?? null;
            if (!is_array($batches)) {
                throw new \InvalidArgumentException('SQLite Application multisite site batches must be a list');
            }

            $prefixedBatches = [];
            foreach (array_values($batches) as $batchIndex => $batch) {
                $prefixedBatches[] = self::prefixedBatch($batch, "blog{$blogId}", $batchIndex);
            }

            try {
                $sitePlan = SQLiteBulkImportSavepointPlan::plan($site['current_rows'], $prefixedBatches, [
                    'database_path' => $databasePath,
                    'page_size' => $pageSize,
                    'journal_mode' => $journalMode,
                    'sync_mode' => $syncMode,
                    'replace_conflicts' => $replaceConflicts,
                ]);
                $status = $sitePlan['rolled_back_batches'] === [] ? 'released' : 'partial';
                if ($status === 'released') {
                    $releasedSites[] = $blogId;
                } else {
                    $rolledBackSites[] = $blogId;
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
                        static fn (array $batch, int $batchIndex): string => (string) ($batch['name'] ?? "blog{$blogId}_wp_bulk_" . ($batchIndex + 1)),
                        $prefixedBatches,
                        array_keys($prefixedBatches)
                    ),
                    'batches' => [],
                    'final_rows' => $site['current_rows'],
                    'released_rows' => $site['current_rows'],
                    'final_option_names' => array_column($site['current_rows'], 'option_name'),
                    'released_option_names' => array_column($site['current_rows'], 'option_name'),
                    'dirty_pages' => [],
                    'journal_bytes' => 0,
                    'error' => $exception->getMessage(),
                ];
                $rolledBackSites[] = $blogId;
                $status = 'rolled_back';
            }

            foreach (($sitePlan['dirty_pages'] ?? []) as $pageNumber) {
                $dirtyPages[self::tenantPageNumber($blogId, (int) $pageNumber)] = true;
            }

            $finalRowsByTable[$tableName] = $sitePlan['final_rows'];
            $releasedRowsByTable[$tableName] = $sitePlan['released_rows'];
            $sitePlans[$blogId] = [
                'blog_id' => $blogId,
                'table' => $tableName,
                'status' => $status,
                'savepoint_prefix' => "blog{$blogId}",
                'plan' => $sitePlan,
            ];
        }

        $globalPlan = null;
        if ($globalBatches !== []) {
            $prefixedGlobal = [];
            foreach (array_values($globalBatches) as $batchIndex => $batch) {
                $prefixedGlobal[] = self::prefixedBatch($batch, 'network', $batchIndex);
            }

            $globalPlan = SQLiteBulkImportSavepointPlan::plan([], $prefixedGlobal, [
                'database_path' => $databasePath,
                'page_size' => $pageSize,
                'journal_mode' => $journalMode,
                'sync_mode' => $syncMode,
                'replace_conflicts' => $replaceConflicts,
            ]);
            foreach ($globalPlan['dirty_pages'] as $pageNumber) {
                $dirtyPages[self::tenantPageNumber(0, (int) $pageNumber)] = true;
            }
            $finalRowsByTable['wp_sitemeta'] = $globalPlan['final_rows'];
            $releasedRowsByTable['wp_sitemeta'] = $globalPlan['released_rows'];
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
            'site_count' => count($sitePlans),
            'table_names' => $tableNames,
            'released_sites' => $releasedSites,
            'rolled_back_sites' => $rolledBackSites,
            'sites' => array_values($sitePlans),
            'global_plan' => $globalPlan,
            'final_rows_by_table' => $finalRowsByTable,
            'released_rows_by_table' => $releasedRowsByTable,
            'dirty_pages' => array_map('intval', array_keys($dirtyPages)),
            'journal_bytes' => $dirtyPages === [] ? 0 : 28 + (count($dirtyPages) * ($pageSize + 8)),
            'dependencies' => [
                'sqlite-application-multisite-import-savepoint-current',
                'sqlite-application-bulk-import-savepoint-current',
                'sqlite-savepoint-current-rollback',
            ],
        ];
    }

    /**
     * @param array<string,mixed> $site
     */
    private static function tenantId(array $site): int
    {
        $blogId = $site['blog_id'] ?? null;
        if (!is_int($blogId) && !(is_string($blogId) && ctype_digit($blogId))) {
            throw new \InvalidArgumentException('SQLite Application multisite blog_id must be a positive integer');
        }
        $blogId = (int) $blogId;
        if ($blogId <= 0) {
            throw new \InvalidArgumentException('SQLite Application multisite blog_id must be positive');
        }
        if (!isset($site['current_rows']) || !is_array($site['current_rows'])) {
            throw new \InvalidArgumentException('SQLite Application multisite current rows must be a list');
        }

        return $blogId;
    }

    private static function optionsTableName(int $blogId): string
    {
        return $blogId === 1 ? 'wp_options' : 'wp_' . $blogId . '_options';
    }

    /**
     * @param array<string,mixed> $batch
     * @return array<string,mixed>
     */
    private static function prefixedBatch(array $batch, string $prefix, int $index): array
    {
        $copy = $batch;
        $name = (string) ($copy['name'] ?? 'wp_bulk_' . ($index + 1));
        $copy['name'] = $prefix . '_' . $name;

        return $copy;
    }

    private static function tenantPageNumber(int $blogId, int $pageNumber): int
    {
        return ($blogId * 100000) + $pageNumber;
    }
}
