<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewDeleteReturningCurrentSourceNextPlan;

$rows168 = [
    ['key_name' => 'plugin_root', 'key_value' => 'root', 'load_policy' => 'yes', 'parent_key_name' => null, 'priority' => 0],
    ['key_name' => 'plugin_child_a', 'key_value' => 'child-a', 'load_policy' => 'yes', 'parent_key_name' => 'plugin_root', 'priority' => 10],
    ['key_name' => 'plugin_child_b', 'key_value' => 'child-b', 'load_policy' => 'no', 'parent_key_name' => 'plugin_root', 'priority' => 20],
    ['key_name' => 'plugin_grandchild', 'key_value' => 'grandchild', 'load_policy' => 'no', 'parent_key_name' => 'plugin_child_a', 'priority' => 30],
    ['key_name' => 'siteurl', 'key_value' => 'https://example.test', 'load_policy' => 'yes', 'parent_key_name' => null, 'priority' => 40],
    ['key_name' => 'plugin_next_root', 'key_value' => 'next-root', 'load_policy' => 'yes', 'parent_key_name' => null, 'priority' => 50],
    ['key_name' => 'plugin_next_child', 'key_value' => 'next-child', 'load_policy' => 'no', 'parent_key_name' => 'plugin_next_root', 'priority' => 60],
];

$currentView168 = [
    'name' => 'app_recursive_setting_delete_view',
    'source' => 'main@view-cookie-168-current',
    'trigger' => 'app_recursive_setting_delete_view_io_delete',
    'trigger_source' => 'main@trigger-cookie-168-current',
    'root_key' => 'root_name',
    'columns' => ['key_name', 'key_value', 'load_policy', 'parent_key_name', 'priority'],
];
$nextView168 = $currentView168;
$nextView168['source'] = 'main@view-cookie-168-next';
$nextView168['trigger_source'] = 'main@trigger-cookie-168-next';

$returning168 = [
    'old.key_name',
    ['expr' => 'old.key_value', 'as' => 'value'],
    ['expr' => 'old.parent_key_name', 'as' => 'parent'],
    ['expr' => 'root.root_name', 'as' => 'delete_root'],
    ['expr' => 'depth', 'as' => 'delete_depth'],
    ['expr' => 'trigger_source', 'as' => 'trigger_cookie'],
    static fn (array $old, array $root, string $source, int $ordinal, int $depth): string => $source . ':' . $root['root_name'] . ':' . $ordinal . ':' . $depth . ':' . $old['key_name'],
];

$run168 = static fn (array $options = [], ?array $currentRoots = null, ?array $nextRoots = null, ?array $currentView = null, ?array $nextView = null, ?array $returning = null): array => SQLiteTriggerRecursiveViewDeleteReturningCurrentSourceNextPlan::execute(
    $rows168,
    $currentRoots ?? [['root_name' => 'plugin_root']],
    $nextRoots ?? [['root_name' => 'plugin_next_root']],
    $currentView ?? $currentView168,
    $nextView ?? $nextView168,
    $returning ?? $returning168,
    $options + ['savepoint' => 'app_recursive_view_delete_168'],
);

$rolled168 = static fn (): array => $run168(['blocked_key' => 'plugin_child_b']);
$released168 = static fn (): array => $run168(['release_current' => true]);
$admitted168 = static fn (): array => $run168(['release_current' => true, 'admit_next_source' => true]);
$nonRecursive168 = static fn (): array => $run168(['release_current' => true, 'recursive_triggers' => false]);

