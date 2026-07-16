<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next256.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next256-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'wp-next256-current-source';
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$members = [$mainJournal, $usersJournal];
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$formatPage = static function (string $label) use ($pageSize): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr(4), 20, 1);
    $page = substr_replace($page, pack('N', 2), 56, 4);
    $page = substr_replace($page, pack('N', 256), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503256), 68, 4);

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
    1 => $formatPage('wp next256 stale schema before source provenance fence'),
    2 => $page('wp next256 stale wp_options root before source provenance fence'),
    3 => $page('wp next256 stale active_plugins before source provenance fence'),
];
$recovered = [
    1 => $formatPage('wp next256 current schema after source provenance fence'),
    2 => $page('wp next256 current wp_options root after source provenance fence'),
    3 => $page('wp next256 current active_plugins after source provenance fence'),
];
$tokens = [
    $mainJournal => 'dev=8:ino=2561:size=4096:mtime=25601:snapshot=main-current',
    $usersJournal => 'dev=8:ino=2562:size=1024:mtime=25602:snapshot=users-current',
];
$headers = [
    $mainJournal => hash('sha256', 'wp main rollback header next256'),
    $usersJournal => hash('sha256', 'wp users rollback header next256'),
];
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 256, 0x57503256]));
$masterDigest = hash('sha256', 'wp next256 master source');
$masterToken = 'dev=8:ino=2560:size=96:mtime=25600:snapshot=master-current';
$databaseToken = 'dev=8:ino=2569:size=1536:mtime=25699:snapshot=database-current';
$cleanupToken = 'master-cleanup:deleted:mtime=24400:dirsync=ok';
$readerLeaseToken = 'reader-lease:shared-cache:epoch=256:opened-after-master-cleanup';
$pagerCacheSourceToken = 'pager-cache-source:epoch=256:master-journal-recovery=complete';
$readTransactionToken = 'read-transaction:epoch=256:schema=103:change-counter=256:master-current';
$schemaReparseToken = 'schema-reparse:epoch=256:schema-cookie=103:ddl=master-current';
$statementSchemaRootToken = 'statement-schema-root:epoch=256:root=1:cookie=103:sql=wp-options-current';
$currentSourceProvenanceToken = 'current-source:master-journal:epoch=256:members=2:database-token=2569:schema=103';
$oldCurrentSourceProvenanceToken = 'current-source:master-journal:epoch=242:members=2:database-token=2568:schema=102';
$currentDatabaseHeaderChangeCounterToken = 'database-header-change-counter:epoch=256:master-current:reset=complete';
$oldDatabaseHeaderChangeCounterToken = 'database-header-change-counter:epoch=246:before-master-reset';
$currentDatabaseHeaderSchemaCookieToken = 'database-header-schema-cookie:epoch=256:schema-cookie=103:master-current';
$oldDatabaseHeaderSchemaCookieToken = 'database-header-schema-cookie:epoch=246:schema-cookie=102:before-master-reset';
$base = [
    'source_id' => $sourceId,
    'epoch' => 256,
    'format_signature' => $formatSignature,
    'publication_generation' => 256,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 256,
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
    1 => ['label' => 'schema-cache-stale-schema-cookie', 'reader_id' => 'schema-cache-reader', 'image' => $recovered[1], 'current_source_provenance_token' => $currentSourceProvenanceToken, 'database_header_change_counter_token' => $currentDatabaseHeaderChangeCounterToken, 'database_header_schema_cookie_token' => $oldDatabaseHeaderSchemaCookieToken] + $base,
    2 => ['label' => 'options-root-stale-change-counter', 'reader_id' => 'options-root-reader', 'image' => $before[2], 'current_source_provenance_token' => $currentSourceProvenanceToken, 'database_header_change_counter_token' => $oldDatabaseHeaderChangeCounterToken, 'database_header_schema_cookie_token' => $currentDatabaseHeaderSchemaCookieToken] + $base,
    3 => ['label' => 'active-plugins-stale-source', 'reader_id' => 'active-plugins-reader', 'image' => $recovered[3], 'current_source_provenance_token' => $oldCurrentSourceProvenanceToken, 'database_header_change_counter_token' => $currentDatabaseHeaderChangeCounterToken, 'database_header_schema_cookie_token' => $currentDatabaseHeaderSchemaCookieToken] + $base,
];
$read = static fn (int $pageNumber, string $sourceToken = null, string $snapshotToken = null, string $schemaCookieToken = null): array => [
    'reader_id' => 'read-' . $pageNumber,
    'page_number' => $pageNumber,
    'source_id' => $sourceId,
    'epoch' => 256,
    'format_signature' => $formatSignature,
    'publication_generation' => 256,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 256,
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
    'database_header_change_counter_token' => $snapshotToken ?? $currentDatabaseHeaderChangeCounterToken,
    'database_header_schema_cookie_token' => $schemaCookieToken ?? $currentDatabaseHeaderSchemaCookieToken,
];

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::databaseHeaderSchemaCookieFence(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    $readerCache,
    [$read(1), $read(2), $read(3, $oldCurrentSourceProvenanceToken)],
    $sourceId,
    256,
    256,
    $masterDigest,
    256,
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
    $currentDatabaseHeaderChangeCounterToken,
    $currentDatabaseHeaderSchemaCookieToken,
);

$summary = [
    'scenario' => 'application-pager-master-journal-reader-cache-current-source-next256',
    'applicationUse' => 'A copied Application database reopens schema reader-cache pages unless they were opened against the same recovered master-journal current-source, database header change-counter, and schema-cookie; stale options and active_plugins reads reopen before plugin import resumes.',
    'status' => $plan['status'],
    'sourceProvenanceInvalidatedPages' => $plan['current_source_provenance_invalidated_cache_page_numbers'],
    'databaseHeaderChangeCounterInvalidatedPages' => $plan['database_header_change_counter_invalidated_cache_page_numbers'],
    'databaseHeaderSchemaCookieInvalidatedPages' => $plan['database_header_schema_cookie_invalidated_cache_page_numbers'],
    'cacheHits' => $plan['read_cache_hits'],
    'reopenReaders' => $plan['reopen_reader_ids'],
    'dependencyClosure' => 'no new support component needed; this reuses lane-local pager master-journal reader-cache, current-source provenance, statement schema-root, and read-transaction fences',
];

if ($summary['status'] !== 'pager-master-journal-reader-cache-current-source-next256'
    || $summary['sourceProvenanceInvalidatedPages'] !== [3]
    || $summary['databaseHeaderChangeCounterInvalidatedPages'] !== [2]
    || $summary['databaseHeaderSchemaCookieInvalidatedPages'] !== [1]
    || $summary['cacheHits'] !== ['read-1' => false, 'read-2' => false, 'read-3' => false]
) {
    fwrite(STDERR, "application-pager-master-journal-reader-cache-current-source-next256 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
echo "application-pager-master-journal-reader-cache-current-source-next256 self-test passed\n";
