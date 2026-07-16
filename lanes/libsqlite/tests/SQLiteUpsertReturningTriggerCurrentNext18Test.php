<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;
use PortLibs\LibSqlite\SQLiteUpsertReturningTriggerPlan;

$rows = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'https://old.test', 'load_policy' => 'yes', 'revision' => 5, 'touched' => 'old'],
    ['setting_id' => 2, 'key_name' => 'public_url', 'key_value' => 'https://public.test', 'load_policy' => 'yes', 'revision' => 2, 'touched' => 'old'],
    ['setting_id' => 3, 'key_name' => 'site_title', 'key_value' => 'Old Title', 'load_policy' => 'no', 'revision' => 7, 'touched' => 'old'],
];

$assignments = [
    'key_value' => static fn (array $current, array $excluded): mixed => $excluded['key_value'],
    'load_policy' => static fn (array $current, array $excluded): mixed => $excluded['load_policy'],
    'revision' => static fn (array $current, array $excluded): int => (int) $current['revision'] + (int) $excluded['revision'],
    'touched' => static fn (array $current, array $excluded): mixed => $excluded['touched'],
];

$triggers = [
    [
        'name' => 'before_setting_insert',
        'timing' => 'before',
        'event' => 'insert',
        'table' => 'app_settings',
        'values' => ['action' => 'insert-before', 'name' => 'new.key_name', 'new_value' => 'new.key_value'],
    ],
    [
        'name' => 'after_setting_insert',
        'timing' => 'after',
        'event' => 'insert',
        'table' => 'app_settings',
        'values' => ['action' => 'insert-after', 'name' => 'new.key_name', 'new_revision' => 'new.revision'],
        'mutate_target' => true,
        'set' => ['touched' => 'after-insert-trigger'],
    ],
    [
        'name' => 'before_setting_update',
        'timing' => 'before',
        'event' => 'update',
        'table' => 'app_settings',
        'of' => ['key_value'],
        'values' => ['action' => 'update-before', 'name' => 'new.key_name', 'old_value' => 'old.key_value', 'new_value' => 'new.key_value'],
    ],
    [
        'name' => 'after_setting_update',
        'timing' => 'after',
        'event' => 'update',
        'table' => 'app_settings',
        'when' => ['new.load_policy', '=', 'yes'],
        'values' => ['action' => 'update-after', 'name' => 'new.key_name', 'old_revision' => 'old.revision', 'new_revision' => 'new.revision'],
        'mutate_target' => true,
        'set' => ['touched' => 'after-update-trigger'],
    ],
];

$run = static function (array $incomingRows, ?callable $where = null) use ($rows, $assignments, $triggers): array {
    return SQLiteUpsertReturningTriggerPlan::execute(
        $rows,
        $incomingRows,
        ['key_name'],
        $assignments,
        $triggers,
        $where,
        [['key_name']],
    );
};

$mixedPlan = static fn (): array => $run([
    ['setting_id' => 10, 'key_name' => 'base_url', 'key_value' => 'https://new.test', 'load_policy' => 'yes', 'revision' => 4, 'touched' => 'statement-update'],
    ['setting_id' => 11, 'key_name' => 'module_enabled', 'key_value' => '1', 'load_policy' => 'no', 'revision' => 1, 'touched' => 'statement-insert'],
    ['setting_id' => 12, 'key_name' => 'public_url', 'key_value' => 'https://skip.test', 'load_policy' => 'yes', 'revision' => 3, 'touched' => 'statement-skip'],
], static fn (array $current, array $excluded): bool => $excluded['touched'] !== 'statement-skip');

$repeatPlan = static fn (): array => $run([
    ['setting_id' => 20, 'key_name' => 'module_cache', 'key_value' => 'first', 'load_policy' => 'no', 'revision' => 1, 'touched' => 'insert-first'],
    ['setting_id' => 21, 'key_name' => 'module_cache', 'key_value' => 'second', 'load_policy' => 'yes', 'revision' => 2, 'touched' => 'update-second'],
]);

$updateOnlyPlan = static fn (): array => $run([
    ['setting_id' => 30, 'key_name' => 'site_title', 'key_value' => 'Updated Title', 'load_policy' => 'no', 'revision' => 1, 'touched' => 'statement-no-after'],
]);

