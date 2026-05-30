<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$database = '/srv/wp-content/database/wp-next176.sqlite';
$master = '/srv/wp-content/database/wp-next176.sqlite-mj';
$currentSource = 'wp-next176-current-master-source';
$nextSource = 'wp-next176-next-master-source';
$currentMembers = [
    $database . '-journal',
    '/srv/wp-content/database/wp-next176-old-plugin.sqlite-journal',
];
$nextMembers = [
    $database . '-journal',
    '/srv/wp-content/database/wp-next176-current-plugin.sqlite-journal',
    '/srv/wp-content/database/wp-next176-users.sqlite-journal',
];
$currentMaster = implode("\n", $currentMembers) . "\n";
$nextMaster = implode("\n", $nextMembers) . "\n";
$currentDigest = hash('sha256', implode("\n", $currentMembers));
$nextDigest = hash('sha256', implode("\n", $nextMembers));
$page = static fn (string $label): string => str_pad($label, $pageSize, '.', STR_PAD_RIGHT);

$currentPages = [
    1 => $page('next176 current schema page from first master source'),
    2 => $page('next176 current active plugins from first master source'),
    3 => $page('next176 current roles page from first master source'),
    4 => $page('next176 current cron page from first master source'),
    5 => $page('next176 current transient page from first master source'),
    6 => $page('next176 current rewrite rules from first master source'),
    7 => $page('next176 current cache page from first master source'),
    8 => $page('next176 current options autoload page from first master source'),
];
$nextPages = [
    1 => $currentPages[1],
    2 => $currentPages[2],
    3 => $page('next176 next roles page after master source rollover'),
    4 => $currentPages[4],
    5 => $currentPages[5],
    6 => $page('next176 next rewrite rules after master source rollover'),
    7 => $currentPages[7],
    8 => $page('next176 next options autoload page after master source rollover'),
];
$cache = [
    1 => ['image' => $currentPages[1], 'source_id' => $currentSource, 'epoch' => 176, 'reader_id' => 'schema-reader', 'master_digest' => $currentDigest, 'master_members' => $currentMembers, 'shared' => true],
    2 => ['image' => $currentPages[2], 'source_id' => $nextSource, 'epoch' => 177, 'reader_id' => 'preopened-next-reader', 'master_digest' => $nextDigest, 'master_members' => $nextMembers],
    3 => ['image' => $currentPages[3], 'source_id' => $currentSource, 'epoch' => 176, 'reader_id' => 'roles-reader', 'master_digest' => $currentDigest, 'master_members' => $currentMembers, 'pinned' => true],
    4 => ['image' => $currentPages[4], 'source_id' => $currentSource, 'epoch' => 176, 'reader_id' => 'dirty-reader', 'master_digest' => $currentDigest, 'master_members' => $currentMembers, 'dirty' => true],
    5 => ['image' => $currentPages[5], 'source_id' => $currentSource, 'epoch' => 175, 'reader_id' => 'old-epoch-reader', 'master_digest' => $currentDigest, 'master_members' => $currentMembers],
    6 => ['image' => $page('next176 stale rewrite rules cache image'), 'source_id' => $currentSource, 'epoch' => 176, 'reader_id' => 'stale-image-reader', 'master_digest' => $currentDigest, 'master_members' => $currentMembers],
    7 => ['image' => $currentPages[7], 'source_id' => $currentSource, 'epoch' => 176, 'reader_id' => 'stale-members-reader', 'master_digest' => $currentDigest, 'master_members' => [$database . '-journal']],
    8 => ['image' => $nextPages[8], 'source_id' => $nextSource, 'epoch' => 177, 'reader_id' => 'next-autoload-reader', 'master_digest' => $nextDigest, 'master_members' => $nextMembers],
];
$reads = [
    ['reader_id' => 'schema-current', 'page_number' => 1, 'source_id' => $currentSource, 'epoch' => 176, 'master_digest' => $currentDigest, 'phase' => 'current'],
    ['reader_id' => 'schema-next', 'page_number' => 1, 'source_id' => $nextSource, 'epoch' => 177, 'master_digest' => $nextDigest, 'phase' => 'next'],
    ['reader_id' => 'preopened-next', 'page_number' => 2, 'source_id' => $nextSource, 'epoch' => 177, 'master_digest' => $nextDigest, 'phase' => 'next'],
    ['reader_id' => 'roles-next', 'page_number' => 3, 'source_id' => $nextSource, 'epoch' => 177, 'master_digest' => $nextDigest, 'phase' => 'next'],
    ['reader_id' => 'dirty-current', 'page_number' => 4, 'source_id' => $currentSource, 'epoch' => 176, 'master_digest' => $currentDigest, 'phase' => 'current'],
    ['reader_id' => 'old-epoch-current', 'page_number' => 5, 'source_id' => $currentSource, 'epoch' => 176, 'master_digest' => $currentDigest, 'phase' => 'current'],
    ['reader_id' => 'stale-image-current', 'page_number' => 6, 'source_id' => $currentSource, 'epoch' => 176, 'master_digest' => $currentDigest, 'phase' => 'current'],
    ['reader_id' => 'stale-members-current', 'page_number' => 7, 'source_id' => $currentSource, 'epoch' => 176, 'master_digest' => $currentDigest, 'phase' => 'current'],
    ['reader_id' => 'autoload-next', 'page_number' => 8, 'source_id' => $nextSource, 'epoch' => 177, 'master_digest' => $nextDigest, 'phase' => 'next'],
];

