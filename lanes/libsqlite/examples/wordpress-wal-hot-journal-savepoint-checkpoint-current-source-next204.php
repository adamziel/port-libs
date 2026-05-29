<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$digest = static fn (string $bytes): string => hash('sha256', $bytes);
$databaseDigest = $digest('wp next204 checkpointed database image');
$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next204Plan(
    [
        'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next203',
        'database_path' => '/srv/www/wp-content/database/wp-next204.sqlite',
        'journal_path' => '/srv/www/wp-content/database/wp-next204.sqlite-journal',
        'wal_path' => '/srv/www/wp-content/database/wp-next204.sqlite-wal',
        'checkpoint_generation' => 204,
        'schema_cookie' => 9182,
        'checkpointed_page_count' => 7,
        'checkpointed_database_digest' => $databaseDigest,
        'operation_names' => ['verify_checkpoint_page_cache_leases_current_source_next203'],
        'dependencies' => [
            'sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next203',
            'sqlite-checkpoint-page-cache-lease-fence',
        ],
    ],
    [
        [
            'name' => 'wp-options-current-generation-ticket',
            'observed_checkpoint_generation' => 204,
            'observed_schema_cookie' => 9182,
            'observed_page_count' => 7,
            'observed_database_digest' => $databaseDigest,
            'reader_epoch' => 204,
        ],
        [
            'name' => 'wp-options-stale-generation-ticket',
            'observed_checkpoint_generation' => 203,
            'observed_schema_cookie' => 9182,
            'observed_page_count' => 7,
            'observed_database_digest' => $databaseDigest,
            'reader_epoch' => 203,
        ],
    ]
);

$summary = [
    'scenario' => 'wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next204',
    'wordpressUse' => 'After a copied WordPress import recovers a hot journal and checkpoints WAL frames, retained wp_options page-cache leases must also carry the current checkpoint generation, schema cookie, page count, and database digest before reuse.',
    'status' => $plan['status'],
    'admittedLeases' => $plan['admitted_lease_names'],
    'reopenLeases' => $plan['reopen_lease_names'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if ($summary['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-next204'
    || $summary['admittedLeases'] !== ['wp-options-current-generation-ticket']
    || $summary['reopenLeases'] !== ['wp-options-stale-generation-ticket']
) {
    fwrite(STDERR, "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next204 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
