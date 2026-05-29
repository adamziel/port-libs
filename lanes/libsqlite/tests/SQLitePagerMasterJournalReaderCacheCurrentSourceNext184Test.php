<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-next184.sqlite';
$masterPath = '/srv/wp-content/database/wp-next184.sqlite-mj';
$masterBytes = $databasePath . "-journal\n/srv/wp-content/database/wp-next184-meta.sqlite-journal\n";
$members = [$databasePath . '-journal', '/srv/wp-content/database/wp-next184-meta.sqlite-journal'];
$stat = ['device' => 2050, 'inode' => 78184, 'size' => strlen($masterBytes), 'mtime' => 184001, 'ctime' => 184002, 'generation' => 'mj-generation-a', 'readOffset' => 0, 'readLength' => strlen($masterBytes)];
$token = hash('sha256', implode('|', [
    $masterPath,
    (string) $stat['device'],
    (string) $stat['inode'],
    (string) $stat['generation'],
    $stat['size'],
    $stat['mtime'],
    $stat['ctime'],
    $stat['readOffset'],
    $stat['readLength'],
    implode("\n", $members),
    hash('sha256', $masterBytes),
]));
$sourceId = 'next184-current-master-read-token-source';
$page = static fn (string $label, string $pad = '.'): string => str_pad($label, $pageSize, $pad, STR_PAD_RIGHT);
$before = [
    1 => $page('next184 current schema before master journal read token'),
    2 => $page('next184 current wp_options root before master journal read token'),
    3 => $page('next184 stale active_plugins before master journal read token'),
    4 => $page('next184 stale plugin settings before master journal read token'),
    5 => $page('next184 unchanged comments before master journal read token'),
    6 => $page('next184 stale transients before master journal read token'),
    7 => $page('next184 stale cron before master journal read token'),
    8 => $page('next184 stale optionmeta before master journal read token'),
];
$refreshed = [
    3 => $page('next184 current active_plugins after recreated master journal'),
    4 => $page('next184 current plugin settings after recreated master journal'),
];
$databaseBytes = implode('', $before);
$cacheEntry = static fn (string $label, string $image, array $extra = []): array => array_merge([
    'label' => $label,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 21,
    'master_members' => $members,
    'master_read_token' => $token,
    'master_generation' => 'mj-generation-a',
    'master_size' => strlen($masterBytes),
], $extra);
$readerCache = static fn (): array => [
    1 => $cacheEntry('schema-retained', $before[1]),
    2 => $cacheEntry('root-retained', $before[2]),
    3 => $cacheEntry('active-refreshable', $before[3]),
    4 => $cacheEntry('settings-stale-token', $refreshed[4], ['master_read_token' => hash('sha256', 'old-master-read')]),
    5 => $cacheEntry('comments-stale-generation', $before[5], ['master_generation' => 'mj-generation-old']),
    6 => $cacheEntry('transients-stale-size', $before[6], ['master_size' => strlen($masterBytes) - 1]),
    7 => $cacheEntry('cron-pinned-stale-image', $page('next184 pinned cron image from previous master journal'), ['pinned' => true]),
    8 => $cacheEntry('optionmeta-dirty', $before[8], ['dirty' => true]),
];

