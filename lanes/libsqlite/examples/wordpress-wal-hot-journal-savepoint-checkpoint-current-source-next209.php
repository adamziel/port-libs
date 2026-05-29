<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$digest = static fn (string $value): string => hash('sha256', $value);
$databaseDigest = $digest('wp next209 checkpointed database pages');
$walDigest = $digest('wp next209 published WAL sidecar');
$consumerDigest = $digest('wp next209 reopened statement generation');

$statementPlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next206',
    'database_path' => '/srv/www/wp-content/database/wp-options.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-options.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-options.sqlite-wal',
    'page_size' => 512,
    'minimum_statement_generation' => 206,
    'checkpointed_database_digest' => $databaseDigest,
    'expected_wal_digest' => $walDigest,
    'consumer_digest' => $consumerDigest,
    'admitted_consumer_names' => ['wp-options-current-select', 'wp-cron-current-select'],
    'quarantined_consumer_names' => ['old-plugin-settings-select'],
    'blocked_guard_names' => [],
    'operation_names' => ['verify_reopened_statement_generation_current_source_next206'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next206'],
];

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::writerGenerationAdvancePlan(
    $statementPlan,
    [
        [
            'name' => 'wp-options-autoload-update',
            'kind' => 'writer',
            'writer_generation' => 207,
            'statement_generation' => 206,
            'observed_database_digest' => $databaseDigest,
            'observed_wal_digest' => $walDigest,
            'observed_consumer_digest' => $consumerDigest,
            'retains_consumers' => ['wp-options-current-select', 'wp-cron-current-select'],
            'reopens_consumers' => ['old-plugin-settings-select'],
        ],
        [
            'name' => 'stale-plugin-writer',
            'kind' => 'writer',
            'writer_generation' => 206,
            'statement_generation' => 205,
            'observed_database_digest' => $databaseDigest,
            'observed_wal_digest' => $walDigest,
            'observed_consumer_digest' => $consumerDigest,
            'retains_consumers' => ['wp-options-current-select'],
            'reopens_consumers' => [],
        ],
    ],
    207
);

$summary = [
    'scenario' => 'wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next209',
    'wordpressUse' => 'A copied WordPress import resumes after hot-journal recovery and checkpoint publication, then admits only writer handles that retain current prepared statements and reopen stale plugin statements before appending new WAL frames.',
    'status' => $plan['status'],
    'admittedWriters' => $plan['admitted_writer_names'],
    'reopenWriters' => $plan['reopen_writer_names'],
    'writerDigest' => $plan['writer_digest'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    if (
        $summary['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-next209'
        || $summary['admittedWriters'] !== ['wp-options-autoload-update']
        || $summary['reopenWriters'] !== ['stale-plugin-writer']
    ) {
        fwrite(STDERR, "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next209 self-test failed\n");
        exit(1);
    }

    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

return $summary;
