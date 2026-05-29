<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLitePagerDirtyPageCacheSpillPlan.php';
require_once __DIR__ . '/../src/SQLitePagerSavepointMasterCacheSpillCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLitePagerSavepointMasterCacheSpillCurrentSourceNextPlan;

$pageSize = 512;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$plan = SQLitePagerSavepointMasterCacheSpillCurrentSourceNextPlan::plan(
    '/srv/wp-content/database/wp-options.sqlite',
    '/srv/wp-content/database/wp-options.sqlite-mj',
    'wp_options_import_batch',
    $pageSize,
    [
        1 => $page('wp next144 base sqlite header'),
        2 => $page('wp next144 base wp_options root'),
        3 => $page('wp next144 base autoload index'),
        4 => $page('wp next144 base transient rows'),
    ],
    [
        1 => $page('wp next144 recovered sqlite header current source'),
        2 => $page('wp next144 recovered wp_options root current source'),
    ],
    [
        ['page' => 2, 'image' => $page('wp next144 imported active_plugins spilled page'), 'dirty' => true, 'journaled' => true, 'source_id' => 'wp-next144-source', 'epoch' => 3],
        ['page' => 3, 'image' => $page('wp next144 imported autoload index spilled page'), 'dirty' => true, 'journaled' => true, 'source_id' => 'wp-next144-source', 'epoch' => 3],
    ],
    6,
    4,
    'wp-next144-source',
    3,
    true,
    true,
    [2, 3],
    'reserved',
    2,
);

assert($plan['status'] === 'pager-savepoint-master-cache-spill-current-source-next144');
assert($plan['spilled_page_numbers'] === [2, 3]);
assert($plan['rollback_reads'][0]['restored_from_savepoint_before_image'] === true);
assert($plan['release_page_numbers'] === [2, 3]);

echo json_encode([
    'status' => $plan['status'],
    'spilled' => $plan['spilled_page_numbers'],
    'rollbackRestored' => array_column($plan['rollback_reads'], 'restored_from_savepoint_before_image'),
    'dependencies' => $plan['dependencies'],
], JSON_PRETTY_PRINT) . PHP_EOL;
