<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows172 = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes', 'generation' => 0, 'source_phase' => 'seed'],
    ['setting_id' => 2, 'key_name' => 'landing_url', 'key_value' => 'https://landing_url.test', 'load_policy' => 'yes', 'generation' => 0, 'source_phase' => 'seed'],
];
$currentView172 = [
    'name' => 'app_recursive_setting_import',
    'source' => 'main@view-cookie-172-current',
    'trigger' => 'app_recursive_setting_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-172-current',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag', 'spawn_child'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy'],
    'audit_label' => 'current-recursive-view-trigger',
];
$nextView172 = [
    'name' => 'app_recursive_setting_import',
    'source' => 'main@view-cookie-172-next',
    'trigger' => 'app_recursive_setting_import_io_insert',
    'trigger_source' => 'main@trigger-cookie-172-next',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag', 'spawn_child', 'origin'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy'],
    'audit_label' => 'next-recursive-view-trigger',
];
$currentInput172 = [
    ['import_id' => 10, 'name' => 'base_url', 'value' => 'https://current.test', 'load_policy_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'current_module', 'value' => 'enabled', 'load_policy_flag' => 'no', 'spawn_child' => true],
];
$nextInput172 = [
    ['import_id' => 20, 'name' => 'landing_url', 'value' => 'https://next.test', 'load_policy_flag' => 'yes', 'spawn_child' => true, 'origin' => 'next-source'],
    ['import_id' => 21, 'name' => 'next_module', 'value' => 'active', 'load_policy_flag' => 'no', 'spawn_child' => false, 'origin' => 'next-source'],
];
$returning172 = [
    ['expr' => 'new.key_name', 'as' => 'name'],
    ['expr' => 'new.key_value', 'as' => 'value'],
    ['expr' => 'old.key_value', 'as' => 'old_value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    static fn (array $new, ?array $old, array $incoming, string $event, int $ordinal, int $depth, string $source): string => $source . ':' . $event . ':' . $ordinal . ':' . $depth . ':' . ($old['key_name'] ?? 'new') . '>' . $new['key_name'],
];

$plan172 = static fn (array $options = [], ?array $currentInput = null, ?array $nextInput = null, ?array $currentView = null, ?array $nextView = null, ?array $returning = null): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeRecursiveChildYieldStream(
    $rows172,
    $currentInput ?? $currentInput172,
    $nextInput ?? $nextInput172,
    $currentView ?? $currentView172,
    $nextView ?? $nextView172,
    $returning ?? $returning172,
    $options + ['key' => 'key_name', 'savepoint' => 'app_recursive_view_172'],
);
$pinned172 = static fn (): array => $plan172();
$admitted172 = static fn (): array => $plan172(['admit_next_source' => true]);
$nonRecursive172 = static fn (): array => $plan172(['recursive_triggers' => false]);
$depthOne172 = static fn (): array => $plan172(['max_depth' => 1]);

$cases172 = [
    'pinned status' => [static fn (): mixed => $pinned172()['status'], 'trigger-recursive-view-returning-current-source-next172-current-pinned'],
    'admitted status' => [static fn (): mixed => $admitted172()['status'], 'trigger-recursive-view-returning-current-source-next172-next-admitted'],
    'savepoint retained' => [static fn (): mixed => $pinned172()['savepoint'], 'app_recursive_view_172'],
    'key retained' => [static fn (): mixed => $pinned172()['key'], 'key_name'],
    'recursive default true' => [static fn (): mixed => $pinned172()['recursive_triggers'], true],
    'max depth default two' => [static fn (): mixed => $pinned172()['max_depth'], 2],
    'child suffix default' => [static fn (): mixed => $pinned172()['child_suffix'], ':child'],
    'current view source retained' => [static fn (): mixed => $pinned172()['current_view']['source'], 'main@view-cookie-172-current'],
    'current trigger source retained' => [static fn (): mixed => $pinned172()['current_view']['trigger_source'], 'main@trigger-cookie-172-current'],
    'next view source retained' => [static fn (): mixed => $pinned172()['next_view']['source'], 'main@view-cookie-172-next'],
    'next trigger source retained' => [static fn (): mixed => $pinned172()['next_view']['trigger_source'], 'main@trigger-cookie-172-next'],
    'pinned visible view remains current' => [static fn (): mixed => $pinned172()['visible_view']['source'], 'main@view-cookie-172-current'],
    'admitted visible view becomes next' => [static fn (): mixed => $admitted172()['visible_view']['source'], 'main@view-cookie-172-next'],
    'before rows preserved' => [static fn (): mixed => array_column($pinned172()['before_rows'], 'key_name'), ['base_url', 'landing_url']],
    'current rows include recursive children' => [static fn (): mixed => array_column($pinned172()['current_rows'], 'key_name'), ['base_url', 'landing_url', 'current_module', 'base_url:child', 'current_module:child', 'base_url:child:child', 'current_module:child:child']],
    'pinned after savepoint restores base rows' => [static fn (): mixed => array_column($pinned172()['after_savepoint'], 'key_name'), ['base_url', 'landing_url']],
    'admitted after savepoint includes current and next recursion' => [static fn (): mixed => array_column($admitted172()['after_savepoint'], 'key_name'), ['base_url', 'landing_url', 'current_module', 'base_url:child', 'current_module:child', 'base_url:child:child', 'current_module:child:child', 'next_module', 'landing_url:child', 'landing_url:child:child']],
    'current returning names include update insert and recursion' => [static fn (): mixed => array_column($pinned172()['visible_returning_rows'], 'name'), ['base_url', 'current_module', 'base_url:child', 'current_module:child', 'base_url:child:child', 'current_module:child:child']],
    'current returning old value for update' => [static fn (): mixed => $pinned172()['visible_returning_rows'][0]['old_value'], 'https://old.test'],
    'current returning old value for insert null' => [static fn (): mixed => $pinned172()['visible_returning_rows'][1]['old_value'], null],
    'current event sequence' => [static fn (): mixed => array_column($pinned172()['visible_returning_rows'], 'event_name'), ['update', 'insert', 'insert', 'insert', 'insert', 'insert']],
    'current depth sequence' => [static fn (): mixed => array_column($pinned172()['visible_returning_rows'], 'depth_value'), [0, 0, 1, 1, 2, 2]],
    'current trigger aliases' => [static fn (): mixed => array_unique(array_column($pinned172()['visible_returning_rows'], 'trigger_source_alias')), ['main@trigger-cookie-172-current']],
    'callable trace includes current update' => [static fn (): mixed => $pinned172()['visible_returning_rows'][0]['expr6'], 'main@trigger-cookie-172-current:update:0:0:base_url>base_url'],
    'callable trace includes recursive child' => [static fn (): mixed => $pinned172()['visible_returning_rows'][2]['expr6'], 'main@trigger-cookie-172-current:insert:0:1:new>base_url:child'],
    'suppressed next returning names while pinned' => [static fn (): mixed => array_column($pinned172()['suppressed_returning_rows'], 'name'), ['landing_url', 'next_module', 'landing_url:child', 'landing_url:child:child']],
    'suppressed next trigger aliases while pinned' => [static fn (): mixed => array_unique(array_column($pinned172()['suppressed_returning_rows'], 'trigger_source_alias')), ['main@trigger-cookie-172-next']],
    'pinned next returning rows empty' => [static fn (): mixed => $pinned172()['next_returning_rows'], []],
    'attempted next returning rows retained' => [static fn (): mixed => array_column(array_column($pinned172()['attempted_next_returning_rows'], 'returning'), 'name'), ['landing_url', 'next_module', 'landing_url:child', 'landing_url:child:child']],
    'admitted visible returning includes both phases' => [static fn (): mixed => array_column($admitted172()['visible_returning_rows'], 'name'), ['base_url', 'current_module', 'base_url:child', 'current_module:child', 'base_url:child:child', 'current_module:child:child', 'landing_url', 'next_module', 'landing_url:child', 'landing_url:child:child']],
    'admitted next returning names' => [static fn (): mixed => array_column(array_column($admitted172()['next_returning_rows'], 'returning'), 'name'), ['landing_url', 'next_module', 'landing_url:child', 'landing_url:child:child']],
    'admitted next old value sees base plus current output' => [static fn (): mixed => $admitted172()['next_returning_rows'][0]['returning']['old_value'], 'https://landing_url.test'],
    'admitted next module insert old value null' => [static fn (): mixed => $admitted172()['next_returning_rows'][1]['returning']['old_value'], null],
    'current yield phases' => [static fn (): mixed => array_unique(array_column($pinned172()['current_yield_stream'], 'phase')), ['current']],
    'attempted next yield phases' => [static fn (): mixed => array_unique(array_column($pinned172()['attempted_next_yield_stream'], 'phase')), ['next']],
    'attempted stream phase order pinned' => [static fn (): mixed => array_column($pinned172()['attempted_yield_stream'], 'phase'), ['current', 'current', 'current', 'current', 'current', 'current', 'next', 'next', 'next', 'next']],
    'visible stream excludes next while pinned' => [static fn (): mixed => array_column($pinned172()['visible_yield_stream'], 'phase'), ['current', 'current', 'current', 'current', 'current', 'current']],
    'visible stream includes next when admitted' => [static fn (): mixed => array_column($admitted172()['visible_yield_stream'], 'phase'), ['current', 'current', 'current', 'current', 'current', 'current', 'next', 'next', 'next', 'next']],
    'current trigger effect labels' => [static fn (): mixed => array_unique(array_column($pinned172()['current_trigger_effects'], 'audit_label')), ['current-recursive-view-trigger']],
    'attempted next trigger effect labels' => [static fn (): mixed => array_unique(array_column($pinned172()['attempted_next_trigger_effects'], 'audit_label')), ['next-recursive-view-trigger']],
    'pinned next trigger effects hidden' => [static fn (): mixed => $pinned172()['next_trigger_effects'], []],
    'admitted next trigger effects visible' => [static fn (): mixed => array_column($admitted172()['next_trigger_effects'], 'key_name'), ['landing_url', 'next_module', 'landing_url:child', 'landing_url:child:child']],
    'current changes count recursion' => [static fn (): mixed => $pinned172()['current_changes'], 6],
    'pinned changes zero after source pin rollback' => [static fn (): mixed => $pinned172()['changes'], 0],
    'pinned next changes suppressed' => [static fn (): mixed => $pinned172()['next_changes'], 0],
    'pinned attempted next changes counted' => [static fn (): mixed => $pinned172()['attempted_next_changes'], 4],
    'admitted changes include both phases' => [static fn (): mixed => $admitted172()['changes'], 10],
    'statement rows pinned current only' => [static fn (): mixed => $pinned172()['statement_rows'], 2],
    'attempted statement rows both phases' => [static fn (): mixed => $pinned172()['attempted_statement_rows'], 4],
    'recursive rows pinned current only' => [static fn (): mixed => $pinned172()['recursive_rows'], 4],
    'attempted recursive rows both phases' => [static fn (): mixed => $pinned172()['attempted_recursive_rows'], 6],
    'source transition next starts from savepoint while pinned' => [static fn (): mixed => $pinned172()['source_transition']['next_started_from'], 'savepoint-current-source'],
    'source transition next starts from current output when admitted' => [static fn (): mixed => $admitted172()['source_transition']['next_started_from'], 'current-trigger-output'],
    'source transition current returning drained' => [static fn (): mixed => $pinned172()['source_transition']['current_returning_visibility'], 'drained-before-next-source'],
    'source transition next attempted only' => [static fn (): mixed => $pinned172()['source_transition']['next_returning_visibility'], 'attempted-only-current-source-pinned'],
    'source transition next admitted' => [static fn (): mixed => $admitted172()['source_transition']['next_returning_visibility'], 'admitted-after-current-drain'],
    'dependency closure marker' => [static fn (): mixed => $pinned172()['dependency_closure'], 'no-new-support-component-reuses-native-view-trigger-recursion-returning-current-source'],
    'dependency includes slice' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next172', $pinned172()['dependencies'], true), true],
    'dependency includes returning boundary' => [static fn (): mixed => in_array('sqlite-returning-yield-before-next-view-source-admission', $pinned172()['dependencies'], true), true],
    'non recursive returns only statement rows' => [static fn (): mixed => array_column($nonRecursive172()['visible_returning_rows'], 'name'), ['base_url', 'current_module']],
    'non recursive current rows omit children' => [static fn (): mixed => array_column($nonRecursive172()['current_rows'], 'key_name'), ['base_url', 'landing_url', 'current_module']],
    'depth one returning includes first children only' => [static fn (): mixed => array_column($depthOne172()['visible_returning_rows'], 'name'), ['base_url', 'current_module', 'base_url:child', 'current_module:child']],
    'depth one recursive rows count' => [static fn (): mixed => $depthOne172()['recursive_rows'], 2],
    'custom child suffix applies' => [static fn (): mixed => array_column($plan172(['child_suffix' => ':shadow'])['visible_returning_rows'], 'name'), ['base_url', 'current_module', 'base_url:shadow', 'current_module:shadow', 'base_url:shadow:shadow', 'current_module:shadow:shadow']],
    'wildcard-like direct returning value' => [static fn (): mixed => array_column($plan172([], null, null, null, null, ['key_name'])['visible_returning_rows'], 'key_name'), ['base_url', 'current_module', 'base_url:child', 'current_module:child', 'base_url:child:child', 'current_module:child:child']],
    'spawn false suppresses recursive child' => [static fn (): mixed => array_column($plan172([], [['import_id' => 12, 'name' => 'single_option', 'value' => 'one', 'load_policy_flag' => 'no', 'spawn_child' => false]])['visible_returning_rows'], 'name'), ['single_option']],
    'empty projection throws' => [static fn (): mixed => $plan172([], null, null, null, null, []), InvalidArgumentException::class],
    'bad savepoint throws' => [static fn (): mixed => $plan172(['savepoint' => 'bad savepoint']), InvalidArgumentException::class],
    'bad key throws' => [static fn (): mixed => $plan172(['key' => 'bad-key']), InvalidArgumentException::class],
    'bad max depth throws' => [static fn (): mixed => $plan172(['max_depth' => 33]), InvalidArgumentException::class],
    'bad child suffix throws' => [static fn (): mixed => $plan172(['child_suffix' => 'bad suffix']), InvalidArgumentException::class],
    'bad trigger source throws' => [static fn (): mixed => $plan172([], null, null, ['name' => 'v', 'source' => 'ok', 'trigger' => 'trg', 'trigger_source' => 'bad source', 'columns' => ['name'], 'mapping' => ['name' => 'key_name']]), InvalidArgumentException::class],
    'empty view columns throws' => [static fn (): mixed => $plan172([], null, null, ['name' => 'v', 'source' => 'ok', 'trigger' => 'trg', 'trigger_source' => 'ok', 'columns' => [], 'mapping' => ['name' => 'key_name']]), InvalidArgumentException::class],
    'bad view mapping throws' => [static fn (): mixed => $plan172([], null, null, ['name' => 'v', 'source' => 'ok', 'trigger' => 'trg', 'trigger_source' => 'ok', 'columns' => ['name'], 'mapping' => ['missing' => 'key_name']]), InvalidArgumentException::class],
    'missing input view column throws' => [static fn (): mixed => $plan172([], [['import_id' => 1, 'value' => 'x', 'load_policy_flag' => 'yes']]), InvalidArgumentException::class],
    'duplicate base key throws' => [static fn (): mixed => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeRecursiveChildYieldStream(array_merge($rows172, [['key_name' => 'base_url']]), [], [], $currentView172, $nextView172, $returning172), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases172 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source next172 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
