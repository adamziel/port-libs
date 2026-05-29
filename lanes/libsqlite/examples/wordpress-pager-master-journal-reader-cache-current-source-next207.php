<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$pageSize = 512;
$database = '/srv/wp-content/database/wp-options.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$masterJournal = $database . '-mj';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$formatPage = static function (string $label) use ($pageSize): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr(4), 20, 1);
    $page = substr_replace($page, pack('N', 2), 56, 4);
    $page = substr_replace($page, pack('N', 207), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503237), 68, 4);

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

$members = [$mainJournal, $usersJournal];
$memberOrderDigest = hash('sha256', implode("\n", $members));
$recovered = [
    1 => $formatPage('wp schema after database file token recovery'),
    2 => $page('wp_options root after database file token recovery'),
];
$memberTokens = [
    $mainJournal => 'dev=8:ino=9301:size=4096:mtime=20701:generation=main-current',
    $usersJournal => 'dev=8:ino=9302:size=1024:mtime=20702:generation=users-current',
];
$memberHeaders = [
    $mainJournal => hash('sha256', 'main rollback header after next207 recovery'),
    $usersJournal => hash('sha256', 'users rollback header after next207 recovery'),
];
$masterToken = 'dev=8:ino=9300:size=64:mtime=20700:generation=master-current';
$databaseToken = 'dev=8:ino=9303:size=1024:mtime=20703:generation=database-current';
$oldDatabaseToken = 'dev=8:ino=9303:size=1024:mtime=20702:generation=database-prior';
$recoveredPageSetDigest = $recoveredDigest($recovered);
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 207, 0x57503237]));
$sourceId = 'wordpress-database-file-token-source';
$masterDigest = hash('sha256', 'wordpress-next207-master-source');

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext207(
    $database,
    $masterJournal,
    implode("\n", $members) . "\n",
    $page('old schema') . $page('old wp_options root'),
    $pageSize,
    $recovered,
    [
        1 => [
            'label' => 'schema-retained',
            'image' => $recovered[1],
            'source_id' => $sourceId,
            'epoch' => 207,
            'reader_id' => 'schema-reader',
            'format_signature' => $formatSignature,
            'publication_generation' => 207,
            'master_source_digest' => $masterDigest,
            'recovery_sequence' => 13,
            'recovered_page_set_digest' => $recoveredPageSetDigest,
            'member_journal_tokens' => $memberTokens,
            'member_journal_header_digests' => $memberHeaders,
            'master_member_order_digest' => $memberOrderDigest,
            'master_journal_file_token' => $masterToken,
            'database_file_token' => $databaseToken,
        ],
        2 => [
            'label' => 'options-reader-from-old-database-token',
            'image' => $recovered[2],
            'source_id' => $sourceId,
            'epoch' => 207,
            'reader_id' => 'options-reader',
            'format_signature' => $formatSignature,
            'publication_generation' => 207,
            'master_source_digest' => $masterDigest,
            'recovery_sequence' => 13,
            'recovered_page_set_digest' => $recoveredPageSetDigest,
            'member_journal_tokens' => $memberTokens,
            'member_journal_header_digests' => $memberHeaders,
            'master_member_order_digest' => $memberOrderDigest,
            'master_journal_file_token' => $masterToken,
            'database_file_token' => $oldDatabaseToken,
        ],
    ],
    [
        [
            'reader_id' => 'schema-read',
            'page_number' => 1,
            'source_id' => $sourceId,
            'epoch' => 207,
            'format_signature' => $formatSignature,
            'publication_generation' => 207,
            'master_source_digest' => $masterDigest,
            'recovery_sequence' => 13,
            'recovered_page_set_digest' => $recoveredPageSetDigest,
            'member_journal_token_digest' => $mapDigest($memberTokens),
            'member_journal_header_digest' => $mapDigest($memberHeaders),
            'master_member_order_digest' => $memberOrderDigest,
            'master_journal_file_token' => $masterToken,
            'database_file_token' => $databaseToken,
        ],
        [
            'reader_id' => 'options-read',
            'page_number' => 2,
            'source_id' => $sourceId,
            'epoch' => 207,
            'format_signature' => $formatSignature,
            'publication_generation' => 207,
            'master_source_digest' => $masterDigest,
            'recovery_sequence' => 13,
            'recovered_page_set_digest' => $recoveredPageSetDigest,
            'member_journal_token_digest' => $mapDigest($memberTokens),
            'member_journal_header_digest' => $mapDigest($memberHeaders),
            'master_member_order_digest' => $memberOrderDigest,
            'master_journal_file_token' => $masterToken,
            'database_file_token' => $databaseToken,
        ],
    ],
    $sourceId,
    207,
    207,
    $masterDigest,
    13,
    $memberTokens,
    $memberHeaders,
    $masterToken,
    $databaseToken,
);

if (($argv[1] ?? '') === '--self-test') {
    if ($plan['status'] !== 'pager-master-journal-reader-cache-current-source-next207') {
        throw new RuntimeException('unexpected status');
    }
    if ($plan['database_file_token_invalidated_cache_page_numbers'] !== [2]) {
        throw new RuntimeException('expected wp_options page to reopen after database file-token drift');
    }
    if ($plan['read_cache_hits']['schema-read'] !== true || $plan['read_cache_hits']['options-read'] !== false) {
        throw new RuntimeException('unexpected reader-cache hit map');
    }
    echo "wordpress-pager-master-journal-reader-cache-current-source-next207 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'invalidated' => $plan['database_file_token_invalidated_cache_page_numbers'],
    'schemaCacheHit' => $plan['read_cache_hits']['schema-read'],
    'optionsCacheHit' => $plan['read_cache_hits']['options-read'],
    'reopen' => $plan['reopen_reader_ids'],
], JSON_PRETTY_PRINT) . PHP_EOL;
