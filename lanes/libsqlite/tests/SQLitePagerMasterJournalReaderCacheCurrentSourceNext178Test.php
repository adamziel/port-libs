<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-next178.sqlite';
$masterPath = '/srv/wp-content/database/wp-next178.sqlite-mj';
$mainJournal = $databasePath . '-journal';
$usersJournal = '/srv/wp-content/database/wp-next178-users.sqlite-journal';
$termsJournal = '/srv/wp-content/database/wp-next178-terms.sqlite-journal';
$masterBytes = $mainJournal . "\n" . $usersJournal . "\n" . $termsJournal . "\n";
$masterDigest = hash('sha256', $mainJournal . "\n" . $usersJournal . "\n" . $termsJournal);
$sourceId = 'next178-current-source';
$page = static fn (string $label, string $pad = '.'): string => str_pad($label, $pageSize, $pad, STR_PAD_RIGHT);

$before = [
    1 => $page('next178 stale schema before member recovery'),
    2 => $page('next178 stale wp_options root before member recovery'),
    3 => $page('next178 stale active_plugins before member recovery'),
    4 => $page('next178 stale plugin settings before member recovery'),
    5 => $page('next178 unchanged comments before member recovery'),
    6 => $page('next178 stale users page before member recovery'),
    7 => $page('next178 stale terms page before member recovery'),
    8 => $page('next178 stale optionmeta before member recovery'),
];
$recovered = [
    1 => $page('next178 recovered schema after member recovery'),
    2 => $page('next178 recovered wp_options root after member recovery'),
    3 => $page('next178 recovered active_plugins after member recovery'),
    4 => $page('next178 recovered plugin settings after member recovery'),
    6 => $page('next178 recovered users after member recovery'),
    7 => $page('next178 recovered terms after member recovery'),
];
$memberStates = [
    $mainJournal => ['generation' => 12, 'deleted' => true, 'recovered' => true],
    $usersJournal => ['generation' => 7, 'deleted' => false, 'recovered' => true],
    $termsJournal => ['generation' => 4, 'deleted' => true, 'recovered' => false],
];
$cache = static fn (): array => [
    1 => ['reader_id' => 'schema', 'image' => $recovered[1], 'source_id' => $sourceId, 'epoch' => 14, 'member_journal' => $mainJournal, 'member_generation' => 12, 'master_digest' => $masterDigest],
    2 => ['reader_id' => 'root-refresh', 'image' => $before[2], 'source_id' => $sourceId, 'epoch' => 14, 'member_journal' => $mainJournal, 'member_generation' => 12, 'master_digest' => $masterDigest],
    3 => ['reader_id' => 'active-old-generation', 'image' => $recovered[3], 'source_id' => $sourceId, 'epoch' => 14, 'member_journal' => $mainJournal, 'member_generation' => 11, 'master_digest' => $masterDigest],
    4 => ['reader_id' => 'settings-dirty', 'image' => $recovered[4], 'source_id' => $sourceId, 'epoch' => 14, 'member_journal' => $mainJournal, 'member_generation' => 12, 'master_digest' => $masterDigest, 'dirty' => true],
    5 => ['reader_id' => 'comments-source', 'image' => $before[5], 'source_id' => 'old-source', 'epoch' => 14, 'member_journal' => $mainJournal, 'member_generation' => 12, 'master_digest' => $masterDigest],
    6 => ['reader_id' => 'users-not-deleted', 'image' => $recovered[6], 'source_id' => $sourceId, 'epoch' => 14, 'member_journal' => $usersJournal, 'member_generation' => 7, 'master_digest' => $masterDigest],
    7 => ['reader_id' => 'terms-not-recovered', 'image' => $recovered[7], 'source_id' => $sourceId, 'epoch' => 14, 'member_journal' => $termsJournal, 'member_generation' => 4, 'master_digest' => $masterDigest],
    8 => ['reader_id' => 'optionmeta-master', 'image' => $before[8], 'source_id' => $sourceId, 'epoch' => 14, 'member_journal' => $mainJournal, 'member_generation' => 12, 'master_digest' => hash('sha256', 'old-master')],
];
$writes = [
    3 => $page('next178 rewritten active_plugins after member generation fence'),
    4 => $page('next178 rewritten plugin settings after member generation fence'),
];

