<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteViewUpsertReturningSavepointPlan;

$schema = [
    new SQLiteSchemaRecord('table', 'app_settings', 'app_settings', 2, 'CREATE TABLE app_settings(setting_id integer, key_name text, key_value text, load_policy text)', 1),
    new SQLiteSchemaRecord('view', 'app_setting_import_view', 'app_setting_import_view', 0, 'CREATE VIEW app_setting_import_view(import_id, name, value, load_policy_flag) AS SELECT setting_id, key_name, key_value, load_policy FROM app_settings', 2),
    new SQLiteSchemaRecord('trigger', 'app_setting_import_view_io_insert', 'app_setting_import_view', 0, 'CREATE TRIGGER app_setting_import_view_io_insert INSTEAD OF INSERT ON app_setting_import_view BEGIN INSERT INTO app_settings(setting_id, key_name, key_value, load_policy) VALUES(new.import_id, new.name, new.value, new.load_policy_flag) ON CONFLICT(key_name) DO UPDATE SET setting_id=excluded.setting_id, key_value=excluded.key_value, load_policy=excluded.load_policy RETURNING key_name; END', 3),
];

$parents = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_name' => 'landing_url', 'key_value' => 'https://landing.test', 'load_policy' => 'yes'],
    ['setting_id' => 3, 'key_name' => 'site_title', 'key_value' => 'Old Title', 'load_policy' => 'no'],
];

$children = [
    ['meta_id' => 10, 'setting_id' => 1, 'meta_key' => 'source', 'meta_value' => 'core'],
    ['meta_id' => 11, 'setting_id' => 2, 'meta_key' => 'source', 'meta_value' => 'core'],
    ['meta_id' => 12, 'setting_id' => 3, 'meta_key' => 'source', 'meta_value' => 'core'],
];

$viewToTable = [
    'import_id' => 'setting_id',
    'name' => 'key_name',
    'value' => 'key_value',
    'load_policy_flag' => 'load_policy',
];

$assignments = [
    'setting_id' => static fn (array $old, array $incoming): mixed => $incoming['setting_id'],
    'key_value' => static fn (array $old, array $incoming): mixed => $incoming['key_value'],
    'load_policy' => static fn (array $old, array $incoming): mixed => $incoming['load_policy'],
];

$foreignKey = ['parent_key' => 'setting_id', 'child_key' => 'setting_id', 'deferred' => true];
$returning = [
    ['expr' => 'new.key_name', 'as' => 'name'],
    ['expr' => 'new.setting_id', 'as' => 'id'],
    ['expr' => 'excluded.key_value', 'as' => 'incoming_value'],
    static fn (array $new, ?array $old, array $incoming, string $event): mixed => $old['setting_id'] ?? null,
    static fn (array $new, ?array $old, array $incoming, string $event): string => $event . ':' . ($old === null ? 'insert' : 'update') . ':' . $new['key_name'],
];

$triggers = [
    [
        'name' => 'app_settings_bu_rekey_meta',
        'timing' => 'before',
        'event' => 'update',
        'action' => 'update-child',
        'match' => 'old.setting_id',
        'set_child_key' => 'new.setting_id',
        'values' => ['old_key' => 'old.setting_id', 'new_key' => 'new.setting_id'],
    ],
    [
        'name' => 'app_settings_ai_meta',
        'timing' => 'after',
        'event' => 'insert',
        'action' => 'insert-child',
        'row' => ['meta_id' => 'new.setting_id', 'setting_id' => 'new.parent_key', 'meta_key' => 'view-import', 'meta_value' => 'new.key_name'],
        'values' => ['name' => 'new.key_name', 'key' => 'new.setting_id'],
    ],
];

