<?php

declare(strict_types=1);

require dirname(__DIR__) . '/../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSavepointStack;

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$savepoints = new SQLiteSavepointStack();
$savepoints->beginTransaction('wp-import');
$savepoints->recordPageImageWrite(1, $page('before-root-catalog'));
$savepoints->recordWalFrameWrite(1, 1);
$savepoints->savepoint('plugin-batch');
$savepoints->recordPageImageWrite(2, $page('before-plugin-option'));
$savepoints->recordWalFrameWrite(2, 2);
$savepoints->savepoint('autoload-index');
$savepoints->recordPageImageWrite(3, $page('before-autoload-index'));
$savepoints->recordWalFrameWrite(3, 3, true);
$savepoints->savepoint('transient-cache');
$savepoints->recordPageImageWrite(4, $page('before-transient-cache'));
$savepoints->recordWalFrameWrite(4, 4);

$currentDatabase = $page('current-root-catalog')
    . $page('current-plugin-option')
    . $page('current-autoload-index')
    . $page('current-transient-cache');

$plan = $savepoints->rollbackToCurrentSourceThenRelease('autoload-index', $currentDatabase, [
    2 => $page('current-plugin-option'),
    3 => $page('current-autoload-index'),
    4 => $page('current-transient-cache'),
], $pageSize);

$summary = [
    'scenario' => 'application-savepoint-nested-rollback-release-current-source-next116',
    'applicationUse' => 'Model a copied wp_options import where a nested autoload-index savepoint fails, verifies the current database image, rolls back nested pages, releases the still-active savepoint, and leaves the outer plugin batch ready to retry or commit.',
    'currentSourceVerified' => $plan['current_source_verified'],
    'currentSourcePages' => $plan['current_source_page_numbers'],
    'restoredPages' => $plan['restored_page_numbers'],
    'discardedWalFrames' => array_column($plan['discarded_wal_frames'], 'frame_index'),
    'namesAfterRollback' => $plan['names_after_rollback'],
    'namesAfterRelease' => $plan['names_after_release'],
    'pendingPagesAfterRelease' => $plan['pending_page_numbers_after_release'],
    'pendingWalAfterRelease' => $plan['pending_wal_frame_indexes_after_release'],
    'rolledBackAutoloadPage' => rtrim(substr($plan['rolled_back_database_bytes'], $pageSize * 2, $pageSize), '.'),
    'rolledBackTransientPage' => rtrim(substr($plan['rolled_back_database_bytes'], $pageSize * 3, $pageSize), '.'),
    'dependencyClosure' => 'no new support component needed; this composes existing native PHP savepoint page-image, WAL-frame, and release bookkeeping',
];

if (PHP_SAPI === 'cli' && in_array('--self-test', $argv, true)) {
    assert($summary['currentSourceVerified'] === true);
    assert($summary['restoredPages'] === [3, 4]);
    assert($summary['discardedWalFrames'] === [3, 4]);
    assert($summary['namesAfterRelease'] === ['wp-import', 'plugin-batch']);
    assert($summary['rolledBackAutoloadPage'] === 'before-autoload-index');
    echo "application-savepoint-nested-rollback-release-current-source-next116 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
