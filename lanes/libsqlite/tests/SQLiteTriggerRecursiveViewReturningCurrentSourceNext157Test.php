<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows157 = [
    ['option_name' => 'siteurl', 'option_value' => 'https://example.test', 'autoload' => 'yes', 'parent_name' => null],
    ['option_name' => 'plugin_alpha', 'option_value' => 'enabled', 'autoload' => 'yes', 'parent_name' => 'siteurl'],
    ['option_name' => 'plugin_alpha_child', 'option_value' => 'child-on', 'autoload' => 'no', 'parent_name' => 'plugin_alpha'],
    ['option_name' => 'plugin_beta', 'option_value' => 'disabled', 'autoload' => 'yes', 'parent_name' => 'siteurl'],
    ['option_name' => 'theme_mods_test', 'option_value' => 'theme', 'autoload' => 'yes', 'parent_name' => 'siteurl'],
    ['option_name' => 'plugin_next', 'option_value' => 'next-on', 'autoload' => 'yes', 'parent_name' => 'plugin_beta'],
];

$currentView157 = [
    'name' => 'wp_recursive_option_view',
    'source' => 'main@view-cookie-157-current',
    'trigger' => 'wp_recursive_option_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-157-current',
    'root_key' => 'root_name',
    'parent_key' => 'parent_name',
    'columns' => ['option_name', 'option_value', 'autoload', 'parent_name'],
    'where' => static fn (array $row, string $root, int $depth): bool => $depth <= 2 && str_starts_with((string) $row['option_name'], 'plugin_'),
    'order_by' => 'option_name',
];
$nextView157 = [
    'name' => 'wp_recursive_option_view',
    'source' => 'main@view-cookie-157-next',
    'trigger' => 'wp_recursive_option_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-157-next',
    'root_key' => 'root_name',
    'parent_key' => 'parent_name',
    'columns' => ['option_name', 'option_value', 'autoload', 'parent_name'],
    'where' => static fn (array $row, string $root, int $depth): bool => $depth <= 3 && str_starts_with((string) $row['option_name'], 'plugin_'),
    'order_by' => 'option_name',
];
$returning157 = [
    'option_name',
    ['expr' => 'option_value', 'as' => 'value'],
    ['expr' => 'root', 'as' => 'root_name'],
    ['expr' => 'depth', 'as' => 'recursive_depth'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    static fn (array $incoming, array $viewRow, string $triggerSource, int $ordinal): string => $triggerSource . ':' . $viewRow['_root'] . ':' . $ordinal . ':' . $incoming['option_name'],
];

$plan157 = static fn (array $options = [], ?array $currentRoots = null, ?array $nextRoots = null, ?array $currentView = null, ?array $nextView = null, ?array $returning = null): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext157(
    $rows157,
    $currentRoots ?? [['root_name' => 'siteurl']],
    $nextRoots ?? [['root_name' => 'plugin_beta']],
    $currentView ?? $currentView157,
    $nextView ?? $nextView157,
    $returning ?? $returning157,
    $options + ['savepoint' => 'wp_recursive_view_157'],
);

$pinned157 = static fn (): array => $plan157();
$admitted157 = static fn (): array => $plan157(['admit_next_source' => true]);
$limited157 = static fn (): array => $plan157(['max_depth' => 1]);

$cases157 = [
    'pinned status' => [static fn (): mixed => $pinned157()['status'], 'trigger-recursive-view-returning-current-source-pinned-next157'],
    'savepoint retained' => [static fn (): mixed => $pinned157()['savepoint'], 'wp_recursive_view_157'],
    'current source' => [static fn (): mixed => $pinned157()['current_view']['source'], 'main@view-cookie-157-current'],
    'current trigger source' => [static fn (): mixed => $pinned157()['current_view']['trigger_source'], 'main@trigger-cookie-157-current'],
    'next source' => [static fn (): mixed => $pinned157()['next_view']['source'], 'main@view-cookie-157-next'],
    'next trigger source' => [static fn (): mixed => $pinned157()['next_view']['trigger_source'], 'main@trigger-cookie-157-next'],
    'visible source pinned current' => [static fn (): mixed => $pinned157()['visible_view']['source'], 'main@view-cookie-157-current'],
    'trigger source changed' => [static fn (): mixed => $pinned157()['trigger_source_changed'], true],
    'next not admitted' => [static fn (): mixed => $pinned157()['next_source_admitted'], false],
    'current recursive names' => [static fn (): mixed => array_column($pinned157()['current_recursive_rows'], 'option_name'), ['plugin_alpha', 'plugin_beta', 'plugin_alpha_child', 'plugin_next']],
    'current recursive roots' => [static fn (): mixed => array_values(array_unique(array_column($pinned157()['current_recursive_rows'], '_root'))), ['siteurl']],
    'current recursive depths' => [static fn (): mixed => array_column($pinned157()['current_recursive_rows'], '_depth'), [1, 1, 2, 2]],
    'current filter omits theme' => [static fn (): mixed => in_array('theme_mods_test', array_column($pinned157()['current_recursive_rows'], 'option_name'), true), false],
    'current returning count' => [static fn (): mixed => count($pinned157()['current_returning_rows']), 4],
    'current returning audit names' => [static fn (): mixed => array_column(array_column($pinned157()['current_returning_rows'], 'returning'), 'option_name'), ['audit:current:siteurl:plugin_alpha', 'audit:current:siteurl:plugin_beta', 'audit:current:siteurl:plugin_alpha_child', 'audit:current:siteurl:plugin_next']],
    'current returning roots' => [static fn (): mixed => array_column(array_column($pinned157()['current_returning_rows'], 'returning'), 'root_name'), ['siteurl', 'siteurl', 'siteurl', 'siteurl']],
    'current returning depths' => [static fn (): mixed => array_column(array_column($pinned157()['current_returning_rows'], 'returning'), 'recursive_depth'), [1, 1, 2, 2]],
    'current returning trigger aliases' => [static fn (): mixed => array_values(array_unique(array_column(array_column($pinned157()['current_returning_rows'], 'returning'), 'trigger_source_alias'))), ['main@trigger-cookie-157-current']],
    'callable trace first' => [static fn (): mixed => $pinned157()['current_returning_rows'][0]['returning']['expr6'], 'main@trigger-cookie-157-current:siteurl:0:audit:current:siteurl:plugin_alpha'],
    'current yield source' => [static fn (): mixed => array_values(array_unique(array_column($pinned157()['current_yield_stream'], 'source'))), ['main@view-cookie-157-current']],
    'current yield trigger' => [static fn (): mixed => array_values(array_unique(array_column($pinned157()['current_yield_stream'], 'trigger'))), ['wp_recursive_option_view_io_insert']],
    'current rows include audit inserts' => [static fn (): mixed => array_slice(array_column($pinned157()['current_rows'], 'option_name'), -4), ['audit:current:siteurl:plugin_alpha', 'audit:current:siteurl:plugin_beta', 'audit:current:siteurl:plugin_alpha_child', 'audit:current:siteurl:plugin_next']],
    'current audit parent' => [static fn (): mixed => $pinned157()['current_rows'][6]['parent_name'], 'siteurl'],
    'current changes count' => [static fn (): mixed => $pinned157()['current_changes'], 4],
    'changes pinned zero' => [static fn (): mixed => $pinned157()['changes'], 0],
    'statement rows current only' => [static fn (): mixed => $pinned157()['statement_rows'], 4],
    'attempted rows include next attempt' => [static fn (): mixed => $pinned157()['attempted_statement_rows'], 5],
    'next returning suppressed' => [static fn (): mixed => $pinned157()['next_returning_rows'], []],
    'next recursive suppressed' => [static fn (): mixed => $pinned157()['next_recursive_rows'], []],
    'attempted next recursive names' => [static fn (): mixed => array_column($pinned157()['attempted_next_recursive_rows'], 'option_name'), ['plugin_next']],
    'attempted next returning names' => [static fn (): mixed => array_column(array_column($pinned157()['attempted_next_returning_rows'], 'returning'), 'option_name'), ['audit:next:plugin_beta:plugin_next']],
    'attempted next trigger alias' => [static fn (): mixed => $pinned157()['attempted_next_returning_rows'][0]['returning']['trigger_source_alias'], 'main@trigger-cookie-157-next'],
    'after savepoint restores base' => [static fn (): mixed => array_column($pinned157()['after_savepoint'], 'option_name'), array_column($rows157, 'option_name')],
    'yield boundary pinned' => [static fn (): mixed => $pinned157()['yield_boundary'], 'recursive-view-current-returning-drained-before-next-source'],
    'dependency marker' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next157', $pinned157()['dependencies'], true), true],
    'materialization dependency' => [static fn (): mixed => in_array('sqlite-recursive-view-source-materialization', $pinned157()['dependencies'], true), true],
    'attempted only dependency' => [static fn (): mixed => in_array('sqlite-next-recursive-view-source-attempted-only', $pinned157()['dependencies'], true), true],

    'admitted status' => [static fn (): mixed => $admitted157()['status'], 'trigger-recursive-view-returning-next-source-admitted-next157'],
    'admitted visible source next' => [static fn (): mixed => $admitted157()['visible_view']['source'], 'main@view-cookie-157-next'],
    'admitted flag' => [static fn (): mixed => $admitted157()['next_source_admitted'], true],
    'admitted next recursive names' => [static fn (): mixed => array_column($admitted157()['next_recursive_rows'], 'option_name'), ['plugin_next']],
    'admitted next returning names' => [static fn (): mixed => array_column(array_column($admitted157()['next_returning_rows'], 'returning'), 'option_name'), ['audit:next:plugin_beta:plugin_next']],
    'admitted next changes' => [static fn (): mixed => $admitted157()['next_changes'], 1],
    'admitted total changes' => [static fn (): mixed => $admitted157()['changes'], 5],
    'admitted statement rows' => [static fn (): mixed => $admitted157()['statement_rows'], 5],
    'admitted after names tail' => [static fn (): mixed => array_slice(array_column($admitted157()['after_savepoint'], 'option_name'), -5), ['audit:current:siteurl:plugin_alpha', 'audit:current:siteurl:plugin_beta', 'audit:current:siteurl:plugin_alpha_child', 'audit:current:siteurl:plugin_next', 'audit:next:plugin_beta:plugin_next']],
    'admitted boundary' => [static fn (): mixed => $admitted157()['yield_boundary'], 'recursive-view-current-returning-drained-then-next-source-admitted'],

    'limited current names' => [static fn (): mixed => array_column($limited157()['current_recursive_rows'], 'option_name'), ['plugin_alpha', 'plugin_beta']],
    'limited current depths' => [static fn (): mixed => $limited157()['recursive_depths']['current'], [1, 1]],
    'custom savepoint' => [static fn (): mixed => $plan157(['savepoint' => 'wp_custom_recursive_view_157'])['savepoint'], 'wp_custom_recursive_view_157'],
    'empty returning throws' => [static fn (): mixed => $plan157([], null, null, null, null, []), InvalidArgumentException::class],
    'bad savepoint throws' => [static fn (): mixed => $plan157(['savepoint' => 'bad savepoint']), InvalidArgumentException::class],
    'bad max depth throws' => [static fn (): mixed => $plan157(['max_depth' => 0]), InvalidArgumentException::class],
    'bad source throws' => [static fn (): mixed => $plan157([], null, null, ['name' => 'v', 'source' => 'bad source', 'trigger' => 'trg', 'trigger_source' => 'ok', 'root_key' => 'root_name', 'parent_key' => 'parent_name', 'columns' => ['option_name']]), InvalidArgumentException::class],
    'empty columns throws' => [static fn (): mixed => $plan157([], null, null, ['name' => 'v', 'source' => 'ok', 'trigger' => 'trg', 'trigger_source' => 'ok', 'root_key' => 'root_name', 'parent_key' => 'parent_name', 'columns' => []]), InvalidArgumentException::class],
    'bad where throws' => [static fn (): mixed => $plan157([], null, null, ['name' => 'v', 'source' => 'ok', 'trigger' => 'trg', 'trigger_source' => 'ok', 'root_key' => 'root_name', 'parent_key' => 'parent_name', 'columns' => ['option_name'], 'where' => 'nope']), InvalidArgumentException::class],
    'missing root key throws' => [static fn (): mixed => $plan157([], [['missing' => 'siteurl']]), InvalidArgumentException::class],
    'duplicate option throws' => [static fn (): mixed => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeNext157(array_merge($rows157, [['option_name' => 'siteurl']]), [], [], $currentView157, $nextView157, $returning157), InvalidArgumentException::class],
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
