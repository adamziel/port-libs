<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerHotJournalCacheSpillMasterCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-next145.sqlite';
$journalPath = $databasePath . '-journal';
$masterPath = '/srv/wp-content/database/wp-next145.sqlite-mj';
$masterBytes = $journalPath . "\n/srv/wp-content/database/site-next145.sqlite-journal\n";
$sourceId = 'master-hot-current-next145';
$page = static fn (string $label, string $pad = '.'): string => str_pad($label, $pageSize, $pad, STR_PAD_RIGHT);

$before = [
    1 => $page('next145 before schema root dirty crash image'),
    2 => $page('next145 before wp_options root dirty crash image'),
    3 => $page('next145 before autoload index dirty crash image'),
    4 => $page('next145 before plugin settings dirty crash image'),
    5 => $page('next145 before transient cache dirty crash image'),
    6 => $page('next145 before comments cache unchanged image'),
];
$databaseBytes = implode('', $before);
$hot = [
    1 => $page('next145 recovered schema root master hot source'),
    2 => $page('next145 recovered wp_options root master hot source'),
    3 => $page('next145 recovered autoload index master hot source'),
    4 => $page('next145 recovered plugin settings master hot source'),
    5 => $page('next145 recovered transient cache master hot source'),
];
$cache = [
    1 => ['image' => $page('next145 dirty schema cache after recovery'), 'current_image' => $hot[1], 'source_id' => $sourceId, 'epoch' => 4, 'journaled' => true, 'bytes' => $pageSize, 'walFrame' => 12],
    2 => ['image' => $page('next145 dirty options cache after recovery'), 'current_image' => $hot[2], 'source_id' => $sourceId, 'epoch' => 4, 'journaled' => true, 'bytes' => $pageSize, 'walFrame' => 13],
    3 => ['image' => $page('next145 dirty autoload cache stale source'), 'current_image' => $before[3], 'source_id' => $sourceId, 'epoch' => 4, 'journaled' => true, 'bytes' => $pageSize, 'walFrame' => 14],
    4 => ['image' => $page('next145 dirty plugin cache old source id'), 'current_image' => $hot[4], 'source_id' => 'old-master-hot-source', 'epoch' => 4, 'journaled' => true, 'bytes' => $pageSize, 'walFrame' => 15],
    5 => ['image' => $page('next145 dirty transient pinned cache'), 'current_image' => $hot[5], 'source_id' => $sourceId, 'epoch' => 4, 'journaled' => true, 'pinned' => true, 'bytes' => $pageSize, 'walFrame' => 16],
    6 => ['image' => $page('next145 clean comments cache'), 'current_image' => $before[6], 'source_id' => $sourceId, 'epoch' => 4, 'dirty' => false, 'journaled' => true, 'bytes' => $pageSize],
];

$plan = static fn (
    ?array $hotPages = null,
    ?array $cachePages = null,
    string $journalMode = 'delete',
    bool $journalSynced = true,
    string $lockState = 'reserved',
    bool $cacheSpillEnabled = true,
    ?int $maxSpillPages = null,
    mixed $master = '__default__',
    ?string $bytes = null,
    ?int $size = null,
    ?string $path = null,
    ?string $journal = null,
    ?string $masterJournal = null,
    ?string $currentSource = null,
    int $epoch = 4,
): array => SQLitePagerHotJournalCacheSpillMasterCurrentSourceNextPlan::plan(
    $path ?? $databasePath,
    $journal ?? $journalPath,
    $masterJournal ?? $masterPath,
    $master === '__default__' ? $masterBytes : $master,
    $bytes ?? $databaseBytes,
    $size ?? $pageSize,
    $hotPages ?? $hot,
    $cachePages ?? $cache,
    9,
    4,
    $journalMode,
    $journalSynced,
    $lockState,
    $cacheSpillEnabled,
    $maxSpillPages,
    $currentSource ?? $sourceId,
    $epoch,
);

