<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 128;
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);
$database = '/srv/wp-content/database/wp-next166.sqlite';
$master = '/srv/wp-content/database/wp-next166.sqlite-mj';
$masterBytes = $database . "-journal\n/srv/wp-content/database/wp-next166-comments.sqlite-journal\n";
$masterDigest = hash('sha256', $masterBytes);
$currentSource = 'pager-reader-current-next166';
$nextSource = 'pager-reader-next-next166';
$currentEpoch = 166;
$nextEpoch = 167;
$currentGeneration = 40;
$nextGeneration = 41;
$currentSchema = 3201;
$nextSchema = 3202;

$currentPages = [
    1 => $page('next166 current schema page'),
    2 => $page('next166 current active_plugins page'),
    3 => $page('next166 current option autoload page'),
    4 => $page('next166 current plugin setting page'),
    5 => $page('next166 current transient page'),
    6 => $page('next166 current comments page'),
    7 => $page('next166 current removed overflow page'),
];
$nextPages = [
    1 => $currentPages[1],
    2 => $page('next166 next active_plugins page'),
    3 => $currentPages[3],
    4 => $currentPages[4],
    5 => $page('next166 next transient page'),
    6 => $currentPages[6],
];
$cache = [
    1 => ['image' => $currentPages[1], 'source' => 'schema-cache', 'source_id' => $currentSource, 'epoch' => 166, 'generation' => 40, 'schema_cookie' => 3201, 'page_count' => 7, 'master_digest' => $masterDigest],
    2 => ['image' => $currentPages[2], 'source' => 'changed-active-cache', 'source_id' => $currentSource, 'epoch' => 166, 'generation' => 40, 'schema_cookie' => 3201, 'page_count' => 7, 'master_digest' => $masterDigest],
    3 => ['image' => $currentPages[3], 'source' => 'pinned-autoload-cache', 'source_id' => $currentSource, 'epoch' => 166, 'generation' => 40, 'schema_cookie' => 3201, 'page_count' => 7, 'pinned' => true, 'master_digest' => $masterDigest],
    4 => ['image' => $currentPages[4], 'source' => 'dirty-plugin-cache', 'source_id' => $currentSource, 'epoch' => 166, 'generation' => 40, 'schema_cookie' => 3201, 'page_count' => 7, 'dirty' => true, 'master_digest' => $masterDigest],
    5 => ['image' => $currentPages[5], 'source' => 'wrong-generation-cache', 'source_id' => $currentSource, 'epoch' => 166, 'generation' => 39, 'schema_cookie' => 3201, 'page_count' => 7, 'master_digest' => $masterDigest],
    6 => ['image' => $page('next166 stale comments cache'), 'source' => 'stale-comments-cache', 'source_id' => $currentSource, 'epoch' => 166, 'generation' => 40, 'schema_cookie' => 3201, 'page_count' => 7, 'master_digest' => $masterDigest],
    7 => ['image' => $currentPages[7], 'source' => 'removed-overflow-cache', 'source_id' => $currentSource, 'epoch' => 166, 'generation' => 40, 'schema_cookie' => 3201, 'page_count' => 7, 'master_digest' => $masterDigest],
];
$reads = [
    ['reader_id' => 'schema-reader', 'page_number' => 1, 'source_id' => $nextSource, 'epoch' => 167, 'generation' => 41, 'schema_cookie' => 3202, 'page_count' => 6],
    ['reader_id' => 'active-reader', 'page_number' => 2, 'source_id' => $nextSource, 'epoch' => 167, 'generation' => 41, 'schema_cookie' => 3202, 'page_count' => 6],
    ['reader_id' => 'autoload-reader', 'page_number' => 3, 'source_id' => $nextSource, 'epoch' => 167, 'generation' => 41, 'schema_cookie' => 3202, 'page_count' => 6],
    ['reader_id' => 'plugin-reader', 'page_number' => 4, 'source_id' => $nextSource, 'epoch' => 167, 'generation' => 41, 'schema_cookie' => 3202, 'page_count' => 6],
    ['reader_id' => 'transient-reader', 'page_number' => 5, 'source_id' => $nextSource, 'epoch' => 167, 'generation' => 41, 'schema_cookie' => 3202, 'page_count' => 6],
    ['reader_id' => 'comments-reader', 'page_number' => 6, 'source_id' => $nextSource, 'epoch' => 167, 'generation' => 41, 'schema_cookie' => 3202, 'page_count' => 6],
];

