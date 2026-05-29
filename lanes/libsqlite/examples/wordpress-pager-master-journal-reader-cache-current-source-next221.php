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
$database = '/srv/www/wp-content/database/wp-options-next221.sqlite';
$usersDatabase = '/srv/www/wp-content/database/wp-users-next221.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$sourceId = 'wp-options-reader-cache-ticket-next221';
$publication = 221;
$recoverySequence = 221;
$masterDigest = hash('sha256', 'wp-next221-master-source');
$masterBytes = "{$mainJournal}\n{$usersJournal}\n";
$masterToken = 'dev=8:ino=2221:size=96:mtime=22100:generation=master-current';
$databaseToken = 'dev=8:ino=9221:size=2048:mtime=22199:generation=database-current';
$currentHeader = hash('sha256', 'wp-options:schema=221:change-counter=91:page-count=3');
$currentTicket = ['change_counter' => 91, 'schema_cookie' => 221, 'version_valid_for' => 91, 'page_count' => 3];
$oldTicket = ['change_counter' => 90, 'schema_cookie' => 220, 'version_valid_for' => 90, 'page_count' => 4];
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$formatPage = static function (string $label) use ($pageSize): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr(4), 20, 1);
    $page = substr_replace($page, pack('N', 2), 56, 4);
    $page = substr_replace($page, pack('N', 221), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503231), 68, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$before = [
    1 => $formatPage('next221 wp_options schema before ticket recovery'),
    2 => $page('next221 stale alloptions before ticket recovery'),
    3 => $page('next221 stale active_plugins before ticket recovery'),
    4 => $page('next221 stale rewrite_rules before ticket recovery'),
];
$recovered = [
    1 => $formatPage('next221 current wp_options schema after ticket recovery'),
    2 => $page('next221 current alloptions after ticket recovery'),
    3 => $page('next221 current active_plugins after ticket recovery'),
];
$mapDigest = static function (array $map): string {
    ksort($map, SORT_STRING);
    $parts = [];
    foreach ($map as $member => $value) {
        $parts[] = $member . '=' . $value;
    }

    return hash('sha256', implode('|', $parts));
};
$recoveredDigest = static function (array $pages) use ($pageSize): string {
    ksort($pages, SORT_NUMERIC);
    $parts = [];
    foreach ($pages as $pageNumber => $image) {
        $parts[] = $pageNumber . ':' . hash('sha256', $image);
    }

    return hash('sha256', implode('|', $parts));
};
$memberTokens = [
    $mainJournal => 'dev=8:ino=3321:size=4096:mtime=22101:generation=options-current',
    $usersJournal => 'dev=8:ino=4421:size=1024:mtime=22102:generation=users-current',
];
$memberHeaders = [
    $mainJournal => hash('sha256', 'options-rollback-header-221'),
    $usersJournal => hash('sha256', 'users-rollback-header-221'),
];
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 221, 0x57503231]));
$recoveredPageSet = $recoveredDigest($recovered);
$memberTokenDigest = $mapDigest($memberTokens);
$memberHeaderDigest = $mapDigest($memberHeaders);
$memberOrderDigest = hash('sha256', implode("\n", [$mainJournal, $usersJournal]));
$masterBytesDigest = hash('sha256', $masterBytes);
$databaseBytes = implode('', $before);
$cacheEntry = static fn (string $label, string $image, array $ticket) => [
    'label' => $label,
    'image' => $image,
    'reader_id' => $label . '-reader',
    'source_id' => $sourceId,
    'epoch' => 221,
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
    'pager_header_ticket' => $ticket,
];
$readTicket = static fn (int $pageNumber, array $ticket) => [
    'reader_id' => 'wp-read-' . $pageNumber,
    'page_number' => $pageNumber,
    'source_id' => $sourceId,
    'epoch' => 221,
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
    'pager_header_ticket' => $ticket,
];

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext221(
    $database,
    $master,
    $masterBytes,
    $databaseBytes,
    $pageSize,
    $recovered,
    [
        1 => $cacheEntry('schema', $recovered[1], $currentTicket),
        2 => $cacheEntry('alloptions', $before[2], $currentTicket),
        3 => $cacheEntry('active-plugins', $recovered[3], $oldTicket),
        4 => $cacheEntry('rewrite-rules', $before[4], $oldTicket),
    ],
    [
        $readTicket(1, $currentTicket),
        $readTicket(2, $currentTicket),
        $readTicket(3, $oldTicket),
        $readTicket(4, $oldTicket),
    ],
    $sourceId,
    221,
    $publication,
    $masterDigest,
    $recoverySequence,
    $memberTokens,
    $memberHeaders,
    $masterToken,
    $databaseToken,
    $currentHeader,
    $currentTicket,
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'pager-master-journal-reader-cache-current-source-next221');
    assert($plan['retained_cache_page_numbers'] === [1]);
    assert($plan['refreshed_cache_page_numbers'] === [2]);
    assert($plan['pager_header_ticket_invalidated_cache_page_numbers'] === [3, 4]);
    assert($plan['read_cache_hits']['wp-read-3'] === false);
    echo "wordpress-pager-master-journal-reader-cache-current-source-next221 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'pagerHeaderTicket' => $plan['current_pager_header_ticket'],
    'retained' => $plan['retained_cache_page_numbers'],
    'refreshed' => $plan['refreshed_cache_page_numbers'],
    'ticketInvalidated' => $plan['pager_header_ticket_invalidated_cache_page_numbers'],
    'reopenReaders' => $plan['reopen_reader_ids'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
