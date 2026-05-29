<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next236.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next236-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'wp-next236-current-source';
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$members = [$mainJournal, $usersJournal];
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$formatPage = static function (string $label) use ($pageSize): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr(4), 20, 1);
    $page = substr_replace($page, pack('N', 2), 56, 4);
    $page = substr_replace($page, pack('N', 236), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503236), 68, 4);

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
    1 => $formatPage('wp next236 stale schema before schema reparse fence'),
    2 => $page('wp next236 stale wp_options root before schema reparse fence'),
    3 => $page('wp next236 stale active_plugins before schema reparse fence'),
];
$recovered = [
    1 => $formatPage('wp next236 current schema after schema reparse fence'),
    2 => $page('wp next236 current wp_options root after schema reparse fence'),
    3 => $page('wp next236 current active_plugins after schema reparse fence'),
];
$tokens = [
    $mainJournal => 'dev=8:ino=2361:size=4096:mtime=23601:generation=main-current',
    $usersJournal => 'dev=8:ino=2362:size=1024:mtime=23602:generation=users-current',
];
$headers = [
    $mainJournal => hash('sha256', 'wp main rollback header next236'),
    $usersJournal => hash('sha256', 'wp users rollback header next236'),
];
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 236, 0x57503236]));
$masterDigest = hash('sha256', 'wp next236 master source');
$masterToken = 'dev=8:ino=2360:size=96:mtime=23600:generation=master-current';
$databaseToken = 'dev=8:ino=2369:size=1536:mtime=23699:generation=database-current';
$cleanupToken = 'master-cleanup:deleted:mtime=23700:dirsync=ok';
$readerLeaseToken = 'reader-lease:shared-cache:epoch=236:opened-after-master-cleanup';
$pagerCacheSourceToken = 'pager-cache-source:epoch=236:master-journal-recovery=complete';
$readTransactionToken = 'read-transaction:epoch=236:schema=96:change-counter=236:master-current';
$schemaReparseToken = 'schema-reparse:epoch=236:schema-cookie=96:ddl=master-current';
$oldSchemaReparseToken = 'schema-reparse:epoch=235:schema-cookie=95:ddl=before-master-current';
$base = [
    'source_id' => $sourceId,
    'epoch' => 236,
    'format_signature' => $formatSignature,
    'publication_generation' => 236,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 236,
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
    1 => ['label' => 'schema-cache', 'reader_id' => 'schema-cache-reader', 'image' => $recovered[1], 'schema_reparse_token' => $schemaReparseToken] + $base,
    2 => ['label' => 'options-root-cache', 'reader_id' => 'options-root-reader', 'image' => $before[2], 'schema_reparse_token' => $schemaReparseToken] + $base,
    3 => ['label' => 'active-plugins-stale-schema-reparse', 'reader_id' => 'active-plugins-reader', 'image' => $recovered[3], 'schema_reparse_token' => $oldSchemaReparseToken] + $base,
];
$read = static fn (int $pageNumber, string $schemaToken = null): array => [
    'reader_id' => 'read-' . $pageNumber,
    'page_number' => $pageNumber,
    'source_id' => $sourceId,
    'epoch' => 236,
    'format_signature' => $formatSignature,
    'publication_generation' => 236,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 236,
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
];

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext236(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    $readerCache,
    [$read(1), $read(2), $read(3, $oldSchemaReparseToken)],
    $sourceId,
    236,
    236,
    $masterDigest,
    236,
    $tokens,
    $headers,
    $masterToken,
    $databaseToken,
    $cleanupToken,
    $readerLeaseToken,
    $pagerCacheSourceToken,
    $readTransactionToken,
    $schemaReparseToken,
);

$summary = [
    'scenario' => 'wordpress-pager-master-journal-reader-cache-current-source-next236',
    'wordpressUse' => 'A copied WordPress database keeps schema/options reader-cache pages only when their schema-reparse ticket was opened after master-journal recovery; stale active_plugins schema reads reopen before plugin import resumes.',
    'status' => $plan['status'],
    'schemaReparseInvalidatedPages' => $plan['schema_reparse_invalidated_cache_page_numbers'],
    'cacheHits' => $plan['read_cache_hits'],
    'reopenReaders' => $plan['reopen_reader_ids'],
    'dependencyClosure' => 'no new support component needed; this reuses lane-local pager master-journal reader-cache and read-transaction fences',
];

if ($summary['status'] !== 'pager-master-journal-reader-cache-current-source-next236'
    || $summary['schemaReparseInvalidatedPages'] !== [3]
    || $summary['cacheHits'] !== ['read-1' => true, 'read-2' => true, 'read-3' => false]
) {
    fwrite(STDERR, "wordpress-pager-master-journal-reader-cache-current-source-next236 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
echo "wordpress-pager-master-journal-reader-cache-current-source-next236 self-test passed\n";
