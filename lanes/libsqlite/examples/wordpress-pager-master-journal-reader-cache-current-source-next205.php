<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next205.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next205-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$oldMaster = $database . '-old-mj';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$before = [
    1 => $page('next205 stale schema before member master-name recovery'),
    2 => $page('next205 stale wp_options root before member master-name recovery'),
    3 => $page('next205 stale active_plugins before member master-name recovery'),
    4 => $page('next205 stale usermeta before member master-name recovery'),
];
$current = [
    1 => $page('next205 current schema after member master-name recovery'),
    2 => $page('next205 current wp_options root after member master-name recovery'),
    3 => $page('next205 current active_plugins after member master-name recovery'),
    4 => $page('next205 current usermeta after member master-name recovery'),
];
$pageMembers = [
    1 => $mainJournal,
    2 => $mainJournal,
    3 => $mainJournal,
    4 => $usersJournal,
];
$memberDigest = static fn (string $member, string $name): string => hash('sha256', $member . '|' . $name);
$currentDigests = [
    $mainJournal => $memberDigest($mainJournal, $master),
    $usersJournal => $memberDigest($usersJournal, $master),
];
$pageSourceDigest = static fn (int $pageNumber, string $member, string $digest, string $image): string => hash('sha256', $pageNumber . '|' . $member . '|' . $digest . '|' . hash('sha256', $image));
$txDigest = static function (array $pages) use ($pageMembers, $currentDigests): string {
    $parts = [];
    foreach ($pages as $pageNumber) {
        $parts[] = $pageNumber . ':' . $currentDigests[$pageMembers[$pageNumber]];
    }

    return hash('sha256', implode('|', $parts));
};
$cacheEntry = static function (int $pageNumber, string $label, string $image, array $txPages, array $extra = []) use ($pageMembers, $currentDigests, $pageSourceDigest, $txDigest): array {
    $member = $pageMembers[$pageNumber];
    $digest = $currentDigests[$member];

    return array_merge([
        'label' => $label,
        'image' => $image,
        'reader_id' => $label . '-reader',
        'reader_transaction_id' => 'wp-options-import',
        'member_journal_path' => $member,
        'member_master_name_digest' => $digest,
        'transaction_master_name_digest' => $txDigest($txPages),
        'page_source_digest' => $pageSourceDigest($pageNumber, $member, $digest, $image),
        'source_id' => 'pager-reader-cache-master-name-next205',
        'epoch' => 205,
    ], $extra);
};
$reads = array_map(
    static function (int $pageNumber) use ($pageMembers, $currentDigests, $pageSourceDigest, $txDigest, $current): array {
        $member = $pageMembers[$pageNumber];
        return [
            'reader_id' => 'read-' . $pageNumber,
            'reader_transaction_id' => 'wp-options-import',
            'page_number' => $pageNumber,
            'member_journal_path' => $member,
            'member_master_name_digest' => $currentDigests[$member],
            'transaction_master_name_digest' => $txDigest([1, 2, 3, 4]),
            'page_source_digest' => $pageSourceDigest($pageNumber, $member, $currentDigests[$member], $current[$pageNumber]),
        ];
    },
    [1, 2, 3, 4],
);

$plan = SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext205(
    $database,
    $master,
    $mainJournal . "\n" . $usersJournal . "\n",
    implode('', $before),
    $pageSize,
    $current,
    [
        1 => $cacheEntry(1, 'schema-retained-master-name', $current[1], [1, 2, 3, 4]),
        2 => $cacheEntry(2, 'root-refresh-master-name', $before[2], [1, 2, 3, 4], [
            'page_source_digest' => $pageSourceDigest(2, $mainJournal, $currentDigests[$mainJournal], $current[2]),
        ]),
        3 => $cacheEntry(3, 'active-plugins-old-master-name', $current[3], [1, 2, 3, 4], [
            'member_master_name_digest' => $memberDigest($mainJournal, $oldMaster),
            'page_source_digest' => $pageSourceDigest(3, $mainJournal, $memberDigest($mainJournal, $oldMaster), $current[3]),
        ]),
        4 => $cacheEntry(4, 'usermeta-current-master-name', $current[4], [1, 2, 3, 4]),
    ],
    $reads,
    $pageMembers,
    [
        $mainJournal => $master,
        $usersJournal => $master,
    ],
    'pager-reader-cache-master-name-next205',
    205,
);

if (($plan['status'] ?? '') !== 'pager-master-journal-reader-cache-current-source-next205') {
    throw new RuntimeException('unexpected next205 status');
}
if (($plan['read_cache_hits']['read-3'] ?? true) !== false) {
    throw new RuntimeException('active_plugins reader cache should reopen after old master-name ticket');
}
if (!in_array(3, $plan['invalidated_cache_page_numbers'], true)) {
    throw new RuntimeException('active_plugins cache page should be invalidated');
}

echo 'wordpress-pager-master-journal-reader-cache-current-source-next205 self-test passed' . PHP_EOL;
