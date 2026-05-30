<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteTenantSavepointWalPlan
{
    /**
     * @param list<array{blog_id:int,database_path:string,database_bytes:string,wal:SQLiteWal,wal_bytes:string,savepoints:SQLiteSavepointStack,savepoint:string,page_numbers:list<int>}> $sites
     * @return array{status:string,site_count:int,rolled_back_site_count:int,stable_site_count:int,total_restored_pages:int,total_discarded_wal_frames:int,sites:list<array<string,mixed>>,current_reader_matrix:array<int,list<string>>,next_reader_matrix:array<int,list<string>>,dependencies:list<string>}
     */
    public static function rollbackToAcrossSites(array $sites, int $pageSize): array
    {
        if ($pageSize < 1) {
            throw new \InvalidArgumentException('SQLite multisite savepoint WAL plan requires a positive page size');
        }
        if ($sites === []) {
            throw new \InvalidArgumentException('SQLite multisite savepoint WAL plan requires at least one site');
        }

        $summaries = [];
        $currentReaderMatrix = [];
        $nextReaderMatrix = [];
        $dependencies = ['sqlite-application-multisite-savepoint-wal-current-next'];
        $rolledBack = 0;
        $stable = 0;
        $totalRestoredPages = 0;
        $totalDiscardedWalFrames = 0;

        foreach ($sites as $site) {
            $summary = self::siteSummary($site, $pageSize);
            $summaries[] = $summary;
            $currentReaderMatrix[$summary['blog_id']] = $summary['current_reader_sources'];
            $nextReaderMatrix[$summary['blog_id']] = $summary['next_reader_sources'];
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
            'site_count' => count($summaries),
            'rolled_back_site_count' => $rolledBack,
            'stable_site_count' => $stable,
            'total_restored_pages' => $totalRestoredPages,
            'total_discarded_wal_frames' => $totalDiscardedWalFrames,
            'sites' => $summaries,
            'current_reader_matrix' => $currentReaderMatrix,
            'next_reader_matrix' => $nextReaderMatrix,
            'dependencies' => array_values(array_unique($dependencies)),
        ];
    }

    /**
     * @param array{blog_id:int,database_path:string,database_bytes:string,wal:SQLiteWal,wal_bytes:string,savepoints:SQLiteSavepointStack,savepoint:string,page_numbers:list<int>} $site
     * @return array<string,mixed>
     */
    private static function siteSummary(array $site, int $pageSize): array
    {
        foreach (['blog_id', 'database_path', 'database_bytes', 'wal', 'wal_bytes', 'savepoints', 'savepoint', 'page_numbers'] as $key) {
            if (!array_key_exists($key, $site)) {
                throw new \InvalidArgumentException("SQLite multisite savepoint WAL site is missing {$key}");
            }
        }
        if (!is_int($site['blog_id']) || $site['blog_id'] < 1) {
            throw new \InvalidArgumentException('SQLite multisite savepoint WAL blog ids must be positive integers');
        }
        if (!is_string($site['database_path']) || $site['database_path'] === '') {
            throw new \InvalidArgumentException('SQLite multisite savepoint WAL database path must not be empty');
        }
        if (!is_string($site['database_bytes']) || strlen($site['database_bytes']) % $pageSize !== 0) {
            throw new \InvalidArgumentException('SQLite multisite savepoint WAL database bytes must be page aligned');
        }
        if (!$site['wal'] instanceof SQLiteWal || !$site['savepoints'] instanceof SQLiteSavepointStack) {
            throw new \InvalidArgumentException('SQLite multisite savepoint WAL site requires WAL and savepoint objects');
        }
        if (!is_string($site['wal_bytes']) || $site['wal_bytes'] === '') {
            throw new \InvalidArgumentException('SQLite multisite savepoint WAL bytes must not be empty');
        }
        if (!is_string($site['savepoint']) || $site['savepoint'] === '') {
            throw new \InvalidArgumentException('SQLite multisite savepoint WAL savepoint name must not be empty');
        }
        if (!is_array($site['page_numbers']) || $site['page_numbers'] === []) {
            throw new \InvalidArgumentException('SQLite multisite savepoint WAL page list must not be empty');
        }

        $imagePlan = $site['savepoints']->rollbackToImagePlan($site['savepoint'], $pageSize);
        $rolledBackDatabaseBytes = $site['savepoints']->rollbackToDatabaseImage($site['savepoint'], $site['database_bytes'], $pageSize);
        $recovery = SQLiteWalSavepointRecoveryPlan::currentNextAfterRollbackTo(
            $site['savepoints'],
            $site['savepoint'],
            $site['wal'],
            $site['wal_bytes'],
            $rolledBackDatabaseBytes,
            $site['page_numbers']
        );

        $rolledBack = $imagePlan['restored_page_numbers'] !== [] || $recovery['discarded_frame_count'] > 0;

        return [
            'blog_id' => $site['blog_id'],
            'database_path' => $site['database_path'],
            'savepoint' => $site['savepoint'],
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
                ['sqlite-application-multisite-site-savepoint-wal'],
                $recovery['dependencies']
            ))),
        ];
    }
}
