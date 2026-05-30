<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-next168.sqlite';
$masterPath = '/srv/wp-content/database/wp-next168.sqlite-mj';
$masterBytes = $databasePath . "-journal\n/srv/wp-content/database/wp-next168-network.sqlite-journal\n";
$masterDigest = hash('sha256', $databasePath . "-journal\n/srv/wp-content/database/wp-next168-network.sqlite-journal");
$sourceId = 'next168-before-master-reader-source';
$sourceDigest = hash('sha256', 'next168-master-current-source');
$generation = 14;
$page = static fn (string $label, string $pad = '.'): string => str_pad($label, $pageSize, $pad, STR_PAD_RIGHT);
$headerPage = static function (string $label, int $changeCounter, int $schemaCookie, int $versionValidFor) use ($page, $pageSize): string {
    $bytes = $page($label);
    $bytes = substr_replace($bytes, pack('N', $changeCounter), 24, 4);
    $bytes = substr_replace($bytes, pack('N', $schemaCookie), 40, 4);
    $bytes = substr_replace($bytes, pack('N', $versionValidFor), 92, 4);

    return str_pad(substr($bytes, 0, $pageSize), $pageSize, '.', STR_PAD_RIGHT);
};
$digest = static fn (int $pageNumber, string $image, string $digestSource = null, int $digestGeneration = null): string => hash(
    'sha256',
    ($digestSource ?? $sourceDigest) . '|' . ($digestGeneration ?? $generation) . '|' . $pageNumber . '|' . hash('sha256', $image)
);

$before = [
    1 => $headerPage('next168 stale header before master journal', 40, 50, 40),
    2 => $page('next168 stale wp_options root before master journal'),
    3 => $page('next168 stale active_plugins before master journal'),
    4 => $page('next168 stale plugin settings before master journal'),
    5 => $page('next168 unchanged comments before master journal'),
    6 => $page('next168 stale transient timeout before master journal'),
    7 => $page('next168 stale cron before master journal'),
];
$recovered = [
    1 => $headerPage('next168 recovered header after master journal', 41, 51, 41),
    2 => $page('next168 recovered wp_options root after master journal'),
    3 => $page('next168 recovered active_plugins after master journal'),
    4 => $page('next168 recovered plugin settings after master journal'),
    6 => $page('next168 recovered transient timeout after master journal'),
    7 => $page('next168 recovered cron after master journal'),
];
$header = ['change_counter' => 41, 'schema_cookie' => 51, 'version_valid_for' => 41];
$cacheHeader = ['change_counter' => 41, 'schema_cookie' => 51, 'version_valid_for' => 41];
$entry = static fn (string $label, string $image, int $pageNumber, array $meta = [], array $extra = []): array => array_merge([
    'label' => $label,
    'image' => $image,
    'source_id' => $sourceId,
    'epoch' => 13,
    'master_journal_digest' => $masterDigest,
    'page_source_digest' => $digest($pageNumber, $image),
    'source_generation' => $generation,
], $meta, $extra);
$readerCache = static fn (): array => [
    1 => $entry('header-retained-current-source', $recovered[1], 1, $cacheHeader),
    2 => $entry('root-refreshable-current-source', $before[2], 2, $cacheHeader, ['page_source_digest' => $digest(2, $recovered[2])]),
    3 => $entry('active-plugins-stale-page-source', $recovered[3], 3, $cacheHeader, ['page_source_digest' => $digest(3, $before[3])]),
    4 => $entry('settings-stale-generation', $recovered[4], 4, $cacheHeader, ['source_generation' => 13]),
    5 => $entry('comments-stale-header', $before[5], 5, ['change_counter' => 40, 'schema_cookie' => 51, 'version_valid_for' => 41]),
    6 => $entry('transient-dirty', $recovered[6], 6, $cacheHeader, ['dirty' => true]),
    7 => $entry('cron-pinned-stale-image', $before[7], 7, $cacheHeader, ['pinned' => true]),
];
$nextWrites = [
    3 => $page('next168 rewritten active_plugins after source fence'),
    4 => $page('next168 rewritten plugin settings after source fence'),
];

