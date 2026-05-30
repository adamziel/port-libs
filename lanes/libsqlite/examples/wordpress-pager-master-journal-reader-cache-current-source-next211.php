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
require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$pageSize = 512;
$database = '/srv/www/wp-content/database/wp-options-next211.sqlite';
$usersDatabase = '/srv/www/wp-content/database/wp-users-next211.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$masterJournal = $database . '-mj';
$sourceId = 'wordpress-next211-master-source';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$formatPage = static function (string $label) use ($pageSize): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr(4), 20, 1);
    $page = substr_replace($page, pack('N', 2), 56, 4);
    $page = substr_replace($page, pack('N', 211), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503231), 68, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$digestPages = static function (array $pages) use ($pageSize): string {
    ksort($pages, SORT_NUMERIC);
    $parts = [];
    foreach ($pages as $pageNumber => $image) {
        if (strlen($image) !== $pageSize) {
            throw new RuntimeException('bad page image');
        }
        $parts[] = $pageNumber . ':' . hash('sha256', $image);
    }

    return hash('sha256', implode('|', $parts));
};
$digestMap = static function (array $map): string {
    ksort($map, SORT_STRING);
    $parts = [];
    foreach ($map as $key => $value) {
        $parts[] = $key . '=' . $value;
    }

    return hash('sha256', implode('|', $parts));
};

$recovered = [
    1 => $formatPage('next211 schema after attached member page replay'),
    2 => $page('next211 wp_options root after attached member page replay'),
    3 => $page('next211 active_plugins after attached member page replay'),
];
$masterBytes = $mainJournal . "\n" . $usersJournal . "\n";
$tokens = [
    $mainJournal => 'dev=8:ino=4211:size=4096:mtime=21101:generation=main-current',
    $usersJournal => 'dev=8:ino=4212:size=2048:mtime=21102:generation=users-current',
];
$headers = [
    $mainJournal => hash('sha256', 'wp-main-header-next211'),
    $usersJournal => hash('sha256', 'wp-users-header-next211'),
];
$currentMemberPages = [
    $mainJournal => hash('sha256', 'main-pages:1,2,3'),
    $usersJournal => hash('sha256', 'users-pages:profile-meta-current'),
];
$oldMemberPages = [
    $mainJournal => $currentMemberPages[$mainJournal],
    $usersJournal => hash('sha256', 'users-pages:profile-meta-before-retry'),
];
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 211, 0x57503231]));
$recoveredDigest = $digestPages($recovered);
$tokenDigest = $digestMap($tokens);
$headerDigest = $digestMap($headers);
$memberPageDigest = $digestMap($currentMemberPages);
$masterToken = 'dev=8:ino=4210:size=70:mtime=21100:generation=master-current';
$masterBytesDigest = hash('sha256', $masterBytes);
$orderDigest = hash('sha256', implode("\n", [$mainJournal, $usersJournal]));

$ticket = static fn (string $readerId, int $pageNumber, string $memberDigest = null): array => [
    'reader_id' => $readerId,
    'page_number' => $pageNumber,
    'source_id' => $sourceId,
    'epoch' => 211,
    'format_signature' => $formatSignature,
    'publication_generation' => 211,
    'master_source_digest' => hash('sha256', 'wp-next211-master-current'),
    'recovery_sequence' => 211,
    'recovered_page_set_digest' => $recoveredDigest,
    'member_journal_token_digest' => $tokenDigest,
    'member_journal_header_digest' => $headerDigest,
    'member_journal_recovered_page_digest' => $memberDigest ?? $memberPageDigest,
    'master_member_order_digest' => $orderDigest,
    'master_journal_file_token' => $masterToken,
    'master_journal_bytes_digest' => $masterBytesDigest,
];
$cacheEntry = static fn (string $label, string $image, array $memberPages): array => [
    'label' => $label,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 211,
    'reader_id' => $label . '-reader',
    'format_signature' => $formatSignature,
    'publication_generation' => 211,
    'master_source_digest' => hash('sha256', 'wp-next211-master-current'),
    'recovery_sequence' => 211,
    'recovered_page_set_digest' => $recoveredDigest,
    'member_journal_tokens' => $tokens,
    'member_journal_header_digests' => $headers,
    'member_journal_recovered_page_digests' => $memberPages,
    'master_member_order_digest' => $orderDigest,
    'master_journal_file_token' => $masterToken,
    'master_journal_bytes_digest' => $masterBytesDigest,
];

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantReaderCacheSourceDigestFence(
    $database,
    $masterJournal,
    $masterBytes,
    $page('old schema') . $page('old root') . $page('old active plugins'),
    $pageSize,
    $recovered,
    [
        1 => $cacheEntry('schema-retained', $recovered[1], $currentMemberPages),
        2 => $cacheEntry('options-root-retained', $recovered[2], $currentMemberPages),
        3 => $cacheEntry('active-plugins-reopened', $recovered[3], $oldMemberPages),
    ],
    [
        $ticket('schema-read', 1),
        $ticket('root-read', 2),
        $ticket('active-plugins-read', 3),
    ],
    $sourceId,
    211,
    211,
    hash('sha256', 'wp-next211-master-current'),
    211,
    $tokens,
    $headers,
    $currentMemberPages,
    $masterToken,
);

$summary = [
    'status' => $plan['status'],
    'invalidated' => $plan['member_recovered_page_invalidated_cache_page_numbers'],
    'schemaCacheHit' => $plan['read_cache_hits']['schema-read'],
    'rootCacheHit' => $plan['read_cache_hits']['root-read'],
    'activePluginsCacheHit' => $plan['read_cache_hits']['active-plugins-read'],
    'reopen' => $plan['reopen_reader_ids'],
    'wordpressUse' => 'A copied WordPress import with attached usermeta journal replay keeps schema/root reader-cache pages but reopens active_plugins when the attached member journal recovered-page set changes under the same master-journal bytes.',
];

if (($argv[1] ?? '') === '--self-test') {
    if ($summary['status'] !== 'pager-master-journal-reader-cache-current-source-next211'
        || $summary['invalidated'] !== [3]
        || $summary['schemaCacheHit'] !== true
        || $summary['rootCacheHit'] !== true
        || $summary['activePluginsCacheHit'] !== false
        || $summary['reopen'] !== ['active-plugins-read']
    ) {
        fwrite(STDERR, "wordpress-pager-master-journal-reader-cache-current-source-next211 self-test failed\n");
        exit(1);
    }

    echo "wordpress-pager-master-journal-reader-cache-current-source-next211 self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT) . PHP_EOL;
