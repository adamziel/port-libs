<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerSavepointWalCacheRecoveryCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSavepointStack;

$tests = [];

$pageSize = 512;
$page = static fn (string $label, string $pad = '.'): string => str_pad($label, $pageSize, $pad, STR_PAD_RIGHT);

$base = [
    1 => $page('next133 base sqlite header'),
    2 => $page('next133 base wp_options active_plugins'),
    3 => $page('next133 base wp_options plugin settings'),
    4 => $page('next133 base transient timeout row'),
    5 => $page('next133 base autoload index leaf'),
];
$databaseBytes = implode('', $base);
$walFrames = [
    1 => ['page' => 1, 'image' => $page('next133 retained wal schema cookie frame'), 'commit_frame' => true],
    2 => ['page' => 2, 'image' => $page('next133 discarded active_plugins frame')],
    3 => ['page' => 3, 'image' => $page('next133 discarded plugin settings frame')],
    4 => ['page' => 4, 'image' => $page('next133 discarded transient timeout frame')],
    5 => ['page' => 2, 'image' => $page('next133 discarded active_plugins retry frame'), 'commit_frame' => true],
];

$makeStack = static function (): SQLiteSavepointStack {
    $stack = new SQLiteSavepointStack();
    $stack->beginTransaction('wordpress-import');
    $stack->recordWalFrameWrite(1, 1, true);
    $stack->savepoint('plugin-options');
    $stack->recordWalFrameWrite(2, 2);
    $stack->recordWalFrameWrite(3, 3);
    $stack->savepoint('transient-cache');
    $stack->recordWalFrameWrite(4, 4);
    $stack->recordWalFrameWrite(5, 2, true);

    return $stack;
};

$cachePages = [
    1 => ['image' => $walFrames[1]['image'], 'frame' => 1, 'source' => 'wal-cache-retained-frame', 'dirty' => false],
    2 => ['image' => $walFrames[5]['image'], 'frame' => 5, 'source' => 'wal-cache-discarded-commit-frame', 'dirty' => true],
    3 => ['image' => $walFrames[3]['image'], 'frame' => 3, 'source' => 'wal-cache-discarded-plugin-frame', 'dirty' => true],
    4 => ['image' => $walFrames[4]['image'], 'frame' => 4, 'source' => 'wal-cache-discarded-transient-frame', 'dirty' => true],
    5 => ['image' => $base[5], 'frame' => 0, 'source' => 'database-cache-base-image', 'dirty' => false],
];

$plan = static fn (
    ?array $cache = null,
    ?array $frames = null,
    ?array $reads = null,
    bool $refresh = true,
    string $savepoint = 'plugin-options',
    ?string $db = null,
    ?int $size = null,
): array => SQLitePagerSavepointWalCacheRecoveryCurrentSourceNextPlan::plan(
    $db ?? $databaseBytes,
    $size ?? $pageSize,
    $savepoint,
    $makeStack(),
    $cache ?? $cachePages,
    $frames ?? $walFrames,
    $reads ?? [1, 2, 3, 4, 5],
    $refresh
);

