<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$digest = static fn (string $value): string => hash('sha256', $value);
$token = ['id' => 'wp-import-current-source-next227', 'epoch' => 227];
$admission = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next211',
    'database_path' => '/srv/www/wp-content/database/wp-next227.sqlite',
    'wal_path' => '/srv/www/wp-content/database/wp-next227.sqlite-wal',
    'journal_path' => '/srv/www/wp-content/database/wp-next227.sqlite-journal',
    'current_source_token' => $token,
    'checkpoint_frame' => 44,
    'checkpoint_cookie' => 90227,
    'schema_cookie' => 1227,
    'next_source_epoch' => 228,
    'checkpoint_admitted' => true,
    'admitted_reader_names' => ['wp-options-import', 'wp-theme-import'],
    'reopen_reader_names' => ['old-plugin-reader'],
];
$scope = static function (string $name, array $pages, array $readers) use ($digest): array {
    $pageDigests = [];
    foreach ($pages as $page) {
        $pageDigests[$page] = $digest($name . ':page:' . $page);
    }

    return [
        'name' => $name,
        'savepoint_depth' => 0,
        'released' => true,
        'rollback_generation' => 227,
        'checkpoint_frame' => 44,
        'checkpoint_cookie' => 90227,
        'schema_cookie' => 1227,
        'journal_delete_receipt' => true,
        'wal_reset_frame' => 44,
        'reader_names' => $readers,
        'page_digests' => $pageDigests,
    ];
};
$receipt = static function (string $name, array $pages) use ($digest, $token): array {
    $pageDigests = [];
    foreach ($pages as $page) {
        $pageDigests[$page] = $digest($name . ':page:' . $page);
    }

    return [
        'scope_name' => $name,
        'source_token_id' => $token['id'],
        'source_epoch' => 227,
        'checkpoint_frame' => 44,
        'checkpoint_cookie' => 90227,
        'schema_cookie' => 1227,
        'journal_delete_receipt' => true,
        'page_digests' => $pageDigests,
        'next_source_epoch' => 228,
    ];
};

$finalized = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next219Plan($admission, [
    $scope('wp-options-savepoint', [1, 2], ['wp-options-import']),
    $scope('wp-theme-savepoint', [3], ['wp-theme-import']),
]);
$summary = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next227Plan($finalized, [
    $receipt('wp-options-savepoint', [1, 2]),
    $receipt('wp-theme-savepoint', [3]),
]);

if (
    $summary['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-next227'
    || $summary['checkpoint_publish_allowed'] !== true
    || $summary['publishable_scope_names'] !== ['wp-options-savepoint', 'wp-theme-savepoint']
) {
    fwrite(STDERR, "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next227 self-test failed\n");
    exit(1);
}

echo "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next227 self-test passed\n";
