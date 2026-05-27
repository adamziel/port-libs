<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteViewUpsertReturningSavepointPlan;

$schema = [
    new SQLiteSchemaRecord('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer, option_name text, option_value text, autoload text)', 1),
    new SQLiteSchemaRecord('view', 'wp_option_import_view', 'wp_option_import_view', 0, 'CREATE VIEW wp_option_import_view(import_id, name, value, autoload_flag) AS SELECT option_id, option_name, option_value, autoload FROM wp_options', 2),
    new SQLiteSchemaRecord('trigger', 'wp_option_import_view_io_insert', 'wp_option_import_view', 0, 'CREATE TRIGGER wp_option_import_view_io_insert INSTEAD OF INSERT ON wp_option_import_view BEGIN INSERT INTO wp_options(option_id, option_name, option_value, autoload) VALUES(new.import_id, new.name, new.value, new.autoload_flag) ON CONFLICT(option_name) DO UPDATE SET option_id=excluded.option_id, option_value=excluded.option_value, autoload=excluded.autoload RETURNING option_name; END', 3),
];

$parents = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'blogname', 'option_value' => 'Old Blog', 'autoload' => 'no'],
];

$children = [
    ['meta_id' => 10, 'option_id' => 1, 'meta_key' => 'source', 'meta_value' => 'core'],
    ['meta_id' => 11, 'option_id' => 2, 'meta_key' => 'source', 'meta_value' => 'core'],
    ['meta_id' => 12, 'option_id' => 3, 'meta_key' => 'source', 'meta_value' => 'core'],
];

$viewToTable = [
    'import_id' => 'option_id',
    'name' => 'option_name',
    'value' => 'option_value',
    'autoload_flag' => 'autoload',
];

$assignments = [
    'option_id' => static fn (array $old, array $incoming): mixed => $incoming['option_id'],
    'option_value' => static fn (array $old, array $incoming): mixed => $incoming['option_value'],
    'autoload' => static fn (array $old, array $incoming): mixed => $incoming['autoload'],
];

$foreignKey = ['parent_key' => 'option_id', 'child_key' => 'option_id', 'deferred' => true];
$returning = [
    ['expr' => 'new.option_name', 'as' => 'name'],
    ['expr' => 'new.option_id', 'as' => 'id'],
    ['expr' => 'excluded.option_value', 'as' => 'incoming_value'],
    static fn (array $new, ?array $old, array $incoming, string $event): mixed => $old['option_id'] ?? null,
    static fn (array $new, ?array $old, array $incoming, string $event): string => $event . ':' . ($old === null ? 'insert' : 'update') . ':' . $new['option_name'],
];

$triggers = [
    [
        'name' => 'wp_options_bu_rekey_meta',
        'timing' => 'before',
        'event' => 'update',
        'action' => 'update-child',
        'match' => 'old.option_id',
        'set_child_key' => 'new.option_id',
        'values' => ['old_key' => 'old.option_id', 'new_key' => 'new.option_id'],
    ],
    [
        'name' => 'wp_options_ai_meta',
        'timing' => 'after',
        'event' => 'insert',
        'action' => 'insert-child',
        'row' => ['meta_id' => 'new.option_id', 'option_id' => 'new.parent_key', 'meta_key' => 'view-import', 'meta_value' => 'new.option_name'],
        'values' => ['name' => 'new.option_name', 'key' => 'new.option_id'],
    ],
];

$run = static function (array $viewRows, ?array $schemaRows = null, ?array $mapping = null, ?array $fk = null, ?array $triggerRows = null, ?callable $where = null, string $savepoint = 'wp_view_import') use ($schema, $parents, $children, $viewToTable, $assignments, $foreignKey, $triggers, $returning): array {
    return SQLiteViewUpsertReturningSavepointPlan::execute(
        $schemaRows ?? $schema,
        'wp_option_import_view_io_insert',
        $parents,
        $children,
        $viewRows,
        $mapping ?? $viewToTable,
        ['option_name'],
        $assignments,
        $fk ?? $foreignKey,
        $triggerRows ?? $triggers,
        $where,
        $returning,
        $savepoint,
    );
};

$mixed = static fn (): array => $run([
    ['import_id' => 101, 'name' => 'siteurl', 'value' => 'https://new.test', 'autoload_flag' => 'yes'],
    ['import_id' => 102, 'name' => 'fresh_plugin', 'value' => 'enabled', 'autoload_flag' => 'no'],
    ['import_id' => 103, 'name' => 'home', 'value' => 'https://skip.test', 'autoload_flag' => 'skip'],
], null, null, null, null, static fn (array $old, array $incoming): bool => $incoming['autoload'] !== 'skip');

