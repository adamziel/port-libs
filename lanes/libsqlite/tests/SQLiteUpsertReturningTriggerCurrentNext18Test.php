<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;
use PortLibs\LibSqlite\SQLiteUpsertReturningTriggerPlan;

$rows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes', 'revision' => 5, 'touched' => 'old'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes', 'revision' => 2, 'touched' => 'old'],
    ['option_id' => 3, 'option_name' => 'blogname', 'option_value' => 'Old Blog', 'autoload' => 'no', 'revision' => 7, 'touched' => 'old'],
];

$assignments = [
    'option_value' => static fn (array $current, array $excluded): mixed => $excluded['option_value'],
    'autoload' => static fn (array $current, array $excluded): mixed => $excluded['autoload'],
    'revision' => static fn (array $current, array $excluded): int => (int) $current['revision'] + (int) $excluded['revision'],
    'touched' => static fn (array $current, array $excluded): mixed => $excluded['touched'],
];

$triggers = [
    [
        'name' => 'before_option_insert',
        'timing' => 'before',
        'event' => 'insert',
        'table' => 'wp_options',
        'values' => ['action' => 'insert-before', 'name' => 'new.option_name', 'new_value' => 'new.option_value'],
    ],
    [
        'name' => 'after_option_insert',
        'timing' => 'after',
        'event' => 'insert',
        'table' => 'wp_options',
        'values' => ['action' => 'insert-after', 'name' => 'new.option_name', 'new_revision' => 'new.revision'],
        'mutate_target' => true,
        'set' => ['touched' => 'after-insert-trigger'],
    ],
    [
        'name' => 'before_option_update',
        'timing' => 'before',
        'event' => 'update',
        'table' => 'wp_options',
        'of' => ['option_value'],
        'values' => ['action' => 'update-before', 'name' => 'new.option_name', 'old_value' => 'old.option_value', 'new_value' => 'new.option_value'],
    ],
    [
        'name' => 'after_option_update',
        'timing' => 'after',
        'event' => 'update',
        'table' => 'wp_options',
        'when' => ['new.autoload', '=', 'yes'],
        'values' => ['action' => 'update-after', 'name' => 'new.option_name', 'old_revision' => 'old.revision', 'new_revision' => 'new.revision'],
        'mutate_target' => true,
        'set' => ['touched' => 'after-update-trigger'],
    ],
];

$run = static function (array $incomingRows, ?callable $where = null) use ($rows, $assignments, $triggers): array {
    return SQLiteUpsertReturningTriggerPlan::execute(
        $rows,
        $incomingRows,
        ['option_name'],
        $assignments,
        $triggers,
        $where,
        [['option_name']],
    );
};

$mixedPlan = static fn (): array => $run([
    ['option_id' => 10, 'option_name' => 'siteurl', 'option_value' => 'https://new.test', 'autoload' => 'yes', 'revision' => 4, 'touched' => 'statement-update'],
    ['option_id' => 11, 'option_name' => 'plugin_enabled', 'option_value' => '1', 'autoload' => 'no', 'revision' => 1, 'touched' => 'statement-insert'],
    ['option_id' => 12, 'option_name' => 'home', 'option_value' => 'https://skip.test', 'autoload' => 'yes', 'revision' => 3, 'touched' => 'statement-skip'],
], static fn (array $current, array $excluded): bool => $excluded['touched'] !== 'statement-skip');

$repeatPlan = static fn (): array => $run([
    ['option_id' => 20, 'option_name' => 'transient_plugin', 'option_value' => 'first', 'autoload' => 'no', 'revision' => 1, 'touched' => 'insert-first'],
    ['option_id' => 21, 'option_name' => 'transient_plugin', 'option_value' => 'second', 'autoload' => 'yes', 'revision' => 2, 'touched' => 'update-second'],
]);

$updateOnlyPlan = static fn (): array => $run([
    ['option_id' => 30, 'option_name' => 'blogname', 'option_value' => 'Updated Blog', 'autoload' => 'no', 'revision' => 1, 'touched' => 'statement-no-after'],
]);

