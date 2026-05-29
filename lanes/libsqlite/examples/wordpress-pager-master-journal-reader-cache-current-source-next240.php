<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next240.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next240-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'wp-next240-current-source';
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$members = [$mainJournal, $usersJournal];
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$formatPage = static function (string $label) use ($pageSize): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr(4), 20, 1);
    $page = substr_replace($page, pack('N', 2), 56, 4);
    $page = substr_replace($page, pack('N', 240), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503240), 68, 4);

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
    1 => $formatPage('wp next240 stale schema before statement-root fence'),
    2 => $page('wp next240 stale wp_options root before statement-root fence'),
    3 => $page('wp next240 stale active_plugins before statement-root fence'),
];
$recovered = [
    1 => $formatPage('wp next240 current schema after statement-root fence'),
    2 => $page('wp next240 current wp_options root after statement-root fence'),
    3 => $page('wp next240 current active_plugins after statement-root fence'),
];
$tokens = [
    $mainJournal => 'dev=8:ino=2401:size=4096:mtime=24001:generation=main-current',
    $usersJournal => 'dev=8:ino=2402:size=1024:mtime=24002:generation=users-current',
];
$headers = [
    $mainJournal => hash('sha256', 'wp main rollback header next240'),
    $usersJournal => hash('sha256', 'wp users rollback header next240'),
];
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 240, 0x57503240]));
$masterDigest = hash('sha256', 'wp next240 master source');
$masterToken = 'dev=8:ino=2400:size=96:mtime=24000:generation=master-current';
$databaseToken = 'dev=8:ino=2409:size=1536:mtime=24099:generation=database-current';
$cleanupToken = 'master-cleanup:deleted:mtime=24100:dirsync=ok';
$readerLeaseToken = 'reader-lease:shared-cache:epoch=240:opened-after-master-cleanup';
$pagerCacheSourceToken = 'pager-cache-source:epoch=240:master-journal-recovery=complete';
$readTransactionToken = 'read-transaction:epoch=240:schema=100:change-counter=240:master-current';
$schemaReparseToken = 'schema-reparse:epoch=240:schema-cookie=100:ddl=master-current';
$statementSchemaRootToken = 'statement-schema-root:epoch=240:root=1:cookie=100:sql=wp-options-current';
$oldStatementSchemaRootToken = 'statement-schema-root:epoch=239:root=1:cookie=99:sql=wp-options-prior';
$base = [
    'source_id' => $sourceId,
    'epoch' => 240,
    'format_signature' => $formatSignature,
    'publication_generation' => 240,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 240,
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
];
$readerCache = [
    1 => ['label' => 'schema-cache', 'reader_id' => 'schema-cache-reader', 'image' => $recovered[1], 'statement_schema_root_token' => $statementSchemaRootToken] + $base,
    2 => ['label' => 'options-root-cache', 'reader_id' => 'options-root-reader', 'image' => $before[2], 'statement_schema_root_token' => $statementSchemaRootToken] + $base,
    3 => ['label' => 'active-plugins-stale-statement-root', 'reader_id' => 'active-plugins-reader', 'image' => $recovered[3], 'statement_schema_root_token' => $oldStatementSchemaRootToken] + $base,
];
$read = static fn (int $pageNumber, string $statementToken = null): array => [
    'reader_id' => 'read-' . $pageNumber,
    'page_number' => $pageNumber,
    'source_id' => $sourceId,
    'epoch' => 240,
    'format_signature' => $formatSignature,
    'publication_generation' => 240,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 240,
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
    'statement_schema_root_token' => $statementToken ?? $statementSchemaRootToken,
];

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext240(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    $readerCache,
    [$read(1), $read(2), $read(3, $oldStatementSchemaRootToken)],
    $sourceId,
    240,
    240,
    $masterDigest,
    240,
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
);

$summary = [
    'scenario' => 'wordpress-pager-master-journal-reader-cache-current-source-next240',
    'wordpressUse' => 'A copied WordPress database keeps schema/options reader-cache pages only when a prepared statement was planned against the current recovered schema root; stale active_plugins statement reads reopen before plugin import resumes.',
    'status' => $plan['status'],
    'statementSchemaRootInvalidatedPages' => $plan['statement_schema_root_invalidated_cache_page_numbers'],
    'cacheHits' => $plan['read_cache_hits'],
    'reopenReaders' => $plan['reopen_reader_ids'],
    'dependencyClosure' => 'no new support component needed; this reuses lane-local pager master-journal reader-cache, read-transaction, and schema-reparse fences',
];

if ($summary['status'] !== 'pager-master-journal-reader-cache-current-source-next240'
    || $summary['statementSchemaRootInvalidatedPages'] !== [3]
    || $summary['cacheHits'] !== ['read-1' => true, 'read-2' => true, 'read-3' => false]
) {
    fwrite(STDERR, "wordpress-pager-master-journal-reader-cache-current-source-next240 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
echo "wordpress-pager-master-journal-reader-cache-current-source-next240 self-test passed\n";