$rollback = static fn (): array => $run(
    [
        ['import_id' => 201, 'name' => 'siteurl', 'value' => 'https://first.test', 'autoload_flag' => 'yes'],
        ['import_id' => 202, 'name' => 'home', 'value' => 'bad', 'autoload_flag' => 'yes'],
        ['import_id' => 203, 'name' => 'never_seen', 'value' => 'nope', 'autoload_flag' => 'no'],
    ],
    null,
    null,
    ['parent_key' => 'option_id', 'child_key' => 'option_id', 'deferred' => false],
    [
        [
            'name' => 'wp_options_bu_orphan_home',
            'timing' => 'before',
            'event' => 'update',
            'action' => 'update-child',
            'when' => ['new.option_name', '=', 'home'],
            'match' => 'old.option_id',
            'set_child_key' => 999,
            'values' => ['name' => 'new.option_name'],
        ],
        $triggers[0],
        $triggers[1],
    ],
);

$deferred = static fn (): array => $run(
    [['import_id' => 301, 'name' => 'home', 'value' => 'deferred', 'autoload_flag' => 'yes']],
    null,
    null,
    ['parent_key' => 'option_id', 'child_key' => 'option_id', 'deferred' => true],
    [[
        'name' => 'wp_options_bu_orphan_home_deferred',
        'timing' => 'before',
        'event' => 'update',
        'action' => 'update-child',
        'match' => 'old.option_id',
        'set_child_key' => 999,
        'values' => ['name' => 'new.option_name'],
    ]],
);

$badNotInstead = $schema;
$badNotInstead[2] = new SQLiteSchemaRecord('trigger', 'wp_option_import_view_io_insert', 'wp_option_import_view', 0, 'CREATE TRIGGER wp_option_import_view_io_insert BEFORE INSERT ON wp_option_import_view BEGIN SELECT new.name; END', 4);

$badTableTrigger = $schema;
$badTableTrigger[2] = new SQLiteSchemaRecord('trigger', 'wp_option_import_view_io_insert', 'wp_options', 0, 'CREATE TRIGGER wp_option_import_view_io_insert INSTEAD OF INSERT ON wp_options BEGIN SELECT new.option_name; END', 5);

