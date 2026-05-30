<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next243.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next243-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'wp-next243-current-source';
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$members = [$mainJournal, $usersJournal];
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$formatPage = static function (string $label) use ($pageSize): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr(4), 20, 1);
    $page = substr_replace($page, pack('N', 2), 56, 4);
    $page = substr_replace($page, pack('N', 243), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503243), 68, 4);

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
    1 => $formatPage('wp next243 stale schema before source provenance fence'),
    2 => $page('wp next243 stale wp_options root before source provenance fence'),
    3 => $page('wp next243 stale active_plugins before source provenance fence'),
];
$recovered = [
    1 => $formatPage('wp next243 current schema after source provenance fence'),
    2 => $page('wp next243 current wp_options root after source provenance fence'),
    3 => $page('wp next243 current active_plugins after source provenance fence'),
];
$tokens = [
    $mainJournal => 'dev=8:ino=2431:size=4096:mtime=24301:generation=main-current',
    $usersJournal => 'dev=8:ino=2432:size=1024:mtime=24302:generation=users-current',
];
$headers = [
    $mainJournal => hash('sha256', 'wp main rollback header next243'),
    $usersJournal => hash('sha256', 'wp users rollback header next243'),
];
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 243, 0x57503243]));
$masterDigest = hash('sha256', 'wp next243 master source');
$masterToken = 'dev=8:ino=2430:size=96:mtime=24300:generation=master-current';
$databaseToken = 'dev=8:ino=2439:size=1536:mtime=24399:generation=database-current';
$cleanupToken = 'master-cleanup:deleted:mtime=24400:dirsync=ok';
$readerLeaseToken = 'reader-lease:shared-cache:epoch=243:opened-after-master-cleanup';
$pagerCacheSourceToken = 'pager-cache-source:epoch=243:master-journal-recovery=complete';
$readTransactionToken = 'read-transaction:epoch=243:schema=103:change-counter=243:master-current';
$schemaReparseToken = 'schema-reparse:epoch=243:schema-cookie=103:ddl=master-current';
$statementSchemaRootToken = 'statement-schema-root:epoch=243:root=1:cookie=103:sql=wp-options-current';
$currentSourceProvenanceToken = 'current-source:master-journal:epoch=243:members=2:database-token=2439:schema=103';
$oldCurrentSourceProvenanceToken = 'current-source:master-journal:epoch=242:members=2:database-token=2438:schema=102';
$base = [
    'source_id' => $sourceId,
    'epoch' => 243,
    'format_signature' => $formatSignature,
    'publication_generation' => 243,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 243,
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
];
$readerCache = [
    1 => ['label' => 'schema-cache', 'reader_id' => 'schema-cache-reader', 'image' => $recovered[1], 'current_source_provenance_token' => $currentSourceProvenanceToken] + $base,
    2 => ['label' => 'options-root-cache', 'reader_id' => 'options-root-reader', 'image' => $before[2], 'current_source_provenance_token' => $currentSourceProvenanceToken] + $base,
    3 => ['label' => 'active-plugins-stale-source', 'reader_id' => 'active-plugins-reader', 'image' => $recovered[3], 'current_source_provenance_token' => $oldCurrentSourceProvenanceToken] + $base,
];
$read = static fn (int $pageNumber, string $sourceToken = null): array => [
    'reader_id' => 'read-' . $pageNumber,
    'page_number' => $pageNumber,
    'source_id' => $sourceId,
    'epoch' => 243,
    'format_signature' => $formatSignature,
    'publication_generation' => 243,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 243,
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
    'current_source_provenance_token' => $sourceToken ?? $currentSourceProvenanceToken,
];

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantRecoveryPlaybackFence(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    $readerCache,
    [$read(1), $read(2), $read(3, $oldCurrentSourceProvenanceToken)],
    $sourceId,
    243,
    243,
    $masterDigest,
    243,
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
);

$summary = [
    'scenario' => 'wordpress-pager-master-journal-reader-cache-current-source-next243',
    'wordpressUse' => 'A copied WordPress database keeps schema/options reader-cache pages only when they were opened against the same recovered master-journal current-source; stale active_plugins reads reopen before plugin import resumes.',
    'status' => $plan['status'],
    'sourceProvenanceInvalidatedPages' => $plan['current_source_provenance_invalidated_cache_page_numbers'],
    'cacheHits' => $plan['read_cache_hits'],
    'reopenReaders' => $plan['reopen_reader_ids'],
    'dependencyClosure' => 'no new support component needed; this reuses lane-local pager master-journal reader-cache, statement schema-root, and read-transaction fences',
];

if ($summary['status'] !== 'pager-master-journal-reader-cache-current-source-next243'
    || $summary['sourceProvenanceInvalidatedPages'] !== [3]
    || $summary['cacheHits'] !== ['read-1' => true, 'read-2' => true, 'read-3' => false]
) {
    fwrite(STDERR, "wordpress-pager-master-journal-reader-cache-current-source-next243 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
echo "wordpress-pager-master-journal-reader-cache-current-source-next243 self-test passed\n";
