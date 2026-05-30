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

$pageSize = 512;
$database = '/srv/wp-content/database/wp-options-next208.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-users-next208.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$masterJournal = $database . '-mj';
$masterBytes = $mainJournal . "\n" . $usersJournal . "\n";
$sourceId = 'wordpress-master-read-snapshot-source';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$formatPage = static function (string $label) use ($pageSize): string {
    $page = str_pad('SQLite format 3' . "\0", 100, "\0", STR_PAD_RIGHT) . str_repeat('.', $pageSize - 100);
    $page = substr_replace($page, pack('n', 512), 16, 2);
    $page = substr_replace($page, chr(4), 20, 1);
    $page = substr_replace($page, pack('N', 2), 56, 4);
    $page = substr_replace($page, pack('N', 208), 60, 4);
    $page = substr_replace($page, pack('N', 0x57503238), 68, 4);

    return substr_replace($page, $label, 100, strlen($label));
};
$recovered = [
    1 => $formatPage('wp schema after master read snapshot recovery'),
    2 => $page('wp_options alloptions page after master read snapshot recovery'),
];
$databaseBytes = $formatPage('old wp schema before master read snapshot') . $page('old wp_options alloptions before master read snapshot');
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
$currentTokens = [
    $mainJournal => 'dev=8:ino=2080:size=4096:mtime=20800:generation=main-current',
    $usersJournal => 'dev=8:ino=2081:size=1024:mtime=20801:generation=users-current',
];
$currentHeaders = [
    $mainJournal => hash('sha256', 'main-current-rollback-header-next208'),
    $usersJournal => hash('sha256', 'users-current-rollback-header-next208'),
];
$currentSnapshotDigest = hash('sha256', $masterJournal . '|offset=0|length=' . strlen($masterBytes) . '|bytes=' . $masterBytes . '|current');
$oldSnapshotDigest = hash('sha256', $masterJournal . '|offset=0|length=' . strlen($masterBytes) . '|bytes=' . $masterBytes . '|prior');
$formatSignature = hash('sha256', implode('|', [512, 4, 2, 208, 0x57503238]));
$recoveredPageSetDigest = $recoveredDigest($recovered);
$tokenDigest = $mapDigest($currentTokens);
$headerDigest = $mapDigest($currentHeaders);
$orderDigest = hash('sha256', implode("\n", [$mainJournal, $usersJournal]));

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantRecoveredPageSetFence(
    $database,
    $masterJournal,
    $masterBytes,
    $databaseBytes,
    $pageSize,
    $recovered,
    [
        1 => [
            'label' => 'schema-retained',
            'image' => $recovered[1],
            'source_id' => $sourceId,
            'epoch' => 208,
            'reader_id' => 'schema-reader',
            'format_signature' => $formatSignature,
            'publication_generation' => 208,
            'master_source_digest' => hash('sha256', 'wordpress-current-master-source-next208'),
            'recovery_sequence' => 208,
            'recovered_page_set_digest' => $recoveredPageSetDigest,
            'member_journal_tokens' => $currentTokens,
            'member_journal_header_digests' => $currentHeaders,
            'master_member_order_digest' => $orderDigest,
            'master_read_snapshot_digest' => $currentSnapshotDigest,
        ],
        2 => [
            'label' => 'alloptions-stale-master-read-snapshot',
            'image' => $recovered[2],
            'source_id' => $sourceId,
            'epoch' => 208,
            'reader_id' => 'alloptions-reader',
            'format_signature' => $formatSignature,
            'publication_generation' => 208,
            'master_source_digest' => hash('sha256', 'wordpress-current-master-source-next208'),
            'recovery_sequence' => 208,
            'recovered_page_set_digest' => $recoveredPageSetDigest,
            'member_journal_tokens' => $currentTokens,
            'member_journal_header_digests' => $currentHeaders,
            'master_member_order_digest' => $orderDigest,
            'master_read_snapshot_digest' => $oldSnapshotDigest,
        ],
    ],
    [
        [
            'reader_id' => 'schema-read',
            'page_number' => 1,
            'source_id' => $sourceId,
            'epoch' => 208,
            'format_signature' => $formatSignature,
            'publication_generation' => 208,
            'master_source_digest' => hash('sha256', 'wordpress-current-master-source-next208'),
            'recovery_sequence' => 208,
            'recovered_page_set_digest' => $recoveredPageSetDigest,
            'member_journal_token_digest' => $tokenDigest,
            'member_journal_header_digest' => $headerDigest,
            'master_member_order_digest' => $orderDigest,
            'master_read_snapshot_digest' => $currentSnapshotDigest,
        ],
        [
            'reader_id' => 'alloptions-read',
            'page_number' => 2,
            'source_id' => $sourceId,
            'epoch' => 208,
            'format_signature' => $formatSignature,
            'publication_generation' => 208,
            'master_source_digest' => hash('sha256', 'wordpress-current-master-source-next208'),
            'recovery_sequence' => 208,
            'recovered_page_set_digest' => $recoveredPageSetDigest,
            'member_journal_token_digest' => $tokenDigest,
            'member_journal_header_digest' => $headerDigest,
            'master_member_order_digest' => $orderDigest,
            'master_read_snapshot_digest' => $currentSnapshotDigest,
        ],
    ],
    $sourceId,
    208,
    208,
    hash('sha256', 'wordpress-current-master-source-next208'),
    208,
    $currentTokens,
    $currentHeaders,
    $currentSnapshotDigest,
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'pager-master-journal-reader-cache-current-source-next208');
    assert($plan['master_read_snapshot_invalidated_cache_page_numbers'] === [2]);
    assert($plan['read_cache_hits']['schema-read'] === true);
    assert($plan['read_cache_hits']['alloptions-read'] === false);
    assert($plan['reopen_reader_ids'] === ['alloptions-read']);
    echo "wordpress-pager-master-journal-reader-cache-current-source-next208 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'snapshotInvalidated' => $plan['master_read_snapshot_invalidated_cache_page_numbers'],
    'schemaCacheHit' => $plan['read_cache_hits']['schema-read'],
    'alloptionsCacheHit' => $plan['read_cache_hits']['alloptions-read'],
    'reopen' => $plan['reopen_reader_ids'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
