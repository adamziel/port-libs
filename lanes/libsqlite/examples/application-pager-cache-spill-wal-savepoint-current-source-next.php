<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePagerCacheSpillWalSavepointCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSavepointStack;

$pageSize = 512;
$databasePath = '/var/www/html/wp-content/database/wp-next143.sqlite';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$base = [
    1 => $page('wp next143 base sqlite header'),
    2 => $page('wp next143 base wp_options root'),
    3 => $page('wp next143 base active_plugins'),
    4 => $page('wp next143 base autoload index'),
];
$walFrames = [
    1 => ['page' => 2, 'image' => $page('wp next143 retained wal options root')],
    2 => ['page' => 3, 'image' => $page('wp next143 retained wal active plugins'), 'commit_frame' => true],
    3 => ['page' => 4, 'image' => $page('wp next143 discarded wal autoload retry')],
];

$stack = new SQLiteSavepointStack();
$stack->beginTransaction('wp-import');
$stack->recordWalFrameWrite(1, 2, false);
$stack->recordWalFrameWrite(2, 3, true);
$stack->savepoint('plugin-settings');
$stack->recordPageImageWrite(2, $walFrames[1]['image']);
$stack->recordPageImageWrite(3, $walFrames[2]['image']);
$stack->recordPageImageWrite(4, $base[4]);
$stack->recordWalFrameWrite(3, 4, false);

$plan = SQLitePagerCacheSpillWalSavepointCurrentSourceNextPlan::currentSourceNext(
    $databasePath,
    implode('', $base),
    $pageSize,
    'plugin-settings',
    $stack,
    $walFrames,
    [
        ['page' => 2, 'image' => $page('wp next143 retry options root spill'), 'current_image' => $walFrames[1]['image'], 'walFrame' => 1],
        ['page' => 3, 'image' => $page('wp next143 retry active plugins spill'), 'current_image' => $walFrames[2]['image'], 'walFrame' => 2],
        ['page' => 4, 'image' => $walFrames[3]['image'], 'current_image' => $base[4], 'walFrame' => 3],
    ],
    6,
    3,
);

if (($argv[1] ?? '') === '--self-test') {
    if ($plan['status'] !== 'pager_cache_spill_wal_savepoint_current_source_next143') {
        throw new RuntimeException('Unexpected WAL savepoint cache-spill status');
    }
    if ($plan['spilled_page_numbers'] !== [2, 3]) {
        throw new RuntimeException('Unexpected WAL cache-spill page set');
    }
    if ($plan['rejected_page_numbers'] !== [4]) {
        throw new RuntimeException('Discarded WAL tail page was not rejected');
    }
    echo "application-pager-cache-spill-wal-savepoint-current-source-next143 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'rollback_to_frame' => $plan['rollback_to_frame'],
    'spilled_pages' => $plan['spilled_page_numbers'],
    'rejected_pages' => $plan['rejected_page_numbers'],
    'next_wal_frames' => $plan['appended_wal_frames'],
], JSON_PRETTY_PRINT) . "\n";
