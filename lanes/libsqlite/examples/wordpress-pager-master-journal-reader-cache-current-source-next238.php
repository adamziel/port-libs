<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next238.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next238-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'wp-next238-current-source';
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$members = [$mainJournal, $usersJournal];
$changeCounter = 238001;
$schemaRootDigest = hash('sha256', 'wp next238 sqlite_schema root after plugin import DDL');
$oldSchemaRootDigest = hash('sha256', 'wp next237 sqlite_schema root before plugin import DDL');
$mapDigest = static function (array $map): string {
    ksort($map, SORT_STRING);
    $parts = [];
    foreach ($map as $member => $value) {
        $parts[] = $member . '=' . $value;
    }

    return hash('sha256', implode('|', $parts));
};
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$formatPage = static function (string $label) use ($pageSize, $changeCounter): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr(4), 20, 1);
    $page = substr_replace($page, pack('N', 2), 56, 4);
    $page = substr_replace($page, pack('N', $changeCounter), 24, 4);
    $page = substr_replace($page, pack('N', 238), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503238), 68, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$before = [
    1 => $formatPage('wp next238 stale schema before root digest fence'),
    2 => $page('wp next238 stale wp_options root before root digest fence'),
    3 => $page('wp next238 stale active_plugins before root digest fence'),
];
$recovered = [
    1 => $formatPage('wp next238 current schema after root digest fence'),
    2 => $page('wp next238 current wp_options root after root digest fence'),
    3 => $page('wp next238 current active_plugins after root digest fence'),
];
$tokens = [
    $mainJournal => 'dev=8:ino=2381:size=4096:mtime=23801:generation=main-current',
    $usersJournal => 'dev=8:ino=2382:size=1024:mtime=23802:generation=users-current',
];
$headers = [
    $mainJournal => hash('sha256', 'wp main rollback header next238'),
    $usersJournal => hash('sha256', 'wp users rollback header next238'),
];
$recoveredDigest = static function (array $pages) use ($pageSize): string {
    ksort($pages, SORT_NUMERIC);
    $parts = [];
    foreach ($pages as $pageNumber => $image) {
        $parts[] = $pageNumber . ':' . hash('sha256', substr($image, 0, $pageSize));
    }

    return hash('sha256', implode('|', $parts));
};
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 238, 0x57503238]));
$masterDigest = hash('sha256', 'wp next238 master source');
$masterToken = 'dev=8:ino=2380:size=96:mtime=23800:generation=master-current';
$databaseToken = 'dev=8:ino=2389:size=1536:mtime=23899:generation=database-current';
$cleanupToken = 'master-cleanup:deleted:mtime=23900:dirsync=ok';
$readerLeaseToken = 'reader-lease:shared-cache:epoch=238:opened-after-master-cleanup';
$pagerCacheSourceToken = 'pager-cache-source:epoch=238:master-journal-recovery=complete';
$mainPathToken = 'db-path-token:main:/srv/wp-content/database/wp-next238.sqlite';
$base = [
    'source_id' => $sourceId,
    'epoch' => 238,
    'format_signature' => $formatSignature,
    'publication_generation' => 238,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 238,
    'recovered_page_set_digest' => $recoveredDigest($recovered),
    'member_journal_tokens' => $tokens,
    'member_journal_header_digests' => $headers,
    'master_member_order_digest' => hash('sha256', implode("\n", $members)),
    'master_journal_file_token' => $masterToken,
    'master_journal_bytes_digest' => hash('sha256', $masterBytes),
    'database_file_token' => $databaseToken,
    'master_journal_cleanup_token' => $cleanupToken,
    'reader_lease_token' => $readerLeaseToken,
    'pager_cache_source_token' => $pagerCacheSourceToken,
    'database_path_token' => $mainPathToken,
    'database_change_counter' => $changeCounter,
    'schema_root_digest' => $schemaRootDigest,
];
$readerCache = [
    1 => ['label' => 'schema-cache', 'reader_id' => 'schema-cache-reader', 'image' => $recovered[1]] + $base,
    2 => ['label' => 'options-root-cache', 'reader_id' => 'options-root-reader', 'image' => $before[2]] + $base,
    3 => ['label' => 'active-plugins-old-schema-root-cache', 'reader_id' => 'active-plugins-reader', 'image' => $recovered[3], 'schema_root_digest' => $oldSchemaRootDigest] + $base,
];
$read = static fn (int $pageNumber): array => [
    'reader_id' => 'read-' . $pageNumber,
    'page_number' => $pageNumber,
    'source_id' => $sourceId,
    'epoch' => 238,
    'format_signature' => $formatSignature,
    'publication_generation' => 238,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 238,
    'recovered_page_set_digest' => $recoveredDigest($recovered),
    'member_journal_token_digest' => $mapDigest($tokens),
    'member_journal_header_digest' => $mapDigest($headers),
    'master_member_order_digest' => hash('sha256', implode("\n", $members)),
    'master_journal_file_token' => $masterToken,
    'master_journal_bytes_digest' => hash('sha256', $masterBytes),
    'database_file_token' => $databaseToken,
    'master_journal_cleanup_token' => $cleanupToken,
    'reader_lease_token' => $readerLeaseToken,
    'pager_cache_source_token' => $pagerCacheSourceToken,
    'database_path_token' => $mainPathToken,
    'database_change_counter' => $changeCounter,
    'schema_root_digest' => $schemaRootDigest,
];

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext238(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    $readerCache,
    [$read(1), $read(2), $read(3)],
    $sourceId,
    238,
    238,
    $masterDigest,
    238,
    $tokens,
    $headers,
    $masterToken,
    $databaseToken,
    $cleanupToken,
    $readerLeaseToken,
    $pagerCacheSourceToken,
    $mainPathToken,
    $changeCounter,
    $schemaRootDigest,
);

$summary = [
    'scenario' => 'wordpress-pager-master-journal-reader-cache-current-source-next238',
    'wordpressUse' => 'A copied WordPress database keeps recovered schema/options reader-cache pages only when the sqlite_schema root digest matches the master-journal-recovered DDL state; an active_plugins page cached before plugin-table DDL reopens before import continues.',
    'status' => $plan['status'],
    'schemaRootDigestInvalidatedPages' => $plan['schema_root_digest_invalidated_cache_page_numbers'],
    'cacheHits' => $plan['read_cache_hits'],
    'reopenReaders' => $plan['reopen_reader_ids'],
    'dependencyClosure' => 'no new support component needed; this reuses lane-local pager master-journal reader-cache path/source/lease/change-counter tickets and adds schema-root digest admission evidence',
];

if ($summary['status'] !== 'pager-master-journal-reader-cache-current-source-next238'
    || $summary['schemaRootDigestInvalidatedPages'] !== [3]
    || $summary['cacheHits'] !== ['read-1' => true, 'read-2' => true, 'read-3' => false]
) {
    fwrite(STDERR, "wordpress-pager-master-journal-reader-cache-current-source-next238 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
echo "wordpress-pager-master-journal-reader-cache-current-source-next238 self-test passed\n";
