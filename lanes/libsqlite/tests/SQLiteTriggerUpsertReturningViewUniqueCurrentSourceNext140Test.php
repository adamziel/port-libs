<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteTriggerReturningUpsertViewCurrentNextPlan;

$schema = [
    new SQLiteSchemaRecord('table', 'app_settings', 'app_settings', 2, 'CREATE TABLE app_settings(setting_id integer UNIQUE, key_name text UNIQUE, key_value text, load_policy text, touched text)', 1),
    new SQLiteSchemaRecord('view', 'app_setting_import_view', 'app_setting_import_view', 0, 'CREATE VIEW app_setting_import_view(import_id, name, value, load_policy_flag) AS SELECT setting_id, key_name, key_value, load_policy FROM app_settings', 2),
    new SQLiteSchemaRecord('trigger', 'app_setting_import_io_insert', 'app_setting_import_view', 0, 'CREATE TRIGGER app_setting_import_io_insert INSTEAD OF INSERT ON app_setting_import_view BEGIN INSERT INTO app_settings(setting_id, key_name, key_value, load_policy) VALUES(new.import_id, new.name, new.value, new.load_policy_flag) ON CONFLICT(key_name) DO UPDATE SET setting_id=excluded.setting_id, key_value=excluded.key_value, load_policy=excluded.load_policy RETURNING setting_id, key_name, key_value; END', 3),
];

$parents = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes', 'touched' => 'seed'],
    ['setting_id' => 2, 'key_name' => 'landing_url', 'key_value' => 'https://landing_url.test', 'load_policy' => 'yes', 'touched' => 'seed'],
    ['setting_id' => 3, 'key_name' => 'display_title', 'key_value' => 'Old Title', 'load_policy' => 'no', 'touched' => 'seed'],
];

$children = [
    ['meta_id' => 11, 'setting_id' => 1, 'meta_key' => 'source', 'meta_value' => 'core'],
    ['meta_id' => 12, 'setting_id' => 2, 'meta_key' => 'source', 'meta_value' => 'core'],
    ['meta_id' => 13, 'setting_id' => 3, 'meta_key' => 'source', 'meta_value' => 'core'],
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
    ['expr' => 'new.setting_id', 'as' => 'id'],
    ['expr' => 'new.key_name', 'as' => 'name'],
    ['expr' => 'new.key_value', 'as' => 'value'],
    ['expr' => 'excluded.setting_id', 'as' => 'incoming_id'],
    static fn (array $new, ?array $old, array $incoming, string $event): string => $event . ':' . ($old['setting_id'] ?? 'insert'),
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
        'name' => 'app_settings_bi_seed_touch',
        'timing' => 'before',
        'event' => 'insert',
        'action' => 'set-new',
        'set' => ['touched' => 'before-insert'],
        'values' => ['name' => 'new.key_name', 'new_key' => 'new.setting_id'],
    ],
    [
        'name' => 'app_settings_au_touch',
        'timing' => 'after',
        'event' => 'update',
        'action' => 'set-new',
        'set' => ['touched' => 'after-update'],
        'values' => ['name' => 'new.key_name', 'new_key' => 'new.setting_id'],
    ],
    [
        'name' => 'app_settings_ai_meta',
        'timing' => 'after',
        'event' => 'insert',
        'action' => 'insert-child',
        'row' => ['meta_id' => 'new.setting_id', 'setting_id' => 'new.parent_key', 'meta_key' => 'imported', 'meta_value' => 'new.key_name'],
        'values' => ['name' => 'new.key_name', 'new_key' => 'new.setting_id'],
    ],
];

$run = static function (array $viewRows, ?array $uniqueConstraints = [['key_name'], ['setting_id']], ?array $triggerRows = null) use ($schema, $parents, $children, $mapping, $assignments, $foreignKey, $triggers, $returning): array {
    return SQLiteTriggerReturningUpsertViewCurrentNextPlan::execute(
        $schema,
        'app_setting_import_io_insert',
        $parents,
        $children,
        $viewRows,
        $mapping,
        ['key_name'],
        $assignments,
        $foreignKey,
        $triggerRows ?? $triggers,
        static fn (array $old, array $incoming): bool => $incoming['load_policy'] !== 'skip',
        $returning,
        'next140',
        $uniqueConstraints,
    );
};