$walPlan = static fn (): array => $plan(null, null, 'wal', true, 'shared');
$deferredPlan = static fn (): array => $plan(null, [
    3 => $cache[3],
    5 => $cache[5],
    6 => $cache[6],
]);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager_hot_journal_cache_spill_master_current_source_next145'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'cache_spill_pages_rebased_to_master_hot_current_source'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $databasePath],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], $journalPath],
    'master path' => [static fn (): mixed => $plan()['master_journal_path'], $masterPath],
    'master members' => [static fn (): mixed => $plan()['master_members'], [$journalPath, '/srv/wp-content/database/site-next145.sqlite-journal']],
    'page size' => [static fn (): mixed => $plan()['page_size'], $pageSize],
    'journal mode' => [static fn (): mixed => $plan()['journal_mode'], 'delete'],
    'current source id' => [static fn (): mixed => $plan()['current_source']['id'], $sourceId],
    'current source epoch' => [static fn (): mixed => $plan()['current_source']['epoch'], 4],
    'hot pages' => [static fn (): mixed => $plan()['hot_journal_page_numbers'], [1, 2, 3, 4, 5]],
    'admitted pages' => [static fn (): mixed => $plan()['admitted_page_numbers'], [1, 2]],
    'rejected pages' => [static fn (): mixed => $plan()['rejected_page_numbers'], [3, 4, 5, 6]],
    'page one admitted' => [static fn (): mixed => $plan()['source_checks_by_page'][1]['admitted'], true],
    'page one recovered prefix' => [static fn (): mixed => $plan()['source_checks_by_page'][1]['recovered_prefix'], 'next145 recovered schema root master hot source'],
    'page one current matches' => [static fn (): mixed => $plan()['source_checks_by_page'][1]['matches_recovered_current_source'], true],
    'page two admitted hot' => [static fn (): mixed => $plan()['source_checks_by_page'][2]['hot_journal_page'], true],
    'page three stale rejected' => [static fn (): mixed => $plan()['rejected_pages'][3], ['current_source_mismatch_after_hot_recovery']],
    'page three mismatch flag' => [static fn (): mixed => $plan()['source_checks_by_page'][3]['matches_recovered_current_source'], false],
    'page four source rejected' => [static fn (): mixed => $plan()['rejected_pages'][4], ['stale_master_source_id']],
    'page four source id' => [static fn (): mixed => $plan()['source_checks_by_page'][4]['source_id'], 'old-master-hot-source'],
    'page five pinned rejected' => [static fn (): mixed => $plan()['rejected_pages'][5], ['cache_page_pinned']],
    'page five pinned flag' => [static fn (): mixed => $plan()['source_checks_by_page'][5]['pinned'], true],
    'page six clean rejected' => [static fn (): mixed => $plan()['rejected_pages'][6], ['cache_page_clean']],
    'page six hot false' => [static fn (): mixed => $plan()['source_checks_by_page'][6]['hot_journal_page'], false],
    'source row count' => [static fn (): mixed => count($plan()['source_checks']), 6],
    'spill status' => [static fn (): mixed => $plan()['spill']['status'], 'spilled'],
    'spill target' => [static fn (): mixed => $plan()['spill']['spill_target'], 'database_pages_after_rollback_journal'],
    'spilled pages' => [static fn (): mixed => $plan()['spilled_page_numbers'], [1, 2]],
    'spill dirty pages filtered' => [static fn (): mixed => $plan()['spill']['current']['dirty_pages'], [1, 2]],
    'spill journaled pages filtered' => [static fn (): mixed => $plan()['spill']['current']['journaled_pages'], [1, 2]],
    'spill no pinned pages' => [static fn (): mixed => $plan()['spill']['current']['pinned_pages'], []],
    'spill next dirty empty' => [static fn (): mixed => $plan()['spill']['next']['dirty_pages'], []],
    'spill promotes lock' => [static fn (): mixed => $plan()['spill']['operations'][0]['op'], 'promote_lock'],
    'operation reads master' => [static fn (): mixed => $plan()['operations'][0]['op'], 'read_master_journal_for_cache_spill'],
    'operation reads member' => [static fn (): mixed => $plan()['operations'][0]['member'], $journalPath],
    'operation restores page one' => [static fn (): mixed => $plan()['operations'][1]['page_number'], 1],
    'operation restores page five' => [static fn (): mixed => $plan()['operations'][5]['page_number'], 5],
    'operation admits page one' => [static fn (): mixed => $plan()['operations'][6]['op'], 'admit_master_hot_cache_spill_page'],
    'operation admits page two' => [static fn (): mixed => $plan()['operations'][7]['page'], 2],
    'operation defers page three' => [static fn (): mixed => $plan()['operations'][8]['reasons'], ['current_source_mismatch_after_hot_recovery']],
    'operation defers page four' => [static fn (): mixed => $plan()['operations'][9]['page'], 4],
    'operation defers page five' => [static fn (): mixed => $plan()['operations'][10]['reasons'], ['cache_page_pinned']],
    'operation defers page six' => [static fn (): mixed => $plan()['operations'][11]['reasons'], ['cache_page_clean']],
    'operation spill promote after filter' => [static fn (): mixed => $plan()['operations'][12]['op'], 'promote_lock'],
    'operation write page one' => [static fn (): mixed => $plan()['operations'][13]['page'], 1],
    'operation write page two' => [static fn (): mixed => $plan()['operations'][15]['page'], 2],
    'digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'dependency next145' => [static fn (): mixed => in_array('sqlite-pager-hot-journal-cache-spill-master-current-source-next145', $plan()['dependencies'], true), true],
    'dependency master validation' => [static fn (): mixed => in_array('sqlite-master-journal-current-source-member-validation', $plan()['dependencies'], true), true],
    'dependency cache spill next107' => [static fn (): mixed => in_array('sqlite-pager-cache-spill-journalmode-current-source-next107', $plan()['dependencies'], true), true],
    'dependency hot before spill' => [static fn (): mixed => in_array('sqlite-hot-journal-before-cache-spill', $plan()['dependencies'], true), true],
    'wal status' => [static fn (): mixed => $walPlan()['status'], 'pager_hot_journal_cache_spill_master_current_source_next145'],
    'wal target' => [static fn (): mixed => $walPlan()['spill']['spill_target'], 'wal_frames'],
    'wal frame pages' => [static fn (): mixed => $walPlan()['wal_frame_pages'], [1, 2]],
    'wal database unchanged' => [static fn (): mixed => $walPlan()['spill']['next']['database_image'], 'unchanged_until_checkpoint'],
    'wal first operation appends frame' => [static fn (): mixed => $walPlan()['spill']['operations'][0]['op'], 'append_wal_frame'],
    'one page limit admitted unchanged' => [static fn (): mixed => $plan(null, null, 'delete', true, 'reserved', true, 1)['admitted_page_numbers'], [1, 2]],
    'one page limit spills page one' => [static fn (): mixed => $plan(null, null, 'delete', true, 'reserved', true, 1)['spilled_page_numbers'], [1]],
    'unsynced defers' => [static fn (): mixed => $plan(null, null, 'delete', false)['status'], 'pager_hot_journal_cache_spill_master_current_source_deferred_next145'],
    'unsynced reason' => [static fn (): mixed => $plan(null, null, 'delete', false)['spill']['blocked_reasons'], ['journal_not_synced']],
    'disabled defers' => [static fn (): mixed => $plan(null, null, 'delete', true, 'reserved', false)['status'], 'pager_hot_journal_cache_spill_master_current_source_deferred_next145'],
    'all rejected defers' => [static fn (): mixed => $deferredPlan()['status'], 'pager_hot_journal_cache_spill_master_current_source_deferred_next145'],
    'all rejected no admitted pages' => [static fn (): mixed => $deferredPlan()['admitted_page_numbers'], []],
    'all rejected no eligible' => [static fn (): mixed => $deferredPlan()['spill']['blocked_reasons'], ['no_journaled_unpinned_dirty_pages']],
    'missing journal source rejected' => [static fn (): mixed => $plan(null, [1 => array_replace($cache[1], ['journaled' => false])])['rejected_pages'][1], ['missing_rollback_source']],
    'stale epoch rejected' => [static fn (): mixed => $plan(null, [1 => array_replace($cache[1], ['epoch' => 3])])['rejected_pages'][1], ['stale_master_source_epoch']],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager hot journal cache spill master current source next145 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'rejects empty database path' => static fn () => $plan(null, null, 'delete', true, 'reserved', true, null, '__default__', null, null, ''),
    'rejects empty journal path' => static fn () => $plan(null, null, 'delete', true, 'reserved', true, null, '__default__', null, null, null, ''),
    'rejects empty master path' => static fn () => $plan(null, null, 'delete', true, 'reserved', true, null, '__default__', null, null, null, null, ''),
    'rejects missing master' => static fn () => $plan(null, null, 'delete', true, 'reserved', true, null, null),
    'rejects master without member' => static fn () => $plan(null, null, 'delete', true, 'reserved', true, null, '/tmp/other.sqlite-journal'),
    'rejects empty database bytes' => static fn () => $plan(null, null, 'delete', true, 'reserved', true, null, '__default__', ''),
    'rejects misaligned database bytes' => static fn () => $plan(null, null, 'delete', true, 'reserved', true, null, '__default__', $databaseBytes . 'x'),
    'rejects small page size' => static fn () => $plan(null, null, 'delete', true, 'reserved', true, null, '__default__', null, 128),
    'rejects non power page size' => static fn () => $plan([1 => str_pad('x', 768, '.')], [1 => ['image' => str_pad('y', 768, '.')]], 'delete', true, 'reserved', true, null, $journalPath, str_pad('db', 768, '.') . str_pad('db2', 768, '.'), 768),
    'rejects empty hot pages' => static fn () => $plan([]),
    'rejects empty cache pages' => static fn () => $plan(null, []),
    'rejects zero hot page' => static fn () => $plan([0 => $hot[1]]),
    'rejects outside hot page' => static fn () => $plan([7 => $hot[1]]),
    'rejects short hot image' => static fn () => $plan([1 => 'short']),
    'rejects zero cache page' => static fn () => $plan(null, [0 => $cache[1]]),
    'rejects outside cache page' => static fn () => $plan(null, [7 => $cache[1]]),
    'rejects short cache image' => static fn () => $plan(null, [1 => ['image' => 'short']]),
    'rejects short current image' => static fn () => $plan(null, [1 => array_replace($cache[1], ['current_image' => 'short'])]),
    'rejects empty cache source' => static fn () => $plan(null, [1 => array_replace($cache[1], ['source_id' => ''])]),
    'rejects bad cache epoch' => static fn () => $plan(null, [1 => array_replace($cache[1], ['epoch' => 0])]),
    'rejects bad cache bytes' => static fn () => $plan(null, [1 => array_replace($cache[1], ['bytes' => -1])]),
    'rejects bad cache wal frame' => static fn () => $plan(null, [1 => array_replace($cache[1], ['walFrame' => 0])]),
    'rejects empty current source' => static fn () => $plan(null, null, 'delete', true, 'reserved', true, null, '__default__', null, null, null, null, null, ''),
    'rejects bad current epoch' => static fn () => $plan(null, null, 'delete', true, 'reserved', true, null, '__default__', null, null, null, null, null, null, 0),
    'rejects bad journal mode' => static fn () => $plan(null, null, 'bad'),
];

foreach ($throws as $name => $callback) {
    $tests['pager hot journal cache spill master current source next145 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
