<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$digest = static fn (string $value): string => hash('sha256', $value);
$sourceToken = 'wordpress-import-current-source-next260';
$generation = 260;
$schemaCookie = 4260;
$checkpointFrame = 72;
$commitFrames = [68, 69, 72];
$databaseDigest = $digest('wordpress next260 checkpoint database');
$pageCacheDigest = $digest('wordpress next260 checkpoint cache');
$prefixDigest = hash('sha256', json_encode([$sourceToken, $generation, $commitFrames], JSON_THROW_ON_ERROR));
$common = [
    'source_token' => $sourceToken,
    'database_digest' => $databaseDigest,
    'page_cache_digest' => $pageCacheDigest,
    'commit_generation' => $generation,
    'schema_cookie' => $schemaCookie,
];
$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next260AdmitCurrentSource([
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next246',
    'durable_handoff_admitted' => true,
    'database_path' => '/srv/www/wp-content/database/wordpress.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wordpress.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wordpress.sqlite-wal',
    'source_token' => $sourceToken,
    'database_digest' => $databaseDigest,
    'page_cache_digest' => $pageCacheDigest,
    'commit_generation' => $generation,
    'schema_cookie' => $schemaCookie,
    'checkpoint_frame' => $checkpointFrame,
    'dirty_pages' => [1, 2, 5, 9],
    'commit_frames' => $commitFrames,
    'accepted_reader_names' => ['wp-options-import', 'plugin-cache-reader'],
], [[
    'name' => 'delete-hot-journal-after-plugin-recovery',
] + $common + [
    'recovered_pages' => [1, 2],
    'journal_checksum_valid' => true,
    'hot_journal_deleted' => true,
    'directory_sync_done' => true,
]], [[
    'name' => 'plugin-batch-savepoint-prefix',
    'source_token' => $sourceToken,
    'commit_generation' => $generation,
    'checkpoint_frame' => $checkpointFrame,
    'retained_wal_frames' => $commitFrames,
    'prefix_digest' => $prefixDigest,
    'savepoint_scope_closed' => true,
]], [[
    'name' => 'checkpoint-import-database-image',
] + $common + [
    'checkpoint_frame' => $checkpointFrame,
    'database_pages' => [1, 2, 5, 9],
    'checkpointed_wal_frames' => $commitFrames,
    'database_sync_done' => true,
    'wal_index_sync_done' => true,
    'exclusive_lock_held' => true,
]], [
    [
        'name' => 'wp-options-import',
    ] + $common + [
        'checkpoint_frame' => $checkpointFrame,
        'snapshot_reopened' => true,
        'hot_journal_seen' => false,
    ],
    [
        'name' => 'plugin-cache-reader',
    ] + $common + [
        'checkpoint_frame' => $checkpointFrame,
        'snapshot_reopened' => true,
        'hot_journal_seen' => false,
    ],
]);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'wal-hot-journal-savepoint-checkpoint-current-source-next260');
    assert($plan['current_source_admitted'] === true);
    assert($plan['covered_database_pages'] === [1, 2, 5, 9]);
    assert($plan['retained_wal_frames'] === $commitFrames);
    echo "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next260 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next260',
    'status' => $plan['status'],
    'currentSourceAdmitted' => $plan['current_source_admitted'],
    'coveredDatabasePages' => $plan['covered_database_pages'],
    'retainedWalFrames' => $plan['retained_wal_frames'],
    'readerAction' => $plan['reader_action'],
    'wordpressUse' => 'A copied wp_options plugin import only publishes the post-hot-journal checkpoint as the current source after savepoint WAL prefix, checkpoint sync, and reopened reader tokens all match.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
