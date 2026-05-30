<?php

declare(strict_types=1);

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
    $page = substr_replace($page, pack('N', 206), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503236), 68, 4);

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
$recoveredDigest = static function (array $pages) use ($pageSize): string {
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
    1 => $formatPage('wp schema after master-journal file token recovery'),
    2 => $page('wp_options root after master-journal file token recovery'),
];
$memberTokens = [
    $mainJournal => 'dev=8:ino=9201:size=4096:mtime=20601:generation=main-current',
    $usersJournal => 'dev=8:ino=9202:size=1024:mtime=20602:generation=users-current',
];
$memberHeaders = [
    $mainJournal => hash('sha256', 'main rollback header after next206 recovery'),
    $usersJournal => hash('sha256', 'users rollback header after next206 recovery'),
];
$masterToken = 'dev=8:ino=9200:size=64:mtime=20600:generation=master-current';
$oldMasterToken = 'dev=8:ino=9200:size=64:mtime=20599:generation=master-prior';
$recoveredPageSetDigest = $recoveredDigest($recovered);
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 206, 0x57503236]));
$sourceId = 'wordpress-master-journal-file-token-source';
$masterDigest = hash('sha256', 'wordpress-next206-master-source');

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantSharedReaderPinFence(
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
            'epoch' => 206,
            'reader_id' => 'schema-reader',
            'format_signature' => $formatSignature,
            'publication_generation' => 206,
            'master_source_digest' => $masterDigest,
            'recovery_sequence' => 12,
            'recovered_page_set_digest' => $recoveredPageSetDigest,
            'member_journal_tokens' => $memberTokens,
            'member_journal_header_digests' => $memberHeaders,
            'master_member_order_digest' => $memberOrderDigest,
            'master_journal_file_token' => $masterToken,
        ],
        2 => [
            'label' => 'options-reader-from-old-master-token',
            'image' => $recovered[2],
            'source_id' => $sourceId,
            'epoch' => 206,
            'reader_id' => 'options-reader',
            'format_signature' => $formatSignature,
            'publication_generation' => 206,
            'master_source_digest' => $masterDigest,
            'recovery_sequence' => 12,
            'recovered_page_set_digest' => $recoveredPageSetDigest,
            'member_journal_tokens' => $memberTokens,
            'member_journal_header_digests' => $memberHeaders,
            'master_member_order_digest' => $memberOrderDigest,
            'master_journal_file_token' => $oldMasterToken,
        ],
    ],
    [
        [
            'reader_id' => 'schema-read',
            'page_number' => 1,
            'source_id' => $sourceId,
            'epoch' => 206,
            'format_signature' => $formatSignature,
            'publication_generation' => 206,
            'master_source_digest' => $masterDigest,
            'recovery_sequence' => 12,
            'recovered_page_set_digest' => $recoveredPageSetDigest,
            'member_journal_token_digest' => $mapDigest($memberTokens),
            'member_journal_header_digest' => $mapDigest($memberHeaders),
            'master_member_order_digest' => $memberOrderDigest,
            'master_journal_file_token' => $masterToken,
        ],
        [
            'reader_id' => 'options-read',
            'page_number' => 2,
            'source_id' => $sourceId,
            'epoch' => 206,
            'format_signature' => $formatSignature,
            'publication_generation' => 206,
            'master_source_digest' => $masterDigest,
            'recovery_sequence' => 12,
            'recovered_page_set_digest' => $recoveredPageSetDigest,
            'member_journal_token_digest' => $mapDigest($memberTokens),
            'member_journal_header_digest' => $mapDigest($memberHeaders),
            'master_member_order_digest' => $memberOrderDigest,
            'master_journal_file_token' => $masterToken,
        ],
    ],
    $sourceId,
    206,
    206,
    $masterDigest,
    12,
    $memberTokens,
    $memberHeaders,
    $masterToken,
);

if (($argv[1] ?? '') === '--self-test') {
    if ($plan['status'] !== 'pager-master-journal-reader-cache-current-source-next206') {
        throw new RuntimeException('unexpected status');
    }
    if ($plan['master_journal_file_token_invalidated_cache_page_numbers'] !== [2]) {
        throw new RuntimeException('expected wp_options page to reopen after master-journal file-token drift');
    }
    if ($plan['read_cache_hits']['schema-read'] !== true || $plan['read_cache_hits']['options-read'] !== false) {
        throw new RuntimeException('unexpected reader-cache hit map');
    }
    echo "wordpress-pager-master-journal-reader-cache-current-source-next206 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'invalidated' => $plan['master_journal_file_token_invalidated_cache_page_numbers'],
    'schemaCacheHit' => $plan['read_cache_hits']['schema-read'],
    'optionsCacheHit' => $plan['read_cache_hits']['options-read'],
    'reopen' => $plan['reopen_reader_ids'],
], JSON_PRETTY_PRINT) . PHP_EOL;
