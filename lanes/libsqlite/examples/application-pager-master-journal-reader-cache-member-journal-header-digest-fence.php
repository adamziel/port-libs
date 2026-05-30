<?php

declare(strict_types=1);

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
    $page = substr_replace($page, pack('N', 196), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503139), 68, 4);

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
$recovered = [
    1 => $formatPage('wp schema after master journal member-header recovery'),
    2 => $page('wp_options root after attached member-header recovery'),
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
    $mainJournal => 'dev=8:ino=910:size=4096:mtime=19600:generation=main-current',
    $usersJournal => 'dev=8:ino=911:size=1024:mtime=19601:generation=users-current',
];
$currentHeaders = [
    $mainJournal => hash('sha256', 'application-main-current-rollback-header'),
    $usersJournal => hash('sha256', 'application-users-current-rollback-header'),
];
$oldHeaders = [
    $mainJournal => $currentHeaders[$mainJournal],
    $usersJournal => hash('sha256', 'application-users-reused-file-old-header'),
];

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::memberJournalHeaderDigestFence(
    $database,
    $masterJournal,
    $mainJournal . "\n" . $usersJournal . "\n",
    $page('old schema') . $page('old wp_options root'),
    $pageSize,
    $recovered,
    [
        1 => [
            'label' => 'schema-retained',
            'image' => $recovered[1],
            'source_id' => 'application-member-header-source',
            'epoch' => 196,
            'reader_id' => 'schema-reader',
            'format_signature' => hash('sha256', implode('|', [512, 4, 2, 196, 0x57503139])),
            'publication_generation' => 196,
            'master_source_digest' => hash('sha256', 'application-current-master-source'),
            'recovery_sequence' => 11,
            'recovered_page_set_digest' => $recoveredDigest($recovered),
            'member_journal_tokens' => $currentTokens,
            'member_journal_header_digests' => $currentHeaders,
        ],
        2 => [
            'label' => 'wp-options-reader-from-old-attached-header',
            'image' => $recovered[2],
            'source_id' => 'application-member-header-source',
            'epoch' => 196,
            'reader_id' => 'options-reader',
            'format_signature' => hash('sha256', implode('|', [512, 4, 2, 196, 0x57503139])),
            'publication_generation' => 196,
            'master_source_digest' => hash('sha256', 'application-current-master-source'),
            'recovery_sequence' => 11,
            'recovered_page_set_digest' => $recoveredDigest($recovered),
            'member_journal_tokens' => $currentTokens,
            'member_journal_header_digests' => $oldHeaders,
        ],
    ],
    [
        [
            'reader_id' => 'schema-read',
            'page_number' => 1,
            'source_id' => 'application-member-header-source',
            'epoch' => 196,
            'format_signature' => hash('sha256', implode('|', [512, 4, 2, 196, 0x57503139])),
            'publication_generation' => 196,
            'master_source_digest' => hash('sha256', 'application-current-master-source'),
            'recovery_sequence' => 11,
            'recovered_page_set_digest' => $recoveredDigest($recovered),
            'member_journal_token_digest' => $mapDigest($currentTokens),
            'member_journal_header_digest' => $mapDigest($currentHeaders),
        ],
        [
            'reader_id' => 'options-read',
            'page_number' => 2,
            'source_id' => 'application-member-header-source',
            'epoch' => 196,
            'format_signature' => hash('sha256', implode('|', [512, 4, 2, 196, 0x57503139])),
            'publication_generation' => 196,
            'master_source_digest' => hash('sha256', 'application-current-master-source'),
            'recovery_sequence' => 11,
            'recovered_page_set_digest' => $recoveredDigest($recovered),
            'member_journal_token_digest' => $mapDigest($currentTokens),
            'member_journal_header_digest' => $mapDigest($currentHeaders),
        ],
    ],
    'application-member-header-source',
    196,
    196,
    hash('sha256', 'application-current-master-source'),
    11,
    $currentTokens,
    $currentHeaders,
);

$summary = [
    'status' => $plan['status'],
    'invalidated' => $plan['member_header_invalidated_cache_page_numbers'],
    'schemaCacheHit' => $plan['read_cache_hits']['schema-read'],
    'optionsCacheHit' => $plan['read_cache_hits']['options-read'],
    'reopen' => $plan['reopen_reader_ids'],
];

if (($argv[1] ?? '') === '--self-test') {
    if (
        $summary['status'] !== 'pager-master-journal-reader-cache-current-source-next196'
        || $summary['invalidated'] !== [2]
        || $summary['schemaCacheHit'] !== true
        || $summary['optionsCacheHit'] !== false
        || $summary['reopen'] !== ['options-read']
    ) {
        fwrite(STDERR, "application-pager-master-journal-reader-cache-member-journal-header-digest-fence self-test failed\n");
        exit(1);
    }

    echo "application-pager-master-journal-reader-cache-member-journal-header-digest-fence self-test passed\n";
    exit(0);
}

echo json_encode($summary, JSON_PRETTY_PRINT) . PHP_EOL;
