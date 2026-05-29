<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows161 = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes', 'source' => 'seed'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes', 'source' => 'seed'],
];
$currentView161 = [
    'name' => 'wp_option_import_view',
    'source' => 'main@view-cookie-161-current',
    'trigger' => 'wp_option_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-161-current',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_child',
    'audit_label' => 'current-recursive-trigger-body',
];
$nextView161 = [
    'name' => 'wp_option_import_view',
    'source' => 'main@view-cookie-161-next',
    'trigger' => 'wp_option_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-161-next',
    'columns' => ['import_id', 'name', 'value', 'autoload_flag', 'origin'],
    'mapping' => ['import_id' => 'option_id', 'name' => 'option_name', 'value' => 'option_value', 'autoload_flag' => 'autoload', 'origin' => 'source'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_next_child',
    'audit_label' => 'next-recursive-trigger-body',
];
$currentInput161 = [
    ['import_id' => 10, 'name' => 'plugin_seed', 'value' => 'enabled', 'autoload_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'siteurl', 'value' => 'https://current.test', 'autoload_flag' => 'yes', 'spawn_child' => false],
];
$nextInput161 = [
    ['import_id' => 20, 'name' => 'rewrite_rules', 'value' => 'cached', 'autoload_flag' => 'yes', 'origin' => 'next-import', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'home', 'value' => 'https://next-home.test', 'autoload_flag' => 'yes', 'origin' => 'next-import', 'spawn_child' => false],
];
$returning161 = [
    'new.option_name',
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'view.name', 'as' => 'view_name'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    static fn (array $new, array $viewRow, string $event, int $ordinal, int $depth, string $source): string => $source . ':' . $event . ':' . $ordinal . ':' . $depth . ':' . $viewRow['name'] . '>' . $new['option_name'],
];

$plan161 = static fn (array $options = [], ?array $currentInput = null, ?array $nextInput = null, ?array $currentView = null, ?array $nextView = null, ?array $returning = null): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext161(
    $rows161,
    $currentInput ?? $currentInput161,
    $nextInput ?? $nextInput161,
    $currentView ?? $currentView161,
    $nextView ?? $nextView161,
    $returning ?? $returning161,
    $options + ['key' => 'option_name', 'savepoint' => 'wp_recursive_view_161', 'max_depth' => 2],
);

$pinned161 = static fn (): array => $plan161();
$admitted161 = static fn (): array => $plan161(['admit_next_source' => true]);
$nonRecursive161 = static fn (): array => $plan161(['recursive_triggers' => false]);
$depthOne161 = static fn (): array => $plan161(['max_depth' => 1]);

$cases161 = [
    'pinned status' => [static fn (): mixed => $pinned161()['status'], 'trigger-recursive-view-returning-current-source-pinned-next161'],
    'pinned savepoint' => [static fn (): mixed => $pinned161()['savepoint'], 'wp_recursive_view_161'],
    'pinned key' => [static fn (): mixed => $pinned161()['key'], 'option_name'],
    'pinned recursive enabled' => [static fn (): mixed => $pinned161()['recursive_triggers'], true],
    'pinned max depth' => [static fn (): mixed => $pinned161()['max_depth'], 2],
    'pinned current view source' => [static fn (): mixed => $pinned161()['current_view']['source'], 'main@view-cookie-161-current'],
    'pinned next view source' => [static fn (): mixed => $pinned161()['next_view']['source'], 'main@view-cookie-161-next'],
    'pinned current trigger source' => [static fn (): mixed => $pinned161()['current_view']['trigger_source'], 'main@trigger-cookie-161-current'],
    'pinned next trigger source' => [static fn (): mixed => $pinned161()['next_view']['trigger_source'], 'main@trigger-cookie-161-next'],
    'pinned visible source remains current' => [static fn (): mixed => $pinned161()['visible_view']['source'], 'main@view-cookie-161-current'],
    'pinned trigger source changed' => [static fn (): mixed => $pinned161()['trigger_source_changed'], true],
    'pinned next source not admitted' => [static fn (): mixed => $pinned161()['next_source_admitted'], false],
    'pinned current mapping name' => [static fn (): mixed => $pinned161()['current_view']['mapping']['name'], 'option_name'],
    'pinned next mapping origin' => [static fn (): mixed => $pinned161()['next_view']['mapping']['origin'], 'source'],
    'pinned recursive suffix' => [static fn (): mixed => $pinned161()['current_view']['recursive_suffix'], '_child'],
    'pinned changes suppressed at boundary' => [static fn (): mixed => $pinned161()['changes'], 0],
    'pinned current changes counted' => [static fn (): mixed => $pinned161()['current_changes'], 4],
    'pinned next changes suppressed' => [static fn (): mixed => $pinned161()['next_changes'], 0],
    'pinned attempted next changes counted' => [static fn (): mixed => $pinned161()['attempted_next_changes'], 4],
    'pinned statement rows current recursive' => [static fn (): mixed => $pinned161()['statement_rows'], 4],
    'pinned attempted statement rows both sources' => [static fn (): mixed => $pinned161()['attempted_statement_rows'], 8],
    'pinned current returning count' => [static fn (): mixed => count($pinned161()['current_returning_rows']), 4],
    'pinned current returning names' => [static fn (): mixed => array_column(array_column($pinned161()['current_returning_rows'], 'returning'), 'option_name'), ['plugin_seed', 'siteurl', 'plugin_seed_child', 'plugin_seed_child_child']],
    'pinned current returning depths' => [static fn (): mixed => array_column(array_column($pinned161()['current_returning_rows'], 'returning'), 'depth_value'), [0, 0, 1, 2]],
    'pinned current returning trigger aliases' => [static fn (): mixed => array_column(array_column($pinned161()['current_returning_rows'], 'returning'), 'trigger_source_alias'), ['main@trigger-cookie-161-current', 'main@trigger-cookie-161-current', 'main@trigger-cookie-161-current', 'main@trigger-cookie-161-current']],
    'pinned callable traces' => [static fn (): mixed => array_column(array_column($pinned161()['current_returning_rows'], 'returning'), 'expr7'), ['main@trigger-cookie-161-current:insert:0:0:plugin_seed>plugin_seed', 'main@trigger-cookie-161-current:update:1:0:siteurl>siteurl', 'main@trigger-cookie-161-current:insert:2:1:plugin_seed_child>plugin_seed_child', 'main@trigger-cookie-161-current:insert:3:2:plugin_seed_child_child>plugin_seed_child_child']],
    'pinned current yield depths' => [static fn (): mixed => array_column($pinned161()['current_yield_stream'], 'depth'), [0, 0, 1, 2]],
    'pinned current yield parent keys' => [static fn (): mixed => array_column($pinned161()['current_yield_stream'], 'parent_key'), [null, null, 'plugin_seed', 'plugin_seed_child']],
    'pinned current recursive edge count' => [static fn (): mixed => count($pinned161()['current_recursive_edges']), 2],
    'pinned current recursive child keys' => [static fn (): mixed => array_column($pinned161()['current_recursive_edges'], 'child_key'), ['plugin_seed_child', 'plugin_seed_child_child']],
    'pinned current rows include recursive children' => [static fn (): mixed => array_column($pinned161()['current_rows'], 'option_name'), ['siteurl', 'home', 'plugin_seed', 'plugin_seed_child', 'plugin_seed_child_child']],
    'pinned current siteurl updated' => [static fn (): mixed => $pinned161()['current_rows'][0]['option_value'], 'https://current.test'],
    'pinned after savepoint restores base names' => [static fn (): mixed => array_column($pinned161()['after_savepoint'], 'option_name'), ['siteurl', 'home']],
    'pinned after savepoint restores base value' => [static fn (): mixed => $pinned161()['after_savepoint'][0]['option_value'], 'https://old.test'],
    'pinned next returning suppressed' => [static fn (): mixed => $pinned161()['next_returning_rows'], []],
    'pinned attempted next returning names' => [static fn (): mixed => array_column(array_column($pinned161()['attempted_next_returning_rows'], 'returning'), 'option_name'), ['rewrite_rules', 'home', 'rewrite_rules_next_child', 'rewrite_rules_next_child_next_child']],
    'pinned attempted next depths' => [static fn (): mixed => array_column(array_column($pinned161()['attempted_next_returning_rows'], 'returning'), 'depth_value'), [0, 0, 1, 2]],
    'pinned attempted next source token' => [static fn (): mixed => array_column(array_column($pinned161()['attempted_next_returning_rows'], 'returning'), 'trigger_source_alias'), ['main@trigger-cookie-161-next', 'main@trigger-cookie-161-next', 'main@trigger-cookie-161-next', 'main@trigger-cookie-161-next']],
    'pinned attempted next recursive children' => [static fn (): mixed => array_column($pinned161()['attempted_next_recursive_edges'], 'child_key'), ['rewrite_rules_next_child', 'rewrite_rules_next_child_next_child']],
    'pinned next yield stream hidden' => [static fn (): mixed => $pinned161()['next_yield_stream'], []],
    'pinned current trigger labels' => [static fn (): mixed => array_column($pinned161()['current_trigger_effects'], 'audit_label'), ['current-recursive-trigger-body', 'current-recursive-trigger-body', 'current-recursive-trigger-body', 'current-recursive-trigger-body']],
    'pinned attempted next trigger labels' => [static fn (): mixed => array_column($pinned161()['attempted_next_trigger_effects'], 'audit_label'), ['next-recursive-trigger-body', 'next-recursive-trigger-body', 'next-recursive-trigger-body', 'next-recursive-trigger-body']],
    'pinned boundary' => [static fn (): mixed => $pinned161()['yield_boundary'], 'recursive-view-returning-current-source-drained-before-next-source'],
    'pinned dependency marker' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next161', $pinned161()['dependencies'], true), true],

    'admitted status' => [static fn (): mixed => $admitted161()['status'], 'trigger-recursive-view-returning-next-source-admitted-next161'],
    'admitted visible source is next' => [static fn (): mixed => $admitted161()['visible_view']['source'], 'main@view-cookie-161-next'],
    'admitted next source flag' => [static fn (): mixed => $admitted161()['next_source_admitted'], true],
    'admitted changes include current and next' => [static fn (): mixed => $admitted161()['changes'], 8],
    'admitted next changes counted' => [static fn (): mixed => $admitted161()['next_changes'], 4],
    'admitted statement rows both sources' => [static fn (): mixed => $admitted161()['statement_rows'], 8],
    'admitted next returning names' => [static fn (): mixed => array_column(array_column($admitted161()['next_returning_rows'], 'returning'), 'option_name'), ['rewrite_rules', 'home', 'rewrite_rules_next_child', 'rewrite_rules_next_child_next_child']],
    'admitted next recursive edges visible' => [static fn (): mixed => count($admitted161()['next_recursive_edges']), 2],
    'admitted final names' => [static fn (): mixed => array_column($admitted161()['after_savepoint'], 'option_name'), ['siteurl', 'home', 'plugin_seed', 'plugin_seed_child', 'plugin_seed_child_child', 'rewrite_rules', 'rewrite_rules_next_child', 'rewrite_rules_next_child_next_child']],
    'admitted home next value' => [static fn (): mixed => $admitted161()['after_savepoint'][1]['option_value'], 'https://next-home.test'],
    'admitted rewrite source origin' => [static fn (): mixed => $admitted161()['after_savepoint'][5]['source'], 'next-import'],
    'admitted boundary' => [static fn (): mixed => $admitted161()['yield_boundary'], 'recursive-view-returning-next-source-admitted-after-current-drain'],

    'non recursive status remains pinned' => [static fn (): mixed => $nonRecursive161()['status'], 'trigger-recursive-view-returning-current-source-pinned-next161'],
    'non recursive flag false' => [static fn (): mixed => $nonRecursive161()['recursive_triggers'], false],
    'non recursive current count top level only' => [static fn (): mixed => count($nonRecursive161()['current_returning_rows']), 2],
    'non recursive no current edges' => [static fn (): mixed => $nonRecursive161()['current_recursive_edges'], []],
    'non recursive current names' => [static fn (): mixed => array_column(array_column($nonRecursive161()['current_returning_rows'], 'returning'), 'option_name'), ['plugin_seed', 'siteurl']],
    'depth one returns one child' => [static fn (): mixed => array_column(array_column($depthOne161()['current_returning_rows'], 'returning'), 'option_name'), ['plugin_seed', 'siteurl', 'plugin_seed_child']],
    'depth one edge count' => [static fn (): mixed => count($depthOne161()['current_recursive_edges']), 1],
    'custom savepoint accepted' => [static fn (): mixed => $plan161(['savepoint' => 'wp_custom_recursive_161'])['savepoint'], 'wp_custom_recursive_161'],
    'empty returning throws' => [static fn (): mixed => $plan161([], null, null, null, null, []), InvalidArgumentException::class],
    'bad savepoint throws' => [static fn (): mixed => $plan161(['savepoint' => 'bad savepoint']), InvalidArgumentException::class],
    'bad key throws' => [static fn (): mixed => $plan161(['key' => 'bad-key']), InvalidArgumentException::class],
    'bad max depth throws' => [static fn (): mixed => $plan161(['max_depth' => -1]), InvalidArgumentException::class],
    'bad trigger source throws' => [static fn (): mixed => $plan161([], null, null, ['name' => 'v', 'source' => 'ok', 'trigger' => 'trg', 'trigger_source' => 'bad source', 'columns' => ['name'], 'mapping' => ['name' => 'option_name']]), InvalidArgumentException::class],
    'empty view columns throws' => [static fn (): mixed => $plan161([], null, null, ['name' => 'v', 'source' => 'ok', 'trigger' => 'trg', 'trigger_source' => 'ok', 'columns' => [], 'mapping' => ['name' => 'option_name']]), InvalidArgumentException::class],
    'bad view mapping throws' => [static fn (): mixed => $plan161([], null, null, ['name' => 'v', 'source' => 'ok', 'trigger' => 'trg', 'trigger_source' => 'ok', 'columns' => ['name'], 'mapping' => ['missing' => 'option_name']]), InvalidArgumentException::class],
    'missing recursive mapping throws' => [static fn (): mixed => $plan161([], null, null, ['name' => 'v', 'source' => 'ok', 'trigger' => 'trg', 'trigger_source' => 'ok', 'columns' => ['name'], 'mapping' => ['name' => 'option_name'], 'recursive_column' => 'missing']), InvalidArgumentException::class],
    'missing view column throws' => [static fn (): mixed => $plan161([], [['import_id' => 1, 'value' => 'x', 'autoload_flag' => 'yes']]), InvalidArgumentException::class],
    'duplicate base key throws' => [static fn (): mixed => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext161(array_merge($rows161, [['option_name' => 'siteurl']]), $currentInput161, $nextInput161, $currentView161, $nextView161, $returning161), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases161 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source next161 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
