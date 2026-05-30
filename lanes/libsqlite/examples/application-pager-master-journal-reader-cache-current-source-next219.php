<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';

$pageSize = 512;
$database = '/srv/www/wp-content/database/wp-options-next219.sqlite';
$usersDatabase = '/srv/www/wp-content/database/wp-users-next219.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'wp-options-reader-cache-page-count-next219';
$publication = 219;
$recoverySequence = 219;
$masterDigest = hash('sha256', 'wp-next219-master-source');
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$masterToken = 'dev=8:ino=2219:size=96:mtime=21900:generation=master-current';
$databaseToken = 'dev=8:ino=9219:size=1536:mtime=21999:generation=database-current';
$currentHeader = hash('sha256', 'wp-options:schema=219:change-counter=90:page-count=3');
$currentPageCount = 3;
$oldPageCount = 4;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$formatPage = static function (string $label) use ($pageSize): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr(4), 20, 1);
    $page = substr_replace($page, pack('N', 2), 56, 4);
    $page = substr_replace($page, pack('N', 219), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503239), 68, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$before = [
    1 => $formatPage('next219 wp_options schema before page-count recovery'),
    2 => $page('next219 stale alloptions before page-count recovery'),
    3 => $page('next219 stale active_plugins before page-count recovery'),
    4 => $page('next219 truncated rewrite_rules before page-count recovery'),
];
$recovered = [
    1 => $formatPage('next219 current wp_options schema after page-count recovery'),
    2 => $page('next219 current alloptions after page-count recovery'),
    3 => $page('next219 current active_plugins after page-count recovery'),
];
$recoveredDigest = static function (array $pages) use ($pageSize): string {
    ksort($pages, SORT_NUMERIC);
    $parts = [];
    foreach ($pages as $pageNumber => $image) {
        if (strlen($image) !== $pageSize) {
            throw new RuntimeException('bad fixture');
        }
        $parts[] = $pageNumber . ':' . hash('sha256', $image);
    }

    return hash('sha256', implode('|', $parts));
};
$mapDigest = static function (array $map): string {
    ksort($map, SORT_STRING);
    $parts = [];
    foreach ($map as $member => $value) {
        $parts[] = $member . '=' . $value;
    }

    return hash('sha256', implode('|', $parts));
};
$memberTokens = [
    $mainJournal => 'dev=8:ino=3319:size=4096:mtime=21901:generation=options-current',
    $usersJournal => 'dev=8:ino=4419:size=1024:mtime=21902:generation=users-current',
];
$memberHeaders = [
    $mainJournal => hash('sha256', 'options-rollback-header-219'),
    $usersJournal => hash('sha256', 'users-rollback-header-219'),
];
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 219, 0x57503239]));
$recoveredPageSet = $recoveredDigest($recovered);
$memberTokenDigest = $mapDigest($memberTokens);
$memberHeaderDigest = $mapDigest($memberHeaders);
$memberOrderDigest = hash('sha256', implode("\n", [$mainJournal, $usersJournal]));
$masterBytesDigest = hash('sha256', $masterBytes);
$databaseBytes = implode('', $before);
$cacheEntry = static fn (string $label, string $image, int $pageCount) => [
    'label' => $label,
    'image' => $image,
    'reader_id' => $label . '-reader',
    'source_id' => $sourceId,
    'epoch' => 219,
    'format_signature' => $formatSignature,
    'publication_generation' => $publication,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => $recoverySequence,
    'recovered_page_set_digest' => $recoveredPageSet,
    'member_journal_tokens' => $memberTokens,
    'member_journal_header_digests' => $memberHeaders,
    'master_member_order_digest' => $memberOrderDigest,
    'master_journal_file_token' => $masterToken,
    'master_journal_bytes_digest' => $masterBytesDigest,
    'database_file_token' => $databaseToken,
    'database_header_digest' => $currentHeader,
    'database_page_count' => $pageCount,
];
$readTicket = static fn (int $pageNumber, int $pageCount) => [
    'reader_id' => 'wp-read-' . $pageNumber,
    'page_number' => $pageNumber,
    'source_id' => $sourceId,
    'epoch' => 219,
    'format_signature' => $formatSignature,
    'publication_generation' => $publication,
    'master_source_digest' => $masterDigest,
    'recovery_sequence' => $recoverySequence,
    'recovered_page_set_digest' => $recoveredPageSet,
    'member_journal_token_digest' => $memberTokenDigest,
    'member_journal_header_digest' => $memberHeaderDigest,
    'master_member_order_digest' => $memberOrderDigest,
    'master_journal_file_token' => $masterToken,
    'master_journal_bytes_digest' => $masterBytesDigest,
    'database_file_token' => $databaseToken,
    'database_header_digest' => $currentHeader,
    'database_page_count' => $pageCount,
];

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantReaderCacheRetentionFence(
    $database,
    $master,
    $masterBytes,
    $databaseBytes,
    $pageSize,
    $recovered,
    [
        1 => $cacheEntry('schema', $recovered[1], $currentPageCount),
        2 => $cacheEntry('alloptions', $before[2], $currentPageCount),
        3 => $cacheEntry('active-plugins', $recovered[3], $oldPageCount),
        4 => $cacheEntry('rewrite-rules', $before[4], $oldPageCount),
    ],
    [
        $readTicket(1, $currentPageCount),
        $readTicket(2, $currentPageCount),
        $readTicket(3, $oldPageCount),
        $readTicket(4, $oldPageCount),
    ],
    $sourceId,
    219,
    $publication,
    $masterDigest,
    $recoverySequence,
    $memberTokens,
    $memberHeaders,
    $masterToken,
    $databaseToken,
    $currentHeader,
    $currentPageCount,
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'pager-master-journal-reader-cache-current-source-next219');
    assert($plan['retained_cache_page_numbers'] === [1]);
    assert($plan['refreshed_cache_page_numbers'] === [2]);
    assert($plan['database_page_count_invalidated_cache_page_numbers'] === [3, 4]);
    assert($plan['database_page_count_truncated_cache_page_numbers'] === [4]);
    assert($plan['read_cache_hits']['wp-read-4'] === false);
    echo "application-pager-master-journal-reader-cache-current-source-next219 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'databasePageCount' => $plan['current_database_page_count'],
    'retained' => $plan['retained_cache_page_numbers'],
    'refreshed' => $plan['refreshed_cache_page_numbers'],
    'pageCountInvalidated' => $plan['database_page_count_invalidated_cache_page_numbers'],
    'truncated' => $plan['database_page_count_truncated_cache_page_numbers'],
    'reopenReaders' => $plan['reopen_reader_ids'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
