<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next241.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next241-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'wp-next241-current-source';
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$members = [$mainJournal, $usersJournal];
$changeCounter = 241001;
$schemaRootDigest = hash('sha256', 'wp next241 sqlite_schema root after plugin import DDL');
$schemaCookie = 24177;
$oldSchemaCookie = 24077;
$mapDigest = static function (array $map): string {
    ksort($map, SORT_STRING);
    $parts = [];
    foreach ($map as $member => $value) {
        $parts[] = $member . '=' . $value;
    }

    return hash('sha256', implode('|', $parts));
};
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$formatPage = static function (string $label) use ($pageSize, $changeCounter, $schemaCookie): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr(4), 20, 1);
    $page = substr_replace($page, pack('N', $changeCounter), 24, 4);
    $page = substr_replace($page, pack('N', $schemaCookie), 40, 4);
    $page = substr_replace($page, pack('N', 2), 56, 4);
    $page = substr_replace($page, pack('N', 241), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503241), 68, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$before = [
    1 => $formatPage('wp next241 stale schema before schema cookie fence'),
    2 => $page('wp next241 stale wp_options root before schema cookie fence'),
    3 => $page('wp next241 stale active_plugins before schema cookie fence'),
];
$recovered = [
    1 => $formatPage('wp next241 current schema after schema cookie fence'),
    2 => $page('wp next241 current wp_options root after schema cookie fence'),
    3 => $page('wp next241 current active_plugins after schema cookie fence'),
];
$tokens = [
    $mainJournal => 'dev=8:ino=2411:size=4096:mtime=24101:generation=main-current',
    $usersJournal => 'dev=8:ino=2412:size=1024:mtime=24102:generation=users-current',
];
$headers = [
    $mainJournal => hash('sha256', 'wp main rollback header next241'),
    $usersJournal => hash('sha256', 'wp users rollback header next241'),
];
$recoveredDigest = static function (array $pages) use ($pageSize): string {
    ksort($pages, SORT_NUMERIC);
    $parts = [];
    foreach ($pages as $pageNumber => $image) {
        $parts[] = $pageNumber . ':' . hash('sha256', substr($image, 0, $pageSize));
    }

    return hash('sha256', implode('|', $parts));
};
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 241, 0x57503241]));
$masterDigest = hash('sha256', 'wp next241 master source');
$masterToken = 'dev=8:ino=2410:size=96:mtime=24100:generation=master-current';
$databaseToken = 'dev=8:ino=2419:size=1536:mtime=24199:generation=database-current';
$cleanupToken = 'master-cleanup:deleted:mtime=24200:dirsync=ok';
$readerLeaseToken = 'reader-lease:shared-cache:epoch=241:opened-after-master-cleanup';
$pagerCacheSourceToken = 'pager-cache-source:epoch=241:master-journal-recovery=complete';
$mainPathToken = 'db-path-token:main:/srv/wp-content/database/wp-next241.sqlite';
$base = [
    'source_id' => $sourceId,
    'epoch' => 241,
    'format_signature' => $formatSignature,
    'publication_generation' => 241,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 241,
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
    'schema_cookie' => $schemaCookie,
];
$readerCache = [
    1 => ['label' => 'schema-cache', 'reader_id' => 'schema-cache-reader', 'image' => $recovered[1]] + $base,
    2 => ['label' => 'options-root-cache', 'reader_id' => 'options-root-reader', 'image' => $before[2]] + $base,
    3 => ['label' => 'active-plugins-old-schema-cookie-cache', 'reader_id' => 'active-plugins-reader', 'image' => $recovered[3], 'schema_cookie' => $oldSchemaCookie] + $base,
];
$read = static fn (int $pageNumber): array => [
    'reader_id' => 'read-' . $pageNumber,
    'page_number' => $pageNumber,
    'source_id' => $sourceId,
    'epoch' => 241,
    'format_signature' => $formatSignature,
    'publication_generation' => 241,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 241,
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
    'schema_cookie' => $schemaCookie,
];

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantMasterJournalReadFence(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    $readerCache,
    [$read(1), $read(2), $read(3)],
    $sourceId,
    241,
    241,
    $masterDigest,
    241,
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
    $schemaCookie,
);

$summary = [
    'scenario' => 'application-pager-master-journal-reader-cache-current-source-next241',
    'applicationUse' => 'A copied Application database keeps recovered schema/options reader-cache pages only when the page-1 schema cookie matches the master-journal-recovered DDL state; active_plugins cached before plugin-table DDL reopens before import continues.',
    'status' => $plan['status'],
    'schemaCookieInvalidatedPages' => $plan['schema_cookie_invalidated_cache_page_numbers'],
    'cacheHits' => $plan['read_cache_hits'],
    'reopenReaders' => $plan['reopen_reader_ids'],
    'dependencyClosure' => 'no new support component needed; this reuses lane-local pager master-journal reader-cache source/change-counter/schema-root tickets and adds schema-cookie admission evidence',
];

if ($summary['status'] !== 'pager-master-journal-reader-cache-current-source-next241'
    || $summary['schemaCookieInvalidatedPages'] !== [3]
    || $summary['cacheHits'] !== ['read-1' => true, 'read-2' => true, 'read-3' => false]
) {
    fwrite(STDERR, "application-pager-master-journal-reader-cache-current-source-next241 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
echo "application-pager-master-journal-reader-cache-current-source-next241 self-test passed\n";
