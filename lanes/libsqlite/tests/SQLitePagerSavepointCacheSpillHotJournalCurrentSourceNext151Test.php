<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerSavepointCacheSpillHotJournalCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSavepointStack;

$tests = [];

$pageSize = 512;
$databasePath = '/srv/wp-content/database/wp-next151.sqlite';
$page = static fn (string $label, string $pad = '.'): string => str_pad($label, $pageSize, $pad, STR_PAD_RIGHT);

$before = [
    1 => $page('next151 before schema root stale crash'),
    2 => $page('next151 before wp_options active_plugins stale crash'),
    3 => $page('next151 before autoload index stale crash'),
    4 => $page('next151 before plugin settings stale crash'),
    5 => $page('next151 before transient stale crash'),
    6 => $page('next151 before comments clean'),
];
$databaseBytes = implode('', $before);
$hot = [
    1 => $page('next151 recovered schema root current source'),
    2 => $page('next151 recovered active_plugins current source'),
    3 => $page('next151 recovered autoload index current source'),
    4 => $page('next151 recovered plugin settings current source'),
    5 => $page('next151 recovered transient current source'),
];
$dirty = [
    1 => $page('next151 dirty schema root after recovery'),
    2 => $page('next151 dirty active_plugins after recovery'),
    3 => $page('next151 dirty autoload stale savepoint'),
    4 => $page('next151 dirty plugin settings pinned'),
    5 => $page('next151 dirty transient stale current'),
    6 => $page('next151 clean comments cache'),
];

$makeStack = static function () use ($hot, $before): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-import-next151');
    $stack->recordPageImageWrite(1, $hot[1]);
    $stack->savepoint('plugin-batch-next151');
    $stack->recordPageImageWrite(2, $hot[2]);
    $stack->recordPageImageWrite(3, $before[3]);
    $stack->recordPageImageWrite(4, $hot[4]);
    $stack->recordPageImageWrite(5, $hot[5]);

    return $stack;
};

$cachePages = [
    ['page' => 1, 'image' => $dirty[1], 'current_image' => $hot[1], 'bytes' => $pageSize, 'journaled' => true, 'walFrame' => 21],
    ['page' => 2, 'image' => $dirty[2], 'current_image' => $hot[2], 'bytes' => $pageSize, 'journaled' => true, 'walFrame' => 22],
    ['page' => 3, 'image' => $dirty[3], 'current_image' => $hot[3], 'bytes' => $pageSize, 'journaled' => true, 'walFrame' => 23],
    ['page' => 4, 'image' => $dirty[4], 'current_image' => $hot[4], 'bytes' => $pageSize, 'journaled' => true, 'pinned' => true, 'walFrame' => 24],
    ['page' => 5, 'image' => $dirty[5], 'current_image' => $before[5], 'bytes' => $pageSize, 'journaled' => true, 'walFrame' => 25],
    ['page' => 6, 'image' => $dirty[6], 'current_image' => $before[6], 'bytes' => $pageSize, 'dirty' => false, 'journaled' => true],
];

$plan = static fn (
    ?array $hotPages = null,
    ?array $pages = null,
    string $journalMode = 'delete',
    bool $journalSynced = true,
    string $lockState = 'reserved',
    bool $cacheSpillEnabled = true,
    ?int $maxSpillPages = null,
    string $savepoint = 'plugin-batch-next151',
    ?string $bytes = null,
    ?int $size = null,
    ?string $path = null,
): array => SQLitePagerSavepointCacheSpillHotJournalCurrentSourceNextPlan::plan(
    $path ?? $databasePath,
    $bytes ?? $databaseBytes,
    $size ?? $pageSize,
    $hotPages ?? $hot,
    $savepoint,
    $makeStack(),
    $pages ?? $cachePages,
    8,
    4,
    $journalMode,
    $journalSynced,
    $lockState,
    $cacheSpillEnabled,
    $maxSpillPages,
);

