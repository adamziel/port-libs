<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-next178.sqlite';
$masterPath = '/srv/wp-content/database/wp-next178.sqlite-mj';
$mainJournal = $databasePath . '-journal';
$usersJournal = '/srv/wp-content/database/wp-next178-users.sqlite-journal';
$masterBytes = $mainJournal . "\n" . $usersJournal . "\n";
$masterDigest = hash('sha256', $mainJournal . "\n" . $usersJournal);
$sourceId = 'wp-next178-current-source';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$before = [
    1 => $page('wp next178 stale schema before member recovery'),
    2 => $page('wp next178 stale active_plugins before member recovery'),
    3 => $page('wp next178 stale plugin settings before member recovery'),
    4 => $page('wp next178 stale users before member recovery'),
];
$recovered = [
    1 => $page('wp next178 recovered schema after member recovery'),
    2 => $page('wp next178 recovered active_plugins after member recovery'),
    3 => $page('wp next178 recovered plugin settings after member recovery'),
    4 => $page('wp next178 recovered users after member recovery'),
];

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext178(
    $databasePath,
    $masterPath,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    [
        1 => ['reader_id' => 'schema-reader', 'image' => $recovered[1], 'source_id' => $sourceId, 'epoch' => 9, 'member_journal' => $mainJournal, 'member_generation' => 5, 'master_digest' => $masterDigest],
        2 => ['reader_id' => 'active-reader', 'image' => $before[2], 'source_id' => $sourceId, 'epoch' => 9, 'member_journal' => $mainJournal, 'member_generation' => 5, 'master_digest' => $masterDigest],
        4 => ['reader_id' => 'users-reader', 'image' => $recovered[4], 'source_id' => $sourceId, 'epoch' => 9, 'member_journal' => $usersJournal, 'member_generation' => 2, 'master_digest' => $masterDigest],
    ],
    [
        $mainJournal => ['generation' => 5, 'deleted' => true, 'recovered' => true],
        $usersJournal => ['generation' => 2, 'deleted' => false, 'recovered' => true],
    ],
    [1, 2, 3, 4],
    [
        2 => $page('wp next178 rewritten active_plugins after member generation fence'),
    ],
    $sourceId,
    9,
);

$summary = [
    'scenario' => 'wordpress-pager-master-journal-reader-cache-current-source-next178',
    'status' => $plan['status'],
    'memberRows' => $plan['member_rows'],
    'retainedCachePages' => $plan['retained_page_numbers'],
    'refreshedCachePages' => $plan['refreshed_page_numbers'],
    'invalidatedCachePages' => $plan['invalidated_page_numbers'],
    'usersReason' => $plan['cache_rows'][2]['reason'],
    'activeWriteBefore' => $plan['next_writes'][0]['before_prefix'],
    'wordpressUse' => 'A copied WordPress attached-database recovery reuses wp_options reader-cache pages only after the current master-journal member generation is recovered and its rollback journal has been deleted.',
    'dependencyClosure' => 'no new support component needed; this reuses lane-local pager master-journal and reader-cache source tracking primitives',
];

if ($summary['status'] !== 'pager-master-journal-reader-cache-current-source-next178'
    || $summary['retainedCachePages'] !== [1]
    || $summary['refreshedCachePages'] !== [2]
    || $summary['invalidatedCachePages'] !== [4]
    || $summary['usersReason'] !== 'reader_cache_member_journal_not_deleted'
    || $summary['activeWriteBefore'] !== 'wp next178 recovered active_plugins after member recovery'
) {
    fwrite(STDERR, "wordpress-pager-master-journal-reader-cache-current-source-next178 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
echo "wordpress-pager-master-journal-reader-cache-current-source-next178 self-test passed\n";
