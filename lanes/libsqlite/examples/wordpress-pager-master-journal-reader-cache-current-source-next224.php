<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next224.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next224-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'wp-next224-current-source';
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$members = [$mainJournal, $usersJournal];
$mapDigest = static function (array $map): string {
    ksort($map, SORT_STRING);
    $parts = [];
    foreach ($map as $member => $value) {
        $parts[] = $member . '=' . $value;
    }

    return hash('sha256', implode('|', $parts));
};
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$formatPage = static function (string $label) use ($pageSize): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr(4), 20, 1);
    $page = substr_replace($page, pack('N', 2), 56, 4);
    $page = substr_replace($page, pack('N', 224), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503234), 68, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$before = [
    1 => $formatPage('wp next224 stale schema before reader lease'),
    2 => $page('wp next224 stale wp_options root before reader lease'),
    3 => $page('wp next224 stale active_plugins reader before lease'),
];
$recovered = [
    1 => $formatPage('wp next224 current schema after reader lease'),
    2 => $page('wp next224 current wp_options root after reader lease'),
    3 => $page('wp next224 current active_plugins after reader lease'),
];
$tokens = [
    $mainJournal => 'dev=8:ino=2241:size=4096:mtime=22401:generation=main-current',
    $usersJournal => 'dev=8:ino=2242:size=1024:mtime=22402:generation=users-current',
];
$headers = [
    $mainJournal => hash('sha256', 'wp main rollback header next224'),
    $usersJournal => hash('sha256', 'wp users rollback header next224'),
];
$recoveredDigest = static function (array $pages) use ($pageSize): string {
    ksort($pages, SORT_NUMERIC);
    $parts = [];
    foreach ($pages as $pageNumber => $image) {
        $parts[] = $pageNumber . ':' . hash('sha256', substr($image, 0, $pageSize));
    }

    return hash('sha256', implode('|', $parts));
};
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 224, 0x57503234]));
$masterDigest = hash('sha256', 'wp next224 master source');
$masterToken = 'dev=8:ino=2240:size=96:mtime=22400:generation=master-current';
$databaseToken = 'dev=8:ino=2249:size=1536:mtime=22499:generation=database-current';
$cleanupToken = 'master-cleanup:deleted:mtime=22500:dirsync=ok';
$readerLeaseToken = 'reader-lease:shared-cache:epoch=224:opened-after-master-cleanup';
$oldReaderLeaseToken = 'reader-lease:shared-cache:epoch=223:opened-before-master-cleanup';
$base = [
    'source_id' => $sourceId,
    'epoch' => 224,
    'format_signature' => $formatSignature,
    'publication_generation' => 224,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 224,
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
    1 => ['label' => 'schema-cache', 'reader_id' => 'schema-cache-reader', 'image' => $recovered[1]] + $base,
    2 => ['label' => 'options-root-cache', 'reader_id' => 'options-root-reader', 'image' => $before[2]] + $base,
    3 => ['label' => 'active-plugins-stale-reader-lease', 'reader_id' => 'active-plugins-reader', 'image' => $recovered[3], 'reader_lease_token' => $oldReaderLeaseToken] + $base,
];
$read = static fn (int $pageNumber): array => [
    'reader_id' => 'read-' . $pageNumber,
    'page_number' => $pageNumber,
    'source_id' => $sourceId,
    'epoch' => 224,
    'format_signature' => $formatSignature,
    'publication_generation' => 224,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => 224,
    'recovered_page_set_digest' => $recoveredDigest($recovered),
    'member_journal_token_digest' => $mapDigest($tokens),
    'member_journal_header_digest' => $mapDigest($headers),
    'master_member_order_digest' => hash('sha256', implode("\n", $members)),
    'master_journal_file_token' => $masterToken,
    'master_journal_bytes_digest' => hash('sha256', $masterBytes),
    'database_file_token' => $databaseToken,
    'master_journal_cleanup_token' => $cleanupToken,
    'reader_lease_token' => $readerLeaseToken,
];

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext224(
    $database,
    $master,
    $masterBytes,
    implode('', $before),
    $pageSize,
    $recovered,
    $readerCache,
    [$read(1), $read(2), $read(3)],
    $sourceId,
    224,
    224,
    $masterDigest,
    224,
    $tokens,
    $headers,
    $masterToken,
    $databaseToken,
    $cleanupToken,
    $readerLeaseToken,
);

$summary = [
    'scenario' => 'wordpress-pager-master-journal-reader-cache-current-source-next224',
    'wordpressUse' => 'A copied WordPress database keeps schema/options reader-cache pages only when their shared-cache reader lease was opened after master-journal cleanup; active_plugins readers pinned before cleanup reopen.',
    'status' => $plan['status'],
    'readerLeaseInvalidatedPages' => $plan['reader_lease_invalidated_cache_page_numbers'],
    'cacheHits' => $plan['read_cache_hits'],
    'reopenReaders' => $plan['reopen_reader_ids'],
    'dependencyClosure' => 'no new support component needed; this reuses lane-local pager master-journal reader-cache cleanup-token and reader-ticket evidence',
];

if ($summary['status'] !== 'pager-master-journal-reader-cache-current-source-next224'
    || $summary['readerLeaseInvalidatedPages'] !== [3]
    || $summary['cacheHits'] !== ['read-1' => true, 'read-2' => true, 'read-3' => false]
) {
    fwrite(STDERR, "wordpress-pager-master-journal-reader-cache-current-source-next224 self-test failed\n");
    exit(1);
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
echo "wordpress-pager-master-journal-reader-cache-current-source-next224 self-test passed\n";
