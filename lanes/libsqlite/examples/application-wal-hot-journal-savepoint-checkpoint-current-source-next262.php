<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$digest = static fn (string $value): string => hash('sha256', $value);
$databaseDigest = $digest('application next262 checkpoint database image');
$pageCacheDigest = $digest('application next262 clean page cache');
$sourceToken = 'wp-next262-current-source';
$common = [
    'source_token' => $sourceToken,
    'database_digest' => $databaseDigest,
    'page_cache_digest' => $pageCacheDigest,
    'commit_generation' => 262,
    'schema_cookie' => 1262,
    'checkpoint_frame' => 52,
];

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next262FenceReaderCache([
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next260',
    'current_source_admitted' => true,
    'database_path' => '/srv/www/wp-content/database/wp-next262.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next262.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next262.sqlite-wal',
    'dirty_pages' => [1, 4],
    'accepted_reader_names' => ['wp-options-reader', 'autoload-index-reader'],
    'operation_names' => ['admit_hot_journal_savepoint_checkpoint_current_source_next260'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next260'],
] + $common, [
    [
        'name' => 'options-page-cache',
        'reader_name' => 'wp-options-reader',
        'page_number' => 1,
        'hot_journal_generation_seen' => null,
        'stale_wal_frame_seen' => null,
        'evicted' => false,
    ] + $common,
    [
        'name' => 'autoload-page-cache',
        'reader_name' => 'autoload-index-reader',
        'page_number' => 4,
        'hot_journal_generation_seen' => null,
        'stale_wal_frame_seen' => null,
        'evicted' => false,
    ] + $common,
], [
    [
        'name' => 'retry-options-page',
        'reader_name' => 'wp-options-reader',
        'page_number' => 1,
        'snapshot_reopened' => true,
        'hot_journal_visible' => false,
        'stale_wal_visible' => false,
    ] + $common,
    [
        'name' => 'retry-autoload-page',
        'reader_name' => 'autoload-index-reader',
        'page_number' => 4,
        'snapshot_reopened' => true,
        'hot_journal_visible' => false,
        'stale_wal_visible' => false,
    ] + $common,
]);

echo json_encode([
    'scenario' => 'application-wal-hot-journal-savepoint-checkpoint-current-source-next262',
    'applicationUse' => 'A copied Application import retries option/autoload reads after hot-journal recovery and checkpoint publication without reusing stale reader page-cache entries from the old WAL generation.',
    'status' => $plan['status'],
    'readerCacheAdmitted' => $plan['reader_cache_admitted'],
    'cacheAction' => $plan['cache_action'],
    'retryAction' => $plan['retry_action'],
    'usableCachePages' => $plan['usable_cache_pages'],
    'blockedGuards' => $plan['blocked_guard_names'],
    'dependencyClosure' => $plan['dependency_closure'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
