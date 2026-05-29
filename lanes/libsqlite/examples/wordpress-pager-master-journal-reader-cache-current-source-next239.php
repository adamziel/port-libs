<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next239.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next239-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'wp-next239-current-source';
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$members = [$mainJournal, $usersJournal];
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$formatPage = static function (string $label) use ($pageSize): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr(4), 20, 1);
    $page = substr_replace($page, pack('N', 2), 56, 4);
    $page = substr_replace($page, pack('N', 239), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503239), 68, 4);

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
    1 => $formatPage('wp next239 stale schema before schema reparse fence'),
    2 => $page('wp next239 stale wp_options root before schema reparse fence'),
    3 => $page('wp next239 stale active_plugins before schema reparse fence'),
];
$recovered = [
    1 => $formatPage('wp next239 current schema after schema reparse fence'),
    2 => $page('wp next239 current wp_options root after schema reparse fence'),
    3 => $page('wp next239 current active_plugins after schema reparse fence'),
];
$tokens = [
    $mainJournal => 'dev=8:ino=2391:size=4096:mtime=23901:generation=main-current',
    $usersJournal => 'dev=8:ino=2392:size=1024:mtime=23902:generation=users-current',
];
$headers = [
    $mainJournal => hash('sha256', 'wp main rollback header next239'),
    $usersJournal => hash('sha256', 'wp users rollback header next239'),
];
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 239, 0x57503239]));
$masterDigest = hash('sha256', 'wp next239 master source');
$masterToken = 'dev=8:ino=2390:size=96:mtime=23900:generation=master-current';
$databaseToken = 'dev=8:ino=2399:size=1536:mtime=23999:generation=database-current';
$cleanupToken = 'master-cleanup:deleted:mtime=23700:dirsync=ok';
$readerLeaseToken = 'reader-lease:shared-cache:epoch=239:opened-after-master-cleanup';
$pagerCacheSourceToken = 'pager-cache-source:epoch=239:master-journal-recovery=complete';
$readTransactionToken = 'read-transaction:epoch=239:schema=96:change-counter=239:master-current';
$schemaReparseToken = 'schema-reparse:epoch=239:schema-cookie=96:ddl=master-current';
$oldSchemaReparseToken = 'schema-reparse:epoch=235:schema-cookie=95:ddl=before-master-current';
$sharedCacheGenerationToken = 'shared-cache-generation:epoch=239:schema-cookie=96:master-current';
$oldSharedCacheGenerationToken = 'shared-cache-generation:epoch=235:schema-cookie=95:before-master-current';
$base = [
    'source_id' => $sourceId,
    'epoch' => 239,
    'format_signature' => $formatSignature,
    'publication_generation' => 239,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 239,
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
];
$readerCache = [
    1 => ['label' => 'schema-cache', 'reader_id' => 'schema-cache-reader', 'image' => $recovered[1], 'schema_reparse_token' => $schemaReparseToken, 'shared_cache_generation_token' => $sharedCacheGenerationToken] + $base,
    2 => ['label' => 'options-root-stale-shared-generation', 'reader_id' => 'options-root-reader', 'image' => $before[2], 'schema_reparse_token' => $schemaReparseToken, 'shared_cache_generation_token' => $oldSharedCacheGenerationToken] + $base,
    3 => ['label' => 'active-plugins-stale-schema-reparse', 'reader_id' => 'active-plugins-reader', 'image' => $recovered[3], 'schema_reparse_token' => $oldSchemaReparseToken, 'shared_cache_generation_token' => $sharedCacheGenerationToken] + $base,
];
$read = static fn (int $pageNumber, string $schemaToken = null, string $sharedGeneration = null): array => [
    'reader_id' => 'read-' . $pageNumber,
    'page_number' => $pageNumber,
    'source_id' => $sourceId,
    'epoch' => 239,
    'format_signature' => $formatSignature,
    'publication_generation' => 239,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 239,
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
    'schema_reparse_token' => $schemaToken ?? $schemaReparseToken,
    'shared_cache_generation_token' => $sharedGeneration ?? $sharedCacheGenerationToken,
];

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext239(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    $readerCache,
    [$read(1), $read(2), $read(3, $oldSchemaReparseToken)],
    $sourceId,
    239,
    239,
    $masterDigest,
    239,
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
);

$summary = [
    'scenario' => 'wordpress-pager-master-journal-reader-cache-current-source-next239',
    'wordpressUse' => 'A copied WordPress database keeps schema reader-cache pages only when both schema-reparse and shared-cache generation tickets were opened after master-journal recovery; stale options and active_plugins readers reopen before plugin import resumes.',
    'status' => $plan['status'],
    'schemaReparseInvalidatedPages' => $plan['schema_reparse_invalidated_cache_page_numbers'],
    'sharedGenerationInvalidatedPages' => $plan['shared_cache_generation_invalidated_cache_page_numbers'],
    'cacheHits' => $plan['read_cache_hits'],
    'reopenReaders' => $plan['reopen_reader_ids'],
    'dependencyClosure' => 'no new support component needed; this reuses lane-local pager master-journal reader-cache, schema-reparse, and read-transaction fences',
];

if ($summary['status'] !== 'pager-master-journal-reader-cache-current-source-next239'
    || $summary['schemaReparseInvalidatedPages'] !== [3]
    || $summary['sharedGenerationInvalidatedPages'] !== [2]
    || $summary['cacheHits'] !== ['read-1' => true, 'read-2' => false, 'read-3' => false]
) {
    fwrite(STDERR, "wordpress-pager-master-journal-reader-cache-current-source-next239 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
echo "wordpress-pager-master-journal-reader-cache-current-source-next239 self-test passed\n";
