<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next200.sqlite';
$master = '/srv/wp-content/database/wp-next200.sqlite-mj';
$journal = $database . '-journal';
$usersJournal = '/srv/wp-content/database/wp-next200-users.sqlite-journal';
$masterBytes = $usersJournal . "\n" . $journal . "\n";
$members = [$usersJournal, $journal];
$sourceId = 'pager-member-generation-next200-current-source';
$checkpoint = 2007;
$epoch = 200;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$memberToken = static fn (string $member, int $generation): string => 'member-source-generation:' . substr(hash('sha256', $master . '|' . hash('sha256', $masterBytes) . '|' . $checkpoint . '|' . $member . '|' . $generation), 0, 40);
$pageDigest = static fn (int $pageNumber, string $image, string $member, string $token): string => hash('sha256', $pageNumber . '|' . $member . '|' . $token . '|' . hash('sha256', $image));
$readerToken = static function (string $group, array $parts): string {
    sort($parts, SORT_NATURAL);

    return 'reader-member-generation:' . substr(hash('sha256', $group . '|' . implode('|', $parts)), 0, 40);
};

$before = [
    1 => $page('next200 schema before member generation fence'),
    2 => $page('next200 active_plugins before member generation fence'),
    3 => $page('next200 users before member generation fence'),
    4 => $page('next200 usermeta before member generation fence'),
    5 => $page('next200 cron before member generation fence'),
    6 => $page('next200 transient before member generation fence'),
    7 => $page('next200 rewrite rules before member generation fence'),
];
$current = [
    2 => ['image' => $page('next200 active_plugins after member generation fence'), 'member_journal_path' => $journal],
    3 => ['image' => $before[3], 'member_journal_path' => $usersJournal],
    4 => ['image' => $page('next200 usermeta after member generation fence'), 'member_journal_path' => $usersJournal],
    5 => ['image' => $page('next200 cron after member generation fence'), 'member_journal_path' => $journal],
];
$generations = [$journal => 11, $usersJournal => 12];
$tokens = [$journal => $memberToken($journal, 11), $usersJournal => $memberToken($usersJournal, 12)];
$oldTokens = [$journal => $memberToken($journal, 10), $usersJournal => $memberToken($usersJournal, 11)];
$source = static function (int $pageNumber) use ($before, $current, $tokens, $pageDigest): array {
    if (isset($current[$pageNumber])) {
        $member = $current[$pageNumber]['member_journal_path'];
        $image = $current[$pageNumber]['image'];
        return [$member, $tokens[$member], $image, $pageDigest($pageNumber, $image, $member, $tokens[$member])];
    }
    $member = 'database-image-before-master-journal-recovery-next200';
    $token = 'database-before-master-member-generation-next200';
    return [$member, $token, $before[$pageNumber], $pageDigest($pageNumber, $before[$pageNumber], $member, $token)];
};
$oldSource = static function (int $pageNumber, string $member) use ($before, $oldTokens, $pageDigest): array {
    return [$member, $oldTokens[$member], $before[$pageNumber], $pageDigest($pageNumber, $before[$pageNumber], $member, $oldTokens[$member])];
};
$groupToken = static function (string $group, array $pages) use ($source, $readerToken): string {
    $parts = [];
    foreach ($pages as $pageNumber) {
        [, $token, , $digest] = $source($pageNumber);
        $parts[] = $pageNumber . ':' . $token . ':' . $digest;
    }

    return $readerToken($group, $parts);
};
$oldGroupToken = static function (string $group, array $pages, array $membersByPage) use ($oldSource, $readerToken): string {
    $parts = [];
    foreach ($pages as $pageNumber) {
        [, $token, , $digest] = $oldSource($pageNumber, $membersByPage[$pageNumber]);
        $parts[] = $pageNumber . ':' . $token . ':' . $digest;
    }

    return $readerToken($group, $parts);
};

$cacheEntry = static function (string $label, int $pageNumber, string $group, string $readerTokenValue, array $extra = []) use ($source, $sourceId, $epoch): array {
    [$member, $memberTokenValue, $image, $digest] = $source($pageNumber);
    return array_merge([
        'label' => $label,
        'image' => $image,
        'member_journal_path' => $member,
        'member_generation_token' => $memberTokenValue,
        'reader_id' => $label . '-reader',
        'reader_transaction_id' => $group,
        'reader_member_generation_token' => $readerTokenValue,
        'page_source_digest' => $digest,
        'source_id' => $sourceId,
        'epoch' => $epoch,
    ], $extra);
};
$read = static function (string $id, int $pageNumber, string $group, string $readerTokenValue, array $extra = []) use ($source): array {
    [$member, $memberTokenValue, , $digest] = $source($pageNumber);
    return array_merge([
        'reader_id' => $id,
        'reader_transaction_id' => $group,
        'page_number' => $pageNumber,
        'member_journal_path' => $member,
        'member_generation_token' => $memberTokenValue,
        'reader_member_generation_token' => $readerTokenValue,
        'page_source_digest' => $digest,
    ], $extra);
};

