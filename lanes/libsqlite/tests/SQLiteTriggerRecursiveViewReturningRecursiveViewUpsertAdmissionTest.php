<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan;

$rows164 = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes', 'source' => 'seed'],
    ['setting_id' => 2, 'key_name' => 'landing_url', 'key_value' => 'https://landing_url.test', 'load_policy' => 'yes', 'source' => 'seed'],
];
$currentView164 = [
    'name' => 'app_setting_import_view',
    'source' => 'main@view-cookie-164-current',
    'trigger' => 'app_setting_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-164-current',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_retry',
    'audit_label' => 'current-recursive-trigger-body-retry',
];
$nextView164 = [
    'name' => 'app_setting_import_view',
    'source' => 'main@view-cookie-164-next',
    'trigger' => 'app_setting_import_view_io_insert',
    'trigger_source' => 'main@trigger-cookie-164-next',
    'columns' => ['import_id', 'name', 'value', 'load_policy_flag', 'origin'],
    'mapping' => ['import_id' => 'setting_id', 'name' => 'key_name', 'value' => 'key_value', 'load_policy_flag' => 'load_policy', 'origin' => 'source'],
    'recursive_column' => 'name',
    'recursive_suffix' => '_next_retry',
    'audit_label' => 'next-recursive-trigger-body-retry',
];
$currentInput164 = [
    ['import_id' => 10, 'name' => 'module_seed', 'value' => 'enabled', 'load_policy_flag' => 'yes', 'spawn_child' => true],
    ['import_id' => 11, 'name' => 'skip_me', 'value' => 'disabled', 'load_policy_flag' => 'skip', 'spawn_child' => true],
    ['import_id' => 12, 'name' => 'base_url', 'value' => 'https://current.test', 'load_policy_flag' => 'yes', 'spawn_child' => false],
];
$nextInput164 = [
    ['import_id' => 20, 'name' => 'routing_rules', 'value' => 'cached', 'load_policy_flag' => 'yes', 'origin' => 'next-import', 'spawn_child' => true],
    ['import_id' => 21, 'name' => 'landing_url', 'value' => 'https://next-landing_url.test', 'load_policy_flag' => 'yes', 'origin' => 'next-import', 'spawn_child' => false],
    ['import_id' => 22, 'name' => 'next_skip', 'value' => 'ignored', 'load_policy_flag' => 'skip', 'origin' => 'next-import', 'spawn_child' => true],
];
$returning164 = [
    'new.key_name',
    ['expr' => 'new.key_value', 'as' => 'value'],
    ['expr' => 'old.key_value', 'as' => 'old_value'],
    ['expr' => 'view.name', 'as' => 'view_name'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'ordinal', 'as' => 'ordinal_value'],
    ['expr' => 'depth', 'as' => 'depth_value'],
    ['expr' => 'trigger_source', 'as' => 'trigger_source_alias'],
    static fn (array $new, array $viewRow, ?array $old, string $event, int $ordinal, int $depth, string $source): string => $source . ':' . $event . ':' . $ordinal . ':' . $depth . ':' . ($old['key_value'] ?? 'insert') . '>' . $new['key_value'],
];

$plan164 = static fn (array $options = [], ?array $currentInput = null, ?array $nextInput = null, ?array $currentView = null, ?array $nextView = null, ?array $returning = null): array => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeRecursiveViewUpsertAdmission(
    $rows164,
    $currentInput ?? $currentInput164,
    $nextInput ?? $nextInput164,
    $currentView ?? $currentView164,
    $nextView ?? $nextView164,
    $returning ?? $returning164,
    $options + ['key' => 'key_name', 'savepoint' => 'app_recursive_view_164', 'max_depth' => 2],
);

$pinned164 = static fn (): array => $plan164();
$admitted164 = static fn (): array => $plan164(['admit_next_source' => true]);
$ignore164 = static fn (): array => $plan164(['conflict_action' => 'ignore', 'admit_next_source' => true]);
$nonRecursive164 = static fn (): array => $plan164(['recursive_triggers' => false]);

