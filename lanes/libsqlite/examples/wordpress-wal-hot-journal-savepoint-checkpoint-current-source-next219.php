<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

$hash = static fn (string $value): string => hash('sha256', $value);
$admissionPlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next211',
    'database_path' => '/srv/www/wp-content/database/wp-next219.sqlite',
    'wal_path' => '/srv/www/wp-content/database/wp-next219.sqlite-wal',
    'journal_path' => '/srv/www/wp-content/database/wp-next219.sqlite-journal',
    'current_source_token' => ['id' => 'wp-next219-current-source', 'epoch' => 219],
    'checkpoint_frame' => 42,
    'checkpoint_cookie' => 21942,
    'schema_cookie' => 21917,
    'admitted_reader_names' => ['wp-schema-reader', 'wp-options-reader', 'wp-cron-reader'],
    'reopen_reader_names' => ['wp-old-plugin-reader'],
    'checkpoint_admitted' => true,
    'next_source_epoch' => 220,
];
$scope = static function (string $name, array $readers, array $pages) use ($admissionPlan): array {
    return [
        'name' => $name,
        'savepoint_depth' => 0,
        'released' => true,
        'rollback_generation' => $admissionPlan['current_source_token']['epoch'],
        'checkpoint_frame' => $admissionPlan['checkpoint_frame'],
        'checkpoint_cookie' => $admissionPlan['checkpoint_cookie'],
        'schema_cookie' => $admissionPlan['schema_cookie'],
        'journal_delete_receipt' => true,
        'wal_reset_frame' => $admissionPlan['checkpoint_frame'],
        'reader_names' => $readers,
        'page_digests' => $pages,
    ];
};

$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next219Plan($admissionPlan, [
    $scope('wp-plugin-import', ['wp-schema-reader', 'wp-options-reader'], [
        1 => $hash('wp schema after plugin import checkpoint'),
        2 => $hash('wp_options after plugin import checkpoint'),
    ]),
    $scope('wp-cron-flush', ['wp-cron-reader'], [
        3 => $hash('cron option after checkpoint'),
    ]),
]);

$summary = [
    'scenario' => 'wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next219',
    'wordpressUse' => 'A copied WordPress plugin import publishes the checkpoint as the next current source only after hot-journal savepoint scopes are released, page digests are recorded, and stale readers remain fenced for reopen.',
    'status' => $plan['status'],
    'checkpointNextSourcePublished' => $plan['checkpoint_next_source_published'],
    'finalizedScopes' => $plan['finalized_scope_names'],
    'nextSourceEpoch' => $plan['next_source_epoch'],
    'dependencyClosure' => $plan['dependency_closure'],
];

if ($summary['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-next219'
    || $summary['checkpointNextSourcePublished'] !== true
    || $summary['finalizedScopes'] !== ['wp-plugin-import', 'wp-cron-flush']
) {
    fwrite(STDERR, "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next219 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
