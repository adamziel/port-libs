<?php

declare(strict_types=1);

require __DIR__ . '/../src/SQLitePagerMasterJournalWalCacheCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLitePagerMasterJournalWalCacheCurrentSourceNextPlan;

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-options-next129.sqlite';
$masterPath = '/srv/wp-content/database/wp-options-next129.sqlite-mj';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$database = implode('', [
    $page('wp next129 stale header before master rollback'),
    $page('wp next129 stale wp_options root before master rollback'),
    $page('wp next129 stale autoload index before master rollback'),
    $page('wp next129 comments page untouched by import'),
    $page('wp next129 stale transient payload before master rollback'),
]);

$plan = SQLitePagerMasterJournalWalCacheCurrentSourceNextPlan::plan(
    $databasePath,
    $masterPath,
    $databasePath . "-journal\n",
    $database,
    $pageSize,
    [
        1 => $page('wp next129 recovered sqlite header current source'),
        2 => $page('wp next129 recovered wp_options root current source'),
        3 => $page('wp next129 recovered autoload index current source'),
        5 => $page('wp next129 recovered transient payload current source'),
    ],
    [
        2 => ['image' => $page('wp next129 stale wp_options root before master rollback'), 'frame' => 31],
        3 => ['image' => $page('wp next129 recovered autoload index current source'), 'source' => 'wal-cache-after-recovery', 'frame' => 32],
        5 => ['image' => $page('wp next129 stale transient payload before master rollback'), 'dirty' => true, 'frame' => 33],
    ],
    [
        2 => $page('wp next129 retry append wp_options root after cache refresh'),
        5 => $page('wp next129 retry append transient payload after cache refresh'),
    ],
    [1, 2, 3, 5],
);

$summary = [
    'scenario' => 'wordpress-pager-master-journal-wal-cache-current-source-next129',
    'wordpressUse' => 'After a copied WordPress SQLite database recovers through a master journal, stale WAL page-cache entries for wp_options pages are refreshed before retry appends or checkpoint writes use them as the current source.',
    'status' => $plan['status'],
    'currentSourceVerified' => $plan['current_source_verified'],
    'staleCachePages' => $plan['stale_cache_page_numbers'],
    'refreshedCachePages' => $plan['refreshed_cache_page_numbers'],
    'retainedCachePages' => $plan['retained_cache_page_numbers'],
    'checkpointPages' => $plan['checkpoint_page_numbers'],
    'finalSources' => $plan['final_sources'],
    'dependencyClosure' => 'no new support component needed; this composes existing native PHP master-journal recovery, WAL cache current-source tracking, and checkpoint/write planning evidence',
];

if (($argv[1] ?? '') === '--self-test') {
    assert($summary['status'] === 'pager-master-journal-wal-cache-current-source-next129');
    assert($summary['currentSourceVerified'] === true);
    assert($summary['staleCachePages'] === [2, 5]);
    assert($summary['refreshedCachePages'] === [2, 5]);
    assert($summary['finalSources'][2] === 'wal-append-after-master-cache-refresh');
    echo "wordpress-pager-master-journal-wal-cache-current-source-next129 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
