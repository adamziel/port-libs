<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next255.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next255-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'wp-next255-reader-page-map';
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$members = [$mainJournal, $usersJournal];
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$formatPage = static function (string $label) use ($pageSize): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr(4), 20, 1);
    $page = substr_replace($page, pack('N', 2), 56, 4);
    $page = substr_replace($page, pack('N', 255), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503255), 68, 4);

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
    1 => $formatPage('wp next255 stale schema before reader page-map digest fence'),
    2 => $page('wp next255 stale wp_options before reader page-map digest fence'),
    3 => $page('wp next255 stale active_plugins before reader page-map digest fence'),
];
$recovered = [
    1 => $formatPage('wp next255 current schema after reader page-map digest fence'),
    2 => $page('wp next255 current wp_options after reader page-map digest fence'),
    3 => $page('wp next255 current active_plugins after reader page-map digest fence'),
];
$tokens = [
    $mainJournal => 'dev=8:ino=2551:size=4096:mtime=25501:generation=main-current',
    $usersJournal => 'dev=8:ino=2552:size=1024:mtime=25502:generation=users-current',
];
$headers = [
    $mainJournal => hash('sha256', 'wp main rollback header next255'),
    $usersJournal => hash('sha256', 'wp users rollback header next255'),
];
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 255, 0x57503255]));
$masterDigest = hash('sha256', 'wp next255 master source');
$masterToken = 'dev=8:ino=2550:size=96:mtime=25500:generation=master-current';
$databaseToken = 'dev=8:ino=2559:size=1536:mtime=25599:generation=database-current';
$cleanupToken = 'master-cleanup:deleted:mtime=25200:dirsync=ok';
$readerLeaseToken = 'reader-lease:shared-cache:epoch=255:opened-after-master-cleanup';
$pagerCacheSourceToken = 'pager-cache-source:epoch=255:master-journal-recovery=complete';
$readTransactionToken = 'read-transaction:epoch=255:schema=110:change-counter=255:master-current';
$schemaReparseToken = 'schema-reparse:epoch=255:schema-cookie=110:ddl=master-current';
$statementSchemaRootToken = 'statement-schema-root:epoch=255:root=1:cookie=110:sql=wp-options-current';
$currentSourceProvenanceToken = 'current-source:master-journal:epoch=255:members=2:database-token=2559:schema=110';
$currentPagerReaderCacheGenerationToken = 'pager-reader-cache-generation:epoch=255:master-current:reset=complete';
$currentReaderSnapshotToken = 'reader-snapshot:epoch=255:source=master-current:lease=shared:pages=1,2,3';
$oldReaderSnapshotToken = 'reader-snapshot:epoch=250:source=before-master-current:lease=shared';
$currentReaderPageMapDigestToken = 'reader-page-map:epoch=255:source=master-current:lease=shared:pages=1,2,3';
$oldReaderPageMapDigestToken = 'reader-page-map:epoch=250:source=before-master-current:lease=shared';
$base = [
    'source_id' => $sourceId,
    'epoch' => 255,
    'format_signature' => $formatSignature,
    'publication_generation' => 255,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 255,
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
    1 => ['label' => 'schema-cache-current-page-map', 'reader_id' => 'schema-reader', 'image' => $recovered[1], 'pager_reader_cache_generation_token' => $currentPagerReaderCacheGenerationToken, 'reader_snapshot_token' => $currentReaderSnapshotToken, 'reader_page_map_digest_token' => $currentReaderPageMapDigestToken] + $base,
    2 => ['label' => 'options-root-stale-page-map', 'reader_id' => 'options-reader', 'image' => $before[2], 'pager_reader_cache_generation_token' => $currentPagerReaderCacheGenerationToken, 'reader_snapshot_token' => $currentReaderSnapshotToken, 'reader_page_map_digest_token' => $oldReaderPageMapDigestToken] + $base,
    3 => ['label' => 'active-plugins-stale-snapshot', 'reader_id' => 'plugins-reader', 'image' => $recovered[3], 'pager_reader_cache_generation_token' => $currentPagerReaderCacheGenerationToken, 'reader_snapshot_token' => $oldReaderSnapshotToken, 'reader_page_map_digest_token' => $currentReaderPageMapDigestToken] + $base,
];
$read = static fn (int $pageNumber, ?string $pageMapToken = null): array => [
    'reader_id' => 'read-' . $pageNumber,
    'page_number' => $pageNumber,
    'source_id' => $sourceId,
    'epoch' => 255,
    'format_signature' => $formatSignature,
    'publication_generation' => 255,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 255,
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
    'reader_snapshot_token' => $currentReaderSnapshotToken,
    'reader_page_map_digest_token' => $pageMapToken ?? $currentReaderPageMapDigestToken,
];

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::readerPageMapDigestFence(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    $readerCache,
    [$read(1), $read(2), $read(3)],
    $sourceId,
    255,
    255,
    $masterDigest,
    255,
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
    $currentReaderPageMapDigestToken,
);

$summary = [
    'scenario' => 'wordpress-pager-master-journal-reader-cache-current-source-next255',
    'wordpressUse' => 'A copied WordPress database keeps schema reader-cache pages only when the reader page-map digest was opened after master-journal current-source recovery; stale wp_options and active_plugins readers reopen before import continues.',
    'status' => $plan['status'],
    'pageMapInvalidatedPages' => $plan['reader_page_map_digest_invalidated_cache_page_numbers'],
    'snapshotInvalidatedPages' => $plan['reader_snapshot_invalidated_cache_page_numbers'],
    'cacheHits' => $plan['read_cache_hits'],
    'reopenReaders' => $plan['reopen_reader_ids'],
    'dependencyClosure' => 'no new support component needed; this reuses lane-local pager master-journal reader-cache and current-source generation fences',
];

if ($summary['status'] !== 'pager-master-journal-reader-cache-current-source-next255'
    || $summary['pageMapInvalidatedPages'] !== [2]
    || $summary['snapshotInvalidatedPages'] !== [3]
    || $summary['cacheHits'] !== ['read-1' => true, 'read-2' => false, 'read-3' => false]
) {
    fwrite(STDERR, "wordpress-pager-master-journal-reader-cache-current-source-next255 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
echo "wordpress-pager-master-journal-reader-cache-current-source-next255 self-test passed\n";
