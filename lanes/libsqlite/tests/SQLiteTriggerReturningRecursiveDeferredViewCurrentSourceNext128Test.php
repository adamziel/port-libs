<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerReturningRecursiveDeferredViewCurrentSourceNextPlan;

$rows128 = [
    ['option_name' => 'siteurl', 'option_value' => 'https://old.test', 'revision' => 1, 'depth' => 0, 'autoload' => 'yes', 'parent_name' => null],
    ['option_name' => 'plugin_seed', 'option_value' => 'seed-old', 'revision' => 4, 'depth' => 1, 'autoload' => 'no', 'parent_name' => 'siteurl'],
    ['option_name' => 'orphan_option', 'option_value' => 'orphan-old', 'revision' => 1, 'depth' => 0, 'autoload' => 'no', 'parent_name' => 'missing_parent'],
];

$assign128 = [
    'option_value' => static fn (array $old, array $incoming): mixed => $incoming['option_value'],
    'revision' => static fn (array $old, array $incoming): int => (int) $old['revision'] + (int) $incoming['revision'],
    'depth' => static fn (array $old, array $incoming): mixed => $incoming['depth'],
    'autoload' => static fn (array $old, array $incoming): mixed => $incoming['autoload'],
    'parent_name' => static fn (array $old, array $incoming): mixed => $incoming['parent_name'] ?? ($old['parent_name'] ?? null),
];

$triggers128 = [
    [
        'name' => 'wp_options_ai_child_128',
        'timing' => 'after',
        'event' => 'insert',
        'action' => 'upsert-parent',
        'when' => ['new.depth', '<', 2],
        'row' => [
            'option_name' => ['concat' => ['new.option_name', ':child']],
            'option_value' => ['concat' => ['new.option_value', ':child']],
            'revision' => 1,
            'depth' => ['add' => ['new.depth', 1]],
            'autoload' => 'new.autoload',
            'parent_name' => 'new.option_name',
        ],
        'values' => ['name' => 'new.option_name', 'parent' => 'new.parent_name'],
    ],
    [
        'name' => 'wp_options_au_child_128',
        'timing' => 'after',
        'event' => 'update',
        'action' => 'upsert-parent',
        'when' => ['new.depth', '<', 2],
        'row' => [
            'option_name' => ['concat' => ['new.option_name', ':child']],
            'option_value' => ['concat' => ['new.option_value', ':child']],
            'revision' => 1,
            'depth' => ['add' => ['new.depth', 1]],
            'autoload' => 'new.autoload',
            'parent_name' => 'new.option_name',
        ],
        'values' => ['name' => 'new.option_name', 'parent' => 'new.parent_name'],
    ],
];

$returning128 = [
    'option_name',
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'excluded.option_value', 'as' => 'incoming_value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'trigger_depth'],
    ['expr' => 'trigger', 'as' => 'source_trigger'],
    'parent_name',
];

$view128 = [
    'name' => 'wp_autoloaded_options_128',
    'columns' => ['option_name', 'option_value', 'parent_name', 'autoload'],
    'where' => static fn (array $row): bool => ($row['autoload'] ?? null) === 'yes',
    'order_by' => 'option_name',
];

$run128 = static fn (array $current = null, array $next = null, array $options = [], array $view = null): array => SQLiteTriggerReturningRecursiveDeferredViewCurrentSourceNextPlan::execute(
    $rows128,
    $current ?? [
        ['option_name' => 'plugin_seed', 'option_value' => 'seed-current', 'revision' => 2, 'depth' => 1, 'autoload' => 'yes', 'parent_name' => 'siteurl'],
        ['option_name' => 'fresh_plugin', 'option_value' => 'fresh-current', 'revision' => 1, 'depth' => 1, 'autoload' => 'yes', 'parent_name' => 'siteurl'],
        ['option_name' => 'missing_parent', 'option_value' => 'repair-current', 'revision' => 1, 'depth' => 1, 'autoload' => 'no', 'parent_name' => 'siteurl'],
    ],
    $next ?? [
        ['option_name' => 'plugin_seed:child', 'option_value' => 'seed-child-next', 'revision' => 3, 'depth' => 2, 'autoload' => 'yes', 'parent_name' => 'plugin_seed'],
        ['option_name' => 'next_plugin', 'option_value' => 'next', 'revision' => 1, 'depth' => 1, 'autoload' => 'yes', 'parent_name' => 'siteurl'],
    ],
    ['option_name'],
    $assign128,
    $triggers128,
    ['parent_key' => 'option_name', 'child_key' => 'parent_name', 'deferred' => true],
    $view ?? $view128,
    $options + ['current_source' => 'main@cookie-128', 'next_source' => 'main@cookie-129', 'returning' => $returning128],
);

$plan128 = static fn (): array => $run128();
$blocked128 = static fn (): array => $run128([
    ['option_name' => 'plugin_seed', 'option_value' => 'seed-current', 'revision' => 2, 'depth' => 1, 'autoload' => 'yes', 'parent_name' => 'siteurl'],
    ['option_name' => 'fresh_plugin', 'option_value' => 'fresh-current', 'revision' => 1, 'depth' => 1, 'autoload' => 'yes', 'parent_name' => 'missing_parent'],
]);
$unblocked128 = static fn (): array => $run128(null, null, ['rollback_on_deferred_violation' => false]);

