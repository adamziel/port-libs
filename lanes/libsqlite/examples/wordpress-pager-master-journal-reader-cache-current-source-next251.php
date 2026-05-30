<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next251.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next251-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'wp-next251-reader-snapshot';
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$members = [$mainJournal, $usersJournal];
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$formatPage = static function (string $label) use ($pageSize): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr(4), 20, 1);
    $page = substr_replace($page, pack('N', 2), 56, 4);
    $page = substr_replace($page, pack('N', 251), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503251), 68, 4);

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
    1 => $formatPage('wp next251 stale schema before reader snapshot fence'),
    2 => $page('wp next251 stale wp_options before reader snapshot fence'),
    3 => $page('wp next251 stale active_plugins before reader snapshot fence'),
];
$recovered = [
    1 => $formatPage('wp next251 current schema after reader snapshot fence'),
    2 => $page('wp next251 current wp_options after reader snapshot fence'),
    3 => $page('wp next251 current active_plugins after reader snapshot fence'),
];
$tokens = [
    $mainJournal => 'dev=8:ino=2511:size=4096:mtime=25101:generation=main-current',
    $usersJournal => 'dev=8:ino=2512:size=1024:mtime=25102:generation=users-current',
];
$headers = [
    $mainJournal => hash('sha256', 'wp main rollback header next251'),
    $usersJournal => hash('sha256', 'wp users rollback header next251'),
];
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 251, 0x57503251]));
$masterDigest = hash('sha256', 'wp next251 master source');
$masterToken = 'dev=8:ino=2510:size=96:mtime=25100:generation=master-current';
$databaseToken = 'dev=8:ino=2519:size=1536:mtime=25199:generation=database-current';
$cleanupToken = 'master-cleanup:deleted:mtime=25200:dirsync=ok';
$readerLeaseToken = 'reader-lease:shared-cache:epoch=251:opened-after-master-cleanup';
$pagerCacheSourceToken = 'pager-cache-source:epoch=251:master-journal-recovery=complete';
$readTransactionToken = 'read-transaction:epoch=251:schema=110:change-counter=251:master-current';
$schemaReparseToken = 'schema-reparse:epoch=251:schema-cookie=110:ddl=master-current';
$statementSchemaRootToken = 'statement-schema-root:epoch=251:root=1:cookie=110:sql=wp-options-current';
$currentSourceProvenanceToken = 'current-source:master-journal:epoch=251:members=2:database-token=2519:schema=110';
$currentPagerReaderCacheGenerationToken = 'pager-reader-cache-generation:epoch=251:master-current:reset=complete';
$oldPagerReaderCacheGenerationToken = 'pager-reader-cache-generation:epoch=250:before-master-reset';
$currentReaderSnapshotToken = 'reader-snapshot:epoch=251:source=master-current:lease=shared:pages=1,2,3';
$oldReaderSnapshotToken = 'reader-snapshot:epoch=250:source=before-master-current:lease=shared';
$base = [
    'source_id' => $sourceId,
    'epoch' => 251,
    'format_signature' => $formatSignature,
    'publication_generation' => 251,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 251,
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
    'statement_schema_root_token' => $statementSchemaRootToken,
    'current_source_provenance_token' => $currentSourceProvenanceToken,
];
$readerCache = [
    1 => ['label' => 'schema-cache-current-snapshot', 'reader_id' => 'schema-reader', 'image' => $recovered[1], 'pager_reader_cache_generation_token' => $currentPagerReaderCacheGenerationToken, 'reader_snapshot_token' => $currentReaderSnapshotToken] + $base,
    2 => ['label' => 'options-root-stale-snapshot', 'reader_id' => 'options-reader', 'image' => $before[2], 'pager_reader_cache_generation_token' => $currentPagerReaderCacheGenerationToken, 'reader_snapshot_token' => $oldReaderSnapshotToken] + $base,
    3 => ['label' => 'active-plugins-stale-generation', 'reader_id' => 'plugins-reader', 'image' => $recovered[3], 'pager_reader_cache_generation_token' => $oldPagerReaderCacheGenerationToken, 'reader_snapshot_token' => $currentReaderSnapshotToken] + $base,
];
$read = static fn (int $pageNumber, ?string $snapshotToken = null): array => [
    'reader_id' => 'read-' . $pageNumber,
    'page_number' => $pageNumber,
    'source_id' => $sourceId,
    'epoch' => 251,
    'format_signature' => $formatSignature,
    'publication_generation' => 251,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 251,
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
    'statement_schema_root_token' => $statementSchemaRootToken,
    'current_source_provenance_token' => $currentSourceProvenanceToken,
    'pager_reader_cache_generation_token' => $currentPagerReaderCacheGenerationToken,
    'reader_snapshot_token' => $snapshotToken ?? $currentReaderSnapshotToken,
];

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantPinnedReaderReceipt(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    $readerCache,
    [$read(1), $read(2), $read(3)],
    $sourceId,
    251,
    251,
    $masterDigest,
    251,
    $tokens,
    $headers,
    $masterToken,
    $databaseToken,
    $cleanupToken,
    $readerLeaseToken,
    $pagerCacheSourceToken,
    $readTransactionToken,
    $schemaReparseToken,
    $statementSchemaRootToken,
    $currentSourceProvenanceToken,
    $currentPagerReaderCacheGenerationToken,
    $currentReaderSnapshotToken,
);

$summary = [
    'scenario' => 'wordpress-pager-master-journal-reader-cache-current-source-next251',
    'wordpressUse' => 'A copied WordPress database keeps schema reader-cache pages only when the reader snapshot was opened after master-journal current-source recovery; stale wp_options and active_plugins readers reopen before import continues.',
    'status' => $plan['status'],
    'snapshotInvalidatedPages' => $plan['reader_snapshot_invalidated_cache_page_numbers'],
    'generationInvalidatedPages' => $plan['pager_reader_cache_generation_invalidated_cache_page_numbers'],
    'cacheHits' => $plan['read_cache_hits'],
    'reopenReaders' => $plan['reopen_reader_ids'],
    'dependencyClosure' => 'no new support component needed; this reuses lane-local pager master-journal reader-cache and current-source generation fences',
];

if ($summary['status'] !== 'pager-master-journal-reader-cache-current-source-next251'
    || $summary['snapshotInvalidatedPages'] !== [2]
    || $summary['generationInvalidatedPages'] !== [3]
    || $summary['cacheHits'] !== ['read-1' => true, 'read-2' => false, 'read-3' => false]
) {
    fwrite(STDERR, "wordpress-pager-master-journal-reader-cache-current-source-next251 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
echo "wordpress-pager-master-journal-reader-cache-current-source-next251 self-test passed\n";