$cases164 = [
    'pinned status' => [static fn (): mixed => $pinned164()['status'], 'trigger-recursive-view-returning-current-source-pinned-next164'],
    'pinned savepoint' => [static fn (): mixed => $pinned164()['savepoint'], 'app_recursive_view_164'],
    'pinned skip column' => [static fn (): mixed => $pinned164()['skip_column'], 'load_policy_flag'],
    'pinned conflict action' => [static fn (): mixed => $pinned164()['conflict_action'], 'replace'],
    'pinned visible source remains current' => [static fn (): mixed => $pinned164()['visible_view']['source'], 'main@view-cookie-164-current'],
    'pinned current returning names' => [static fn (): mixed => array_column(array_column($pinned164()['current_returning_rows'], 'returning'), 'key_name'), ['module_seed', 'base_url', 'module_seed_retry', 'module_seed_retry_retry']],
    'pinned current returning events' => [static fn (): mixed => array_column(array_column($pinned164()['current_returning_rows'], 'returning'), 'event_name'), ['insert', 'update', 'insert', 'insert']],
    'pinned old value for base_url update' => [static fn (): mixed => array_column(array_column($pinned164()['current_returning_rows'], 'returning'), 'old_value')[1], 'https://old.test'],
    'pinned callable retry traces' => [static fn (): mixed => array_column(array_column($pinned164()['current_returning_rows'], 'returning'), 'expr8'), ['main@trigger-cookie-164-current:insert:0:0:insert>enabled', 'main@trigger-cookie-164-current:update:2:0:https://old.test>https://current.test', 'main@trigger-cookie-164-current:insert:3:1:insert>enabled/child', 'main@trigger-cookie-164-current:insert:4:2:insert>enabled/child/child']],
    'pinned skipped current name' => [static fn (): mixed => $pinned164()['current_skipped_rows'][0]['returning']['key_name'], 'skip_me'],
    'pinned skipped current status' => [static fn (): mixed => $pinned164()['current_yield_stream'][1]['status'], 'skipped-raise-ignore'],
    'pinned skipped current has no child' => [static fn (): mixed => array_column($pinned164()['current_recursive_edges'], 'parent_key'), ['module_seed', 'module_seed_retry']],
    'pinned current replaced key' => [static fn (): mixed => $pinned164()['current_replaced_keys'], ['base_url']],
    'pinned current rows names' => [static fn (): mixed => array_column($pinned164()['current_rows'], 'key_name'), ['base_url', 'landing_url', 'module_seed', 'module_seed_retry', 'module_seed_retry_retry']],
    'pinned current base_url replaced' => [static fn (): mixed => $pinned164()['current_rows'][0]['key_value'], 'https://current.test'],
    'pinned after savepoint restored' => [static fn (): mixed => array_column($pinned164()['after_savepoint'], 'key_name'), ['base_url', 'landing_url']],
    'pinned changes hidden at boundary' => [static fn (): mixed => $pinned164()['changes'], 0],
    'pinned current changes count' => [static fn (): mixed => $pinned164()['current_changes'], 4],
    'pinned attempted next changes count' => [static fn (): mixed => $pinned164()['attempted_next_changes'], 4],
    'pinned next returning suppressed' => [static fn (): mixed => $pinned164()['next_returning_rows'], []],
    'pinned attempted next names' => [static fn (): mixed => array_column(array_column($pinned164()['attempted_next_returning_rows'], 'returning'), 'key_name'), ['routing_rules', 'landing_url', 'routing_rules_next_retry', 'routing_rules_next_retry_next_retry']],
    'pinned attempted next skipped name' => [static fn (): mixed => $pinned164()['attempted_next_skipped_rows'][0]['returning']['key_name'], 'next_skip'],
    'pinned attempted next replaced landing_url' => [static fn (): mixed => $pinned164()['attempted_next_replaced_keys'], ['landing_url']],
    'pinned boundary' => [static fn (): mixed => $pinned164()['yield_boundary'], 'recursive-view-returning-next164-current-source-drained-before-next-source'],
    'pinned dependency marker' => [static fn (): mixed => in_array('sqlite-trigger-recursive-view-returning-current-source-next164', $pinned164()['dependencies'], true), true],

    'admitted status' => [static fn (): mixed => $admitted164()['status'], 'trigger-recursive-view-returning-next-source-admitted-next164'],
    'admitted visible source next' => [static fn (): mixed => $admitted164()['visible_view']['source'], 'main@view-cookie-164-next'],
    'admitted changes include both sources' => [static fn (): mixed => $admitted164()['changes'], 8],
    'admitted next returning names' => [static fn (): mixed => array_column(array_column($admitted164()['next_returning_rows'], 'returning'), 'key_name'), ['routing_rules', 'landing_url', 'routing_rules_next_retry', 'routing_rules_next_retry_next_retry']],
    'admitted next landing_url old value sees base current drain' => [static fn (): mixed => array_column(array_column($admitted164()['next_returning_rows'], 'returning'), 'old_value')[1], 'https://landing_url.test'],
    'admitted final names' => [static fn (): mixed => array_column($admitted164()['after_savepoint'], 'key_name'), ['base_url', 'landing_url', 'module_seed', 'module_seed_retry', 'module_seed_retry_retry', 'routing_rules', 'routing_rules_next_retry', 'routing_rules_next_retry_next_retry']],
    'admitted final landing_url value' => [static fn (): mixed => $admitted164()['after_savepoint'][1]['key_value'], 'https://next-landing_url.test'],
    'admitted next skipped visible' => [static fn (): mixed => array_column(array_column($admitted164()['next_skipped_rows'], 'returning'), 'key_name'), ['next_skip']],
    'admitted boundary' => [static fn (): mixed => $admitted164()['yield_boundary'], 'recursive-view-returning-next164-next-source-admitted-after-current-drain'],

    'ignore conflict action retained' => [static fn (): mixed => $ignore164()['conflict_action'], 'ignore'],
    'ignore current replaced empty' => [static fn (): mixed => $ignore164()['current_replaced_keys'], []],
    'ignore current skips base_url conflict' => [static fn (): mixed => array_column(array_column($ignore164()['current_skipped_rows'], 'returning'), 'key_name'), ['skip_me', 'base_url']],
    'ignore next skips landing_url conflict' => [static fn (): mixed => array_column(array_column($ignore164()['next_skipped_rows'], 'returning'), 'key_name'), ['landing_url', 'next_skip']],
    'ignore final base_url unchanged' => [static fn (): mixed => $ignore164()['after_savepoint'][0]['key_value'], 'https://old.test'],
    'ignore final landing_url unchanged' => [static fn (): mixed => $ignore164()['after_savepoint'][1]['key_value'], 'https://landing_url.test'],

    'non recursive names top level only' => [static fn (): mixed => array_column(array_column($nonRecursive164()['current_returning_rows'], 'returning'), 'key_name'), ['module_seed', 'base_url']],
    'non recursive no edges' => [static fn (): mixed => $nonRecursive164()['current_recursive_edges'], []],
    'depth one returns one child' => [static fn (): mixed => array_column(array_column($plan164(['max_depth' => 1])['current_returning_rows'], 'returning'), 'key_name'), ['module_seed', 'base_url', 'module_seed_retry']],
    'custom skip value leaves skip row changed' => [static fn (): mixed => array_column(array_column($plan164(['skip_value' => 'never'])['current_returning_rows'], 'returning'), 'key_name'), ['module_seed', 'skip_me', 'base_url', 'module_seed_retry', 'skip_me_retry', 'module_seed_retry_retry', 'skip_me_retry_retry']],
    'custom savepoint accepted' => [static fn (): mixed => $plan164(['savepoint' => 'app_custom_recursive_164'])['savepoint'], 'app_custom_recursive_164'],
    'empty returning throws' => [static fn (): mixed => $plan164([], null, null, null, null, []), InvalidArgumentException::class],
    'bad conflict action throws' => [static fn (): mixed => $plan164(['conflict_action' => 'abort']), InvalidArgumentException::class],
    'bad skip column throws' => [static fn (): mixed => $plan164(['skip_column' => 'bad-column']), InvalidArgumentException::class],
    'bad savepoint throws' => [static fn (): mixed => $plan164(['savepoint' => 'bad savepoint']), InvalidArgumentException::class],
    'bad max depth throws' => [static fn (): mixed => $plan164(['max_depth' => -1]), InvalidArgumentException::class],
    'bad trigger source throws' => [static fn (): mixed => $plan164([], null, null, ['name' => 'v', 'source' => 'ok', 'trigger' => 'trg', 'trigger_source' => 'bad source', 'columns' => ['name'], 'mapping' => ['name' => 'key_name']]), InvalidArgumentException::class],
    'bad view mapping throws' => [static fn (): mixed => $plan164([], null, null, ['name' => 'v', 'source' => 'ok', 'trigger' => 'trg', 'trigger_source' => 'ok', 'columns' => ['name'], 'mapping' => ['missing' => 'key_name']]), InvalidArgumentException::class],
    'missing recursive mapping throws' => [static fn (): mixed => $plan164([], null, null, ['name' => 'v', 'source' => 'ok', 'trigger' => 'trg', 'trigger_source' => 'ok', 'columns' => ['name'], 'mapping' => ['name' => 'key_name'], 'recursive_column' => 'missing']), InvalidArgumentException::class],
    'missing view column throws' => [static fn (): mixed => $plan164([], [['import_id' => 1, 'value' => 'x', 'load_policy_flag' => 'yes']]), InvalidArgumentException::class],
    'duplicate base key throws' => [static fn (): mixed => SQLiteTriggerRecursiveViewReturningCurrentSourceNextPlan::executeRecursiveViewUpsertAdmission(array_merge($rows164, [['key_name' => 'base_url']]), $currentInput164, $nextInput164, $currentView164, $nextView164, $returning164), InvalidArgumentException::class],
];

$tests = [];
foreach ($cases164 as $name => [$callback, $expected]) {
    $tests['trigger recursive view returning current source next164 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