$plan = static fn (
    ?array $recoveredPages = null,
    ?array $readerCache = null,
    ?array $states = null,
    ?array $reads = null,
    ?array $writePages = null,
    ?string $master = null,
    ?string $database = null,
    ?int $size = null,
    ?string $path = null,
    ?string $masterJournalPath = null,
    ?string $source = null,
    int $epoch = 14,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext178(
    $path ?? $databasePath,
    $masterJournalPath ?? $masterPath,
    $master ?? $masterBytes,
    $database ?? implode('', $before),
    $size ?? $pageSize,
    $recoveredPages ?? $recovered,
    $readerCache ?? $cache(),
    $states ?? $memberStates,
    $reads ?? [1, 2, 3, 4, 5, 6, 7, 8],
    $writePages ?? $writes,
    $source ?? $sourceId,
    $epoch,
);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next178'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'current master-journal member generations and delete state fence reader-cache reuse'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $databasePath],
    'master path' => [static fn (): mixed => $plan()['master_journal_path'], $masterPath],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], $mainJournal],
    'page size' => [static fn (): mixed => $plan()['page_size'], 512],
    'members' => [static fn (): mixed => $plan()['master_members'], [$mainJournal, $usersJournal, $termsJournal]],
    'master digest' => [static fn (): mixed => $plan()['master_digest'], $masterDigest],
    'member rows count' => [static fn (): mixed => count($plan()['member_rows']), 3],
    'main member admitted' => [static fn (): mixed => $plan()['member_rows'][0]['admitted'], true],
    'users member reason' => [static fn (): mixed => $plan()['member_rows'][1]['reason'], 'member_journal_not_deleted_after_recovery'],
    'terms member reason' => [static fn (): mixed => $plan()['member_rows'][2]['reason'], 'member_journal_not_recovered'],
    'recovered pages' => [static fn (): mixed => $plan()['recovered_page_numbers'], [1, 2, 3, 4, 6, 7]],
    'cache row count' => [static fn (): mixed => count($plan()['cache_rows']), 8],
    'retained pages' => [static fn (): mixed => $plan()['retained_page_numbers'], [1]],
    'refreshed pages' => [static fn (): mixed => $plan()['refreshed_page_numbers'], [2]],
    'invalidated pages' => [static fn (): mixed => $plan()['invalidated_page_numbers'], [3, 4, 5, 6, 7, 8]],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'retained reason' => [static fn (): mixed => $plan()['cache_rows'][0]['reason'], 'reader_cache_matches_recovered_member_current_source'],
    'refreshed reason' => [static fn (): mixed => $plan()['cache_rows'][1]['reason'], 'reader_cache_refreshed_from_recovered_member_current_source'],
    'generation mismatch reason' => [static fn (): mixed => $plan()['cache_rows'][2]['reason'], 'reader_cache_member_generation_mismatch'],
    'dirty reason' => [static fn (): mixed => $plan()['cache_rows'][3]['reason'], 'dirty_reader_cache_cannot_cross_master_member_boundary'],
    'source reason' => [static fn (): mixed => $plan()['cache_rows'][4]['reason'], 'reader_cache_source_id_mismatch_after_member_recovery'],
    'not deleted reason' => [static fn (): mixed => $plan()['cache_rows'][5]['reason'], 'reader_cache_member_journal_not_deleted'],
    'not recovered reason' => [static fn (): mixed => $plan()['cache_rows'][6]['reason'], 'reader_cache_member_journal_not_recovered'],
    'master digest reason' => [static fn (): mixed => $plan()['cache_rows'][7]['reason'], 'reader_cache_master_digest_mismatch_after_member_recovery'],
    'row generation before' => [static fn (): mixed => $plan()['cache_rows'][2]['member_generation_before'], 11],
    'row generation current' => [static fn (): mixed => $plan()['cache_rows'][2]['member_generation_current'], 12],
    'row not deleted flag' => [static fn (): mixed => $plan()['cache_rows'][5]['member_deleted'], false],
    'row not recovered flag' => [static fn (): mixed => $plan()['cache_rows'][6]['member_recovered'], false],
    'read count' => [static fn (): mixed => count($plan()['next_reads']), 8],
    'read one hit' => [static fn (): mixed => $plan()['next_reads'][0]['cache_hit'], true],
    'read two refreshed hit' => [static fn (): mixed => $plan()['next_reads'][1]['cache_hit'], true],
    'read three miss' => [static fn (): mixed => $plan()['next_reads'][2]['cache_hit'], false],
    'read two prefix' => [static fn (): mixed => $plan()['next_reads'][1]['prefix'], 'next178 recovered wp_options root after member recovery'],
    'read six source' => [static fn (): mixed => $plan()['next_reads'][5]['source'], 'master-journal-member-generation-current-source-next178'],
    'write count' => [static fn (): mixed => count($plan()['next_writes']), 2],
    'write active before' => [static fn (): mixed => $plan()['next_writes'][0]['before_prefix'], 'next178 recovered active_plugins after member recovery'],
    'write active after' => [static fn (): mixed => $plan()['next_writes'][0]['after_prefix'], 'next178 rewritten active_plugins after member generation fence'],
    'write journal flag' => [static fn (): mixed => $plan()['next_writes'][1]['journal_before_from_recovered_member_source'], true],
    'final page three source' => [static fn (): mixed => $plan()['final_sources'][3], 'next-write-after-member-generation-reader-cache-next178'],
    'final page four prefix' => [static fn (): mixed => $plan()['final_prefixes'][4], 'next178 rewritten plugin settings after member generation fence'],
    'final bytes include rewrite' => [static fn (): mixed => str_contains($plan()['final_database_bytes'], 'rewritten active_plugins'), true],
    'operation read master' => [static fn (): mixed => $plan()['operations'][0]['op'], 'read_current_master_journal_member_generation_next178'],
    'operation verify members' => [static fn (): mixed => $plan()['operations'][1]['op'], 'verify_member_journal_recovery_and_delete_state_next178'],
    'operation retain' => [static fn (): mixed => $plan()['operations'][2]['op'], 'retain_reader_cache_member_generation_next178'],
    'operation refresh' => [static fn (): mixed => $plan()['operations'][3]['op'], 'refresh_reader_cache_member_generation_next178'],
    'operation invalidate' => [static fn (): mixed => $plan()['operations'][4]['op'], 'invalidate_reader_cache_member_generation_next178'],
    'operation read cache' => [static fn (): mixed => $plan()['operations'][10]['op'], 'next_read_uses_member_generation_reader_cache_next178'],
    'operation read source' => [static fn (): mixed => $plan()['operations'][12]['op'], 'next_read_uses_member_generation_current_source_next178'],
    'operation write' => [static fn (): mixed => $plan()['operations'][18]['op'], 'capture_next_write_after_member_generation_reader_cache_next178'],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next178', $plan()['dependencies'], true), true],
    'non overlap mentions generation' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'generation'), true],
    'duplicate members collapsed' => [static fn (): mixed => $plan(null, null, null, [1], [], $masterBytes . $mainJournal . "\n")['master_members'], [$mainJournal, $usersJournal, $termsJournal]],
    'all valid no reopen' => [static function () use ($plan, $cache, $memberStates, $usersJournal, $termsJournal): mixed {
        $states = $memberStates;
        $states[$usersJournal]['deleted'] = true;
        $states[$termsJournal]['recovered'] = true;
        return $plan(null, [1 => $cache()[1]], $states, [1], [])['requires_reader_reopen'];
    }, false],
    'pinned mismatch reason' => [static fn (): mixed => $plan(null, [1 => array_merge($cache()[1], ['image' => $before[1], 'pinned' => true])], null, [1], [])['cache_rows'][0]['reason'], 'pinned_reader_cache_image_predates_member_recovery'],
    'epoch mismatch reason' => [static fn (): mixed => $plan(null, [1 => array_merge($cache()[1], ['epoch' => 13])], null, [1], [])['cache_rows'][0]['reason'], 'reader_cache_epoch_mismatch_after_member_recovery'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next178 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty database path rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, ''),
    'empty master path rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, null, ''),
    'empty source rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, null, null, ''),
    'empty master rejected' => static fn () => $plan(null, null, null, null, null, ''),
    'wrong master rejected' => static fn () => $plan(null, null, null, null, null, '/tmp/other.sqlite-journal'),
    'bad page size rejected' => static fn () => $plan(null, null, null, null, null, null, null, 500),
    'empty database rejected' => static fn () => $plan(null, null, null, null, null, null, ''),
    'unaligned database rejected' => static fn () => $plan(null, null, null, null, null, null, implode('', $before) . 'x'),
    'empty recovered rejected' => static fn () => $plan([]),
    'empty cache rejected' => static fn () => $plan(null, []),
    'empty states rejected' => static fn () => $plan(null, null, []),
    'empty next work rejected' => static fn () => $plan(null, null, null, [], []),
    'bad epoch rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, null, null, null, 0),
    'zero recovered rejected' => static fn () => $plan([0 => $recovered[1]]),
    'short recovered rejected' => static fn () => $plan([1 => 'short']),
    'recovered outside rejected' => static fn () => $plan([9 => $page('outside')]),
    'missing member state rejected' => static fn () => $plan(null, null, [$mainJournal => $memberStates[$mainJournal]]),
    'bad member generation rejected' => static fn () => $plan(null, null, array_merge($memberStates, [$mainJournal => ['generation' => 0, 'deleted' => true, 'recovered' => true]])),
    'zero cache page rejected' => static fn () => $plan(null, [0 => $cache()[1]]),
    'short cache image rejected' => static fn () => $plan(null, [1 => array_merge($cache()[1], ['image' => 'short'])]),
    'empty cache source rejected' => static fn () => $plan(null, [1 => array_merge($cache()[1], ['source_id' => ''])]),
    'bad cache epoch rejected' => static fn () => $plan(null, [1 => array_merge($cache()[1], ['epoch' => 0])]),
    'empty member journal rejected' => static fn () => $plan(null, [1 => array_merge($cache()[1], ['member_journal' => ''])]),
    'bad cache generation rejected' => static fn () => $plan(null, [1 => array_merge($cache()[1], ['member_generation' => 0])]),
    'empty master digest rejected' => static fn () => $plan(null, [1 => array_merge($cache()[1], ['master_digest' => ''])]),
    'cache outside rejected' => static fn () => $plan(null, [9 => array_merge($cache()[1], ['image' => $page('outside')])]),
    'bad read rejected' => static fn () => $plan(null, null, null, [0], []),
    'read outside rejected' => static fn () => $plan(null, null, null, [9], []),
    'bad write rejected' => static fn () => $plan(null, null, null, [], [0 => $writes[3]]),
    'short write rejected' => static fn () => $plan(null, null, null, [], [3 => 'short']),
    'write outside rejected' => static fn () => $plan(null, null, null, [], [9 => $page('outside')]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next178 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