$cases = [
    'mixed changes count excludes skipped conflict' => [static fn (): mixed => $mixedPlan()['changes'], 2],
    'mixed returning rows include update then insert only' => [static fn (): mixed => array_column($mixedPlan()['returning_rows'], 'key_name'), ['base_url', 'module_enabled']],
    'mixed skipped row is tracked by excluded name' => [static fn (): mixed => array_column($mixedPlan()['skipped_rows'], 'key_name'), ['public_url']],
    'mixed inserted row appears in inserted rows' => [static fn (): mixed => array_column($mixedPlan()['inserted_rows'], 'key_name'), ['module_enabled']],
    'mixed updated row appears in updated rows' => [static fn (): mixed => array_column($mixedPlan()['updated_rows'], 'key_name'), ['base_url']],
    'mixed returning update reports statement value before after trigger' => [static fn (): mixed => $mixedPlan()['returning_rows'][0]['touched'], 'statement-update'],
    'mixed after update row reflects after trigger mutation' => [static fn (): mixed => $mixedPlan()['after'][0]['touched'], 'after-update-trigger'],
    'mixed returning insert reports statement value before after trigger' => [static fn (): mixed => $mixedPlan()['returning_rows'][1]['touched'], 'statement-insert'],
    'mixed after insert row reflects after trigger mutation' => [static fn (): mixed => $mixedPlan()['after'][3]['touched'], 'after-insert-trigger'],
    'mixed skipped row keeps original after value' => [static fn (): mixed => $mixedPlan()['after'][1]['key_value'], 'https://public.test'],
    'mixed before image remains unchanged' => [static fn (): mixed => array_column($mixedPlan()['before'], 'touched'), ['old', 'old', 'old']],
    'mixed trigger names fire in sqlite order' => [static fn (): mixed => array_column($mixedPlan()['trigger_effects'], 'trigger'), ['before_setting_update', 'after_setting_update', 'before_setting_insert', 'after_setting_insert']],
    'mixed trigger actions preserve timing order' => [static fn (): mixed => array_column(array_column($mixedPlan()['trigger_effects'], 'row'), 'action'), ['update-before', 'update-after', 'insert-before', 'insert-after']],
    'mixed before update trigger sees old value' => [static fn (): mixed => $mixedPlan()['trigger_effects'][0]['row']['old_value'], 'https://old.test'],
    'mixed before update trigger sees new value' => [static fn (): mixed => $mixedPlan()['trigger_effects'][0]['row']['new_value'], 'https://new.test'],
    'mixed after update trigger sees old revision' => [static fn (): mixed => $mixedPlan()['trigger_effects'][1]['row']['old_revision'], 5],
    'mixed after update trigger sees new revision' => [static fn (): mixed => $mixedPlan()['trigger_effects'][1]['row']['new_revision'], 9],
    'mixed before insert trigger sees inserted setting name' => [static fn (): mixed => $mixedPlan()['trigger_effects'][2]['row']['name'], 'module_enabled'],
    'mixed after insert trigger sees inserted revision' => [static fn (): mixed => $mixedPlan()['trigger_effects'][3]['row']['new_revision'], 1],
    'mixed skipped update fires no triggers for skipped row' => [static fn (): mixed => in_array('public_url', array_column(array_column($mixedPlan()['trigger_effects'], 'row'), 'name'), true), false],

    'repeat first statement row inserts' => [static fn (): mixed => $repeatPlan()['inserted_rows'][0]['key_value'], 'first'],
    'repeat second statement row updates current inserted row' => [static fn (): mixed => $repeatPlan()['updated_rows'][0]['key_value'], 'second'],
    'repeat returning order preserves insert then update' => [static fn (): mixed => array_column($repeatPlan()['returning_rows'], 'key_value'), ['first', 'second']],
    'repeat returning update uses current inserted revision' => [static fn (): mixed => $repeatPlan()['returning_rows'][1]['revision'], 3],
    'repeat after table has one cache row' => [static fn (): mixed => count(array_filter($repeatPlan()['after'], static fn (array $row): bool => $row['key_name'] === 'module_cache')), 1],
    'repeat triggers include insert and update pairs' => [static fn (): mixed => array_column($repeatPlan()['trigger_effects'], 'event'), ['insert', 'insert', 'update', 'update']],
    'repeat update before trigger old value is first inserted value' => [static fn (): mixed => $repeatPlan()['trigger_effects'][2]['row']['old_value'], 'first'],
    'repeat update before trigger new value is second incoming value' => [static fn (): mixed => $repeatPlan()['trigger_effects'][2]['row']['new_value'], 'second'],
    'repeat after trigger mutates final row only after returning' => [static fn (): mixed => $repeatPlan()['after'][3]['touched'], 'after-update-trigger'],
    'repeat returning row keeps statement touched value' => [static fn (): mixed => $repeatPlan()['returning_rows'][1]['touched'], 'update-second'],

    'update only where false produces no change' => [static fn (): mixed => $run([['setting_id' => 40, 'key_name' => 'base_url', 'key_value' => 'skip', 'load_policy' => 'yes', 'revision' => 1, 'touched' => 'skip']], static fn (): bool => false)['changes'], 0],
    'update only where false produces no trigger effects' => [static fn (): mixed => $run([['setting_id' => 40, 'key_name' => 'base_url', 'key_value' => 'skip', 'load_policy' => 'yes', 'revision' => 1, 'touched' => 'skip']], static fn (): bool => false)['trigger_effects'], []],
    'update only after trigger when false does not mutate target' => [static fn (): mixed => $updateOnlyPlan()['after'][2]['touched'], 'statement-no-after'],
    'update only after trigger when false omitted from effects' => [static fn (): mixed => array_column($updateOnlyPlan()['trigger_effects'], 'trigger'), ['before_setting_update']],
    'update only before trigger still sees old row' => [static fn (): mixed => $updateOnlyPlan()['trigger_effects'][0]['row']['old_value'], 'Old Title'],
    'update only returning still reports changed row' => [static fn (): mixed => $updateOnlyPlan()['returning_rows'][0]['key_value'], 'Updated Title'],
    'update only changes count is one' => [static fn (): mixed => $updateOnlyPlan()['changes'], 1],

    'project returning rows after trigger plan' => [static fn (): mixed => SQLiteUpsertDoUpdateWherePlan::returningRows($mixedPlan()['returning_rows'], ['name' => 'key_name', 'touch' => 'touched']), [['name' => 'base_url', 'touch' => 'statement-update'], ['name' => 'module_enabled', 'touch' => 'statement-insert']]],
    'missing trigger new column throws' => [static fn (): mixed => SQLiteUpsertReturningTriggerPlan::execute($rows, [['setting_id' => 50, 'key_name' => 'x', 'key_value' => 'x', 'load_policy' => 'no', 'revision' => 1, 'touched' => 'x']], ['key_name'], $assignments, [[
        'name' => 'bad_new',
        'timing' => 'before',
        'event' => 'insert',
        'table' => 'app_settings',
        'values' => ['bad' => 'new.missing'],
    ]]), InvalidArgumentException::class],
    'insert trigger old reference throws' => [static fn (): mixed => SQLiteUpsertReturningTriggerPlan::execute($rows, [['setting_id' => 51, 'key_name' => 'x', 'key_value' => 'x', 'load_policy' => 'no', 'revision' => 1, 'touched' => 'x']], ['key_name'], $assignments, [[
        'name' => 'bad_old',
        'timing' => 'before',
        'event' => 'insert',
        'table' => 'app_settings',
        'values' => ['bad' => 'old.key_name'],
    ]]), InvalidArgumentException::class],
    'unsupported trigger table throws' => [static fn (): mixed => SQLiteUpsertReturningTriggerPlan::execute($rows, [['setting_id' => 52, 'key_name' => 'x', 'key_value' => 'x', 'load_policy' => 'no', 'revision' => 1, 'touched' => 'x']], ['key_name'], $assignments, [[
        'name' => 'bad_table',
        'timing' => 'before',
        'event' => 'insert',
        'table' => 'other_table',
    ]]), InvalidArgumentException::class],
    'unsupported trigger when operator throws' => [static fn (): mixed => SQLiteUpsertReturningTriggerPlan::execute($rows, [['setting_id' => 53, 'key_name' => 'base_url', 'key_value' => 'x', 'load_policy' => 'yes', 'revision' => 1, 'touched' => 'x']], ['key_name'], $assignments, [[
        'name' => 'bad_when',
        'timing' => 'before',
        'event' => 'update',
        'table' => 'app_settings',
        'when' => ['new.load_policy', 'LIKE', 'y%'],
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