$plan = static fn (
    ?array $cache = null,
    ?array $reads = null,
    ?array $refresh = null,
    ?string $master = null,
    ?array $masterStat = null,
    ?string $bytes = null,
    ?int $size = null,
    ?string $path = null,
    ?string $masterJournalPath = null,
    ?string $source = null,
    int $epoch = 21,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantNext184(
    $path ?? $databasePath,
    $masterJournalPath ?? $masterPath,
    $master ?? $masterBytes,
    $masterStat ?? $stat,
    $bytes ?? $databaseBytes,
    $size ?? $pageSize,
    $cache ?? $readerCache(),
    $reads ?? [1, 2, 3, 4, 5, 6, 7, 8],
    $refresh ?? $refreshed,
    $source ?? $sourceId,
    $epoch,
);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next184'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_read_token_fences_reader_cache_across_recreated_current_source'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $databasePath],
    'master path' => [static fn (): mixed => $plan()['master_journal_path'], $masterPath],
    'page size' => [static fn (): mixed => $plan()['page_size'], 512],
    'members' => [static fn (): mixed => $plan()['current_members'], $members],
    'member digest' => [static fn (): mixed => $plan()['current_member_digest'], hash('sha256', implode("\n", $members))],
    'stat device' => [static fn (): mixed => $plan()['current_master_stat']['device'], '2050'],
    'stat inode' => [static fn (): mixed => $plan()['current_master_stat']['inode'], '78184'],
    'stat size' => [static fn (): mixed => $plan()['current_master_stat']['size'], strlen($masterBytes)],
    'stat generation' => [static fn (): mixed => $plan()['current_master_stat']['generation'], 'mj-generation-a'],
    'read token' => [static fn (): mixed => $plan()['current_master_read_token'], $token],
    'current source id' => [static fn (): mixed => $plan()['current_source']['id'], $sourceId],
    'current epoch' => [static fn (): mixed => $plan()['current_source']['epoch'], 21],
    'next source prefix' => [static fn (): mixed => str_starts_with($plan()['next_source']['id'], 'master-reader-cache-read-token:'), true],
    'next epoch' => [static fn (): mixed => $plan()['next_source']['epoch'], 22],
    'row count' => [static fn (): mixed => count($plan()['reader_rows']), 8],
    'retained pages' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1, 2]],
    'refreshed pages' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], [3]],
    'invalidated pages' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [4, 5, 6, 7, 8]],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'retained reason' => [static fn (): mixed => $plan()['reader_rows'][0]['reason'], 'reader_cache_matches_master_read_token_source'],
    'refresh reason' => [static fn (): mixed => $plan()['reader_rows'][2]['reason'], 'reader_cache_refreshed_from_master_read_token_source'],
    'token changed reason' => [static fn (): mixed => $plan()['reader_rows'][3]['reason'], 'reader_cache_master_read_token_changed'],
    'generation changed reason' => [static fn (): mixed => $plan()['reader_rows'][4]['reason'], 'reader_cache_master_generation_changed'],
    'size changed reason' => [static fn (): mixed => $plan()['reader_rows'][5]['reason'], 'reader_cache_master_size_changed'],
    'pinned stale reason' => [static fn (): mixed => $plan()['reader_rows'][6]['reason'], 'pinned_reader_cache_image_predates_master_read_token'],
    'dirty reason' => [static fn (): mixed => $plan()['reader_rows'][7]['reason'], 'dirty_reader_cache_from_prior_master_journal_generation'],
    'token match true' => [static fn (): mixed => $plan()['reader_rows'][0]['master_read_token_matches'], true],
    'token match false' => [static fn (): mixed => $plan()['reader_rows'][3]['master_read_token_matches'], false],
    'generation before old' => [static fn (): mixed => $plan()['reader_rows'][4]['master_generation_before'], 'mj-generation-old'],
    'generation current' => [static fn (): mixed => $plan()['reader_rows'][4]['master_generation_current'], 'mj-generation-a'],
    'size before stale' => [static fn (): mixed => $plan()['reader_rows'][5]['master_size_before'], strlen($masterBytes) - 1],
    'size current' => [static fn (): mixed => $plan()['reader_rows'][5]['master_size_current'], strlen($masterBytes)],
    'image match retained' => [static fn (): mixed => $plan()['reader_rows'][1]['image_matches_current_source'], true],
    'image match refresh false' => [static fn (): mixed => $plan()['reader_rows'][2]['image_matches_current_source'], false],
    'next read count' => [static fn (): mixed => count($plan()['next_reads']), 8],
    'read retained hit' => [static fn (): mixed => $plan()['next_reads'][0]['cache_hit'], true],
    'read refreshed hit' => [static fn (): mixed => $plan()['next_reads'][2]['cache_hit'], true],
    'read invalidated miss' => [static fn (): mixed => $plan()['next_reads'][3]['cache_hit'], false],
    'read source epoch' => [static fn (): mixed => $plan()['next_reads'][0]['epoch'], 22],
    'read token carried' => [static fn (): mixed => $plan()['next_reads'][0]['master_read_token'], $token],
    'read refreshed prefix' => [static fn (): mixed => $plan()['next_reads'][2]['prefix'], 'next184 current active_plugins after recreated master journal'],
    'operation read token' => [static fn (): mixed => $plan()['operations'][0]['op'], 'read_current_master_journal_with_generation_token_next184'],
    'operation retain' => [static fn (): mixed => $plan()['operations'][1]['op'], 'retain_reader_cache_after_master_read_token_recheck_next184'],
    'operation refresh' => [static fn (): mixed => $plan()['operations'][3]['op'], 'refresh_reader_cache_after_master_read_token_recheck_next184'],
    'operation invalidate' => [static fn (): mixed => $plan()['operations'][4]['op'], 'invalidate_reader_cache_after_master_read_token_recheck_next184'],
    'operation read hit' => [static fn (): mixed => $plan()['operations'][9]['op'], 'next_read_uses_master_read_token_reader_cache_next184'],
    'operation read miss' => [static fn (): mixed => $plan()['operations'][12]['op'], 'next_read_reopens_after_master_read_token_change_next184'],
    'final source refreshed' => [static fn (): mixed => $plan()['final_sources'][3], 'master-journal-read-token-current-source-next184'],
    'final source untouched' => [static fn (): mixed => $plan()['final_sources'][1], 'database-before-master-read-token-reader-cache-next184'],
    'final prefix refreshed' => [static fn (): mixed => $plan()['final_prefixes'][4], 'next184 current plugin settings after recreated master journal'],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next184', $plan()['dependencies'], true), true],
    'dependency token marker' => [static fn (): mixed => in_array('sqlite-pager-master-journal-read-token-source-fence-next184', $plan()['dependencies'], true), true],
    'non-overlap mentions next181' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'next181 pending membership'), true],
    'all cache valid no reopen' => [static fn (): mixed => $plan([1 => $cacheEntry('schema-retained', $before[1])], [1], [])['requires_reader_reopen'], false],
    'member change invalidates' => [static fn (): mixed => $plan([1 => $cacheEntry('schema-retained', $before[1], ['master_members' => [$databasePath . '-journal']])], [1], [])['reader_rows'][0]['reason'], 'reader_cache_master_member_set_changed_next184'],
    'source mismatch invalidates' => [static fn (): mixed => $plan([1 => $cacheEntry('schema-retained', $before[1], ['source_id' => 'old-source'])], [1], [])['reader_rows'][0]['reason'], 'reader_cache_source_id_predates_master_read_token'],
    'epoch mismatch invalidates' => [static fn (): mixed => $plan([1 => $cacheEntry('schema-retained', $before[1], ['epoch' => 20])], [1], [])['reader_rows'][0]['reason'], 'reader_cache_epoch_predates_master_read_token'],
    'duplicate master members collapsed' => [static fn (): mixed => $plan(null, [1], [], $masterBytes . $databasePath . "-journal\n", array_merge($stat, ['size' => strlen($masterBytes . $databasePath . "-journal\n"), 'readLength' => strlen($masterBytes . $databasePath . "-journal\n")]))['current_members'], $members],
    'different inode changes token' => [static fn (): mixed => $plan(null, [1], [], null, array_merge($stat, ['inode' => 78185]))['current_master_read_token'] !== $token, true],
    'different generation changes token' => [static fn (): mixed => $plan(null, [1], [], null, array_merge($stat, ['generation' => 'mj-generation-b']))['current_master_read_token'] !== $token, true],
    'different mtime changes token' => [static fn (): mixed => $plan(null, [1], [], null, array_merge($stat, ['mtime' => 184099]))['current_master_read_token'] !== $token, true],
    'same textual members still invalidated by token' => [static fn (): mixed => $plan([1 => $cacheEntry('schema-retained', $before[1], ['master_read_token' => hash('sha256', 'same-members-old-file')])], [1], [])['invalidated_cache_page_numbers'], [1]],
    'invalidated token read misses cache' => [static fn (): mixed => $plan([1 => $cacheEntry('schema-retained', $before[1], ['master_read_token' => hash('sha256', 'same-members-old-file')])], [1], [])['next_reads'][0]['cache_hit'], false],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next184 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty database path rejected' => static fn () => $plan(null, null, null, null, null, null, null, ''),
    'empty master path rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, ''),
    'empty source rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, null, ''),
    'empty master bytes rejected' => static fn () => $plan(null, null, null, ''),
    'wrong master members rejected' => static fn () => $plan(null, null, null, '/tmp/other.sqlite-journal'),
    'bad page size rejected' => static fn () => $plan(null, null, null, null, null, null, 500),
    'empty database bytes rejected' => static fn () => $plan(null, null, null, null, null, ''),
    'unaligned database bytes rejected' => static fn () => $plan(null, null, null, null, null, $databaseBytes . 'x'),
    'empty cache rejected' => static fn () => $plan([]),
    'empty reads rejected' => static fn () => $plan(null, []),
    'bad epoch rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, null, null, null, 0),
    'missing stat device rejected' => static fn () => $plan(null, null, null, null, array_diff_key($stat, ['device' => true])),
    'missing stat inode rejected' => static fn () => $plan(null, null, null, null, array_diff_key($stat, ['inode' => true])),
    'missing stat generation rejected' => static fn () => $plan(null, null, null, null, array_diff_key($stat, ['generation' => true])),
    'bad stat size rejected' => static fn () => $plan(null, null, null, null, array_merge($stat, ['size' => -1])),
    'partial read rejected' => static fn () => $plan(null, null, null, null, array_merge($stat, ['readLength' => 4])),
    'offset read rejected' => static fn () => $plan(null, null, null, null, array_merge($stat, ['readOffset' => 1])),
    'cache page zero rejected' => static fn () => $plan([0 => $cacheEntry('bad', $before[1])]),
    'short cache image rejected' => static fn () => $plan([1 => $cacheEntry('bad', 'short')]),
    'empty cache source rejected' => static fn () => $plan([1 => $cacheEntry('bad', $before[1], ['source_id' => ''])]),
    'empty cache token rejected' => static fn () => $plan([1 => $cacheEntry('bad', $before[1], ['master_read_token' => ''])]),
    'bad cache epoch rejected' => static fn () => $plan([1 => $cacheEntry('bad', $before[1], ['epoch' => 0])]),
    'bad cache master size rejected' => static fn () => $plan([1 => $cacheEntry('bad', $before[1], ['master_size' => -1])]),
    'missing cache members rejected' => static fn () => $plan([1 => array_diff_key($cacheEntry('bad', $before[1]), ['master_members' => true])]),
    'empty cache member rejected' => static fn () => $plan([1 => $cacheEntry('bad', $before[1], ['master_members' => ['']])]),
    'empty cache generation rejected' => static fn () => $plan([1 => $cacheEntry('bad', $before[1], ['master_generation' => ''])]),
    'cache outside rejected' => static fn () => $plan([9 => $cacheEntry('bad', $page('outside'))]),
    'bad read page rejected' => static fn () => $plan(null, [0], []),
    'read outside rejected' => static fn () => $plan(null, [9], []),
    'bad refresh page rejected' => static fn () => $plan(null, [1], [0 => $refreshed[3]]),
    'short refresh image rejected' => static fn () => $plan(null, [1], [3 => 'short']),
    'refresh outside rejected' => static fn () => $plan(null, [1], [9 => $page('outside')]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next184 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
