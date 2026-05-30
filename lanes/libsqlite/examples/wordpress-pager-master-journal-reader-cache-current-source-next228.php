<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next228.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next228-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'wp-next228-current-source';
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$members = [$mainJournal, $usersJournal];
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$formatPage = static function (string $label) use ($pageSize): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr(4), 20, 1);
    $page = substr_replace($page, pack('N', 2), 56, 4);
    $page = substr_replace($page, pack('N', 228), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503238), 68, 4);

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
$before = [
    1 => $formatPage('wp next228 stale schema before payload fence'),
    2 => $page('wp next228 stale wp_options root before payload fence'),
    3 => $page('wp next228 stale active_plugins before payload fence'),
];
$recovered = [
    1 => $formatPage('wp next228 current schema after payload fence'),
    2 => $page('wp next228 current wp_options root after payload fence'),
    3 => $page('wp next228 current active_plugins after payload fence'),
];
$currentDigest = static fn (int $pageNumber): string => hash('sha256', $recovered[$pageNumber] ?? $before[$pageNumber]);
$recoveredDigest = static function (array $pages): string {
    ksort($pages, SORT_NUMERIC);
    $parts = [];
    foreach ($pages as $pageNumber => $image) {
        $parts[] = $pageNumber . ':' . hash('sha256', $image);
    }

    return hash('sha256', implode('|', $parts));
};
$tokens = [
    $mainJournal => 'dev=8:ino=2281:size=4096:mtime=22801:generation=main-current',
    $usersJournal => 'dev=8:ino=2282:size=1024:mtime=22802:generation=users-current',
];
$headers = [
    $mainJournal => hash('sha256', 'wp main rollback header next228'),
    $usersJournal => hash('sha256', 'wp users rollback header next228'),
];
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 228, 0x57503238]));
$masterDigest = hash('sha256', 'wp next228 master source');
$masterToken = 'dev=8:ino=2280:size=96:mtime=22800:generation=master-current';
$databaseToken = 'dev=8:ino=2289:size=1536:mtime=22899:generation=database-current';
$cleanupToken = 'master-cleanup:deleted:mtime=22900:dirsync=ok';
$readerLeaseToken = 'reader-lease:shared-cache:epoch=228:opened-after-master-cleanup';
$base = [
    'source_id' => $sourceId,
    'epoch' => 228,
    'format_signature' => $formatSignature,
    'publication_generation' => 228,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 228,
    'recovered_page_set_digest' => $recoveredDigest($recovered),
    'member_journal_tokens' => $tokens,
    'member_journal_header_digests' => $headers,
    'master_member_order_digest' => hash('sha256', implode("\n", $members)),
    'master_journal_file_token' => $masterToken,
    'master_journal_bytes_digest' => hash('sha256', $masterBytes),
    'database_file_token' => $databaseToken,
    'master_journal_cleanup_token' => $cleanupToken,
    'reader_lease_token' => $readerLeaseToken,
];
$readerCache = [
    1 => ['label' => 'schema-cache', 'reader_id' => 'schema-cache-reader', 'image' => $recovered[1], 'page_payload_digest' => $currentDigest(1)] + $base,
    2 => ['label' => 'options-root-cache', 'reader_id' => 'options-root-reader', 'image' => $before[2], 'page_payload_digest' => $currentDigest(2)] + $base,
    3 => ['label' => 'active-plugins-stale-payload', 'reader_id' => 'active-plugins-reader', 'image' => $recovered[3], 'page_payload_digest' => hash('sha256', 'old-active-plugins-payload')] + $base,
];
$read = static fn (int $pageNumber): array => [
    'reader_id' => 'read-' . $pageNumber,
    'page_number' => $pageNumber,
    'source_id' => $sourceId,
    'epoch' => 228,
    'format_signature' => $formatSignature,
    'publication_generation' => 228,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 228,
    'recovered_page_set_digest' => $recoveredDigest($recovered),
    'member_journal_token_digest' => $mapDigest($tokens),
    'member_journal_header_digest' => $mapDigest($headers),
    'master_member_order_digest' => hash('sha256', implode("\n", $members)),
    'master_journal_file_token' => $masterToken,
    'master_journal_bytes_digest' => hash('sha256', $masterBytes),
    'database_file_token' => $databaseToken,
    'master_journal_cleanup_token' => $cleanupToken,
    'reader_lease_token' => $readerLeaseToken,
    'page_payload_digest' => $currentDigest($pageNumber),
];

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantReaderIdOrderingFence(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    $readerCache,
    [$read(1), $read(2), $read(3)],
    $sourceId,
    228,
    228,
    $masterDigest,
    228,
    $tokens,
    $headers,
    $masterToken,
    $databaseToken,
    $cleanupToken,
    $readerLeaseToken,
);

$summary = [
    'scenario' => 'wordpress-pager-master-journal-reader-cache-current-source-next228',
    'wordpressUse' => 'A copied WordPress database keeps schema/options reader-cache pages only when the per-page payload digest matches the recovered master-journal source; stale active_plugins payload cache reopens before plugin import resumes.',
    'status' => $plan['status'],
    'pagePayloadInvalidatedPages' => $plan['page_payload_invalidated_cache_page_numbers'],
    'cacheHits' => $plan['read_cache_hits'],
    'reopenReaders' => $plan['reopen_reader_ids'],
    'dependencyClosure' => 'no new support component needed; this reuses lane-local pager master-journal reader-cache current-source and reader-lease evidence',
];

if ($summary['status'] !== 'pager-master-journal-reader-cache-current-source-next228'
    || $summary['pagePayloadInvalidatedPages'] !== [3]
    || $summary['cacheHits'] !== ['read-1' => true, 'read-2' => true, 'read-3' => false]
) {
    fwrite(STDERR, "wordpress-pager-master-journal-reader-cache-current-source-next228 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
echo "wordpress-pager-master-journal-reader-cache-current-source-next228 self-test passed\n";
