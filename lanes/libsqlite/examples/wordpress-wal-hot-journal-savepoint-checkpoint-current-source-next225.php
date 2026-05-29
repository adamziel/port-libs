<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$digest = static fn (string $value): string => hash('sha256', $value);
$scopeDigest = $digest('wp next225 finalized savepoint scopes');
$publishPlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next219',
    'database_path' => '/srv/www/wp-content/database/wp-options.sqlite',
    'wal_path' => '/srv/www/wp-content/database/wp-options.sqlite-wal',
    'journal_path' => '/srv/www/wp-content/database/wp-options.sqlite-journal',
    'current_source_token' => ['id' => 'wp-next225-current-source', 'epoch' => 225],
    'checkpoint_frame' => 61,
    'checkpoint_cookie' => 22561,
    'schema_cookie' => 22517,
    'next_source_epoch' => 226,
    'savepoint_scope_digest' => $scopeDigest,
    'checkpoint_next_source_published' => true,
    'operation_names' => ['publish_checkpoint_next_source_after_savepoint_finalization_next219'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next219'],
];

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next225Plan(
    $publishPlan,
    [
        [
            'name' => 'wp-options-database-header',
            'header_region' => 'database-header',
            'source_id' => 'wp-next225-current-source',
            'source_epoch' => 225,
            'checkpoint_frame' => 61,
            'checkpoint_cookie' => 22561,
            'schema_cookie' => 22517,
            'next_source_epoch' => 226,
            'savepoint_scope_digest' => $scopeDigest,
            'header_digest' => $digest('database-header:wp-options'),
            'write_synced' => true,
        ],
        [
            'name' => 'wp-options-wal-index-header',
            'header_region' => 'wal-index-header',
            'source_id' => 'wp-next225-current-source',
            'source_epoch' => 225,
            'checkpoint_frame' => 61,
            'checkpoint_cookie' => 22561,
            'schema_cookie' => 22517,
            'next_source_epoch' => 226,
            'savepoint_scope_digest' => $scopeDigest,
            'header_digest' => $digest('wal-index-header:wp-options'),
            'write_synced' => true,
        ],
        [
            'name' => 'wp-options-change-counter',
            'header_region' => 'change-counter',
            'source_id' => 'wp-next225-current-source',
            'source_epoch' => 225,
            'checkpoint_frame' => 61,
            'checkpoint_cookie' => 22561,
            'schema_cookie' => 22517,
            'next_source_epoch' => 226,
            'savepoint_scope_digest' => $scopeDigest,
            'header_digest' => $digest('change-counter:wp-options'),
            'write_synced' => true,
        ],
    ]
);

$summary = [
    'scenario' => 'wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next225',
    'wordpressUse' => 'A copied WordPress wp_options import recovers a hot journal, finalizes plugin savepoints, checkpoints WAL frames, and admits the database header as the current source only after the database header, WAL-index header, and change-counter receipts match the checkpoint cookies.',
    'status' => $plan['status'],
    'headerPublished' => $plan['checkpoint_current_source_header_published'],
    'publishedRegions' => $plan['published_header_regions'],
    'receiptNames' => $plan['published_receipt_names'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    if (
        $summary['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-next225'
        || $summary['headerPublished'] !== true
        || $summary['publishedRegions'] !== ['change-counter', 'database-header', 'wal-index-header']
    ) {
        fwrite(STDERR, "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next225 self-test failed\n");
        exit(1);
    }

    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

return $summary;
