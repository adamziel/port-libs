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

$pageSize = 512;
$database = '/srv/www/wp-content/database/wp-options-next213.sqlite';
$usersDatabase = '/srv/www/wp-content/database/wp-users-next213.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'wp-options-reader-cache-header-digest-next213';
$publication = 213;
$recoverySequence = 213;
$masterDigest = hash('sha256', 'wp-next213-master-source');
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$masterToken = 'dev=8:ino=2213:size=96:mtime=21300:generation=master-current';
$databaseToken = 'dev=8:ino=9213:size=2048:mtime=21399:generation=database-current';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$formatPage = static function (string $label, int $counter) use ($pageSize): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr(4), 20, 1);
    $page = substr_replace($page, pack('N', $counter), 24, 4);
    $page = substr_replace($page, pack('N', 2), 56, 4);
    $page = substr_replace($page, pack('N', 213), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503233), 68, 4);
    $page = substr_replace($page, pack('N', 71), 40, 4);
    $page = substr_replace($page, pack('N', $counter), 92, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$headerDigest = static fn (string $pageOne): string => hash('sha256', substr($pageOne, 0, 100));
$before = [
    1 => $formatPage('next213 wp_options schema before header digest recovery', 212),
    2 => $page('next213 stale alloptions before header digest recovery'),
    3 => $page('next213 stale active_plugins before header digest recovery'),
];
$recovered = [
    1 => $formatPage('next213 current wp_options schema after header digest recovery', 213),
    2 => $page('next213 current alloptions after header digest recovery'),
    3 => $page('next213 current active_plugins after header digest recovery'),
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
    $mainJournal => 'dev=8:ino=3313:size=4096:mtime=21301:generation=options-current',
    $usersJournal => 'dev=8:ino=4413:size=1024:mtime=21302:generation=users-current',
];
$memberHeaders = [
    $mainJournal => hash('sha256', 'options-rollback-header-213'),
    $usersJournal => hash('sha256', 'users-rollback-header-213'),
];
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 213, 0x57503233]));
$recoveredPageSet = $recoveredDigest($recovered);
$memberTokenDigest = $mapDigest($memberTokens);
$memberHeaderDigest = $mapDigest($memberHeaders);
$memberOrderDigest = hash('sha256', implode("\n", [$mainJournal, $usersJournal]));
$masterBytesDigest = hash('sha256', $masterBytes);
$currentHeaderDigest = $headerDigest($recovered[1]);
$oldHeaderDigest = $headerDigest($before[1]);
$databaseBytes = implode('', $before);
$cacheEntry = static fn (string $label, string $image, string $digest) => [
    'label' => $label,
    'image' => $image,
    'reader_id' => $label . '-reader',
    'source_id' => $sourceId,
    'epoch' => 213,
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
    'database_header_digest' => $digest,
];
$readTicket = static fn (int $pageNumber, string $digest) => [
    'reader_id' => 'wp-read-' . $pageNumber,
    'page_number' => $pageNumber,
    'source_id' => $sourceId,
    'epoch' => 213,
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
    'database_header_digest' => $digest,
];

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::databaseHeaderDigestFence(
    $database,
    $master,
    $masterBytes,
    $databaseBytes,
    $pageSize,
    $recovered,
    [
        1 => $cacheEntry('schema', $recovered[1], $currentHeaderDigest),
        2 => $cacheEntry('alloptions', $before[2], $currentHeaderDigest),
        3 => $cacheEntry('active-plugins', $recovered[3], $oldHeaderDigest),
    ],
    [
        $readTicket(1, $currentHeaderDigest),
        $readTicket(2, $currentHeaderDigest),
        $readTicket(3, $oldHeaderDigest),
    ],
    $sourceId,
    213,
    $publication,
    $masterDigest,
    $recoverySequence,
    $memberTokens,
    $memberHeaders,
    $masterToken,
    $databaseToken,
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'pager-master-journal-reader-cache-current-source-next213');
    assert($plan['retained_cache_page_numbers'] === [1]);
    assert($plan['refreshed_cache_page_numbers'] === [2]);
    assert($plan['database_header_digest_invalidated_cache_page_numbers'] === [3]);
    assert($plan['read_cache_hits']['wp-read-3'] === false);
    echo "wordpress-pager-master-journal-reader-cache-current-source-next213 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'databaseHeaderDigest' => $plan['current_database_header_digest'],
    'retained' => $plan['retained_cache_page_numbers'],
    'refreshed' => $plan['refreshed_cache_page_numbers'],
    'headerInvalidated' => $plan['database_header_digest_invalidated_cache_page_numbers'],
    'reopenReaders' => $plan['reopen_reader_ids'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
