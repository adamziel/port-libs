<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePagerSavepointCacheSpillHotJournalCurrentSourceNext151Plan;
use PortLibs\LibSqlite\SQLiteSavepointStack;

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databasePath = '/srv/wp-content/database/wp-options-next151.sqlite';

$before = [
    1 => $page('wp next151 stale schema root before hot journal'),
    2 => $page('wp next151 stale active_plugins before hot journal'),
    3 => $page('wp next151 stale autoload index before hot journal'),
    4 => $page('wp next151 stale plugin settings before hot journal'),
];
$hot = [
    1 => $page('wp next151 recovered schema root'),
    2 => $page('wp next151 recovered active_plugins'),
    3 => $page('wp next151 recovered autoload index'),
    4 => $page('wp next151 recovered plugin settings'),
];

$savepoints = new SQLiteSavepointStack();
$savepoints->beginTransaction('wp-options-import');
$savepoints->savepoint('plugin-settings-batch');
$savepoints->recordPageImageWrite(2, $hot[2]);
$savepoints->recordPageImageWrite(3, $before[3]);
$savepoints->recordPageImageWrite(4, $hot[4]);

$summary = SQLitePagerSavepointCacheSpillHotJournalCurrentSourceNext151Plan::plan(
    $databasePath,
    implode('', $before),
    $pageSize,
    $hot,
    'plugin-settings-batch',
    $savepoints,
    [
        ['page' => 2, 'image' => $page('wp next151 dirty active_plugins after recovery'), 'current_image' => $hot[2], 'bytes' => $pageSize, 'journaled' => true],
        ['page' => 3, 'image' => $page('wp next151 dirty autoload stale savepoint'), 'current_image' => $hot[3], 'bytes' => $pageSize, 'journaled' => true],
        ['page' => 4, 'image' => $page('wp next151 dirty plugin settings pinned'), 'current_image' => $hot[4], 'bytes' => $pageSize, 'journaled' => true, 'pinned' => true],
    ],
    6,
    3,
);

if (($argv[1] ?? '') === '--self-test') {
    if (
        $summary['status'] !== 'pager_savepoint_cache_spill_hot_journal_current_source_next151'
        || $summary['admitted_page_numbers'] !== [2]
        || $summary['rejected_pages'][3] !== ['stale_savepoint_before_image_before_hot_journal_recovery']
        || $summary['spilled_page_numbers'] !== [2]
    ) {
        fwrite(STDERR, "wordpress-pager-savepoint-cache-spill-hot-journal-current-source-next151 self-test failed\n");
        exit(1);
    }
    echo "wordpress-pager-savepoint-cache-spill-hot-journal-current-source-next151 self-test passed\n";
    exit(0);
}

echo json_encode([
    'status' => $summary['status'],
    'savepoint' => $summary['savepoint'],
    'admitted_page_numbers' => $summary['admitted_page_numbers'],
    'rejected_pages' => $summary['rejected_pages'],
    'spilled_page_numbers' => $summary['spilled_page_numbers'],
    'wordpressUse' => 'A copied wp_options import can recover a hot rollback journal before cache spill, then spill only dirty savepoint pages whose before-image was recaptured from the recovered current source.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
