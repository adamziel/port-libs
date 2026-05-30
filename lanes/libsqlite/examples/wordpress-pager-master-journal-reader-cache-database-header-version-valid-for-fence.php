<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next259.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next259-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'wp-next259-current-source';
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$members = [$mainJournal, $usersJournal];
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$formatPage = static function (string $label) use ($pageSize): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr(4), 20, 1);
    $page = substr_replace($page, pack('N', 106), 40, 4);
    $page = substr_replace($page, pack('N', 2), 56, 4);
    $page = substr_replace($page, pack('N', 259), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503259), 68, 4);
    $page = substr_replace($page, pack('N', 259), 92, 4);

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
    1 => $formatPage('wp next259 stale schema before version-valid-for fence'),
    2 => $page('wp next259 stale wp_options root before version-valid-for fence'),
    3 => $page('wp next259 stale active_plugins before version-valid-for fence'),
    4 => $page('wp next259 stale cron before version-valid-for fence'),
];
$recovered = [
    1 => $formatPage('wp next259 current schema after version-valid-for fence'),
    2 => $page('wp next259 current wp_options root after version-valid-for fence'),
    3 => $page('wp next259 current active_plugins after version-valid-for fence'),
    4 => $page('wp next259 current cron after version-valid-for fence'),
];
$tokens = [
    $mainJournal => 'dev=8:ino=2591:size=4096:mtime=25901:snapshot=main-current',
    $usersJournal => 'dev=8:ino=2592:size=1024:mtime=25902:snapshot=users-current',
];
$headers = [
    $mainJournal => hash('sha256', 'wp main rollback header next259'),
    $usersJournal => hash('sha256', 'wp users rollback header next259'),
];
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 259, 0x57503259]));
$masterDigest = hash('sha256', 'wp next259 master source');
$masterToken = 'dev=8:ino=2590:size=96:mtime=25900:snapshot=master-current';
$databaseToken = 'dev=8:ino=2599:size=2048:mtime=25999:snapshot=database-current';
$cleanupToken = 'master-cleanup:deleted:mtime=25940:dirsync=ok';
$readerLeaseToken = 'reader-lease:shared-cache:epoch=259:opened-after-master-cleanup';
$pagerCacheSourceToken = 'pager-cache-source:epoch=259:master-journal-recovery=complete';
$readTransactionToken = 'read-transaction:epoch=259:schema=106:change-counter=259:master-current';
$schemaReparseToken = 'schema-reparse:epoch=259:schema-cookie=106:ddl=master-current';
$statementSchemaRootToken = 'statement-schema-root:epoch=259:root=1:cookie=106:sql=wp-options-current';
$currentSourceProvenanceToken = 'current-source:master-journal:epoch=259:members=2:database-token=2599:schema=106';
$currentDatabaseHeaderChangeCounterToken = 'database-header-change-counter:epoch=259:master-current:reset=complete';
$currentDatabaseHeaderSchemaCookieToken = 'database-header-schema-cookie:epoch=259:schema-cookie=106:master-current';
$currentDatabaseHeaderVersionValidForToken = 'database-header-version-valid-for:epoch=259:change-counter=259:schema-cookie=106:master-current';
$oldDatabaseHeaderVersionValidForToken = 'database-header-version-valid-for:epoch=252:change-counter=252:schema-cookie=105:before-master-reset';
$base = [
    'source_id' => $sourceId,
    'epoch' => 259,
    'format_signature' => $formatSignature,
    'publication_generation' => 259,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 259,
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
    'database_header_change_counter_token' => $currentDatabaseHeaderChangeCounterToken,
    'database_header_schema_cookie_token' => $currentDatabaseHeaderSchemaCookieToken,
];
$readerCache = [
    1 => ['label' => 'schema-cache-stale-version-valid-for', 'reader_id' => 'schema-cache-reader', 'image' => $recovered[1], 'database_header_version_valid_for_token' => $oldDatabaseHeaderVersionValidForToken] + $base,
    2 => ['label' => 'options-root-current', 'reader_id' => 'options-root-reader', 'image' => $recovered[2], 'database_header_version_valid_for_token' => $currentDatabaseHeaderVersionValidForToken] + $base,
    3 => ['label' => 'active-plugins-stale-version-valid-for', 'reader_id' => 'active-plugins-reader', 'image' => $recovered[3], 'database_header_version_valid_for_token' => $oldDatabaseHeaderVersionValidForToken] + $base,
    4 => ['label' => 'cron-current', 'reader_id' => 'cron-reader', 'image' => $recovered[4], 'database_header_version_valid_for_token' => $currentDatabaseHeaderVersionValidForToken] + $base,
];
$read = static fn (int $pageNumber, string $versionToken = null): array => [
    'reader_id' => 'read-' . $pageNumber,
    'page_number' => $pageNumber,
    'source_id' => $sourceId,
    'epoch' => 259,
    'format_signature' => $formatSignature,
    'publication_generation' => 259,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 259,
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
    'database_header_change_counter_token' => $currentDatabaseHeaderChangeCounterToken,
    'database_header_schema_cookie_token' => $currentDatabaseHeaderSchemaCookieToken,
    'database_header_version_valid_for_token' => $versionToken ?? $currentDatabaseHeaderVersionValidForToken,
];

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::databaseHeaderVersionValidForFence(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    $readerCache,
    [$read(1), $read(2), $read(3, $oldDatabaseHeaderVersionValidForToken), $read(4)],
    $sourceId,
    259,
    259,
    $masterDigest,
    259,
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
    $currentDatabaseHeaderVersionValidForToken,
);

$summary = [
    'scenario' => 'wordpress-pager-master-journal-reader-cache-current-source-next259',
    'wordpressUse' => 'A copied WordPress SQLite database reopens page-1 schema and active_plugins readers unless their cached header version-valid-for value matches the recovered master-journal current source before plugin import resumes.',
    'status' => $plan['status'],
    'databaseHeaderVersionValidForInvalidatedPages' => $plan['database_header_version_valid_for_invalidated_cache_page_numbers'],
    'cacheHits' => $plan['read_cache_hits'],
    'reopenReaders' => $plan['reopen_reader_ids'],
    'dependencyClosure' => 'no new support component needed; this reuses lane-local pager master-journal reader-cache and database-header current-source fences',
];

if ($summary['status'] !== 'pager-master-journal-reader-cache-current-source-next259'
    || $summary['databaseHeaderVersionValidForInvalidatedPages'] !== [1, 3]
    || $summary['cacheHits'] !== ['read-1' => false, 'read-2' => true, 'read-3' => false, 'read-4' => true]
    || $summary['reopenReaders'] !== ['read-1', 'read-3']
) {
    fwrite(STDERR, "wordpress-pager-master-journal-reader-cache-current-source-next259 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
echo "wordpress-pager-master-journal-reader-cache-current-source-next259 self-test passed\n";
