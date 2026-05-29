<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-next184.sqlite';
$masterPath = '/srv/wp-content/database/wp-next184.sqlite-mj';
$masterBytes = $databasePath . "-journal\n/srv/wp-content/database/wp-next184-meta.sqlite-journal\n";
$members = [$databasePath . '-journal', '/srv/wp-content/database/wp-next184-meta.sqlite-journal'];
$stat = ['device' => 2050, 'inode' => 78184, 'size' => strlen($masterBytes), 'mtime' => 184001, 'ctime' => 184002, 'generation' => 'wp-master-current-generation', 'readOffset' => 0, 'readLength' => strlen($masterBytes)];
$token = hash('sha256', implode('|', [
    $masterPath,
    (string) $stat['device'],
    (string) $stat['inode'],
    (string) $stat['generation'],
    $stat['size'],
    $stat['mtime'],
    $stat['ctime'],
    $stat['readOffset'],
    $stat['readLength'],
    implode("\n", $members),
    hash('sha256', $masterBytes),
]));
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$before = [
    1 => $page('wp next184 schema page after current master source'),
    2 => $page('wp next184 active_plugins before recreated master source'),
    3 => $page('wp next184 plugin settings before recreated master source'),
    4 => $page('wp next184 comments unchanged before recreated master source'),
];
$current = [
    2 => $page('wp next184 active_plugins after recreated master source'),
    3 => $page('wp next184 plugin settings after recreated master source'),
];
$sourceId = 'wp-next184-current-master-token-source';

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext184(
    $databasePath,
    $masterPath,
    $masterBytes,
    $stat,
    implode('', $before),
    $pageSize,
    [
        1 => ['label' => 'wp-schema-retained', 'image' => $before[1], 'source_id' => $sourceId, 'epoch' => 9, 'master_members' => $members, 'master_read_token' => $token, 'master_generation' => 'wp-master-current-generation', 'master_size' => strlen($masterBytes)],
        2 => ['label' => 'wp-active-plugins-old-master-file', 'image' => $current[2], 'source_id' => $sourceId, 'epoch' => 9, 'master_members' => $members, 'master_read_token' => hash('sha256', 'old-unlinked-master'), 'master_generation' => 'wp-master-previous-generation', 'master_size' => strlen($masterBytes)],
        3 => ['label' => 'wp-plugin-settings-refresh', 'image' => $before[3], 'source_id' => $sourceId, 'epoch' => 9, 'master_members' => $members, 'master_read_token' => $token, 'master_generation' => 'wp-master-current-generation', 'master_size' => strlen($masterBytes)],
    ],
    [1, 2, 3, 4],
    $current,
    $sourceId,
    9,
);

$summary = [
    'scenario' => 'wordpress-pager-master-journal-reader-cache-current-source-next184',
    'status' => $plan['status'],
    'masterReadToken' => $plan['current_master_read_token'],
    'retainedCachePages' => $plan['retained_cache_page_numbers'],
    'refreshedCachePages' => $plan['refreshed_cache_page_numbers'],
    'invalidatedCachePages' => $plan['invalidated_cache_page_numbers'],
    'recreatedMasterReason' => $plan['reader_rows'][1]['reason'],
    'recreatedMasterCacheHit' => $plan['next_reads'][1]['cache_hit'],
    'refreshedSettingsPrefix' => $plan['next_reads'][2]['prefix'],
    'wordpressUse' => 'A copied wp_options rollback reader rejects cache pages pinned to an unlinked/recreated master-journal sidecar even when the member list text is unchanged.',
    'dependencyClosure' => 'no new support component needed; this reuses native pager reader-cache source tracking and master-journal membership parsing',
];

if ($summary['status'] !== 'pager-master-journal-reader-cache-current-source-next184'
    || $summary['retainedCachePages'] !== [1]
    || $summary['refreshedCachePages'] !== [3]
    || $summary['invalidatedCachePages'] !== [2]
    || $summary['recreatedMasterReason'] !== 'reader_cache_master_read_token_changed'
    || $summary['recreatedMasterCacheHit'] !== false
    || $summary['refreshedSettingsPrefix'] !== 'wp next184 plugin settings after recreated master source'
) {
    fwrite(STDERR, "wordpress-pager-master-journal-reader-cache-current-source-next184 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
echo "wordpress-pager-master-journal-reader-cache-current-source-next184 self-test passed\n";