$run = static function (array $viewRows, ?array $schemaRows = null, ?array $mapping = null, ?array $fk = null, ?array $triggerRows = null, ?callable $where = null, string $savepoint = 'app_view_import') use ($schema, $parents, $children, $viewToTable, $assignments, $foreignKey, $triggers, $returning): array {
    return SQLiteViewUpsertReturningSavepointPlan::execute(
        $schemaRows ?? $schema,
        'app_setting_import_view_io_insert',
        $parents,
        $children,
        $viewRows,
        $mapping ?? $viewToTable,
        ['key_name'],
        $assignments,
        $fk ?? $foreignKey,
        $triggerRows ?? $triggers,
        $where,
        $returning,
        $savepoint,
    );
};

$mixed = static fn (): array => $run([
    ['import_id' => 101, 'name' => 'base_url', 'value' => 'https://new.test', 'load_policy_flag' => 'yes'],
    ['import_id' => 102, 'name' => 'fresh_module', 'value' => 'enabled', 'load_policy_flag' => 'no'],
    ['import_id' => 103, 'name' => 'landing_url', 'value' => 'https://skip.test', 'load_policy_flag' => 'skip'],
], null, null, null, null, static fn (array $old, array $incoming): bool => $incoming['load_policy'] !== 'skip');

$rollback = static fn (): array => $run(
    [
        ['import_id' => 201, 'name' => 'base_url', 'value' => 'https://first.test', 'load_policy_flag' => 'yes'],
        ['import_id' => 202, 'name' => 'landing_url', 'value' => 'bad', 'load_policy_flag' => 'yes'],
        ['import_id' => 203, 'name' => 'never_seen', 'value' => 'nope', 'load_policy_flag' => 'no'],
    ],
    null,
    null,
    ['parent_key' => 'setting_id', 'child_key' => 'setting_id', 'deferred' => false],
    [
        [
            'name' => 'app_settings_bu_orphan_landing',
            'timing' => 'before',
            'event' => 'update',
            'action' => 'update-child',
            'when' => ['new.key_name', '=', 'landing_url'],
            'match' => 'old.setting_id',
            'set_child_key' => 999,
            'values' => ['name' => 'new.key_name'],
        ],
        $triggers[0],
        $triggers[1],
    ],
);

$deferred = static fn (): array => $run(
    [['import_id' => 301, 'name' => 'landing_url', 'value' => 'deferred', 'load_policy_flag' => 'yes']],
    null,
    null,
    ['parent_key' => 'setting_id', 'child_key' => 'setting_id', 'deferred' => true],
    [[
        'name' => 'app_settings_bu_orphan_landing_deferred',
        'timing' => 'before',
        'event' => 'update',
        'action' => 'update-child',
        'match' => 'old.setting_id',
        'set_child_key' => 999,
        'values' => ['name' => 'new.key_name'],
    ]],
);

$badNotInstead = $schema;
$badNotInstead[2] = new SQLiteSchemaRecord('trigger', 'app_setting_import_view_io_insert', 'app_setting_import_view', 0, 'CREATE TRIGGER app_setting_import_view_io_insert BEFORE INSERT ON app_setting_import_view BEGIN SELECT new.name; END', 4);

$badTableTrigger = $schema;
$badTableTrigger[2] = new SQLiteSchemaRecord('trigger', 'app_setting_import_view_io_insert', 'app_settings', 0, 'CREATE TRIGGER app_setting_import_view_io_insert INSTEAD OF INSERT ON app_settings BEGIN SELECT new.key_name; END', 5);

