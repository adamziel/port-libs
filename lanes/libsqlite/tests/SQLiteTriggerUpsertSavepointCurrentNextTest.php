<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerUpsertSavepointCurrentNextPlan;

$rows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes', 'level' => 0],
    ['option_id' => 2, 'option_name' => 'active_plugins', 'option_value' => 'a:0:{}', 'autoload' => 'yes', 'level' => 0],
];
$incoming = [
    ['option_id' => 20, 'option_name' => 'siteurl', 'option_value' => 'https://new.test', 'autoload' => 'yes', 'level' => 0],
    ['option_id' => 30, 'option_name' => 'plugin_seed', 'option_value' => 'seed', 'autoload' => 'no', 'level' => 0],
    ['option_id' => 40, 'option_name' => 'bad_plugin', 'option_value' => 'bad', 'autoload' => 'no', 'level' => 0],
    ['option_id' => 50, 'option_name' => 'skip_plugin', 'option_value' => 'skip', 'autoload' => 'no', 'level' => 0],
    ['option_id' => 60, 'option_name' => 'after_bad', 'option_value' => 'after', 'autoload' => 'no', 'level' => 0],
];
$assignments = [
    'option_id' => static fn (array $old, array $new): mixed => $new['option_id'],
    'option_value' => static fn (array $old, array $new): mixed => $new['option_value'],
    'autoload' => static fn (array $old, array $new): mixed => $new['autoload'],
    'level' => static fn (array $old, array $new): mixed => $new['level'],
];
$triggers = [
    [
        'name' => 'wp_options_bu_https_guard',
        'timing' => 'before',
        'event' => 'update',
        'action' => 'set-new',
        'when' => ['new.option_name', '=', 'siteurl'],
        'set' => ['option_value' => 'concat:new.option_value:/wp'],
        'values' => ['name' => 'new.option_name', 'value' => 'new.option_value'],
    ],
    [
        'name' => 'wp_options_ai_child',
        'timing' => 'after',
        'event' => 'insert',
        'action' => 'upsert-row',
        'when' => ['new.level', '<', 1],
        'row' => [
            'option_id' => 'new_plus.option_id',
            'option_name' => 'concat:new.option_name:_meta',
            'option_value' => 'concat:new.option_value::meta',
            'autoload' => 'new.autoload',
            'level' => 'new_plus.level',
        ],
        'values' => ['name' => 'new.option_name'],
    ],
    [
        'name' => 'wp_options_bi_bad_guard',
        'timing' => 'before',
        'event' => 'insert',
        'action' => 'raise',
        'when' => ['new.option_name', '=', 'bad_plugin'],
        'raise' => 'abort',
        'reason' => 'blocked-plugin-option',
        'values' => ['name' => 'new.option_name'],
    ],
    [
        'name' => 'wp_options_bi_skip_guard',
        'timing' => 'before',
        'event' => 'insert',
        'action' => 'raise',
        'when' => ['new.option_name', '=', 'skip_plugin'],
        'raise' => 'ignore',
        'reason' => 'ignored-plugin-option',
        'values' => ['name' => 'new.option_name'],
    ],
    [
        'name' => 'wp_options_ai_after_bad_guard',
        'timing' => 'after',
        'event' => 'insert',
        'action' => 'raise',
        'when' => ['new.option_name', '=', 'after_bad_meta'],
        'raise' => 'abort',
        'reason' => 'after-trigger-child-blocked',
        'values' => ['name' => 'new.option_name'],
    ],
];

$plan = static fn (array $selected = null, array $options = []): array => SQLiteTriggerUpsertSavepointCurrentNextPlan::execute(
    $rows,
    $selected ?? $incoming,
    ['option_name'],
    $assignments,
    $triggers,
    ['savepoint' => 'wp-import-row', 'wal_frame' => 7] + $options,
);
$result = static fn (): array => $plan();
$failStatement = static fn (): array => $plan($incoming, ['conflict_action' => 'fail-statement']);

