<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$digest = static fn (string $value): string => hash('sha256', $value);
$databaseDigest = $digest('wp next210 checkpointed database pages');
$walDigest = $digest('wp next210 post-checkpoint wal source');
$consumerDigest = $digest('wp next210 current consumer source');

$writerPlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next209',
    'database_path' => '/srv/www/wp-content/database/wp-options.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-options.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-options.sqlite-wal',
    'page_size' => 512,
    'minimum_statement_generation' => 209,
    'next_writer_generation' => 210,
    'checkpointed_database_digest' => $databaseDigest,
    'expected_wal_digest' => $walDigest,
    'consumer_digest' => $consumerDigest,
    'admitted_writer_names' => ['wp-options-autoload-update', 'wp-cron-option-update'],
    'reopen_writer_names' => ['stale-plugin-writer'],
    'blocked_guard_names' => [],
    'operation_names' => ['verify_post_checkpoint_writer_generation_current_source_next209'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next209'],
];

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next210Plan(
    $writerPlan,
    [
        [
            'name' => 'autoload-frame-batch',
            'writer_name' => 'wp-options-autoload-update',
            'writer_generation' => 210,
            'checkpoint_frame' => 18,
            'first_frame' => 19,
            'commit_frame' => 22,
            'observed_database_digest' => $databaseDigest,
            'observed_wal_digest' => $walDigest,
            'observed_consumer_digest' => $consumerDigest,
            'page_digests' => [
                2 => $digest('wp_options root after next210 autoload update'),
                5 => $digest('plugin settings overflow after next210 update'),
            ],
            'exclusive_lock_receipt' => true,
        ],
        [
            'name' => 'stale-plugin-frame-batch',
            'writer_name' => 'stale-plugin-writer',
            'writer_generation' => 210,
            'checkpoint_frame' => 18,
            'first_frame' => 19,
            'commit_frame' => 22,
            'observed_database_digest' => $databaseDigest,
            'observed_wal_digest' => $walDigest,
            'observed_consumer_digest' => $consumerDigest,
            'page_digests' => [
                9 => $digest('stale plugin option frame should be blocked'),
            ],
            'exclusive_lock_receipt' => true,
        ],
    ],
    22
);

$summary = [
    'scenario' => 'wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next210',
    'wordpressUse' => 'A copied WordPress options import resumes after hot-journal recovery and checkpoint publication, then admits only current writer WAL frame batches while stale plugin writers reopen before appending.',
    'status' => $plan['status'],
    'acceptedAppendBatches' => $plan['accepted_append_batch_names'],
    'blockedAppendBatches' => $plan['blocked_append_batch_names'],
    'appendDigest' => $plan['append_digest'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if (PHP_SAPI === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    if (
        $summary['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-next210'
        || $summary['acceptedAppendBatches'] !== ['autoload-frame-batch']
        || $summary['blockedAppendBatches'] !== ['stale-plugin-frame-batch']
    ) {
        fwrite(STDERR, "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next210 self-test failed\n");
        exit(1);
    }

    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
}

return $summary;
