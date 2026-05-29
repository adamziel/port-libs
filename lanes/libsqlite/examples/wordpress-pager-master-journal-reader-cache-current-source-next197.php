<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$pageSize = 512;
$databasePath = '/srv/www/wp-content/database/wp-next197.sqlite';
$journalPath = $databasePath . '-journal';
$usersJournalPath = '/srv/www/wp-content/database/wp-next197-users.sqlite-journal';
$masterJournalPath = $databasePath . '-mj';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$masterJournalBytes = $usersJournalPath . "\0" . $journalPath . "\0" . $usersJournalPath . "\0";
$members = [$usersJournalPath, $journalPath];
$memberDigest = hash('sha256', implode("\n", $members));
$sourceId = 'wordpress-next197-master-member-current-source';
$nonce = 'wordpress-import-master-member-next197';

$before = [
    1 => $page('next197 WordPress schema page before master member source'),
    2 => $page('next197 stale wp_options root before master member source'),
    3 => $page('next197 stale active_plugins page before member source'),
];

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::masterJournalMemberSourceFence(
    $databasePath,
    $masterJournalPath,
    $masterJournalBytes,
    implode('', $before),
    $pageSize,
    [
        1 => [
            'reader_id' => 'schema-reader',
            'image' => $before[1],
            'source_id' => $sourceId,
            'epoch' => 197,
            'master_member_digest' => $memberDigest,
            'current_source_nonce' => $nonce,
        ],
        2 => [
            'reader_id' => 'wp-options-reader',
            'image' => $before[2],
            'source_id' => $sourceId,
            'epoch' => 197,
            'master_member_digest' => $memberDigest,
            'current_source_nonce' => $nonce,
        ],
        3 => [
            'reader_id' => 'active-plugins-reader',
            'image' => $before[3],
            'source_id' => $sourceId,
            'epoch' => 197,
            'master_member_digest' => hash('sha256', 'previous-attached-member-set'),
            'current_source_nonce' => $nonce,
        ],
    ],
    [
        ['reader_id' => 'schema-read', 'page_number' => 1],
        ['reader_id' => 'wp-options-read', 'page_number' => 2],
        ['reader_id' => 'active-plugins-read', 'page_number' => 3],
    ],
    [
        2 => $page('next197 current wp_options root after master member source'),
    ],
    $sourceId,
    197,
    $nonce,
);

echo 'status: ' . $plan['status'] . PHP_EOL;
echo 'members: ' . implode(',', $plan['current_members']) . PHP_EOL;
echo 'retained: ' . implode(',', $plan['retained_page_numbers']) . PHP_EOL;
echo 'refreshed: ' . implode(',', $plan['refreshed_page_numbers']) . PHP_EOL;
echo 'invalidated: ' . implode(',', $plan['invalidated_page_numbers']) . PHP_EOL;