$cases = [
    'savepoint name is preserved' => [static fn (): mixed => $result()['savepoint'], 'wp-import-row'],
    'current wal frame is input frame' => [static fn (): mixed => $result()['current_wal_frame'], 7],
    'next wal frame advances only committed changed rows' => [static fn (): mixed => $result()['next_wal_frame'], 9],
    'changes count includes update insert and trigger child' => [static fn (): mixed => $result()['changes'], 3],
    'final names preserve first rows and committed child only' => [static fn (): mixed => array_column($result()['rows'], 'option_name'), ['siteurl', 'active_plugins', 'plugin_seed', 'plugin_seed_meta']],
    'siteurl update uses before trigger value' => [static fn (): mixed => $result()['rows'][0]['option_value'], 'https://new.test/wp'],
    'siteurl update rekeys option id' => [static fn (): mixed => $result()['rows'][0]['option_id'], 20],
    'active plugins remains current' => [static fn (): mixed => $result()['rows'][1]['option_value'], 'a:0:{}'],
    'plugin child gets incremented id' => [static fn (): mixed => $result()['rows'][3]['option_id'], 31],
    'plugin child gets incremented level' => [static fn (): mixed => $result()['rows'][3]['level'], 1],
    'plugin child value derives from current inserted row' => [static fn (): mixed => $result()['rows'][3]['option_value'], 'seed:meta'],
    'row result statuses show release rollback skip rollback' => [static fn (): mixed => array_column($result()['row_results'], 'status'), ['changed', 'changed', 'rolled-back', 'skipped', 'rolled-back']],
    'row result savepoint actions include row rollback' => [static fn (): mixed => array_column($result()['row_results'], 'savepoint_action'), ['release-row', 'release-row', 'rollback-row', 'release-row', 'rollback-row']],
    'row result wal frames do not advance for rolled back rows' => [static fn (): mixed => array_column($result()['row_results'], 'wal_frame'), [8, 9, 9, 9, 9]],
    'bad plugin rollback reason is reported' => [static fn (): mixed => $result()['row_results'][2]['reason'], 'blocked-plugin-option'],
    'skip plugin ignore reason is reported' => [static fn (): mixed => $result()['row_results'][3]['reason'], 'ignored-plugin-option'],
    'after child rollback reason is reported' => [static fn (): mixed => $result()['row_results'][4]['reason'], 'after-trigger-child-blocked'],
    'before bad rollback has zero row deltas' => [static fn (): mixed => $result()['row_results'][2]['rolled_back_count'], 0],
    'after bad rollback removes parent and trigger child' => [static fn (): mixed => $result()['row_results'][4]['rolled_back_count'], 2],
    'rolled back rows list contains after bad parent' => [static fn (): mixed => $result()['rolled_back_rows'][0]['option_name'], 'after_bad'],
    'rolled back rows list contains after bad trigger child' => [static fn (): mixed => $result()['rolled_back_rows'][1]['option_name'], 'after_bad_meta'],
    'rolled back rows exclude bad before-trigger row' => [static fn (): mixed => in_array('bad_plugin', array_column($result()['rolled_back_rows'], 'option_name'), true), false],
    'yielded changed rows are depth first for trigger child' => [static fn (): mixed => array_column($result()['yielded'], 'option_name'), ['siteurl', 'plugin_seed_meta', 'plugin_seed', 'skip_plugin']],
    'yielded events include update insert insert skip insert' => [static fn (): mixed => array_column($result()['yielded'], 'event'), ['update', 'insert', 'insert', 'insert']],
    'yielded statuses include skipped ignore row' => [static fn (): mixed => array_column($result()['yielded'], 'status'), ['changed', 'changed', 'changed', 'skipped']],
    'yielded depths include trigger child depth' => [static fn (): mixed => array_column($result()['yielded'], 'depth'), [0, 1, 0, 0]],
    'trigger child yield names source trigger' => [static fn (): mixed => $result()['yielded'][1]['source_trigger'], 'wp_options_ai_child'],
    'skip yield names ignore reason' => [static fn (): mixed => $result()['yielded'][3]['reason'], 'ignored-plugin-option'],
    'trigger effects include before update set-new' => [static fn (): mixed => $result()['trigger_effects'][0]['action'], 'set-new'],
    'trigger effects include recursive upsert action' => [static fn (): mixed => in_array('upsert-row', array_column($result()['trigger_effects'], 'action'), true), true],
    'trigger effects include savepoint rollback actions' => [static fn (): mixed => count(array_filter($result()['trigger_effects'], static fn (array $effect): bool => $effect['action'] === 'rollback-current-row')), 2],
    'first rollback effect names savepoint' => [static fn (): mixed => $result()['trigger_effects'][array_key_last($result()['trigger_effects']) - 1]['savepoint'], 'wp-import-row'],
    'last rollback effect records after trigger depth' => [static fn (): mixed => $result()['trigger_effects'][array_key_last($result()['trigger_effects'])]['depth'], 1],
    'last rollback effect counts two rows' => [static fn (): mixed => $result()['trigger_effects'][array_key_last($result()['trigger_effects'])]['rolled_back_count'], 2],
    'savepoint not preserved after committed rows' => [static fn (): mixed => $result()['savepoint_preserved'], false],
    'dependencies include current next marker' => [static fn (): mixed => in_array('sqlite-trigger-upsert-savepoint-current-next73', $result()['dependencies'], true), true],
    'dependencies include row savepoint marker' => [static fn (): mixed => in_array('sqlite-row-savepoint-upsert-trigger-yield', $result()['dependencies'], true), true],

    'fail statement stops after first abort' => [static fn (): mixed => count($failStatement()['row_results']), 3],
    'fail statement reports aborted status' => [static fn (): mixed => $failStatement()['row_results'][2]['status'], 'aborted'],
    'fail statement keeps prior committed rows' => [static fn (): mixed => array_column($failStatement()['rows'], 'option_name'), ['siteurl', 'active_plugins', 'plugin_seed', 'plugin_seed_meta']],
    'fail statement keeps prior wal frame' => [static fn (): mixed => $failStatement()['next_wal_frame'], 9],
    'fail statement does not process skip row' => [static fn (): mixed => in_array('skip_plugin', array_column($failStatement()['yielded'], 'option_name'), true), false],

    'null conflict target inserts separate rows' => [static fn (): mixed => count($plan([
        ['option_id' => 70, 'option_name' => null, 'option_value' => 'a', 'autoload' => 'no', 'level' => 1],
        ['option_id' => 71, 'option_name' => null, 'option_value' => 'b', 'autoload' => 'no', 'level' => 1],
    ])['rows']), 4],
    'null conflict target advances both frames' => [static fn (): mixed => $plan([
        ['option_id' => 70, 'option_name' => null, 'option_value' => 'a', 'autoload' => 'no', 'level' => 1],
        ['option_id' => 71, 'option_name' => null, 'option_value' => 'b', 'autoload' => 'no', 'level' => 1],
    ])['next_wal_frame'], 9],
    'empty savepoint rejected' => [static fn (): mixed => SQLiteTriggerUpsertSavepointCurrentNextPlan::execute($rows, [], ['option_name'], $assignments, $triggers, ['savepoint' => ' ']), InvalidArgumentException::class],
    'empty conflict target rejected' => [static fn (): mixed => SQLiteTriggerUpsertSavepointCurrentNextPlan::execute($rows, [], [], $assignments, $triggers), InvalidArgumentException::class],
    'bad conflict target rejected' => [static fn (): mixed => SQLiteTriggerUpsertSavepointCurrentNextPlan::execute($rows, [], ['bad-name'], $assignments, $triggers), InvalidArgumentException::class],
    'bad conflict action rejected' => [static fn (): mixed => $plan([], ['conflict_action' => 'replace']), InvalidArgumentException::class],
    'bad trigger action rejected' => [static fn (): mixed => SQLiteTriggerUpsertSavepointCurrentNextPlan::execute($rows, [$incoming[1]], ['option_name'], $assignments, [[
        'name' => 'bad',
        'timing' => 'after',
        'event' => 'insert',
        'action' => 'delete-row',
    ]]), InvalidArgumentException::class],
    'bad trigger when operator rejected' => [static fn (): mixed => SQLiteTriggerUpsertSavepointCurrentNextPlan::execute($rows, [$incoming[1]], ['option_name'], $assignments, [[
        'name' => 'bad_when',
        'timing' => 'after',
        'event' => 'insert',
        'action' => 'audit',
        'when' => ['new.option_name', 'LIKE', 'plugin%'],
    ]]), InvalidArgumentException::class],
    'old reference on insert rejected' => [static fn (): mixed => SQLiteTriggerUpsertSavepointCurrentNextPlan::execute($rows, [$incoming[1]], ['option_name'], $assignments, [[
        'name' => 'bad_old',
        'timing' => 'after',
        'event' => 'insert',
        'action' => 'audit',
        'values' => ['old_name' => 'old.option_name'],
    ]]), InvalidArgumentException::class],
    'missing incoming conflict column rejected' => [static fn (): mixed => $plan([['option_id' => 99, 'option_value' => 'missing']]), InvalidArgumentException::class],
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
