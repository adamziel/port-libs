<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';

$pageSize = 512;
$database = '/srv/www/wp-content/database/wp-options-next191.sqlite';
$journal = $database . '-journal';
$master = $database . '-mj';
$usersJournal = '/srv/www/wp-content/database/wp-users-next191.sqlite-journal';
$sourceId = 'wp-options-reader-cache-delete-next191';
$syncGeneration = 19;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$members = [$usersJournal, $journal];
$masterBytes = $usersJournal . "\0" . $journal . "\0" . str_repeat("\0", 64);
$deleteToken = 'master-delete-synced:' . substr(hash('sha256', $master . '|' . $syncGeneration . '|' . implode("\n", $members)), 0, 40);
$databaseBytes = implode('', [
    $page('next191 wp_options schema before master delete sync'),
    $page('next191 stale alloptions before master delete sync'),
    $page('next191 active_plugins before master delete sync'),
    $page('next191 rewrite_rules before master delete sync'),
]);
$cache = [
    1 => [
        'reader_id' => 'schema',
        'image' => $page('next191 wp_options schema before master delete sync'),
        'source_id' => $sourceId,
        'epoch' => 191,
        'master_delete_token' => $deleteToken,
        'directory_sync_generation' => $syncGeneration,
    ],
    2 => [
        'reader_id' => 'alloptions',
        'image' => $page('next191 stale alloptions before master delete sync'),
        'source_id' => $sourceId,
        'epoch' => 191,
        'master_delete_token' => $deleteToken,
        'directory_sync_generation' => $syncGeneration,
    ],
    3 => [
        'reader_id' => 'active_plugins',
        'image' => $page('next191 active_plugins before master delete sync'),
        'source_id' => $sourceId,
        'epoch' => 191,
        'master_delete_token' => 'old-delete-proof',
        'directory_sync_generation' => $syncGeneration,
    ],
    4 => [
        'reader_id' => 'rewrite_rules',
        'image' => $page('next191 rewrite_rules before master delete sync'),
        'source_id' => $sourceId,
        'epoch' => 191,
        'master_delete_token' => $deleteToken,
        'directory_sync_generation' => $syncGeneration - 1,
    ],
];

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::planMasterJournalDeleteDirectorySyncFence(
    $database,
    $master,
    $masterBytes,
    $databaseBytes,
    $pageSize,
    $cache,
    [1, 2, 3, 4],
    [
        2 => $page('next191 current alloptions after master delete sync'),
    ],
    $sourceId,
    191,
    $syncGeneration,
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'pager-master-journal-reader-cache-current-source-next191');
    assert($plan['current_members'] === $members);
    assert($plan['retained_page_numbers'] === [1]);
    assert($plan['refreshed_page_numbers'] === [2]);
    assert($plan['invalidated_page_numbers'] === [3, 4]);
    assert($plan['next_reads'][1]['cache_hit'] === true);
    assert($plan['next_reads'][2]['reason'] === 'next_read_reopens_after_master_journal_delete');
    echo "wordpress-pager-master-journal-reader-cache-current-source-next191 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'deleteToken' => $plan['current_master_delete_token'],
    'retained' => $plan['retained_page_numbers'],
    'refreshed' => $plan['refreshed_page_numbers'],
    'invalidated' => $plan['invalidated_reasons'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
