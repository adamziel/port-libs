<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteTriggerReturningUpsertViewCurrentNextPlan;

$schema = [
    new SQLiteSchemaRecord('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer, option_name text, option_value text, autoload text, touched text)', 1),
    new SQLiteSchemaRecord('view', 'wp_option_stage_view', 'wp_option_stage_view', 0, 'CREATE VIEW wp_option_stage_view(import_id, name, value, autoload_flag) AS SELECT option_id, option_name, option_value, autoload FROM wp_options', 2),
    new SQLiteSchemaRecord('trigger', 'wp_option_stage_io_insert', 'wp_option_stage_view', 0, 'CREATE TRIGGER wp_option_stage_io_insert INSTEAD OF INSERT ON wp_option_stage_view BEGIN INSERT INTO wp_options(option_id, option_name, option_value, autoload) VALUES(new.import_id, new.name, new.value, new.autoload_flag) ON CONFLICT(option_name) DO UPDATE SET option_id=excluded.option_id, option_value=excluded.option_value, autoload=excluded.autoload RETURNING option_name, option_value; END', 3),
];

$parents = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes', 'touched' => 'seed'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes', 'touched' => 'seed'],
    ['option_id' => 3, 'option_name' => 'blogname', 'option_value' => 'Old Blog', 'autoload' => 'no', 'touched' => 'seed'],
];

$children = [
    ['meta_id' => 10, 'option_id' => 1, 'meta_key' => 'source', 'meta_value' => 'core'],
    ['meta_id' => 11, 'option_id' => 2, 'meta_key' => 'source', 'meta_value' => 'core'],
    ['meta_id' => 12, 'option_id' => 3, 'meta_key' => 'source', 'meta_value' => 'core'],
];

$mapping = [
    'import_id' => 'option_id',
    'name' => 'option_name',
    'value' => 'option_value',
    'autoload_flag' => 'autoload',
];

$assignments = [
    'option_id' => static fn (array $old, array $incoming): mixed => $incoming['option_id'],
    'option_value' => static fn (array $old, array $incoming): mixed => $incoming['option_value'],
    'autoload' => static fn (array $old, array $incoming): mixed => $incoming['autoload'],
    'touched' => static fn (array $old, array $incoming): string => 'upsert',
];

$foreignKey = ['parent_key' => 'option_id', 'child_key' => 'option_id', 'deferred' => true];
$returning = [
    ['expr' => 'new.option_name', 'as' => 'name'],
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'excluded.option_value', 'as' => 'incoming_value'],
    static fn (array $new, ?array $old, array $incoming, string $event): string => $event . ':' . ($old === null ? 'insert' : 'update'),
    static fn (array $new, ?array $old, array $incoming, string $event): mixed => $old['option_value'] ?? null,
];