$successful = static fn (): array => $run([
    ['import_id' => 101, 'name' => 'base_url', 'value' => 'https://new.test', 'load_policy_flag' => 'yes'],
    ['import_id' => 102, 'name' => 'module_enabled', 'value' => '1', 'load_policy_flag' => 'no'],
    ['import_id' => 103, 'name' => 'landing_url', 'value' => 'skip-me', 'load_policy_flag' => 'skip'],
    ['import_id' => 104, 'name' => 'display_title', 'value' => 'New Title', 'load_policy_flag' => 'yes'],
]);

$secondaryConflict = static fn (): array => $run([
    ['import_id' => 101, 'name' => 'base_url', 'value' => 'https://first.test', 'load_policy_flag' => 'yes'],
    ['import_id' => 2, 'name' => 'display_title', 'value' => 'collides-with-landing_url-id', 'load_policy_flag' => 'yes'],
    ['import_id' => 105, 'name' => 'unseen_setting', 'value' => 'nope', 'load_policy_flag' => 'no'],
]);

$withoutSecondaryConstraint = static fn (): array => $run([
    ['import_id' => 101, 'name' => 'base_url', 'value' => 'https://first.test', 'load_policy_flag' => 'yes'],
    ['import_id' => 2, 'name' => 'display_title', 'value' => 'collides-with-landing_url-id', 'load_policy_flag' => 'yes'],
], [['key_name']]);

$afterTriggerConflictTriggers = $triggers;
$afterTriggerConflictTriggers[] = [
    'name' => 'app_settings_au_duplicate_id',
    'timing' => 'after',
    'event' => 'update',
    'action' => 'set-new',
    'when' => ['new.key_name', '=', 'base_url'],
    'set' => ['setting_id' => 2],
    'values' => ['name' => 'new.key_name', 'new_key' => 'new.setting_id'],
];
$afterTriggerConflict = static fn (): array => $run([
    ['import_id' => 101, 'name' => 'base_url', 'value' => 'https://after-trigger.test', 'load_policy_flag' => 'yes'],
], [['key_name'], ['setting_id']], $afterTriggerConflictTriggers);