$cases128 = [
    'status ok' => [static fn (): mixed => $plan128()['status'], 'view-current-source-drained-before-next-source'],
    'current source retained' => [static fn (): mixed => $plan128()['current_source'], 'main@cookie-128'],
    'next source advanced' => [static fn (): mixed => $plan128()['next_source'], 'main@cookie-129'],
    'view name retained' => [static fn (): mixed => $plan128()['view'], 'wp_autoloaded_options_128'],
    'view source is current' => [static fn (): mixed => $plan128()['view_source'], 'main@cookie-128'],
    'view columns retained' => [static fn (): mixed => $plan128()['view_columns'], ['option_name', 'option_value', 'parent_name', 'autoload']],
    'view row count' => [static fn (): mixed => $plan128()['view_row_count'], 5],
    'view rows ordered from current source' => [static fn (): mixed => array_column($plan128()['view_rows'], 'option_name'), ['fresh_plugin', 'fresh_plugin:child', 'plugin_seed', 'plugin_seed:child', 'siteurl']],
    'view sees current update value' => [static fn (): mixed => $plan128()['view_rows'][2]['option_value'], 'seed-current'],
    'view sees recursive current child' => [static fn (): mixed => $plan128()['view_rows'][3]['parent_name'], 'plugin_seed'],
    'current returning count' => [static fn (): mixed => count($plan128()['current_returning_rows']), 6],
    'next returning count' => [static fn (): mixed => count($plan128()['next_returning_rows']), 3],
    'attempted next returning count' => [static fn (): mixed => count($plan128()['attempted_next_returning_rows']), 3],
    'current returning names' => [static fn (): mixed => array_column(array_column($plan128()['current_returning_rows'], 'returning'), 'option_name'), ['plugin_seed', 'plugin_seed:child', 'fresh_plugin', 'fresh_plugin:child', 'missing_parent', 'missing_parent:child']],
    'next returning names' => [static fn (): mixed => array_column(array_column($plan128()['next_returning_rows'], 'returning'), 'option_name'), ['plugin_seed:child', 'next_plugin', 'next_plugin:child']],
    'next source rows are current parent rows' => [static fn (): mixed => array_column($plan128()['current_parent_rows'], 'option_name'), ['siteurl', 'plugin_seed', 'orphan_option', 'plugin_seed:child', 'fresh_plugin', 'fresh_plugin:child', 'missing_parent', 'missing_parent:child']],
    'final rows include next rows' => [static fn (): mixed => array_column($plan128()['next_parent_rows'], 'option_name'), ['siteurl', 'plugin_seed', 'orphan_option', 'plugin_seed:child', 'fresh_plugin', 'fresh_plugin:child', 'missing_parent', 'missing_parent:child', 'next_plugin', 'next_plugin:child']],
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
    'children include deferred orphan' => [static fn (): mixed => array_column($plan128()['children'], 'option_name'), ['siteurl', 'plugin_seed', 'orphan_option']],
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
    'blocked view still materialized' => [static fn (): mixed => array_column($blocked128()['view_rows'], 'option_name'), ['fresh_plugin', 'fresh_plugin:child', 'plugin_seed', 'plugin_seed:child', 'siteurl']],
    'blocked parent rows rolled back for next' => [static fn (): mixed => array_column($blocked128()['next_parent_rows'], 'option_name'), ['siteurl', 'plugin_seed', 'orphan_option']],
    'blocked current rows include attempted changes' => [static fn (): mixed => array_column($blocked128()['current_parent_rows'], 'option_name'), ['siteurl', 'plugin_seed', 'orphan_option', 'plugin_seed:child', 'fresh_plugin', 'fresh_plugin:child']],
    'blocked boundary rollback' => [static fn (): mixed => $blocked128()['yield_boundary'], 'current-returning-view-yield-then-deferred-fk-rollback'],
    'unblocked status with violations' => [static fn (): mixed => $unblocked128()['status'], 'view-current-source-drained-before-next-source'],
    'unblocked keeps next source' => [static fn (): mixed => $unblocked128()['next_source'], 'main@cookie-129'],
    'unblocked attempted equals visible next' => [static fn (): mixed => $unblocked128()['attempted_next_returning_rows'], $unblocked128()['next_returning_rows']],
    'empty view columns throw' => [static fn (): mixed => $run128(null, null, [], ['name' => 'bad_view', 'columns' => []]), InvalidArgumentException::class],
    'bad view name throws' => [static fn (): mixed => $run128(null, null, [], ['name' => 'bad view', 'columns' => ['option_name']]), InvalidArgumentException::class],
    'bad view where throws' => [static fn (): mixed => $run128(null, null, [], ['name' => 'bad_view', 'columns' => ['option_name'], 'where' => 'nope']), InvalidArgumentException::class],
    'bad order column throws' => [static fn (): mixed => $run128(null, null, [], ['name' => 'bad_view', 'columns' => ['option_name'], 'order_by' => 'bad column']), InvalidArgumentException::class],
    'bad current source throws' => [static fn (): mixed => $run128(null, null, ['current_source' => 'bad token']), InvalidArgumentException::class],
    'bad parent key throws' => [static fn (): mixed => SQLiteTriggerReturningRecursiveDeferredViewCurrentSourceNextPlan::execute($rows128, [['option_name' => 'x']], [['option_name' => 'y']], ['option_name'], $assign128, $triggers128, ['parent_key' => 'bad key', 'child_key' => 'parent_name'], $view128), InvalidArgumentException::class],
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
