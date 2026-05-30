<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next246.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next246-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'wp-next246-current-source';
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$members = [$mainJournal, $usersJournal];
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$formatPage = static function (string $label) use ($pageSize): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr(4), 20, 1);
    $page = substr_replace($page, pack('N', 2), 56, 4);
    $page = substr_replace($page, pack('N', 246), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503246), 68, 4);

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
    1 => $formatPage('wp next246 stale schema before version vector fence'),
    2 => $page('wp next246 stale wp_options root before version vector fence'),
    3 => $page('wp next246 stale active_plugins before version vector fence'),
];
$recovered = [
    1 => $formatPage('wp next246 current schema after version vector fence'),
    2 => $page('wp next246 current wp_options root after version vector fence'),
    3 => $page('wp next246 current active_plugins after version vector fence'),
];
$tokens = [
    $mainJournal => 'dev=8:ino=2461:size=4096:mtime=24601:generation=main-current',
    $usersJournal => 'dev=8:ino=2462:size=1024:mtime=24602:generation=users-current',
];
$headers = [
    $mainJournal => hash('sha256', 'wp main rollback header next246'),
    $usersJournal => hash('sha256', 'wp users rollback header next246'),
];
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 246, 0x57503246]));
$masterDigest = hash('sha256', 'wp next246 master source');
$masterToken = 'dev=8:ino=2460:size=96:mtime=24600:generation=master-current';
$databaseToken = 'dev=8:ino=2469:size=1536:mtime=24699:generation=database-current';
$cleanupToken = 'master-cleanup:deleted:mtime=24700:dirsync=ok';
$readerLeaseToken = 'reader-lease:shared-cache:epoch=246:opened-after-master-cleanup';
$pagerCacheSourceToken = 'pager-cache-source:epoch=246:master-journal-recovery=complete';
$readTransactionToken = 'read-transaction:epoch=246:schema=106:change-counter=246:master-current';
$schemaReparseToken = 'schema-reparse:epoch=246:schema-cookie=106:ddl=master-current';
$statementSchemaRootToken = 'statement-schema-root:epoch=246:root=1:cookie=106:sql=wp-options-current';
$currentSourceProvenanceToken = 'current-source:master-journal:epoch=246:members=2:database-token=2469:schema=106';
$currentVersionVectorToken = 'version-vector:main=246/106/users=41/9/options-root=2/autoload=7';
$oldVersionVectorToken = 'version-vector:main=245/105/users=40/8/options-root=2/autoload=6';
$base = [
    'source_id' => $sourceId,
    'epoch' => 246,
    'format_signature' => $formatSignature,
    'publication_generation' => 246,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 246,
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
    1 => ['label' => 'schema-cache', 'reader_id' => 'schema-cache-reader', 'image' => $recovered[1], 'current_source_version_vector_token' => $currentVersionVectorToken] + $base,
    2 => ['label' => 'options-root-cache', 'reader_id' => 'options-root-reader', 'image' => $before[2], 'current_source_version_vector_token' => $currentVersionVectorToken] + $base,
    3 => ['label' => 'active-plugins-stale-vector', 'reader_id' => 'active-plugins-reader', 'image' => $recovered[3], 'current_source_version_vector_token' => $oldVersionVectorToken] + $base,
];
$read = static fn (int $pageNumber, string $vectorToken = null): array => [
    'reader_id' => 'read-' . $pageNumber,
    'page_number' => $pageNumber,
    'source_id' => $sourceId,
    'epoch' => 246,
    'format_signature' => $formatSignature,
    'publication_generation' => 246,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 246,
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
    'current_source_version_vector_token' => $vectorToken ?? $currentVersionVectorToken,
];

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::planCurrentSourceVersionVectorFence(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    $readerCache,
    [$read(1), $read(2), $read(3, $oldVersionVectorToken)],
    $sourceId,
    246,
    246,
    $masterDigest,
    246,
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
    $currentVersionVectorToken,
);

$summary = [
    'scenario' => 'wordpress-pager-master-journal-reader-cache-current-source-next246',
    'wordpressUse' => 'A copied WordPress database keeps schema/options reader-cache pages only when the main and attached user database version vector matches the recovered master-journal current source; stale active_plugins reads reopen before plugin import resumes.',
    'status' => $plan['status'],
    'versionVectorInvalidatedPages' => $plan['current_source_version_vector_invalidated_cache_page_numbers'],
    'cacheHits' => $plan['read_cache_hits'],
    'reopenReaders' => $plan['reopen_reader_ids'],
    'dependencyClosure' => 'no new support component needed; this reuses lane-local pager master-journal reader-cache and current-source provenance fences',
];

if ($summary['status'] !== 'pager-master-journal-reader-cache-current-source-next246'
    || $summary['versionVectorInvalidatedPages'] !== [3]
    || $summary['cacheHits'] !== ['read-1' => true, 'read-2' => true, 'read-3' => false]
) {
    fwrite(STDERR, "wordpress-pager-master-journal-reader-cache-current-source-next246 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
echo "wordpress-pager-master-journal-reader-cache-current-source-next246 self-test passed\n";
