<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next242.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next242-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'wp-next242-current-source';
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$members = [$mainJournal, $usersJournal];
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$formatPage = static function (string $label) use ($pageSize): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr(4), 20, 1);
    $page = substr_replace($page, pack('N', 2), 56, 4);
    $page = substr_replace($page, pack('N', 242), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503242), 68, 4);

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
    1 => $formatPage('wp next242 stale schema before statement snapshot fence'),
    2 => $page('wp next242 stale wp_options root before statement snapshot fence'),
    3 => $page('wp next242 stale active_plugins before statement snapshot fence'),
];
$recovered = [
    1 => $formatPage('wp next242 current schema after statement snapshot fence'),
    2 => $page('wp next242 current wp_options root after statement snapshot fence'),
    3 => $page('wp next242 current active_plugins after statement snapshot fence'),
];
$tokens = [
    $mainJournal => 'dev=8:ino=2421:size=4096:mtime=24201:generation=main-current',
    $usersJournal => 'dev=8:ino=2422:size=1024:mtime=24202:generation=users-current',
];
$headers = [
    $mainJournal => hash('sha256', 'wp main rollback header next242'),
    $usersJournal => hash('sha256', 'wp users rollback header next242'),
];
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 242, 0x57503242]));
$masterDigest = hash('sha256', 'wp next242 master source');
$masterToken = 'dev=8:ino=2420:size=96:mtime=24200:generation=master-current';
$databaseToken = 'dev=8:ino=2429:size=1536:mtime=24299:generation=database-current';
$cleanupToken = 'master-cleanup:deleted:mtime=23700:dirsync=ok';
$readerLeaseToken = 'reader-lease:shared-cache:epoch=242:opened-after-master-cleanup';
$pagerCacheSourceToken = 'pager-cache-source:epoch=242:master-journal-recovery=complete';
$readTransactionToken = 'read-transaction:epoch=242:schema=96:change-counter=242:master-current';
$schemaReparseToken = 'schema-reparse:epoch=242:schema-cookie=96:ddl=master-current';
$oldSchemaReparseToken = 'schema-reparse:epoch=235:schema-cookie=95:ddl=before-master-current';
$sharedCacheGenerationToken = 'shared-cache-generation:epoch=242:schema-cookie=96:master-current';
$oldSharedCacheGenerationToken = 'shared-cache-generation:epoch=235:schema-cookie=95:before-master-current';
$statementSnapshotToken = 'statement-snapshot:epoch=242:stmt-cache=wp-options:master-current';
$oldStatementSnapshotToken = 'statement-snapshot:epoch=235:stmt-cache=wp-options:before-master-current';
$base = [
    'source_id' => $sourceId,
    'epoch' => 242,
    'format_signature' => $formatSignature,
    'publication_generation' => 242,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 242,
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
    1 => ['label' => 'schema-cache', 'reader_id' => 'schema-cache-reader', 'image' => $recovered[1], 'schema_reparse_token' => $schemaReparseToken, 'shared_cache_generation_token' => $sharedCacheGenerationToken, 'statement_snapshot_token' => $statementSnapshotToken] + $base,
    2 => ['label' => 'options-root-stale-statement-snapshot', 'reader_id' => 'options-root-reader', 'image' => $before[2], 'schema_reparse_token' => $schemaReparseToken, 'shared_cache_generation_token' => $sharedCacheGenerationToken, 'statement_snapshot_token' => $oldStatementSnapshotToken] + $base,
    3 => ['label' => 'active-plugins-stale-schema-reparse', 'reader_id' => 'active-plugins-reader', 'image' => $recovered[3], 'schema_reparse_token' => $oldSchemaReparseToken, 'shared_cache_generation_token' => $sharedCacheGenerationToken, 'statement_snapshot_token' => $statementSnapshotToken] + $base,
];
$read = static fn (int $pageNumber, string $schemaToken = null, string $sharedGeneration = null, string $statementSnapshot = null): array => [
    'reader_id' => 'read-' . $pageNumber,
    'page_number' => $pageNumber,
    'source_id' => $sourceId,
    'epoch' => 242,
    'format_signature' => $formatSignature,
    'publication_generation' => 242,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 242,
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
    'statement_snapshot_token' => $statementSnapshot ?? $statementSnapshotToken,
];

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::planStatementSnapshotFence(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    $readerCache,
    [$read(1), $read(2), $read(3, $oldSchemaReparseToken)],
    $sourceId,
    242,
    242,
    $masterDigest,
    242,
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
);

$summary = [
    'scenario' => 'application-pager-master-journal-reader-cache-statement-snapshot-fence',
    'applicationUse' => 'A copied Application database keeps schema reader-cache pages only when schema-reparse, shared-cache generation, and prepared statement snapshot tickets were opened after master-journal recovery; stale options and active_plugins readers reopen before plugin import resumes.',
    'status' => $plan['status'],
    'schemaReparseInvalidatedPages' => $plan['schema_reparse_invalidated_cache_page_numbers'],
    'sharedGenerationInvalidatedPages' => $plan['shared_cache_generation_invalidated_cache_page_numbers'],
    'statementSnapshotInvalidatedPages' => $plan['statement_snapshot_invalidated_cache_page_numbers'],
    'cacheHits' => $plan['read_cache_hits'],
    'reopenReaders' => $plan['reopen_reader_ids'],
    'dependencyClosure' => 'no new support component needed; this reuses lane-local pager master-journal reader-cache, schema-reparse, shared-generation, and read-transaction fences',
];

if ($summary['status'] !== 'pager-master-journal-reader-cache-current-source-next242'
    || $summary['schemaReparseInvalidatedPages'] !== [3]
    || $summary['sharedGenerationInvalidatedPages'] !== []
    || $summary['statementSnapshotInvalidatedPages'] !== [2]
    || $summary['cacheHits'] !== ['read-1' => true, 'read-2' => false, 'read-3' => false]
) {
    fwrite(STDERR, "application-pager-master-journal-reader-cache-statement-snapshot-fence self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
echo "application-pager-master-journal-reader-cache-statement-snapshot-fence self-test passed\n";