$walPlan = static fn (): array => $plan(null, null, 'wal', true, 'shared');
$deferredPlan = static fn (): array => $plan(null, [
    ['page' => 3, 'image' => $dirty[3], 'current_image' => $hot[3], 'bytes' => $pageSize, 'journaled' => true, 'walFrame' => 23],
    ['page' => 4, 'image' => $dirty[4], 'current_image' => $hot[4], 'bytes' => $pageSize, 'journaled' => true, 'pinned' => true, 'walFrame' => 24],
    ['page' => 5, 'image' => $dirty[5], 'current_image' => $before[5], 'bytes' => $pageSize, 'journaled' => true, 'walFrame' => 25],
]);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager_savepoint_cache_spill_hot_journal_current_source_next151'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'cache_spill_uses_savepoint_before_images_rebased_to_hot_journal_current_source'],
    'database path' => [static fn (): mixed => $plan()['database_path'], $databasePath],
    'hot journal path' => [static fn (): mixed => $plan()['hot_journal_path'], $databasePath . '-journal'],
    'savepoint' => [static fn (): mixed => $plan()['savepoint'], 'plugin-batch-next151'],
    'page size' => [static fn (): mixed => $plan()['page_size'], $pageSize],
    'page count' => [static fn (): mixed => $plan()['page_count'], 6],
    'journal mode' => [static fn (): mixed => $plan()['journal_mode'], 'delete'],
    'delete hot journal flag' => [static fn (): mixed => $plan()['delete_hot_journal_after_recovery'], true],
    'hot journal pages' => [static fn (): mixed => $plan()['hot_journal_page_numbers'], [1, 2, 3, 4, 5]],
    'savepoint restore pages' => [static fn (): mixed => $plan()['savepoint_restore_page_numbers'], [2, 3, 4, 5]],
    'savepoint missing pages' => [static fn (): mixed => $plan()['savepoint_missing_page_numbers'], []],
    'admitted pages' => [static fn (): mixed => $plan()['admitted_page_numbers'], [2]],
    'rejected pages' => [static fn (): mixed => $plan()['rejected_page_numbers'], [1, 3, 4, 5, 6]],
    'page one missing savepoint rejected' => [static fn (): mixed => $plan()['rejected_pages'][1], ['missing_savepoint_before_image']],
    'page one hot current matches' => [static fn (): mixed => $plan()['source_checks_by_page'][1]['current_matches_recovered'], true],
    'page one savepoint mismatch false' => [static fn (): mixed => $plan()['source_checks_by_page'][1]['savepoint_matches_recovered'], false],
    'page two admitted' => [static fn (): mixed => $plan()['source_checks_by_page'][2]['admitted'], true],
    'page two cache prefix' => [static fn (): mixed => $plan()['source_checks_by_page'][2]['cache_prefix'], 'next151 dirty active_plugins after recovery'],
    'page two current prefix' => [static fn (): mixed => $plan()['source_checks_by_page'][2]['current_prefix'], 'next151 recovered active_plugins current source'],
    'page two savepoint prefix' => [static fn (): mixed => $plan()['source_checks_by_page'][2]['savepoint_prefix'], 'next151 recovered active_plugins current source'],
    'page two savepoint matches' => [static fn (): mixed => $plan()['source_checks_by_page'][2]['savepoint_matches_recovered'], true],
    'page three stale savepoint rejected' => [static fn (): mixed => $plan()['rejected_pages'][3], ['stale_savepoint_before_image_before_hot_journal_recovery']],
    'page three savepoint prefix' => [static fn (): mixed => $plan()['source_checks_by_page'][3]['savepoint_prefix'], 'next151 before autoload index stale crash'],
    'page three recovered prefix' => [static fn (): mixed => $plan()['source_checks_by_page'][3]['recovered_prefix'], 'next151 recovered autoload index current source'],
    'page four pinned rejected' => [static fn (): mixed => $plan()['rejected_pages'][4], ['cache_page_pinned']],
    'page four pinned flag' => [static fn (): mixed => $plan()['source_checks_by_page'][4]['pinned'], true],
    'page five stale current rejected' => [static fn (): mixed => $plan()['rejected_pages'][5], ['current_source_mismatch_after_hot_journal_recovery']],
    'page five current matches false' => [static fn (): mixed => $plan()['source_checks_by_page'][5]['current_matches_recovered'], false],
    'page six clean missing savepoint rejected' => [static fn (): mixed => $plan()['rejected_pages'][6], ['cache_page_clean', 'missing_savepoint_before_image']],
    'source check row count' => [static fn (): mixed => count($plan()['source_checks']), 6],
    'spill status' => [static fn (): mixed => $plan()['spill']['status'], 'spilled'],
    'spill target' => [static fn (): mixed => $plan()['spill']['spill_target'], 'database_pages_after_rollback_journal'],
    'spilled pages' => [static fn (): mixed => $plan()['spilled_page_numbers'], [2]],
    'spill dirty pages filtered' => [static fn (): mixed => $plan()['spill']['current']['dirty_pages'], [2]],
    'spill journaled pages filtered' => [static fn (): mixed => $plan()['spill']['current']['journaled_pages'], [2]],
    'spill pinned empty' => [static fn (): mixed => $plan()['spill']['current']['pinned_pages'], []],
    'spill next dirty empty' => [static fn (): mixed => $plan()['spill']['next']['dirty_pages'], []],
    'operation opens hot journal' => [static fn (): mixed => $plan()['operations'][0]['op'], 'open_hot_journal_for_savepoint_cache_spill'],
    'operation restores first page' => [static fn (): mixed => $plan()['operations'][1]['page'], 1],
    'operation restores fifth page' => [static fn (): mixed => $plan()['operations'][5]['page'], 5],
    'operation deletes hot journal' => [static fn (): mixed => $plan()['operations'][6]['op'], 'delete_hot_journal_after_savepoint_spill_recovery'],
    'operation defers page one' => [static fn (): mixed => $plan()['operations'][7]['reasons'], ['missing_savepoint_before_image']],
    'operation admits page two' => [static fn (): mixed => $plan()['operations'][8]['op'], 'admit_hot_journal_savepoint_cache_spill_page'],
    'operation defers stale savepoint' => [static fn (): mixed => $plan()['operations'][9]['reasons'], ['stale_savepoint_before_image_before_hot_journal_recovery']],
    'operation defers pinned page' => [static fn (): mixed => $plan()['operations'][10]['page'], 4],
    'operation defers stale current page' => [static fn (): mixed => $plan()['operations'][11]['reasons'], ['current_source_mismatch_after_hot_journal_recovery']],
    'operation defers clean page' => [static fn (): mixed => $plan()['operations'][12]['reasons'], ['cache_page_clean', 'missing_savepoint_before_image']],
    'operation promotes lock' => [static fn (): mixed => $plan()['operations'][13]['op'], 'promote_lock'],
    'operation writes admitted page' => [static fn (): mixed => $plan()['operations'][14]['page'], 2],
    'recovered database uses hot page three' => [static fn (): mixed => rtrim(substr($plan()['hot_journal_recovered_database_bytes'], (3 - 1) * $pageSize, 56), '.'), 'next151 recovered autoload index current source'],
    'spilled database writes dirty page two' => [static fn (): mixed => rtrim(substr($plan()['spilled_database_bytes'], (2 - 1) * $pageSize, 56), '.'), 'next151 dirty active_plugins after recovery'],
    'spilled database leaves stale page three recovered only' => [static fn (): mixed => rtrim(substr($plan()['spilled_database_bytes'], (3 - 1) * $pageSize, 56), '.'), 'next151 recovered autoload index current source'],
    'digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'dependency next151' => [static fn (): mixed => in_array('sqlite-pager-savepoint-cache-spill-hot-journal-current-source-next151', $plan()['dependencies'], true), true],
    'dependency next107' => [static fn (): mixed => in_array('sqlite-pager-cache-spill-journalmode-current-source-next107', $plan()['dependencies'], true), true],
    'dependency savepoint image' => [static fn (): mixed => in_array('sqlite-savepoint-page-image-rollback', $plan()['dependencies'], true), true],
    'dependency hot recovery' => [static fn (): mixed => in_array('sqlite-hot-journal-recovery-before-cache-spill', $plan()['dependencies'], true), true],
    'wal status' => [static fn (): mixed => $walPlan()['status'], 'pager_savepoint_cache_spill_hot_journal_current_source_next151'],
    'wal target' => [static fn (): mixed => $walPlan()['spill']['spill_target'], 'wal_frames'],
    'wal frame pages' => [static fn (): mixed => $walPlan()['wal_frame_pages'], [2]],
    'wal database unchanged until checkpoint' => [static fn (): mixed => $walPlan()['spill']['next']['database_image'], 'unchanged_until_checkpoint'],
    'wal append operation' => [static fn (): mixed => $walPlan()['spill']['operations'][0]['op'], 'append_wal_frame'],
    'one page limit admitted unchanged' => [static fn (): mixed => $plan(null, null, 'delete', true, 'reserved', true, 1)['admitted_page_numbers'], [2]],
    'one page limit spills page two' => [static fn (): mixed => $plan(null, null, 'delete', true, 'reserved', true, 1)['spilled_page_numbers'], [2]],
    'unsynced defers status' => [static fn (): mixed => $plan(null, null, 'delete', false)['status'], 'pager_savepoint_cache_spill_hot_journal_current_source_deferred_next151'],
    'unsynced blocked reason' => [static fn (): mixed => $plan(null, null, 'delete', false)['spill']['blocked_reasons'], ['journal_not_synced']],
    'disabled defers status' => [static fn (): mixed => $plan(null, null, 'delete', true, 'reserved', false)['status'], 'pager_savepoint_cache_spill_hot_journal_current_source_deferred_next151'],
    'disabled blocked reason' => [static fn (): mixed => $plan(null, null, 'delete', true, 'reserved', false)['spill']['blocked_reasons'], ['cache_spill_disabled']],
    'all rejected defers status' => [static fn (): mixed => $deferredPlan()['status'], 'pager_savepoint_cache_spill_hot_journal_current_source_deferred_next151'],
    'all rejected admitted empty' => [static fn (): mixed => $deferredPlan()['admitted_page_numbers'], []],
    'all rejected no eligible' => [static fn (): mixed => $deferredPlan()['spill']['blocked_reasons'], ['no_journaled_unpinned_dirty_pages']],
    'transaction savepoint admits page one' => [static fn (): mixed => $plan(null, [['page' => 1, 'image' => $dirty[1], 'current_image' => $hot[1], 'journaled' => true]], 'delete', true, 'reserved', true, null, 'wp-import-next151')['admitted_page_numbers'], [1]],
    'transaction savepoint spills page one' => [static fn (): mixed => $plan(null, [['page' => 1, 'image' => $dirty[1], 'current_image' => $hot[1], 'journaled' => true]], 'delete', true, 'reserved', true, null, 'wp-import-next151')['spilled_page_numbers'], [1]],
    'no delete operation when preserve hot journal' => [static fn (): mixed => in_array('delete_hot_journal_after_savepoint_spill_recovery', array_column(SQLitePagerSavepointCacheSpillHotJournalCurrentSourceNextPlan::plan($databasePath, $databaseBytes, $pageSize, $hot, 'plugin-batch-next151', $makeStack(), $cachePages, 8, 4, 'delete', true, 'reserved', true, null, false)['operations'], 'op'), true), false],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager savepoint cache spill hot journal current source next151 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'rejects empty database path' => static fn () => $plan(null, null, 'delete', true, 'reserved', true, null, 'plugin-batch-next151', null, null, ''),
    'rejects empty database bytes' => static fn () => $plan(null, null, 'delete', true, 'reserved', true, null, 'plugin-batch-next151', ''),
    'rejects misaligned database bytes' => static fn () => $plan(null, null, 'delete', true, 'reserved', true, null, 'plugin-batch-next151', $databaseBytes . 'x'),
    'rejects small page size' => static fn () => $plan(null, null, 'delete', true, 'reserved', true, null, 'plugin-batch-next151', null, 128),
    'rejects non power page size' => static fn () => $plan([1 => str_pad('hot', 768, '.')], [['page' => 1, 'image' => str_pad('dirty', 768, '.')]], 'delete', true, 'reserved', true, null, 'plugin-batch-next151', str_pad('db', 768, '.') . str_pad('db2', 768, '.'), 768),
    'rejects empty hot pages' => static fn () => $plan([]),
    'rejects empty savepoint' => static fn () => $plan(null, null, 'delete', true, 'reserved', true, null, ''),
    'rejects empty cache pages' => static fn () => $plan(null, []),
    'rejects zero hot page' => static fn () => $plan([0 => $hot[1]]),
    'rejects outside hot page' => static fn () => $plan([7 => $hot[1]]),
    'rejects short hot image' => static fn () => $plan([1 => 'short']),
    'rejects zero cache page' => static fn () => $plan(null, [['page' => 0, 'image' => $dirty[1]]]),
    'rejects duplicate cache page' => static fn () => $plan(null, [['page' => 2, 'image' => $dirty[2]], ['page' => 2, 'image' => $dirty[2]]]),
    'rejects outside cache page' => static fn () => $plan(null, [['page' => 7, 'image' => $dirty[2]]]),
    'rejects short cache image' => static fn () => $plan(null, [['page' => 2, 'image' => 'short']]),
    'rejects short current image' => static fn () => $plan(null, [['page' => 2, 'image' => $dirty[2], 'current_image' => 'short']]),
    'rejects bad cache bytes' => static fn () => $plan(null, [['page' => 2, 'image' => $dirty[2], 'bytes' => -1]]),
    'rejects bad wal frame' => static fn () => $plan(null, [['page' => 2, 'image' => $dirty[2], 'walFrame' => 0]]),
    'rejects missing savepoint' => static fn () => $plan(null, null, 'delete', true, 'reserved', true, null, 'missing-next151'),
    'rejects bad journal mode' => static fn () => $plan(null, null, 'bad'),
];

foreach ($throws as $name => $callback) {
    $tests['pager savepoint cache spill hot journal current source next151 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