$optionsToken = $groupToken('tx-options', [1, 2]);
$usersToken = $groupToken('tx-users', [3, 4]);
$cronToken = $groupToken('tx-cron', [5]);
$dirtyToken = $groupToken('tx-dirty', [6]);
$rewriteToken = $groupToken('tx-rewrite', [7]);
$oldUsersToken = $oldGroupToken('tx-users', [3, 4], [3 => $usersJournal, 4 => $usersJournal]);
$cache = static fn (): array => [
    1 => $cacheEntry('schema-retained-generation', 1, 'tx-options', $optionsToken),
    2 => $cacheEntry('active-refreshed-generation', 2, 'tx-options', $optionsToken, ['image' => $before[2]]),
    3 => $cacheEntry('users-byte-identical-old-generation', 3, 'tx-users', $oldUsersToken, ['reader_member_generation_token' => $oldUsersToken, 'member_generation_token' => $oldTokens[$usersJournal], 'page_source_digest' => $oldSource(3, $usersJournal)[3]]),
    4 => $cacheEntry('usermeta-old-generation', 4, 'tx-users', $oldUsersToken, ['reader_member_generation_token' => $oldUsersToken, 'member_generation_token' => $oldTokens[$usersJournal], 'page_source_digest' => $oldSource(4, $usersJournal)[3], 'image' => $before[4]]),
    5 => $cacheEntry('cron-pinned-stale-generation', 5, 'tx-cron', $cronToken, ['pinned' => true, 'image' => $before[5]]),
    6 => $cacheEntry('transient-dirty-generation', 6, 'tx-dirty', $dirtyToken, ['dirty' => true]),
    7 => $cacheEntry('rewrite-source-id-old', 7, 'tx-rewrite', $rewriteToken, ['source_id' => 'old-source']),
];
$reads = static fn (?string $cronTicket = null): array => [
    $read('schema-reader', 1, 'tx-options', $optionsToken),
    $read('active-reader', 2, 'tx-options', $optionsToken),
    $read('users-reader', 3, 'tx-users', $usersToken),
    $read('usermeta-reader', 4, 'tx-users', $usersToken),
    $read('cron-reader', 5, 'tx-cron', $cronTicket ?? $cronToken),
    $read('transient-reader', 6, 'tx-dirty', $dirtyToken),
    $read('rewrite-reader', 7, 'tx-rewrite', $rewriteToken),
];
$plan = static fn (
    ?array $readerCache = null,
    ?array $readList = null,
    ?array $pages = null,
    ?array $memberGenerations = null,
    ?string $bytes = null,
    ?int $size = null,
    ?string $masterBytesOverride = null,
    ?string $path = null,
    ?string $masterPath = null,
    ?string $source = null,
    int $epochOverride = 200,
    int $checkpointOverride = 2007,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext200(
    $path ?? $database,
    $masterPath ?? $master,
    $masterBytesOverride ?? $masterBytes,
    $bytes ?? implode('', $before),
    $size ?? $pageSize,
    $readerCache ?? $cache(),
    $readList ?? $reads(),
    $pages ?? $current,
    $memberGenerations ?? $generations,
    $source ?? $sourceId,
    $epochOverride,
    $checkpointOverride,
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
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next200'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_member_generation_fences_reader_cache_current_source_reuse'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $database],
    'master path' => [static fn (): mixed => $plan()['master_journal_path'], $master],
    'page size' => [static fn (): mixed => $plan()['page_size'], 512],
    'members sorted' => [static fn (): mixed => $plan()['current_members'], $members],
    'main member token' => [static fn (): mixed => $plan()['member_generation_tokens'][$journal], $tokens[$journal]],
    'users member token' => [static fn (): mixed => $plan()['member_generation_tokens'][$usersJournal], $tokens[$usersJournal]],
    'options reader token' => [static fn (): mixed => $plan()['reader_member_generation_tokens']['tx-options'], $optionsToken],
    'users reader token' => [static fn (): mixed => $plan()['reader_member_generation_tokens']['tx-users'], $usersToken],
    'row count' => [static fn (): mixed => count($plan()['reader_rows']), 7],
    'retained pages' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1]],
    'refreshed pages' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], [2]],
    'invalidated pages' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [3, 4, 5, 6, 7]],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'users invalidated reason' => [static fn (): mixed => $plan()['invalidated_reasons'][3], 'reader_cache_member_generation_token_predates_current_source'],
    'usermeta grouped invalidated reason' => [static fn (): mixed => $plan()['invalidated_reasons'][4], 'reader_cache_member_generation_token_predates_current_source'],
    'cron pinned reason' => [static fn (): mixed => $plan()['invalidated_reasons'][5], 'pinned_reader_cache_image_predates_member_generation'],
    'dirty reason' => [static fn (): mixed => $plan()['invalidated_reasons'][6], 'dirty_reader_cache_cannot_cross_member_generation_fence'],
    'source reason' => [static fn (): mixed => $plan()['invalidated_reasons'][7], 'reader_cache_source_id_predates_current_source'],
    'schema admitted' => [static fn (): mixed => $row('schema-retained-generation')['admitted'], true],
    'schema reason' => [static fn (): mixed => $row('schema-retained-generation')['reason'], 'reader_cache_member_generation_matches_current_source'],
    'active refreshed reason' => [static fn (): mixed => $row('active-refreshed-generation')['reason'], 'reader_cache_refreshed_after_member_generation'],
    'active cache prefix' => [static fn (): mixed => $row('active-refreshed-generation')['cache_prefix'], 'next200 active_plugins before member generation fence'],
    'active current prefix' => [static fn (): mixed => $row('active-refreshed-generation')['current_prefix'], 'next200 active_plugins after member generation fence'],
    'users member mismatch' => [static fn (): mixed => $row('users-byte-identical-old-generation')['member_generation_token_matches'], false],
    'users page digest mismatch' => [static fn (): mixed => $row('users-byte-identical-old-generation')['page_source_digest_matches'], false],
    'users reader token mismatch' => [static fn (): mixed => $row('users-byte-identical-old-generation')['reader_member_generation_token_matches'], false],
    'cron pinned flag' => [static fn (): mixed => $row('cron-pinned-stale-generation')['pinned'], true],
    'transient dirty flag' => [static fn (): mixed => $row('transient-dirty-generation')['dirty'], true],
    'next read count' => [static fn (): mixed => count($plan()['next_reads']), 7],
    'schema cache hit' => [static fn (): mixed => $plan()['read_cache_hits']['schema-reader'], true],
    'active cache hit' => [static fn (): mixed => $plan()['read_cache_hits']['active-reader'], true],
    'users cache miss' => [static fn (): mixed => $plan()['read_cache_hits']['users-reader'], false],
    'usermeta grouped miss' => [static fn (): mixed => $plan()['read_cache_hits']['usermeta-reader'], false],
    'cron miss' => [static fn (): mixed => $plan()['read_cache_hits']['cron-reader'], false],
    'read source current' => [static fn (): mixed => $plan()['next_reads'][0]['source'], 'member-generation-reader-cache-current-source-next200'],
    'miss source reopen' => [static fn (): mixed => $plan()['next_reads'][2]['source'], 'member-generation-reader-reopen-current-source-next200'],
    'miss reason grouped' => [static fn (): mixed => $plan()['next_reads'][3]['reason'], 'next_read_reopens_after_member_generation_cache_invalidation'],
    'read member current true' => [static fn (): mixed => $plan()['next_reads'][1]['member_generation_current'], true],
    'read page source current true' => [static fn (): mixed => $plan()['next_reads'][1]['page_source_current'], true],
    'read token current true' => [static fn (): mixed => $plan()['next_reads'][1]['reader_member_generation_current'], true],
    'stale cron ticket misses' => [static fn (): mixed => $plan(null, $reads($oldGroupToken('tx-cron', [5], [5 => $journal])))['read_cache_hits']['cron-reader'], false],
    'stale cron ticket reason' => [static fn (): mixed => $plan(null, $reads($oldGroupToken('tx-cron', [5], [5 => $journal])))['next_reads'][4]['reason'], 'next_read_reopens_after_member_generation_cache_invalidation'],
    'reopen readers' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['cron-reader', 'rewrite-reader', 'transient-reader', 'usermeta-reader', 'users-reader']],
    'operation derive' => [static fn (): mixed => $plan()['operations'][0]['op'], 'derive_master_journal_member_generation_tokens_next200'],
    'operation invalidate present' => [static fn (): mixed => in_array('invalidate_reader_cache_member_generation_after_master_current_source_next200', array_column($plan()['operations'], 'op'), true), true],
    'operation refresh present' => [static fn (): mixed => in_array('refresh_reader_cache_member_generation_after_master_current_source_next200', array_column($plan()['operations'], 'op'), true), true],
    'operation retain present' => [static fn (): mixed => in_array('retain_reader_cache_member_generation_after_master_current_source_next200', array_column($plan()['operations'], 'op'), true), true],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next200', $plan()['dependencies'], true), true],
    'dependency member fence' => [static fn (): mixed => in_array('sqlite-pager-master-journal-member-generation-reader-cache-fence', $plan()['dependencies'], true), true],
    'non overlap next194' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'does not repeat next194'), true],
    'all current no reopen' => [static fn (): mixed => $plan([1 => $cacheEntry('schema-retained-generation', 1, 'tx-one', $groupToken('tx-one', [1]))], [$read('schema-reader', 1, 'tx-one', $groupToken('tx-one', [1]))], [])['requires_reader_reopen'], false],
    'all current retained' => [static fn (): mixed => $plan([1 => $cacheEntry('schema-retained-generation', 1, 'tx-one', $groupToken('tx-one', [1]))], [$read('schema-reader', 1, 'tx-one', $groupToken('tx-one', [1]))], [])['retained_cache_page_numbers'], [1]],
    'different generation changes token' => [static fn (): mixed => $plan(null, null, null, [$journal => 12, $usersJournal => 12])['member_generation_tokens'][$journal] !== $tokens[$journal], true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next200 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty database path rejected' => static fn () => $plan(null, null, null, null, null, null, null, ''),
    'empty master path rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, ''),
    'empty source rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, null, ''),
    'blank master rejected' => static fn () => $plan(null, null, null, null, null, null, str_repeat("\0", 16)),
    'missing main journal rejected' => static fn () => $plan(null, null, null, null, null, null, $usersJournal . "\n"),
    'bad page size rejected' => static fn () => $plan(null, null, null, null, null, 500),
    'empty database rejected' => static fn () => $plan(null, null, null, null, ''),
    'unaligned database rejected' => static fn () => $plan(null, null, null, null, implode('', $before) . 'x'),
    'empty cache rejected' => static fn () => $plan([]),
    'empty reads rejected' => static fn () => $plan(null, []),
    'bad epoch rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, null, null, 0),
    'bad checkpoint rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, null, null, 200, 0),
    'missing member generation rejected' => static fn () => $plan(null, null, null, [$journal => 11]),
    'bad member generation rejected' => static fn () => $plan(null, null, null, [$journal => 0, $usersJournal => 12]),
    'bad current page rejected' => static fn () => $plan(null, null, [0 => ['image' => $before[1], 'member_journal_path' => $journal]]),
    'short current page rejected' => static fn () => $plan(null, null, [1 => ['image' => 'short', 'member_journal_path' => $journal]]),
    'unknown current member rejected' => static fn () => $plan(null, null, [1 => ['image' => $before[1], 'member_journal_path' => '/tmp/unknown-journal']]),
    'bad cache page rejected' => static fn () => $plan([0 => $cacheEntry('bad', 1, 'tx-one', $groupToken('tx-one', [1]))]),
    'short cache image rejected' => static fn () => $plan([1 => $cacheEntry('bad', 1, 'tx-one', $groupToken('tx-one', [1]), ['image' => 'short'])]),
    'missing cache member token rejected' => static fn () => $plan([1 => $cacheEntry('bad', 1, 'tx-one', $groupToken('tx-one', [1]), ['member_generation_token' => ''])]),
    'bad cache epoch rejected' => static fn () => $plan([1 => $cacheEntry('bad', 1, 'tx-one', $groupToken('tx-one', [1]), ['epoch' => 0])]),
    'bad read page rejected' => static fn () => $plan(null, [['reader_id' => 'bad', 'reader_transaction_id' => 'tx', 'page_number' => 0, 'member_journal_path' => $journal, 'member_generation_token' => $tokens[$journal], 'reader_member_generation_token' => $optionsToken, 'page_source_digest' => $source(1)[3]]]),
    'read outside rejected' => static fn () => $plan(null, [['reader_id' => 'bad', 'reader_transaction_id' => 'tx', 'page_number' => 9, 'member_journal_path' => $journal, 'member_generation_token' => $tokens[$journal], 'reader_member_generation_token' => $optionsToken, 'page_source_digest' => $source(1)[3]]]),
    'empty read token rejected' => static fn () => $plan(null, [$read('bad', 1, 'tx-options', '')]),
    'cache transaction absent from reads rejected' => static fn () => $plan([1 => $cacheEntry('bad', 1, 'tx-missing', $groupToken('tx-one', [1]))], [$read('schema-reader', 1, 'tx-one', $groupToken('tx-one', [1]))]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next200 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
