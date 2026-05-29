<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-next187.sqlite';
$masterPath = '/srv/wp-content/database/wp-next187.sqlite-mj';
$mainJournal = $databasePath . '-journal';
$metaJournal = '/srv/wp-content/database/wp-next187-meta.sqlite-journal';
$masterBytes = $mainJournal . "\n" . $metaJournal . "\n";
$members = [$mainJournal, $metaJournal];
$ordinals = [$mainJournal => 1, $metaJournal => 2];
$completeDigest = hash('sha256', $masterPath . '|' . strlen($masterBytes) . '|' . implode("\n", $members) . '|' . hash('sha256', $masterBytes));
$prefixBytes = $mainJournal . "\n";
$prefixDigest = hash('sha256', $masterPath . '|' . strlen($prefixBytes) . '|' . $mainJournal . '|' . hash('sha256', $prefixBytes));
$sourceId = 'wp-next187-current-complete-master-source';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$before = [
    1 => $page('wp next187 schema before complete master membership read'),
    2 => $page('wp next187 active_plugins before attached metadata recovery'),
    3 => $page('wp next187 plugin settings before attached metadata recovery'),
    4 => $page('wp next187 autoload index before attached metadata recovery'),
];
$current = [
    2 => $page('wp next187 active_plugins after attached metadata recovery'),
    3 => $page('wp next187 plugin settings after attached metadata recovery'),
];

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext187(
    $databasePath,
    $masterPath,
    $masterBytes,
    implode('', $before),
    $pageSize,
    [
        1 => ['label' => 'wp-schema-retained', 'image' => $before[1], 'source_id' => $sourceId, 'epoch' => 12, 'master_member_ordinals' => $ordinals, 'master_complete_read_digest' => $completeDigest, 'master_byte_length' => strlen($masterBytes)],
        2 => ['label' => 'wp-active-plugins-prefix-master-read', 'image' => $current[2], 'source_id' => $sourceId, 'epoch' => 12, 'master_member_ordinals' => [$mainJournal => 1], 'master_complete_read_digest' => $prefixDigest, 'master_byte_length' => strlen($prefixBytes)],
        3 => ['label' => 'wp-plugin-settings-refresh', 'image' => $before[3], 'source_id' => $sourceId, 'epoch' => 12, 'master_member_ordinals' => $ordinals, 'master_complete_read_digest' => $completeDigest, 'master_byte_length' => strlen($masterBytes)],
    ],
    [1, 2, 3, 4],
    $current,
    $sourceId,
    12,
);

$summary = [
    'scenario' => 'wordpress-pager-master-journal-reader-cache-current-source-next187',
    'status' => $plan['status'],
    'retainedCachePages' => $plan['retained_cache_page_numbers'],
    'refreshedCachePages' => $plan['refreshed_cache_page_numbers'],
    'invalidatedCachePages' => $plan['invalidated_cache_page_numbers'],
    'prefixReadReason' => $plan['reader_rows'][1]['reason'],
    'prefixReadMissingMembers' => $plan['reader_rows'][1]['missing_members'],
    'prefixReadCacheHit' => $plan['next_reads'][1]['cache_hit'],
    'settingsPrefix' => $plan['next_reads'][2]['prefix'],
    'wordpressUse' => 'A copied wp_options rollback reader rejects an active_plugins page cached from a prefix master-journal read before the attached metadata journal member was visible.',
    'dependencyClosure' => 'no new support component needed; this reuses lane-local pager reader-cache planning and master-journal membership parsing',
];

if ($summary['status'] !== 'pager-master-journal-reader-cache-current-source-next187'
    || $summary['retainedCachePages'] !== [1]
    || $summary['refreshedCachePages'] !== [3]
    || $summary['invalidatedCachePages'] !== [2]
    || $summary['prefixReadReason'] !== 'reader_cache_master_complete_read_digest_changed'
    || $summary['prefixReadMissingMembers'] !== [$metaJournal]
    || $summary['prefixReadCacheHit'] !== false
    || $summary['settingsPrefix'] !== 'wp next187 plugin settings after attached metadata recovery'
) {
    fwrite(STDERR, "wordpress-pager-master-journal-reader-cache-current-source-next187 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
echo "wordpress-pager-master-journal-reader-cache-current-source-next187 self-test passed\n";
