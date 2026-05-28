<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSchemaRecord;
use PortLibs\LibSqlite\SQLiteTriggerReturningUpsertViewCurrentNextPlan;

$schema = [
    new SQLiteSchemaRecord('table', 'wp_options', 'wp_options', 2, 'CREATE TABLE wp_options(option_id integer UNIQUE, option_name text UNIQUE, option_value text, autoload text, touched text)', 1),
    new SQLiteSchemaRecord('view', 'wp_option_import_view', 'wp_option_import_view', 0, 'CREATE VIEW wp_option_import_view(import_id, name, value, autoload_flag) AS SELECT option_id, option_name, option_value, autoload FROM wp_options', 2),
    new SQLiteSchemaRecord('trigger', 'wp_option_import_io_insert', 'wp_option_import_view', 0, 'CREATE TRIGGER wp_option_import_io_insert INSTEAD OF INSERT ON wp_option_import_view BEGIN INSERT INTO wp_options(option_id, option_name, option_value, autoload) VALUES(new.import_id, new.name, new.value, new.autoload_flag) ON CONFLICT(option_name) DO UPDATE SET option_id=excluded.option_id, option_value=excluded.option_value, autoload=excluded.autoload RETURNING option_id, option_name, option_value; END', 3),
];

$parents = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes', 'touched' => 'seed'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes', 'touched' => 'seed'],
    ['option_id' => 3, 'option_name' => 'blogname', 'option_value' => 'Old Blog', 'autoload' => 'no', 'touched' => 'seed'],
];

$children = [
    ['meta_id' => 11, 'option_id' => 1, 'meta_key' => 'source', 'meta_value' => 'core'],
    ['meta_id' => 12, 'option_id' => 2, 'meta_key' => 'source', 'meta_value' => 'core'],
    ['meta_id' => 13, 'option_id' => 3, 'meta_key' => 'source', 'meta_value' => 'core'],
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
    ['expr' => 'new.option_id', 'as' => 'id'],
    ['expr' => 'new.option_name', 'as' => 'name'],
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'excluded.option_id', 'as' => 'incoming_id'],
    static fn (array $new, ?array $old, array $incoming, string $event): string => $event . ':' . ($old['option_id'] ?? 'insert'),
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
        'name' => 'wp_options_bi_seed_touch',
        'timing' => 'before',
        'event' => 'insert',
        'action' => 'set-new',
        'set' => ['touched' => 'before-insert'],
        'values' => ['name' => 'new.option_name', 'new_key' => 'new.option_id'],
    ],
    [
        'name' => 'wp_options_au_touch',
        'timing' => 'after',
        'event' => 'update',
        'action' => 'set-new',
        'set' => ['touched' => 'after-update'],
        'values' => ['name' => 'new.option_name', 'new_key' => 'new.option_id'],
    ],
    [
        'name' => 'wp_options_ai_meta',
        'timing' => 'after',
        'event' => 'insert',
        'action' => 'insert-child',
        'row' => ['meta_id' => 'new.option_id', 'option_id' => 'new.parent_key', 'meta_key' => 'imported', 'meta_value' => 'new.option_name'],
        'values' => ['name' => 'new.option_name', 'new_key' => 'new.option_id'],
    ],
];

$run = static function (array $viewRows, ?array $uniqueConstraints = [['option_name'], ['option_id']], ?array $triggerRows = null) use ($schema, $parents, $children, $mapping, $assignments, $foreignKey, $triggers, $returning): array {
    return SQLiteTriggerReturningUpsertViewCurrentNextPlan::execute(
        $schema,
        'wp_option_import_io_insert',
        $parents,
        $children,
        $viewRows,
        $mapping,
        ['option_name'],
        $assignments,
        $foreignKey,
        $triggerRows ?? $triggers,
        static fn (array $old, array $incoming): bool => $incoming['autoload'] !== 'skip',
        $returning,
        'next140',
        $uniqueConstraints,
    );
};

$successful = static fn (): array => $run([
    ['import_id' => 101, 'name' => 'siteurl', 'value' => 'https://new.test', 'autoload_flag' => 'yes'],
    ['import_id' => 102, 'name' => 'plugin_enabled', 'value' => '1', 'autoload_flag' => 'no'],
    ['import_id' => 103, 'name' => 'home', 'value' => 'skip-me', 'autoload_flag' => 'skip'],
    ['import_id' => 104, 'name' => 'blogname', 'value' => 'New Blog', 'autoload_flag' => 'yes'],
]);

