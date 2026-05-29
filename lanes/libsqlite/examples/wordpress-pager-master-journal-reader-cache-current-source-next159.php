<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-next159.sqlite';
$masterPath = '/srv/wp-content/database/wp-next159.sqlite-mj';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$before = [
    1 => $page('wp next159 stale schema before current master journal'),
    2 => $page('wp next159 stale active_plugins before current master journal'),
    3 => $page('wp next159 stale plugin settings before current master journal'),
];
$recovered = [
    1 => $page('wp next159 recovered schema from current master journal'),
    2 => $page('wp next159 recovered active_plugins from current master journal'),
    3 => $page('wp next159 recovered plugin settings from current master journal'),
];
$sourceBefore = 'wp-next159-before-current-master-source';

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::currentSourceReaderCacheRebaseWithWrites(
    $databasePath,
    $masterPath,
    $databasePath . "-journal\n/srv/wp-content/database/old-plugin-next159.sqlite-journal\n",
    $databasePath . "-journal\n/srv/wp-content/database/current-plugin-next159.sqlite-journal\n",
    implode('', $before),
    $pageSize,
    $recovered,
    [
        1 => ['label' => 'wp-schema-reader-cache', 'image' => $recovered[1], 'source_id' => $sourceBefore, 'epoch' => 3],
        2 => ['label' => 'wp-pinned-active-plugins', 'image' => $before[2], 'source_id' => $sourceBefore, 'epoch' => 3, 'pinned' => true],
        3 => ['label' => 'wp-dirty-plugin-settings', 'image' => $before[3], 'source_id' => $sourceBefore, 'epoch' => 3, 'dirty' => true],
    ],
    [1, 2, 3],
    [2 => $page('wp next159 active_plugins rewrite after current master reader cache')],
    $sourceBefore,
    3,
);

$summary = [
    'scenario' => 'wordpress-pager-master-journal-reader-cache-current-source-next159',
    'status' => $plan['status'],
    'cachedMembershipStale' => $plan['cached_membership_stale'],
    'retainedCachePages' => $plan['retained_cache_page_numbers'],
    'invalidatedCachePages' => $plan['invalidated_cache_page_numbers'],
    'requiresReaderReopen' => $plan['requires_reader_reopen'],
    'nextWriteBeforePrefix' => $plan['next_writes'][0]['before_prefix'],
    'wordpressUse' => 'A copied wp_options recovery can discard stale pinned reader-cache pages after re-reading the current master-journal member list, then journal the next active_plugins write from the recovered current source.',
    'dependencyClosure' => 'no new support component needed; this reuses lane-local pager master-journal and reader-cache current-source primitives',
];

if ($summary['status'] !== 'pager-master-journal-reader-cache-current-source-next159'
    || $summary['cachedMembershipStale'] !== true
    || $summary['retainedCachePages'] !== [1]
    || $summary['invalidatedCachePages'] !== [2, 3]
    || $summary['requiresReaderReopen'] !== true
    || $summary['nextWriteBeforePrefix'] !== 'wp next159 recovered active_plugins from current master journal'
) {
    fwrite(STDERR, "wordpress-pager-master-journal-reader-cache-current-source-next159 self-test failed\n");
    exit(1);
}

echo "wordpress-pager-master-journal-reader-cache-current-source-next159 self-test passed\n";
echo json_encode($summary, JSON_PRETTY_PRINT) . "\n";
