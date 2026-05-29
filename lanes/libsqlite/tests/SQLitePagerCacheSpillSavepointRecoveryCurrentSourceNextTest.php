<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerCacheSpillSavepointRecoveryCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSavepointStack;

$tests = [];

$pageSize = 512;
$page = static fn (string $label, string $pad = '.'): string => str_pad($label, $pageSize, $pad, STR_PAD_RIGHT);

$before1 = $page('next120 before schema page');
$before2 = $page('next120 before wp_options active_plugins');
$before3 = $page('next120 before wp_options plugin settings');
$before4 = $page('next120 before autoload index leaf');
$before5 = $page('next120 before transient cache row');
$spilled2 = $page('next120 spilled active_plugins cache page');
$spilled3 = $page('next120 spilled plugin settings cache page');
$spilled5 = $page('next120 spilled transient cache page');
$databaseBytes = $before1 . $before2 . $before3 . $before4 . $before5;

$makeStack = static function () use ($before1, $before2, $before3, $before5): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wp-options-import');
    $stack->recordPageImageWrite(1, $before1);
    $stack->savepoint('plugin-settings');
    $stack->recordPageImageWrite(2, $before2);
    $stack->recordPageImageWrite(3, $before3);
    $stack->savepoint('transient-cache');
    $stack->recordPageImageWrite(5, $before5);

    return $stack;
};

$cachePages = [
    ['page' => 2, 'image' => $spilled2, 'bytes' => $pageSize, 'journaled' => true],
    ['page' => 3, 'image' => $spilled3, 'bytes' => $pageSize, 'journaled' => true],
    ['page' => 4, 'image' => $page('next120 pinned autoload index dirty page'), 'bytes' => $pageSize, 'journaled' => true, 'pinned' => true],
    ['page' => 5, 'image' => $spilled5, 'bytes' => $pageSize, 'journaled' => true],
];

$plan = static fn (
    ?array $pages = null,
    bool $journalSynced = true,
    string $lockState = 'reserved',
    bool $cacheSpillEnabled = true,
    ?int $maxSpillPages = 3,
    ?string $db = null,
    string $savepoint = 'plugin-settings',
): array => SQLitePagerCacheSpillSavepointRecoveryCurrentSourceNextPlan::currentSourceNext(
    $db ?? $databaseBytes,
    $pageSize,
    $savepoint,
    $makeStack(),
    $pages ?? $cachePages,
    8,
    3,
    $journalSynced,
    $lockState,
    $cacheSpillEnabled,
    $maxSpillPages
);

$cases = [
    'status' => static fn (): mixed => $plan()['status'],
    'reason' => static fn (): mixed => $plan()['reason'],
    'savepoint' => static fn (): mixed => $plan()['savepoint'],
    'page size' => static fn (): mixed => $plan()['page_size'],
    'current source verified' => static fn (): mixed => $plan()['current_source_verified'],
    'current source mismatches' => static fn (): mixed => $plan()['current_source_mismatch_pages'],
    'spill status' => static fn (): mixed => $plan()['spill']['status'],
    'spill current lock' => static fn (): mixed => $plan()['spill']['current']['lock'],
    'spill next lock' => static fn (): mixed => $plan()['spill']['next']['lock'],
    'spilled page numbers' => static fn (): mixed => $plan()['spilled_page_numbers'],
    'restored spilled page numbers' => static fn (): mixed => $plan()['restored_spilled_page_numbers'],
    'spill survived page numbers' => static fn (): mixed => $plan()['spill_survived_page_numbers'],
    'unspilled dirty page numbers' => static fn (): mixed => $plan()['unspilled_dirty_page_numbers'],
    'rollback page numbers' => static fn (): mixed => $plan()['rollback_page_numbers'],
    'rollback restored page numbers' => static fn (): mixed => $plan()['rollback_restored_page_numbers'],
    'rollback missing page numbers' => static fn (): mixed => $plan()['rollback_missing_page_numbers'],
    'page two spilled prefix' => static fn (): mixed => $plan()['page_sources'][2]['spilled_prefix'],
    'page two rolled back prefix' => static fn (): mixed => $plan()['page_sources'][2]['rolled_back_prefix'],
    'page two spill matches cache' => static fn (): mixed => $plan()['page_sources'][2]['spill_matches_cache_image'],
    'page two rollback matches before' => static fn (): mixed => $plan()['page_sources'][2]['rollback_matches_before_image'],
    'page two rollback not spilled' => static fn (): mixed => $plan()['page_sources'][2]['rollback_matches_spilled_image'],
    'page three rolled back prefix' => static fn (): mixed => $plan()['page_sources'][3]['rolled_back_prefix'],
    'page four was pinned' => static fn (): mixed => $plan()['page_sources'][4]['was_pinned'],
    'page four not spilled' => static fn (): mixed => $plan()['page_sources'][4]['was_spilled'],
    'page four remains before image' => static fn (): mixed => $plan()['page_sources'][4]['rolled_back_prefix'],
    'page five restored nested image' => static fn (): mixed => $plan()['page_sources'][5]['rolled_back_prefix'],
    'spilled database has page two cache bytes' => static fn (): mixed => substr($plan()['spilled_database_bytes'], $pageSize, $pageSize),
    'rolled back database restores page two' => static fn (): mixed => substr($plan()['rolled_back_database_bytes'], $pageSize, $pageSize),
    'rolled back database restores page three' => static fn (): mixed => substr($plan()['rolled_back_database_bytes'], $pageSize * 2, $pageSize),
    'rolled back database restores page five' => static fn (): mixed => substr($plan()['rolled_back_database_bytes'], $pageSize * 4, $pageSize),
    'operation promotes lock' => static fn (): mixed => $plan()['operations'][0]['op'],
    'operation first spill write' => static fn (): mixed => $plan()['operations'][1]['page'],
    'operation second spill mark clean' => static fn (): mixed => $plan()['operations'][4]['op'],
    'operation apply spilled image' => static fn (): mixed => $plan()['operations'][7]['op'],
    'operation restore first spilled page' => static fn (): mixed => $plan()['operations'][10]['op'],
    'operation restore savepoint' => static fn (): mixed => $plan()['operations'][10]['savepoint'],
    'dependency includes next120' => static fn (): mixed => in_array('sqlite-pager-cache-spill-savepoint-recovery-current-source-next120', $plan()['dependencies'], true),
    'dependency includes savepoint image rollback' => static fn (): mixed => in_array('sqlite-savepoint-page-image-rollback', $plan()['dependencies'], true),
    'dependency includes cache spill' => static fn (): mixed => in_array('sqlite-pager-cache-spill-current-next71', $plan()['dependencies'], true),
];

