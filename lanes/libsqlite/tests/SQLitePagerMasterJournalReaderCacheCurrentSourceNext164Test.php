<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-next164.sqlite';
$masterPath = '/srv/wp-content/database/wp-next164.sqlite-mj';
$masterBytes = $databasePath . "-journal\n/srv/wp-content/database/wp-next164-meta.sqlite-journal\n";
$masterDigest = hash('sha256', $databasePath . "-journal\n/srv/wp-content/database/wp-next164-meta.sqlite-journal");
$sourceId = 'next164-before-master-header';
$page = static fn (string $label, string $pad = '.'): string => str_pad($label, $pageSize, $pad, STR_PAD_RIGHT);
$headerPage = static function (string $label, int $changeCounter, int $schemaCookie, int $versionValidFor) use ($page, $pageSize): string {
    $bytes = $page($label);
    $bytes = substr_replace($bytes, pack('N', $changeCounter), 24, 4);
    $bytes = substr_replace($bytes, pack('N', $schemaCookie), 40, 4);
    $bytes = substr_replace($bytes, pack('N', $versionValidFor), 92, 4);
    return str_pad(substr($bytes, 0, $pageSize), $pageSize, '.', STR_PAD_RIGHT);
};

$before = [
    1 => $headerPage('next164 stale sqlite header before master journal', 10, 20, 10),
    2 => $page('next164 stale wp_options root before recovered header'),
    3 => $page('next164 stale active_plugins before recovered header'),
    4 => $page('next164 stale plugin settings before recovered header'),
    5 => $page('next164 unchanged comments page before recovered header'),
    6 => $page('next164 stale transients before recovered header'),
    7 => $page('next164 stale cron option before recovered header'),
];
$recovered = [
    1 => $headerPage('next164 recovered sqlite header after master journal', 11, 21, 11),
    2 => $page('next164 recovered wp_options root after master journal'),
    3 => $page('next164 recovered active_plugins after master journal'),
    4 => $page('next164 recovered plugin settings after master journal'),
    6 => $page('next164 recovered transients after master journal'),
    7 => $page('next164 recovered cron option after master journal'),
];
$databaseBytes = implode('', $before);
$header = ['change_counter' => 11, 'schema_cookie' => 21, 'version_valid_for' => 11];
$cacheHeader = ['change_counter' => 11, 'schema_cookie' => 21, 'version_valid_for' => 11];
$staleCounter = ['change_counter' => 10, 'schema_cookie' => 21, 'version_valid_for' => 11];
$staleSchema = ['change_counter' => 11, 'schema_cookie' => 20, 'version_valid_for' => 11];
$staleValidFor = ['change_counter' => 11, 'schema_cookie' => 21, 'version_valid_for' => 10];
$cacheEntry = static fn (string $label, string $image, array $meta = [], array $extra = []): array => array_merge([
    'label' => $label,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 9,
    'master_journal_digest' => $masterDigest,
], $meta, $extra);
$readerCache = static fn (): array => [
    1 => $cacheEntry('header-retained', $recovered[1], $cacheHeader),
    2 => $cacheEntry('root-refreshable', $before[2], $cacheHeader),
    3 => $cacheEntry('active-plugins-stale-change-counter', $recovered[3], $staleCounter),
    4 => $cacheEntry('settings-stale-schema-cookie', $recovered[4], $staleSchema),
    5 => $cacheEntry('comments-stale-version-valid-for', $before[5], $staleValidFor),
    6 => $cacheEntry('transients-dirty', $recovered[6], $cacheHeader, ['dirty' => true]),
    7 => $cacheEntry('cron-pinned-image', $before[7], $cacheHeader, ['pinned' => true]),
];
$nextWrites = [
    3 => $page('next164 rewritten active_plugins after header cache fence'),
    4 => $page('next164 rewritten plugin settings after header cache fence'),
];

