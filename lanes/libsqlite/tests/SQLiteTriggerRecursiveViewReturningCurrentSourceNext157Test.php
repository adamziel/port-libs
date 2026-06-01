<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows157 = [
    ['key_name' => 'base_url', 'key_value' => 'https://example.test', 'load_policy' => 'yes', 'parent_name' => null],
    ['key_name' => 'module_alpha', 'key_value' => 'enabled', 'load_policy' => 'yes', 'parent_name' => 'base_url'],
    ['key_name' => 'module_alpha_child', 'key_value' => 'child-on', 'load_policy' => 'no', 'parent_name' => 'module_alpha'],
    ['key_name' => 'module_beta', 'key_value' => 'disabled', 'load_policy' => 'yes', 'parent_name' => 'base_url'],
    ['key_name' => 'theme_mods_test', 'key_value' => 'theme', 'load_policy' => 'yes', 'parent_name' => 'base_url'],
    ['key_name' => 'module_next', 'key_value' => 'next-on', 'load_policy' => 'yes', 'parent_name' => 'module_beta'],
];

$currentView157 = [
    'name' => 'app_recursive_setting_view',
    'source' => 'main@view-cookie-157-current',
    'trigger' => 'app_recursive_setting_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-157-current',
    'root_key' => 'root_name',
    'parent_key' => 'parent_name',
    'columns' => ['key_name', 'key_value', 'load_policy', 'parent_name'],
    'where' => static fn (array $row, string $root, int $depth): bool => $depth <= 2 && str_starts_with((string) $row['key_name'], 'module_'),
    'order_by' => 'key_name',
];
$nextView157 = [
    'name' => 'app_recursive_setting_view',
    'source' => 'main@view-cookie-157-next',
    'trigger' => 'app_recursive_setting_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-157-next',
    'root_key' => 'root_name',
    'parent_key' => 'parent_name',
    'columns' => ['key_name', 'key_value', 'load_policy', 'parent_name'],
    'where' => static fn (array $row, string $root, int $depth): bool => $depth <= 3 && str_starts_with((string) $row['key_name'], 'module_'),
    'order_by' => 'key_name',
];
$returning157 = [
    'key_name',
    ['expr' => 'key_value', 'as' => 'value'],
    ['expr' => 'root', 'as' => 'root_name'],
    ['expr' => 'depth', 'as' => 'recursive_depth'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    static fn (array $incoming, array $viewRow, string $triggerSource, int $ordinal): string => $triggerSource . ':' . $viewRow['_root'] . ':' . $ordinal . ':' . $incoming['key_name'],
];

$plan157 = static fn (array $options = [], ?array $currentRoots = null, ?array $nextRoots = null, ?array $currentView = null, ?array $nextView = null, ?array $returning = null): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeRecursiveViewSourceHandoff(
    $rows157,
    $currentRoots ?? [['root_name' => 'base_url']],
    $nextRoots ?? [['root_name' => 'module_beta']],
    $currentView ?? $currentView157,
    $nextView ?? $nextView157,
    $returning ?? $returning157,
    $options + ['savepoint' => 'app_recursive_view_157'],
);

$pinned157 = static fn (): array => $plan157();
$admitted157 = static fn (): array => $plan157(['admit_next_source' => true]);
$limited157 = static fn (): array => $plan157(['max_depth' => 1]);

$cases157 = [
    'pinned status' => [static fn (): mixed => $pinned157()['status'], 'trigger-recursive-view-returning-current-source-pinned-next157'],
    'savepoint retained' => [static fn (): mixed => $pinned157()['savepoint'], 'app_recursive_view_157'],
    'current source' => [static fn (): mixed => $pinned157()['current_view']['source'], 'main@view-cookie-157-current'],
    'current trigger source' => [static fn (): mixed => $pinned157()['current_view']['trigger_source'], 'main@trigger-cookie-157-current'],
    'next source' => [static fn (): mixed => $pinned157()['next_view']['source'], 'main@view-cookie-157-next'],
    'next trigger source' => [static fn (): mixed => $pinned157()['next_view']['trigger_source'], 'main@trigger-cookie-157-next'],
    'visible source pinned current' => [static fn (): mixed => $pinned157()['visible_view']['source'], 'main@view-cookie-157-current'],
    'trigger source changed' => [static fn (): mixed => $pinned157()['trigger_source_changed'], true],
    'next not admitted' => [static fn (): mixed => $pinned157()['next_source_admitted'], false],
    'current recursive names' => [static fn (): mixed => array_column($pinned157()['current_recursive_rows'], 'key_name'), ['module_alpha', 'module_beta', 'module_alpha_child', 'module_next']],
    'current recursive roots' => [static fn (): mixed => array_values(array_unique(array_column($pinned157()['current_recursive_rows'], '_root'))), ['base_url']],
    'current recursive depths' => [static fn (): mixed => array_column($pinned157()['current_recursive_rows'], '_depth'), [1, 1, 2, 2]],
    'current filter omits theme' => [static fn (): mixed => in_array('theme_mods_test', array_column($pinned157()['current_recursive_rows'], 'key_name'), true), false],
    'current returning count' => [static fn (): mixed => count($pinned157()['current_returning_rows']), 4],
    'current returning audit names' => [static fn (): mixed => array_column(array_column($pinned157()['current_returning_rows'], 'returning'), 'key_name'), ['audit:current:base_url:module_alpha', 'audit:current:base_url:module_beta', 'audit:current:base_url:module_alpha_child', 'audit:current:base_url:module_next']],
    'current returning roots' => [static fn (): mixed => array_column(array_column($pinned157()['current_returning_rows'], 'returning'), 'root_name'), ['base_url', 'base_url', 'base_url', 'base_url']],
    'current returning depths' => [static fn (): mixed => array_column(array_column($pinned157()['current_returning_rows'], 'returning'), 'recursive_depth'), [1, 1, 2, 2]],
    'current returning trigger aliases' => [static fn (): mixed => array_values(array_unique(array_column(array_column($pinned157()['current_returning_rows'], 'returning'), 'trigger_source_alias'))), ['main@trigger-cookie-157-current']],
    'callable trace first' => [static fn (): mixed => $pinned157()['current_returning_rows'][0]['returning']['expr6'], 'main@trigger-cookie-157-current:base_url:0:audit:current:base_url:module_alpha'],
    'current yield source' => [static fn (): mixed => array_values(array_unique(array_column($pinned157()['current_yield_stream'], 'source'))), ['main@view-cookie-157-current']],
    'current yield trigger' => [static fn (): mixed => array_values(array_unique(array_column($pinned157()['current_yield_stream'], 'trigger'))), ['app_recursive_setting_view_io_insert']],
    'current rows include audit inserts' => [static fn (): mixed => array_slice(array_column($pinned157()['current_rows'], 'key_name'), -4), ['audit:current:base_url:module_alpha', 'audit:current:base_url:module_beta', 'audit:current:base_url:module_alpha_child', 'audit:current:base_url:module_next']],
    'current audit parent' => [static fn (): mixed => $pinned157()['current_rows'][6]['parent_name'], 'base_url'],
    'current changes count' => [static fn (): mixed => $pinned157()['current_changes'], 4],
    'changes pinned zero' => [static fn (): mixed => $pinned157()['changes'], 0],
    'statement rows current only' => [static fn (): mixed => $pinned157()['statement_rows'], 4],
    'attempted rows include next attempt' => [static fn (): mixed => $pinned157()['attempted_statement_rows'], 5],
    'next returning suppressed' => [static fn (): mixed => $pinned157()['next_returning_rows'], []],
    'next recursive suppressed' => [static fn (): mixed => $pinned157()['next_recursive_rows'], []],
    'attempted next recursive names' => [static fn (): mixed => array_column($pinned157()['attempted_next_recursive_rows'], 'key_name'), ['module_next']],
    'attempted next returning names' => [static fn (): mixed => array_column(array_column($pinned157()['attempted_next_returning_rows'], 'returning'), 'key_name'), ['audit:next:module_beta:module_next']],
    'attempted next trigger alias' => [static fn (): mixed => $pinned157()['attempted_next_returning_rows'][0]['returning']['trigger_source_alias'], 'main@trigger-cookie-157-next'],
    'after savepoint restores base' => [static fn (): mixed => array_column($pinned157()['after_savepoint'], 'key_name'), array_column($rows157, 'key_name')],
    'yield boundary pinned' => [static fn (): mixed => $pinned157()['yield_boundary'], 'recursive-view-current-returning-drained-before-next-source'],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next157', $pinned157()['dependencies'], true), true],
    'materialization dependency' => [static fn (): mixed => in_array('sqlite-recursive-view-source-materialization', $pinned157()['dependencies'], true), true],
    'attempted only dependency' => [static fn (): mixed => in_array('sqlite-next-recursive-view-source-attempted-only', $pinned157()['dependencies'], true), true],

    'admitted status' => [static fn (): mixed => $admitted157()['status'], 'trigger-recursive-view-returning-next-source-admitted-next157'],
    'admitted visible source next' => [static fn (): mixed => $admitted157()['visible_view']['source'], 'main@view-cookie-157-next'],
    'admitted flag' => [static fn (): mixed => $admitted157()['next_source_admitted'], true],
    'admitted next recursive names' => [static fn (): mixed => array_column($admitted157()['next_recursive_rows'], 'key_name'), ['module_next']],
    'admitted next returning names' => [static fn (): mixed => array_column(array_column($admitted157()['next_returning_rows'], 'returning'), 'key_name'), ['audit:next:module_beta:module_next']],
    'admitted next changes' => [static fn (): mixed => $admitted157()['next_changes'], 1],
    'admitted total changes' => [static fn (): mixed => $admitted157()['changes'], 5],
    'admitted statement rows' => [static fn (): mixed => $admitted157()['statement_rows'], 5],
    'admitted after names tail' => [static fn (): mixed => array_slice(array_column($admitted157()['after_savepoint'], 'key_name'), -5), ['audit:current:base_url:module_alpha', 'audit:current:base_url:module_beta', 'audit:current:base_url:module_alpha_child', 'audit:current:base_url:module_next', 'audit:next:module_beta:module_next']],
    'admitted boundary' => [static fn (): mixed => $admitted157()['yield_boundary'], 'recursive-view-current-returning-drained-then-next-source-admitted'],

    'limited current names' => [static fn (): mixed => array_column($limited157()['current_recursive_rows'], 'key_name'), ['module_alpha', 'module_beta']],
    'limited current depths' => [static fn (): mixed => $limited157()['recursive_depths']['current'], [1, 1]],
    'custom savepoint' => [static fn (): mixed => $plan157(['savepoint' => 'app_custom_recursive_view_157'])['savepoint'], 'app_custom_recursive_view_157'],
    'empty returning throws' => [static fn (): mixed => $plan157([], null, null, null, null, []), InvalidArgumentException::class],
    'bad savepoint throws' => [static fn (): mixed => $plan157(['savepoint' => 'bad savepoint']), InvalidArgumentException::class],
    'bad max depth throws' => [static fn (): mixed => $plan157(['max_depth' => 0]), InvalidArgumentException::class],
    'bad source throws' => [static fn (): mixed => $plan157([], null, null, ['name' => 'v', 'source' => 'bad source', 'trigger' => 'trg', 'trigger_source' => 'ok', 'root_key' => 'root_name', 'parent_key' => 'parent_name', 'columns' => ['key_name']]), InvalidArgumentException::class],
    'empty columns throws' => [static fn (): mixed => $plan157([], null, null, ['name' => 'v', 'source' => 'ok', 'trigger' => 'trg', 'trigger_source' => 'ok', 'root_key' => 'root_name', 'parent_key' => 'parent_name', 'columns' => []]), InvalidArgumentException::class],
    'bad where throws' => [static fn (): mixed => $plan157([], null, null, ['name' => 'v', 'source' => 'ok', 'trigger' => 'trg', 'trigger_source' => 'ok', 'root_key' => 'root_name', 'parent_key' => 'parent_name', 'columns' => ['key_name'], 'where' => 'nope']), InvalidArgumentException::class],
    'missing root key throws' => [static fn (): mixed => $plan157([], [['missing' => 'base_url']]), InvalidArgumentException::class],
    'duplicate key throws' => [static fn (): mixed => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeRecursiveViewSourceHandoff(array_merge($rows157, [['key_name' => 'base_url']]), [], [], $currentView157, $nextView157, $returning157), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases157 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source next157 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
