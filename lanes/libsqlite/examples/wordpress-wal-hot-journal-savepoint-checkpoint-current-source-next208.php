<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$digest = static fn (string $bytes): string => hash('sha256', $bytes);
$databaseDigest = $digest($page('wp next208 schema checkpoint') . $page('wp next208 options checkpoint'));
$walDigest = $digest('wp next208 checkpoint wal');
$pageDigests = [
    1 => $digest($page('wp next208 schema checkpoint')),
    2 => $digest($page('wp next208 options checkpoint')),
];

$consumerPlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next206',
    'database_path' => '/srv/www/wp-content/database/wp-next208.sqlite',
    'journal_path' => '/srv/www/wp-content/database/wp-next208.sqlite-journal',
    'wal_path' => '/srv/www/wp-content/database/wp-next208.sqlite-wal',
    'page_size' => $pageSize,
    'minimum_statement_generation' => 206,
    'checkpointed_database_digest' => $databaseDigest,
    'expected_wal_digest' => $walDigest,
    'expected_page_digests' => $pageDigests,
    'admitted_consumer_names' => ['wp-options-select-current'],
    'blocked_guard_names' => [],
    'operation_names' => ['verify_reopened_statement_generation_current_source_next206'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next206'],
];

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::readerSlotCheckpointAdmissionPlan(
    $consumerPlan,
    [
        [
            'name' => 'wp-options-reader-slot',
            'consumer_name' => 'wp-options-select-current',
            'read_mark' => 8,
            'reader_epoch' => 208,
            'checkpoint_frame' => 8,
            'root_pages' => [1, 2],
            'observed_database_digest' => $databaseDigest,
            'observed_wal_digest' => $walDigest,
            'observed_page_digests' => $pageDigests,
            'lock_receipt' => true,
        ],
        [
            'name' => 'wp-options-stale-plugin-slot',
            'consumer_name' => 'wp-plugin-stale-statement',
            'read_mark' => 7,
            'reader_epoch' => 205,
            'checkpoint_frame' => 7,
            'root_pages' => [2],
            'observed_database_digest' => $databaseDigest,
            'observed_wal_digest' => $walDigest,
            'observed_page_digests' => [2 => $pageDigests[2]],
            'lock_receipt' => true,
        ],
    ],
    8
);

$summary = [
    'scenario' => 'wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next208',
    'wordpressUse' => 'A copied WordPress plugin import reopens after hot-journal recovery and checkpoint publication, then reuses only reader slots tied to next206 current-source statement consumers.',
    'status' => $plan['status'],
    'retainedReaderSlots' => $plan['retained_reader_slot_names'],
    'reopenedReaderSlots' => $plan['reopened_reader_slot_names'],
    'checkpointFrame' => $plan['checkpoint_frame'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    if ($summary['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-next208'
        || $summary['retainedReaderSlots'] !== ['wp-options-reader-slot']
        || $summary['reopenedReaderSlots'] !== ['wp-options-stale-plugin-slot']
    ) {
        fwrite(STDERR, "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next208 self-test failed\n");
        exit(1);
    }

    echo "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next208 self-test passed\n";
}

return $summary;
