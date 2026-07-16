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

$pageSize = 512;
$database = '/srv/www/wp-content/database/wp-options-next212.sqlite';
$usersDatabase = '/srv/www/wp-content/database/wp-users-next212.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'wp-options-reader-cache-database-token-next212';
$publication = 212;
$recoverySequence = 212;
$masterDigest = hash('sha256', 'wp-next212-master-source');
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$masterToken = 'dev=8:ino=2212:size=96:mtime=21200:generation=master-current';
$databaseToken = 'dev=8:ino=9212:size=2048:mtime=21299:generation=database-current';
$oldDatabaseToken = 'dev=8:ino=9212:size=2048:mtime=21298:generation=database-prior';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$formatPage = static function (string $label) use ($pageSize): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr(4), 20, 1);
    $page = substr_replace($page, pack('N', 2), 56, 4);
    $page = substr_replace($page, pack('N', 212), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503232), 68, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$before = [
    1 => $formatPage('next212 wp_options schema before database token recovery'),
    2 => $page('next212 stale alloptions before database token recovery'),
    3 => $page('next212 stale active_plugins before database token recovery'),
    4 => $page('next212 stale rewrite_rules before database token recovery'),
];
$recovered = [
    1 => $formatPage('next212 current wp_options schema after database token recovery'),
    2 => $page('next212 current alloptions after database token recovery'),
    3 => $page('next212 current active_plugins after database token recovery'),
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
    $mainJournal => 'dev=8:ino=3312:size=4096:mtime=21201:generation=options-current',
    $usersJournal => 'dev=8:ino=4412:size=1024:mtime=21202:generation=users-current',
];
$memberHeaders = [
    $mainJournal => hash('sha256', 'options-rollback-header-212'),
    $usersJournal => hash('sha256', 'users-rollback-header-212'),
];
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 212, 0x57503232]));
$recoveredPageSet = $recoveredDigest($recovered);
$memberTokenDigest = $mapDigest($memberTokens);
$memberHeaderDigest = $mapDigest($memberHeaders);
$memberOrderDigest = hash('sha256', implode("\n", [$mainJournal, $usersJournal]));
$masterBytesDigest = hash('sha256', $masterBytes);
$databaseBytes = implode('', $before);
$cacheEntry = static fn (string $label, string $image, string $token) => [
    'label' => $label,
    'image' => $image,
    'reader_id' => $label . '-reader',
    'source_id' => $sourceId,
    'epoch' => 212,
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
    'database_file_token' => $token,
];
$readTicket = static fn (int $pageNumber, string $token) => [
    'reader_id' => 'wp-read-' . $pageNumber,
    'page_number' => $pageNumber,
    'source_id' => $sourceId,
    'epoch' => 212,
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
    'database_file_token' => $token,
];

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantPublicationGenerationFence(
    $database,
    $master,
    $masterBytes,
    $databaseBytes,
    $pageSize,
    $recovered,
    [
        1 => $cacheEntry('schema', $recovered[1], $databaseToken),
        2 => $cacheEntry('alloptions', $before[2], $databaseToken),
        3 => $cacheEntry('active-plugins', $recovered[3], $oldDatabaseToken),
        4 => $cacheEntry('rewrite-rules', $before[4], $oldDatabaseToken),
    ],
    [
        $readTicket(1, $databaseToken),
        $readTicket(2, $databaseToken),
        $readTicket(3, $oldDatabaseToken),
        $readTicket(4, $oldDatabaseToken),
    ],
    $sourceId,
    212,
    $publication,
    $masterDigest,
    $recoverySequence,
    $memberTokens,
    $memberHeaders,
    $masterToken,
    $databaseToken,
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'pager-master-journal-reader-cache-current-source-next212');
    assert($plan['retained_cache_page_numbers'] === [1]);
    assert($plan['refreshed_cache_page_numbers'] === [2]);
    assert($plan['database_file_token_invalidated_cache_page_numbers'] === [3]);
    assert($plan['read_cache_hits']['wp-read-3'] === false);
    echo "application-pager-master-journal-reader-cache-current-source-next212 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'databaseFileToken' => $plan['current_database_file_token'],
    'retained' => $plan['retained_cache_page_numbers'],
    'refreshed' => $plan['refreshed_cache_page_numbers'],
    'databaseTokenInvalidated' => $plan['database_file_token_invalidated_cache_page_numbers'],
    'reopenReaders' => $plan['reopen_reader_ids'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
