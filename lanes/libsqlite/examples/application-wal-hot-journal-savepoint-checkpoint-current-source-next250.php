<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$hash = static fn (string $value): string => hash('sha256', $value);
$databaseDigest = $hash('application next250 copied option checkpoint database');
$pageCacheDigest = $hash('application next250 clean copied option page cache');

$cleanupPlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next247',
    'cleanup_admitted' => true,
    'database_path' => '/srv/www/wp-content/database/application.sqlite',
    'journal_path' => '/srv/www/wp-content/database/application.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/application.sqlite-wal',
    'source_token' => 'application-import-next250',
    'commit_generation' => 250,
    'schema_cookie' => 25077,
    'database_digest' => $databaseDigest,
    'page_cache_digest' => $pageCacheDigest,
    'wal_index_salt' => ['wp-next250-salt-a', 'wp-next250-salt-b'],
    'wal_index_mx_frame' => 31,
    'checkpoint_frame' => 29,
    'dirty_pages' => [1, 2, 4, 7],
    'commit_frames' => [27, 30, 31],
    'reader_names' => ['wp-schema-reader', 'wp-options-reader', 'wp-autoload-reader'],
    'operation_names' => ['seal_post_checkpoint_cleanup_current_source_next247'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next247'],
];

$receipt = static function (string $name, string $kind, array $pages, array $frames, array $readers) use ($cleanupPlan, $databaseDigest, $pageCacheDigest): array {
    return [
        'name' => $name,
        'kind' => $kind,
        'database_path' => $cleanupPlan['database_path'],
        'journal_path' => $cleanupPlan['journal_path'],
        'wal_path' => $cleanupPlan['wal_path'],
        'source_token' => $cleanupPlan['source_token'],
        'commit_generation' => $cleanupPlan['commit_generation'],
        'schema_cookie' => $cleanupPlan['schema_cookie'],
        'database_digest' => $databaseDigest,
        'page_cache_digest' => $pageCacheDigest,
        'wal_index_salt' => $cleanupPlan['wal_index_salt'],
        'wal_index_mx_frame' => $cleanupPlan['wal_index_mx_frame'],
        'checkpoint_frame' => $cleanupPlan['checkpoint_frame'],
        'page_numbers' => $pages,
        'commit_frames' => $frames,
        'reader_names' => $readers,
        'page_cache_invalidated' => true,
        'readmark_cleared' => true,
        'schema_cookie_refreshed' => true,
        'wal_index_refreshed' => true,
        'reader_reopened' => true,
        'hot_journal_visible' => false,
        'stale_wal_visible' => false,
        'savepoint_depth' => 0,
        'shared_lock_held' => true,
    ];
};

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next250AdmitCacheInvalidation($cleanupPlan, [
    $receipt('invalidate-schema-cache', 'cache-invalidate', [1], [27], ['wp-schema-reader']),
    $receipt('clear-options-readmark', 'readmark-clear', [2], [30], ['wp-options-reader']),
    $receipt('refresh-schema-cookie', 'schema-cookie-refresh', [4], [31], ['wp-autoload-reader']),
    $receipt('refresh-wal-index', 'wal-index-refresh', [1, 2, 4, 7], [27, 30, 31], ['wp-schema-reader', 'wp-options-reader', 'wp-autoload-reader']),
]);

$summary = [
    'scenario' => 'application-wal-hot-journal-savepoint-checkpoint-current-source-next250',
    'applicationUse' => 'A copied Application import clears stale pager cache, readmarks, schema-cookie, and WAL-index state after hot-journal checkpoint cleanup before readers use the new current source.',
    'status' => $plan['status'],
    'cacheInvalidationAdmitted' => $plan['cache_invalidation_admitted'],
    'cacheAction' => $plan['cache_action'],
    'readerAction' => $plan['reader_action'],
    'coveredReaders' => $plan['covered_reader_names'],
    'coveredPages' => $plan['covered_dirty_pages'],
    'coveredFrames' => $plan['covered_commit_frames'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if ($summary['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-next250'
    || $summary['cacheInvalidationAdmitted'] !== true
    || $summary['coveredReaders'] !== ['wp-autoload-reader', 'wp-options-reader', 'wp-schema-reader']
) {
    fwrite(STDERR, "application-wal-hot-journal-savepoint-checkpoint-current-source-next250 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
