<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$digest = static fn (string $value): string => hash('sha256', $value);
$pageDigests = static function (string $scope, array $pages) use ($digest): array {
    $rows = [];
    foreach ($pages as $page) {
        $rows[$page] = $digest($scope . ':wp-options-checkpoint-page:' . $page);
    }

    return $rows;
};
$receiptRow = static function (string $scope, array $pages) use ($pageDigests): array {
    return [
        'scope_name' => $scope,
        'publishable' => true,
        'page_digests' => $pageDigests($scope, $pages),
    ];
};
$publishPlan = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next227',
    'database_path' => '/srv/www/wp-content/database/wp-next230.sqlite',
    'wal_path' => '/srv/www/wp-content/database/wp-next230.sqlite-wal',
    'journal_path' => '/srv/www/wp-content/database/wp-next230.sqlite-journal',
    'current_source_token' => ['id' => 'wordpress-import-hot-journal-next230', 'epoch' => 230],
    'checkpoint_frame' => 52,
    'checkpoint_cookie' => 90230,
    'schema_cookie' => 1230,
    'next_source_epoch' => 231,
    'checkpoint_publish_allowed' => true,
    'receipt_rows' => [
        $receiptRow('wp-options-savepoint', [1, 2]),
        $receiptRow('wp-autoload-savepoint', [3]),
    ],
    'operation_names' => ['publish_checkpoint_next_source_receipt_next227'],
    'dependencies' => ['sqlite-wal-hot-journal-savepoint-checkpoint-current-source-next227'],
];
$ticket = static function (string $reader, string $scope, array $pages) use ($pageDigests): array {
    return [
        'reader_name' => $reader,
        'scope_name' => $scope,
        'source_token_id' => 'wordpress-import-hot-journal-next230',
        'source_epoch' => 231,
        'checkpoint_frame' => 52,
        'checkpoint_cookie' => 90230,
        'schema_cookie' => 1230,
        'visible_page_digests' => $pageDigests($scope, $pages),
        'hot_journal_visible' => false,
        'wal_tail_visible' => false,
    ];
};
$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next230Plan($publishPlan, [
    $ticket('wp-options-reader', 'wp-options-savepoint', [1, 2]),
    $ticket('wp-autoload-reader', 'wp-autoload-savepoint', [3]),
]);

if (in_array('--self-test', $argv, true)) {
    assert($plan['status'] === 'wal-hot-journal-savepoint-checkpoint-current-source-next230');
    assert($plan['can_serve_next_source_readers'] === true);
    assert($plan['admitted_reader_names'] === ['wp-options-reader', 'wp-autoload-reader']);
    assert($plan['blocked_reasons'] === []);
    assert(in_array('serve_checkpoint_next_source_readers_next230', $plan['operation_names'], true));
    echo "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next230 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'reason' => $plan['reason'],
    'admitted_reader_names' => $plan['admitted_reader_names'],
    'checkpoint_frame' => $plan['checkpoint_frame'],
    'reader_epoch' => $plan['reader_epoch'],
    'can_serve_next_source_readers' => $plan['can_serve_next_source_readers'],
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
