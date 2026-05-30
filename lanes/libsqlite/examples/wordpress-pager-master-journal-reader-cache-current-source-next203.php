<?php

declare(strict_types=1);

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
$members = [$mainJournal, $usersJournal];
$oldMembers = [$usersJournal, $mainJournal];
$orderDigest = static fn (array $ordered): string => hash('sha256', implode("\n", $ordered));
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
    $page = substr_replace($page, pack('N', 203), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503230), 68, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$recovered = [
    1 => $formatPage('wp schema after ordered master member recovery'),
    2 => $page('wp_options root after ordered master member recovery'),
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
$currentTokens = [
    $mainJournal => 'dev=8:ino=1203:size=4096:mtime=20300:generation=main-current',
    $usersJournal => 'dev=8:ino=1204:size=1024:mtime=20301:generation=users-current',
];
$currentHeaders = [
    $mainJournal => hash('sha256', 'wordpress-main-current-rollback-header-203'),
    $usersJournal => hash('sha256', 'wordpress-users-current-rollback-header-203'),
];
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 203, 0x57503230]));
$sourceId = 'wordpress-member-order-source';
$masterDigest = hash('sha256', 'wordpress-current-master-source-next203');
$recoveredSetDigest = $recoveredDigest($recovered);
$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantMemberOrderDigestFence(
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
            'epoch' => 203,
            'reader_id' => 'schema-reader',
            'format_signature' => $formatSignature,
            'publication_generation' => 203,
            'master_source_digest' => $masterDigest,
            'recovery_sequence' => 12,
            'recovered_page_set_digest' => $recoveredSetDigest,
            'member_journal_tokens' => $currentTokens,
            'member_journal_header_digests' => $currentHeaders,
            'master_member_order_digest' => $orderDigest($members),
        ],
        2 => [
            'label' => 'options-reader-from-old-order',
            'image' => $recovered[2],
            'source_id' => $sourceId,
            'epoch' => 203,
            'reader_id' => 'options-reader',
            'format_signature' => $formatSignature,
            'publication_generation' => 203,
            'master_source_digest' => $masterDigest,
            'recovery_sequence' => 12,
            'recovered_page_set_digest' => $recoveredSetDigest,
            'member_journal_tokens' => $currentTokens,
            'member_journal_header_digests' => $currentHeaders,
            'master_member_order_digest' => $orderDigest($oldMembers),
        ],
    ],
    [
        [
            'reader_id' => 'schema-read',
            'page_number' => 1,
            'source_id' => $sourceId,
            'epoch' => 203,
            'format_signature' => $formatSignature,
            'publication_generation' => 203,
            'master_source_digest' => $masterDigest,
            'recovery_sequence' => 12,
            'recovered_page_set_digest' => $recoveredSetDigest,
            'member_journal_token_digest' => $mapDigest($currentTokens),
            'member_journal_header_digest' => $mapDigest($currentHeaders),
            'master_member_order_digest' => $orderDigest($members),
        ],
        [
            'reader_id' => 'options-read',
            'page_number' => 2,
            'source_id' => $sourceId,
            'epoch' => 203,
            'format_signature' => $formatSignature,
            'publication_generation' => 203,
            'master_source_digest' => $masterDigest,
            'recovery_sequence' => 12,
            'recovered_page_set_digest' => $recoveredSetDigest,
            'member_journal_token_digest' => $mapDigest($currentTokens),
            'member_journal_header_digest' => $mapDigest($currentHeaders),
            'master_member_order_digest' => $orderDigest($members),
        ],
    ],
    $sourceId,
    203,
    203,
    $masterDigest,
    12,
    $currentTokens,
    $currentHeaders,
);

$summary = [
    'status' => $plan['status'],
    'orderInvalidated' => $plan['master_member_order_invalidated_cache_page_numbers'],
    'schemaCacheHit' => $plan['read_cache_hits']['schema-read'],
    'optionsCacheHit' => $plan['read_cache_hits']['options-read'],
    'reopen' => $plan['reopen_reader_ids'],
];

if (($argv[1] ?? '') === '--self-test') {
    if (
        $summary['status'] !== 'pager-master-journal-reader-cache-current-source-next203'
        || $summary['orderInvalidated'] !== [2]
        || $summary['schemaCacheHit'] !== true
        || $summary['optionsCacheHit'] !== false
        || $summary['reopen'] !== ['options-read']
    ) {
        fwrite(STDERR, "wordpress-pager-master-journal-reader-cache-current-source-next203 self-test failed\n");
        exit(1);
    }

    echo "wordpress-pager-master-journal-reader-cache-current-source-next203 self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT) . PHP_EOL;
