<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next205.sqlite';
$usersDatabase = '/srv/wp-content/database/wp-next205-users.sqlite';
$mainJournal = $database . '-journal';
$usersJournal = $usersDatabase . '-journal';
$master = $database . '-mj';
$masterBytes = $mainJournal . "\n" . $usersJournal . "\n";
$oldMaster = $database . '-old-mj';
$sourceId = 'pager-reader-cache-master-name-next205';
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$before = [
    1 => $page('next205 stale schema before member master-name recovery'),
    2 => $page('next205 stale wp_options root before member master-name recovery'),
    3 => $page('next205 stale active_plugins before member master-name recovery'),
    4 => $page('next205 stale usermeta before member master-name recovery'),
    5 => $page('next205 stale rewrite_rules before member master-name recovery'),
    6 => $page('next205 stale cron before member master-name recovery'),
    7 => $page('next205 stale comments before member master-name recovery'),
];
$current = [
    1 => $page('next205 current schema after member master-name recovery'),
    2 => $page('next205 current wp_options root after member master-name recovery'),
    3 => $page('next205 current active_plugins after member master-name recovery'),
    4 => $page('next205 current usermeta after member master-name recovery'),
    6 => $page('next205 current cron after member master-name recovery'),
];
$databaseBytes = implode('', $before);
$pageMembers = [
    1 => $mainJournal,
    2 => $mainJournal,
    3 => $mainJournal,
    4 => $usersJournal,
    5 => $mainJournal,
    6 => $usersJournal,
    7 => $mainJournal,
];
$memberNames = [
    $mainJournal => $master,
    $usersJournal => $master,
];
$memberDigest = static fn (string $member, string $name): string => hash('sha256', $member . '|' . $name);
$currentMemberDigests = [
    $mainJournal => $memberDigest($mainJournal, $master),
    $usersJournal => $memberDigest($usersJournal, $master),
];
$oldMainDigest = $memberDigest($mainJournal, $oldMaster);
$oldUsersDigest = $memberDigest($usersJournal, $oldMaster);
$pageSourceDigest = static fn (int $pageNumber, string $member, string $digest, string $image): string => hash('sha256', $pageNumber . '|' . $member . '|' . $digest . '|' . hash('sha256', $image));
$txDigest = static function (array $pages) use ($pageMembers, $currentMemberDigests): string {
    $parts = [];
    foreach ($pages as $pageNumber) {
        $parts[] = $pageNumber . ':' . $currentMemberDigests[$pageMembers[$pageNumber]];
    }

    return hash('sha256', implode('|', $parts));
};
$oldTxDigest = static function (array $pages) use ($pageMembers, $oldMainDigest, $oldUsersDigest, $mainJournal): string {
    $parts = [];
    foreach ($pages as $pageNumber) {
        $parts[] = $pageNumber . ':' . ($pageMembers[$pageNumber] === $mainJournal ? $oldMainDigest : $oldUsersDigest);
    }

    return hash('sha256', implode('|', $parts));
};
$cacheEntry = static function (int $pageNumber, string $label, string $image, string $tx, array $txPages, array $extra = []) use ($pageMembers, $currentMemberDigests, $pageSourceDigest, $txDigest, $sourceId): array {
    $member = $pageMembers[$pageNumber];
    $digest = $currentMemberDigests[$member];

    return array_merge([
        'label' => $label,
        'image' => $image,
        'reader_id' => $label . '-reader',
        'reader_transaction_id' => $tx,
        'member_journal_path' => $member,
        'member_master_name_digest' => $digest,
        'transaction_master_name_digest' => $txDigest($txPages),
        'page_source_digest' => $pageSourceDigest($pageNumber, $member, $digest, $image),
        'source_id' => $sourceId,
        'epoch' => 205,
    ], $extra);
};
$reads = static function (array $overrides = []) use ($pageMembers, $currentMemberDigests, $pageSourceDigest, $txDigest, $current): array {
    $txPages = [
        'tx-options' => [1, 2, 3, 5, 7],
        'tx-users' => [4, 6],
    ];
    $rows = [];
    foreach ($txPages as $tx => $pages) {
        foreach ($pages as $pageNumber) {
            $member = $pageMembers[$pageNumber];
            $image = $current[$pageNumber] ?? null;
            if ($image === null) {
                $image = str_pad('next205 stale ' . $pageNumber, 512, '.', STR_PAD_RIGHT);
            }
            $rows[] = [
                'reader_id' => 'read-' . $pageNumber,
                'reader_transaction_id' => $tx,
                'page_number' => $pageNumber,
                'member_journal_path' => $member,
                'member_master_name_digest' => $currentMemberDigests[$member],
                'transaction_master_name_digest' => $txDigest($pages),
                'page_source_digest' => $pageSourceDigest($pageNumber, $member, $currentMemberDigests[$member], $image),
            ];
        }
    }
    foreach ($overrides as $readerId => $values) {
        foreach ($rows as &$row) {
            if ($row['reader_id'] === $readerId) {
                $row = array_merge($row, $values);
            }
        }
        unset($row);
    }

    return $rows;
};
$cache = static fn (): array => [
    1 => $cacheEntry(1, 'schema-retained-master-name', $current[1], 'tx-options', [1, 2, 3, 5, 7]),
    2 => $cacheEntry(2, 'root-refreshed-master-name', $before[2], 'tx-options', [1, 2, 3, 5, 7], [
        'page_source_digest' => $pageSourceDigest(2, $mainJournal, $currentMemberDigests[$mainJournal], $current[2]),
    ]),
    3 => $cacheEntry(3, 'active-stale-member-master-name', $current[3], 'tx-options', [1, 2, 3, 5, 7], [
        'member_master_name_digest' => $oldMainDigest,
        'page_source_digest' => $pageSourceDigest(3, $mainJournal, $oldMainDigest, $current[3]),
    ]),
    4 => $cacheEntry(4, 'usermeta-stale-transaction-master-name', $current[4], 'tx-users', [4, 6], [
        'transaction_master_name_digest' => $oldTxDigest([4, 6]),
    ]),
    5 => $cacheEntry(5, 'rewrite-stale-page-source', $before[5], 'tx-options', [1, 2, 3, 5, 7], [
        'page_source_digest' => hash('sha256', 'older-page-five-source'),
    ]),
    6 => $cacheEntry(6, 'cron-pinned-stale-master-name', $before[6], 'tx-users', [4, 6], [
        'pinned' => true,
        'page_source_digest' => $pageSourceDigest(6, $usersJournal, $currentMemberDigests[$usersJournal], $current[6]),
    ]),
    7 => $cacheEntry(7, 'comments-dirty-master-name', $before[7], 'tx-options', [1, 2, 3, 5, 7], ['dirty' => true]),
];
$plan = static fn (
    ?array $readerCache = null,
    ?array $readList = null,
    ?array $pages = null,
    ?array $membersForPages = null,
    ?array $names = null,
    ?string $masterBytesInput = null,
    ?string $databaseBytesInput = null,
    ?int $size = null,
    ?string $path = null,
    ?string $masterPath = null,
    ?string $source = null,
    int $epoch = 205,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantReaderFormatSignatureFence(
    $path ?? $database,
    $masterPath ?? $master,
    $masterBytesInput ?? $masterBytes,
    $databaseBytesInput ?? $databaseBytes,
    $size ?? $pageSize,
    $pages ?? $current,
    $readerCache ?? $cache(),
    $readList ?? $reads(),
    $membersForPages ?? $pageMembers,
    $names ?? $memberNames,
    $source ?? $sourceId,
    $epoch,
);
$row = static function (string $label) use ($plan): array {
    foreach ($plan()['reader_rows'] as $row) {
        if ($row['label'] === $label) {
            return $row;
        }
    }
    throw new RuntimeException('missing row ' . $label);
};

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next205'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'member_rollback_journal_master_name_fences_reader_cache_current_source_reuse'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $database],
    'master path' => [static fn (): mixed => $plan()['master_journal_path'], $master],
    'page size' => [static fn (): mixed => $plan()['page_size'], 512],
    'members' => [static fn (): mixed => $plan()['current_members'], [$mainJournal, $usersJournal]],
    'main digest' => [static fn (): mixed => $plan()['member_master_name_digests'][$mainJournal], $currentMemberDigests[$mainJournal]],
    'users digest' => [static fn (): mixed => $plan()['member_master_name_digests'][$usersJournal], $currentMemberDigests[$usersJournal]],
    'options transaction digest' => [static fn (): mixed => $plan()['reader_transaction_master_name_digests']['tx-options'], $txDigest([1, 2, 3, 5, 7])],
    'users transaction digest' => [static fn (): mixed => $plan()['reader_transaction_master_name_digests']['tx-users'], $txDigest([4, 6])],
    'row count' => [static fn (): mixed => count($plan()['reader_rows']), 7],
    'retained pages' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], []],
    'refreshed pages' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], []],
    'invalidated pages' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [1, 2, 3, 4, 5, 6, 7]],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'schema admitted' => [static fn (): mixed => $row('schema-retained-master-name')['admitted'], true],
    'schema reason' => [static fn (): mixed => $row('schema-retained-master-name')['reason'], 'reader_cache_member_master_name_matches_current_source'],
    'root refreshed reason' => [static fn (): mixed => $row('root-refreshed-master-name')['reason'], 'reader_cache_refreshed_after_member_master_name'],
    'member name reason' => [static fn (): mixed => $row('active-stale-member-master-name')['reason'], 'reader_cache_member_master_name_predates_current_source'],
    'transaction reason' => [static fn (): mixed => $row('usermeta-stale-transaction-master-name')['reason'], 'reader_cache_transaction_master_name_predates_current_source'],
    'page source reason' => [static fn (): mixed => $row('rewrite-stale-page-source')['reason'], 'reader_cache_page_source_digest_predates_master_name'],
    'pinned reason' => [static fn (): mixed => $row('cron-pinned-stale-master-name')['reason'], 'pinned_reader_cache_image_predates_current_master_name'],
    'dirty reason' => [static fn (): mixed => $row('comments-dirty-master-name')['reason'], 'dirty_reader_cache_cannot_cross_member_master_name_fence'],
    'member digest mismatch flag' => [static fn (): mixed => $row('active-stale-member-master-name')['member_master_name_digest_matches'], false],
    'transaction digest mismatch flag' => [static fn (): mixed => $row('usermeta-stale-transaction-master-name')['transaction_master_name_digest_matches'], false],
    'page source mismatch flag' => [static fn (): mixed => $row('rewrite-stale-page-source')['page_source_digest_matches'], false],
    'schema prefix' => [static fn (): mixed => $row('schema-retained-master-name')['current_prefix'], 'next205 current schema after member master-name recovery'],
    'read count' => [static fn (): mixed => count($plan()['next_reads']), 7],
    'read retained misses after transaction invalidation' => [static fn (): mixed => $plan()['read_cache_hits']['read-1'], false],
    'read active miss' => [static fn (): mixed => $plan()['read_cache_hits']['read-3'], false],
    'read users miss' => [static fn (): mixed => $plan()['read_cache_hits']['read-4'], false],
    'read reason invalidated' => [static fn (): mixed => $plan()['next_reads'][0]['reason'], 'next_read_reopens_after_member_master_name_cache_invalidation'],
    'read source' => [static fn (): mixed => $plan()['next_reads'][2]['source'], 'member-master-name-reader-reopen-current-source-next205'],
    'read member current false for stale ticket' => [static fn (): mixed => $plan(null, $reads(['read-1' => ['member_master_name_digest' => $oldMainDigest]]))['next_reads'][0]['member_master_name_current'], false],
    'read stale ticket reason' => [static fn (): mixed => $plan([1 => $cacheEntry(1, 'schema-retained-master-name', $current[1], 'tx-options', [1])], [[
        'reader_id' => 'read-1',
        'reader_transaction_id' => 'tx-options',
        'page_number' => 1,
        'member_journal_path' => $mainJournal,
        'member_master_name_digest' => $oldMainDigest,
        'transaction_master_name_digest' => $txDigest([1]),
        'page_source_digest' => $pageSourceDigest(1, $mainJournal, $currentMemberDigests[$mainJournal], $current[1]),
    ]])['next_reads'][0]['reason'], 'reader_ticket_member_master_name_predates_current_source'],
    'single current read hits' => [static fn (): mixed => $plan([1 => $cacheEntry(1, 'schema-retained-master-name', $current[1], 'tx-options', [1])], [[
        'reader_id' => 'read-1',
        'reader_transaction_id' => 'tx-options',
        'page_number' => 1,
        'member_journal_path' => $mainJournal,
        'member_master_name_digest' => $currentMemberDigests[$mainJournal],
        'transaction_master_name_digest' => $txDigest([1]),
        'page_source_digest' => $pageSourceDigest(1, $mainJournal, $currentMemberDigests[$mainJournal], $current[1]),
    ]])['read_cache_hits']['read-1'], true],
    'single refresh hits' => [static fn (): mixed => $plan([2 => $cacheEntry(2, 'root-refreshed-master-name', $before[2], 'tx-options', [2], [
        'page_source_digest' => $pageSourceDigest(2, $mainJournal, $currentMemberDigests[$mainJournal], $current[2]),
    ])], [[
        'reader_id' => 'read-2',
        'reader_transaction_id' => 'tx-options',
        'page_number' => 2,
        'member_journal_path' => $mainJournal,
        'member_master_name_digest' => $currentMemberDigests[$mainJournal],
        'transaction_master_name_digest' => $txDigest([2]),
        'page_source_digest' => $pageSourceDigest(2, $mainJournal, $currentMemberDigests[$mainJournal], $current[2]),
    ]])['next_reads'][0]['prefix'], 'next205 current wp_options root after member master-name recovery'],
    'operation derive' => [static fn (): mixed => $plan()['operations'][0]['op'], 'derive_member_master_name_tokens_current_source_next205'],
    'operation retain' => [static fn (): mixed => $plan([1 => $cacheEntry(1, 'schema-retained-master-name', $current[1], 'tx-options', [1])], [[
        'reader_id' => 'read-1',
        'reader_transaction_id' => 'tx-options',
        'page_number' => 1,
        'member_journal_path' => $mainJournal,
        'member_master_name_digest' => $currentMemberDigests[$mainJournal],
        'transaction_master_name_digest' => $txDigest([1]),
        'page_source_digest' => $pageSourceDigest(1, $mainJournal, $currentMemberDigests[$mainJournal], $current[1]),
    ]])['operations'][1]['op'], 'retain_reader_cache_member_master_name_after_current_source_next205'],
    'operation invalidate' => [static fn (): mixed => in_array('invalidate_reader_cache_member_master_name_after_current_source_next205', array_column($plan()['operations'], 'op'), true), true],
    'reopen readers' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['read-1', 'read-2', 'read-3', 'read-4', 'read-5', 'read-6', 'read-7']],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next205', $plan()['dependencies'], true), true],
    'dependency master name marker' => [static fn (): mixed => in_array('sqlite-pager-reader-cache-member-rollback-master-name-fence', $plan()['dependencies'], true), true],
    'non overlap' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat next203'), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next205 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty database path rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, null, null, ''),
    'empty master path rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, null, null, null, ''),
    'empty source rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, null, null, null, null, ''),
    'empty master bytes rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, null, ''),
    'wrong master membership rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, null, '/tmp/other.sqlite-journal'),
    'bad page size rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, null, null, null, null, 500),
    'empty database rejected' => static fn () => $plan(null, null, null, null, null, null, ''),
    'unaligned database rejected' => static fn () => $plan(null, null, null, null, null, null, $databaseBytes . 'x'),
    'bad epoch rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, 0),
    'empty current pages rejected' => static fn () => $plan(null, null, []),
    'empty cache rejected' => static fn () => $plan([]),
    'empty reads rejected' => static fn () => $plan(null, []),
    'missing member name rejected' => static fn () => $plan(null, null, null, null, [$mainJournal => $master]),
    'wrong member master name rejected' => static fn () => $plan(null, null, null, null, [$mainJournal => $master, $usersJournal => $oldMaster]),
    'current page outside rejected' => static fn () => $plan(null, null, [9 => $page('outside')]),
    'current page short rejected' => static fn () => $plan(null, null, [1 => 'short']),
    'missing page member rejected' => static fn () => $plan(null, null, null, [1 => $mainJournal]),
    'page member outside master rejected' => static fn () => $plan(null, null, null, array_replace($pageMembers, [2 => '/tmp/other.sqlite-journal'])),
    'bad cache page rejected' => static fn () => $plan([0 => $cacheEntry(1, 'bad', $current[1], 'tx-options', [1])]),
    'short cache image rejected' => static fn () => $plan([1 => $cacheEntry(1, 'bad', 'short', 'tx-options', [1])]),
    'missing cache member digest rejected' => static fn () => $plan([1 => array_diff_key($cacheEntry(1, 'bad', $current[1], 'tx-options', [1]), ['member_master_name_digest' => true])]),
    'missing cache transaction digest rejected' => static fn () => $plan([1 => array_diff_key($cacheEntry(1, 'bad', $current[1], 'tx-options', [1]), ['transaction_master_name_digest' => true])]),
    'missing cache page source rejected' => static fn () => $plan([1 => array_diff_key($cacheEntry(1, 'bad', $current[1], 'tx-options', [1]), ['page_source_digest' => true])]),
    'bad cache epoch rejected' => static fn () => $plan([1 => $cacheEntry(1, 'bad', $current[1], 'tx-options', [1], ['epoch' => 0])]),
    'cache outside rejected' => static fn () => $plan([9 => $cacheEntry(1, 'bad', $page('outside'), 'tx-options', [1])]),
    'bad read page rejected' => static fn () => $plan(null, [['reader_id' => 'bad', 'reader_transaction_id' => 'tx', 'page_number' => 0, 'member_journal_path' => $mainJournal, 'member_master_name_digest' => $currentMemberDigests[$mainJournal], 'transaction_master_name_digest' => $txDigest([1]), 'page_source_digest' => $pageSourceDigest(1, $mainJournal, $currentMemberDigests[$mainJournal], $current[1])]]),
    'missing read transaction rejected' => static fn () => $plan(null, [['reader_id' => 'bad', 'page_number' => 1, 'member_journal_path' => $mainJournal, 'member_master_name_digest' => $currentMemberDigests[$mainJournal], 'transaction_master_name_digest' => $txDigest([1]), 'page_source_digest' => $pageSourceDigest(1, $mainJournal, $currentMemberDigests[$mainJournal], $current[1])]]),
    'read outside rejected' => static fn () => $plan(null, [['reader_id' => 'bad', 'reader_transaction_id' => 'tx', 'page_number' => 9, 'member_journal_path' => $mainJournal, 'member_master_name_digest' => $currentMemberDigests[$mainJournal], 'transaction_master_name_digest' => $txDigest([1]), 'page_source_digest' => $pageSourceDigest(1, $mainJournal, $currentMemberDigests[$mainJournal], $current[1])]]),
    'read wrong member rejected' => static fn () => $plan(null, [['reader_id' => 'bad', 'reader_transaction_id' => 'tx', 'page_number' => 1, 'member_journal_path' => $usersJournal, 'member_master_name_digest' => $currentMemberDigests[$usersJournal], 'transaction_master_name_digest' => $txDigest([1]), 'page_source_digest' => $pageSourceDigest(1, $mainJournal, $currentMemberDigests[$mainJournal], $current[1])]]),
    'cache tx missing from reads rejected' => static fn () => $plan([1 => $cacheEntry(1, 'schema-retained-master-name', $current[1], 'tx-missing', [1])], [[
        'reader_id' => 'read-1',
        'reader_transaction_id' => 'tx-options',
        'page_number' => 1,
        'member_journal_path' => $mainJournal,
        'member_master_name_digest' => $currentMemberDigests[$mainJournal],
        'transaction_master_name_digest' => $txDigest([1]),
        'page_source_digest' => $pageSourceDigest(1, $mainJournal, $currentMemberDigests[$mainJournal], $current[1]),
    ]]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next205 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
