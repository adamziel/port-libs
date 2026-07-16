<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerReturningRecursiveDeferredViewCurrentSourceNextPlan;

$rows128 = [
    ['key_name' => 'base_url', 'key_value' => 'https://old.test', 'revision' => 1, 'depth' => 0, 'load_policy' => 'yes', 'parent_name' => null],
    ['key_name' => 'module_seed', 'key_value' => 'seed-old', 'revision' => 4, 'depth' => 1, 'load_policy' => 'no', 'parent_name' => 'base_url'],
    ['key_name' => 'orphan_setting', 'key_value' => 'orphan-old', 'revision' => 1, 'depth' => 0, 'load_policy' => 'no', 'parent_name' => 'missing_parent'],
];

$assign128 = [
    'key_value' => static fn (array $old, array $incoming): mixed => $incoming['key_value'],
    'revision' => static fn (array $old, array $incoming): int => (int) $old['revision'] + (int) $incoming['revision'],
    'depth' => static fn (array $old, array $incoming): mixed => $incoming['depth'],
    'load_policy' => static fn (array $old, array $incoming): mixed => $incoming['load_policy'],
    'parent_name' => static fn (array $old, array $incoming): mixed => $incoming['parent_name'] ?? ($old['parent_name'] ?? null),
];

$triggers128 = [
    [
        'name' => 'app_settings_ai_child_128',
        'timing' => 'after',
        'event' => 'insert',
        'action' => 'upsert-parent',
        'when' => ['new.depth', '<', 2],
        'row' => [
            'key_name' => ['concat' => ['new.key_name', ':child']],
            'key_value' => ['concat' => ['new.key_value', ':child']],
            'revision' => 1,
            'depth' => ['add' => ['new.depth', 1]],
            'load_policy' => 'new.load_policy',
            'parent_name' => 'new.key_name',
        ],
        'values' => ['name' => 'new.key_name', 'parent' => 'new.parent_name'],
    ],
    [
        'name' => 'app_settings_au_child_128',
        'timing' => 'after',
        'event' => 'update',
        'action' => 'upsert-parent',
        'when' => ['new.depth', '<', 2],
        'row' => [
            'key_name' => ['concat' => ['new.key_name', ':child']],
            'key_value' => ['concat' => ['new.key_value', ':child']],
            'revision' => 1,
            'depth' => ['add' => ['new.depth', 1]],
            'load_policy' => 'new.load_policy',
            'parent_name' => 'new.key_name',
        ],
        'values' => ['name' => 'new.key_name', 'parent' => 'new.parent_name'],
    ],
];

