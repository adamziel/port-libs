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
$database = '/srv/www/wp-content/database/wp-options-next230.sqlite';
$usersDatabase = '/srv/www/wp-content/database/wp-users-next230.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'wp-options-reader-cache-sqlite-version-next230';
$publication = 230;
$recoverySequence = 230;
$counter = 62;
$sqliteVersion = 3046000;
$oldSqliteVersion = 3045000;
$masterDigest = hash('sha256', 'wp-next230-master-source');
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$masterToken = 'dev=8:ino=2230:size=96:mtime=23000:generation=master-current';
$databaseToken = 'dev=8:ino=9230:size=1536:mtime=23099:generation=database-current';
$currentHeader = hash('sha256', 'wp-options:schema=230:change-counter=62:version-valid-for=62:sqlite-version=3046000:page-count=3');
$pageCount = 3;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$formatPage = static function (string $label, int $version) use ($pageSize): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr(4), 20, 1);
    $page = substr_replace($page, pack('N', 2), 56, 4);
    $page = substr_replace($page, pack('N', 230), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503230), 68, 4);
    $page = substr_replace($page, pack('N', 62), 24, 4);
    $page = substr_replace($page, pack('N', 62), 92, 4);
    $page = substr_replace($page, pack('N', $version), 96, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$before = [
    1 => $formatPage('next230 wp_options schema before version recovery', $oldSqliteVersion),
    2 => $page('next230 stale alloptions before version recovery'),
    3 => $page('next230 stale active_plugins before version recovery'),
];
$recovered = [
    1 => $formatPage('next230 current wp_options schema after version recovery', $sqliteVersion),
    2 => $page('next230 current alloptions after version recovery'),
    3 => $page('next230 current active_plugins after version recovery'),
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
    $mainJournal => 'dev=8:ino=3330:size=4096:mtime=23001:generation=options-current',
    $usersJournal => 'dev=8:ino=4430:size=1024:mtime=23002:generation=users-current',
];
$memberHeaders = [
    $mainJournal => hash('sha256', 'options-rollback-header-230'),
    $usersJournal => hash('sha256', 'users-rollback-header-230'),
];
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 230, 0x57503230]));
$recoveredPageSet = $recoveredDigest($recovered);
$memberTokenDigest = $mapDigest($memberTokens);
$memberHeaderDigest = $mapDigest($memberHeaders);
$memberOrderDigest = hash('sha256', implode("\n", [$mainJournal, $usersJournal]));
$masterBytesDigest = hash('sha256', $masterBytes);
$databaseBytes = implode('', $before);
$cacheEntry = static fn (string $label, string $image, int $version) => [
    'label' => $label,
    'image' => $image,
    'reader_id' => $label . '-reader',
    'source_id' => $sourceId,
    'epoch' => 230,
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
    'sqlite_version_number' => $version,
];
$readTicket = static fn (int $pageNumber, int $version) => [
    'reader_id' => 'wp-read-' . $pageNumber,
    'page_number' => $pageNumber,
    'source_id' => $sourceId,
    'epoch' => 230,
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
    'sqlite_version_number' => $version,
];

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext230(
    $database,
    $master,
    $masterBytes,
    $databaseBytes,
    $pageSize,
    $recovered,
    [
        1 => $cacheEntry('schema', $recovered[1], $sqliteVersion),
        2 => $cacheEntry('alloptions', $before[2], $sqliteVersion),
        3 => $cacheEntry('active-plugins', $recovered[3], $oldSqliteVersion),
    ],
    [
        $readTicket(1, $sqliteVersion),
        $readTicket(2, $sqliteVersion),
        $readTicket(3, $oldSqliteVersion),
    ],
    $sourceId,
    230,
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
    $sqliteVersion,
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'pager-master-journal-reader-cache-current-source-next230');
    assert($plan['retained_cache_page_numbers'] === [1]);
    assert($plan['refreshed_cache_page_numbers'] === [2]);
    assert($plan['sqlite_version_number_invalidated_cache_page_numbers'] === [3]);
    assert($plan['read_cache_hits']['wp-read-3'] === false);
    echo "wordpress-pager-master-journal-reader-cache-current-source-next230 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'sqliteVersionNumber' => $plan['current_sqlite_version_number'],
    'retained' => $plan['retained_cache_page_numbers'],
    'refreshed' => $plan['refreshed_cache_page_numbers'],
    'sqliteVersionInvalidated' => $plan['sqlite_version_number_invalidated_cache_page_numbers'],
    'reopenReaders' => $plan['reopen_reader_ids'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
