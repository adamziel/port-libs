<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next245.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next245-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'wp-next245-current-source';
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$members = [$mainJournal, $usersJournal];
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$formatPage = static function (string $label) use ($pageSize): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr(4), 20, 1);
    $page = substr_replace($page, pack('N', 2), 56, 4);
    $page = substr_replace($page, pack('N', 245), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503245), 68, 4);

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
    1 => $formatPage('wp next245 stale schema before rootpage map fence'),
    2 => $page('wp next245 stale wp_options root before rootpage map fence'),
    3 => $page('wp next245 stale active_plugins before rootpage map fence'),
];
$recovered = [
    1 => $formatPage('wp next245 current schema after rootpage map fence'),
    2 => $page('wp next245 current wp_options root after rootpage map fence'),
    3 => $page('wp next245 current active_plugins after rootpage map fence'),
];
$tokens = [
    $mainJournal => 'dev=8:ino=2451:size=4096:mtime=24501:generation=main-current',
    $usersJournal => 'dev=8:ino=2452:size=1024:mtime=24502:generation=users-current',
];
$headers = [
    $mainJournal => hash('sha256', 'wp main rollback header next245'),
    $usersJournal => hash('sha256', 'wp users rollback header next245'),
];
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 245, 0x57503245]));
$masterDigest = hash('sha256', 'wp next245 master source');
$masterToken = 'dev=8:ino=2450:size=96:mtime=24500:generation=master-current';
$databaseToken = 'dev=8:ino=2459:size=1536:mtime=24599:generation=database-current';
$cleanupToken = 'master-cleanup:deleted:mtime=24500:dirsync=ok';
$readerLeaseToken = 'reader-lease:shared-cache:epoch=245:opened-after-master-cleanup';
$pagerCacheSourceToken = 'pager-cache-source:epoch=245:master-journal-recovery=complete';
$readTransactionToken = 'read-transaction:epoch=245:schema=101:change-counter=245:master-current';
$schemaReparseToken = 'schema-reparse:epoch=245:schema-cookie=101:ddl=master-current';
$sharedCacheGenerationToken = 'shared-cache-generation:epoch=245:schema-cookie=101:master-current';
$statementSnapshotToken = 'statement-snapshot:epoch=245:stmt-cache=wp-options:master-current';
$oldStatementSnapshotToken = 'statement-snapshot:epoch=238:stmt-cache=wp-options:before-master-current';
$rootpageMapToken = 'rootpage-map:epoch=245:wp_options=2:autoload=4:option_name=6';
$oldRootpageMapToken = 'rootpage-map:epoch=238:wp_options=2:autoload=5:option_name=7';
$base = [
    'source_id' => $sourceId,
    'epoch' => 245,
    'format_signature' => $formatSignature,
    'publication_generation' => 245,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 245,
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
];
$readerCache = [
    1 => ['label' => 'schema-cache', 'reader_id' => 'schema-cache-reader', 'image' => $recovered[1], 'statement_snapshot_token' => $statementSnapshotToken, 'rootpage_map_token' => $rootpageMapToken] + $base,
    2 => ['label' => 'options-root-stale-rootpage-map', 'reader_id' => 'options-root-reader', 'image' => $recovered[2], 'statement_snapshot_token' => $statementSnapshotToken, 'rootpage_map_token' => $oldRootpageMapToken] + $base,
    3 => ['label' => 'active-plugins-stale-statement-snapshot', 'reader_id' => 'active-plugins-reader', 'image' => $recovered[3], 'statement_snapshot_token' => $oldStatementSnapshotToken, 'rootpage_map_token' => $rootpageMapToken] + $base,
];
$read = static fn (int $pageNumber, string $statementSnapshot = null, string $rootpageMap = null): array => [
    'reader_id' => 'read-' . $pageNumber,
    'page_number' => $pageNumber,
    'source_id' => $sourceId,
    'epoch' => 245,
    'format_signature' => $formatSignature,
    'publication_generation' => 245,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 245,
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
    'statement_snapshot_token' => $statementSnapshot ?? $statementSnapshotToken,
    'rootpage_map_token' => $rootpageMap ?? $rootpageMapToken,
];

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext245(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    $readerCache,
    [$read(1), $read(2), $read(3, $oldStatementSnapshotToken)],
    $sourceId,
    245,
    245,
    $masterDigest,
    245,
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
);

$summary = [
    'scenario' => 'wordpress-pager-master-journal-reader-cache-current-source-next245',
    'wordpressUse' => 'A copied WordPress database keeps a schema reader-cache page only when the prepared statement snapshot and sqlite_schema rootpage map were both opened after master-journal recovery; stale wp_options rootpage and active_plugins statement readers reopen before plugin import resumes.',
    'status' => $plan['status'],
    'rootpageMapInvalidatedPages' => $plan['rootpage_map_invalidated_cache_page_numbers'],
    'statementSnapshotInvalidatedPages' => $plan['statement_snapshot_invalidated_cache_page_numbers'],
    'cacheHits' => $plan['read_cache_hits'],
    'reopenReaders' => $plan['reopen_reader_ids'],
    'dependencyClosure' => 'no new support component needed; this reuses lane-local pager master-journal reader-cache, statement snapshot, schema-reparse, shared-generation, and read-transaction fences',
];

if ($summary['status'] !== 'pager-master-journal-reader-cache-current-source-next245'
    || $summary['rootpageMapInvalidatedPages'] !== [2]
    || $summary['statementSnapshotInvalidatedPages'] !== [3]
    || $summary['cacheHits'] !== ['read-1' => true, 'read-2' => false, 'read-3' => false]
) {
    fwrite(STDERR, "wordpress-pager-master-journal-reader-cache-current-source-next245 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
echo "wordpress-pager-master-journal-reader-cache-current-source-next245 self-test passed\n";