$plan = static fn (
    ?array $pages = null,
    ?array $cache = null,
    ?array $reads = null,
    ?array $writes = null,
    ?string $currentDigest = null,
    ?int $currentGeneration = null,
    ?string $master = null,
    ?string $bytes = null,
    ?int $size = null,
    ?string $path = null,
    ?string $masterJournalPath = null,
    ?string $source = null,
    int $epoch = 13,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::planReaderCacheSourceDigestFence(
    $path ?? $databasePath,
    $masterJournalPath ?? $masterPath,
    $master ?? $masterBytes,
    $bytes ?? implode('', $before),
    $size ?? $pageSize,
    $pages ?? $recovered,
    $cache ?? $readerCache(),
    $reads ?? [1, 2, 3, 4, 5, 6, 7],
    $writes ?? $nextWrites,
    $source ?? $sourceId,
    $epoch,
    $currentDigest ?? $sourceDigest,
    $currentGeneration ?? $generation,
);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next168'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'master_journal_reader_cache_requires_current_source_digest_generation_fence'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $databasePath],
    'master path' => [static fn (): mixed => $plan()['master_journal_path'], $masterPath],
    'page size' => [static fn (): mixed => $plan()['page_size'], $pageSize],
    'members' => [static fn (): mixed => $plan()['current_members'], [$databasePath . '-journal', '/srv/wp-content/database/wp-next168-network.sqlite-journal']],
    'header' => [static fn (): mixed => $plan()['current_header'], $header],
    'current digest' => [static fn (): mixed => $plan()['current_source_digest'], $sourceDigest],
    'current generation' => [static fn (): mixed => $plan()['current_source_generation'], $generation],
    'input source id' => [static fn (): mixed => $plan()['input_source']['id'], $sourceId],
    'input epoch' => [static fn (): mixed => $plan()['input_source']['epoch'], 13],
    'recovered epoch' => [static fn (): mixed => $plan()['recovered_source']['epoch'], 14],
    'recovered source prefix' => [static fn (): mixed => str_starts_with($plan()['recovered_source']['id'], 'master-reader-header:'), true],
    'recovered pages' => [static fn (): mixed => $plan()['recovered_page_numbers'], [1, 2, 3, 4, 6, 7]],
    'reader row count' => [static fn (): mixed => count($plan()['reader_rows']), 7],
    'retained pages' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1]],
    'refreshed pages' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], [2]],
    'header plus source invalidated pages' => [static fn (): mixed => $plan()['invalidated_cache_page_numbers'], [3, 4, 5, 6, 7]],
    'source invalidated pages' => [static fn (): mixed => $plan()['source_invalidated_cache_page_numbers'], [3, 4]],
    'requires reader reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'retained header reason' => [static fn (): mixed => $plan()['reader_rows'][0]['reason'], 'reader_cache_matches_current_header_source'],
    'retained source reason' => [static fn (): mixed => $plan()['reader_rows'][0]['source_reason'], 'reader_cache_source_digest_matches_current_source'],
    'refresh source reason' => [static fn (): mixed => $plan()['reader_rows'][1]['source_reason'], 'reader_cache_source_digest_matches_current_source'],
    'stale page digest reason' => [static fn (): mixed => $plan()['reader_rows'][2]['source_reason'], 'reader_cache_page_source_digest_predates_current_source'],
    'stale generation reason' => [static fn (): mixed => $plan()['reader_rows'][3]['source_reason'], 'reader_cache_source_generation_predates_current_source'],
    'stale header carries header reason' => [static fn (): mixed => $plan()['reader_rows'][4]['source_reason'], 'reader_cache_change_counter_predates_current_header'],
    'dirty carries dirty reason' => [static fn (): mixed => $plan()['reader_rows'][5]['source_reason'], 'dirty_reader_cache_from_aborted_master_recovery'],
    'pinned carries pinned reason' => [static fn (): mixed => $plan()['reader_rows'][6]['source_reason'], 'pinned_reader_cache_image_predates_current_header'],
    'row source admitted retained' => [static fn (): mixed => $plan()['reader_rows'][0]['source_admitted'], true],
    'row source admitted refreshed' => [static fn (): mixed => $plan()['reader_rows'][1]['source_admitted'], true],
    'row source rejected digest' => [static fn (): mixed => $plan()['reader_rows'][2]['source_admitted'], false],
    'row source rejected generation' => [static fn (): mixed => $plan()['reader_rows'][3]['source_admitted'], false],
    'row cache digest differs' => [static fn (): mixed => $plan()['reader_rows'][2]['cache_page_source_digest'] !== $plan()['reader_rows'][2]['current_page_source_digest'], true],
    'row generation differs' => [static fn (): mixed => $plan()['reader_rows'][3]['cache_source_generation'], 13],
    'row current generation' => [static fn (): mixed => $plan()['reader_rows'][3]['current_source_generation'], $generation],
    'next read count' => [static fn (): mixed => count($plan()['next_reads']), 7],
    'read retained hit' => [static fn (): mixed => $plan()['next_reads'][0]['cache_hit'], true],
    'read refreshed hit' => [static fn (): mixed => $plan()['next_reads'][1]['cache_hit'], true],
    'read digest invalidated miss' => [static fn (): mixed => $plan()['next_reads'][2]['cache_hit'], false],
    'read generation invalidated miss' => [static fn (): mixed => $plan()['next_reads'][3]['cache_hit'], false],
    'read digest invalidated source' => [static fn (): mixed => $plan()['next_reads'][2]['source'], 'master-journal-reader-cache-source-fence-current-source'],
    'read generation invalidated reason' => [static fn (): mixed => $plan()['next_reads'][3]['source_fence_reason'], 'source_digest_or_generation_reopened_reader_cache'],
    'read current prefix' => [static fn (): mixed => $plan()['next_reads'][2]['prefix'], 'next168 recovered active_plugins after master journal'],
    'read unchanged prefix' => [static fn (): mixed => $plan()['next_reads'][4]['prefix'], 'next168 unchanged comments before master journal'],
    'read header counter' => [static fn (): mixed => $plan()['next_reads'][0]['header_change_counter'], 41],
    'read header schema' => [static fn (): mixed => $plan()['next_reads'][0]['header_schema_cookie'], 51],
    'write count' => [static fn (): mixed => count($plan()['next_writes']), 2],
    'write active before' => [static fn (): mixed => $plan()['next_writes'][0]['before_prefix'], 'next168 recovered active_plugins after master journal'],
    'write active after' => [static fn (): mixed => $plan()['next_writes'][0]['after_prefix'], 'next168 rewritten active_plugins after source fence'],
    'write settings before' => [static fn (): mixed => $plan()['next_writes'][1]['before_prefix'], 'next168 recovered plugin settings after master journal'],
    'write journal flag' => [static fn (): mixed => $plan()['next_writes'][1]['journal_before_from_current_header_source'], true],
    'operation read master' => [static fn (): mixed => $plan()['operations'][0]['op'], 'read_current_master_journal_for_header_reader_cache'],
    'operation source invalidates digest' => [static fn (): mixed => $plan()['operations'][count($plan()['operations']) - 2]['op'], 'invalidate_reader_cache_after_master_current_source_digest'],
    'operation source invalidates generation' => [static fn (): mixed => $plan()['operations'][count($plan()['operations']) - 1]['reason'], 'reader_cache_source_generation_predates_current_source'],
    'final page one source' => [static fn (): mixed => $plan()['final_sources'][1], 'master-journal-header-current-source'],
    'final page three source' => [static fn (): mixed => $plan()['final_sources'][3], 'next-write-after-master-header-reader-cache'],
    'final page four prefix' => [static fn (): mixed => $plan()['final_prefixes'][4], 'next168 rewritten plugin settings after source fence'],
    'final bytes contain active rewrite' => [static fn (): mixed => str_contains($plan()['final_database_bytes'], 'rewritten active_plugins'), true],
    'final bytes contain settings rewrite' => [static fn (): mixed => str_contains($plan()['final_database_bytes'], 'rewritten plugin settings'), true],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next168', $plan()['dependencies'], true), true],
    'dependency source fence' => [static fn (): mixed => in_array('sqlite-pager-reader-cache-current-source-digest-fence', $plan()['dependencies'], true), true],
    'base dependency retained' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next164', $plan()['dependencies'], true), true],
    'all cache valid no source invalidation' => [static fn (): mixed => $plan(null, [1 => $entry('header-retained-current-source', $recovered[1], 1, $cacheHeader)], [1], [])['source_invalidated_cache_page_numbers'], []],
    'all cache valid no reopen' => [static fn (): mixed => $plan(null, [1 => $entry('header-retained-current-source', $recovered[1], 1, $cacheHeader)], [1], [])['requires_reader_reopen'], false],
    'duplicate members collapsed' => [static fn (): mixed => $plan(null, null, [1], [], null, null, $masterBytes . $databasePath . "-journal\n")['current_members'], [$databasePath . '-journal', '/srv/wp-content/database/wp-next168-network.sqlite-journal']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next168 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty current source digest rejected' => static fn () => $plan(null, null, null, null, ''),
    'bad current source generation rejected' => static fn () => $plan(null, null, null, null, null, 0),
    'missing page source digest rejected' => static fn () => $plan(null, [1 => $entry('bad', $recovered[1], 1, $cacheHeader, ['page_source_digest' => ''])], [1], []),
    'bad source generation rejected' => static fn () => $plan(null, [1 => $entry('bad', $recovered[1], 1, $cacheHeader, ['source_generation' => 0])], [1], []),
    'bad cache page rejected by next168' => static fn () => $plan(null, [0 => $entry('bad', $recovered[1], 1, $cacheHeader)], [1], []),
    'base empty database path rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, null, ''),
    'base wrong master bytes rejected' => static fn () => $plan(null, null, null, null, null, null, '/tmp/other.sqlite-journal'),
    'base bad page size rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, 500),
    'base read outside rejected' => static fn () => $plan(null, null, [8], []),
    'base write outside rejected' => static fn () => $plan(null, null, [], [8 => $page('outside')]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next168 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
