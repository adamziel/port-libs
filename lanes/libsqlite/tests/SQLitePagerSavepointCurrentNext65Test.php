<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePagerSavepointCurrentNextPlan;

$tests = [];

$events = [
    ['op' => 'begin', 'name' => 'wp_import'],
    ['op' => 'page_write', 'page' => 2],
    ['op' => 'wal_frame', 'frame' => 1, 'page' => 2],
    ['op' => 'savepoint', 'name' => 'plugin_a'],
    ['op' => 'page_image_write', 'page' => 4, 'image' => str_repeat('A', 32)],
    ['op' => 'wal_frame', 'frame' => 2, 'page' => 4],
    ['op' => 'savepoint', 'name' => 'plugin_b'],
    ['op' => 'page_write', 'page' => 5],
    ['op' => 'wal_frame', 'frame' => 3, 'page' => 5, 'commit' => true],
];

$rollback = static fn (): array => SQLitePagerSavepointCurrentNextPlan::currentNext($events, ['op' => 'rollback_to', 'name' => 'plugin_a']);
$release = static fn (): array => SQLitePagerSavepointCurrentNextPlan::currentNext($events, ['op' => 'release', 'name' => 'plugin_b']);
$savepoint = static fn (): array => SQLitePagerSavepointCurrentNextPlan::currentNext($events, ['op' => 'savepoint', 'name' => 'plugin_c']);
$commit = static fn (): array => SQLitePagerSavepointCurrentNextPlan::currentNext($events, ['op' => 'commit']);
$outerRelease = static fn (): array => SQLitePagerSavepointCurrentNextPlan::currentNext([['op' => 'begin', 'name' => 'wp_import'], ['op' => 'page_write', 'page' => 2]], ['op' => 'release', 'name' => 'wp_import']);
$implicit = static fn (): array => SQLitePagerSavepointCurrentNextPlan::currentNext([], ['op' => 'savepoint', 'name' => 'implicit_wp']);
$rollbackAll = static fn (): array => SQLitePagerSavepointCurrentNextPlan::currentNext($events, ['op' => 'rollback']);