$plan = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $readerCache = null,
    ?array $nextReads = null,
    ?string $masterBytesArg = null,
    ?int $size = null,
    ?string $db = null,
    ?string $mj = null,
    ?string $currentSourceArg = null,
    ?string $nextSourceArg = null,
    ?int $currentEpochArg = null,
    ?int $nextEpochArg = null,
    ?int $currentGenerationArg = null,
    ?int $nextGenerationArg = null,
    ?int $currentSchemaArg = null,
    ?int $nextSchemaArg = null,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::planReaderCacheNextSourceFence(
    $db ?? $database,
    $mj ?? $master,
    $masterBytesArg ?? $masterBytes,
    $size ?? $pageSize,
    $current ?? $currentPages,
    $next ?? $nextPages,
    $readerCache ?? $cache,
    $nextReads ?? $reads,
    $currentSourceArg ?? $currentSource,
    $nextSourceArg ?? $nextSource,
    $currentEpochArg ?? $currentEpoch,
    $nextEpochArg ?? $nextEpoch,
    $currentGenerationArg ?? $currentGeneration,
    $nextGenerationArg ?? $nextGeneration,
    $currentSchemaArg ?? $currentSchema,
    $nextSchemaArg ?? $nextSchema,
);

$freshCache = [
    1 => ['image' => $currentPages[1], 'source_id' => $currentSource, 'epoch' => 166, 'generation' => 40, 'schema_cookie' => 3201, 'page_count' => 7, 'master_digest' => $masterDigest],
];

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next166'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'next reader cache is reused only after master-journal recovery when generation schema and page-count fences still match'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $database],
    'master path' => [static fn (): mixed => $plan()['master_journal_path'], $master],
    'page size' => [static fn (): mixed => $plan()['page_size'], 128],
    'master members' => [static fn (): mixed => $plan()['master_members'], [$database . '-journal', '/srv/wp-content/database/wp-next166-comments.sqlite-journal']],
    'master digest' => [static fn (): mixed => $plan()['master_digest'], $masterDigest],
    'current source id' => [static fn (): mixed => $plan()['current_source']['id'], $currentSource],
    'next source id' => [static fn (): mixed => $plan()['next_source']['id'], $nextSource],
    'current epoch' => [static fn (): mixed => $plan()['current_source']['epoch'], 166],
    'next epoch' => [static fn (): mixed => $plan()['next_source']['epoch'], 167],
    'current generation' => [static fn (): mixed => $plan()['current_source']['generation'], 40],
    'next generation' => [static fn (): mixed => $plan()['next_source']['generation'], 41],
    'current schema' => [static fn (): mixed => $plan()['current_source']['schema_cookie'], 3201],
    'next schema' => [static fn (): mixed => $plan()['next_source']['schema_cookie'], 3202],
    'current page count' => [static fn (): mixed => $plan()['current_source']['page_count'], 7],
    'next page count' => [static fn (): mixed => $plan()['next_source']['page_count'], 6],
    'schema changed' => [static fn (): mixed => $plan()['schema_changed'], true],
    'page count truncated' => [static fn (): mixed => $plan()['page_count_truncated'], true],
    'cache row count' => [static fn (): mixed => count($plan()['cache_rows']), 7],
    'reusable pages' => [static fn (): mixed => $plan()['reusable_page_numbers'], [1, 3]],
    'invalidated pages' => [static fn (): mixed => $plan()['invalidated_page_numbers'], [2, 4, 5, 6, 7]],
    'changed page reason' => [static fn (): mixed => $plan()['invalidated_reasons'][2], 'reader_cache_page_changed_in_next_source'],
    'dirty reason' => [static fn (): mixed => $plan()['invalidated_reasons'][4], 'dirty_reader_cache_page_cannot_seed_next_source'],
    'generation reason' => [static fn (): mixed => $plan()['invalidated_reasons'][5], 'reader_cache_generation_is_not_current'],
    'stale image reason' => [static fn (): mixed => $plan()['invalidated_reasons'][6], 'reader_cache_image_is_not_current_source'],
    'truncated page reason' => [static fn (): mixed => $plan()['invalidated_reasons'][7], 'reader_cache_page_truncated_from_next_source'],
    'row one reusable' => [static fn (): mixed => $plan()['cache_rows'][0]['reason'], 'reader_cache_page_reusable_for_next_source'],
    'row two next digest differs' => [static fn (): mixed => $plan()['cache_rows'][1]['current_digest'] !== $plan()['cache_rows'][1]['next_digest'], true],
    'row three pinned reusable' => [static fn (): mixed => $plan()['cache_rows'][2]['reason'], 'reader_cache_page_reusable_for_next_source'],
    'row four dirty flag' => [static fn (): mixed => $plan()['cache_rows'][3]['dirty'], true],
    'row seven next digest null' => [static fn (): mixed => $plan()['cache_rows'][6]['next_digest'], null],
    'read count' => [static fn (): mixed => count($plan()['reads']), 6],
    'read cache hits' => [static fn (): mixed => $plan()['read_cache_hits'], [
        'schema-reader' => true,
        'active-reader' => false,
        'autoload-reader' => true,
        'plugin-reader' => false,
        'transient-reader' => false,
        'comments-reader' => false,
    ]],
    'schema read prefix' => [static fn (): mixed => $plan()['read_prefixes']['schema-reader'], 'next166 current schema page'],
    'active read prefix' => [static fn (): mixed => $plan()['read_prefixes']['active-reader'], 'next166 next active_plugins page'],
    'comments read prefix' => [static fn (): mixed => $plan()['read_prefixes']['comments-reader'], 'next166 current comments page'],
    'schema read source cache' => [static fn (): mixed => $plan()['reads'][0]['source'], 'reader-cache-current-source-next166'],
    'active read source next' => [static fn (): mixed => $plan()['reads'][1]['source'], 'next-master-journal-current-source'],
    'read ticket current' => [static fn (): mixed => $plan()['reads'][0]['ticket_current'], true],
    'read next source id' => [static fn (): mixed => $plan()['reads'][0]['source_id'], $nextSource],
    'read next generation' => [static fn (): mixed => $plan()['reads'][0]['generation'], 41],
    'read next schema' => [static fn (): mixed => $plan()['reads'][0]['schema_cookie'], 3202],
    'read page count' => [static fn (): mixed => $plan()['reads'][0]['page_count'], 6],
    'reopen readers' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['active-reader', 'plugin-reader', 'transient-reader', 'comments-reader']],
    'first operation' => [static fn (): mixed => $plan()['operations'][0]['op'], 'read_current_master_journal_before_reader_cache_next166'],
    'first retain operation' => [static fn (): mixed => $plan()['operations'][1]['op'], 'retain_reader_cache_page_for_next166'],
    'first invalidate operation' => [static fn (): mixed => $plan()['operations'][2]['op'], 'invalidate_reader_cache_page_for_next166'],
    'last operation' => [static fn (): mixed => $plan()['operations'][13]['op'], 'reader_cache_next166_reopen'],
    'operation count' => [static fn (): mixed => count($plan()['operations']), 14],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next166', $plan()['dependencies'], true), true],
    'dependency generation' => [static fn (): mixed => in_array('sqlite-master-journal-reader-cache-generation-fence', $plan()['dependencies'], true), true],
    'dependency schema pagecount' => [static fn (): mixed => in_array('sqlite-master-journal-reader-cache-schema-pagecount-fence', $plan()['dependencies'], true), true],
    'dependency prior source' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next163', $plan()['dependencies'], true), true],
    'unchanged schema flag false' => [static fn (): mixed => $plan(null, null, $freshCache, [['reader_id' => 'schema-reader', 'page_number' => 1]], null, null, null, null, null, null, null, null, null, null, 3201, 3201)['schema_changed'], false],
    'unchanged page count flag false' => [static fn (): mixed => $plan([1 => $currentPages[1]], [1 => $currentPages[1]], $freshCache, [['reader_id' => 'schema-reader', 'page_number' => 1]])['page_count_truncated'], false],
    'stale read source forces reopen' => [static fn (): mixed => $plan(null, null, $freshCache, [['reader_id' => 'schema-reader', 'page_number' => 1, 'source_id' => $currentSource, 'epoch' => 167, 'generation' => 41, 'schema_cookie' => 3202, 'page_count' => 6]])['reads'][0]['ticket_current'], false],
    'stale read generation forces reopen id' => [static fn (): mixed => $plan(null, null, $freshCache, [['reader_id' => 'schema-reader', 'page_number' => 1, 'source_id' => $nextSource, 'epoch' => 167, 'generation' => 40, 'schema_cookie' => 3202, 'page_count' => 6]])['reopen_reader_ids'], ['schema-reader']],
    'stale read schema forces reopen id' => [static fn (): mixed => $plan(null, null, $freshCache, [['reader_id' => 'schema-reader', 'page_number' => 1, 'source_id' => $nextSource, 'epoch' => 167, 'generation' => 41, 'schema_cookie' => 3201, 'page_count' => 6]])['reopen_reader_ids'], ['schema-reader']],
    'stale read page count forces reopen id' => [static fn (): mixed => $plan(null, null, $freshCache, [['reader_id' => 'schema-reader', 'page_number' => 1, 'source_id' => $nextSource, 'epoch' => 167, 'generation' => 41, 'schema_cookie' => 3202, 'page_count' => 7]])['reopen_reader_ids'], ['schema-reader']],
    'source token invalidation reason' => [static fn (): mixed => $plan(null, null, [1 => array_replace($freshCache[1], ['source_id' => 'old'])], [['reader_id' => 'schema-reader', 'page_number' => 1]])['invalidated_reasons'][1], 'reader_cache_source_token_is_not_current'],
    'schema cookie invalidation reason' => [static fn (): mixed => $plan(null, null, [1 => array_replace($freshCache[1], ['schema_cookie' => 3200])], [['reader_id' => 'schema-reader', 'page_number' => 1]])['invalidated_reasons'][1], 'reader_cache_schema_cookie_is_not_current'],
    'page count invalidation reason' => [static fn (): mixed => $plan(null, null, [1 => array_replace($freshCache[1], ['page_count' => 6])], [['reader_id' => 'schema-reader', 'page_number' => 1]])['invalidated_reasons'][1], 'reader_cache_page_count_is_not_current'],
    'master digest invalidation reason' => [static fn (): mixed => $plan(null, null, [1 => array_replace($freshCache[1], ['master_digest' => str_repeat('0', 64)])], [['reader_id' => 'schema-reader', 'page_number' => 1]])['invalidated_reasons'][1], 'reader_cache_master_digest_is_not_current'],
    'pinned changed reason wins' => [static fn (): mixed => $plan(null, null, [2 => array_replace($cache[2], ['pinned' => true])], [['reader_id' => 'active-reader', 'page_number' => 2]])['invalidated_reasons'][2], 'pinned_reader_cache_changed_or_truncated_next_source'],
    'default read ticket current' => [static fn (): mixed => $plan(null, null, $freshCache, [['reader_id' => 'schema-reader', 'page_number' => 1]])['reads'][0]['ticket_current'], true],
    'default source label' => [static fn (): mixed => $plan(null, null, [1 => ['image' => $currentPages[1], 'source_id' => $currentSource, 'epoch' => 166, 'generation' => 40, 'schema_cookie' => 3201, 'page_count' => 7]], [['reader_id' => 'schema-reader', 'page_number' => 1]])['cache_rows'][0]['source'], 'master-journal-reader-cache-next166'],
    'duplicate master members collapse' => [static fn (): mixed => $plan(null, null, $freshCache, [['reader_id' => 'schema-reader', 'page_number' => 1]], $masterBytes . $database . "-journal\n")['master_members'], [$database . '-journal', '/srv/wp-content/database/wp-next166-comments.sqlite-journal']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next166 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty database rejected' => static fn () => $plan(null, null, null, null, null, null, ''),
    'empty master path rejected' => static fn () => $plan(null, null, null, null, null, null, null, ''),
    'empty current source rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, ''),
    'empty next source rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, null, ''),
    'same source rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, $currentSource, $currentSource),
    'blank master bytes rejected' => static fn () => $plan(null, null, null, null, " \n"),
    'bad page size rejected' => static fn () => $plan(null, null, null, null, null, 0),
    'bad current epoch rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, null, null, 0),
    'non increasing epoch rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, null, null, 166, 166),
    'bad current generation rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, null, null, null, null, 0),
    'non increasing generation rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, null, null, null, null, 40, 40),
    'bad current schema rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, null, null, null, null, null, null, -1),
    'bad next schema rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, null, null, null, null, null, null, null, -1),
    'empty current pages rejected' => static fn () => $plan([]),
    'empty next pages rejected' => static fn () => $plan(null, []),
    'empty cache rejected' => static fn () => $plan(null, null, []),
    'empty reads rejected' => static fn () => $plan(null, null, null, []),
    'unreferenced database journal rejected' => static fn () => $plan(null, null, null, null, '/tmp/other.sqlite-journal'),
    'zero current page rejected' => static fn () => $plan([0 => $currentPages[1]]),
    'short current page rejected' => static fn () => $plan([1 => 'short']),
    'zero next page rejected' => static fn () => $plan(null, [0 => $nextPages[1]]),
    'short next page rejected' => static fn () => $plan(null, [1 => 'short']),
    'zero cache page rejected' => static fn () => $plan(null, null, [0 => $cache[1]]),
    'short cache image rejected' => static fn () => $plan(null, null, [1 => ['image' => 'short']]),
    'negative cache epoch rejected' => static fn () => $plan(null, null, [1 => array_replace($freshCache[1], ['epoch' => -1])]),
    'negative cache generation rejected' => static fn () => $plan(null, null, [1 => array_replace($freshCache[1], ['generation' => -1])]),
    'negative cache schema rejected' => static fn () => $plan(null, null, [1 => array_replace($freshCache[1], ['schema_cookie' => -1])]),
    'negative cache page count rejected' => static fn () => $plan(null, null, [1 => array_replace($freshCache[1], ['page_count' => -1])]),
    'empty reader id rejected' => static fn () => $plan(null, null, null, [['reader_id' => '', 'page_number' => 1]]),
    'zero read page rejected' => static fn () => $plan(null, null, null, [['reader_id' => 'bad', 'page_number' => 0]]),
    'negative read epoch rejected' => static fn () => $plan(null, null, null, [['reader_id' => 'bad', 'page_number' => 1, 'epoch' => -1]]),
    'negative read generation rejected' => static fn () => $plan(null, null, null, [['reader_id' => 'bad', 'page_number' => 1, 'generation' => -1]]),
    'negative read schema rejected' => static fn () => $plan(null, null, null, [['reader_id' => 'bad', 'page_number' => 1, 'schema_cookie' => -1]]),
    'negative read page count rejected' => static fn () => $plan(null, null, null, [['reader_id' => 'bad', 'page_number' => 1, 'page_count' => -1]]),
    'read outside next source rejected' => static fn () => $plan(null, null, null, [['reader_id' => 'removed-reader', 'page_number' => 7]]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next166 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