$returning128 = [
    'key_name',
    ['expr' => 'new.key_value', 'as' => 'value'],
    ['expr' => 'excluded.key_value', 'as' => 'incoming_value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'trigger_depth'],
    ['expr' => 'trigger', 'as' => 'source_trigger'],
    'parent_name',
];

$view128 = [
    'name' => 'app_loadable_settings_128',
    'columns' => ['key_name', 'key_value', 'parent_name', 'load_policy'],
    'where' => static fn (array $row): bool => ($row['load_policy'] ?? null) === 'yes',
    'order_by' => 'key_name',
];

$run128 = static fn (array $current = null, array $next = null, array $options = [], array $view = null): array => SQLiteTriggerReturningRecursiveDeferredViewCurrentSourceNextPlan::execute(
    $rows128,
    $current ?? [
        ['key_name' => 'module_seed', 'key_value' => 'seed-current', 'revision' => 2, 'depth' => 1, 'load_policy' => 'yes', 'parent_name' => 'base_url'],
        ['key_name' => 'fresh_module', 'key_value' => 'fresh-current', 'revision' => 1, 'depth' => 1, 'load_policy' => 'yes', 'parent_name' => 'base_url'],
        ['key_name' => 'missing_parent', 'key_value' => 'repair-current', 'revision' => 1, 'depth' => 1, 'load_policy' => 'no', 'parent_name' => 'base_url'],
    ],
    $next ?? [
        ['key_name' => 'module_seed:child', 'key_value' => 'seed-child-next', 'revision' => 3, 'depth' => 2, 'load_policy' => 'yes', 'parent_name' => 'module_seed'],
        ['key_name' => 'next_module', 'key_value' => 'next', 'revision' => 1, 'depth' => 1, 'load_policy' => 'yes', 'parent_name' => 'base_url'],
    ],
    ['key_name'],
    $assign128,
    $triggers128,
    ['parent_key' => 'key_name', 'child_key' => 'parent_name', 'deferred' => true],
    $view ?? $view128,
    $options + ['current_source' => 'main@cookie-128', 'next_source' => 'main@cookie-129', 'returning' => $returning128],
);

$plan128 = static fn (): array => $run128();
$blocked128 = static fn (): array => $run128([
    ['key_name' => 'module_seed', 'key_value' => 'seed-current', 'revision' => 2, 'depth' => 1, 'load_policy' => 'yes', 'parent_name' => 'base_url'],
    ['key_name' => 'fresh_module', 'key_value' => 'fresh-current', 'revision' => 1, 'depth' => 1, 'load_policy' => 'yes', 'parent_name' => 'missing_parent'],
]);
$unblocked128 = static fn (): array => $run128(null, null, ['rollback_on_deferred_violation' => false]);

$cases128 = [
    'status ok' => [static fn (): mixed => $plan128()['status'], 'view-current-source-drained-before-next-source'],
    'current source retained' => [static fn (): mixed => $plan128()['current_source'], 'main@cookie-128'],
    'next source advanced' => [static fn (): mixed => $plan128()['next_source'], 'main@cookie-129'],
    'view name retained' => [static fn (): mixed => $plan128()['view'], 'app_loadable_settings_128'],
    'view source is current' => [static fn (): mixed => $plan128()['view_source'], 'main@cookie-128'],
    'view columns retained' => [static fn (): mixed => $plan128()['view_columns'], ['key_name', 'key_value', 'parent_name', 'load_policy']],
    'view row count' => [static fn (): mixed => $plan128()['view_row_count'], 5],
    'view rows ordered from current source' => [static fn (): mixed => array_column($plan128()['view_rows'], 'key_name'), ['base_url', 'fresh_module', 'fresh_module:child', 'module_seed', 'module_seed:child']],
    'view sees current update value' => [static fn (): mixed => $plan128()['view_rows'][3]['key_value'], 'seed-current'],
    'view sees recursive current child' => [static fn (): mixed => $plan128()['view_rows'][4]['parent_name'], 'module_seed'],
    'current returning count' => [static fn (): mixed => count($plan128()['current_returning_rows']), 6],
    'next returning count' => [static fn (): mixed => count($plan128()['next_returning_rows']), 3],
    'attempted next returning count' => [static fn (): mixed => count($plan128()['attempted_next_returning_rows']), 3],
    'current returning names' => [static fn (): mixed => array_column(array_column($plan128()['current_returning_rows'], 'returning'), 'key_name'), ['module_seed', 'module_seed:child', 'fresh_module', 'fresh_module:child', 'missing_parent', 'missing_parent:child']],
    'next returning names' => [static fn (): mixed => array_column(array_column($plan128()['next_returning_rows'], 'returning'), 'key_name'), ['module_seed:child', 'next_module', 'next_module:child']],
    'next source rows are current parent rows' => [static fn (): mixed => array_column($plan128()['current_parent_rows'], 'key_name'), ['base_url', 'module_seed', 'orphan_setting', 'module_seed:child', 'fresh_module', 'fresh_module:child', 'missing_parent', 'missing_parent:child']],
    'final rows include next rows' => [static fn (): mixed => array_column($plan128()['next_parent_rows'], 'key_name'), ['base_url', 'module_seed', 'orphan_setting', 'module_seed:child', 'fresh_module', 'fresh_module:child', 'missing_parent', 'missing_parent:child', 'next_module', 'next_module:child']],
    'current yield stream source' => [static fn (): mixed => array_values(array_unique(array_column($plan128()['current_yield_stream'], 'source_token'))), ['main@cookie-128']],
    'next yield stream source' => [static fn (): mixed => array_values(array_unique(array_column($plan128()['next_yield_stream'], 'source_token'))), ['main@cookie-129']],
    'yield stream count' => [static fn (): mixed => count($plan128()['yield_stream']), 9],
    'deferred fk checked after view' => [static fn (): mixed => $plan128()['deferred_foreign_key_checked_after_view'], true],
    'no fk violations after repair' => [static fn (): mixed => $plan128()['foreign_key_violations'], []],
    'rollback false' => [static fn (): mixed => $plan128()['rollback_to_current_source'], false],
    'next source not blocked' => [static fn (): mixed => $plan128()['next_source_blocked_by_deferred_fk'], false],
    'yield boundary ok' => [static fn (): mixed => $plan128()['yield_boundary'], 'current-returning-view-yield-then-next-source'],
    'dependencies name next128' => [static fn (): mixed => $plan128()['dependencies'][0], 'sqlite-trigger-returning-recursive-deferred-view-current-source-next128'],
    'dependencies include view materialization' => [static fn (): mixed => in_array('sqlite-view-current-source-materialization', $plan128()['dependencies'], true), true],
    'handoff remains current to next' => [static fn (): mixed => $plan128()['handoff']['from'] . '->' . $plan128()['handoff']['to'], 'main@cookie-128->main@cookie-129'],
    'handoff returning drained' => [static fn (): mixed => $plan128()['handoff']['returning_rows_drained'], 6],
    'children include deferred orphan' => [static fn (): mixed => array_column($plan128()['children'], 'key_name'), ['base_url', 'module_seed', 'orphan_setting']],
    'blocked status' => [static fn (): mixed => $blocked128()['status'], 'deferred-fk-blocked-before-next-source'],
    'blocked next source stays current' => [static fn (): mixed => $blocked128()['next_source'], 'main@cookie-128'],
    'blocked next returning suppressed' => [static fn (): mixed => $blocked128()['next_returning_rows'], []],
    'blocked attempted next retained' => [static fn (): mixed => count($blocked128()['attempted_next_returning_rows']), 3],
    'blocked next yield suppressed' => [static fn (): mixed => $blocked128()['next_yield_stream'], []],
    'blocked attempted next yield retained' => [static fn (): mixed => count($blocked128()['attempted_next_yield_stream']), 3],
    'blocked rollback true' => [static fn (): mixed => $blocked128()['rollback_to_current_source'], true],
    'blocked flag true' => [static fn (): mixed => $blocked128()['next_source_blocked_by_deferred_fk'], true],
    'blocked violation count' => [static fn (): mixed => count($blocked128()['foreign_key_violations']), 1],
    'blocked violation key' => [static fn (): mixed => $blocked128()['foreign_key_violations'][0]['child_key'], 'missing_parent'],
    'blocked violation phase' => [static fn (): mixed => $blocked128()['foreign_key_violations'][0]['phase'], 'deferred-commit-before-next-source'],
    'blocked view still materialized' => [static fn (): mixed => array_column($blocked128()['view_rows'], 'key_name'), ['base_url', 'fresh_module', 'fresh_module:child', 'module_seed', 'module_seed:child']],
    'blocked parent rows rolled back for next' => [static fn (): mixed => array_column($blocked128()['next_parent_rows'], 'key_name'), ['base_url', 'module_seed', 'orphan_setting']],
    'blocked current rows include attempted changes' => [static fn (): mixed => array_column($blocked128()['current_parent_rows'], 'key_name'), ['base_url', 'module_seed', 'orphan_setting', 'module_seed:child', 'fresh_module', 'fresh_module:child']],
    'blocked boundary rollback' => [static fn (): mixed => $blocked128()['yield_boundary'], 'current-returning-view-yield-then-deferred-fk-rollback'],
    'unblocked status with violations' => [static fn (): mixed => $unblocked128()['status'], 'view-current-source-drained-before-next-source'],
    'unblocked keeps next source' => [static fn (): mixed => $unblocked128()['next_source'], 'main@cookie-129'],
    'unblocked attempted equals visible next' => [static fn (): mixed => $unblocked128()['attempted_next_returning_rows'], $unblocked128()['next_returning_rows']],
    'empty view columns throw' => [static fn (): mixed => $run128(null, null, [], ['name' => 'bad_view', 'columns' => []]), InvalidArgumentException::class],
    'bad view name throws' => [static fn (): mixed => $run128(null, null, [], ['name' => 'bad view', 'columns' => ['key_name']]), InvalidArgumentException::class],
    'bad view where throws' => [static fn (): mixed => $run128(null, null, [], ['name' => 'bad_view', 'columns' => ['key_name'], 'where' => 'nope']), InvalidArgumentException::class],
    'bad order column throws' => [static fn (): mixed => $run128(null, null, [], ['name' => 'bad_view', 'columns' => ['key_name'], 'order_by' => 'bad column']), InvalidArgumentException::class],
    'bad current source throws' => [static fn (): mixed => $run128(null, null, ['current_source' => 'bad token']), InvalidArgumentException::class],
    'bad parent key throws' => [static fn (): mixed => SQLiteTriggerReturningRecursiveDeferredViewCurrentSourceNextPlan::execute($rows128, [['key_name' => 'x']], [['key_name' => 'y']], ['key_name'], $assign128, $triggers128, ['parent_key' => 'bad key', 'child_key' => 'parent_name'], $view128), InvalidArgumentException::class],
];

foreach ($cases128 as $name => [$callback, $expected]) {
    $tests['trigger returning recursive deferred view current source next128 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
