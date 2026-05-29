<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';

$pageSize = 512;
$database = '/srv/www/wp-content/database/wp-options-next195.sqlite';
$journal = $database . '-journal';
$master = $database . '-mj';
$usersJournal = '/srv/www/wp-content/database/wp-users-next195.sqlite-journal';
$sourceId = 'wp-options-reader-cache-member-ticket-next195';
$syncGeneration = 195;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$members = [$usersJournal, $journal];
$masterBytes = $usersJournal . "\0" . $journal . "\0";
$deleteToken = 'master-delete-synced:' . substr(hash('sha256', $master . '|' . $syncGeneration . '|' . implode("\n", $members)), 0, 40);
$journalDigests = [
    $usersJournal => hash('sha256', 'wp-users-current-journal-next195'),
    $journal => hash('sha256', 'wp-options-current-journal-next195'),
];
$tickets = [];
foreach ($members as $member) {
    $tickets[$member] = 'master-member-ticket:' . substr(hash('sha256', $member . '|' . $journalDigests[$member] . '|' . $deleteToken . '|' . $syncGeneration), 0, 40);
}
ksort($tickets, SORT_STRING);
$ticketValues = array_values($tickets);
$ticketFor = static fn (int $pageNumber): string => $ticketValues[($pageNumber - 1) % count($ticketValues)];
$oldTicket = 'master-member-ticket:' . str_repeat('f', 40);
$databaseBytes = implode('', [
    $page('next195 wp_options schema after master member fence'),
    $page('next195 alloptions bytes unchanged after attached journal rewrite'),
    $page('next195 active_plugins before member ticket refresh'),
]);
$cache = [
    1 => [
        'reader_id' => 'schema',
        'image' => $page('next195 wp_options schema after master member fence'),
        'source_id' => $sourceId,
        'epoch' => 195,
        'master_delete_token' => $deleteToken,
        'directory_sync_generation' => $syncGeneration,
        'master_member_ticket' => $ticketFor(1),
    ],
    2 => [
        'reader_id' => 'alloptions',
        'image' => $page('next195 alloptions bytes unchanged after attached journal rewrite'),
        'source_id' => $sourceId,
        'epoch' => 195,
        'master_delete_token' => $deleteToken,
        'directory_sync_generation' => $syncGeneration,
        'master_member_ticket' => $oldTicket,
    ],
    3 => [
        'reader_id' => 'active_plugins',
        'image' => $page('next195 active_plugins before member ticket refresh'),
        'source_id' => $sourceId,
        'epoch' => 195,
        'master_delete_token' => $deleteToken,
        'directory_sync_generation' => $syncGeneration,
        'master_member_ticket' => $ticketFor(3),
    ],
];

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext195(
    $database,
    $master,
    $masterBytes,
    $databaseBytes,
    $pageSize,
    $cache,
    [
        ['reader_id' => 'schema-read', 'page_number' => 1, 'master_member_ticket' => $ticketFor(1)],
        ['reader_id' => 'alloptions-read', 'page_number' => 2, 'master_member_ticket' => $oldTicket],
        ['reader_id' => 'active-plugins-read', 'page_number' => 3, 'master_member_ticket' => $ticketFor(3)],
    ],
    [
        3 => $page('next195 current active_plugins after member ticket refresh'),
    ],
    $sourceId,
    195,
    $syncGeneration,
    $journalDigests,
);

if (($argv[1] ?? '') === '--self-test') {
    assert($plan['status'] === 'pager-master-journal-reader-cache-current-source-next195');
    assert($plan['retained_page_numbers'] === [1]);
    assert($plan['refreshed_page_numbers'] === [3]);
    assert($plan['invalidated_page_numbers'] === [2]);
    assert($plan['next_reads'][1]['source_reason'] === 'reader_cache_reopened_after_master_member_ticket_change');
    echo "wordpress-pager-master-journal-reader-cache-current-source-next195 self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'retained' => $plan['retained_page_numbers'],
    'refreshed' => $plan['refreshed_page_numbers'],
    'invalidated' => $plan['member_ticket_invalidated_page_numbers'],
    'readCacheHits' => $plan['read_cache_hits'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
