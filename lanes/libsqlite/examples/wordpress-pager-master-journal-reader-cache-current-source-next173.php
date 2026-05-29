<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next173.sqlite';
$master = '/srv/wp-content/database/wp-next173.sqlite-mj';
$members = [
    $database . '-journal',
    '/srv/wp-content/database/wp-next173-site.sqlite-journal',
    '/srv/wp-content/database/wp-next173-users.sqlite-journal',
];
$masterDigest = hash('sha256', implode("\n", $members));
$sourceId = 'wp-next173-current-source-after-master-read';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$pages = [
    1 => $page('wp next173 schema after fresh master membership'),
    2 => $page('wp next173 active_plugins after fresh master membership'),
    3 => $page('wp next173 user roles after fresh master membership'),
];

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext173(
    $database,
    $master,
    implode("\n", $members) . "\n",
    $pageSize,
    $pages,
    [
        1 => ['image' => $pages[1], 'source_id' => $sourceId, 'epoch' => 173, 'reader_id' => 'schema-reader', 'master_digest' => $masterDigest, 'master_members' => $members, 'shared' => true],
        2 => ['image' => $page('wp next173 stale active_plugins before membership refresh'), 'source_id' => $sourceId, 'epoch' => 173, 'reader_id' => 'active-reader', 'master_digest' => $masterDigest, 'master_members' => $members],
        3 => ['image' => $pages[3], 'source_id' => $sourceId, 'epoch' => 173, 'reader_id' => 'roles-reader', 'master_digest' => '', 'master_members' => [$members[0], $members[1]]],
    ],
    [
        ['reader_id' => 'schema-reader', 'page_number' => 1, 'source_id' => $sourceId, 'epoch' => 173, 'master_digest' => $masterDigest],
        ['reader_id' => 'active-reader', 'page_number' => 2, 'source_id' => $sourceId, 'epoch' => 173, 'master_digest' => $masterDigest],
        ['reader_id' => 'roles-reader', 'page_number' => 3, 'source_id' => $sourceId, 'epoch' => 173, 'master_digest' => $masterDigest],
    ],
    $sourceId,
    173,
);

$summary = [
    'scenario' => 'wordpress-pager-master-journal-reader-cache-current-source-next173',
    'status' => $plan['status'],
    'retainedPages' => $plan['retained_page_numbers'],
    'refreshedPages' => $plan['refreshed_page_numbers'],
    'invalidatedPages' => $plan['invalidated_page_numbers'],
    'rolesReason' => $plan['invalidated_reasons'][3],
    'schemaCacheHit' => $plan['read_cache_hits']['schema-reader'],
    'activePluginsPrefix' => $plan['read_prefixes']['active-reader'],
    'wordpressUse' => 'A copied wp_options reader can retain a shared schema page, refresh active_plugins from current source, and reopen a stale roles reader when the freshly read master journal gained an attached users journal.',
    'dependencyClosure' => 'no new support component needed; this reuses lane-local pager master-journal membership parsing and reader-cache ticket primitives',
];

if (
    $summary['status'] !== 'pager-master-journal-reader-cache-current-source-next173'
    || $summary['retainedPages'] !== [1]
    || $summary['refreshedPages'] !== [2]
    || $summary['invalidatedPages'] !== [3]
    || $summary['rolesReason'] !== 'reader_cache_master_journal_membership_mismatch'
    || $summary['schemaCacheHit'] !== true
    || $summary['activePluginsPrefix'] !== 'wp next173 active_plugins after fresh master membership'
) {
    fwrite(STDERR, "wordpress-pager-master-journal-reader-cache-current-source-next173 self-test failed\n");
    exit(1);
}

echo "wordpress-pager-master-journal-reader-cache-current-source-next173 self-test passed\n";
echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