$cases168 = [
    'rolled status' => [static fn (): mixed => $rolled168()['status'], 'trigger-recursive-view-delete-returning-current-source-rolled-back-next168'],
    'rolled savepoint' => [static fn (): mixed => $rolled168()['savepoint'], 'app_recursive_view_delete_168'],
    'rolled current source' => [static fn (): mixed => $rolled168()['current_view']['source'], 'main@view-cookie-168-current'],
    'rolled visible source current' => [static fn (): mixed => $rolled168()['visible_view']['source'], 'main@view-cookie-168-current'],
    'rolled drained returning before blocker' => [static fn (): mixed => array_column(array_column($rolled168()['current_returning_rows'], 'returning'), 'key_name'), ['plugin_root', 'plugin_child_a']],
    'rolled callable trace' => [static fn (): mixed => array_column(array_column($rolled168()['current_returning_rows'], 'returning'), 'expr6'), [
        'main@trigger-cookie-168-current:plugin_root:0:0:plugin_root',
        'main@trigger-cookie-168-current:plugin_root:1:1:plugin_child_a',
    ]],
    'rolled blocked key' => [static fn (): mixed => $rolled168()['current_blocked_rows'][0]['returning']['key_name'], 'plugin_child_b'],
    'rolled blocked event' => [static fn (): mixed => $rolled168()['current_blocked_rows'][0]['event'], 'blocked-rollback'],
    'rolled current flag' => [static fn (): mixed => $rolled168()['current_rolled_back'], true],
    'rolled changes hidden' => [static fn (): mixed => $rolled168()['changes'], 0],
    'rolled current changes zero' => [static fn (): mixed => $rolled168()['current_changes'], 0],
    'rolled rows restored' => [static fn (): mixed => array_column($rolled168()['after_current_savepoint'], 'key_name'), array_column($rows168, 'key_name')],
    'rolled next attempted from restored image' => [static fn (): mixed => array_column(array_column($rolled168()['attempted_next_returning_rows'], 'returning'), 'key_name'), ['plugin_next_root', 'plugin_next_child']],
    'rolled next suppressed' => [static fn (): mixed => $rolled168()['next_returning_rows'], []],
    'rolled boundary' => [static fn (): mixed => $rolled168()['yield_boundary'], 'recursive-view-delete-returning-next168-current-delete-returning-drained-then-rolled-back'],

    'released status' => [static fn (): mixed => $released168()['status'], 'trigger-recursive-view-delete-returning-current-source-released-next168'],
    'released deleted keys' => [static fn (): mixed => $released168()['current_deleted_keys'], ['plugin_root', 'plugin_child_a', 'plugin_child_b', 'plugin_grandchild']],
    'released returning parents' => [static fn (): mixed => array_column(array_column($released168()['current_returning_rows'], 'returning'), 'parent'), [null, 'plugin_root', 'plugin_root', 'plugin_child_a']],
    'released trigger cookies current' => [static fn (): mixed => array_values(array_unique(array_column($released168()['current_returning_rows'], 'trigger_source'))), ['main@trigger-cookie-168-current']],
    'released recursive edges' => [static fn (): mixed => array_map(static fn (array $edge): array => [$edge['parent_key'], $edge['child_key']], $released168()['current_recursive_edges']), [
        ['plugin_root', 'plugin_child_a'],
        ['plugin_root', 'plugin_child_b'],
        ['plugin_child_a', 'plugin_grandchild'],
    ]],
    'released depths' => [static fn (): mixed => array_column(array_column($released168()['current_returning_rows'], 'returning'), 'delete_depth'), [0, 1, 1, 2]],
    'released rows remain' => [static fn (): mixed => array_column($released168()['after_current_savepoint'], 'key_name'), ['siteurl', 'plugin_next_root', 'plugin_next_child']],
    'released changes current only' => [static fn (): mixed => $released168()['changes'], 4],
    'released next attempted sees surviving next root' => [static fn (): mixed => $released168()['attempted_next_deleted_keys'], ['plugin_next_root', 'plugin_next_child']],
    'released next still suppressed' => [static fn (): mixed => $released168()['next_deleted_keys'], []],
    'released boundary' => [static fn (): mixed => $released168()['yield_boundary'], 'recursive-view-delete-returning-next168-current-delete-released-next-source-held'],

    'admitted status' => [static fn (): mixed => $admitted168()['status'], 'trigger-recursive-view-delete-returning-next-source-admitted-next168'],
    'admitted visible source next' => [static fn (): mixed => $admitted168()['visible_view']['source'], 'main@view-cookie-168-next'],
    'admitted next trigger source' => [static fn (): mixed => array_column($admitted168()['next_returning_rows'], 'trigger_source'), ['main@trigger-cookie-168-next', 'main@trigger-cookie-168-next']],
    'admitted next names' => [static fn (): mixed => array_column(array_column($admitted168()['next_returning_rows'], 'returning'), 'key_name'), ['plugin_next_root', 'plugin_next_child']],
    'admitted next depths' => [static fn (): mixed => array_column(array_column($admitted168()['next_returning_rows'], 'returning'), 'delete_depth'), [0, 1]],
    'admitted all changes' => [static fn (): mixed => $admitted168()['changes'], 6],
    'admitted final rows' => [static fn (): mixed => array_column($admitted168()['after_savepoint'], 'key_name'), ['siteurl']],
    'admitted statement rows' => [static fn (): mixed => $admitted168()['statement_rows'], 6],
    'admitted boundary' => [static fn (): mixed => $admitted168()['yield_boundary'], 'recursive-view-delete-returning-next168-next-source-admitted-after-current-delete-drain'],

    'nonrecursive deletes root only' => [static fn (): mixed => $nonRecursive168()['current_deleted_keys'], ['plugin_root']],
    'nonrecursive keeps children' => [static fn (): mixed => array_column($nonRecursive168()['after_current_savepoint'], 'key_name'), ['plugin_child_a', 'plugin_child_b', 'plugin_grandchild', 'siteurl', 'plugin_next_root', 'plugin_next_child']],
    'max depth zero deletes root only' => [static fn (): mixed => $run168(['release_current' => true, 'max_depth' => 0])['current_deleted_keys'], ['plugin_root']],
    'max depth one deletes children only' => [static fn (): mixed => $run168(['release_current' => true, 'max_depth' => 1])['current_deleted_keys'], ['plugin_root', 'plugin_child_a', 'plugin_child_b']],
    'custom root deletes siteurl' => [static fn (): mixed => $run168(['release_current' => true], [['root_name' => 'siteurl']])['current_deleted_keys'], ['siteurl']],
    'custom savepoint accepted' => [static fn (): mixed => $run168(['savepoint' => 'app_custom_delete_168'])['savepoint'], 'app_custom_delete_168'],
    'missing root is no-op' => [static fn (): mixed => $run168(['release_current' => true], [['root_name' => 'missing']])['changes'], 0],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-delete-returning-current-source-next168', $released168()['dependencies'], true), true],
    'delete trigger dependency marker' => [static fn (): mixed => in_array('sqlite-instead-of-delete-view-trigger-returning-current-source', $released168()['dependencies'], true), true],
    'bad savepoint rejected' => [static fn (): mixed => $run168(['savepoint' => 'bad savepoint']), InvalidArgumentException::class],
    'bad max depth rejected' => [static fn (): mixed => $run168(['max_depth' => -1]), InvalidArgumentException::class],
    'empty returning rejected' => [static fn (): mixed => $run168([], null, null, null, null, []), InvalidArgumentException::class],
    'bad view source rejected' => [static fn (): mixed => $run168([], null, null, ['name' => 'v', 'source' => 'bad source', 'trigger' => 'trg', 'trigger_source' => 'ok', 'columns' => ['key_name']]), InvalidArgumentException::class],
    'bad root key rejected' => [static fn (): mixed => $run168([], [['missing' => 'plugin_root']]), InvalidArgumentException::class],
    'duplicate row rejected' => [static fn (): mixed => SQLiteTriggerRecursiveViewDeleteReturningCurrentSourceNextPlan::execute(array_merge($rows168, [['key_name' => 'siteurl']]), [], [], $currentView168, $nextView168, $returning168), InvalidArgumentException::class],
    'bad parent key rejected' => [static fn (): mixed => $run168(['parent_key' => 'bad-key']), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases168 as $name => [$callback, $expected]) {
    $tests['trigger recursive view delete returning current source next168 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
