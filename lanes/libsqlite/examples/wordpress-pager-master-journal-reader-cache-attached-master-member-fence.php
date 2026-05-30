<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next169.sqlite';
$mainJournal = $database . '-journal';
$metaJournal = '/srv/wp-content/database/wp-next169-site-meta.sqlite-journal';
$pluginJournal = '/srv/wp-content/database/wp-next169-plugin.sqlite-journal';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::planReaderCacheAttachedMasterMemberFence(
    $database,
    $database . '-mj',
    $mainJournal . "\n" . $metaJournal . "\n" . $pluginJournal . "\n",
    $pageSize,
    [
        $mainJournal => ['generation' => 12, 'recovered' => true, 'hot' => false, 'deleted' => true],
        $metaJournal => ['generation' => 8, 'recovered' => true, 'hot' => false, 'deleted' => true],
        $pluginJournal => ['generation' => 4, 'recovered' => true, 'hot' => false, 'deleted' => true],
    ],
    [
        1 => $page('wp next169 recovered schema after attached master journal'),
        2 => $page('wp next169 recovered active_plugins after attached master journal'),
        3 => $page('wp next169 recovered site meta after attached master journal'),
    ],
    [
        1 => ['reader_id' => 'schema-reader', 'image' => $page('wp next169 recovered schema after attached master journal'), 'source_id' => 'wp-next169-current', 'epoch' => 9, 'member_journal' => $mainJournal, 'member_generation' => 12],
        2 => ['reader_id' => 'active-reader', 'image' => $page('wp next169 stale active_plugins before attached recovery'), 'source_id' => 'wp-next169-current', 'epoch' => 9, 'member_journal' => $mainJournal, 'member_generation' => 12, 'pinned' => true],
        3 => ['reader_id' => 'site-meta-reader', 'image' => $page('wp next169 recovered site meta after attached master journal'), 'source_id' => 'wp-next169-current', 'epoch' => 9, 'member_journal' => $metaJournal, 'member_generation' => 7],
    ],
    [
        ['reader_id' => 'schema-reader', 'page_number' => 1, 'member_journal' => $mainJournal],
        ['reader_id' => 'active-reader', 'page_number' => 2, 'member_journal' => $mainJournal],
        ['reader_id' => 'site-meta-reader', 'page_number' => 3, 'member_journal' => $metaJournal],
    ],
    'wp-next169-current',
    9,
);

$summary = [
    'status' => $plan['status'],
    'retained' => $plan['retained_cache_page_numbers'],
    'invalidated' => $plan['invalidated_cache_page_numbers'],
    'activePluginsPrefix' => $plan['read_prefixes']['active-reader'],
    'siteMetaReason' => $plan['invalidated_reasons'][3],
    'wordpressUse' => 'A copied WordPress multisite import reuses only reader-cache pages whose attached rollback-journal member was fully recovered and deleted by the current master journal.',
];

if (
    $summary['status'] !== 'pager-master-journal-reader-cache-current-source-next169'
    || $summary['retained'] !== [1]
    || $summary['invalidated'] !== [2, 3]
    || $summary['activePluginsPrefix'] !== 'wp next169 recovered active_plugins after attached master journal'
    || $summary['siteMetaReason'] !== 'reader_cache_member_generation_not_current'
) {
    throw new RuntimeException('WordPress pager master-journal reader cache next169 smoke failed');
}

echo json_encode($summary, JSON_PRETTY_PRINT) . PHP_EOL;
