<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerUpsertSavepointCurrentNextPlan;

$rows = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes', 'level' => 0],
    ['setting_id' => 2, 'key_name' => 'module_registry', 'key_value' => 'a:0:{}', 'load_policy' => 'yes', 'level' => 0],
];
$incoming = [
    ['setting_id' => 20, 'key_name' => 'base_url', 'key_value' => 'https://new.test', 'load_policy' => 'yes', 'level' => 0],
    ['setting_id' => 30, 'key_name' => 'module_seed', 'key_value' => 'seed', 'load_policy' => 'no', 'level' => 0],
    ['setting_id' => 40, 'key_name' => 'bad_module', 'key_value' => 'bad', 'load_policy' => 'no', 'level' => 0],
    ['setting_id' => 50, 'key_name' => 'skip_module', 'key_value' => 'skip', 'load_policy' => 'no', 'level' => 0],
    ['setting_id' => 60, 'key_name' => 'after_bad', 'key_value' => 'after', 'load_policy' => 'no', 'level' => 0],
];
$assignments = [
    'setting_id' => static fn (array $old, array $new): mixed => $new['setting_id'],
    'key_value' => static fn (array $old, array $new): mixed => $new['key_value'],
    'load_policy' => static fn (array $old, array $new): mixed => $new['load_policy'],
    'level' => static fn (array $old, array $new): mixed => $new['level'],
];
$triggers = [
    [
        'name' => 'app_settings_bu_https_guard',
        'timing' => 'before',
        'event' => 'update',
        'action' => 'set-new',
        'when' => ['new.key_name', '=', 'base_url'],
        'set' => ['key_value' => 'concat:new.key_value:/settings'],
        'values' => ['name' => 'new.key_name', 'value' => 'new.key_value'],
    ],
    [
        'name' => 'app_settings_ai_child',
        'timing' => 'after',
        'event' => 'insert',
        'action' => 'upsert-row',
        'when' => ['new.level', '<', 1],
        'row' => [
            'setting_id' => 'new_plus.setting_id',
            'key_name' => 'concat:new.key_name:_meta',
            'key_value' => 'concat:new.key_value::meta',
            'load_policy' => 'new.load_policy',
            'level' => 'new_plus.level',
        ],
        'values' => ['name' => 'new.key_name'],
    ],
    [
        'name' => 'app_settings_bi_bad_guard',
        'timing' => 'before',
        'event' => 'insert',
        'action' => 'raise',
        'when' => ['new.key_name', '=', 'bad_module'],
        'raise' => 'abort',
        'reason' => 'blocked-module-setting',
        'values' => ['name' => 'new.key_name'],
    ],
    [
        'name' => 'app_settings_bi_skip_guard',
        'timing' => 'before',
        'event' => 'insert',
        'action' => 'raise',
        'when' => ['new.key_name', '=', 'skip_module'],
        'raise' => 'ignore',
        'reason' => 'ignored-module-setting',
        'values' => ['name' => 'new.key_name'],
    ],
    [
        'name' => 'app_settings_ai_after_bad_guard',
        'timing' => 'after',
        'event' => 'insert',
        'action' => 'raise',
        'when' => ['new.key_name', '=', 'after_bad_meta'],
        'raise' => 'abort',
        'reason' => 'after-trigger-child-blocked',
        'values' => ['name' => 'new.key_name'],
    ],
];

$plan = static fn (array $selected = null, array $options = []): array => SQLiteTriggerUpsertSavepointCurrentNextPlan::execute(
    $rows,
    $selected ?? $incoming,
    ['key_name'],
    $assignments,
    $triggers,
    ['savepoint' => 'app-import-row', 'wal_frame' => 7] + $options,
);
$result = static fn (): array => $plan();
$failStatement = static fn (): array => $plan($incoming, ['conflict_action' => 'fail-statement']);