$plan = static fn (
    ?array $pages = null,
    ?array $cache = null,
    ?array $reads = null,
    ?array $writes = null,
    ?string $master = null,
    ?string $bytes = null,
    ?int $size = null,
    ?string $path = null,
    ?string $masterJournalPath = null,
    ?string $source = null,
    int $epoch = 9,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::planReaderCacheMasterJournalRecoveryFence(
    $path ?? $databasePath,
    $masterJournalPath ?? $masterPath,
    $master ?? $masterBytes,
    $bytes ?? $databaseBytes,
    $size ?? $pageSize,
    $pages ?? $recovered,
    $cache ?? $readerCache(),
    $reads ?? [1, 2, 3, 4, 5, 6, 7],
    $writes ?? $nextWrites,
    $source ?? $sourceId,
    $epoch,
);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next164'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_recovery_header_state_fences_reader_cache_before_next_source'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $databasePath],
    'master path' => [static fn (): mixed => $plan()['master_journal_path'], $masterPath],
    'page size' => [static fn (): mixed => $plan()['page_size'], $pageSize],
    'members' => [static fn (): mixed => $plan()['current_members'], [$databasePath . '-journal', '/srv/wp-content/database/wp-next164-meta.sqlite-journal']],
    'master digest' => [static fn (): mixed => $plan()['current_master_journal_digest'], $masterDigest],
    'header state' => [static fn (): mixed => $plan()['current_header'], $header],
    'input source id' => [static fn (): mixed => $plan()['input_source']['id'], $sourceId],
    'input epoch' => [static fn (): mixed => $plan()['input_source']['epoch'], 9],
    'recovered epoch' => [static fn (): mixed => $plan()['recovered_source']['epoch'], 10],
    'recovered source id prefix' => [static fn (): mixed => str_starts_with($plan()['recovered_source']['id'], 'master-reader-header:'), true],
    'recovered pages' => [static fn (): mixed => $plan()['recovered_page_numbers'], [1, 2, 3, 4, 6, 7]],
    'row count' => [static fn (): mixed => count($plan()['reader_rows']), 7],
    'retained pages' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1]],
    'refreshed pages' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], [2]],
    'invalidated pages' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [3, 4, 5, 6, 7]],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'retained reason' => [static fn (): mixed => $plan()['reader_rows'][0]['reason'], 'reader_cache_matches_current_header_source'],
    'refreshed reason' => [static fn (): mixed => $plan()['reader_rows'][1]['reason'], 'reader_cache_refreshed_from_current_header_source'],
    'change counter reason' => [static fn (): mixed => $plan()['reader_rows'][2]['reason'], 'reader_cache_change_counter_predates_current_header'],
    'schema cookie reason' => [static fn (): mixed => $plan()['reader_rows'][3]['reason'], 'reader_cache_schema_cookie_predates_current_header'],
    'version valid reason' => [static fn (): mixed => $plan()['reader_rows'][4]['reason'], 'reader_cache_version_valid_for_predates_current_header'],
    'dirty reason' => [static fn (): mixed => $plan()['reader_rows'][5]['reason'], 'dirty_reader_cache_from_aborted_master_recovery'],
    'pinned reason' => [static fn (): mixed => $plan()['reader_rows'][6]['reason'], 'pinned_reader_cache_image_predates_current_header'],
    'row current header' => [static fn (): mixed => $plan()['reader_rows'][2]['current_header'], $header],
    'row stale counter' => [static fn (): mixed => $plan()['reader_rows'][2]['cache_header']['change_counter'], 10],
    'row stale schema' => [static fn (): mixed => $plan()['reader_rows'][3]['cache_header']['schema_cookie'], 20],
    'row stale valid for' => [static fn (): mixed => $plan()['reader_rows'][4]['cache_header']['version_valid_for'], 10],
    'digest match retained' => [static fn (): mixed => $plan()['reader_rows'][0]['master_journal_digest_matches_current'], true],
    'image match retained' => [static fn (): mixed => $plan()['reader_rows'][0]['image_matches_current_source'], true],
    'image mismatch refreshed' => [static fn (): mixed => $plan()['reader_rows'][1]['image_matches_current_source'], false],
    'next read count' => [static fn (): mixed => count($plan()['next_reads']), 7],
    'read retained cache hit' => [static fn (): mixed => $plan()['next_reads'][0]['cache_hit'], true],
    'read refreshed cache hit' => [static fn (): mixed => $plan()['next_reads'][1]['cache_hit'], true],
    'read invalidated cache miss' => [static fn (): mixed => $plan()['next_reads'][2]['cache_hit'], false],
    'read current prefix' => [static fn (): mixed => $plan()['next_reads'][2]['prefix'], 'next164 recovered active_plugins after master journal'],
    'read unchanged prefix' => [static fn (): mixed => $plan()['next_reads'][4]['prefix'], 'next164 unchanged comments page before recovered header'],
    'read header counter' => [static fn (): mixed => $plan()['next_reads'][0]['header_change_counter'], 11],
    'read header schema' => [static fn (): mixed => $plan()['next_reads'][0]['header_schema_cookie'], 21],
    'write count' => [static fn (): mixed => count($plan()['next_writes']), 2],
    'write active before' => [static fn (): mixed => $plan()['next_writes'][0]['before_prefix'], 'next164 recovered active_plugins after master journal'],
    'write active after' => [static fn (): mixed => $plan()['next_writes'][0]['after_prefix'], 'next164 rewritten active_plugins after header cache fence'],
    'write settings before' => [static fn (): mixed => $plan()['next_writes'][1]['before_prefix'], 'next164 recovered plugin settings after master journal'],
    'write journal flag' => [static fn (): mixed => $plan()['next_writes'][1]['journal_before_from_current_header_source'], true],
    'operation read master' => [static fn (): mixed => $plan()['operations'][0]['op'], 'read_current_master_journal_for_header_reader_cache'],
    'operation retain' => [static fn (): mixed => $plan()['operations'][1]['op'], 'retain_reader_cache_after_master_header_recovery'],
    'operation refresh' => [static fn (): mixed => $plan()['operations'][2]['op'], 'refresh_reader_cache_after_master_header_recovery'],
    'operation invalidate counter' => [static fn (): mixed => $plan()['operations'][3]['op'], 'invalidate_reader_cache_after_master_header_recovery'],
    'operation write capture' => [static fn (): mixed => $plan()['operations'][8]['op'], 'capture_next_write_before_image_after_master_header_recovery'],
    'final page one source' => [static fn (): mixed => $plan()['final_sources'][1], 'master-journal-header-current-source'],
    'final page three source' => [static fn (): mixed => $plan()['final_sources'][3], 'next-write-after-master-header-reader-cache'],
    'final page four prefix' => [static fn (): mixed => $plan()['final_prefixes'][4], 'next164 rewritten plugin settings after header cache fence'],
    'final bytes contain rewrite' => [static fn (): mixed => str_contains($plan()['final_database_bytes'], 'rewritten active_plugins'), true],
    'final bytes exclude stale active plugins' => [static fn (): mixed => str_contains($plan()['final_database_bytes'], 'stale active_plugins'), false],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next164', $plan()['dependencies'], true), true],
    'dependency header counter' => [static fn (): mixed => in_array('sqlite-pager-reader-cache-header-change-counter-fence', $plan()['dependencies'], true), true],
    'dependency schema cookie' => [static fn (): mixed => in_array('sqlite-pager-reader-cache-schema-cookie-fence', $plan()['dependencies'], true), true],
    'all cache valid no reopen' => [static fn (): mixed => $plan(null, [1 => $cacheEntry('header-retained', $recovered[1], $cacheHeader)], [1], [])['requires_reader_reopen'], false],
    'matching image stale digest invalidates' => [static fn (): mixed => $plan(null, [1 => $cacheEntry('header-stale-digest', $recovered[1], $cacheHeader, ['master_journal_digest' => hash('sha256', 'old')])], [1], [])['invalidated_cache_page_numbers'], [1]],
    'matching image stale digest reason' => [static fn (): mixed => $plan(null, [1 => $cacheEntry('header-stale-digest', $recovered[1], $cacheHeader, ['master_journal_digest' => hash('sha256', 'old')])], [1], [])['reader_rows'][0]['reason'], 'reader_cache_master_journal_digest_mismatch'],
    'duplicate members collapsed' => [static fn (): mixed => $plan(null, null, [1], [], $masterBytes . $databasePath . "-journal\n")['current_members'], [$databasePath . '-journal', '/srv/wp-content/database/wp-next164-meta.sqlite-journal']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next164 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty database path rejected' => static fn () => $plan(null, null, null, null, null, null, null, ''),
    'empty master path rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, ''),
    'empty source rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, null, ''),
    'missing master bytes rejected' => static fn () => $plan(null, null, null, null, ''),
    'wrong master bytes rejected' => static fn () => $plan(null, null, null, null, '/tmp/other.sqlite-journal'),
    'bad page size rejected' => static fn () => $plan(null, null, null, null, null, null, 500),
    'empty database bytes rejected' => static fn () => $plan(null, null, null, null, null, ''),
    'unaligned database rejected' => static fn () => $plan(null, null, null, null, null, $databaseBytes . 'x'),
    'empty recovered rejected' => static fn () => $plan([]),
    'empty cache rejected' => static fn () => $plan(null, []),
    'empty next work rejected' => static fn () => $plan(null, null, [], []),
    'bad epoch rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, null, null, 0),
    'zero recovered page rejected' => static fn () => $plan([0 => $recovered[1]]),
    'short recovered page rejected' => static fn () => $plan([1 => 'short']),
    'recovered outside rejected' => static fn () => $plan([8 => $page('outside')]),
    'zero cache page rejected' => static fn () => $plan(null, [0 => $cacheEntry('bad', $recovered[1], $cacheHeader)]),
    'short cache image rejected' => static fn () => $plan(null, [1 => $cacheEntry('bad', 'short', $cacheHeader)]),
    'empty cache source rejected' => static fn () => $plan(null, [1 => $cacheEntry('bad', $recovered[1], $cacheHeader, ['source_id' => ''])]),
    'empty cache digest rejected' => static fn () => $plan(null, [1 => $cacheEntry('bad', $recovered[1], $cacheHeader, ['master_journal_digest' => ''])]),
    'bad cache epoch rejected' => static fn () => $plan(null, [1 => $cacheEntry('bad', $recovered[1], $cacheHeader, ['epoch' => 0])]),
    'bad change counter rejected' => static fn () => $plan(null, [1 => $cacheEntry('bad', $recovered[1], ['change_counter' => -1, 'schema_cookie' => 21, 'version_valid_for' => 11])]),
    'bad read page rejected' => static fn () => $plan(null, null, [0], []),
    'read outside rejected' => static fn () => $plan(null, null, [8], []),
    'zero write page rejected' => static fn () => $plan(null, null, [], [0 => $nextWrites[3]]),
    'short write page rejected' => static fn () => $plan(null, null, [], [3 => 'short']),
    'write outside rejected' => static fn () => $plan(null, null, [], [8 => $page('outside')]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next164 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
