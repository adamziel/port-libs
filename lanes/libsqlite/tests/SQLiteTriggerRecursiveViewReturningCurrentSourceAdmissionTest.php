<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rowsAdmission = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes', 'source' => 'seed'],
    ['setting_id' => 2, 'key_name' => 'landing_url', 'key_value' => 'https://landing_url.test', 'load_policy' => 'yes', 'source' => 'seed'],
];
$currentViewAdmission = [
    'name' => 'app_setting_import_view',
    'source' => 'main@view-cookie-admission-current',
    'trigger' => 'app_setting_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-admission-current',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_child',
    'audit_label' => 'current-recursive-trigger-body',
];
$nextViewAdmission = [
    'name' => 'app_setting_import_view',
    'source' => 'main@view-cookie-admission-next',
    'trigger' => 'app_setting_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-admission-next',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag', 'origin'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy', 'origin' => 'source'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_next_child',
    'audit_label' => 'next-recursive-trigger-body',
];
$currentInputAdmission = [
    ['import_id' => 10, 'name' => 'module_seed', 'value' => 'enabled', 'load_policy_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'base_url', 'value' => 'https://current.test', 'load_policy_flag' => 'yes', 'spawn_child' => false],
];
$nextInputAdmission = [
    ['import_id' => 20, 'name' => 'routing_rules', 'value' => 'cached', 'load_policy_flag' => 'yes', 'origin' => 'next-import', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'landing_url', 'value' => 'https://next-landing_url.test', 'load_policy_flag' => 'yes', 'origin' => 'next-import', 'spawn_child' => false],
];
$returningAdmission = [
    'new.key_name',
    ['expr' => 'new.key_value', 'as' => 'value'],
    ['expr' => 'view.name', 'as' => 'view_name'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    static fn (array $new, array $viewRow, string $event, int $ordinal, int $depth, string $source): string => $source . ':' . $event . ':' . $ordinal . ':' . $depth . ':' . $viewRow['name'] . '>' . $new['key_name'],
];

$planAdmission = static fn (array $options = [], ?array $currentInput = null, ?array $nextInput = null, ?array $currentView = null, ?array $nextView = null, ?array $returning = null): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeRecursiveViewCurrentSourceAdmission(
    $rowsAdmission,
    $currentInput ?? $currentInputAdmission,
    $nextInput ?? $nextInputAdmission,
    $currentView ?? $currentViewAdmission,
    $nextView ?? $nextViewAdmission,
    $returning ?? $returningAdmission,
    $options + ['key' => 'key_name', 'savepoint' => 'app_recursive_view_admission', 'max_depth' => 2],
);

$pinnedAdmission = static fn (): array => $planAdmission();
$admittedAdmission = static fn (): array => $planAdmission(['admit_next_source' => true]);
$nonRecursiveAdmission = static fn (): array => $planAdmission(['recursive_triggers' => false]);
$depthOneAdmission = static fn (): array => $planAdmission(['max_depth' => 1]);

$casesAdmission = [
    'pinned status' => [static fn (): mixed => $pinnedAdmission()['status'], 'trigger-recursive-view-returning-current-source-pinned'],
    'pinned savepoint' => [static fn (): mixed => $pinnedAdmission()['savepoint'], 'app_recursive_view_admission'],
    'pinned key' => [static fn (): mixed => $pinnedAdmission()['key'], 'key_name'],
    'pinned recursive enabled' => [static fn (): mixed => $pinnedAdmission()['recursive_triggers'], true],
    'pinned max depth' => [static fn (): mixed => $pinnedAdmission()['max_depth'], 2],
    'pinned current view source' => [static fn (): mixed => $pinnedAdmission()['current_view']['source'], 'main@view-cookie-admission-current'],
    'pinned next view source' => [static fn (): mixed => $pinnedAdmission()['next_view']['source'], 'main@view-cookie-admission-next'],
    'pinned current trigger source' => [static fn (): mixed => $pinnedAdmission()['current_view']['trigger_source'], 'main@trigger-cookie-admission-current'],
    'pinned next trigger source' => [static fn (): mixed => $pinnedAdmission()['next_view']['trigger_source'], 'main@trigger-cookie-admission-next'],
    'pinned visible source remains current' => [static fn (): mixed => $pinnedAdmission()['visible_view']['source'], 'main@view-cookie-admission-current'],
    'pinned trigger source changed' => [static fn (): mixed => $pinnedAdmission()['trigger_source_changed'], true],
    'pinned next source not admitted' => [static fn (): mixed => $pinnedAdmission()['next_source_admitted'], false],
    'pinned current mapping name' => [static fn (): mixed => $pinnedAdmission()['current_view']['mapping']['name'], 'key_name'],
    'pinned next mapping origin' => [static fn (): mixed => $pinnedAdmission()['next_view']['mapping']['origin'], 'source'],
    'pinned recursive suffix' => [static fn (): mixed => $pinnedAdmission()['current_view']['recursive_suffix'], '_child'],
    'pinned changes suppressed at boundary' => [static fn (): mixed => $pinnedAdmission()['changes'], 0],
    'pinned current changes counted' => [static fn (): mixed => $pinnedAdmission()['current_changes'], 4],
    'pinned next changes suppressed' => [static fn (): mixed => $pinnedAdmission()['next_changes'], 0],
    'pinned attempted next changes counted' => [static fn (): mixed => $pinnedAdmission()['attempted_next_changes'], 4],
    'pinned statement rows current recursive' => [static fn (): mixed => $pinnedAdmission()['statement_rows'], 4],
    'pinned attempted statement rows both sources' => [static fn (): mixed => $pinnedAdmission()['attempted_statement_rows'], 8],
    'pinned current returning count' => [static fn (): mixed => count($pinnedAdmission()['current_returning_rows']), 4],
    'pinned current returning names' => [static fn (): mixed => array_column(array_column($pinnedAdmission()['current_returning_rows'], 'returning'), 'key_name'), ['module_seed', 'base_url', 'module_seed_child', 'module_seed_child_child']],
    'pinned current returning depths' => [static fn (): mixed => array_column(array_column($pinnedAdmission()['current_returning_rows'], 'returning'), 'depth_value'), [0, 0, 1, 2]],
    'pinned current returning trigger aliases' => [static fn (): mixed => array_column(array_column($pinnedAdmission()['current_returning_rows'], 'returning'), 'trigger_source_alias'), ['main@trigger-cookie-admission-current', 'main@trigger-cookie-admission-current', 'main@trigger-cookie-admission-current', 'main@trigger-cookie-admission-current']],
    'pinned callable traces' => [static fn (): mixed => array_column(array_column($pinnedAdmission()['current_returning_rows'], 'returning'), 'expr7'), ['main@trigger-cookie-admission-current:insert:0:0:module_seed>module_seed', 'main@trigger-cookie-admission-current:update:1:0:base_url>base_url', 'main@trigger-cookie-admission-current:insert:2:1:module_seed_child>module_seed_child', 'main@trigger-cookie-admission-current:insert:3:2:module_seed_child_child>module_seed_child_child']],
    'pinned current yield depths' => [static fn (): mixed => array_column($pinnedAdmission()['current_yield_stream'], 'depth'), [0, 0, 1, 2]],
    'pinned current yield parent keys' => [static fn (): mixed => array_column($pinnedAdmission()['current_yield_stream'], 'parent_key'), [null, null, 'module_seed', 'module_seed_child']],
    'pinned current recursive edge count' => [static fn (): mixed => count($pinnedAdmission()['current_recursive_edges']), 2],
    'pinned current recursive child keys' => [static fn (): mixed => array_column($pinnedAdmission()['current_recursive_edges'], 'child_key'), ['module_seed_child', 'module_seed_child_child']],
    'pinned current rows include recursive children' => [static fn (): mixed => array_column($pinnedAdmission()['current_rows'], 'key_name'), ['base_url', 'landing_url', 'module_seed', 'module_seed_child', 'module_seed_child_child']],
    'pinned current base_url updated' => [static fn (): mixed => $pinnedAdmission()['current_rows'][0]['key_value'], 'https://current.test'],
    'pinned after savepoint restores base names' => [static fn (): mixed => array_column($pinnedAdmission()['after_savepoint'], 'key_name'), ['base_url', 'landing_url']],
    'pinned after savepoint restores base value' => [static fn (): mixed => $pinnedAdmission()['after_savepoint'][0]['key_value'], 'https://old.test'],
    'pinned next returning suppressed' => [static fn (): mixed => $pinnedAdmission()['next_returning_rows'], []],
    'pinned attempted next returning names' => [static fn (): mixed => array_column(array_column($pinnedAdmission()['attempted_next_returning_rows'], 'returning'), 'key_name'), ['routing_rules', 'landing_url', 'routing_rules_next_child', 'routing_rules_next_child_next_child']],
    'pinned attempted next depths' => [static fn (): mixed => array_column(array_column($pinnedAdmission()['attempted_next_returning_rows'], 'returning'), 'depth_value'), [0, 0, 1, 2]],
    'pinned attempted next source token' => [static fn (): mixed => array_column(array_column($pinnedAdmission()['attempted_next_returning_rows'], 'returning'), 'trigger_source_alias'), ['main@trigger-cookie-admission-next', 'main@trigger-cookie-admission-next', 'main@trigger-cookie-admission-next', 'main@trigger-cookie-admission-next']],
    'pinned attempted next recursive children' => [static fn (): mixed => array_column($pinnedAdmission()['attempted_next_recursive_edges'], 'child_key'), ['routing_rules_next_child', 'routing_rules_next_child_next_child']],
    'pinned next yield stream hidden' => [static fn (): mixed => $pinnedAdmission()['next_yield_stream'], []],
    'pinned current trigger labels' => [static fn (): mixed => array_column($pinnedAdmission()['current_trigger_effects'], 'audit_label'), ['current-recursive-trigger-body', 'current-recursive-trigger-body', 'current-recursive-trigger-body', 'current-recursive-trigger-body']],
    'pinned attempted next trigger labels' => [static fn (): mixed => array_column($pinnedAdmission()['attempted_next_trigger_effects'], 'audit_label'), ['next-recursive-trigger-body', 'next-recursive-trigger-body', 'next-recursive-trigger-body', 'next-recursive-trigger-body']],
    'pinned boundary' => [static fn (): mixed => $pinnedAdmission()['yield_boundary'], 'recursive-view-returning-current-source-drained-before-next-source'],
    'pinned dependency marker' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-admission', $pinnedAdmission()['dependencies'], true), true],

    'admitted status' => [static fn (): mixed => $admittedAdmission()['status'], 'trigger-recursive-view-returning-next-source-admitted'],
    'admitted visible source is next' => [static fn (): mixed => $admittedAdmission()['visible_view']['source'], 'main@view-cookie-admission-next'],
    'admitted next source flag' => [static fn (): mixed => $admittedAdmission()['next_source_admitted'], true],
    'admitted changes include current and next' => [static fn (): mixed => $admittedAdmission()['changes'], 8],
    'admitted next changes counted' => [static fn (): mixed => $admittedAdmission()['next_changes'], 4],
    'admitted statement rows both sources' => [static fn (): mixed => $admittedAdmission()['statement_rows'], 8],
    'admitted next returning names' => [static fn (): mixed => array_column(array_column($admittedAdmission()['next_returning_rows'], 'returning'), 'key_name'), ['routing_rules', 'landing_url', 'routing_rules_next_child', 'routing_rules_next_child_next_child']],
    'admitted next recursive edges visible' => [static fn (): mixed => count($admittedAdmission()['next_recursive_edges']), 2],
    'admitted final names' => [static fn (): mixed => array_column($admittedAdmission()['after_savepoint'], 'key_name'), ['base_url', 'landing_url', 'module_seed', 'module_seed_child', 'module_seed_child_child', 'routing_rules', 'routing_rules_next_child', 'routing_rules_next_child_next_child']],
    'admitted landing_url next value' => [static fn (): mixed => $admittedAdmission()['after_savepoint'][1]['key_value'], 'https://next-landing_url.test'],
    'admitted rewrite source origin' => [static fn (): mixed => $admittedAdmission()['after_savepoint'][5]['source'], 'next-import'],
    'admitted boundary' => [static fn (): mixed => $admittedAdmission()['yield_boundary'], 'recursive-view-returning-next-source-admitted-after-current-drain'],

    'non recursive status remains pinned' => [static fn (): mixed => $nonRecursiveAdmission()['status'], 'trigger-recursive-view-returning-current-source-pinned'],
    'non recursive flag false' => [static fn (): mixed => $nonRecursiveAdmission()['recursive_triggers'], false],
    'non recursive current count top level only' => [static fn (): mixed => count($nonRecursiveAdmission()['current_returning_rows']), 2],
    'non recursive no current edges' => [static fn (): mixed => $nonRecursiveAdmission()['current_recursive_edges'], []],
    'non recursive current names' => [static fn (): mixed => array_column(array_column($nonRecursiveAdmission()['current_returning_rows'], 'returning'), 'key_name'), ['module_seed', 'base_url']],
    'depth one returns one child' => [static fn (): mixed => array_column(array_column($depthOneAdmission()['current_returning_rows'], 'returning'), 'key_name'), ['module_seed', 'base_url', 'module_seed_child']],
    'depth one edge count' => [static fn (): mixed => count($depthOneAdmission()['current_recursive_edges']), 1],
    'custom savepoint accepted' => [static fn (): mixed => $planAdmission(['savepoint' => 'app_custom_recursive_admission'])['savepoint'], 'app_custom_recursive_admission'],
    'empty returning throws' => [static fn (): mixed => $planAdmission([], null, null, null, null, []), InvalidArgumentException::class],
    'bad savepoint throws' => [static fn (): mixed => $planAdmission(['savepoint' => 'bad savepoint']), InvalidArgumentException::class],
    'bad key throws' => [static fn (): mixed => $planAdmission(['key' => 'bad-key']), InvalidArgumentException::class],
    'bad max depth throws' => [static fn (): mixed => $planAdmission(['max_depth' => -1]), InvalidArgumentException::class],
    'bad trigger source throws' => [static fn (): mixed => $planAdmission([], null, null, ['name' => 'v', 'source' => 'ok', 'trigger' => 'trg', 'trigger_source' => 'bad source', 'columns' => ['name'], 'mapping' => ['name' => 'key_name']]), InvalidArgumentException::class],
    'empty view columns throws' => [static fn (): mixed => $planAdmission([], null, null, ['name' => 'v', 'source' => 'ok', 'trigger' => 'trg', 'trigger_source' => 'ok', 'columns' => [], 'mapping' => ['name' => 'key_name']]), InvalidArgumentException::class],
    'bad view mapping throws' => [static fn (): mixed => $planAdmission([], null, null, ['name' => 'v', 'source' => 'ok', 'trigger' => 'trg', 'trigger_source' => 'ok', 'columns' => ['name'], 'mapping' => ['missing' => 'key_name']]), InvalidArgumentException::class],
    'missing recursive mapping throws' => [static fn (): mixed => $planAdmission([], null, null, ['name' => 'v', 'source' => 'ok', 'trigger' => 'trg', 'trigger_source' => 'ok', 'columns' => ['name'], 'mapping' => ['name' => 'key_name'], 'recursive_column' => 'missing']), InvalidArgumentException::class],
    'missing view column throws' => [static fn (): mixed => $planAdmission([], [['import_id' => 1, 'value' => 'x', 'load_policy_flag' => 'yes']]), InvalidArgumentException::class],
    'duplicate base key throws' => [static fn (): mixed => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeRecursiveViewCurrentSourceAdmission(array_merge($rowsAdmission, [['key_name' => 'base_url']]), $currentInputAdmission, $nextInputAdmission, $currentViewAdmission, $nextViewAdmission, $returningAdmission), InvalidArgumentException::class],
];

$tests = [];
foreach ($casesAdmission as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source admission ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