$cases = [
    'mixed changes count excludes skipped conflict' => [static fn (): mixed => $mixedPlan()['changes'], 2],
    'mixed returning rows include update then insert only' => [static fn (): mixed => array_column($mixedPlan()['returning_rows'], 'option_name'), ['siteurl', 'plugin_enabled']],
    'mixed skipped row is tracked by excluded name' => [static fn (): mixed => array_column($mixedPlan()['skipped_rows'], 'option_name'), ['home']],
    'mixed inserted row appears in inserted rows' => [static fn (): mixed => array_column($mixedPlan()['inserted_rows'], 'option_name'), ['plugin_enabled']],
    'mixed updated row appears in updated rows' => [static fn (): mixed => array_column($mixedPlan()['updated_rows'], 'option_name'), ['siteurl']],
    'mixed returning update reports statement value before after trigger' => [static fn (): mixed => $mixedPlan()['returning_rows'][0]['touched'], 'statement-update'],
    'mixed after update row reflects after trigger mutation' => [static fn (): mixed => $mixedPlan()['after'][0]['touched'], 'after-update-trigger'],
    'mixed returning insert reports statement value before after trigger' => [static fn (): mixed => $mixedPlan()['returning_rows'][1]['touched'], 'statement-insert'],
    'mixed after insert row reflects after trigger mutation' => [static fn (): mixed => $mixedPlan()['after'][3]['touched'], 'after-insert-trigger'],
    'mixed skipped row keeps original after value' => [static fn (): mixed => $mixedPlan()['after'][1]['option_value'], 'https://home.test'],
    'mixed before image remains unchanged' => [static fn (): mixed => array_column($mixedPlan()['before'], 'touched'), ['old', 'old', 'old']],
    'mixed trigger names fire in sqlite order' => [static fn (): mixed => array_column($mixedPlan()['trigger_effects'], 'trigger'), ['before_option_update', 'after_option_update', 'before_option_insert', 'after_option_insert']],
    'mixed trigger actions preserve timing order' => [static fn (): mixed => array_column(array_column($mixedPlan()['trigger_effects'], 'row'), 'action'), ['update-before', 'update-after', 'insert-before', 'insert-after']],
    'mixed before update trigger sees old value' => [static fn (): mixed => $mixedPlan()['trigger_effects'][0]['row']['old_value'], 'https://old.test'],
    'mixed before update trigger sees new value' => [static fn (): mixed => $mixedPlan()['trigger_effects'][0]['row']['new_value'], 'https://new.test'],
    'mixed after update trigger sees old revision' => [static fn (): mixed => $mixedPlan()['trigger_effects'][1]['row']['old_revision'], 5],
    'mixed after update trigger sees new revision' => [static fn (): mixed => $mixedPlan()['trigger_effects'][1]['row']['new_revision'], 9],
    'mixed before insert trigger sees inserted option name' => [static fn (): mixed => $mixedPlan()['trigger_effects'][2]['row']['name'], 'plugin_enabled'],
    'mixed after insert trigger sees inserted revision' => [static fn (): mixed => $mixedPlan()['trigger_effects'][3]['row']['new_revision'], 1],
    'mixed skipped update fires no triggers for skipped row' => [static fn (): mixed => in_array('home', array_column(array_column($mixedPlan()['trigger_effects'], 'row'), 'name'), true), false],

    'repeat first statement row inserts' => [static fn (): mixed => $repeatPlan()['inserted_rows'][0]['option_value'], 'first'],
    'repeat second statement row updates current inserted row' => [static fn (): mixed => $repeatPlan()['updated_rows'][0]['option_value'], 'second'],
    'repeat returning order preserves insert then update' => [static fn (): mixed => array_column($repeatPlan()['returning_rows'], 'option_value'), ['first', 'second']],
    'repeat returning update uses current inserted revision' => [static fn (): mixed => $repeatPlan()['returning_rows'][1]['revision'], 3],
    'repeat after table has one transient row' => [static fn (): mixed => count(array_filter($repeatPlan()['after'], static fn (array $row): bool => $row['option_name'] === 'transient_plugin')), 1],
    'repeat triggers include insert and update pairs' => [static fn (): mixed => array_column($repeatPlan()['trigger_effects'], 'event'), ['insert', 'insert', 'update', 'update']],
    'repeat update before trigger old value is first inserted value' => [static fn (): mixed => $repeatPlan()['trigger_effects'][2]['row']['old_value'], 'first'],
    'repeat update before trigger new value is second incoming value' => [static fn (): mixed => $repeatPlan()['trigger_effects'][2]['row']['new_value'], 'second'],
    'repeat after trigger mutates final row only after returning' => [static fn (): mixed => $repeatPlan()['after'][3]['touched'], 'after-update-trigger'],
    'repeat returning row keeps statement touched value' => [static fn (): mixed => $repeatPlan()['returning_rows'][1]['touched'], 'update-second'],

    'update only where false produces no change' => [static fn (): mixed => $run([['option_id' => 40, 'option_name' => 'siteurl', 'option_value' => 'skip', 'autoload' => 'yes', 'revision' => 1, 'touched' => 'skip']], static fn (): bool => false)['changes'], 0],
    'update only where false produces no trigger effects' => [static fn (): mixed => $run([['option_id' => 40, 'option_name' => 'siteurl', 'option_value' => 'skip', 'autoload' => 'yes', 'revision' => 1, 'touched' => 'skip']], static fn (): bool => false)['trigger_effects'], []],
    'update only after trigger when false does not mutate target' => [static fn (): mixed => $updateOnlyPlan()['after'][2]['touched'], 'statement-no-after'],
    'update only after trigger when false omitted from effects' => [static fn (): mixed => array_column($updateOnlyPlan()['trigger_effects'], 'trigger'), ['before_option_update']],
    'update only before trigger still sees old row' => [static fn (): mixed => $updateOnlyPlan()['trigger_effects'][0]['row']['old_value'], 'Old Blog'],
    'update only returning still reports changed row' => [static fn (): mixed => $updateOnlyPlan()['returning_rows'][0]['option_value'], 'Updated Blog'],
    'update only changes count is one' => [static fn (): mixed => $updateOnlyPlan()['changes'], 1],

    'project returning rows after trigger plan' => [static fn (): mixed => SQLiteUpsertDoUpdateWherePlan::returningRows($mixedPlan()['returning_rows'], ['name' => 'option_name', 'touch' => 'touched']), [['name' => 'siteurl', 'touch' => 'statement-update'], ['name' => 'plugin_enabled', 'touch' => 'statement-insert']]],
    'missing trigger new column throws' => [static fn (): mixed => SQLiteUpsertReturningTriggerPlan::execute($rows, [['option_id' => 50, 'option_name' => 'x', 'option_value' => 'x', 'autoload' => 'no', 'revision' => 1, 'touched' => 'x']], ['option_name'], $assignments, [[
        'name' => 'bad_new',
        'timing' => 'before',
        'event' => 'insert',
        'table' => 'wp_options',
        'values' => ['bad' => 'new.missing'],
    ]]), InvalidArgumentException::class],
    'insert trigger old reference throws' => [static fn (): mixed => SQLiteUpsertReturningTriggerPlan::execute($rows, [['option_id' => 51, 'option_name' => 'x', 'option_value' => 'x', 'autoload' => 'no', 'revision' => 1, 'touched' => 'x']], ['option_name'], $assignments, [[
        'name' => 'bad_old',
        'timing' => 'before',
        'event' => 'insert',
        'table' => 'wp_options',
        'values' => ['bad' => 'old.option_name'],
    ]]), InvalidArgumentException::class],
    'unsupported trigger table throws' => [static fn (): mixed => SQLiteUpsertReturningTriggerPlan::execute($rows, [['option_id' => 52, 'option_name' => 'x', 'option_value' => 'x', 'autoload' => 'no', 'revision' => 1, 'touched' => 'x']], ['option_name'], $assignments, [[
        'name' => 'bad_table',
        'timing' => 'before',
        'event' => 'insert',
        'table' => 'wp_postmeta',
    ]]), InvalidArgumentException::class],
    'unsupported trigger when operator throws' => [static fn (): mixed => SQLiteUpsertReturningTriggerPlan::execute($rows, [['option_id' => 53, 'option_name' => 'siteurl', 'option_value' => 'x', 'autoload' => 'yes', 'revision' => 1, 'touched' => 'x']], ['option_name'], $assignments, [[
        'name' => 'bad_when',
        'timing' => 'before',
        'event' => 'update',
        'table' => 'wp_options',
        'when' => ['new.autoload', 'LIKE', 'y%'],
    ]]), InvalidArgumentException::class],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['upsert returning trigger current next18 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
