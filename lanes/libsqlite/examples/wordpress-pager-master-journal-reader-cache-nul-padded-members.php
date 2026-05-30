<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';

$pageSize = 512;
$database = '/srv/www/wp-content/database/wp-options-next188.sqlite';
$journal = $database . '-journal';
$master = $database . '-mj';
$usersJournal = '/srv/www/wp-content/database/wp-users-next188.sqlite-journal';
$sourceId = 'wp-options-copy-reader-cache-next188';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$masterBytes = $usersJournal . "\0" . $journal . "\0" . $journal . "\0" . str_repeat("\0", 96);
$members = [$usersJournal, $journal];
$memberToken = 'nul-sector-members:' . substr(hash('sha256', implode("\n", $members)), 0, 40);
$memberDigest = hash('sha256', implode("\n", $members));
$databaseBytes = implode('', [
    $page('next188 wp_options schema cache before master read'),
    $page('next188 stale alloptions root before master read'),
    $page('next188 plugin settings page before master read'),
    $page('next188 rewrite rules page before master read'),
]);
$cache = [
    1 => [
        'reader_id' => 'schema',
        'image' => $page('next188 wp_options schema cache before master read'),
        'source_id' => $sourceId,
        'epoch' => 188,
        'member_token' => $memberToken,
        'member_digest' => $memberDigest,
    ],
    2 => [
        'reader_id' => 'alloptions',
        'image' => $page('next188 stale alloptions root before master read'),
        'source_id' => $sourceId,
        'epoch' => 188,
        'member_token' => $memberToken,
        'member_digest' => $memberDigest,
    ],
    3 => [
        'reader_id' => 'plugins',
        'image' => $page('next188 plugin settings page before master read'),
        'source_id' => $sourceId,
        'epoch' => 188,
        'member_token' => 'stale-member-token',
        'member_digest' => $memberDigest,
    ],
    4 => [
        'reader_id' => 'rewrite-rules',
        'image' => $page('next188 rewrite rules page before master read'),
        'source_id' => $sourceId,
        'epoch' => 188,
        'member_token' => $memberToken,
        'member_digest' => $memberDigest,
        'dirty' => true,
    ],
];

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::planNulPaddedMemberBytesFence(
    $database,
    $master,
    $masterBytes,
    $databaseBytes,
    $pageSize,
    $cache,
    [1, 2, 3, 4],
    [
        2 => $page('next188 current alloptions root after master read'),
    ],
    $sourceId,
    188,
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'pager-master-journal-reader-cache-current-source-next188');
    assert($plan['current_members'] === $members);
    assert($plan['retained_page_numbers'] === [1]);
    assert($plan['refreshed_page_numbers'] === [2]);
    assert($plan['invalidated_page_numbers'] === [3, 4]);
    assert($plan['next_reads'][1]['cache_hit'] === true);
    assert($plan['next_reads'][2]['reason'] === 'next_read_reopens_after_nul_master_journal_parse');
    echo "wordpress-pager-master-journal-reader-cache-nul-padded-members self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'members' => $plan['current_members'],
    'retained' => $plan['retained_page_numbers'],
    'refreshed' => $plan['refreshed_page_numbers'],
    'invalidated' => $plan['invalidated_reasons'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
