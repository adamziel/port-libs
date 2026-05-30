<?php

declare(strict_types=1);

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
    $page = substr_replace($page, pack('N', 192), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503139), 68, 4);

    return substr_replace($page, $label, 100, strlen($label));
};

$recovered = [
    1 => $formatPage('wp schema after master journal member-token recovery'),
    2 => $page('wp_options root after attached member-token recovery'),
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
    $mainJournal => 'dev=8:ino=810:size=4096:mtime=19200:generation=main-current',
    $usersJournal => 'dev=8:ino=811:size=1024:mtime=19201:generation=users-current',
];
$oldTokens = [
    $mainJournal => $currentTokens[$mainJournal],
    $usersJournal => 'dev=8:ino=811:size=1024:mtime=19100:generation=users-prior',
];
$tokenDigest = static function (array $tokens): string {
    ksort($tokens, SORT_STRING);
    $parts = [];
    foreach ($tokens as $member => $token) {
        $parts[] = $member . '=' . $token;
    }

    return hash('sha256', implode('|', $parts));
};

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::attachedMemberJournalTokenFence(
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
            'source_id' => 'application-member-token-source',
            'epoch' => 192,
            'reader_id' => 'schema-reader',
            'format_signature' => hash('sha256', implode('|', [512, 4, 2, 192, 0x57503139])),
            'publication_generation' => 192,
            'master_source_digest' => hash('sha256', 'application-current-master-source'),
            'recovery_sequence' => 9,
            'recovered_page_set_digest' => $recoveredDigest($recovered),
            'member_journal_tokens' => $currentTokens,
        ],
        2 => [
            'label' => 'wp-options-reader-from-old-attached-token',
            'image' => $recovered[2],
            'source_id' => 'application-member-token-source',
            'epoch' => 192,
            'reader_id' => 'options-reader',
            'format_signature' => hash('sha256', implode('|', [512, 4, 2, 192, 0x57503139])),
            'publication_generation' => 192,
            'master_source_digest' => hash('sha256', 'application-current-master-source'),
            'recovery_sequence' => 9,
            'recovered_page_set_digest' => $recoveredDigest($recovered),
            'member_journal_tokens' => $oldTokens,
        ],
    ],
    [
        [
            'reader_id' => 'schema-read',
            'page_number' => 1,
            'source_id' => 'application-member-token-source',
            'epoch' => 192,
            'format_signature' => hash('sha256', implode('|', [512, 4, 2, 192, 0x57503139])),
            'publication_generation' => 192,
            'master_source_digest' => hash('sha256', 'application-current-master-source'),
            'recovery_sequence' => 9,
            'recovered_page_set_digest' => $recoveredDigest($recovered),
            'member_journal_token_digest' => $tokenDigest($currentTokens),
        ],
        [
            'reader_id' => 'options-read',
            'page_number' => 2,
            'source_id' => 'application-member-token-source',
            'epoch' => 192,
            'format_signature' => hash('sha256', implode('|', [512, 4, 2, 192, 0x57503139])),
            'publication_generation' => 192,
            'master_source_digest' => hash('sha256', 'application-current-master-source'),
            'recovery_sequence' => 9,
            'recovered_page_set_digest' => $recoveredDigest($recovered),
            'member_journal_token_digest' => $tokenDigest($currentTokens),
        ],
    ],
    'application-member-token-source',
    192,
    192,
    hash('sha256', 'application-current-master-source'),
    9,
    $currentTokens,
);

echo json_encode([
    'status' => $plan['status'],
    'invalidated' => $plan['member_token_invalidated_cache_page_numbers'],
    'schemaCacheHit' => $plan['read_cache_hits']['schema-read'],
    'optionsCacheHit' => $plan['read_cache_hits']['options-read'],
    'reopen' => $plan['reopen_reader_ids'],
], JSON_PRETTY_PRINT) . PHP_EOL;