$cases = [
    'savepoint name is preserved' => [static fn (): mixed => $result()['savepoint'], 'app-import-row'],
    'current wal frame is input frame' => [static fn (): mixed => $result()['current_wal_frame'], 7],
    'next wal frame advances only committed changed rows' => [static fn (): mixed => $result()['next_wal_frame'], 9],
    'changes count includes update insert and trigger child' => [static fn (): mixed => $result()['changes'], 3],
    'final names preserve first rows and committed child only' => [static fn (): mixed => array_column($result()['rows'], 'key_name'), ['base_url', 'module_registry', 'module_seed', 'module_seed_meta']],
    'base_url update uses before trigger value' => [static fn (): mixed => $result()['rows'][0]['key_value'], 'https://new.test/settings'],
    'base_url update rekeys setting id' => [static fn (): mixed => $result()['rows'][0]['setting_id'], 20],
    'module registry remains current' => [static fn (): mixed => $result()['rows'][1]['key_value'], 'a:0:{}'],
    'module child gets incremented id' => [static fn (): mixed => $result()['rows'][3]['setting_id'], 31],
    'module child gets incremented level' => [static fn (): mixed => $result()['rows'][3]['level'], 1],
    'module child value derives from current inserted row' => [static fn (): mixed => $result()['rows'][3]['key_value'], 'seed:meta'],
    'row result statuses show release rollback skip rollback' => [static fn (): mixed => array_column($result()['row_results'], 'status'), ['changed', 'changed', 'rolled-back', 'skipped', 'rolled-back']],
    'row result savepoint actions include row rollback' => [static fn (): mixed => array_column($result()['row_results'], 'savepoint_action'), ['release-row', 'release-row', 'rollback-row', 'release-row', 'rollback-row']],
    'row result wal frames do not advance for rolled back rows' => [static fn (): mixed => array_column($result()['row_results'], 'wal_frame'), [8, 9, 9, 9, 9]],
    'bad module rollback reason is reported' => [static fn (): mixed => $result()['row_results'][2]['reason'], 'blocked-module-setting'],
    'skip module ignore reason is reported' => [static fn (): mixed => $result()['row_results'][3]['reason'], 'ignored-module-setting'],
    'after child rollback reason is reported' => [static fn (): mixed => $result()['row_results'][4]['reason'], 'after-trigger-child-blocked'],
    'before bad rollback has zero row deltas' => [static fn (): mixed => $result()['row_results'][2]['rolled_back_count'], 0],
    'after bad rollback removes parent and trigger child' => [static fn (): mixed => $result()['row_results'][4]['rolled_back_count'], 2],
    'rolled back rows list contains after bad parent' => [static fn (): mixed => $result()['rolled_back_rows'][0]['key_name'], 'after_bad'],
    'rolled back rows list contains after bad trigger child' => [static fn (): mixed => $result()['rolled_back_rows'][1]['key_name'], 'after_bad_meta'],
    'rolled back rows exclude bad before-trigger row' => [static fn (): mixed => in_array('bad_module', array_column($result()['rolled_back_rows'], 'key_name'), true), false],
    'yielded changed rows are depth first for trigger child' => [static fn (): mixed => array_column($result()['yielded'], 'key_name'), ['base_url', 'module_seed_meta', 'module_seed', 'skip_module']],
    'yielded events include update insert insert skip insert' => [static fn (): mixed => array_column($result()['yielded'], 'event'), ['update', 'insert', 'insert', 'insert']],
    'yielded statuses include skipped ignore row' => [static fn (): mixed => array_column($result()['yielded'], 'status'), ['changed', 'changed', 'changed', 'skipped']],
    'yielded depths include trigger child depth' => [static fn (): mixed => array_column($result()['yielded'], 'depth'), [0, 1, 0, 0]],
    'trigger child yield names source trigger' => [static fn (): mixed => $result()['yielded'][1]['source_trigger'], 'app_settings_ai_child'],
    'skip yield names ignore reason' => [static fn (): mixed => $result()['yielded'][3]['reason'], 'ignored-module-setting'],
    'trigger effects include before update set-new' => [static fn (): mixed => $result()['trigger_effects'][0]['action'], 'set-new'],
    'trigger effects include recursive upsert action' => [static fn (): mixed => in_array('upsert-row', array_column($result()['trigger_effects'], 'action'), true), true],
    'trigger effects include savepoint rollback actions' => [static fn (): mixed => count(array_filter($result()['trigger_effects'], static fn (array $effect): bool => $effect['action'] === 'rollback-current-row')), 2],
    'first rollback effect names savepoint' => [static fn (): mixed => $result()['trigger_effects'][array_key_last($result()['trigger_effects']) - 1]['savepoint'], 'app-import-row'],
    'last rollback effect records after trigger depth' => [static fn (): mixed => $result()['trigger_effects'][array_key_last($result()['trigger_effects'])]['depth'], 1],
    'last rollback effect counts two rows' => [static fn (): mixed => $result()['trigger_effects'][array_key_last($result()['trigger_effects'])]['rolled_back_count'], 2],
    'savepoint not preserved after committed rows' => [static fn (): mixed => $result()['savepoint_preserved'], false],
    'dependencies include current next marker' => [static fn (): mixed => in_array('sqlite-trigger-upsert-savepoint-current-next73', $result()['dependencies'], true), true],
    'dependencies include row savepoint marker' => [static fn (): mixed => in_array('sqlite-row-savepoint-upsert-trigger-yield', $result()['dependencies'], true), true],

    'fail statement stops after first abort' => [static fn (): mixed => count($failStatement()['row_results']), 3],
    'fail statement reports aborted status' => [static fn (): mixed => $failStatement()['row_results'][2]['status'], 'aborted'],
    'fail statement keeps prior committed rows' => [static fn (): mixed => array_column($failStatement()['rows'], 'key_name'), ['base_url', 'module_registry', 'module_seed', 'module_seed_meta']],
    'fail statement keeps prior wal frame' => [static fn (): mixed => $failStatement()['next_wal_frame'], 9],
    'fail statement does not process skip row' => [static fn (): mixed => in_array('skip_module', array_column($failStatement()['yielded'], 'key_name'), true), false],

    'null conflict target inserts separate rows' => [static fn (): mixed => count($plan([
        ['setting_id' => 70, 'key_name' => null, 'key_value' => 'a', 'load_policy' => 'no', 'level' => 1],
        ['setting_id' => 71, 'key_name' => null, 'key_value' => 'b', 'load_policy' => 'no', 'level' => 1],
    ])['rows']), 4],
    'null conflict target advances both frames' => [static fn (): mixed => $plan([
        ['setting_id' => 70, 'key_name' => null, 'key_value' => 'a', 'load_policy' => 'no', 'level' => 1],
        ['setting_id' => 71, 'key_name' => null, 'key_value' => 'b', 'load_policy' => 'no', 'level' => 1],
    ])['next_wal_frame'], 9],
    'empty savepoint rejected' => [static fn (): mixed => SQLiteTriggerUpsertSavepointCurrentNextPlan::execute($rows, [], ['key_name'], $assignments, $triggers, ['savepoint' => ' ']), InvalidArgumentException::class],
    'empty conflict target rejected' => [static fn (): mixed => SQLiteTriggerUpsertSavepointCurrentNextPlan::execute($rows, [], [], $assignments, $triggers), InvalidArgumentException::class],
    'bad conflict target rejected' => [static fn (): mixed => SQLiteTriggerUpsertSavepointCurrentNextPlan::execute($rows, [], ['bad-name'], $assignments, $triggers), InvalidArgumentException::class],
    'bad conflict action rejected' => [static fn (): mixed => $plan([], ['conflict_action' => 'replace']), InvalidArgumentException::class],
    'bad trigger action rejected' => [static fn (): mixed => SQLiteTriggerUpsertSavepointCurrentNextPlan::execute($rows, [$incoming[1]], ['key_name'], $assignments, [[
        'name' => 'bad',
        'timing' => 'after',
        'event' => 'insert',
        'action' => 'delete-row',
    ]]), InvalidArgumentException::class],
    'bad trigger when operator rejected' => [static fn (): mixed => SQLiteTriggerUpsertSavepointCurrentNextPlan::execute($rows, [$incoming[1]], ['key_name'], $assignments, [[
        'name' => 'bad_when',
        'timing' => 'after',
        'event' => 'insert',
        'action' => 'audit',
        'when' => ['new.key_name', 'LIKE', 'module%'],
    ]]), InvalidArgumentException::class],
    'old reference on insert rejected' => [static fn (): mixed => SQLiteTriggerUpsertSavepointCurrentNextPlan::execute($rows, [$incoming[1]], ['key_name'], $assignments, [[
        'name' => 'bad_old',
        'timing' => 'after',
        'event' => 'insert',
        'action' => 'audit',
        'values' => ['old_key' => 'old.key_name'],
    ]]), InvalidArgumentException::class],
    'missing incoming conflict column rejected' => [static fn (): mixed => $plan([['setting_id' => 99, 'key_value' => 'missing']]), InvalidArgumentException::class],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['trigger upsert savepoint current next73 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
