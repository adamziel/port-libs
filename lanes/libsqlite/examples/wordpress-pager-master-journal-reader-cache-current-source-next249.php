<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next249.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next249-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'wp-next249-current-source';
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$members = [$mainJournal, $usersJournal];
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$formatPage = static function (string $label) use ($pageSize): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr(4), 20, 1);
    $page = substr_replace($page, pack('N', 2), 56, 4);
    $page = substr_replace($page, pack('N', 249), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503249), 68, 4);

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
    1 => $formatPage('wp next249 stale schema before source handoff'),
    2 => $page('wp next249 stale wp_options root before source handoff'),
    3 => $page('wp next249 stale active_plugins before source handoff'),
];
$recovered = [
    1 => $formatPage('wp next249 current schema after source handoff'),
    2 => $page('wp next249 current wp_options root after source handoff'),
    3 => $page('wp next249 current active_plugins after source handoff'),
];
$tokens = [
    $mainJournal => 'dev=8:ino=2491:size=4096:mtime=24901:generation=main-current',
    $usersJournal => 'dev=8:ino=2492:size=1024:mtime=24902:generation=users-current',
];
$headers = [
    $mainJournal => hash('sha256', 'wp main rollback header next249'),
    $usersJournal => hash('sha256', 'wp users rollback header next249'),
];
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 249, 0x57503249]));
$masterDigest = hash('sha256', 'wp next249 master source');
$masterToken = 'dev=8:ino=2490:size=96:mtime=24900:generation=master-current';
$databaseToken = 'dev=8:ino=2499:size=1536:mtime=24999:generation=database-current';
$cleanupToken = 'master-cleanup:deleted:mtime=25000:dirsync=ok';
$readerLeaseToken = 'reader-lease:shared-cache:epoch=249:opened-after-master-cleanup';
$pagerCacheSourceToken = 'pager-cache-source:epoch=249:master-journal-recovery=complete';
$readTransactionToken = 'read-transaction:epoch=249:schema=109:change-counter=249:master-current';
$schemaReparseToken = 'schema-reparse:epoch=249:schema-cookie=109:ddl=master-current';
$statementSchemaRootToken = 'statement-schema-root:epoch=249:root=1:cookie=109:sql=wp-options-current';
$currentSourceProvenanceToken = 'current-source:master-journal:epoch=249:members=2:database-token=2499:schema=109';
$currentVersionVectorToken = 'version-vector:main=249/109/users=44/12/options-root=2/autoload=10';
$currentHandoffToken = 'reader-cache-handoff:epoch=249:master=249:source=wp-options-recovered:lease=771';
$oldHandoffToken = 'reader-cache-handoff:epoch=248:master=248:source=wp-options-stale:lease=770';
$base = [
    'source_id' => $sourceId,
    'epoch' => 249,
    'format_signature' => $formatSignature,
    'publication_generation' => 249,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 249,
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
    'current_source_version_vector_token' => $currentVersionVectorToken,
];
$readerCache = [
    1 => ['label' => 'schema-cache', 'reader_id' => 'schema-cache-reader', 'image' => $recovered[1], 'reader_cache_source_handoff_token' => $currentHandoffToken] + $base,
    2 => ['label' => 'options-root-cache', 'reader_id' => 'options-root-reader', 'image' => $before[2], 'reader_cache_source_handoff_token' => $currentHandoffToken] + $base,
    3 => ['label' => 'active-plugins-stale-handoff', 'reader_id' => 'active-plugins-reader', 'image' => $recovered[3], 'reader_cache_source_handoff_token' => $oldHandoffToken] + $base,
];
$read = static fn (int $pageNumber, ?string $handoffToken = null): array => [
    'reader_id' => 'read-' . $pageNumber,
    'page_number' => $pageNumber,
    'source_id' => $sourceId,
    'epoch' => 249,
    'format_signature' => $formatSignature,
    'publication_generation' => 249,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 249,
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
    'current_source_version_vector_token' => $currentVersionVectorToken,
    'reader_cache_source_handoff_token' => $handoffToken ?? $currentHandoffToken,
];

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantReaderRefreshReceipt(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    $readerCache,
    [$read(1), $read(2), $read(3, $oldHandoffToken)],
    $sourceId,
    249,
    249,
    $masterDigest,
    249,
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
    $currentHandoffToken,
);

$summary = [
    'scenario' => 'wordpress-pager-master-journal-reader-cache-current-source-next249',
    'wordpressUse' => 'A copied WordPress database keeps schema/options reader-cache pages only after the recovered master-journal source handoff token matches; stale active_plugins reads reopen before plugin import resumes.',
    'status' => $plan['status'],
    'handoffInvalidatedPages' => $plan['reader_cache_source_handoff_invalidated_cache_page_numbers'],
    'cacheHits' => $plan['read_cache_hits'],
    'reopenReaders' => $plan['reopen_reader_ids'],
    'dependencyClosure' => 'no new support component needed; this reuses lane-local pager master-journal reader-cache and current-source version-vector fences',
];

if ($summary['status'] !== 'pager-master-journal-reader-cache-current-source-next249'
    || $summary['handoffInvalidatedPages'] !== [3]
    || $summary['cacheHits'] !== ['read-1' => true, 'read-2' => true, 'read-3' => false]
) {
    fwrite(STDERR, "wordpress-pager-master-journal-reader-cache-current-source-next249 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
echo "wordpress-pager-master-journal-reader-cache-current-source-next249 self-test passed\n";