$expected = [
    'status' => 'pager_cache_spill_savepoint_recovery_current_source_next120',
    'reason' => 'rollback_to_restores_savepoint_images_after_cache_spill',
    'savepoint' => 'plugin-settings',
    'page size' => $pageSize,
    'current source verified' => true,
    'current source mismatches' => [],
    'spill status' => 'spilled',
    'spill current lock' => 'reserved',
    'spill next lock' => 'exclusive',
    'spilled page numbers' => [2, 3, 5],
    'restored spilled page numbers' => [2, 3, 5],
    'spill survived page numbers' => [],
    'unspilled dirty page numbers' => [4],
    'rollback page numbers' => [2, 3, 5],
    'rollback restored page numbers' => [2, 3, 5],
    'rollback missing page numbers' => [],
    'page two spilled prefix' => 'next120 spilled active_plugins cache page',
    'page two rolled back prefix' => 'next120 before wp_options active_plugins',
    'page two spill matches cache' => true,
    'page two rollback matches before' => true,
    'page two rollback not spilled' => false,
    'page three rolled back prefix' => 'next120 before wp_options plugin settings',
    'page four was pinned' => true,
    'page four not spilled' => false,
    'page four remains before image' => 'next120 before autoload index leaf',
    'page five restored nested image' => 'next120 before transient cache row',
    'spilled database has page two cache bytes' => $spilled2,
    'rolled back database restores page two' => $before2,
    'rolled back database restores page three' => $before3,
    'rolled back database restores page five' => $before5,
    'operation promotes lock' => 'promote_lock',
    'operation first spill write' => 2,
    'operation second spill mark clean' => 'mark_page_clean_in_cache',
    'operation apply spilled image' => 'spill_dirty_page_to_database_image',
    'operation restore first spilled page' => 'restore_spilled_page_from_savepoint_image',
    'operation restore savepoint' => 'plugin-settings',
    'dependency includes next120' => true,
    'dependency includes savepoint image rollback' => true,
    'dependency includes cache spill' => true,
];

foreach ($cases as $name => $callback) {
    $tests['pager cache spill savepoint recovery current source next120 ' . $name] = static function (TestRunner $t) use ($callback, $expected, $name): void {
        $t->same($expected[$name], $callback());
    };
}

