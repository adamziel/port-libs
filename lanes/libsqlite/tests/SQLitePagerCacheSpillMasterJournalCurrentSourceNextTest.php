<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerCacheSpillMasterJournalCurrentSourceNextPlan;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-next150.sqlite';
$journalPath = $databasePath . '-journal';
$masterPath = '/srv/wp-content/database/wp-next150-master-journal';
$otherJournal = '/srv/wp-content/database/site-next150.sqlite-journal';
$sourceId = 'wp-next150-master-current-source';
$page = static fn (string $label, string $pad = '.'): string => str_pad($label, $pageSize, $pad, STR_PAD_RIGHT);

$databasePages = [
    1 => $page('next150 current schema root before spill'),
    2 => $page('next150 current wp_options root before spill'),
    3 => $page('next150 current autoload index before spill'),
    4 => $page('next150 current plugin settings before spill'),
    5 => $page('next150 current transient cache before spill'),
    6 => $page('next150 current comments cache before spill'),
];
$databaseBytes = implode('', $databasePages);
$cachedMaster = $otherJournal . "\n" . $journalPath . "\n";
$currentMaster = $journalPath . "\n" . $otherJournal . "\n";
$nextMaster = $journalPath . "\n" . $otherJournal . "\n";

$cache = [
    1 => ['image' => $page('next150 dirty schema root cache page'), 'before_image' => $databasePages[1], 'master_member' => $journalPath, 'source_id' => $sourceId, 'epoch' => 6, 'journaled' => true, 'bytes' => $pageSize, 'walFrame' => 21],
    2 => ['image' => $page('next150 dirty options root cache page'), 'before_image' => $databasePages[2], 'master_member' => $journalPath, 'source_id' => $sourceId, 'epoch' => 6, 'journaled' => true, 'bytes' => $pageSize, 'walFrame' => 22],
    3 => ['image' => $page('next150 dirty stale before image'), 'before_image' => $page('next150 stale options before image'), 'master_member' => $journalPath, 'source_id' => $sourceId, 'epoch' => 6, 'journaled' => true, 'bytes' => $pageSize, 'walFrame' => 23],
    4 => ['image' => $page('next150 dirty wrong member cache page'), 'before_image' => $databasePages[4], 'master_member' => $otherJournal, 'source_id' => $sourceId, 'epoch' => 6, 'journaled' => true, 'bytes' => $pageSize, 'walFrame' => 24],
    5 => ['image' => $page('next150 dirty pinned transient cache page'), 'before_image' => $databasePages[5], 'master_member' => $journalPath, 'source_id' => $sourceId, 'epoch' => 6, 'journaled' => true, 'pinned' => true, 'bytes' => $pageSize, 'walFrame' => 25],
    6 => ['image' => $page('next150 clean comments cache page'), 'before_image' => $databasePages[6], 'master_member' => $journalPath, 'source_id' => $sourceId, 'epoch' => 6, 'dirty' => false, 'journaled' => true, 'bytes' => $pageSize, 'walFrame' => 26],
];

$plan = static fn (
    ?array $pages = null,
    string $journalMode = 'delete',
    bool $synced = true,
    string $lock = 'reserved',
    bool $enabled = true,
    ?int $max = null,
    mixed $cached = '__default__',
    mixed $current = '__default__',
    mixed $next = '__default__',
    ?string $bytes = null,
    ?int $size = null,
    ?string $path = null,
    ?string $journal = null,
    ?string $master = null,
    ?string $source = null,
    int $epoch = 6,
): array => SQLitePagerCacheSpillMasterJournalCurrentSourceNextPlan::plan(
    $path ?? $databasePath,
    $journal ?? $journalPath,
    $master ?? $masterPath,
    $cached === '__default__' ? $cachedMaster : $cached,
    $current === '__default__' ? $currentMaster : $current,
    $next === '__default__' ? $nextMaster : $next,
    $bytes ?? $databaseBytes,
    $size ?? $pageSize,
    $pages ?? $cache,
    10,
    4,
    $journalMode,
    $synced,
    $lock,
    $enabled,
    $max,
    $source ?? $sourceId,
    $epoch,
);

