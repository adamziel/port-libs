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
    $page = substr_replace($page, pack('N', 209), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503239), 68, 4);

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
$masterBytes = "  {$mainJournal}\n{$usersJournal}\n";
$oldMasterBytes = "{$mainJournal}\n{$usersJournal}\n";
$memberOrderDigest = hash('sha256', implode("\n", $members));
$recovered = [
    1 => $formatPage('wp schema after raw master-journal bytes recovery'),
    2 => $page('wp_options root after raw master-journal bytes recovery'),
];
$memberTokens = [
    $mainJournal => 'dev=8:ino=9291:size=4096:mtime=20901:generation=main-current',
    $usersJournal => 'dev=8:ino=9292:size=1024:mtime=20902:generation=users-current',
];
$memberHeaders = [
    $mainJournal => hash('sha256', 'main rollback header after next209 recovery'),
    $usersJournal => hash('sha256', 'users rollback header after next209 recovery'),
];
$masterToken = 'dev=8:ino=9290:size=72:mtime=20900:generation=master-current';
$recoveredPageSetDigest = $recoveredDigest($recovered);
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 209, 0x57503239]));
$sourceId = 'application-master-journal-bytes-source';
$masterDigest = hash('sha256', 'application-next209-master-source');

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantReaderTransactionRefreshFence(
    $database,
    $masterJournal,
    $masterBytes,
    $page('old schema') . $page('old wp_options root'),
    $pageSize,
    $recovered,
    [
        1 => [
            'label' => 'schema-retained',
            'image' => $recovered[1],
            'source_id' => $sourceId,
            'epoch' => 209,
            'reader_id' => 'schema-reader',
            'format_signature' => $formatSignature,
            'publication_generation' => 209,
            'master_source_digest' => $masterDigest,
            'recovery_sequence' => 12,
            'recovered_page_set_digest' => $recoveredPageSetDigest,
            'member_journal_tokens' => $memberTokens,
            'member_journal_header_digests' => $memberHeaders,
            'master_member_order_digest' => $memberOrderDigest,
            'master_journal_file_token' => $masterToken,
            'master_journal_bytes_digest' => hash('sha256', $masterBytes),
        ],
        2 => [
            'label' => 'options-reader-from-old-master-bytes',
            'image' => $recovered[2],
            'source_id' => $sourceId,
            'epoch' => 209,
            'reader_id' => 'options-reader',
            'format_signature' => $formatSignature,
            'publication_generation' => 209,
            'master_source_digest' => $masterDigest,
            'recovery_sequence' => 12,
            'recovered_page_set_digest' => $recoveredPageSetDigest,
            'member_journal_tokens' => $memberTokens,
            'member_journal_header_digests' => $memberHeaders,
            'master_member_order_digest' => $memberOrderDigest,
            'master_journal_file_token' => $masterToken,
            'master_journal_bytes_digest' => hash('sha256', $oldMasterBytes),
        ],
    ],
    [
        [
            'reader_id' => 'schema-read',
            'page_number' => 1,
            'source_id' => $sourceId,
            'epoch' => 209,
            'format_signature' => $formatSignature,
            'publication_generation' => 209,
            'master_source_digest' => $masterDigest,
            'recovery_sequence' => 12,
            'recovered_page_set_digest' => $recoveredPageSetDigest,
            'member_journal_token_digest' => $mapDigest($memberTokens),
            'member_journal_header_digest' => $mapDigest($memberHeaders),
            'master_member_order_digest' => $memberOrderDigest,
            'master_journal_file_token' => $masterToken,
            'master_journal_bytes_digest' => hash('sha256', $masterBytes),
        ],
        [
            'reader_id' => 'options-read',
            'page_number' => 2,
            'source_id' => $sourceId,
            'epoch' => 209,
            'format_signature' => $formatSignature,
            'publication_generation' => 209,
            'master_source_digest' => $masterDigest,
            'recovery_sequence' => 12,
            'recovered_page_set_digest' => $recoveredPageSetDigest,
            'member_journal_token_digest' => $mapDigest($memberTokens),
            'member_journal_header_digest' => $mapDigest($memberHeaders),
            'master_member_order_digest' => $memberOrderDigest,
            'master_journal_file_token' => $masterToken,
            'master_journal_bytes_digest' => hash('sha256', $masterBytes),
        ],
    ],
    $sourceId,
    209,
    209,
    $masterDigest,
    12,
    $memberTokens,
    $memberHeaders,
    $masterToken,
);

if (($argv[1] ?? '') === '--self-test') {
    if ($plan['status'] !== 'pager-master-journal-reader-cache-current-source-next209') {
        throw new RuntimeException('unexpected status');
    }
    if ($plan['master_journal_bytes_digest_invalidated_cache_page_numbers'] !== [2]) {
        throw new RuntimeException('expected wp_options page to reopen after raw master-journal byte digest drift');
    }
    if ($plan['read_cache_hits']['schema-read'] !== true || $plan['read_cache_hits']['options-read'] !== false) {
        throw new RuntimeException('unexpected reader-cache hit map');
    }
    echo "application-pager-master-journal-reader-cache-current-source-next209 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'invalidated' => $plan['master_journal_bytes_digest_invalidated_cache_page_numbers'],
    'schemaCacheHit' => $plan['read_cache_hits']['schema-read'],
    'optionsCacheHit' => $plan['read_cache_hits']['options-read'],
    'reopen' => $plan['reopen_reader_ids'],
], JSON_PRETTY_PRINT) . PHP_EOL;