$secondaryConflict = static fn (): array => $run([
    ['import_id' => 101, 'name' => 'siteurl', 'value' => 'https://first.test', 'autoload_flag' => 'yes'],
    ['import_id' => 2, 'name' => 'blogname', 'value' => 'collides-with-home-id', 'autoload_flag' => 'yes'],
    ['import_id' => 105, 'name' => 'never_seen', 'value' => 'nope', 'autoload_flag' => 'no'],
]);

$withoutSecondaryConstraint = static fn (): array => $run([
    ['import_id' => 101, 'name' => 'siteurl', 'value' => 'https://first.test', 'autoload_flag' => 'yes'],
    ['import_id' => 2, 'name' => 'blogname', 'value' => 'collides-with-home-id', 'autoload_flag' => 'yes'],
], [['option_name']]);

$afterTriggerConflictTriggers = $triggers;
$afterTriggerConflictTriggers[] = [
    'name' => 'wp_options_au_duplicate_id',
    'timing' => 'after',
    'event' => 'update',
    'action' => 'set-new',
    'when' => ['new.option_name', '=', 'siteurl'],
    'set' => ['option_id' => 2],
    'values' => ['name' => 'new.option_name', 'new_key' => 'new.option_id'],
];
$afterTriggerConflict = static fn (): array => $run([
    ['import_id' => 101, 'name' => 'siteurl', 'value' => 'https://after-trigger.test', 'autoload_flag' => 'yes'],
], [['option_name'], ['option_id']], $afterTriggerConflictTriggers);

