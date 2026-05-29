<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNext155Plan.php';

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNext155Plan;

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-options-next155.sqlite';
$journalPath = $databasePath . '-journal';
$masterPath = $databasePath . '-mj';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$before = [
    1 => $page('wp next155 stale schema reader page'),
    2 => $page('wp next155 stale active_plugins reader page'),
    3 => $page('wp next155 stale autoload index reader page'),
    4 => $page('wp next155 unchanged transient reader page'),
];

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNext155Plan::plan(
    $databasePath,
    $journalPath,
    $masterPath,
    $journalPath . "\n/srv/wp-content/database/site.sqlite-journal\n",
    implode('', $before),
    $pageSize,
    [
        1 => $page('wp next155 recovered schema reader page'),
        2 => $page('wp next155 recovered active_plugins reader page'),
        3 => $page('wp next155 recovered autoload index reader page'),
    ],
    [
        1 => ['image' => $page('wp next155 recovered schema reader page'), 'source_id' => 'wp-next155-source', 'epoch' => 5, 'reader_generation' => 2, 'source' => 'reader-cache-after-recovery'],
        2 => ['image' => $before[2], 'source_id' => 'wp-next155-source', 'epoch' => 5, 'reader_generation' => 2],
        3 => ['image' => $before[3], 'source_id' => 'wp-next155-source', 'epoch' => 5, 'reader_generation' => 2, 'pinned' => true],
        4 => ['image' => $before[4], 'source_id' => 'old-wp-next155-source', 'epoch' => 5, 'reader_generation' => 2],
    ],
    [1, 2, 3, 4],
    'wp-next155-source',
    5,
    2
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'pager_master_journal_reader_cache_current_source_next155');
    assert($plan['retained_cache_page_numbers'] === [1]);
    assert($plan['refreshed_cache_page_numbers'] === [2]);
    assert($plan['invalidated_cache_page_numbers'] === [3, 4]);
    assert($plan['next_reads'][1]['cache_hit'] === true);
    assert($plan['next_reads'][2]['cache_hit'] === false);
    echo "wordpress-pager-master-journal-reader-cache-current-source-next155 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'retained' => $plan['retained_cache_page_numbers'],
    'refreshed' => $plan['refreshed_cache_page_numbers'],
    'invalidated' => $plan['invalidated_cache_page_numbers'],
    'nextReads' => array_map(
        static fn (array $read): array => [
            'page' => $read['page_number'],
            'cacheHit' => $read['cache_hit'],
            'prefix' => $read['prefix'],
        ],
        $plan['next_reads']
    ),
], JSON_PRETTY_PRINT) . "\n";
