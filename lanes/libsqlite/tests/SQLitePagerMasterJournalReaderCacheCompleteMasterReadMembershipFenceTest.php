<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-next187.sqlite';
$masterPath = '/srv/wp-content/database/wp-next187.sqlite-mj';
$mainJournal = $databasePath . '-journal';
$metaJournal = '/srv/wp-content/database/wp-next187-meta.sqlite-journal';
$usersJournal = '/srv/wp-content/database/wp-next187-users.sqlite-journal';
$prefixMasterBytes = $mainJournal . "\n";
$masterBytes = $mainJournal . "\n" . $metaJournal . "\n" . $usersJournal . "\n";
$members = [$mainJournal, $metaJournal, $usersJournal];
$ordinals = array_combine($members, [1, 2, 3]);
$digest = hash('sha256', $masterPath . '|' . strlen($masterBytes) . '|' . implode("\n", $members) . '|' . hash('sha256', $masterBytes));
$prefixDigest = hash('sha256', $masterPath . '|' . strlen($prefixMasterBytes) . '|' . $mainJournal . '|' . hash('sha256', $prefixMasterBytes));
$sourceId = 'next187-current-complete-master-source';
$page = static fn (string $label, string $pad = '.'): string => str_pad($label, $pageSize, $pad, STR_PAD_RIGHT);
$before = [
    1 => $page('next187 schema before complete master membership read'),
    2 => $page('next187 wp_options root before complete master membership read'),
    3 => $page('next187 active_plugins before attached member recovery'),
    4 => $page('next187 plugin settings before attached member recovery'),
    5 => $page('next187 wp_usermeta before attached member recovery'),
    6 => $page('next187 transient cache before attached member recovery'),
    7 => $page('next187 cron option before attached member recovery'),
    8 => $page('next187 pinned comments before attached member recovery'),
];
$current = [
    3 => $page('next187 active_plugins after attached member recovery'),
    4 => $page('next187 plugin settings after attached member recovery'),
    5 => $page('next187 wp_usermeta after attached member recovery'),
];
$databaseBytes = implode('', $before);
$cacheEntry = static fn (string $label, string $image, array $extra = []): array => array_merge([
    'label' => $label,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 31,
    'master_member_ordinals' => $ordinals,
    'master_complete_read_digest' => $digest,
    'master_byte_length' => strlen($masterBytes),
], $extra);
$prefixOrdinals = [$mainJournal => 1];
$reorderedOrdinals = [$mainJournal => 2, $metaJournal => 1, $usersJournal => 3];
$readerCache = static fn (): array => [
    1 => $cacheEntry('schema-retained', $before[1]),
    2 => $cacheEntry('root-retained', $before[2]),
    3 => $cacheEntry('active-plugins-refresh', $before[3]),
    4 => $cacheEntry('settings-prefix-read', $current[4], ['master_member_ordinals' => $prefixOrdinals, 'master_complete_read_digest' => $prefixDigest, 'master_byte_length' => strlen($prefixMasterBytes)]),
    5 => $cacheEntry('usermeta-ordinal-changed', $current[5], ['master_member_ordinals' => $reorderedOrdinals]),
    6 => $cacheEntry('transient-byte-length-stale', $before[6], ['master_byte_length' => strlen($masterBytes) - 1]),
    7 => $cacheEntry('cron-dirty-prefix', $before[7], ['dirty' => true]),
    8 => $cacheEntry('comments-pinned-stale', $page('next187 pinned comments image before full master read'), ['pinned' => true]),
];