$walPlan = static fn (): array => $plan(null, 'wal', true, 'shared');
$removedPlan = static fn (): array => $plan(null, 'delete', true, 'reserved', true, null, '__default__', '__default__', $otherJournal . "\n");
$deferredPlan = static fn (): array => $plan([
    3 => $cache[3],
    5 => $cache[5],
    6 => $cache[6],
]);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager_cache_spill_master_journal_current_source_next150'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'cache_spill_pages_admitted_from_current_master_journal_source'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $databasePath],
    'journal path' => [static fn (): mixed => $plan()['journal_path'], $journalPath],
    'master path' => [static fn (): mixed => $plan()['master_journal_path'], $masterPath],
    'cached members' => [static fn (): mixed => $plan()['cached_members'], [$otherJournal, $journalPath]],
    'current members' => [static fn (): mixed => $plan()['current_members'], [$journalPath, $otherJournal]],
    'next members' => [static fn (): mixed => $plan()['next_members'], [$journalPath, $otherJournal]],
    'cached stale true' => [static fn (): mixed => $plan()['cached_master_stale'], true],
    'current member true' => [static fn (): mixed => $plan()['current_master_member'], true],
    'next member true' => [static fn (): mixed => $plan()['next_master_member'], true],
    'page size' => [static fn (): mixed => $plan()['page_size'], $pageSize],
    'journal mode' => [static fn (): mixed => $plan()['journal_mode'], 'delete'],
    'source id' => [static fn (): mixed => $plan()['current_source']['id'], $sourceId],
    'source epoch' => [static fn (): mixed => $plan()['current_source']['epoch'], 6],
    'admitted pages' => [static fn (): mixed => $plan()['admitted_page_numbers'], [1, 2]],
    'rejected pages' => [static fn (): mixed => $plan()['rejected_page_numbers'], [3, 4, 5, 6]],
    'page one admitted' => [static fn (): mixed => $plan()['source_checks_by_page'][1]['admitted'], true],
    'page one before prefix' => [static fn (): mixed => $plan()['source_checks_by_page'][1]['before_prefix'], 'next150 current schema root before spill'],
    'page one current match' => [static fn (): mixed => $plan()['source_checks_by_page'][1]['matches_current_database'], true],
    'page two master member' => [static fn (): mixed => $plan()['source_checks_by_page'][2]['master_member'], $journalPath],
    'page three stale rejected' => [static fn (): mixed => $plan()['rejected_pages'][3], ['before_image_mismatch_current_database']],
    'page three mismatch flag' => [static fn (): mixed => $plan()['source_checks_by_page'][3]['matches_current_database'], false],
    'page four wrong member rejected' => [static fn (): mixed => $plan()['rejected_pages'][4], ['wrong_master_journal_member']],
    'page five pinned rejected' => [static fn (): mixed => $plan()['rejected_pages'][5], ['cache_page_pinned']],
    'page six clean rejected' => [static fn (): mixed => $plan()['rejected_pages'][6], ['cache_page_clean']],
    'source row count' => [static fn (): mixed => count($plan()['source_checks']), 6],
    'spill status' => [static fn (): mixed => $plan()['spill']['status'], 'spilled'],
    'spill target' => [static fn (): mixed => $plan()['spill']['spill_target'], 'database_pages_after_rollback_journal'],
    'spilled pages' => [static fn (): mixed => $plan()['spilled_page_numbers'], [1, 2]],
    'spill current dirty filtered' => [static fn (): mixed => $plan()['spill']['current']['dirty_pages'], [1, 2]],
    'spill current journaled filtered' => [static fn (): mixed => $plan()['spill']['current']['journaled_pages'], [1, 2]],
    'spill no pinned pages' => [static fn (): mixed => $plan()['spill']['current']['pinned_pages'], []],
    'spill next dirty empty' => [static fn (): mixed => $plan()['spill']['next']['dirty_pages'], []],
    'operation discards cached master' => [static fn (): mixed => $plan()['operations'][0]['op'], 'discard_cached_master_journal_before_cache_spill'],
    'operation reads current master' => [static fn (): mixed => $plan()['operations'][1]['op'], 'read_current_master_journal_before_cache_spill'],
    'operation read member' => [static fn (): mixed => $plan()['operations'][1]['member'], $journalPath],
    'operation admits page one' => [static fn (): mixed => $plan()['operations'][2]['op'], 'admit_master_journal_cache_spill_page'],
    'operation admits page two' => [static fn (): mixed => $plan()['operations'][3]['page'], 2],
    'operation defers page three' => [static fn (): mixed => $plan()['operations'][4]['reasons'], ['before_image_mismatch_current_database']],
    'operation defers page four' => [static fn (): mixed => $plan()['operations'][5]['page'], 4],
    'operation defers page five' => [static fn (): mixed => $plan()['operations'][6]['reasons'], ['cache_page_pinned']],
    'operation defers page six' => [static fn (): mixed => $plan()['operations'][7]['reasons'], ['cache_page_clean']],
    'operation promotes lock' => [static fn (): mixed => $plan()['operations'][8]['op'], 'promote_lock'],
    'operation writes page one' => [static fn (): mixed => $plan()['operations'][9]['page'], 1],
    'operation writes page two' => [static fn (): mixed => $plan()['operations'][11]['page'], 2],
    'digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'dependency next150' => [static fn (): mixed => in_array('sqlite-pager-cache-spill-master-journal-current-source-next150', $plan()['dependencies'], true), true],
    'dependency recheck' => [static fn (): mixed => in_array('sqlite-master-journal-current-source-recheck', $plan()['dependencies'], true), true],
    'dependency spill next107' => [static fn (): mixed => in_array('sqlite-pager-cache-spill-journalmode-current-source-next107', $plan()['dependencies'], true), true],
    'wal status' => [static fn (): mixed => $walPlan()['status'], 'pager_cache_spill_master_journal_current_source_next150'],
    'wal target' => [static fn (): mixed => $walPlan()['spill']['spill_target'], 'wal_frames'],
    'wal frame pages' => [static fn (): mixed => $walPlan()['wal_frame_pages'], [1, 2]],
    'wal database unchanged' => [static fn (): mixed => $walPlan()['spill']['next']['database_image'], 'unchanged_until_checkpoint'],
    'wal first operation appends frame' => [static fn (): mixed => $walPlan()['spill']['operations'][0]['op'], 'append_wal_frame'],
    'max one still admits two' => [static fn (): mixed => $plan(null, 'delete', true, 'reserved', true, 1)['admitted_page_numbers'], [1, 2]],
    'max one spills one' => [static fn (): mixed => $plan(null, 'delete', true, 'reserved', true, 1)['spilled_page_numbers'], [1]],
    'unsynced defers' => [static fn (): mixed => $plan(null, 'delete', false)['status'], 'pager_cache_spill_master_journal_current_source_deferred_next150'],
    'unsynced reason' => [static fn (): mixed => $plan(null, 'delete', false)['spill']['blocked_reasons'], ['journal_not_synced']],
    'disabled defers' => [static fn (): mixed => $plan(null, 'delete', true, 'reserved', false)['status'], 'pager_cache_spill_master_journal_current_source_deferred_next150'],
    'all rejected defers' => [static fn (): mixed => $deferredPlan()['status'], 'pager_cache_spill_master_journal_current_source_deferred_next150'],
    'all rejected no admitted' => [static fn (): mixed => $deferredPlan()['admitted_page_numbers'], []],
    'all rejected no eligible' => [static fn (): mixed => $deferredPlan()['spill']['blocked_reasons'], ['no_journaled_unpinned_dirty_pages']],
    'removed next member defers page one' => [static fn (): mixed => $removedPlan()['rejected_pages'][1], ['journal_removed_from_next_master_source']],
    'removed next member flag' => [static fn (): mixed => $removedPlan()['next_master_member'], false],
    'missing rollback source rejected' => [static fn (): mixed => $plan([1 => array_replace($cache[1], ['journaled' => false])])['rejected_pages'][1], ['missing_rollback_source']],
    'stale source id rejected' => [static fn (): mixed => $plan([1 => array_replace($cache[1], ['source_id' => 'old-source'])])['rejected_pages'][1], ['stale_master_source_id']],
    'stale epoch rejected' => [static fn (): mixed => $plan([1 => array_replace($cache[1], ['epoch' => 5])])['rejected_pages'][1], ['stale_master_source_epoch']],
    'matching cached master skips discard' => [static fn (): mixed => $plan(null, 'delete', true, 'reserved', true, null, $currentMaster)['operations'][0]['op'], 'read_current_master_journal_before_cache_spill'],
    'null next master treated active' => [static fn (): mixed => $plan(null, 'delete', true, 'reserved', true, null, '__default__', '__default__', null)['next_master_member'], true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager cache spill master journal current source next150 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'rejects empty database path' => static fn () => $plan(null, 'delete', true, 'reserved', true, null, '__default__', '__default__', '__default__', null, null, ''),
    'rejects empty journal path' => static fn () => $plan(null, 'delete', true, 'reserved', true, null, '__default__', '__default__', '__default__', null, null, null, ''),
    'rejects empty master path' => static fn () => $plan(null, 'delete', true, 'reserved', true, null, '__default__', '__default__', '__default__', null, null, null, null, ''),
    'rejects missing current master' => static fn () => $plan(null, 'delete', true, 'reserved', true, null, '__default__', null),
    'rejects current master without member' => static fn () => $plan(null, 'delete', true, 'reserved', true, null, '__default__', $otherJournal),
    'rejects empty database bytes' => static fn () => $plan(null, 'delete', true, 'reserved', true, null, '__default__', '__default__', '__default__', ''),
    'rejects misaligned database bytes' => static fn () => $plan(null, 'delete', true, 'reserved', true, null, '__default__', '__default__', '__default__', $databaseBytes . 'x'),
    'rejects small page size' => static fn () => $plan(null, 'delete', true, 'reserved', true, null, '__default__', '__default__', '__default__', null, 128),
    'rejects non power page size' => static fn () => $plan([1 => array_replace($cache[1], ['image' => str_pad('x', 768, '.'), 'before_image' => str_pad('db', 768, '.')])], 'delete', true, 'reserved', true, null, $journalPath, $journalPath, $journalPath, str_pad('db', 768, '.') . str_pad('db2', 768, '.'), 768),
    'rejects empty cache pages' => static fn () => $plan([]),
    'rejects zero cache page' => static fn () => $plan([0 => $cache[1]]),
    'rejects outside cache page' => static fn () => $plan([7 => $cache[1]]),
    'rejects short cache image' => static fn () => $plan([1 => array_replace($cache[1], ['image' => 'short'])]),
    'rejects short before image' => static fn () => $plan([1 => array_replace($cache[1], ['before_image' => 'short'])]),
    'rejects empty master member' => static fn () => $plan([1 => array_replace($cache[1], ['master_member' => ''])]),
    'rejects empty source id' => static fn () => $plan([1 => array_replace($cache[1], ['source_id' => ''])]),
    'rejects bad page epoch' => static fn () => $plan([1 => array_replace($cache[1], ['epoch' => 0])]),
    'rejects bad bytes' => static fn () => $plan([1 => array_replace($cache[1], ['bytes' => -1])]),
    'rejects bad wal frame' => static fn () => $plan([1 => array_replace($cache[1], ['walFrame' => 0])]),
    'rejects empty current source' => static fn () => $plan(null, 'delete', true, 'reserved', true, null, '__default__', '__default__', '__default__', null, null, null, null, null, ''),
    'rejects bad current epoch' => static fn () => $plan(null, 'delete', true, 'reserved', true, null, '__default__', '__default__', '__default__', null, null, null, null, null, null, 0),
    'rejects bad journal mode' => static fn () => $plan(null, 'bad'),
];

foreach ($throws as $name => $callback) {
    $tests['pager cache spill master journal current source next150 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