$cases = [
    'status' => [static fn (): mixed => $plan()['status'], 'pager-savepoint-wal-cache-recovery-current-source-next133'],
    'reason' => [static fn (): mixed => $plan()['reason'], 'rollback_to_savepoint_refreshes_cache_pages_sourced_from_discarded_wal_frames'],
    'savepoint' => [static fn (): mixed => $plan()['savepoint'], 'plugin-options'],
    'page size' => [static fn (): mixed => $plan()['page_size'], $pageSize],
    'rollback frame' => [static fn (): mixed => $plan()['rollback_to_frame'], 1],
    'discarded frame count' => [static fn (): mixed => count($plan()['discarded_wal_frames']), 4],
    'discarded pages' => [static fn (): mixed => $plan()['discarded_page_numbers'], [2, 3, 4]],
    'stale pages' => [static fn (): mixed => $plan()['stale_cache_page_numbers'], [2, 3, 4]],
    'refreshed pages' => [static fn (): mixed => $plan()['refreshed_cache_page_numbers'], [2, 3, 4]],
    'retained pages' => [static fn (): mixed => $plan()['retained_cache_page_numbers'], [1, 5]],
    'blocked pages' => [static fn (): mixed => $plan()['blocked_cache_page_numbers'], []],
    'current source verified' => [static fn (): mixed => $plan()['current_source_verified'], true],
    'cache row count' => [static fn (): mixed => count($plan()['cache_rows']), 5],
    'row one retained frame' => [static fn (): mixed => $plan()['cache_rows'][0]['source_frame'], 1],
    'row one retained source' => [static fn (): mixed => $plan()['cache_rows'][0]['source_after'], 'wal-cache-retained-frame'],
    'row one not stale' => [static fn (): mixed => $plan()['cache_rows'][0]['stale_before_refresh'], false],
    'row one prefix' => [static fn (): mixed => $plan()['cache_rows'][0]['after_prefix'], 'next133 retained wal schema cookie frame'],
    'row two stale' => [static fn (): mixed => $plan()['cache_rows'][1]['stale_before_refresh'], true],
    'row two refreshed' => [static fn (): mixed => $plan()['cache_rows'][1]['refreshed'], true],
    'row two source frame base' => [static fn (): mixed => $plan()['cache_rows'][1]['source_frame'], 0],
    'row two before discarded retry' => [static fn (): mixed => $plan()['cache_rows'][1]['before_prefix'], 'next133 discarded active_plugins retry frame'],
    'row two current base' => [static fn (): mixed => $plan()['cache_rows'][1]['current_prefix'], 'next133 base wp_options active_plugins'],
    'row two after base' => [static fn (): mixed => $plan()['cache_rows'][1]['after_prefix'], 'next133 base wp_options active_plugins'],
    'row two source refreshed' => [static fn (): mixed => $plan()['cache_rows'][1]['source_after'], 'savepoint-rollback-refreshed-wal-cache'],
    'row two matches current' => [static fn (): mixed => $plan()['cache_rows'][1]['matches_current_source_after'], true],
    'row three source frame base' => [static fn (): mixed => $plan()['cache_rows'][2]['source_frame'], 0],
    'row three after base' => [static fn (): mixed => $plan()['cache_rows'][2]['after_prefix'], 'next133 base wp_options plugin settings'],
    'row four after base' => [static fn (): mixed => $plan()['cache_rows'][3]['after_prefix'], 'next133 base transient timeout row'],
    'row five base retained frame' => [static fn (): mixed => $plan()['cache_rows'][4]['source_frame'], 0],
    'row five base retained' => [static fn (): mixed => $plan()['cache_rows'][4]['stale_before_refresh'], false],
    'read row count' => [static fn (): mixed => count($plan()['read_rows']), 5],
    'read one cache hit' => [static fn (): mixed => $plan()['read_rows'][0]['cache_hit'], true],
    'read one source frame' => [static fn (): mixed => $plan()['read_rows'][0]['source_frame'], 1],
    'read two cache hit after refresh' => [static fn (): mixed => $plan()['read_rows'][1]['cache_hit'], true],
    'read two prefix' => [static fn (): mixed => $plan()['read_rows'][1]['prefix'], 'next133 base wp_options active_plugins'],
    'read three prefix' => [static fn (): mixed => $plan()['read_rows'][2]['prefix'], 'next133 base wp_options plugin settings'],
    'read four prefix' => [static fn (): mixed => $plan()['read_rows'][3]['prefix'], 'next133 base transient timeout row'],
    'read five prefix' => [static fn (): mixed => $plan()['read_rows'][4]['prefix'], 'next133 base autoload index leaf'],
    'operation first rollback' => [static fn (): mixed => $plan()['operations'][0]['op'], 'rollback_to_savepoint_wal_prefix'],
    'operation first rollback frame' => [static fn (): mixed => $plan()['operations'][0]['rollback_to_frame'], 1],
    'operation retain page one' => [static fn (): mixed => $plan()['operations'][1]['op'], 'retain_wal_cache_page_after_savepoint_rollback'],
    'operation refresh page two' => [static fn (): mixed => $plan()['operations'][2]['op'], 'refresh_wal_cache_page_after_savepoint_rollback'],
    'operation refresh page four' => [static fn (): mixed => $plan()['operations'][4]['page_number'], 4],
    'operation read page one' => [static fn (): mixed => $plan()['operations'][6]['op'], 'read_page_after_savepoint_wal_cache_recovery'],
    'operation read page five' => [static fn (): mixed => $plan()['operations'][10]['page_number'], 5],
    'digest length' => [static fn (): mixed => strlen($plan()['source_digest']), 64],
    'dependency slice' => [static fn (): mixed => in_array('sqlite-pager-savepoint-wal-cache-recovery-current-source-next133', $plan()['dependencies'], true), true],
    'dependency rollback prefix' => [static fn (): mixed => in_array('sqlite-savepoint-wal-rollback-prefix', $plan()['dependencies'], true), true],
    'dependency current source' => [static fn (): mixed => in_array('sqlite-pager-cache-current-source-validation', $plan()['dependencies'], true), true],
    'read pages sorted unique' => [static fn (): mixed => array_column($plan(null, null, [5, 2, 2, 1])['read_rows'], 'page_number'), [1, 2, 5]],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager savepoint wal cache recovery current source next133 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$blockedCases = [
    'blocked status' => [static fn (): mixed => $plan(null, null, null, false)['status'], 'pager-savepoint-wal-cache-recovery-blocked-current-source-next133'],
    'blocked reason' => [static fn (): mixed => $plan(null, null, null, false)['reason'], 'stale_wal_cache_pages_remain_after_savepoint_rollback'],
    'blocked current source false' => [static fn (): mixed => $plan(null, null, null, false)['current_source_verified'], false],
    'blocked page numbers' => [static fn (): mixed => $plan(null, null, null, false)['blocked_cache_page_numbers'], [2, 3, 4]],
    'blocked refresh pages empty' => [static fn (): mixed => $plan(null, null, null, false)['refreshed_cache_page_numbers'], []],
    'blocked row two remains stale' => [static fn (): mixed => $plan(null, null, null, false)['cache_rows'][1]['matches_current_source_after'], false],
    'blocked row two source' => [static fn (): mixed => $plan(null, null, null, false)['cache_rows'][1]['source_after'], 'stale-savepoint-wal-cache-blocked'],
    'blocked operation blocks page two' => [static fn (): mixed => $plan(null, null, null, false)['operations'][2]['op'], 'block_stale_wal_cache_page_after_savepoint_rollback'],
    'nested savepoint rolls back to frame three' => [static fn (): mixed => $plan(null, null, null, true, 'transient-cache')['rollback_to_frame'], 3],
    'nested savepoint stale page four only' => [static fn (): mixed => $plan(null, null, null, true, 'transient-cache')['stale_cache_page_numbers'], [2, 4]],
    'nested savepoint refreshes page two to frame two' => [static fn (): mixed => $plan(null, null, null, true, 'transient-cache')['cache_rows'][1]['source_frame'], 2],
    'nested savepoint refreshes page four to base' => [static fn (): mixed => $plan(null, null, null, true, 'transient-cache')['cache_rows'][3]['source_frame'], 0],
    'single stale mismatch on retained frame refreshes' => [static fn (): mixed => $plan([1 => ['image' => $base[1], 'frame' => 1]])['stale_cache_page_numbers'], [1]],
    'single stale mismatch after prefix retained wal' => [static fn (): mixed => $plan([1 => ['image' => $base[1], 'frame' => 1]])['cache_rows'][0]['after_prefix'], 'next133 retained wal schema cookie frame'],
];

foreach ($blockedCases as $name => [$callback, $expected]) {
    $tests['pager savepoint wal cache recovery current source next133 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'rejects empty database' => static fn () => $plan(null, null, null, true, 'plugin-options', ''),
    'rejects unaligned database' => static fn () => $plan(null, null, null, true, 'plugin-options', $databaseBytes . 'x'),
    'rejects bad page size' => static fn () => $plan(null, null, null, true, 'plugin-options', null, 0),
    'rejects empty savepoint' => static fn () => $plan(null, null, null, true, ''),
    'rejects empty cache' => static fn () => $plan([]),
    'rejects empty frames' => static fn () => $plan(null, []),
    'rejects empty reads' => static fn () => $plan(null, null, []),
    'rejects bad cache page' => static fn () => $plan([0 => ['image' => $base[1], 'frame' => 0]]),
    'rejects short cache image' => static fn () => $plan([1 => ['image' => 'short', 'frame' => 0]]),
    'rejects bad cache frame' => static fn () => $plan([1 => ['image' => $base[1], 'frame' => -1]]),
    'rejects bad wal frame index' => static fn () => $plan(null, [0 => ['page' => 1, 'image' => $base[1]]]),
    'rejects wal frame outside database' => static fn () => $plan(null, [1 => ['page' => 6, 'image' => $base[1]]]),
    'rejects short wal frame image' => static fn () => $plan(null, [1 => ['page' => 1, 'image' => 'short']]),
    'rejects bad read page' => static fn () => $plan(null, null, [1, 0]),
    'rejects cache outside database' => static fn () => $plan([6 => ['image' => $base[1], 'frame' => 0]]),
    'rejects read outside database' => static fn () => $plan(null, null, [6]),
    'rejects missing savepoint' => static fn () => $plan(null, null, null, true, 'missing-savepoint'),
];

foreach ($throws as $name => $callback) {
    $tests['pager savepoint wal cache recovery current source next133 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
