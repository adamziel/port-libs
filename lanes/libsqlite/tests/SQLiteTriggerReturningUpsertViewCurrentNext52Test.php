<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteTriggerReturningUpsertViewCurrentNextPlan;

$schema = [
    new SQLiteSchemaRecord('table', 'app_settings', 'app_settings', 2, 'CREATE TABLE app_settings(setting_id integer, key_name text, key_value text, load_policy text, touched text)', 1),
    new SQLiteSchemaRecord('view', 'app_setting_stage_view', 'app_setting_stage_view', 0, 'CREATE VIEW app_setting_stage_view(import_id, name, value, load_policy_flag) AS SELECT setting_id, key_name, key_value, load_policy FROM app_settings', 2),
    new SQLiteSchemaRecord('trigger', 'app_setting_stage_io_insert', 'app_setting_stage_view', 0, 'CREATE TRIGGER app_setting_stage_io_insert INSTEAD OF INSERT ON app_setting_stage_view BEGIN INSERT INTO app_settings(setting_id, key_name, key_value, load_policy) VALUES(new.import_id, new.name, new.value, new.load_policy_flag) ON CONFLICT(key_name) DO UPDATE SET setting_id=excluded.setting_id, key_value=excluded.key_value, load_policy=excluded.load_policy RETURNING key_name, key_value; END', 3),
];

$parents = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes', 'touched' => 'seed'],
    ['setting_id' => 2, 'key_name' => 'landing_url', 'key_value' => 'https://landing_url.test', 'load_policy' => 'yes', 'touched' => 'seed'],
    ['setting_id' => 3, 'key_name' => 'display_title', 'key_value' => 'Old Title', 'load_policy' => 'no', 'touched' => 'seed'],
];

$children = [
    ['meta_id' => 10, 'setting_id' => 1, 'meta_key' => 'source', 'meta_value' => 'core'],
    ['meta_id' => 11, 'setting_id' => 2, 'meta_key' => 'source', 'meta_value' => 'core'],
    ['meta_id' => 12, 'setting_id' => 3, 'meta_key' => 'source', 'meta_value' => 'core'],
];

$mapping = [
    'import_id' => 'setting_id',
    'name' => 'key_name',
    'value' => 'key_value',
    'load_policy_flag' => 'load_policy',
];

$assignments = [
    'setting_id' => static fn (array $old, array $incoming): mixed => $incoming['setting_id'],
    'key_value' => static fn (array $old, array $incoming): mixed => $incoming['key_value'],
    'load_policy' => static fn (array $old, array $incoming): mixed => $incoming['load_policy'],
    'touched' => static fn (array $old, array $incoming): string => 'upsert',
];

$foreignKey = ['parent_key' => 'setting_id', 'child_key' => 'setting_id', 'deferred' => true];
$returning = [
    ['expr' => 'new.key_name', 'as' => 'name'],
    ['expr' => 'new.key_value', 'as' => 'value'],
    ['expr' => 'excluded.key_value', 'as' => 'incoming_value'],
    static fn (array $new, ?array $old, array $incoming, string $event): string => $event . ':' . ($old === null ? 'insert' : 'update'),
    static fn (array $new, ?array $old, array $incoming, string $event): mixed => $old['key_value'] ?? null,
];