$cases = [
    'rollback status' => [static fn (): mixed => $rollback()['status'], 'rolled_back_to_savepoint'],
    'rollback action' => [static fn (): mixed => $rollback()['action'], 'rollback_to'],
    'rollback current active' => [static fn (): mixed => $rollback()['current']['active'], true],
    'rollback current depth' => [static fn (): mixed => $rollback()['current']['depth'], 3],
    'rollback current names' => [static fn (): mixed => $rollback()['current']['names'], ['wp_import', 'plugin_a', 'plugin_b']],
    'rollback current pending pages' => [static fn (): mixed => $rollback()['current']['pending_pages'], [2, 4, 5]],
    'rollback current pending wal frames' => [static fn (): mixed => $rollback()['current']['pending_wal_frames'], [1, 2, 3]],
    'rollback next keeps target open' => [static fn (): mixed => $rollback()['next']['names'], ['wp_import', 'plugin_a']],
    'rollback next depth' => [static fn (): mixed => $rollback()['next']['depth'], 2],
    'rollback next pending pages keep outer' => [static fn (): mixed => $rollback()['next']['pending_pages'], [2]],
    'rollback next wal frames keep outer' => [static fn (): mixed => $rollback()['next']['pending_wal_frames'], [1]],
    'rollback transition savepoint' => [static fn (): mixed => $rollback()['transition']['savepoint'], 'plugin_a'],
    'rollback discarded frame names' => [static fn (): mixed => $rollback()['transition']['discarded_frame_names'], ['plugin_b']],
    'rollback page numbers include target and children' => [static fn (): mixed => $rollback()['transition']['rollback_page_numbers'], [4, 5]],
    'rollback target frame cleared' => [static fn (): mixed => $rollback()['transition']['target_frame_cleared'], true],
    'rollback transaction remains active' => [static fn (): mixed => $rollback()['transition']['transaction_active_after'], true],
    'rollback pager journal action' => [static fn (): mixed => $rollback()['pager']['journal_action'], 'restore_savepoint_pages'],
    'rollback pager cache action' => [static fn (): mixed => $rollback()['pager']['dirty_cache_action'], 'clear_rolled_back_pages'],
    'rollback lock remains reserved' => [static fn (): mixed => $rollback()['pager']['lock_after'], 'reserved'],
    'rollback first operation' => [static fn (): mixed => $rollback()['operations'][0]['op'], 'restore_savepoint_pages'],
    'rollback operation pages' => [static fn (): mixed => $rollback()['operations'][0]['pages'], [4, 5]],
    'rollback second operation' => [static fn (): mixed => $rollback()['operations'][1]['op'], 'discard_nested_savepoints'],
    'rollback operation savepoints' => [static fn (): mixed => $rollback()['operations'][1]['savepoints'], ['plugin_b']],
    'release status nested' => [static fn (): mixed => $release()['status'], 'savepoint_released'],
    'release next names' => [static fn (): mixed => $release()['next']['names'], ['wp_import', 'plugin_a']],
    'release next pending pages merged' => [static fn (): mixed => $release()['next']['pending_pages'], [2, 4, 5]],
    'release next wal frames merged' => [static fn (): mixed => $release()['next']['pending_wal_frames'], [1, 2, 3]],
    'release frame names' => [static fn (): mixed => $release()['transition']['released_frame_names'], ['plugin_b']],
    'release merged pages' => [static fn (): mixed => $release()['transition']['merged_page_numbers'], [5]],
    'release target not transaction' => [static fn (): mixed => $release()['transition']['target_is_transaction'], false],
    'release result depth' => [static fn (): mixed => $release()['transition']['result_depth'], 2],
    'release pager journal action' => [static fn (): mixed => $release()['pager']['journal_action'], 'merge_savepoint_journal'],
    'release cache action' => [static fn (): mixed => $release()['pager']['dirty_cache_action'], 'merge_dirty_pages_to_parent'],
    'release operation keeps transaction' => [static fn (): mixed => $release()['operations'][1]['op'], 'keep_transaction_open'],
    'new savepoint status' => [static fn (): mixed => $savepoint()['status'], 'savepoint_opened'],
    'new savepoint next names' => [static fn (): mixed => $savepoint()['next']['names'], ['wp_import', 'plugin_a', 'plugin_b', 'plugin_c']],
    'new savepoint preserves pending pages' => [static fn (): mixed => $savepoint()['next']['pending_pages'], [2, 4, 5]],
    'new savepoint journal action' => [static fn (): mixed => $savepoint()['pager']['journal_action'], 'open_or_reuse_statement_journal'],
    'new savepoint is nested' => [static fn (): mixed => $savepoint()['transition']['created_transaction'], false],
    'implicit savepoint opens transaction' => [static fn (): mixed => $implicit()['transition']['created_transaction'], true],
    'implicit savepoint next active' => [static fn (): mixed => $implicit()['next']['active'], true],
    'implicit savepoint next depth' => [static fn (): mixed => $implicit()['next']['depth'], 1],
    'commit status' => [static fn (): mixed => $commit()['status'], 'transaction_committed'],
    'commit next inactive' => [static fn (): mixed => $commit()['next']['active'], false],
    'commit next pages clear' => [static fn (): mixed => $commit()['next']['pending_pages'], []],
    'commit pages' => [static fn (): mixed => $commit()['transition']['committed_page_numbers'], [2, 4, 5]],
    'commit frame names' => [static fn (): mixed => $commit()['transition']['committed_frame_names'], ['wp_import', 'plugin_a', 'plugin_b']],
    'commit releases savepoints' => [static fn (): mixed => $commit()['transition']['released_savepoint_count'], 2],
    'commit pager journal action' => [static fn (): mixed => $commit()['pager']['journal_action'], 'finalize_transaction_journal'],
    'commit lock none' => [static fn (): mixed => $commit()['pager']['lock_after'], 'none'],
    'outer release commits transaction' => [static fn (): mixed => $outerRelease()['status'], 'transaction_committed_by_release'],
    'outer release target transaction' => [static fn (): mixed => $outerRelease()['transition']['target_is_transaction'], true],
    'outer release next inactive' => [static fn (): mixed => $outerRelease()['next']['active'], false],
    'rollback all status' => [static fn (): mixed => $rollbackAll()['status'], 'transaction_rolled_back'],
    'rollback all pages' => [static fn (): mixed => $rollbackAll()['transition']['rollback_page_numbers'], [2, 4, 5]],
    'rollback all next inactive' => [static fn (): mixed => $rollbackAll()['next']['active'], false],
    'dependencies include pager savepoint' => [static fn (): mixed => in_array('sqlite-pager-savepoint-current-next', $rollback()['dependencies'], true), true],
    'dependencies include savepoint stack' => [static fn (): mixed => in_array('sqlite-savepoint-stack-state', $rollback()['dependencies'], true), true],
    'operation trims uppercase' => [static fn (): mixed => SQLitePagerSavepointCurrentNextPlan::currentNext([], ['op' => ' SAVEPOINT ', 'name' => 'x'])['action'], 'savepoint'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['pager savepoint current next65 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

$throws = [
    'rejects empty event operation' => static fn () => SQLitePagerSavepointCurrentNextPlan::currentNext([['op' => '']], ['op' => 'commit']),
    'rejects unsupported event' => static fn () => SQLitePagerSavepointCurrentNextPlan::currentNext([['op' => 'vacuum']], ['op' => 'commit']),
    'rejects bad page write' => static fn () => SQLitePagerSavepointCurrentNextPlan::currentNext([['op' => 'page_write', 'page' => 0]], ['op' => 'commit']),
    'rejects bad wal frame' => static fn () => SQLitePagerSavepointCurrentNextPlan::currentNext([['op' => 'wal_frame', 'frame' => 0, 'page' => 1]], ['op' => 'commit']),
    'rejects bad wal page' => static fn () => SQLitePagerSavepointCurrentNextPlan::currentNext([['op' => 'wal_frame', 'frame' => 1, 'page' => 0]], ['op' => 'commit']),
    'rejects empty image' => static fn () => SQLitePagerSavepointCurrentNextPlan::currentNext([['op' => 'page_image_write', 'page' => 1, 'image' => '']], ['op' => 'commit']),
    'rejects missing savepoint action name' => static fn () => SQLitePagerSavepointCurrentNextPlan::currentNext([], ['op' => 'savepoint']),
    'rejects missing rollback action name' => static fn () => SQLitePagerSavepointCurrentNextPlan::currentNext($events, ['op' => 'rollback_to']),
    'rejects missing release action name' => static fn () => SQLitePagerSavepointCurrentNextPlan::currentNext($events, ['op' => 'release']),
    'rejects missing target savepoint' => static fn () => SQLitePagerSavepointCurrentNextPlan::currentNext($events, ['op' => 'rollback_to', 'name' => 'missing']),
    'rejects commit without transaction' => static fn () => SQLitePagerSavepointCurrentNextPlan::currentNext([], ['op' => 'commit']),
    'rejects rollback without transaction' => static fn () => SQLitePagerSavepointCurrentNextPlan::currentNext([], ['op' => 'rollback']),
    'rejects unsupported action' => static fn () => SQLitePagerSavepointCurrentNextPlan::currentNext($events, ['op' => 'checkpoint']),
];

foreach ($throws as $name => $callback) {
    $tests['pager savepoint current next65 ' . $name] = static function (TestRunner $t) use ($callback): void {
        $t->throws(Throwable::class, $callback);
    };
}

return $tests;
