<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$digest = static fn (string $bytes): string => hash('sha256', $bytes);
$database = $page('wp next206 schema checkpoint') . $page('wp next206 option checkpoint');
$walDigest = $digest('wp next206 checkpoint wal generation');
$pageDigests = [
    1 => $digest($page('wp next206 schema checkpoint')),
    2 => $digest($page('wp next206 option checkpoint')),
];

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next206Plan(
    [
        'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next203',
        'database_path' => '/srv/www/wp-content/database/wp-options.sqlite',
        'journal_path' => '/srv/www/wp-content/database/wp-options.sqlite-journal',
        'wal_path' => '/srv/www/wp-content/database/wp-options.sqlite-wal',
        'page_size' => $pageSize,
        'checkpointed_database_digest' => $digest($database),
        'expected_wal_digest' => $walDigest,
        'expected_page_digests' => $pageDigests,
        'stale_guard_names' => [],
        'operation_names' => ['verify_checkpoint_page_cache_leases_current_source_next203'],
        'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next203'],
    ],
    [
        [
            'name' => 'wp-options-current-select',
            'reader_epoch' => 207,
            'statement_generation' => 206,
            'root_pages' => [1, 2],
            'observed_database_digest' => $digest($database),
            'observed_wal_digest' => $walDigest,
            'observed_page_digests' => $pageDigests,
        ],
        [
            'name' => 'wp-options-stale-import-statement',
            'reader_epoch' => 205,
            'statement_generation' => 205,
            'root_pages' => [2],
            'observed_database_digest' => $digest($database),
            'observed_wal_digest' => $walDigest,
            'observed_page_digests' => [2 => $pageDigests[2]],
            'hot_journal_digest' => $digest('stale import hot journal'),
        ],
    ],
    206
);

echo json_encode([
    'status' => $plan['status'],
    'reason' => $plan['reason'],
    'admittedConsumers' => $plan['admitted_consumer_names'],
    'quarantinedConsumers' => $plan['quarantined_consumer_names'],
    'blockedGuards' => $plan['blocked_guard_names'],
    'consumerDigest' => $plan['consumer_digest'],
    'dependencyClosure' => $plan['dependency_closure'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