$blockedCases = [
    'unsynced status' => [static fn (): mixed => $plan(null, false)['status'], 'pager_cache_spill_savepoint_recovery_blocked_next120'],
    'unsynced spill deferred' => [static fn (): mixed => $plan(null, false)['spill']['status'], 'deferred'],
    'unsynced reason' => [static fn (): mixed => $plan(null, false)['spill']['blocked_reasons'], ['journal_not_synced']],
    'shared lock status' => [static fn (): mixed => $plan(null, true, 'shared')['status'], 'pager_cache_spill_savepoint_recovery_blocked_next120'],
    'shared lock reason' => [static fn (): mixed => $plan(null, true, 'shared')['spill']['blocked_reasons'], ['exclusive_lock_unavailable']],
    'disabled spill status' => [static fn (): mixed => $plan(null, true, 'reserved', false)['status'], 'pager_cache_spill_savepoint_recovery_blocked_next120'],
    'disabled spill reason' => [static fn (): mixed => $plan(null, true, 'reserved', false)['spill']['blocked_reasons'], ['cache_spill_disabled']],
    'one page limit spills only first page' => [static fn (): mixed => $plan(null, true, 'reserved', true, 1)['spilled_page_numbers'], [2]],
    'one page limit restores first page' => [static fn (): mixed => $plan(null, true, 'reserved', true, 1)['restored_spilled_page_numbers'], [2]],
    'one page limit leaves other dirty pages' => [static fn (): mixed => $plan(null, true, 'reserved', true, 1)['unspilled_dirty_page_numbers'], [3, 4, 5]],
    'stale current source status' => [static fn (): mixed => $plan([['page' => 2, 'image' => $spilled2, 'bytes' => $pageSize, 'journaled' => true, 'current_image' => $page('next120 stale current source')]])['status'], 'pager_cache_spill_savepoint_recovery_blocked_next120'],
    'stale current source mismatch page' => [static fn (): mixed => $plan([['page' => 2, 'image' => $spilled2, 'bytes' => $pageSize, 'journaled' => true, 'current_image' => $page('next120 stale current source')]])['current_source_mismatch_pages'], [2]],
    'transaction savepoint restores root page' => [static fn (): mixed => $plan([['page' => 1, 'image' => $page('next120 spilled schema root'), 'bytes' => $pageSize, 'journaled' => true]], true, 'reserved', true, null, null, 'wp-options-import')['restored_spilled_page_numbers'], [1]],
    'transaction savepoint rolled back prefix' => [static fn (): mixed => $plan([['page' => 1, 'image' => $page('next120 spilled schema root'), 'bytes' => $pageSize, 'journaled' => true]], true, 'reserved', true, null, null, 'wp-options-import')['page_sources'][1]['rolled_back_prefix'], 'next120 before schema page'],
];

foreach ($blockedCases as $name => [$callback, $expectedValue]) {
    $tests['pager cache spill savepoint recovery current source next120 ' . $name] = static function (TestRunner $t) use ($callback, $expectedValue): void {
        $t->same($expectedValue, $callback());
    };
}

$throws = [
    'rejects empty database' => static fn () => SQLitePagerCacheSpillSavepointRecoveryCurrentSourceNextPlan::currentSourceNext('', $pageSize, 'plugin-settings', $makeStack(), $cachePages, 8, 3),
    'rejects unaligned database' => static fn () => SQLitePagerCacheSpillSavepointRecoveryCurrentSourceNextPlan::currentSourceNext($databaseBytes . 'x', $pageSize, 'plugin-settings', $makeStack(), $cachePages, 8, 3),
    'rejects bad page size' => static fn () => SQLitePagerCacheSpillSavepointRecoveryCurrentSourceNextPlan::currentSourceNext($databaseBytes, 0, 'plugin-settings', $makeStack(), $cachePages, 8, 3),
    'rejects empty savepoint' => static fn () => SQLitePagerCacheSpillSavepointRecoveryCurrentSourceNextPlan::currentSourceNext($databaseBytes, $pageSize, '', $makeStack(), $cachePages, 8, 3),
    'rejects empty cache pages' => static fn () => SQLitePagerCacheSpillSavepointRecoveryCurrentSourceNextPlan::currentSourceNext($databaseBytes, $pageSize, 'plugin-settings', $makeStack(), [], 8, 3),
    'rejects bad page number' => static fn () => $plan([['page' => 0, 'image' => $spilled2]]),
    'rejects duplicate page' => static fn () => $plan([['page' => 2, 'image' => $spilled2], ['page' => 2, 'image' => $spilled3]]),
    'rejects page outside database' => static fn () => $plan([['page' => 6, 'image' => $spilled2]]),
    'rejects short cache image' => static fn () => $plan([['page' => 2, 'image' => 'short']]),
    'rejects short current image' => static fn () => $plan([['page' => 2, 'image' => $spilled2, 'current_image' => 'short']]),
    'rejects missing savepoint' => static fn () => $plan(null, true, 'reserved', true, null, null, 'missing-savepoint'),
    'rejects invalid lock state' => static fn () => $plan(null, true, 'bad-lock'),
];

foreach ($throws as $name => $callback) {
    $tests['pager cache spill savepoint recovery current source next120 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