$triggers = [
    [
        'name' => 'wp_options_bu_rekey_meta',
        'timing' => 'before',
        'event' => 'update',
        'action' => 'update-child',
        'match' => 'old.option_id',
        'set_child_key' => 'new.option_id',
        'values' => ['name' => 'new.option_name', 'old_key' => 'old.option_id', 'new_key' => 'new.option_id'],
    ],
    [
        'name' => 'wp_options_ai_normalize_insert',
        'timing' => 'before',
        'event' => 'insert',
        'action' => 'set-new',
        'set' => ['touched' => 'insert-before'],
        'values' => ['name' => 'new.option_name', 'value' => 'new.option_value'],
    ],
    [
        'name' => 'wp_options_au_touch',
        'timing' => 'after',
        'event' => 'update',
        'action' => 'set-new',
        'set' => ['touched' => 'after-update'],
        'values' => ['name' => 'new.option_name', 'value' => 'new.option_value'],
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

$run = static function (array $viewRows, ?array $schemaRows = null, ?array $mappingRows = null, ?array $fk = null, ?array $triggerRows = null, ?callable $where = null, string $savepoint = 'wp_current_next') use ($schema, $parents, $children, $mapping, $assignments, $foreignKey, $triggers, $returning): array {
    return SQLiteTriggerReturningUpsertViewCurrentNextPlan::execute(
        $schemaRows ?? $schema,
        'wp_option_stage_io_insert',
        $parents,
        $children,
        $viewRows,
        $mappingRows ?? $mapping,
        ['option_name'],
        $assignments,
        $fk ?? $foreignKey,
        $triggerRows ?? $triggers,
        $where,
        $returning,
        $savepoint,
    );
};

$mixed = static fn (): array => $run(
    [
        ['import_id' => 101, 'name' => 'siteurl', 'value' => 'https://new.test', 'autoload_flag' => 'yes'],
        ['import_id' => 102, 'name' => 'fresh_plugin', 'value' => 'enabled', 'autoload_flag' => 'no'],
        ['import_id' => 103, 'name' => 'home', 'value' => 'https://skip.test', 'autoload_flag' => 'skip'],
    ],
    null,
    null,
    null,
    null,
    static fn (array $old, array $incoming): bool => $incoming['autoload'] !== 'skip',
);

$rollbackTriggers = $triggers;
$rollbackTriggers[] = [
    'name' => 'wp_options_au_conflict_home_name',
    'timing' => 'after',
    'event' => 'update',
    'action' => 'set-new',
    'when' => ['new.option_name', '=', 'home'],
    'set' => ['option_name' => 'blogname'],
    'values' => ['name' => 'new.option_name'],
];
$rollback = static fn (): array => $run(
    [
        ['import_id' => 201, 'name' => 'siteurl', 'value' => 'https://first.test', 'autoload_flag' => 'yes'],
        ['import_id' => 202, 'name' => 'home', 'value' => 'will-conflict', 'autoload_flag' => 'yes'],
        ['import_id' => 203, 'name' => 'never_seen', 'value' => 'nope', 'autoload_flag' => 'no'],
    ],
    null,
    null,
    null,
    $rollbackTriggers,
);

$deferredTriggers = [
    [
        'name' => 'wp_options_bu_orphan_home_deferred',
        'timing' => 'before',
        'event' => 'update',
        'action' => 'update-child',
        'match' => 'old.option_id',
        'set_child_key' => 999,
        'values' => ['name' => 'new.option_name'],
    ],
];
$deferred = static fn (): array => $run(
    [['import_id' => 301, 'name' => 'home', 'value' => 'deferred', 'autoload_flag' => 'yes']],
    null,
    null,
    ['parent_key' => 'option_id', 'child_key' => 'option_id', 'deferred' => true],
    $deferredTriggers,
);

$badTrigger = $schema;
$badTrigger[2] = new SQLiteSchemaRecord('trigger', 'wp_option_stage_io_insert', 'wp_options', 0, 'CREATE TRIGGER wp_option_stage_io_insert BEFORE INSERT ON wp_options BEGIN SELECT new.option_name; END', 4);

$cases = [
    'savepoint retained' => [static fn (): mixed => $mixed()['savepoint'], 'wp_current_next'],
    'resolved view retained' => [static fn (): mixed => $mixed()['view'], 'wp_option_stage_view'],
    'resolved trigger retained' => [static fn (): mixed => $mixed()['trigger'], 'wp_option_stage_io_insert'],
    'target type is view' => [static fn (): mixed => $mixed()['targetType'], 'view'],
    'statement rows include skipped row' => [static fn (): mixed => $mixed()['statement_rows'], 3],
    'changes exclude skipped row' => [static fn (): mixed => $mixed()['changes'], 2],
    'not rolled back' => [static fn (): mixed => $mixed()['rolled_back'], false],
    'rollback reason null' => [static fn (): mixed => $mixed()['rollback_reason'], null],
    'yield stream has one row per view input' => [static fn (): mixed => count($mixed()['yield_stream']), 3],
    'yield stream ordinals preserve input order' => [static fn (): mixed => array_column($mixed()['yield_stream'], 'ordinal'), [0, 1, 2]],
    'yield stream view name tags every row' => [static fn (): mixed => array_column($mixed()['yield_stream'], 'view'), ['wp_option_stage_view', 'wp_option_stage_view', 'wp_option_stage_view']],
    'yield stream trigger tags every row' => [static fn (): mixed => array_column($mixed()['yield_stream'], 'trigger'), ['wp_option_stage_io_insert', 'wp_option_stage_io_insert', 'wp_option_stage_io_insert']],
    'yield stream events update insert update' => [static fn (): mixed => array_column($mixed()['yield_stream'], 'event'), ['update', 'insert', 'update']],
    'yield stream statuses include skipped' => [static fn (): mixed => array_column($mixed()['yield_stream'], 'status'), ['changed', 'changed', 'skipped']],
    'yield stream changed flags' => [static fn (): mixed => array_column($mixed()['yield_stream'], 'changed'), [true, true, false]],
    'view row names preserved' => [static fn (): mixed => array_column(array_column($mixed()['yield_stream'], 'view_row'), 'name'), ['siteurl', 'fresh_plugin', 'home']],
    'incoming table names projected' => [static fn (): mixed => array_column(array_column($mixed()['yield_stream'], 'incoming_row'), 'option_name'), ['siteurl', 'fresh_plugin', 'home']],
    'incoming table ids projected' => [static fn (): mixed => array_column(array_column($mixed()['yield_stream'], 'incoming_row'), 'option_id'), [101, 102, 103]],
    'update current row is old siteurl' => [static fn (): mixed => $mixed()['yield_stream'][0]['current_row']['option_value'], 'https://old.test'],
    'insert current row is null' => [static fn (): mixed => $mixed()['yield_stream'][1]['current_row'], null],
    'skipped current row is old home' => [static fn (): mixed => $mixed()['yield_stream'][2]['current_row']['option_value'], 'https://home.test'],
    'update next row has new option id' => [static fn (): mixed => $mixed()['yield_stream'][0]['next_row']['option_id'], 101],
    'update next row includes after trigger mutation' => [static fn (): mixed => $mixed()['yield_stream'][0]['next_row']['touched'], 'after-update'],
    'insert next row carries before trigger mutation' => [static fn (): mixed => $mixed()['yield_stream'][1]['next_row']['touched'], 'insert-before'],
    'skipped next row remains current row' => [static fn (): mixed => $mixed()['yield_stream'][2]['next_row']['option_value'], 'https://home.test'],
    'skipped next row did not use incoming id' => [static fn (): mixed => $mixed()['yield_stream'][2]['next_row']['option_id'], 2],
    'returning row count excludes skipped' => [static fn (): mixed => count($mixed()['returning_rows']), 2],
    'returning names include update and insert' => [static fn (): mixed => array_column($mixed()['returning_rows'], 'name'), ['siteurl', 'fresh_plugin']],
    'returning values use before after-trigger image' => [static fn (): mixed => array_column($mixed()['returning_rows'], 'value'), ['https://new.test', 'enabled']],
    'returning incoming values projected' => [static fn (): mixed => array_column($mixed()['returning_rows'], 'incoming_value'), ['https://new.test', 'enabled']],
    'callable returning event labels' => [static fn (): mixed => array_column($mixed()['returning_rows'], 'expr3'), ['update:update', 'insert:insert']],
    'callable returning old value for update' => [static fn (): mixed => $mixed()['returning_rows'][0]['expr4'], 'https://old.test'],
    'callable returning old value for insert null' => [static fn (): mixed => $mixed()['returning_rows'][1]['expr4'], null],
    'yield returning mirrors returning rows' => [static fn (): mixed => array_column(array_filter(array_column($mixed()['yield_stream'], 'returning')), 'name'), ['siteurl', 'fresh_plugin']],
    'yield skipped returning null' => [static fn (): mixed => $mixed()['yield_stream'][2]['returning'], null],
    'parent names after statement' => [static fn (): mixed => array_column($mixed()['parent'], 'option_name'), ['siteurl', 'home', 'blogname', 'fresh_plugin']],
    'parent ids after statement' => [static fn (): mixed => array_column($mixed()['parent'], 'option_id'), [101, 2, 3, 102]],
    'parent touched values show after insert/update triggers' => [static fn (): mixed => array_column($mixed()['parent'], 'touched'), ['after-update', 'seed', 'seed', 'insert-before']],
    'child keys rekey update and inserted meta row' => [static fn (): mixed => array_column($mixed()['child'], 'option_id'), [101, 2, 3, 102]],
    'trigger effects include before update before insert after update after insert' => [static fn (): mixed => array_column($mixed()['trigger_effects'], 'trigger'), ['wp_options_bu_rekey_meta', 'wp_options_au_touch', 'wp_options_ai_normalize_insert', 'wp_options_ai_meta']],
    'trigger effect view ordinals' => [static fn (): mixed => array_column($mixed()['trigger_effects'], 'view_ordinal'), [0, 0, 1, 1]],
    'trigger effect view row values retained' => [static fn (): mixed => array_column(array_column($mixed()['trigger_effects'], 'view_row'), 'value'), ['https://new.test', 'https://new.test', 'enabled', 'enabled']],
    'fk violations empty for mixed run' => [static fn (): mixed => $mixed()['foreign_key_violations'], []],
    'dependencies include view trigger' => [static fn (): mixed => in_array('sqlite-instead-of-view-trigger', $mixed()['dependencies'], true), true],
    'dependencies include trigger fk yield' => [static fn (): mixed => in_array('sqlite-upsert-returning-trigger-fk-yield', $mixed()['dependencies'], true), true],
    'dependencies include current next stream' => [static fn (): mixed => in_array('sqlite-current-next-row-stream', $mixed()['dependencies'], true), true],

    'rollback marks rolled back' => [static fn (): mixed => $rollback()['rolled_back'], true],
    'rollback fails at second ordinal' => [static fn (): mixed => $rollback()['rolled_back_at_ordinal'], 1],
    'rollback reason reports unique conflict' => [static fn (): mixed => str_contains((string) $rollback()['rollback_reason'], 'unique constraint conflict'), true],
    'rollback changes reset' => [static fn (): mixed => $rollback()['changes'], 0],
    'rollback parent ids restored' => [static fn (): mixed => array_column($rollback()['parent'], 'option_id'), [1, 2, 3]],
    'rollback child ids restored' => [static fn (): mixed => array_column($rollback()['child'], 'option_id'), [1, 2, 3]],
    'rollback returning rows suppressed' => [static fn (): mixed => $rollback()['returning_rows'], []],
    'rollback yield stream keeps prior successful diagnostic' => [static fn (): mixed => array_column($rollback()['yield_stream'], 'ordinal'), [0]],
    'rollback prior diagnostic next row was computed' => [static fn (): mixed => $rollback()['yield_stream'][0]['next_row']['option_id'], 201],
    'rollback trigger effects keep prior row diagnostics' => [static fn (): mixed => array_column($rollback()['trigger_effects'], 'view_ordinal'), [0, 0]],

    'deferred violation not rolled back' => [static fn (): mixed => $deferred()['rolled_back'], false],
    'deferred violation records changed row' => [static fn (): mixed => $deferred()['changes'], 1],
    'deferred violation tags view ordinal' => [static fn (): mixed => $deferred()['foreign_key_violations'][0]['view_ordinal'], 0],
    'deferred violation visible before and after triggers' => [static fn (): mixed => $deferred()['yield_stream'][0]['foreign_key_violation_count'], 2],
    'deferred next row retains update' => [static fn (): mixed => $deferred()['yield_stream'][0]['next_row']['option_value'], 'deferred'],
    'deferred child orphan visible' => [static fn (): mixed => $deferred()['child'][1]['option_id'], 999],

    'empty savepoint rejected' => [static fn (): mixed => $run([], null, null, null, null, null, ''), InvalidArgumentException::class],
    'non view instead of trigger rejected' => [static fn (): mixed => $run([], $badTrigger), InvalidArgumentException::class],
    'empty mapping rejected' => [static fn (): mixed => $run([], null, []), InvalidArgumentException::class],
    'missing mapping column rejected' => [static fn (): mixed => $run([], null, ['missing' => 'option_name']), InvalidArgumentException::class],
    'row missing view column rejected' => [static fn (): mixed => $run([['import_id' => 1, 'name' => 'oops', 'autoload_flag' => 'no']]), InvalidArgumentException::class],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['trigger returning upsert view current next52 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