$cases = [
    'successful statement not rolled back' => [static fn (): mixed => $successful()['rolled_back'], false],
    'successful statement rows count includes skipped' => [static fn (): mixed => $successful()['statement_rows'], 4],
    'successful changes exclude skipped row' => [static fn (): mixed => $successful()['changes'], 3],
    'successful yield ordinals preserve source order' => [static fn (): mixed => array_column($successful()['yield_stream'], 'ordinal'), [0, 1, 2, 3]],
    'successful yield events include update insert skipped update' => [static fn (): mixed => array_column($successful()['yield_stream'], 'event'), ['update', 'insert', 'update', 'update']],
    'successful yield statuses include skipped' => [static fn (): mixed => array_column($successful()['yield_stream'], 'status'), ['changed', 'changed', 'skipped', 'changed']],
    'successful current rows are statement current source' => [static fn (): mixed => array_map(static fn (array $row): mixed => $row['current_row']['setting_id'] ?? null, $successful()['yield_stream']), [1, null, 2, 3]],
    'successful next rows include changed keys' => [static fn (): mixed => array_map(static fn (array $row): mixed => $row['next_row']['setting_id'] ?? null, $successful()['yield_stream']), [101, 102, 2, 104]],
    'successful returning ids' => [static fn (): mixed => array_column($successful()['returning_rows'], 'id'), [101, 102, 104]],
    'successful returning names' => [static fn (): mixed => array_column($successful()['returning_rows'], 'name'), ['base_url', 'module_enabled', 'display_title']],
    'successful returning incoming ids' => [static fn (): mixed => array_column($successful()['returning_rows'], 'incoming_id'), [101, 102, 104]],
    'successful callable returning labels' => [static fn (): mixed => array_column($successful()['returning_rows'], 'expr4'), ['update:1', 'insert:insert', 'update:3']],
    'successful skipped returning null' => [static fn (): mixed => $successful()['yield_stream'][2]['returning'], null],
    'successful parent ids final' => [static fn (): mixed => array_column($successful()['parent'], 'setting_id'), [101, 2, 104, 102]],
    'successful parent names final' => [static fn (): mixed => array_column($successful()['parent'], 'key_name'), ['base_url', 'landing_url', 'display_title', 'module_enabled']],
    'successful parent touched final' => [static fn (): mixed => array_column($successful()['parent'], 'touched'), ['after-update', 'seed', 'after-update', 'before-insert']],
    'successful child keys include rekeyed updates and inserted child' => [static fn (): mixed => array_column($successful()['child'], 'setting_id'), [101, 2, 104, 102]],
    'successful trigger effects order' => [static fn (): mixed => array_column($successful()['trigger_effects'], 'trigger'), ['app_settings_bu_rekey_meta', 'app_settings_au_touch', 'app_settings_bi_seed_touch', 'app_settings_ai_meta', 'app_settings_bu_rekey_meta', 'app_settings_au_touch']],
    'successful trigger effect ordinals' => [static fn (): mixed => array_column($successful()['trigger_effects'], 'view_ordinal'), [0, 0, 1, 1, 3, 3]],
    'successful no fk violations' => [static fn (): mixed => $successful()['foreign_key_violations'], []],
    'successful dependencies include current-next stream' => [static fn (): mixed => in_array('sqlite-current-next-row-stream', $successful()['dependencies'], true), true],

    'secondary unique conflict rolls back' => [static fn (): mixed => $secondaryConflict()['rolled_back'], true],
    'secondary unique conflict ordinal' => [static fn (): mixed => $secondaryConflict()['rolled_back_at_ordinal'], 1],
    'secondary unique conflict reason' => [static fn (): mixed => str_contains((string) $secondaryConflict()['rollback_reason'], 'unique constraint conflict'), true],
    'secondary unique conflict changes reset' => [static fn (): mixed => $secondaryConflict()['changes'], 0],
    'secondary unique conflict parent restored ids' => [static fn (): mixed => array_column($secondaryConflict()['parent'], 'setting_id'), [1, 2, 3]],
    'secondary unique conflict child restored ids' => [static fn (): mixed => array_column($secondaryConflict()['child'], 'setting_id'), [1, 2, 3]],
    'secondary unique conflict returning suppressed' => [static fn (): mixed => $secondaryConflict()['returning_rows'], []],
    'secondary unique conflict keeps first diagnostic only' => [static fn (): mixed => array_column($secondaryConflict()['yield_stream'], 'ordinal'), [0]],
    'secondary unique conflict first diagnostic next id' => [static fn (): mixed => $secondaryConflict()['yield_stream'][0]['next_row']['setting_id'], 101],
    'secondary unique conflict effects keep first row diagnostics' => [static fn (): mixed => array_column($secondaryConflict()['trigger_effects'], 'view_ordinal'), [0, 0]],

    'without secondary unique constraint does not roll back' => [static fn (): mixed => $withoutSecondaryConstraint()['rolled_back'], false],
    'without secondary unique constraint changes both rows' => [static fn (): mixed => $withoutSecondaryConstraint()['changes'], 2],
    'without secondary unique constraint permits duplicate ids' => [static fn (): mixed => array_count_values(array_map('strval', array_column($withoutSecondaryConstraint()['parent'], 'setting_id')))['2'], 2],
    'without secondary unique constraint returning includes conflict row' => [static fn (): mixed => array_column($withoutSecondaryConstraint()['returning_rows'], 'name'), ['base_url', 'display_title']],

    'after trigger secondary unique conflict rolls back' => [static fn (): mixed => $afterTriggerConflict()['rolled_back'], true],
    'after trigger secondary unique conflict ordinal' => [static fn (): mixed => $afterTriggerConflict()['rolled_back_at_ordinal'], 0],
    'after trigger secondary unique conflict reason' => [static fn (): mixed => str_contains((string) $afterTriggerConflict()['rollback_reason'], 'after trigger produced a unique constraint conflict'), true],
    'after trigger secondary unique conflict suppresses yield stream' => [static fn (): mixed => $afterTriggerConflict()['yield_stream'], []],
    'after trigger secondary unique conflict suppresses returning' => [static fn (): mixed => $afterTriggerConflict()['returning_rows'], []],
    'after trigger secondary unique conflict restores parents' => [static fn (): mixed => array_column($afterTriggerConflict()['parent'], 'setting_id'), [1, 2, 3]],

    'empty unique constraints rejected' => [static fn (): mixed => $run([], []), InvalidArgumentException::class],
    'malformed unique constraint rejected' => [static fn (): mixed => $run([], [['key_name'], ['bad column']]), InvalidArgumentException::class],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['trigger upsert returning view unique current source next140 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
