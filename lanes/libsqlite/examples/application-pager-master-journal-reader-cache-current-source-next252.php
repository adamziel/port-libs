<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next252.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next252-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'wp-next252-current-source';
$members = [$mainJournal, $usersJournal];
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$formatPage = static function (string $label) use ($pageSize): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr(4), 20, 1);
    $page = substr_replace($page, pack('N', 2), 56, 4);
    $page = substr_replace($page, pack('N', 252), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503252), 68, 4);

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
    1 => $formatPage('wp next252 stale schema before manifest recovery'),
    2 => $page('wp next252 stale wp_options root before manifest recovery'),
    3 => $page('wp next252 stale active_plugins before manifest recovery'),
];
$recovered = [
    1 => $formatPage('wp next252 current schema after manifest recovery'),
    2 => $page('wp next252 current wp_options root after manifest recovery'),
    3 => $page('wp next252 current active_plugins after manifest recovery'),
];
$tokens = [
    $mainJournal => 'dev=8:ino=2521:size=4096:mtime=25201:generation=main-current',
    $usersJournal => 'dev=8:ino=2522:size=1024:mtime=25202:generation=users-current',
];
$headers = [
    $mainJournal => hash('sha256', 'wp main rollback header next252'),
    $usersJournal => hash('sha256', 'wp users rollback header next252'),
];
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 252, 0x57503252]));
$masterDigest = hash('sha256', 'wp next252 master source');
$masterToken = 'dev=8:ino=2520:size=96:mtime=25200:generation=master-current';
$databaseToken = 'dev=8:ino=2529:size=1536:mtime=25299:generation=database-current';
$cleanupToken = 'master-cleanup:deleted:mtime=25300:dirsync=ok';
$readerLeaseToken = 'reader-lease:shared-cache:epoch=252:opened-after-master-cleanup';
$pagerCacheSourceToken = 'pager-cache-source:epoch=252:master-journal-recovery=complete';
$readTransactionToken = 'read-transaction:epoch=252:schema=112:change-counter=252:master-current';
$schemaReparseToken = 'schema-reparse:epoch=252:schema-cookie=112:ddl=master-current';
$sharedCacheGenerationToken = 'shared-cache-generation:epoch=252:schema-cookie=112:master-current';
$statementSnapshotToken = 'statement-snapshot:epoch=252:stmt-cache=wp-options:master-current';
$rootpageMapToken = 'rootpage-map:epoch=252:wp_options=2:autoload=4:option_name=6:users=8';
$pageOwnerMapToken = 'page-owner-map:epoch=252:p1=schema:p2=wp_options:p3=plugins';
$manifestToken = 'master-member-manifest:epoch=252:main=' . substr(hash('sha256', $mainJournal), 0, 12) . ':users=' . substr(hash('sha256', $usersJournal), 0, 12);
$oldManifestToken = 'master-member-manifest:epoch=251:main=old-main:users=old-users';
$base = [
    'source_id' => $sourceId,
    'epoch' => 252,
    'format_signature' => $formatSignature,
    'publication_generation' => 252,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 252,
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
    'rootpage_map_token' => $rootpageMapToken,
    'page_owner_map_token' => $pageOwnerMapToken,
];
$readerCache = [
    1 => ['label' => 'schema-cache', 'reader_id' => 'schema-cache-reader', 'image' => $recovered[1], 'master_member_manifest_token' => $manifestToken] + $base,
    2 => ['label' => 'options-root-stale-manifest', 'reader_id' => 'options-root-reader', 'image' => $recovered[2], 'master_member_manifest_token' => $oldManifestToken] + $base,
    3 => ['label' => 'active-plugins-cache', 'reader_id' => 'active-plugins-reader', 'image' => $recovered[3], 'master_member_manifest_token' => $manifestToken] + $base,
];
$read = static fn (int $pageNumber, string $manifest = null): array => [
    'reader_id' => 'read-' . $pageNumber,
    'page_number' => $pageNumber,
    'source_id' => $sourceId,
    'epoch' => 252,
    'format_signature' => $formatSignature,
    'publication_generation' => 252,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 252,
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
    'rootpage_map_token' => $rootpageMapToken,
    'page_owner_map_token' => $pageOwnerMapToken,
    'master_member_manifest_token' => $manifest ?? $manifestToken,
];

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantMasterJournalReaderCacheReceipt(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    $readerCache,
    [$read(1), $read(2, $oldManifestToken), $read(3)],
    $sourceId,
    252,
    252,
    $masterDigest,
    252,
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
    $manifestToken,
);

$summary = [
    'scenario' => 'application-pager-master-journal-reader-cache-current-source-next252',
    'applicationUse' => 'A copied Application database reopens a wp_options reader if its cache ticket was stamped with the previous master-journal member manifest, even when page-owner and schema tickets still match.',
    'status' => $plan['status'],
    'manifestInvalidatedPages' => $plan['master_member_manifest_invalidated_cache_page_numbers'],
    'cacheHits' => $plan['read_cache_hits'],
    'reopenReaders' => $plan['reopen_reader_ids'],
    'dependencyClosure' => 'no new support component needed; this reuses lane-local pager master-journal reader-cache fences and current-source cache tickets',
];

if ($summary['status'] !== 'pager-master-journal-reader-cache-current-source-next252'
    || $summary['manifestInvalidatedPages'] !== [2]
    || $summary['cacheHits'] !== ['read-1' => true, 'read-2' => false, 'read-3' => true]
) {
    fwrite(STDERR, "application-pager-master-journal-reader-cache-current-source-next252 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
echo "application-pager-master-journal-reader-cache-current-source-next252 self-test passed\n";