$plan = static fn (
    ?array $current = null,
    ?array $next = null,
    ?array $readerCache = null,
    ?array $nextReads = null,
    ?string $currentMasterArg = null,
    ?string $nextMasterArg = null,
    ?int $size = null,
    ?string $db = null,
    ?string $mj = null,
    ?string $currentId = null,
    int $currentEpoch = 176,
    ?string $nextId = null,
    int $nextEpoch = 177,
): array => SQLitePagerMasterJournalReaderCacheCurrentSourceNextPlan::variantMasterJournalSourceRolloverFence(
    $db ?? $database,
    $mj ?? $master,
    $currentMasterArg ?? $currentMaster,
    $nextMasterArg ?? $nextMaster,
    $size ?? $pageSize,
    $current ?? $currentPages,
    $next ?? $nextPages,
    $readerCache ?? $cache,
    $nextReads ?? $reads,
    $currentId ?? $currentSource,
    $currentEpoch,
    $nextId ?? $nextSource,
    $nextEpoch,
);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager-master-journal-reader-cache-current-source-next176'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'next master-journal source rollover fences reader-cache reuse even when page images still match'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $database],
    'master path' => [static fn (): mixed => $plan()['master_journal_path'], $master],
    'page size' => [static fn (): mixed => $plan()['page_size'], 512],
    'current source id' => [static fn (): mixed => $plan()['current_source']['id'], $currentSource],
    'current source epoch' => [static fn (): mixed => $plan()['current_source']['epoch'], 176],
    'current members' => [static fn (): mixed => $plan()['current_source']['members'], $currentMembers],
    'current digest' => [static fn (): mixed => $plan()['current_source']['master_digest'], $currentDigest],
    'next source id' => [static fn (): mixed => $plan()['next_source']['id'], $nextSource],
    'next source epoch' => [static fn (): mixed => $plan()['next_source']['epoch'], 177],
    'next members' => [static fn (): mixed => $plan()['next_source']['members'], $nextMembers],
    'next digest' => [static fn (): mixed => $plan()['next_source']['master_digest'], $nextDigest],
    'cache row count' => [static fn (): mixed => count($plan()['cache_rows']), 8],
    'current retained pages' => [static fn (): mixed => $plan()['current_retained_page_numbers'], [1, 3, 6]],
    'current invalid old epoch' => [static fn (): mixed => $plan()['current_invalidated_reasons'][5], 'reader_cache_current_epoch_mismatch'],
    'current invalid dirty' => [static fn (): mixed => $plan()['current_invalidated_reasons'][4], 'dirty_reader_cache_cannot_survive_current_source'],
    'current invalid next source' => [static fn (): mixed => $plan()['current_invalidated_reasons'][2], 'reader_cache_current_source_id_mismatch'],
    'current invalid stale members' => [static fn (): mixed => $plan()['current_invalidated_reasons'][7], 'reader_cache_current_master_membership_mismatch'],
    'next reusable pages' => [static fn (): mixed => $plan()['next_reusable_page_numbers'], [2, 8]],
    'next invalid current page one' => [static fn (): mixed => $plan()['next_invalidated_reasons'][1], 'reader_cache_source_rollover_requires_reopen'],
    'next invalid pinned roles' => [static fn (): mixed => $plan()['next_invalidated_reasons'][3], 'reader_cache_source_rollover_requires_reopen'],
    'next invalid dirty inherited' => [static fn (): mixed => $plan()['next_invalidated_reasons'][4], 'dirty_reader_cache_cannot_survive_next_source'],
    'next invalid stale image rollover' => [static fn (): mixed => $plan()['next_invalidated_reasons'][6], 'reader_cache_source_rollover_requires_reopen'],
    'first row current admitted' => [static fn (): mixed => $plan()['cache_rows'][0]['current_admitted'], true],
    'first row next rejected' => [static fn (): mixed => $plan()['cache_rows'][0]['next_admitted'], false],
    'first row current image matches' => [static fn (): mixed => $plan()['cache_rows'][0]['current_image_matches'], true],
    'first row next image matches' => [static fn (): mixed => $plan()['cache_rows'][0]['next_image_matches'], true],
    'preopened next row current rejected' => [static fn (): mixed => $plan()['cache_rows'][1]['current_reason'], 'reader_cache_current_source_id_mismatch'],
    'preopened next row admitted next' => [static fn (): mixed => $plan()['cache_rows'][1]['next_admitted'], true],
    'autoload next row prefix' => [static fn (): mixed => $plan()['cache_rows'][7]['next_prefix'], 'next176 next options autoload page after master source rollover'],
    'read count' => [static fn (): mixed => count($plan()['reads']), 9],
    'schema current hit' => [static fn (): mixed => $plan()['read_cache_hits']['schema-current'], true],
    'schema next miss despite same image' => [static fn (): mixed => $plan()['read_cache_hits']['schema-next'], false],
    'preopened next hit' => [static fn (): mixed => $plan()['read_cache_hits']['preopened-next'], true],
    'autoload next hit' => [static fn (): mixed => $plan()['read_cache_hits']['autoload-next'], true],
    'dirty current miss' => [static fn (): mixed => $plan()['read_cache_hits']['dirty-current'], false],
    'old epoch current miss' => [static fn (): mixed => $plan()['read_cache_hits']['old-epoch-current'], false],
    'stale image current hit after refresh not allowed' => [static fn (): mixed => $plan()['read_cache_hits']['stale-image-current'], true],
    'stale members current miss' => [static fn (): mixed => $plan()['read_cache_hits']['stale-members-current'], false],
    'schema next prefix from next source' => [static fn (): mixed => $plan()['read_prefixes']['schema-next'], 'next176 current schema page from first master source'],
    'roles next prefix from next source' => [static fn (): mixed => $plan()['read_prefixes']['roles-next'], 'next176 next roles page after master source rollover'],
    'autoload next prefix from cache' => [static fn (): mixed => $plan()['read_prefixes']['autoload-next'], 'next176 next options autoload page after master source rollover'],
    'schema next ticket current' => [static fn (): mixed => $plan()['reads'][1]['ticket_current'], true],
    'schema next source label' => [static fn (): mixed => $plan()['reads'][1]['source'], 'next-master-source-reopen-next176'],
    'preopened next source label' => [static fn (): mixed => $plan()['reads'][2]['source'], 'reader-cache-next-master-source-next176'],
    'reopen readers' => [static fn (): mixed => $plan()['reopen_reader_ids'], ['schema-next', 'roles-next', 'dirty-current', 'old-epoch-current', 'stale-members-current']],
    'requires reopen' => [static fn (): mixed => $plan()['requires_reader_reopen'], true],
    'first operation' => [static fn (): mixed => $plan()['operations'][0]['op'], 'read_current_and_next_master_journals_for_reader_cache_next176'],
    'retain operation' => [static fn (): mixed => $plan()['operations'][1]['op'], 'retain_reader_cache_for_current_master_source_next176'],
    'rollover invalidation operation' => [static fn (): mixed => $plan()['operations'][2]['op'], 'invalidate_reader_cache_before_next_master_source_next176'],
    'read hit operation exists' => [static fn (): mixed => in_array('next176_current_reader_cache_hit', array_column($plan()['operations'], 'op'), true), true],
    'read reopen operation exists' => [static fn (): mixed => in_array('next176_next_reader_reopen', array_column($plan()['operations'], 'op'), true), true],
    'source digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next176', $plan()['dependencies'], true), true],
    'dependency next173' => [static fn (): mixed => in_array('sqlite-pager-master-journal-reader-cache-current-source-next173', $plan()['dependencies'], true), true],
    'non overlap mentions rollover' => [static fn (): mixed => str_contains($plan()['non_overlap'], 'source rollover'), true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager master journal reader cache current source next176 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'empty database path rejected' => static fn () => $plan(null, null, null, null, null, null, null, ''),
    'empty master path rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, ''),
    'empty current source rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, null, ''),
    'empty next source rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, null, null, 176, ''),
    'same source rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, null, null, 176, $currentSource),
    'non advancing epoch rejected' => static fn () => $plan(null, null, null, null, null, null, null, null, null, null, 176, null, 176),
    'empty current master rejected' => static fn () => $plan(null, null, null, null, ''),
    'empty next master rejected' => static fn () => $plan(null, null, null, null, null, ''),
    'bad page size rejected' => static fn () => $plan(null, null, null, null, null, null, 500),
    'empty current pages rejected' => static fn () => $plan([]),
    'empty next pages rejected' => static fn () => $plan(null, []),
    'empty cache rejected' => static fn () => $plan(null, null, []),
    'empty reads rejected' => static fn () => $plan(null, null, null, []),
    'wrong current master rejected' => static fn () => $plan(null, null, null, null, '/tmp/other.sqlite-journal', null),
    'wrong next master rejected' => static fn () => $plan(null, null, null, null, null, '/tmp/other.sqlite-journal'),
    'zero current page rejected' => static fn () => $plan([0 => $currentPages[1]]),
    'short next page rejected' => static fn () => $plan(null, [1 => 'short']),
    'zero cache page rejected' => static fn () => $plan(null, null, [0 => $cache[1]]),
    'short cache image rejected' => static fn () => $plan(null, null, [1 => array_replace($cache[1], ['image' => 'short'])]),
    'empty cache source rejected' => static fn () => $plan(null, null, [1 => array_replace($cache[1], ['source_id' => ''])]),
    'bad cache epoch rejected' => static fn () => $plan(null, null, [1 => array_replace($cache[1], ['epoch' => 0])]),
    'bad cache digest rejected' => static fn () => $plan(null, null, [1 => array_replace($cache[1], ['master_digest' => 42])]),
    'bad cache members rejected' => static fn () => $plan(null, null, [1 => array_replace($cache[1], ['master_members' => 'bad'])]),
    'empty cache member rejected' => static fn () => $plan(null, null, [1 => array_replace($cache[1], ['master_members' => ['']])]),
    'cache outside current rejected' => static fn () => $plan(null, null, [9 => array_replace($cache[1], ['image' => $page('outside')])]),
    'empty read id rejected' => static fn () => $plan(null, null, null, [['reader_id' => '', 'page_number' => 1, 'source_id' => $nextSource, 'epoch' => 177, 'master_digest' => $nextDigest, 'phase' => 'next']]),
    'empty read source rejected' => static fn () => $plan(null, null, null, [['reader_id' => 'bad', 'page_number' => 1, 'source_id' => '', 'epoch' => 177, 'master_digest' => $nextDigest, 'phase' => 'next']]),
    'bad read phase rejected' => static fn () => $plan(null, null, null, [['reader_id' => 'bad', 'page_number' => 1, 'source_id' => $nextSource, 'epoch' => 177, 'master_digest' => $nextDigest, 'phase' => 'later']]),
    'zero read page rejected' => static fn () => $plan(null, null, null, [['reader_id' => 'bad', 'page_number' => 0, 'source_id' => $nextSource, 'epoch' => 177, 'master_digest' => $nextDigest, 'phase' => 'next']]),
    'bad read epoch rejected' => static fn () => $plan(null, null, null, [['reader_id' => 'bad', 'page_number' => 1, 'source_id' => $nextSource, 'epoch' => 0, 'master_digest' => $nextDigest, 'phase' => 'next']]),
    'read outside rejected' => static fn () => $plan(null, null, null, [['reader_id' => 'bad', 'page_number' => 9, 'source_id' => $nextSource, 'epoch' => 177, 'master_digest' => $nextDigest, 'phase' => 'next']]),
];

foreach ($throws as $name => $callback) {
    $tests['pager master journal reader cache current source next176 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
