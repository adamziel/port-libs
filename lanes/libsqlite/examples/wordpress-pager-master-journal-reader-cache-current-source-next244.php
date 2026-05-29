<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next244.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next244-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'wp-next244-current-source';
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$members = [$mainJournal, $usersJournal];
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$formatPage = static function (string $label) use ($pageSize): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr(4), 20, 1);
    $page = substr_replace($page, pack('N', 2), 56, 4);
    $page = substr_replace($page, pack('N', 244), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503244), 68, 4);

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
    1 => $formatPage('wp next244 stale schema before page digest receipt'),
    2 => $page('wp next244 stale wp_options root before page digest receipt'),
    3 => $page('wp next244 stale active_plugins before page digest receipt'),
];
$recovered = [
    1 => $formatPage('wp next244 current schema after page digest receipt'),
    2 => $page('wp next244 current wp_options root after page digest receipt'),
    3 => $page('wp next244 current active_plugins after page digest receipt'),
];
$tokens = [
    $mainJournal => 'dev=8:ino=2441:size=4096:mtime=24401:generation=main-current',
    $usersJournal => 'dev=8:ino=2442:size=1024:mtime=24402:generation=users-current',
];
$headers = [
    $mainJournal => hash('sha256', 'wp main rollback header next244'),
    $usersJournal => hash('sha256', 'wp users rollback header next244'),
];
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 244, 0x57503244]));
$masterDigest = hash('sha256', 'wp next244 master source');
$masterToken = 'dev=8:ino=2440:size=96:mtime=24400:generation=master-current';
$databaseToken = 'dev=8:ino=2449:size=1536:mtime=24499:generation=database-current';
$cleanupToken = 'master-cleanup:deleted:mtime=24500:dirsync=ok';
$readerLeaseToken = 'reader-lease:shared-cache:epoch=244:opened-after-master-cleanup';
$pagerCacheSourceToken = 'pager-cache-source:epoch=244:master-journal-recovery=complete';
$readTransactionToken = 'read-transaction:epoch=244:schema=104:change-counter=244:master-current';
$schemaReparseToken = 'schema-reparse:epoch=244:schema-cookie=104:ddl=master-current';
$statementSchemaRootToken = 'statement-schema-root:epoch=244:root=1:cookie=104:sql=wp-options-current';
$pageImageDigestReceiptToken = 'page-image-digest-receipt:epoch=244:master=complete:pages=1,2,3';
$oldPageImageDigestReceiptToken = 'page-image-digest-receipt:epoch=243:master=prior:pages=1,2,3';
$base = [
    'source_id' => $sourceId,
    'epoch' => 244,
    'format_signature' => $formatSignature,
    'publication_generation' => 244,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 244,
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
    1 => ['label' => 'schema-cache', 'reader_id' => 'schema-cache-reader', 'image' => $recovered[1], 'page_image_digest_receipt_token' => $pageImageDigestReceiptToken] + $base,
    2 => ['label' => 'options-root-cache', 'reader_id' => 'options-root-reader', 'image' => $before[2], 'page_image_digest_receipt_token' => $pageImageDigestReceiptToken] + $base,
    3 => ['label' => 'active-plugins-stale-digest-receipt', 'reader_id' => 'active-plugins-reader', 'image' => $recovered[3], 'page_image_digest_receipt_token' => $oldPageImageDigestReceiptToken] + $base,
];
$read = static fn (int $pageNumber, string $digestReceipt = null): array => [
    'reader_id' => 'read-' . $pageNumber,
    'page_number' => $pageNumber,
    'source_id' => $sourceId,
    'epoch' => 244,
    'format_signature' => $formatSignature,
    'publication_generation' => 244,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 244,
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
    'page_image_digest_receipt_token' => $digestReceipt ?? $pageImageDigestReceiptToken,
];

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext244(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    $readerCache,
    [$read(1), $read(2), $read(3, $oldPageImageDigestReceiptToken)],
    $sourceId,
    244,
    244,
    $masterDigest,
    244,
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
    $pageImageDigestReceiptToken,
);

$summary = [
    'scenario' => 'wordpress-pager-master-journal-reader-cache-current-source-next244',
    'wordpressUse' => 'A copied WordPress database keeps schema/options cache pages only when the reader ticket also carries the current recovered page-image digest receipt; stale active_plugins readers reopen before plugin import resumes.',
    'status' => $plan['status'],
    'pageImageDigestReceiptInvalidatedPages' => $plan['page_image_digest_receipt_invalidated_cache_page_numbers'],
    'cacheHits' => $plan['read_cache_hits'],
    'reopenReaders' => $plan['reopen_reader_ids'],
    'dependencyClosure' => 'no new support component needed; this reuses lane-local pager master-journal reader-cache and statement schema-root fences',
];

if ($summary['status'] !== 'pager-master-journal-reader-cache-current-source-next244'
    || $summary['pageImageDigestReceiptInvalidatedPages'] !== [3]
    || $summary['cacheHits'] !== ['read-1' => true, 'read-2' => true, 'read-3' => false]
) {
    fwrite(STDERR, "wordpress-pager-master-journal-reader-cache-current-source-next244 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
echo "wordpress-pager-master-journal-reader-cache-current-source-next244 self-test passed\n";
