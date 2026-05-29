<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLitePagerWalSavepointHotCacheCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLitePagerWalSavepointHotCacheCurrentSourceNextPlan;

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$databasePath = '/srv/wp-content/database/wp-options-next139.sqlite';
$sourceId = 'wordpress-wal-hot-cache-next139';

$base = [
    1 => $page('wp next139 base sqlite header'),
    2 => $page('wp next139 base active_plugins option'),
    3 => $page('wp next139 base plugin setting option'),
    4 => $page('wp next139 base transient option'),
];

$plan = SQLitePagerWalSavepointHotCacheCurrentSourceNextPlan::plan(
    $databasePath,
    implode('', $base),
    $pageSize,
    'plugin-import',
    1,
    [
        1 => $page('wp next139 hot recovered sqlite header'),
        2 => $page('wp next139 hot recovered active_plugins option'),
    ],
    [
        1 => ['page' => 1, 'image' => $page('wp next139 retained schema cookie wal frame'), 'commit_frame' => true],
        2 => ['page' => 2, 'image' => $page('wp next139 discarded active_plugins wal frame')],
        3 => ['page' => 3, 'image' => $page('wp next139 discarded plugin setting wal frame')],
    ],
    [
        1 => ['image' => $page('wp next139 retained schema cookie wal frame'), 'source_id' => $sourceId, 'generation' => 4, 'frame' => 1],
        2 => ['image' => $page('wp next139 discarded active_plugins wal frame'), 'source_id' => $sourceId, 'generation' => 4, 'frame' => 2],
        3 => ['image' => $page('wp next139 discarded plugin setting wal frame'), 'source_id' => $sourceId, 'generation' => 4, 'frame' => 3, 'dirty' => true],
    ],
    [1, 2, 3],
    [
        2 => $page('wp next139 rewritten active_plugins after savepoint retry'),
        3 => $page('wp next139 rewritten plugin setting after savepoint retry'),
    ],
    $sourceId,
    4,
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'pager-wal-savepoint-hot-cache-current-source-next139');
    assert($plan['retained_cache_page_numbers'] === [1]);
    assert($plan['refreshed_cache_page_numbers'] === [2]);
    assert($plan['invalidated_cache_page_numbers'] === [3]);
    assert($plan['next_writes'][0]['before_prefix'] === 'wp next139 hot recovered active_plugins option');
    assert(str_contains($plan['final_database_bytes'], 'rewritten active_plugins after savepoint retry'));
    echo "wordpress-pager-wal-savepoint-hot-cache-current-source-next139 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'savepoint' => $plan['savepoint'],
    'retained' => $plan['retained_cache_page_numbers'],
    'refreshed' => $plan['refreshed_cache_page_numbers'],
    'invalidated' => $plan['invalidated_cache_page_numbers'],
    'writes' => $plan['next_writes'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