$cases = [
    'mixed savepoint retained' => [static fn (): mixed => $mixed()['savepoint'], 'app_view_import'],
    'mixed resolved view target' => [static fn (): mixed => $mixed()['view'], 'app_setting_import_view'],
    'mixed resolved trigger target type' => [static fn (): mixed => $mixed()['targetType'], 'view'],
    'mixed statement row count includes skipped view row' => [static fn (): mixed => $mixed()['statement_rows'], 3],
    'mixed changes exclude skipped view row' => [static fn (): mixed => $mixed()['changes'], 2],
    'mixed not rolled back' => [static fn (): mixed => $mixed()['rolled_back'], false],
    'mixed rollback reason null' => [static fn (): mixed => $mixed()['rollback_reason'], null],
    'mixed parent names include inserted module' => [static fn (): mixed => array_column($mixed()['parent'], 'key_name'), ['base_url', 'landing_url', 'site_title', 'fresh_module']],
    'mixed parent ids update base_url only' => [static fn (): mixed => array_column($mixed()['parent'], 'setting_id'), [101, 2, 3, 102]],
    'mixed skipped landing_url remains original' => [static fn (): mixed => $mixed()['parent'][1]['key_value'], 'https://landing.test'],
    'mixed child keys rekey updated and add inserted child' => [static fn (): mixed => array_column($mixed()['child'], 'setting_id'), [101, 2, 3, 102]],
    'mixed returning committed names' => [static fn (): mixed => array_column($mixed()['returning_rows'], 'name'), ['base_url', 'fresh_module']],
    'mixed returning committed ids' => [static fn (): mixed => array_column($mixed()['returning_rows'], 'id'), [101, 102]],
    'mixed old id for insert is null' => [static fn (): mixed => $mixed()['returning_rows'][1]['expr3'], null],
    'mixed incoming values use view row values' => [static fn (): mixed => array_column($mixed()['returning_rows'], 'incoming_value'), ['https://new.test', 'enabled']],
    'mixed callable returning labels events' => [static fn (): mixed => array_column($mixed()['returning_rows'], 'expr4'), ['update:update:base_url', 'insert:insert:fresh_module']],
    'mixed view returning names are tagged' => [static fn (): mixed => array_column($mixed()['view_returning_rows'], 'name'), ['base_url', 'fresh_module']],
    'mixed view returning ordinals skip skipped row' => [static fn (): mixed => array_column($mixed()['view_returning_rows'], 'view_ordinal'), [0, 1]],
    'mixed view returning includes view name' => [static fn (): mixed => array_column($mixed()['view_returning_rows'], 'view'), ['app_setting_import_view', 'app_setting_import_view']],
    'mixed attempted view ordinals all rows' => [static fn (): mixed => array_column($mixed()['attempted_view_rows'], 'ordinal'), [0, 1, 2]],
    'mixed attempted view row names preserved' => [static fn (): mixed => array_column(array_column($mixed()['attempted_view_rows'], 'view_row'), 'name'), ['base_url', 'fresh_module', 'landing_url']],
    'mixed attempted incoming names projected' => [static fn (): mixed => array_column(array_column($mixed()['attempted_view_rows'], 'incoming_row'), 'key_name'), ['base_url', 'fresh_module', 'landing_url']],
    'mixed attempted incoming ids projected' => [static fn (): mixed => array_column(array_column($mixed()['attempted_view_rows'], 'incoming_row'), 'setting_id'), [101, 102, 103]],
    'mixed yielded statuses include skipped' => [static fn (): mixed => array_column($mixed()['attempted_yields'], 'status'), ['changed', 'changed', 'skipped']],
    'mixed yielded events update insert update' => [static fn (): mixed => array_column($mixed()['attempted_yields'], 'event'), ['update', 'insert', 'update']],
    'mixed yielded view ordinals all rows' => [static fn (): mixed => array_column($mixed()['attempted_yields'], 'view_ordinal'), [0, 1, 2]],
    'mixed skipped yielded returning null' => [static fn (): mixed => $mixed()['attempted_yields'][2]['returning'], null],
    'mixed first yielded returning name' => [static fn (): mixed => $mixed()['attempted_yields'][0]['returning']['name'], 'base_url'],
    'mixed trigger names update plus insert' => [static fn (): mixed => array_column($mixed()['trigger_effects'], 'trigger'), ['app_settings_bu_rekey_meta', 'app_settings_ai_meta']],
    'mixed trigger view ordinals' => [static fn (): mixed => array_column($mixed()['trigger_effects'], 'view_ordinal'), [0, 1]],
    'mixed trigger view row value retained' => [static fn (): mixed => $mixed()['trigger_effects'][0]['view_row']['value'], 'https://new.test'],
    'mixed dependencies name view trigger' => [static fn (): mixed => in_array('sqlite-instead-of-view-trigger', $mixed()['dependencies'], true), true],
    'mixed dependencies name savepoint returning' => [static fn (): mixed => in_array('sqlite-upsert-returning-current-savepoint', $mixed()['dependencies'], true), true],
    'mixed fk violations empty' => [static fn (): mixed => $mixed()['foreign_key_violations'], []],

    'rollback marks rolled back' => [static fn (): mixed => $rollback()['rolled_back'], true],
    'rollback ordinal is failed view row' => [static fn (): mixed => $rollback()['rolled_back_at_ordinal'], 1],
    'rollback reason reports immediate constraint' => [static fn (): mixed => str_contains((string) $rollback()['rollback_reason'], 'immediate constraint failed'), true],
    'rollback changes reset' => [static fn (): mixed => $rollback()['changes'], 0],
    'rollback committed returning rows suppressed' => [static fn (): mixed => $rollback()['returning_rows'], []],
    'rollback parent ids restored' => [static fn (): mixed => array_column($rollback()['parent'], 'setting_id'), [1, 2, 3]],
    'rollback parent values restored' => [static fn (): mixed => array_column($rollback()['parent'], 'key_value'), ['https://old.test', 'https://landing.test', 'Old Title']],
    'rollback child ids restored' => [static fn (): mixed => array_column($rollback()['child'], 'setting_id'), [1, 2, 3]],
    'rollback keeps diagnostic view returning for prior row' => [static fn (): mixed => array_column($rollback()['view_returning_rows'], 'name'), ['base_url']],
    'rollback attempted view rows stop at failing row' => [static fn (): mixed => array_column($rollback()['attempted_view_rows'], 'ordinal'), [0, 1]],
    'rollback attempted yields retain prior row only' => [static fn (): mixed => array_column($rollback()['attempted_yields'], 'view_ordinal'), [0]],
    'rollback trigger effects retain prior row triggers' => [static fn (): mixed => array_column($rollback()['trigger_effects'], 'view_ordinal'), [0]],
    'rollback statement rows preserve input count' => [static fn (): mixed => $rollback()['statement_rows'], 3],

    'deferred violation does not roll back' => [static fn (): mixed => $deferred()['rolled_back'], false],
    'deferred violation changes row' => [static fn (): mixed => $deferred()['changes'], 1],
    'deferred violation records statement phase' => [static fn (): mixed => $deferred()['foreign_key_violations'][0]['phase'], 'statement'],
    'deferred violation carries view ordinal' => [static fn (): mixed => $deferred()['foreign_key_violations'][0]['view_ordinal'], 0],
    'deferred violation returning emitted' => [static fn (): mixed => $deferred()['returning_rows'][0]['name'], 'landing_url'],
    'deferred orphan child visible until outer check' => [static fn (): mixed => $deferred()['child'][1]['setting_id'], 999],

    'empty savepoint throws' => [static fn (): mixed => $run([], null, null, null, null, null, ''), InvalidArgumentException::class],
    'non instead of trigger rejected' => [static fn (): mixed => $run([], $badNotInstead), InvalidArgumentException::class],
    'instead of trigger on table rejected' => [static fn (): mixed => $run([], $badTableTrigger), InvalidArgumentException::class],
    'missing view column mapping rejected' => [static fn (): mixed => $run([], null, ['missing' => 'key_name']), InvalidArgumentException::class],
    'empty mapping rejected' => [static fn (): mixed => $run([], null, []), InvalidArgumentException::class],
    'row missing view column rejected' => [static fn (): mixed => $run([['import_id' => 1, 'name' => 'bad', 'load_policy_flag' => 'no']]), InvalidArgumentException::class],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['view upsert returning savepoint current next49 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
