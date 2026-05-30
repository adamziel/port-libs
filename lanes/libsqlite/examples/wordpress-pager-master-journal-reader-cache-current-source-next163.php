<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$pageSize = 96;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$database = '/tmp/wp-content/database/.ht.sqlite';
$members = [
    $database . '-journal',
    '/tmp/wp-content/database/wp_options_meta.sqlite-journal',
];
$currentPages = [
    1 => $page('wp schema root recovered current'),
    2 => $page('wp active_plugins recovered current'),
    3 => $page('wp plugin settings recovered current'),
];
$nextPages = [
    1 => $currentPages[1],
    2 => $page('wp active_plugins next committed'),
    3 => $currentPages[3],
];

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::planMasterJournalMemberDigestRefreshFence(
    $database,
    '/tmp/wp-content/database/.ht.sqlite-mj163',
    $database . "-journal\n/tmp/wp-content/database/old-cache.sqlite-journal\n",
    implode("\n", $members) . "\n",
    $pageSize,
    $currentPages,
    $nextPages,
    [
        1 => ['image' => $currentPages[1], 'source_id' => 'master-reader-current:163', 'epoch' => 163, 'master_members' => $members],
        2 => ['image' => $currentPages[2], 'source_id' => 'master-reader-current:163', 'epoch' => 163, 'master_members' => $members],
        3 => ['image' => $currentPages[3], 'source_id' => 'master-reader-current:163', 'epoch' => 163, 'dirty' => true, 'master_members' => $members],
    ],
    [1, 2, 3],
    'master-reader-current:163',
    'master-reader-next:163',
    163,
    164
);

$summary = [
    'scenario' => 'wordpress-pager-master-journal-reader-cache-current-source-next163',
    'wordpressUse' => 'A copied WordPress options reader keeps only unchanged recovered pages in cache, reads changed active_plugins from the next source, and reports dirty plugin settings cache before another reader observes it.',
    'status' => $plan['status'],
    'readCacheHits' => $plan['read_cache_hits'],
    'readSources' => $plan['read_sources'],
    'readReasons' => $plan['read_reasons'],
    'blockedPages' => $plan['blocked_page_numbers'],
    'dependencyClosure' => 'no new support component needed; reuses native master-journal member parsing and pager-cache current-to-next digest fencing',
];

if (
    $summary['status'] !== 'pager_master_journal_reader_cache_current_source_next163'
    || $summary['readCacheHits'] !== [1 => true, 2 => false, 3 => false]
    || $summary['readReasons'][2] !== 'next_source_page_changed_after_master_journal_recovery'
    || $summary['blockedPages'] !== [3]
) {
    fwrite(STDERR, "unexpected pager master-journal reader-cache current-source next163 summary\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
