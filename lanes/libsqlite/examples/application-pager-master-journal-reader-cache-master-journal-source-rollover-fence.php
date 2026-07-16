<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next176.sqlite';
$master = '/srv/wp-content/database/wp-next176.sqlite-mj';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$currentMembers = [
    $database . '-journal',
    '/srv/wp-content/database/wp-next176-old-plugin.sqlite-journal',
];
$nextMembers = [
    $database . '-journal',
    '/srv/wp-content/database/wp-next176-new-plugin.sqlite-journal',
    '/srv/wp-content/database/wp-next176-users.sqlite-journal',
];
$currentDigest = hash('sha256', implode("\n", $currentMembers));
$nextDigest = hash('sha256', implode("\n", $nextMembers));

$currentPages = [
    1 => $page('wp next176 schema from current master source'),
    2 => $page('wp next176 active_plugins from current master source'),
    3 => $page('wp next176 rewrite rules from current master source'),
];
$nextPages = [
    1 => $currentPages[1],
    2 => $currentPages[2],
    3 => $page('wp next176 rewrite rules from next master source'),
];

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantMasterJournalSourceRolloverFence(
    $database,
    $master,
    implode("\n", $currentMembers) . "\n",
    implode("\n", $nextMembers) . "\n",
    $pageSize,
    $currentPages,
    $nextPages,
    [
        1 => ['image' => $currentPages[1], 'source_id' => 'wp-next176-current-source', 'epoch' => 176, 'reader_id' => 'schema-reader', 'master_digest' => $currentDigest, 'master_members' => $currentMembers, 'shared' => true],
        2 => ['image' => $nextPages[2], 'source_id' => 'wp-next176-next-source', 'epoch' => 177, 'reader_id' => 'active-plugins-next-reader', 'master_digest' => $nextDigest, 'master_members' => $nextMembers],
        3 => ['image' => $currentPages[3], 'source_id' => 'wp-next176-current-source', 'epoch' => 176, 'reader_id' => 'rewrite-reader', 'master_digest' => $currentDigest, 'master_members' => $currentMembers],
    ],
    [
        ['reader_id' => 'schema-current', 'page_number' => 1, 'source_id' => 'wp-next176-current-source', 'epoch' => 176, 'master_digest' => $currentDigest, 'phase' => 'current'],
        ['reader_id' => 'schema-next', 'page_number' => 1, 'source_id' => 'wp-next176-next-source', 'epoch' => 177, 'master_digest' => $nextDigest, 'phase' => 'next'],
        ['reader_id' => 'active-next', 'page_number' => 2, 'source_id' => 'wp-next176-next-source', 'epoch' => 177, 'master_digest' => $nextDigest, 'phase' => 'next'],
        ['reader_id' => 'rewrite-next', 'page_number' => 3, 'source_id' => 'wp-next176-next-source', 'epoch' => 177, 'master_digest' => $nextDigest, 'phase' => 'next'],
    ],
    'wp-next176-current-source',
    176,
    'wp-next176-next-source',
    177,
);

$summary = [
    'scenario' => 'application-pager-master-journal-reader-cache-master-journal-source-rollover-fence',
    'status' => $plan['status'],
    'schemaCurrentHit' => $plan['read_cache_hits']['schema-current'],
    'schemaNextHit' => $plan['read_cache_hits']['schema-next'],
    'activeNextHit' => $plan['read_cache_hits']['active-next'],
    'rewriteNextPrefix' => $plan['read_prefixes']['rewrite-next'],
    'reopenReaders' => $plan['reopen_reader_ids'],
    'applicationUse' => 'A copied Application SQLite database must reopen schema/rewrite readers when a second master-journal source supersedes the current source, even if a page image still matches; only cache entries ticketed to the next source may be reused.',
    'dependencyClosure' => 'no new support component needed; this reuses lane-local master-journal member parsing and pager reader-cache ticketing.',
];

if (
    $summary['status'] !== 'pager-master-journal-reader-cache-current-source-next176'
    || $summary['schemaCurrentHit'] !== true
    || $summary['schemaNextHit'] !== false
    || $summary['activeNextHit'] !== true
    || $summary['rewriteNextPrefix'] !== 'wp next176 rewrite rules from next master source'
    || $summary['reopenReaders'] !== ['schema-next', 'rewrite-next']
) {
    fwrite(STDERR, "application-pager-master-journal-reader-cache-master-journal-source-rollover-fence self-test failed\n");
    exit(1);
}

echo "application-pager-master-journal-reader-cache-master-journal-source-rollover-fence self-test passed\n";
