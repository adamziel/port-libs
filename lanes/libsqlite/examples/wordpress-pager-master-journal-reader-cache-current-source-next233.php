<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next233.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next233-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'wp-next233-current-source';
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$members = [$mainJournal, $usersJournal];
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$formatPage = static function (string $label) use ($pageSize): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr(4), 20, 1);
    $page = substr_replace($page, pack('N', 2), 56, 4);
    $page = substr_replace($page, pack('N', 233), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503233), 68, 4);

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
    1 => $formatPage('wp next233 stale schema before read transaction fence'),
    2 => $page('wp next233 stale wp_options root before read transaction fence'),
    3 => $page('wp next233 stale active_plugins before read transaction fence'),
];
$recovered = [
    1 => $formatPage('wp next233 current schema after read transaction fence'),
    2 => $page('wp next233 current wp_options root after read transaction fence'),
    3 => $page('wp next233 current active_plugins after read transaction fence'),
];
$tokens = [
    $mainJournal => 'dev=8:ino=2331:size=4096:mtime=23301:generation=main-current',
    $usersJournal => 'dev=8:ino=2332:size=1024:mtime=23302:generation=users-current',
];
$headers = [
    $mainJournal => hash('sha256', 'wp main rollback header next233'),
    $usersJournal => hash('sha256', 'wp users rollback header next233'),
];
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 233, 0x57503233]));
$masterDigest = hash('sha256', 'wp next233 master source');
$masterToken = 'dev=8:ino=2330:size=96:mtime=23300:generation=master-current';
$databaseToken = 'dev=8:ino=2339:size=1536:mtime=23399:generation=database-current';
$cleanupToken = 'master-cleanup:deleted:mtime=23400:dirsync=ok';
$readerLeaseToken = 'reader-lease:shared-cache:epoch=233:opened-after-master-cleanup';
$pagerCacheSourceToken = 'pager-cache-source:epoch=233:master-journal-recovery=complete';
$readTransactionToken = 'read-transaction:epoch=233:schema=93:change-counter=233:master-current';
$oldReadTransactionToken = 'read-transaction:epoch=232:schema=92:change-counter=232:before-master-current';
$base = [
    'source_id' => $sourceId,
    'epoch' => 233,
    'format_signature' => $formatSignature,
    'publication_generation' => 233,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 233,
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
];
$readerCache = [
    1 => ['label' => 'schema-cache', 'reader_id' => 'schema-cache-reader', 'image' => $recovered[1], 'read_transaction_token' => $readTransactionToken] + $base,
    2 => ['label' => 'options-root-cache', 'reader_id' => 'options-root-reader', 'image' => $before[2], 'read_transaction_token' => $readTransactionToken] + $base,
    3 => ['label' => 'active-plugins-stale-read-transaction', 'reader_id' => 'active-plugins-reader', 'image' => $recovered[3], 'read_transaction_token' => $oldReadTransactionToken] + $base,
];
$read = static fn (int $pageNumber, string $transaction = null): array => [
    'reader_id' => 'read-' . $pageNumber,
    'page_number' => $pageNumber,
    'source_id' => $sourceId,
    'epoch' => 233,
    'format_signature' => $formatSignature,
    'publication_generation' => 233,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 233,
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
    'read_transaction_token' => $transaction ?? $readTransactionToken,
];

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext233(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    $readerCache,
    [$read(1), $read(2), $read(3, $oldReadTransactionToken)],
    $sourceId,
    233,
    233,
    $masterDigest,
    233,
    $tokens,
    $headers,
    $masterToken,
    $databaseToken,
    $cleanupToken,
    $readerLeaseToken,
    $pagerCacheSourceToken,
    $readTransactionToken,
);

$summary = [
    'scenario' => 'wordpress-pager-master-journal-reader-cache-current-source-next233',
    'wordpressUse' => 'A copied WordPress database keeps schema/options reader-cache pages only when their read-transaction ticket was opened against the recovered master-journal source; stale active_plugins reads reopen before plugin import resumes.',
    'status' => $plan['status'],
    'readTransactionInvalidatedPages' => $plan['read_transaction_invalidated_cache_page_numbers'],
    'cacheHits' => $plan['read_cache_hits'],
    'reopenReaders' => $plan['reopen_reader_ids'],
    'dependencyClosure' => 'no new support component needed; this reuses lane-local pager master-journal reader-cache current-source and pager-cache source fences',
];

if ($summary['status'] !== 'pager-master-journal-reader-cache-current-source-next233'
    || $summary['readTransactionInvalidatedPages'] !== [3]
    || $summary['cacheHits'] !== ['read-1' => true, 'read-2' => true, 'read-3' => false]
) {
    fwrite(STDERR, "wordpress-pager-master-journal-reader-cache-current-source-next233 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
echo "wordpress-pager-master-journal-reader-cache-current-source-next233 self-test passed\n";