$cases = [
    'successful statement not rolled back' => [static fn (): mixed => $successful()['rolled_back'], false],
    'successful statement rows count includes skipped' => [static fn (): mixed => $successful()['statement_rows'], 4],
    'successful changes exclude skipped row' => [static fn (): mixed => $successful()['changes'], 3],
    'successful yield ordinals preserve source order' => [static fn (): mixed => array_column($successful()['yield_stream'], 'ordinal'), [0, 1, 2, 3]],
    'successful yield events include update insert skipped update' => [static fn (): mixed => array_column($successful()['yield_stream'], 'event'), ['update', 'insert', 'update', 'update']],
    'successful yield statuses include skipped' => [static fn (): mixed => array_column($successful()['yield_stream'], 'status'), ['changed', 'changed', 'skipped', 'changed']],
    'successful current rows are statement current source' => [static fn (): mixed => array_map(static fn (array $row): mixed => $row['current_row']['option_id'] ?? null, $successful()['yield_stream']), [1, null, 2, 3]],
    'successful next rows include changed keys' => [static fn (): mixed => array_map(static fn (array $row): mixed => $row['next_row']['option_id'] ?? null, $successful()['yield_stream']), [101, 102, 2, 104]],
    'successful returning ids' => [static fn (): mixed => array_column($successful()['returning_rows'], 'id'), [101, 102, 104]],
    'successful returning names' => [static fn (): mixed => array_column($successful()['returning_rows'], 'name'), ['siteurl', 'plugin_enabled', 'blogname']],
    'successful returning incoming ids' => [static fn (): mixed => array_column($successful()['returning_rows'], 'incoming_id'), [101, 102, 104]],
    'successful callable returning labels' => [static fn (): mixed => array_column($successful()['returning_rows'], 'expr4'), ['update:1', 'insert:insert', 'update:3']],
    'successful skipped returning null' => [static fn (): mixed => $successful()['yield_stream'][2]['returning'], null],
    'successful parent ids final' => [static fn (): mixed => array_column($successful()['parent'], 'option_id'), [101, 2, 104, 102]],
    'successful parent names final' => [static fn (): mixed => array_column($successful()['parent'], 'option_name'), ['siteurl', 'home', 'blogname', 'plugin_enabled']],
    'successful parent touched final' => [static fn (): mixed => array_column($successful()['parent'], 'touched'), ['after-update', 'seed', 'after-update', 'before-insert']],
    'successful child keys include rekeyed updates and inserted child' => [static fn (): mixed => array_column($successful()['child'], 'option_id'), [101, 2, 104, 102]],
    'successful trigger effects order' => [static fn (): mixed => array_column($successful()['trigger_effects'], 'trigger'), ['wp_options_bu_rekey_meta', 'wp_options_au_touch', 'wp_options_bi_seed_touch', 'wp_options_ai_meta', 'wp_options_bu_rekey_meta', 'wp_options_au_touch']],
    'successful trigger effect ordinals' => [static fn (): mixed => array_column($successful()['trigger_effects'], 'view_ordinal'), [0, 0, 1, 1, 3, 3]],
    'successful no fk violations' => [static fn (): mixed => $successful()['foreign_key_violations'], []],
    'successful dependencies include current-next stream' => [static fn (): mixed => in_array('sqlite-current-next-row-stream', $successful()['dependencies'], true), true],

    'secondary unique conflict rolls back' => [static fn (): mixed => $secondaryConflict()['rolled_back'], true],
    'secondary unique conflict ordinal' => [static fn (): mixed => $secondaryConflict()['rolled_back_at_ordinal'], 1],
    'secondary unique conflict reason' => [static fn (): mixed => str_contains((string) $secondaryConflict()['rollback_reason'], 'unique constraint conflict'), true],
    'secondary unique conflict changes reset' => [static fn (): mixed => $secondaryConflict()['changes'], 0],
    'secondary unique conflict parent restored ids' => [static fn (): mixed => array_column($secondaryConflict()['parent'], 'option_id'), [1, 2, 3]],
    'secondary unique conflict child restored ids' => [static fn (): mixed => array_column($secondaryConflict()['child'], 'option_id'), [1, 2, 3]],
    'secondary unique conflict returning suppressed' => [static fn (): mixed => $secondaryConflict()['returning_rows'], []],
    'secondary unique conflict keeps first diagnostic only' => [static fn (): mixed => array_column($secondaryConflict()['yield_stream'], 'ordinal'), [0]],
    'secondary unique conflict first diagnostic next id' => [static fn (): mixed => $secondaryConflict()['yield_stream'][0]['next_row']['option_id'], 101],
    'secondary unique conflict effects keep first row diagnostics' => [static fn (): mixed => array_column($secondaryConflict()['trigger_effects'], 'view_ordinal'), [0, 0]],

    'without secondary unique constraint does not roll back' => [static fn (): mixed => $withoutSecondaryConstraint()['rolled_back'], false],
    'without secondary unique constraint changes both rows' => [static fn (): mixed => $withoutSecondaryConstraint()['changes'], 2],
    'without secondary unique constraint permits duplicate ids' => [static fn (): mixed => array_count_values(array_map('strval', array_column($withoutSecondaryConstraint()['parent'], 'option_id')))['2'], 2],
    'without secondary unique constraint returning includes conflict row' => [static fn (): mixed => array_column($withoutSecondaryConstraint()['returning_rows'], 'name'), ['siteurl', 'blogname']],

    'after trigger secondary unique conflict rolls back' => [static fn (): mixed => $afterTriggerConflict()['rolled_back'], true],
    'after trigger secondary unique conflict ordinal' => [static fn (): mixed => $afterTriggerConflict()['rolled_back_at_ordinal'], 0],
    'after trigger secondary unique conflict reason' => [static fn (): mixed => str_contains((string) $afterTriggerConflict()['rollback_reason'], 'after trigger produced a unique constraint conflict'), true],
    'after trigger secondary unique conflict suppresses yield stream' => [static fn (): mixed => $afterTriggerConflict()['yield_stream'], []],
    'after trigger secondary unique conflict suppresses returning' => [static fn (): mixed => $afterTriggerConflict()['returning_rows'], []],
    'after trigger secondary unique conflict restores parents' => [static fn (): mixed => array_column($afterTriggerConflict()['parent'], 'option_id'), [1, 2, 3]],

    'empty unique constraints rejected' => [static fn (): mixed => $run([], []), InvalidArgumentException::class],
    'malformed unique constraint rejected' => [static fn (): mixed => $run([], [['option_name'], ['bad column']]), InvalidArgumentException::class],
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
