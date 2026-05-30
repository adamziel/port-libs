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
require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';

$pageSize = 512;
$database = '/srv/www/wp-content/database/wp-options-next231.sqlite';
$usersDatabase = '/srv/www/wp-content/database/wp-users-next231.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'wp-options-reader-cache-freelist-next231';
$publication = 231;
$recoverySequence = 231;
$counter = 63;
$freelistTrunk = 4;
$freelistCount = 2;
$oldFreelistTrunk = 0;
$oldFreelistCount = 0;
$pageCount = 4;
$masterDigest = hash('sha256', 'wp-next231-master-source');
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$masterToken = 'dev=8:ino=2231:size=96:mtime=23100:generation=master-current';
$databaseToken = 'dev=8:ino=9231:size=2048:mtime=23199:generation=database-current';
$currentHeader = hash('sha256', 'wp-options:schema=231:change-counter=63:version-valid-for=63:page-count=4;freelist=4/2');
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$formatPage = static function (string $label) use ($pageSize, $freelistTrunk, $freelistCount): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr(4), 20, 1);
    $page = substr_replace($page, pack('N', 2), 56, 4);
    $page = substr_replace($page, pack('N', 231), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503231), 68, 4);
    $page = substr_replace($page, pack('N', 63), 24, 4);
    $page = substr_replace($page, pack('N', $freelistTrunk), 32, 4);
    $page = substr_replace($page, pack('N', $freelistCount), 36, 4);
    $page = substr_replace($page, pack('N', 63), 92, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$before = [
    1 => $formatPage('next231 wp_options schema before freelist recovery'),
    2 => $page('next231 stale alloptions before freelist recovery'),
    3 => $page('next231 stale active_plugins before freelist recovery'),
    4 => $page('next231 stale freelist trunk before recovery'),
];
$recovered = [
    1 => $formatPage('next231 current wp_options schema after freelist recovery'),
    2 => $page('next231 current alloptions after freelist recovery'),
    3 => $page('next231 current active_plugins after freelist recovery'),
    4 => $page('next231 current freelist trunk after recovery'),
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
    $mainJournal => 'dev=8:ino=3331:size=4096:mtime=23101:generation=options-current',
    $usersJournal => 'dev=8:ino=4431:size=1024:mtime=23102:generation=users-current',
];
$memberHeaders = [
    $mainJournal => hash('sha256', 'options-rollback-header-231'),
    $usersJournal => hash('sha256', 'users-rollback-header-231'),
];
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 231, 0x57503231]));
$recoveredPageSet = $recoveredDigest($recovered);
$memberTokenDigest = $mapDigest($memberTokens);
$memberHeaderDigest = $mapDigest($memberHeaders);
$memberOrderDigest = hash('sha256', implode("\n", [$mainJournal, $usersJournal]));
$masterBytesDigest = hash('sha256', $masterBytes);
$databaseBytes = implode('', $before);
$cacheEntry = static fn (string $label, string $image, int $trunk, int $count) => [
    'label' => $label,
    'image' => $image,
    'reader_id' => $label . '-reader',
    'source_id' => $sourceId,
    'epoch' => 231,
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
    'database_change_counter' => $counter,
    'version_valid_for' => $counter,
    'freelist_trunk_page' => $trunk,
    'freelist_page_count' => $count,
];
$readTicket = static fn (int $pageNumber, int $trunk, int $count) => [
    'reader_id' => 'wp-read-' . $pageNumber,
    'page_number' => $pageNumber,
    'source_id' => $sourceId,
    'epoch' => 231,
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
    'database_change_counter' => $counter,
    'version_valid_for' => $counter,
    'freelist_trunk_page' => $trunk,
    'freelist_page_count' => $count,
];

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantPinnedSharedReaderFence(
    $database,
    $master,
    $masterBytes,
    $databaseBytes,
    $pageSize,
    $recovered,
    [
        1 => $cacheEntry('schema', $recovered[1], $freelistTrunk, $freelistCount),
        2 => $cacheEntry('alloptions', $before[2], $freelistTrunk, $freelistCount),
        3 => $cacheEntry('active-plugins', $recovered[3], $oldFreelistTrunk, $oldFreelistCount),
        4 => $cacheEntry('freelist-trunk', $recovered[4], 0, $freelistCount),
    ],
    [
        $readTicket(1, $freelistTrunk, $freelistCount),
        $readTicket(2, $freelistTrunk, $freelistCount),
        $readTicket(3, $oldFreelistTrunk, $oldFreelistCount),
        $readTicket(4, 0, $freelistCount),
    ],
    $sourceId,
    231,
    $publication,
    $masterDigest,
    $recoverySequence,
    $memberTokens,
    $memberHeaders,
    $masterToken,
    $databaseToken,
    $currentHeader,
    $pageCount,
    $counter,
    $counter,
    $freelistTrunk,
    $freelistCount,
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'pager-master-journal-reader-cache-current-source-next231');
    assert($plan['retained_cache_page_numbers'] === [1]);
    assert($plan['refreshed_cache_page_numbers'] === [2]);
    assert($plan['freelist_header_invalidated_cache_page_numbers'] === [3, 4]);
    assert($plan['read_cache_hits']['wp-read-3'] === false);
    assert($plan['read_cache_hits']['wp-read-4'] === false);
    echo "application-pager-master-journal-reader-cache-current-source-next231 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'freelistTrunk' => $plan['current_freelist_trunk_page'],
    'freelistCount' => $plan['current_freelist_page_count'],
    'retained' => $plan['retained_cache_page_numbers'],
    'refreshed' => $plan['refreshed_cache_page_numbers'],
    'freelistInvalidated' => $plan['freelist_header_invalidated_cache_page_numbers'],
    'reopenReaders' => $plan['reopen_reader_ids'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