$triggers = [
    [
        'name' => 'app_settings_bu_rekey_meta',
        'timing' => 'before',
        'event' => 'update',
        'action' => 'update-child',
        'match' => 'old.setting_id',
        'set_child_key' => 'new.setting_id',
        'values' => ['name' => 'new.key_name', 'old_key' => 'old.setting_id', 'new_key' => 'new.setting_id'],
    ],
    [
        'name' => 'app_settings_ai_normalize_insert',
        'timing' => 'before',
        'event' => 'insert',
        'action' => 'set-new',
        'set' => ['touched' => 'insert-before'],
        'values' => ['name' => 'new.key_name', 'value' => 'new.key_value'],
    ],
    [
        'name' => 'app_settings_au_touch',
        'timing' => 'after',
        'event' => 'update',
        'action' => 'set-new',
        'set' => ['touched' => 'after-update'],
        'values' => ['name' => 'new.key_name', 'value' => 'new.key_value'],
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

$run = static function (array $viewRows, ?array $schemaRows = null, ?array $mappingRows = null, ?array $fk = null, ?array $triggerRows = null, ?callable $where = null, string $savepoint = 'app_current_next') use ($schema, $parents, $children, $mapping, $assignments, $foreignKey, $triggers, $returning): array {
    return SQLiteTriggerReturningUpsertViewCurrentNextPlan::execute(
        $schemaRows ?? $schema,
        'app_setting_stage_io_insert',
        $parents,
        $children,
        $viewRows,
        $mappingRows ?? $mapping,
        ['key_name'],
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
        ['import_id' => 101, 'name' => 'base_url', 'value' => 'https://new.test', 'load_policy_flag' => 'yes'],
        ['import_id' => 102, 'name' => 'module_registry', 'value' => 'enabled', 'load_policy_flag' => 'no'],
        ['import_id' => 103, 'name' => 'landing_url', 'value' => 'https://skip.test', 'load_policy_flag' => 'skip'],
    ],
    null,
    null,
    null,
    null,
    static fn (array $old, array $incoming): bool => $incoming['load_policy'] !== 'skip',
);

$rollbackTriggers = $triggers;
$rollbackTriggers[] = [
    'name' => 'app_settings_au_conflict_landing_url_name',
    'timing' => 'after',
    'event' => 'update',
    'action' => 'set-new',
    'when' => ['new.key_name', '=', 'landing_url'],
    'set' => ['key_name' => 'display_title'],
    'values' => ['name' => 'new.key_name'],
];
$rollback = static fn (): array => $run(
    [
        ['import_id' => 201, 'name' => 'base_url', 'value' => 'https://first.test', 'load_policy_flag' => 'yes'],
        ['import_id' => 202, 'name' => 'landing_url', 'value' => 'will-conflict', 'load_policy_flag' => 'yes'],
        ['import_id' => 203, 'name' => 'unseen_setting', 'value' => 'nope', 'load_policy_flag' => 'no'],
    ],
    null,
    null,
    null,
    $rollbackTriggers,
);

$deferredTriggers = [
    [
        'name' => 'app_settings_bu_orphan_landing_url_deferred',
        'timing' => 'before',
        'event' => 'update',
        'action' => 'update-child',
        'match' => 'old.setting_id',
        'set_child_key' => 999,
        'values' => ['name' => 'new.key_name'],
    ],
];
$deferred = static fn (): array => $run(
    [['import_id' => 301, 'name' => 'landing_url', 'value' => 'deferred', 'load_policy_flag' => 'yes']],
    null,
    null,
    ['parent_key' => 'setting_id', 'child_key' => 'setting_id', 'deferred' => true],
    $deferredTriggers,
);

$badTrigger = $schema;
$badTrigger[2] = new SQLiteSchemaRecord('trigger', 'app_setting_stage_io_insert', 'app_settings', 0, 'CREATE TRIGGER app_setting_stage_io_insert BEFORE INSERT ON app_settings BEGIN SELECT new.key_name; END', 4);

$cases = [
    'savepoint retained' => [static fn (): mixed => $mixed()['savepoint'], 'app_current_next'],
    'resolved view retained' => [static fn (): mixed => $mixed()['view'], 'app_setting_stage_view'],
    'resolved trigger retained' => [static fn (): mixed => $mixed()['trigger'], 'app_setting_stage_io_insert'],
    'target type is view' => [static fn (): mixed => $mixed()['targetType'], 'view'],
    'statement rows include skipped row' => [static fn (): mixed => $mixed()['statement_rows'], 3],
    'changes exclude skipped row' => [static fn (): mixed => $mixed()['changes'], 2],
    'not rolled back' => [static fn (): mixed => $mixed()['rolled_back'], false],
    'rollback reason null' => [static fn (): mixed => $mixed()['rollback_reason'], null],
    'yield stream has one row per view input' => [static fn (): mixed => count($mixed()['yield_stream']), 3],
    'yield stream ordinals preserve input order' => [static fn (): mixed => array_column($mixed()['yield_stream'], 'ordinal'), [0, 1, 2]],
    'yield stream view name tags every row' => [static fn (): mixed => array_column($mixed()['yield_stream'], 'view'), ['app_setting_stage_view', 'app_setting_stage_view', 'app_setting_stage_view']],
    'yield stream trigger tags every row' => [static fn (): mixed => array_column($mixed()['yield_stream'], 'trigger'), ['app_setting_stage_io_insert', 'app_setting_stage_io_insert', 'app_setting_stage_io_insert']],
    'yield stream events update insert update' => [static fn (): mixed => array_column($mixed()['yield_stream'], 'event'), ['update', 'insert', 'update']],
    'yield stream statuses include skipped' => [static fn (): mixed => array_column($mixed()['yield_stream'], 'status'), ['changed', 'changed', 'skipped']],
    'yield stream changed flags' => [static fn (): mixed => array_column($mixed()['yield_stream'], 'changed'), [true, true, false]],
    'view row names preserved' => [static fn (): mixed => array_column(array_column($mixed()['yield_stream'], 'view_row'), 'name'), ['base_url', 'module_registry', 'landing_url']],
    'incoming table names projected' => [static fn (): mixed => array_column(array_column($mixed()['yield_stream'], 'incoming_row'), 'key_name'), ['base_url', 'module_registry', 'landing_url']],
    'incoming table ids projected' => [static fn (): mixed => array_column(array_column($mixed()['yield_stream'], 'incoming_row'), 'setting_id'), [101, 102, 103]],
    'update current row is old base_url' => [static fn (): mixed => $mixed()['yield_stream'][0]['current_row']['key_value'], 'https://old.test'],
    'insert current row is null' => [static fn (): mixed => $mixed()['yield_stream'][1]['current_row'], null],
    'skipped current row is old landing_url' => [static fn (): mixed => $mixed()['yield_stream'][2]['current_row']['key_value'], 'https://landing_url.test'],
    'update next row has new setting id' => [static fn (): mixed => $mixed()['yield_stream'][0]['next_row']['setting_id'], 101],
    'update next row includes after trigger mutation' => [static fn (): mixed => $mixed()['yield_stream'][0]['next_row']['touched'], 'after-update'],
    'insert next row carries before trigger mutation' => [static fn (): mixed => $mixed()['yield_stream'][1]['next_row']['touched'], 'insert-before'],
    'skipped next row remains current row' => [static fn (): mixed => $mixed()['yield_stream'][2]['next_row']['key_value'], 'https://landing_url.test'],
    'skipped next row did not use incoming id' => [static fn (): mixed => $mixed()['yield_stream'][2]['next_row']['setting_id'], 2],
    'returning row count excludes skipped' => [static fn (): mixed => count($mixed()['returning_rows']), 2],
    'returning names include update and insert' => [static fn (): mixed => array_column($mixed()['returning_rows'], 'name'), ['base_url', 'module_registry']],
    'returning values use before after-trigger image' => [static fn (): mixed => array_column($mixed()['returning_rows'], 'value'), ['https://new.test', 'enabled']],
    'returning incoming values projected' => [static fn (): mixed => array_column($mixed()['returning_rows'], 'incoming_value'), ['https://new.test', 'enabled']],
    'callable returning event labels' => [static fn (): mixed => array_column($mixed()['returning_rows'], 'expr3'), ['update:update', 'insert:insert']],
    'callable returning old value for update' => [static fn (): mixed => $mixed()['returning_rows'][0]['expr4'], 'https://old.test'],
    'callable returning old value for insert null' => [static fn (): mixed => $mixed()['returning_rows'][1]['expr4'], null],
    'yield returning mirrors returning rows' => [static fn (): mixed => array_column(array_filter(array_column($mixed()['yield_stream'], 'returning')), 'name'), ['base_url', 'module_registry']],
    'yield skipped returning null' => [static fn (): mixed => $mixed()['yield_stream'][2]['returning'], null],
    'parent names after statement' => [static fn (): mixed => array_column($mixed()['parent'], 'key_name'), ['base_url', 'landing_url', 'display_title', 'module_registry']],
    'parent ids after statement' => [static fn (): mixed => array_column($mixed()['parent'], 'setting_id'), [101, 2, 3, 102]],
    'parent touched values show after insert/update triggers' => [static fn (): mixed => array_column($mixed()['parent'], 'touched'), ['after-update', 'seed', 'seed', 'insert-before']],
    'child keys rekey update and inserted meta row' => [static fn (): mixed => array_column($mixed()['child'], 'setting_id'), [101, 2, 3, 102]],
    'trigger effects include before update before insert after update after insert' => [static fn (): mixed => array_column($mixed()['trigger_effects'], 'trigger'), ['app_settings_bu_rekey_meta', 'app_settings_au_touch', 'app_settings_ai_normalize_insert', 'app_settings_ai_meta']],
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
    'rollback parent ids restored' => [static fn (): mixed => array_column($rollback()['parent'], 'setting_id'), [1, 2, 3]],
    'rollback child ids restored' => [static fn (): mixed => array_column($rollback()['child'], 'setting_id'), [1, 2, 3]],
    'rollback returning rows suppressed' => [static fn (): mixed => $rollback()['returning_rows'], []],
    'rollback yield stream keeps prior successful diagnostic' => [static fn (): mixed => array_column($rollback()['yield_stream'], 'ordinal'), [0]],
    'rollback prior diagnostic next row was computed' => [static fn (): mixed => $rollback()['yield_stream'][0]['next_row']['setting_id'], 201],
    'rollback trigger effects keep prior row diagnostics' => [static fn (): mixed => array_column($rollback()['trigger_effects'], 'view_ordinal'), [0, 0]],

    'deferred violation not rolled back' => [static fn (): mixed => $deferred()['rolled_back'], false],
    'deferred violation records changed row' => [static fn (): mixed => $deferred()['changes'], 1],
    'deferred violation tags view ordinal' => [static fn (): mixed => $deferred()['foreign_key_violations'][0]['view_ordinal'], 0],
    'deferred violation visible before and after triggers' => [static fn (): mixed => $deferred()['yield_stream'][0]['foreign_key_violation_count'], 2],
    'deferred next row retains update' => [static fn (): mixed => $deferred()['yield_stream'][0]['next_row']['key_value'], 'deferred'],
    'deferred child orphan visible' => [static fn (): mixed => $deferred()['child'][1]['setting_id'], 999],

    'empty savepoint rejected' => [static fn (): mixed => $run([], null, null, null, null, null, ''), InvalidArgumentException::class],
    'non view instead of trigger rejected' => [static fn (): mixed => $run([], $badTrigger), InvalidArgumentException::class],
    'empty mapping rejected' => [static fn (): mixed => $run([], null, []), InvalidArgumentException::class],
    'missing mapping column rejected' => [static fn (): mixed => $run([], null, ['missing' => 'key_name']), InvalidArgumentException::class],
    'row missing view column rejected' => [static fn (): mixed => $run([['import_id' => 1, 'name' => 'oops', 'load_policy_flag' => 'no']]), InvalidArgumentException::class],
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