$cases = [
    'mixed savepoint retained' => [static fn (): mixed => $mixed()['savepoint'], 'wp_view_import'],
    'mixed resolved view target' => [static fn (): mixed => $mixed()['view'], 'wp_option_import_view'],
    'mixed resolved trigger target type' => [static fn (): mixed => $mixed()['targetType'], 'view'],
    'mixed statement row count includes skipped view row' => [static fn (): mixed => $mixed()['statement_rows'], 3],
    'mixed changes exclude skipped view row' => [static fn (): mixed => $mixed()['changes'], 2],
    'mixed not rolled back' => [static fn (): mixed => $mixed()['rolled_back'], false],
    'mixed rollback reason null' => [static fn (): mixed => $mixed()['rollback_reason'], null],
    'mixed parent names include inserted plugin' => [static fn (): mixed => array_column($mixed()['parent'], 'option_name'), ['siteurl', 'home', 'blogname', 'fresh_plugin']],
    'mixed parent ids update siteurl only' => [static fn (): mixed => array_column($mixed()['parent'], 'option_id'), [101, 2, 3, 102]],
    'mixed skipped home remains original' => [static fn (): mixed => $mixed()['parent'][1]['option_value'], 'https://home.test'],
    'mixed child keys rekey updated and add inserted child' => [static fn (): mixed => array_column($mixed()['child'], 'option_id'), [101, 2, 3, 102]],
    'mixed returning committed names' => [static fn (): mixed => array_column($mixed()['returning_rows'], 'name'), ['siteurl', 'fresh_plugin']],
    'mixed returning committed ids' => [static fn (): mixed => array_column($mixed()['returning_rows'], 'id'), [101, 102]],
    'mixed old id for insert is null' => [static fn (): mixed => $mixed()['returning_rows'][1]['expr3'], null],
    'mixed incoming values use view row values' => [static fn (): mixed => array_column($mixed()['returning_rows'], 'incoming_value'), ['https://new.test', 'enabled']],
    'mixed callable returning labels events' => [static fn (): mixed => array_column($mixed()['returning_rows'], 'expr4'), ['update:update:siteurl', 'insert:insert:fresh_plugin']],
    'mixed view returning names are tagged' => [static fn (): mixed => array_column($mixed()['view_returning_rows'], 'name'), ['siteurl', 'fresh_plugin']],
    'mixed view returning ordinals skip skipped row' => [static fn (): mixed => array_column($mixed()['view_returning_rows'], 'view_ordinal'), [0, 1]],
    'mixed view returning includes view name' => [static fn (): mixed => array_column($mixed()['view_returning_rows'], 'view'), ['wp_option_import_view', 'wp_option_import_view']],
    'mixed attempted view ordinals all rows' => [static fn (): mixed => array_column($mixed()['attempted_view_rows'], 'ordinal'), [0, 1, 2]],
    'mixed attempted view row names preserved' => [static fn (): mixed => array_column(array_column($mixed()['attempted_view_rows'], 'view_row'), 'name'), ['siteurl', 'fresh_plugin', 'home']],
    'mixed attempted incoming names projected' => [static fn (): mixed => array_column(array_column($mixed()['attempted_view_rows'], 'incoming_row'), 'option_name'), ['siteurl', 'fresh_plugin', 'home']],
    'mixed attempted incoming ids projected' => [static fn (): mixed => array_column(array_column($mixed()['attempted_view_rows'], 'incoming_row'), 'option_id'), [101, 102, 103]],
    'mixed yielded statuses include skipped' => [static fn (): mixed => array_column($mixed()['attempted_yields'], 'status'), ['changed', 'changed', 'skipped']],
    'mixed yielded events update insert update' => [static fn (): mixed => array_column($mixed()['attempted_yields'], 'event'), ['update', 'insert', 'update']],
    'mixed yielded view ordinals all rows' => [static fn (): mixed => array_column($mixed()['attempted_yields'], 'view_ordinal'), [0, 1, 2]],
    'mixed skipped yielded returning null' => [static fn (): mixed => $mixed()['attempted_yields'][2]['returning'], null],
    'mixed first yielded returning name' => [static fn (): mixed => $mixed()['attempted_yields'][0]['returning']['name'], 'siteurl'],
    'mixed trigger names update plus insert' => [static fn (): mixed => array_column($mixed()['trigger_effects'], 'trigger'), ['wp_options_bu_rekey_meta', 'wp_options_ai_meta']],
    'mixed trigger view ordinals' => [static fn (): mixed => array_column($mixed()['trigger_effects'], 'view_ordinal'), [0, 1]],
    'mixed trigger view row value retained' => [static fn (): mixed => $mixed()['trigger_effects'][0]['view_row']['value'], 'https://new.test'],
    'mixed dependencies name view trigger' => [static fn (): mixed => in_array('sqlite-instead-of-view-trigger', $mixed()['dependencies'], true), true],
    'mixed dependencies name savepoint returning' => [static fn (): mixed => in_array('sqlite-upsert-returning-current-savepoint', $mixed()['dependencies'], true), true],
    'mixed fk violations empty' => [static fn (): mixed => $mixed()['foreign_key_violations'], []],

    'rollback marks rolled back' => [static fn (): mixed => $rollback()['rolled_back'], true],
    'rollback ordinal is failing view row' => [static fn (): mixed => $rollback()['rolled_back_at_ordinal'], 1],
    'rollback reason reports immediate constraint' => [static fn (): mixed => str_contains((string) $rollback()['rollback_reason'], 'immediate constraint failed'), true],
    'rollback changes reset' => [static fn (): mixed => $rollback()['changes'], 0],
    'rollback committed returning rows suppressed' => [static fn (): mixed => $rollback()['returning_rows'], []],
    'rollback parent ids restored' => [static fn (): mixed => array_column($rollback()['parent'], 'option_id'), [1, 2, 3]],
    'rollback parent values restored' => [static fn (): mixed => array_column($rollback()['parent'], 'option_value'), ['https://old.test', 'https://home.test', 'Old Blog']],
    'rollback child ids restored' => [static fn (): mixed => array_column($rollback()['child'], 'option_id'), [1, 2, 3]],
    'rollback keeps diagnostic view returning for prior row' => [static fn (): mixed => array_column($rollback()['view_returning_rows'], 'name'), ['siteurl']],
    'rollback attempted view rows stop at failing row' => [static fn (): mixed => array_column($rollback()['attempted_view_rows'], 'ordinal'), [0, 1]],
    'rollback attempted yields retain prior row only' => [static fn (): mixed => array_column($rollback()['attempted_yields'], 'view_ordinal'), [0]],
    'rollback trigger effects retain prior row triggers' => [static fn (): mixed => array_column($rollback()['trigger_effects'], 'view_ordinal'), [0]],
    'rollback statement rows preserve input count' => [static fn (): mixed => $rollback()['statement_rows'], 3],

    'deferred violation does not roll back' => [static fn (): mixed => $deferred()['rolled_back'], false],
    'deferred violation changes row' => [static fn (): mixed => $deferred()['changes'], 1],
    'deferred violation records statement phase' => [static fn (): mixed => $deferred()['foreign_key_violations'][0]['phase'], 'statement'],
    'deferred violation carries view ordinal' => [static fn (): mixed => $deferred()['foreign_key_violations'][0]['view_ordinal'], 0],
    'deferred violation returning emitted' => [static fn (): mixed => $deferred()['returning_rows'][0]['name'], 'home'],
    'deferred orphan child visible until outer check' => [static fn (): mixed => $deferred()['child'][1]['option_id'], 999],

    'empty savepoint throws' => [static fn (): mixed => $run([], null, null, null, null, null, ''), InvalidArgumentException::class],
    'non instead of trigger rejected' => [static fn (): mixed => $run([], $badNotInstead), InvalidArgumentException::class],
    'instead of trigger on table rejected' => [static fn (): mixed => $run([], $badTableTrigger), InvalidArgumentException::class],
    'missing view column mapping rejected' => [static fn (): mixed => $run([], null, ['missing' => 'option_name']), InvalidArgumentException::class],
    'empty mapping rejected' => [static fn (): mixed => $run([], null, []), InvalidArgumentException::class],
    'row missing view column rejected' => [static fn (): mixed => $run([['import_id' => 1, 'name' => 'bad', 'autoload_flag' => 'no']]), InvalidArgumentException::class],
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