$plan = static fn (
    ?array $cache = null,
    ?array $reads = null,
    ?array $pages = null,
    ?string $master = null,
    ?string $bytes = null,
    ?int $size = null,
    ?string $path = null,
    ?string $masterJournalPath = null,
    ?string $source = null,
    int $epoch = 31,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::planCompleteMasterReadMembershipFence(
    $path ?? $databasePath,
    $masterJournalPath ?? $masterPath,
    $master ?? $masterBytes,
    $bytes ?? $databaseBytes,
    $size ?? $pageSize,
    $cache ?? $readerCache(),
    $reads ?? [1, 2, 3, 4, 5, 6, 7, 8],
    $pages ?? $current,
    $source ?? $sourceId,
    $epoch,
);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next187'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'complete_master_journal_read_membership_fences_prefix_reader_cache'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $databasePath],
    'master path' => [static fn (): mixed => $plan()['master_journal_path'], $masterPath],
    'page size' => [static fn (): mixed => $plan()['page_size'], 512],
    'members' => [static fn (): mixed => $plan()['current_members'], $members],
    'ordinals' => [static fn (): mixed => $plan()['current_member_ordinals'], $ordinals],
    'byte length' => [static fn (): mixed => $plan()['current_master_byte_length'], strlen($masterBytes)],
    'complete read digest' => [static fn (): mixed => $plan()['current_complete_read_digest'], $digest],
    'current source id' => [static fn (): mixed => $plan()['current_source']['id'], $sourceId],
    'current epoch' => [static fn (): mixed => $plan()['current_source']['epoch'], 31],
    'next source prefix' => [static fn (): mixed => str_starts_with($plan()['next_source']['id'], 'master-reader-cache-complete-read:'), true],
    'next epoch' => [static fn (): mixed => $plan()['next_source']['epoch'], 32],
    'row count' => [static fn (): mixed => count($plan()['reader_rows']), 8],
    'retained pages' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1, 2]],
    'refreshed pages' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], [3]],
    'invalidated pages' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [4, 5, 6, 7, 8]],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'retained reason' => [static fn (): mixed => $plan()['reader_rows'][0]['reason'], 'reader_cache_matches_complete_master_read'],
    'refresh reason' => [static fn (): mixed => $plan()['reader_rows'][2]['reason'], 'reader_cache_refreshed_from_complete_master_read'],
    'prefix digest reason' => [static fn (): mixed => $plan()['reader_rows'][3]['reason'], 'reader_cache_master_complete_read_digest_changed'],
    'ordinal changed reason' => [static fn (): mixed => $plan()['reader_rows'][4]['reason'], 'reader_cache_master_member_ordinal_changed'],
    'byte length reason' => [static fn (): mixed => $plan()['reader_rows'][5]['reason'], 'reader_cache_master_byte_length_changed'],
    'dirty reason' => [static fn (): mixed => $plan()['reader_rows'][6]['reason'], 'dirty_reader_cache_before_complete_master_membership'],
    'pinned reason' => [static fn (): mixed => $plan()['reader_rows'][7]['reason'], 'pinned_reader_cache_image_predates_complete_master_read'],
    'prefix missing members' => [static fn (): mixed => $plan()['reader_rows'][3]['missing_members'], [$metaJournal, $usersJournal]],
    'ordinal mismatch map' => [static fn (): mixed => $plan()['reader_rows'][4]['ordinal_mismatch'][$mainJournal], ['before' => 2, 'current' => 1]],
    'complete digest matches retained' => [static fn (): mixed => $plan()['reader_rows'][0]['complete_read_digest_matches'], true],
    'complete digest mismatch prefix' => [static fn (): mixed => $plan()['reader_rows'][3]['complete_read_digest_matches'], false],
    'byte length before stale' => [static fn (): mixed => $plan()['reader_rows'][5]['master_byte_length_before'], strlen($masterBytes) - 1],
    'byte length current' => [static fn (): mixed => $plan()['reader_rows'][5]['master_byte_length_current'], strlen($masterBytes)],
    'image retained matches' => [static fn (): mixed => $plan()['reader_rows'][1]['image_matches_current_source'], true],
    'image refreshed mismatch' => [static fn (): mixed => $plan()['reader_rows'][2]['image_matches_current_source'], false],
    'cache prefix retained' => [static fn (): mixed => $plan()['reader_rows'][0]['cache_prefix'], 'next187 schema before complete master membership read'],
    'current prefix refreshed' => [static fn (): mixed => $plan()['reader_rows'][2]['current_prefix'], 'next187 active_plugins after attached member recovery'],
    'next read count' => [static fn (): mixed => count($plan()['next_reads']), 8],
    'next read retained hit' => [static fn (): mixed => $plan()['next_reads'][0]['cache_hit'], true],
    'next read refreshed hit' => [static fn (): mixed => $plan()['next_reads'][2]['cache_hit'], true],
    'next read invalidated miss' => [static fn (): mixed => $plan()['next_reads'][3]['cache_hit'], false],
    'next read digest carried' => [static fn (): mixed => $plan()['next_reads'][0]['complete_read_digest'], $digest],
    'next read epoch' => [static fn (): mixed => $plan()['next_reads'][0]['epoch'], 32],
    'next read refreshed prefix' => [static fn (): mixed => $plan()['next_reads'][2]['prefix'], 'next187 active_plugins after attached member recovery'],
    'operation read complete' => [static fn (): mixed => $plan()['operations'][0]['op'], 'read_complete_master_journal_membership_next187'],
    'operation retain' => [static fn (): mixed => $plan()['operations'][1]['op'], 'retain_reader_cache_after_complete_master_read_next187'],
    'operation refresh' => [static fn (): mixed => $plan()['operations'][3]['op'], 'refresh_reader_cache_after_complete_master_read_next187'],
    'operation invalidate' => [static fn (): mixed => $plan()['operations'][4]['op'], 'invalidate_reader_cache_after_complete_master_read_next187'],
    'operation missing members carried' => [static fn (): mixed => $plan()['operations'][4]['missing_members'], [$metaJournal, $usersJournal]],
    'final prefix current page' => [static fn (): mixed => $plan()['final_prefixes'][4], 'next187 plugin settings after attached member recovery'],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next187', $plan()['dependencies'], true), true],
    'dependency complete read marker' => [static fn (): mixed => in_array('sqlite-pager-master-journal-complete-read-membership-fence', $plan()['dependencies'], true), true],
    'non-overlap mentions next184' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'next184 file read-token'), true],
    'all current cache no reopen' => [static fn (): mixed => $plan([1 => $cacheEntry('schema-retained', $before[1])], [1], [])['requires_reader_reopen'], false],
    'source mismatch invalidates' => [static fn (): mixed => $plan([1 => $cacheEntry('schema-retained', $before[1], ['source_id' => 'old-source'])], [1], [])['reader_rows'][0]['reason'], 'reader_cache_source_predates_complete_master_read'],
    'epoch mismatch invalidates' => [static fn (): mixed => $plan([1 => $cacheEntry('schema-retained', $before[1], ['epoch' => 30])], [1], [])['reader_rows'][0]['reason'], 'reader_cache_epoch_predates_complete_master_read'],
    'missing member alone invalidates' => [static fn (): mixed => $plan([1 => $cacheEntry('schema-retained', $before[1], ['master_member_ordinals' => $prefixOrdinals, 'master_complete_read_digest' => $digest])], [1], [])['reader_rows'][0]['reason'], 'reader_cache_prefix_read_missing_master_members'],
    'duplicate current members collapse' => [static fn (): mixed => $plan(null, [1], [], $masterBytes . $mainJournal . "\n")['current_members'], $members],
    'different master path changes next source' => [static fn (): mixed => $plan(null, [1], [], null, null, null, null, '/tmp/other-mj')['next_source']['id'] !== $plan(null, [1], [])['next_source']['id'], true],
    'prefix digest invalidated read misses' => [static fn (): mixed => $plan([1 => $cacheEntry('schema-retained', $before[1], ['master_complete_read_digest' => $prefixDigest])], [1], [])['next_reads'][0]['cache_hit'], false],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next187 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty database path rejected' => static fn () => $plan(null, null, null, null, null, null, null, ''),
    'empty master path rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, ''),
    'empty source rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, null, ''),
    'empty master bytes rejected' => static fn () => $plan(null, null, null, ''),
    'wrong master members rejected' => static fn () => $plan(null, null, null, '/tmp/other.sqlite-journal'),
    'bad page size rejected' => static fn () => $plan(null, null, null, null, null, 500),
    'empty database bytes rejected' => static fn () => $plan(null, null, null, null, ''),
    'unaligned database bytes rejected' => static fn () => $plan(null, null, null, null, $databaseBytes . 'x'),
    'empty cache rejected' => static fn () => $plan([]),
    'empty reads rejected' => static fn () => $plan(null, []),
    'bad epoch rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, null, null, 0),
    'cache page zero rejected' => static fn () => $plan([0 => $cacheEntry('bad', $before[1])]),
    'short cache image rejected' => static fn () => $plan([1 => $cacheEntry('bad', 'short')]),
    'empty cache source rejected' => static fn () => $plan([1 => $cacheEntry('bad', $before[1], ['source_id' => ''])]),
    'empty cache digest rejected' => static fn () => $plan([1 => $cacheEntry('bad', $before[1], ['master_complete_read_digest' => ''])]),
    'bad cache epoch rejected' => static fn () => $plan([1 => $cacheEntry('bad', $before[1], ['epoch' => 0])]),
    'bad cache byte length rejected' => static fn () => $plan([1 => $cacheEntry('bad', $before[1], ['master_byte_length' => -1])]),
    'missing ordinals rejected' => static fn () => $plan([1 => array_diff_key($cacheEntry('bad', $before[1]), ['master_member_ordinals' => true])]),
    'empty ordinals rejected' => static fn () => $plan([1 => $cacheEntry('bad', $before[1], ['master_member_ordinals' => []])]),
    'empty ordinal member rejected' => static fn () => $plan([1 => $cacheEntry('bad', $before[1], ['master_member_ordinals' => ['' => 1]])]),
    'bad ordinal rejected' => static fn () => $plan([1 => $cacheEntry('bad', $before[1], ['master_member_ordinals' => [$mainJournal => 0]])]),
    'cache outside rejected' => static fn () => $plan([9 => $cacheEntry('bad', $page('outside'))]),
    'bad read page rejected' => static fn () => $plan(null, [0], []),
    'read outside rejected' => static fn () => $plan(null, [9], []),
    'bad current page rejected' => static fn () => $plan(null, [1], [0 => $current[3]]),
    'short current page rejected' => static fn () => $plan(null, [1], [3 => 'short']),
    'current outside rejected' => static fn () => $plan(null, [1], [9 => $page('outside')]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next187 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
