<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next248.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next248-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'wp-next248-current-source';
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$members = [$mainJournal, $usersJournal];
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$formatPage = static function (string $label) use ($pageSize): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr(4), 20, 1);
    $page = substr_replace($page, pack('N', 2), 56, 4);
    $page = substr_replace($page, pack('N', 248), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503248), 68, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$mapDigest = static function (array $map): string {
    ksort($map, SORT_STRING);
    $parts = [];
    foreach ($map as $member => $value) {
        $parts[] = $member . '=' . $value;
    }

    return hash('sha256', implode('|', $parts));
};
$recoveredDigest = static function (array $pages): string {
    ksort($pages, SORT_NUMERIC);
    $parts = [];
    foreach ($pages as $pageNumber => $image) {
        $parts[] = $pageNumber . ':' . hash('sha256', $image);
    }

    return hash('sha256', implode('|', $parts));
};
$before = [
    1 => $formatPage('wp next248 stale schema before page owner fence'),
    2 => $page('wp next248 stale wp_options root before page owner fence'),
    3 => $page('wp next248 stale active_plugins before page owner fence'),
];
$recovered = [
    1 => $formatPage('wp next248 current schema after page owner fence'),
    2 => $page('wp next248 current wp_options root after page owner fence'),
    3 => $page('wp next248 current active_plugins after page owner fence'),
];
$tokens = [
    $mainJournal => 'dev=8:ino=2481:size=4096:mtime=24801:generation=main-current',
    $usersJournal => 'dev=8:ino=2482:size=1024:mtime=24802:generation=users-current',
];
$headers = [
    $mainJournal => hash('sha256', 'wp main rollback header next248'),
    $usersJournal => hash('sha256', 'wp users rollback header next248'),
];
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 248, 0x57503248]));
$masterDigest = hash('sha256', 'wp next248 master source');
$masterToken = 'dev=8:ino=2480:size=96:mtime=24800:generation=master-current';
$databaseToken = 'dev=8:ino=2489:size=1536:mtime=24899:generation=database-current';
$cleanupToken = 'master-cleanup:deleted:mtime=24800:dirsync=ok';
$readerLeaseToken = 'reader-lease:shared-cache:epoch=248:opened-after-master-cleanup';
$pagerCacheSourceToken = 'pager-cache-source:epoch=248:master-journal-recovery=complete';
$readTransactionToken = 'read-transaction:epoch=248:schema=108:change-counter=248:master-current';
$schemaReparseToken = 'schema-reparse:epoch=248:schema-cookie=108:ddl=master-current';
$sharedCacheGenerationToken = 'shared-cache-generation:epoch=248:schema-cookie=108:master-current';
$statementSnapshotToken = 'statement-snapshot:epoch=248:stmt-cache=wp-options:master-current';
$rootpageMapToken = 'rootpage-map:epoch=248:wp_options=2:autoload=4:option_name=6';
$oldRootpageMapToken = 'rootpage-map:epoch=245:wp_options=2:autoload=5:option_name=7';
$pageOwnerMapToken = 'page-owner-map:epoch=248:p1=schema:p2=wp_options:p3=active_plugins';
$oldPageOwnerMapToken = 'page-owner-map:epoch=245:p1=schema:p2=wp_options:p3=freelist';
$base = [
    'source_id' => $sourceId,
    'epoch' => 248,
    'format_signature' => $formatSignature,
    'publication_generation' => 248,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 248,
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
    'read_transaction_token' => $readTransactionToken,
    'schema_reparse_token' => $schemaReparseToken,
    'shared_cache_generation_token' => $sharedCacheGenerationToken,
    'statement_snapshot_token' => $statementSnapshotToken,
];
$readerCache = [
    1 => ['label' => 'schema-cache', 'reader_id' => 'schema-cache-reader', 'image' => $recovered[1], 'rootpage_map_token' => $rootpageMapToken, 'page_owner_map_token' => $pageOwnerMapToken] + $base,
    2 => ['label' => 'options-root-stale-owner-map', 'reader_id' => 'options-root-reader', 'image' => $recovered[2], 'rootpage_map_token' => $rootpageMapToken, 'page_owner_map_token' => $oldPageOwnerMapToken] + $base,
    3 => ['label' => 'active-plugins-stale-rootpage-map', 'reader_id' => 'active-plugins-reader', 'image' => $recovered[3], 'rootpage_map_token' => $oldRootpageMapToken, 'page_owner_map_token' => $pageOwnerMapToken] + $base,
];
$read = static fn (int $pageNumber, string $rootpageMap = null, string $ownerMap = null): array => [
    'reader_id' => 'read-' . $pageNumber,
    'page_number' => $pageNumber,
    'source_id' => $sourceId,
    'epoch' => 248,
    'format_signature' => $formatSignature,
    'publication_generation' => 248,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 248,
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
    'read_transaction_token' => $readTransactionToken,
    'schema_reparse_token' => $schemaReparseToken,
    'shared_cache_generation_token' => $sharedCacheGenerationToken,
    'statement_snapshot_token' => $statementSnapshotToken,
    'rootpage_map_token' => $rootpageMap ?? $rootpageMapToken,
    'page_owner_map_token' => $ownerMap ?? $pageOwnerMapToken,
];

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantCacheInvalidationReceipt(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    $readerCache,
    [$read(1), $read(2), $read(3, $oldRootpageMapToken)],
    $sourceId,
    248,
    248,
    $masterDigest,
    248,
    $tokens,
    $headers,
    $masterToken,
    $databaseToken,
    $cleanupToken,
    $readerLeaseToken,
    $pagerCacheSourceToken,
    $readTransactionToken,
    $schemaReparseToken,
    $sharedCacheGenerationToken,
    $statementSnapshotToken,
    $rootpageMapToken,
    $pageOwnerMapToken,
);

$summary = [
    'scenario' => 'wordpress-pager-master-journal-reader-cache-current-source-next248',
    'wordpressUse' => 'A copied WordPress database keeps a shared schema reader-cache page only when the recovered sqlite_schema rootpage map and B-tree page-owner map are both current; stale wp_options page ownership and stale active_plugins rootpage readers reopen before import queries resume.',
    'status' => $plan['status'],
    'pageOwnerMapInvalidatedPages' => $plan['page_owner_map_invalidated_cache_page_numbers'],
    'rootpageMapInvalidatedPages' => $plan['rootpage_map_invalidated_cache_page_numbers'],
    'cacheHits' => $plan['read_cache_hits'],
    'reopenReaders' => $plan['reopen_reader_ids'],
    'dependencyClosure' => 'no new support component needed; this reuses lane-local pager master-journal reader-cache and schema/rootpage fences while adding a page-owner map admission token',
];

if ($summary['status'] !== 'pager-master-journal-reader-cache-current-source-next248'
    || $summary['pageOwnerMapInvalidatedPages'] !== [2]
    || $summary['rootpageMapInvalidatedPages'] !== [3]
    || $summary['cacheHits'] !== ['read-1' => true, 'read-2' => false, 'read-3' => false]
) {
    fwrite(STDERR, "wordpress-pager-master-journal-reader-cache-current-source-next248 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
echo "wordpress-pager-master-journal-reader-cache-current-source-next248 self-test passed\n";
