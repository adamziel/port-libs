<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next179.sqlite';
$master = '/srv/wp-content/database/wp-next179.sqlite-mj';
$source = 'wp-next179-canonical-source';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$canonicalMap = [
    '../database/wp-next179.sqlite-journal' => $database . '-journal',
    '/mnt/wp-alias/wp-next179-users.sqlite-journal' => '/srv/wp-content/database/wp-next179-users.sqlite-journal',
];
$members = [
    '../database/wp-next179.sqlite-journal',
    '/mnt/wp-alias/wp-next179-users.sqlite-journal',
];
$canonicalDigest = hash('sha256', implode("\n", [
    '/srv/wp-content/database/wp-next179-users.sqlite-journal',
    $database . '-journal',
]));
$pages = [
    1 => $page('wp next179 schema page after canonical master read'),
    2 => $page('wp next179 active_plugins page after canonical master read'),
    3 => $page('wp next179 plugin settings after canonical master read'),
];

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::canonicalMemberPathReaderCachePlan(
    $database,
    $master,
    implode("\n", $members) . "\n",
    $canonicalMap,
    $pageSize,
    $pages,
    [
        1 => ['reader_id' => 'schema-reader', 'image' => $pages[1], 'source_id' => $source, 'epoch' => 179, 'master_members' => array_reverse($members), 'canonical_digest' => $canonicalDigest, 'shared' => true],
        2 => ['reader_id' => 'active-reader', 'image' => $page('wp next179 stale active_plugins alias cache'), 'source_id' => $source, 'epoch' => 179, 'master_members' => $members, 'canonical_digest' => $canonicalDigest],
        3 => ['reader_id' => 'settings-reader', 'image' => $pages[3], 'source_id' => 'old-source', 'epoch' => 179, 'master_members' => $members, 'canonical_digest' => $canonicalDigest],
    ],
    [
        ['reader_id' => 'schema-read', 'page_number' => 1, 'source_id' => $source, 'epoch' => 179, 'canonical_digest' => $canonicalDigest],
        ['reader_id' => 'active-read', 'page_number' => 2, 'source_id' => $source, 'epoch' => 179, 'canonical_digest' => $canonicalDigest],
        ['reader_id' => 'settings-read', 'page_number' => 3, 'source_id' => $source, 'epoch' => 179, 'canonical_digest' => $canonicalDigest],
    ],
    [
        2 => $page('wp next179 rewritten active_plugins after canonical fence'),
    ],
    $source,
    179,
);

$summary = [
    'scenario' => 'application-pager-master-journal-reader-cache-current-source-next179',
    'status' => $plan['status'],
    'canonicalMembers' => $plan['canonical_members'],
    'schemaHit' => $plan['read_cache_hits']['schema-read'],
    'activeHitAfterRefresh' => $plan['read_cache_hits']['active-read'],
    'settingsHit' => $plan['read_cache_hits']['settings-read'],
    'activeWriteBefore' => $plan['writes'][0]['before_prefix'],
    'reopenReaders' => $plan['reopen_reader_ids'],
    'applicationUse' => 'A copied Application SQLite database can retain or refresh reader-cache pages when master-journal members are reached through VFS aliases, but only after canonical path resolution proves the same attached rollback journals.',
    'dependencyClosure' => 'no new support component needed; this reuses lane-local master-journal reader-cache planning with a bounded VFS canonical pathname map.',
];

if (
    $summary['status'] !== 'pager-master-journal-reader-cache-current-source-next179'
    || $summary['schemaHit'] !== true
    || $summary['activeHitAfterRefresh'] !== true
    || $summary['settingsHit'] !== false
    || $summary['activeWriteBefore'] !== 'wp next179 active_plugins page after canonical master read'
    || $summary['reopenReaders'] !== ['settings-read']
) {
    fwrite(STDERR, "application-pager-master-journal-reader-cache-current-source-next179 self-test failed\n");
    exit(1);
}

echo "application-pager-master-journal-reader-cache-current-source-next179 self-test passed\n";
