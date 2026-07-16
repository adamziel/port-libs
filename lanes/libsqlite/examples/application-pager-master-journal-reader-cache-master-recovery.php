<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-options-master-recovery.sqlite';
$journalPath = $databasePath . '-journal';
$masterPath = $databasePath . '-mj';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$before = [
    1 => $page('wp master recovery stale schema reader page'),
    2 => $page('wp master recovery stale active_plugins reader page'),
    3 => $page('wp master recovery stale autoload index reader page'),
    4 => $page('wp master recovery unchanged transient reader page'),
];

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::masterJournalRecoveryReaderCachePlan(
    $databasePath,
    $journalPath,
    $masterPath,
    $journalPath . "\n/srv/wp-content/database/site.sqlite-journal\n",
    implode('', $before),
    $pageSize,
    [
        1 => $page('wp master recovery recovered schema reader page'),
        2 => $page('wp master recovery recovered active_plugins reader page'),
        3 => $page('wp master recovery recovered autoload index reader page'),
    ],
    [
        1 => ['image' => $page('wp master recovery recovered schema reader page'), 'source_id' => 'wp-master-recovery-source', 'epoch' => 5, 'reader_generation' => 2, 'source' => 'reader-cache-after-recovery'],
        2 => ['image' => $before[2], 'source_id' => 'wp-master-recovery-source', 'epoch' => 5, 'reader_generation' => 2],
        3 => ['image' => $before[3], 'source_id' => 'wp-master-recovery-source', 'epoch' => 5, 'reader_generation' => 2, 'pinned' => true],
        4 => ['image' => $before[4], 'source_id' => 'old-wp-master-recovery-source', 'epoch' => 5, 'reader_generation' => 2],
    ],
    [1, 2, 3, 4],
    'wp-master-recovery-source',
    5,
    2
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['canonical_status'] === 'pager_master_journal_reader_cache_master_recovery');
    assert($plan['status'] === 'pager_master_journal_reader_cache_current_source_next155');
    assert($plan['retained_cache_page_numbers'] === [1]);
    assert($plan['refreshed_cache_page_numbers'] === [2]);
    assert($plan['invalidated_cache_page_numbers'] === [3, 4]);
    assert($plan['next_reads'][1]['cache_hit'] === true);
    assert($plan['next_reads'][2]['cache_hit'] === false);
    echo "application-pager-master-journal-reader-cache-master-recovery self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['canonical_status'],
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
