<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext201Plan.php';

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext201Plan;

$admission = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next200',
    'database_path' => '/srv/www/wp-content/database/wp-options-next201.sqlite',
    'wal_path' => '/srv/www/wp-content/database/wp-options-next201.sqlite-wal',
    'journal_path' => '/srv/www/wp-content/database/wp-options-next201.sqlite-journal',
    'publication_token' => 'wp-options-next201-retry-publication',
    'previous_reader_epoch' => 200,
    'sealed_reader_epoch' => 203,
    'receipt_ticket_ids' => ['wp-options-reader-a', 'wp-options-reader-b', 'wp-options-reader-c'],
    'receipt_pages' => [1, 2, 4],
    'expected_savepoint_generation' => 12,
    'can_admit_durable_readers' => true,
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next200'],
];
$checkpointDigest = hash('sha256', 'wp_options checkpoint database page images after hot journal recovery');
$walDigest = hash('sha256', 'wp_options retry WAL page images after savepoint release');

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNext201Plan::publishCurrentSources(
    $admission,
    [
        [
            'ticket_id' => 'wp-options-reader-a',
            'page_number' => 1,
            'reader_epoch' => 201,
            'publication_token' => $admission['publication_token'],
            'source' => 'checkpoint-database',
            'source_digest' => $checkpointDigest,
            'cache_epoch' => 201,
            'savepoint_generation' => 12,
            'checkpoint_visible' => true,
            'reader_cache_rebased' => true,
        ],
        [
            'ticket_id' => 'wp-options-reader-b',
            'page_number' => 2,
            'reader_epoch' => 202,
            'publication_token' => $admission['publication_token'],
            'source' => 'next-wal',
            'source_digest' => $walDigest,
            'cache_epoch' => 202,
            'savepoint_generation' => 12,
            'checkpoint_visible' => true,
            'reader_cache_rebased' => true,
        ],
        [
            'ticket_id' => 'wp-options-reader-c',
            'page_number' => 4,
            'reader_epoch' => 203,
            'publication_token' => $admission['publication_token'],
            'source' => 'next-wal',
            'source_digest' => $walDigest,
            'cache_epoch' => 203,
            'savepoint_generation' => 12,
            'checkpoint_visible' => true,
            'reader_cache_rebased' => true,
        ],
    ],
    $checkpointDigest,
    $walDigest,
    null
);

$summary = [
    'scenario' => 'wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next201',
    'wordpressUse' => 'A WordPress options import retry only exposes reader cache rows after hot-journal recovery, savepoint release, checkpoint visibility, and WAL source digests all agree.',
    'status' => $plan['status'],
    'canPublishCurrentSources' => $plan['can_publish_current_sources'],
    'sourceKinds' => $plan['source_kinds'],
    'checkpointSourceCount' => $plan['checkpoint_source_count'],
    'walSourceCount' => $plan['wal_source_count'],
    'hotJournalAbsent' => $plan['hot_journal_absent'],
    'blockedReasons' => $plan['blocked_reasons'],
    'publicationDigest' => $plan['publication_digest'],
];

if (PHP_SAPI === 'cli' && in_array('--self-test', $argv, true)) {
    if ($summary['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-next201'
        || $summary['canPublishCurrentSources'] !== true
        || $summary['sourceKinds'] !== ['checkpoint-database', 'next-wal']
        || $summary['blockedReasons'] !== []
    ) {
        fwrite(STDERR, "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next201 self-test failed\n");
        exit(1);
    }

    echo "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next201 self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
