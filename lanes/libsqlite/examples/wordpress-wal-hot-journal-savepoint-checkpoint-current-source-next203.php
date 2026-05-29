<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$digest = static fn (string $bytes): string => hash('sha256', $bytes);
$checkpointedDatabase = $page('wp next203 schema checkpoint')
    . $page('wp next203 options checkpoint')
    . $page('wp next203 plugin checkpoint');
$publishedWalDigest = $digest('wp next203 restarted wal sidecar');
$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next203Plan(
    [
        'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next196',
        'database_path' => '/srv/www/wp-content/database/wp-next203.sqlite',
        'journal_path' => '/srv/www/wp-content/database/wp-next203.sqlite-journal',
        'wal_path' => '/srv/www/wp-content/database/wp-next203.sqlite-wal',
        'page_size' => $pageSize,
        'mode' => 'restart',
        'sidecar' => [
            'matched' => true,
            'actual_digest' => $publishedWalDigest,
        ],
        'operation_names' => ['publish_wal_sidecar_current_source_next196'],
        'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next196'],
    ],
    $checkpointedDatabase,
    [
        [
            'name' => 'wp-options-current-cache',
            'root_pages' => [1, 2],
            'observed_wal_digest' => $publishedWalDigest,
            'observed_page_digests' => [
                1 => $digest($page('wp next203 schema checkpoint')),
                2 => $digest($page('wp next203 options checkpoint')),
            ],
        ],
        [
            'name' => 'wp-options-old-cache',
            'root_pages' => [2],
            'observed_wal_digest' => $digest('wp next203 old wal sidecar'),
            'observed_page_digests' => [
                2 => $digest($page('wp next203 options before checkpoint')),
            ],
        ],
    ]
);

$summary = [
    'scenario' => 'wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next203',
    'wordpressUse' => 'After a copied WordPress import recovers a hot journal, rolls back a failed savepoint, checkpoints WAL frames, and publishes the restarted WAL sidecar, wp_options page-cache leases are reused only when their WAL and checkpointed database page digests both match the current source.',
    'status' => $plan['status'],
    'admittedLeases' => $plan['admitted_lease_names'],
    'reopenLeases' => $plan['reopen_lease_names'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if ($summary['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-next203'
    || $summary['admittedLeases'] !== ['wp-options-current-cache']
    || $summary['reopenLeases'] !== ['wp-options-old-cache']
) {
    fwrite(STDERR, "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next203 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
