<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan.php';

$digest = static fn (string $value): string => hash('sha256', $value);
$walDigest = $digest('wordpress next231 restarted wal sidecar');
$token = ['id' => 'wordpress-import-current-source-next231', 'epoch' => 231];
$admission = [
    'status' => 'wal-hot-journal-savepoint-checkpoint-current-source-next211',
    'database_path' => '/srv/www/wp-content/database/wordpress.sqlite',
    'wal_path' => '/srv/www/wp-content/database/wordpress.sqlite-wal',
    'journal_path' => '/srv/www/wp-content/database/wordpress.sqlite-journal',
    'current_source_token' => $token,
    'checkpoint_frame' => 64,
    'checkpoint_cookie' => 90231,
    'schema_cookie' => 1231,
    'next_source_epoch' => 232,
    'checkpoint_admitted' => true,
    'admitted_reader_names' => ['wp-options-import'],
    'reopen_reader_names' => ['plugin-cache-reader'],
];
$scope = static function (string $name, array $pages) use ($digest): array {
    $pageDigests = [];
    foreach ($pages as $page) {
        $pageDigests[$page] = $digest($name . ':page:' . $page);
    }

    return [
        'name' => $name,
        'savepoint_depth' => 0,
        'released' => true,
        'rollback_generation' => 231,
        'checkpoint_frame' => 64,
        'checkpoint_cookie' => 90231,
        'schema_cookie' => 1231,
        'journal_delete_receipt' => true,
        'wal_reset_frame' => 64,
        'reader_names' => ['wp-options-import'],
        'page_digests' => $pageDigests,
    ];
};
$publishReceipt = static function (string $name, array $pages) use ($digest, $token): array {
    $pageDigests = [];
    foreach ($pages as $page) {
        $pageDigests[$page] = $digest($name . ':page:' . $page);
    }

    return [
        'scope_name' => $name,
        'source_token_id' => $token['id'],
        'source_epoch' => 231,
        'checkpoint_frame' => 64,
        'checkpoint_cookie' => 90231,
        'schema_cookie' => 1231,
        'journal_delete_receipt' => true,
        'page_digests' => $pageDigests,
        'next_source_epoch' => 232,
    ];
};
$finalized = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next219Plan($admission, [
    $scope('wp-options-savepoint', [1, 2, 5]),
]);
$published = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next227Plan($finalized, [
    $publishReceipt('wp-options-savepoint', [1, 2, 5]),
]);
$readmarks = ['plugin-cache-reader' => 64, 'wp-options-import' => 64];
$checksumDigest = hash('sha256', json_encode([23101, 23102, 64, 64, $readmarks, $walDigest], JSON_THROW_ON_ERROR));
$plan = SQLiteWalHotJournalSavepointCheckpointCurrentSourceNextPlan::next231VerifyWalIndexReopen($published, [[
    'name' => 'wordpress-wal-index-reopen',
    'scope_names' => ['wp-options-savepoint'],
    'source_token_id' => $token['id'],
    'source_epoch' => 231,
    'next_source_epoch' => 232,
    'checkpoint_frame' => 64,
    'checkpoint_cookie' => 90231,
    'schema_cookie' => 1231,
    'wal_digest' => $walDigest,
    'salt_1' => 23101,
    'salt_2' => 23102,
    'checksum_digest' => $checksumDigest,
    'mx_frame' => 64,
    'backfill_frame' => 64,
    'readmark_frames' => $readmarks,
    'readers_reopened' => true,
    'shm_synced' => true,
]], $walDigest);

if (($argv[1] ?? '') === '--self-test') {
    if ($plan['status'] !== 'wal-hot-journal-savepoint-checkpoint-current-source-next231'
        || $plan['can_reopen_current_source'] !== true
        || $plan['receipt_rows'][0]['readmark_frames'] !== $readmarks
        || !in_array('wordpress-import-wal-index-reopen-current-source', $plan['dependencies'], true)
    ) {
        fwrite(STDERR, "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next231 self-test failed\n");
        exit(1);
    }

    echo "wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next231 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'wordpress-wal-hot-journal-savepoint-checkpoint-current-source-next231',
    'status' => $plan['status'],
    'canReopenCurrentSource' => $plan['can_reopen_current_source'],
    'checkpointFrame' => $plan['checkpoint_frame'],
    'coveredScopes' => $plan['covered_scope_names'],
    'wordpressUse' => 'A copied wp_options import reopens the current source only after the WAL-index salts, checksum digest, backfill frame, and reader readmarks match the checkpoint published after hot-journal savepoint recovery.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
