<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$digest = static fn (string $value): string => hash('sha256', $value);
$databaseDigest = $digest('wp next212 checkpoint database');
$walDigest = $digest('wp next212 retained wal');
$writerDigest = $digest('wp next212 writer fence');

$writerPlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next209',
    'database_path' => '/srv/www/wp-content/database/wp-options.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-options.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-options.sqlite-wal',
    'page_size' => 512,
    'minimum_statement_generation' => 209,
    'next_writer_generation' => 212,
    'checkpointed_database_digest' => $databaseDigest,
    'expected_wal_digest' => $walDigest,
    'writer_digest' => $writerDigest,
    'admitted_writer_names' => ['wp-options-autoload-writer'],
    'reopen_writer_names' => ['stale-plugin-writer'],
    'operation_names' => ['verify_post_checkpoint_writer_generation_current_source_next209'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next209'],
];

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next212PassiveCheckpoint(
    $writerPlan,
    [
        [
            'name' => 'wp-options-current-reader',
            'reader_end_frame' => 210,
            'reader_generation' => 212,
            'observed_database_digest' => $databaseDigest,
            'observed_wal_digest' => $walDigest,
            'observed_writer_digest' => $writerDigest,
        ],
        [
            'name' => 'old-plugin-reader',
            'reader_end_frame' => 208,
            'reader_generation' => 211,
            'observed_database_digest' => $databaseDigest,
            'observed_wal_digest' => $walDigest,
            'observed_writer_digest' => $writerDigest,
        ],
    ],
    212
);

$summary = [
    'scenario' => 'wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next212',
    'wordpressUse' => 'A copied WordPress import recovers a hot journal, rolls back a plugin savepoint, then runs a PASSIVE checkpoint that writes only through the current reader pin and preserves WAL bytes for the still-open wp_options reader.',
    'status' => $plan['status'],
    'busy' => $plan['busy'],
    'checkpointedFrame' => $plan['checkpointed_frame'],
    'walAction' => $plan['wal_action'],
    'activeReaders' => $plan['active_reader_names'],
    'reopenReaders' => $plan['reopen_reader_names'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    if (
        $summary['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-next212'
        || $summary['busy'] !== true
        || $summary['checkpointedFrame'] !== 210
        || $summary['walAction'] !== 'preserve_wal'
    ) {
        fwrite(STDERR, "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next212 self-test failed\n");
        exit(1);
    }

    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

return $summary;
