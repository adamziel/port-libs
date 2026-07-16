<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertTriggerForeignKeyYieldPlan;

$tests = [];

$parents = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'autoload' => 'yes', 'revision' => 5],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://home.test', 'autoload' => 'yes', 'revision' => 3],
    ['option_id' => 3, 'option_name' => 'blogname', 'option_value' => 'Old Blog', 'autoload' => 'no', 'revision' => 2],
];

$children = [
    ['meta_id' => 10, 'option_id' => 1, 'meta_key' => 'source', 'meta_value' => 'core'],
    ['meta_id' => 11, 'option_id' => 2, 'meta_key' => 'source', 'meta_value' => 'core'],
    ['meta_id' => 12, 'option_id' => 3, 'meta_key' => 'source', 'meta_value' => 'core'],
];

$assignments = [
    'option_id' => static fn (array $old, array $incoming): mixed => $incoming['option_id'],
    'option_value' => static fn (array $old, array $incoming): mixed => $incoming['option_value'],
    'autoload' => static fn (array $old, array $incoming): mixed => $incoming['autoload'],
    'revision' => static fn (array $old, array $incoming): int => (int) $old['revision'] + (int) $incoming['revision'],
];

$foreignKey = ['parent_key' => 'option_id', 'child_key' => 'option_id', 'deferred' => true];

$triggers = [
    [
        'name' => 'wp_options_bi_alias',
        'timing' => 'before',
        'event' => 'insert',
        'action' => 'set-new',
        'when' => ['new.option_name', '=', 'fresh_plugin'],
        'set' => ['option_id' => 20],
        'values' => ['name' => 'new.option_name', 'key' => 'new.option_id'],
    ],
    [
        'name' => 'wp_options_au_target_touch',
        'timing' => 'after',
        'event' => 'update',
        'action' => 'set-new',
        'set' => ['option_value' => 'after-trigger-touch', 'revision' => 999],
        'values' => ['name' => 'new.option_name', 'key' => 'new.option_id'],
    ],
    [
        'name' => 'wp_options_ai_meta',
        'timing' => 'after',
        'event' => 'insert',
        'action' => 'insert-child',
        'row' => ['meta_id' => 'new.option_id', 'option_id' => 'new.parent_key', 'meta_key' => 'autoload', 'meta_value' => 'new.autoload'],
        'values' => ['name' => 'new.option_name', 'key' => 'new.option_id'],
    ],
    [
        'name' => 'wp_options_bu_rekey_meta',
        'timing' => 'before',
        'event' => 'update',
        'action' => 'update-child',
        'match' => 'old.option_id',
        'set_child_key' => 'new.option_id',
        'values' => ['old_key' => 'old.option_id', 'new_key' => 'new.option_id'],
    ],
];

$returning = [
    'option_id',
    'option_name',
    'option_value',
    ['expr' => 'excluded.option_id', 'as' => 'excluded_option_id'],
    ['expr' => 'new.revision', 'as' => 'statement_revision'],
    static fn (array $new, ?array $old, array $incoming, string $event): string => $event . ':' . ($old['option_name'] ?? 'insert') . '->' . $new['option_name'],
];

$updateReturning = [
    'option_id',
    ['expr' => 'old.option_id', 'as' => 'old_option_id'],
    ['expr' => 'excluded.option_id', 'as' => 'excluded_option_id'],
    ['expr' => 'new.revision', 'as' => 'statement_revision'],
];

$run = static function (array $incoming, ?callable $where = null, ?array $triggerSet = null, ?array $projection = null) use ($parents, $children, $assignments, $foreignKey, $triggers, $returning): array {
    return SQLiteUpsertTriggerForeignKeyYieldPlan::execute(
        $parents,
        $children,
        $incoming,
        ['option_name'],
        $assignments,
        $foreignKey,
        $triggerSet ?? $triggers,
        $where,
        $projection ?? $returning,
    );
};

$mixedPlan = static fn (): array => $run([
    ['option_id' => 101, 'option_name' => 'siteurl', 'option_value' => 'https://new.test', 'autoload' => 'yes', 'revision' => 4],
    ['option_id' => 102, 'option_name' => 'fresh_plugin', 'option_value' => 'enabled', 'autoload' => 'no', 'revision' => 1],
    ['option_id' => 103, 'option_name' => 'home', 'option_value' => 'https://skip.test', 'autoload' => 'skip', 'revision' => 7],
], static fn (array $old, array $incoming): bool => $incoming['autoload'] !== 'skip');

$defaultReturningPlan = static fn (): array => SQLiteUpsertTriggerForeignKeyYieldPlan::execute(
    $parents,
    $children,
    [['option_id' => 101, 'option_name' => 'siteurl', 'option_value' => 'https://new.test', 'autoload' => 'yes', 'revision' => 4]],
    ['option_name'],
    $assignments,
    $foreignKey,
    $triggers,
);

$updateOldReturningPlan = static fn (): array => $run(
    [['option_id' => 101, 'option_name' => 'siteurl', 'option_value' => 'https://new.test', 'autoload' => 'yes', 'revision' => 4]],
    null,
    null,
    $updateReturning,
);

$deferredBreakRepairPlan = static fn (): array => $run(
    [['option_id' => 303, 'option_name' => 'blogname', 'option_value' => 'Fixed Blog', 'autoload' => 'yes', 'revision' => 6]],
    null,
    [
        [
            'name' => 'wp_options_bu_orphan_meta',
            'timing' => 'before',
            'event' => 'update',
            'action' => 'update-child',
            'match' => 'old.option_id',
            'set_child_key' => 999,
            'values' => ['old_key' => 'old.option_id', 'new_key' => 'new.option_id'],
        ],
        [
            'name' => 'wp_options_au_repair_meta',
            'timing' => 'after',
            'event' => 'update',
            'action' => 'update-child',
            'match' => 999,
            'set_child_key' => 'new.option_id',
            'values' => ['name' => 'new.option_name', 'key' => 'new.option_id'],
        ],
    ],
    $updateReturning,
);

$cases = [
    'mixed changes include update and insert only' => [static fn (): mixed => $mixedPlan()['changes'], 2],
    'mixed statuses include skipped update' => [static fn (): mixed => array_column($mixedPlan()['yielded'], 'status'), ['changed', 'changed', 'skipped']],
    'mixed events preserve statement order' => [static fn (): mixed => array_column($mixedPlan()['yielded'], 'event'), ['update', 'insert', 'update']],
    'mixed skipped row has no returning image' => [static fn (): mixed => $mixedPlan()['yielded'][2]['returning'], null],
    'mixed skipped row still exposes old key' => [static fn (): mixed => $mixedPlan()['yielded'][2]['old_key'], 2],
    'mixed update returning excluded key is incoming row' => [static fn (): mixed => $mixedPlan()['yielded'][0]['returning']['excluded_option_id'], 101],
    'mixed update returning key is statement new key' => [static fn (): mixed => $mixedPlan()['yielded'][0]['returning']['option_id'], 101],
    'mixed update returning value precedes after trigger target change' => [static fn (): mixed => $mixedPlan()['yielded'][0]['returning']['option_value'], 'https://new.test'],
    'mixed update final parent reflects after trigger target change' => [static fn (): mixed => $mixedPlan()['parent'][0]['option_value'], 'after-trigger-touch'],
    'mixed update returning revision precedes after trigger target change' => [static fn (): mixed => $mixedPlan()['yielded'][0]['returning']['statement_revision'], 9],
    'mixed update final parent revision reflects after trigger target change' => [static fn (): mixed => $mixedPlan()['parent'][0]['revision'], 999],
    'mixed update callable returning label uses update event' => [static fn (): mixed => $mixedPlan()['yielded'][0]['returning']['expr5'], 'update:siteurl->siteurl'],
    'mixed insert returning key includes before trigger rewrite' => [static fn (): mixed => $mixedPlan()['yielded'][1]['returning']['option_id'], 20],
    'mixed insert returning excluded key preserves incoming value' => [static fn (): mixed => $mixedPlan()['yielded'][1]['returning']['excluded_option_id'], 102],
    'mixed insert returning name is fresh plugin' => [static fn (): mixed => $mixedPlan()['yielded'][1]['returning']['option_name'], 'fresh_plugin'],
    'mixed insert returning revision is inserted row revision' => [static fn (): mixed => $mixedPlan()['yielded'][1]['returning']['statement_revision'], 1],
    'mixed insert callable returning label uses insert event' => [static fn (): mixed => $mixedPlan()['yielded'][1]['returning']['expr5'], 'insert:insert->fresh_plugin'],
    'mixed child keys include before update rekey and insert child' => [static fn (): mixed => array_column($mixedPlan()['child'], 'option_id'), [101, 2, 3, 20]],
    'mixed trigger order includes before update before after update' => [static fn (): mixed => array_column($mixedPlan()['trigger_effects'], 'trigger'), ['wp_options_bu_rekey_meta', 'wp_options_au_target_touch', 'wp_options_bi_alias', 'wp_options_ai_meta']],
    'mixed parent order preserves update slot and append' => [static fn (): mixed => array_column($mixedPlan()['parent'], 'option_name'), ['siteurl', 'home', 'blogname', 'fresh_plugin']],
    'mixed yielded new keys use returning image not skipped incoming old row' => [static fn (): mixed => array_column($mixedPlan()['yielded'], 'new_key'), [101, 20, 103]],
    'mixed returning rows count changed rows only' => [static fn (): mixed => count(array_filter(array_column($mixedPlan()['yielded'], 'returning'))), 2],
    'mixed no foreign key violations' => [static fn (): mixed => $mixedPlan()['foreign_key_violations'], []],
    'mixed first returning row omits old projection by default' => [static fn (): mixed => array_key_exists('old_option_id', $mixedPlan()['yielded'][0]['returning']), false],
    'mixed insert returning row omits old projection by default' => [static fn (): mixed => array_key_exists('old_option_id', $mixedPlan()['yielded'][1]['returning']), false],
    'mixed skipped incoming is not inserted' => [static fn (): mixed => array_column($mixedPlan()['parent'], 'option_value'), ['after-trigger-touch', 'https://home.test', 'Old Blog', 'enabled']],
    'mixed skipped incoming is not counted as update' => [static fn (): mixed => count($mixedPlan()['updated']), 1],
    'mixed inserted list contains trigger rewritten row' => [static fn (): mixed => $mixedPlan()['inserted'][0]['option_id'], 20],
    'mixed updated list contains after trigger final row' => [static fn (): mixed => $mixedPlan()['updated'][0]['option_value'], 'after-trigger-touch'],

    'default returning includes statement row when projection omitted' => [static fn (): mixed => $defaultReturningPlan()['yielded'][0]['returning']['option_value'], 'https://new.test'],
    'default returning includes assignment revision' => [static fn (): mixed => $defaultReturningPlan()['yielded'][0]['returning']['revision'], 9],
    'default returning excludes after trigger target mutation' => [static fn (): mixed => $defaultReturningPlan()['yielded'][0]['returning']['revision'] !== $defaultReturningPlan()['parent'][0]['revision'], true],
    'update old returning old key is current row' => [static fn (): mixed => $updateOldReturningPlan()['yielded'][0]['returning']['old_option_id'], 1],
    'update old returning excluded key is incoming row' => [static fn (): mixed => $updateOldReturningPlan()['yielded'][0]['returning']['excluded_option_id'], 101],
    'update old returning statement revision includes assignment' => [static fn (): mixed => $updateOldReturningPlan()['yielded'][0]['returning']['statement_revision'], 9],

    'deferred repair returning records statement key before after repair' => [static fn (): mixed => $deferredBreakRepairPlan()['yielded'][0]['returning']['option_id'], 303],
    'deferred repair returning old key' => [static fn (): mixed => $deferredBreakRepairPlan()['yielded'][0]['returning']['old_option_id'], 3],
    'deferred repair returning excluded key' => [static fn (): mixed => $deferredBreakRepairPlan()['yielded'][0]['returning']['excluded_option_id'], 303],
    'deferred repair sees transient statement violation' => [static fn (): mixed => $deferredBreakRepairPlan()['yielded'][0]['violations_before_after_triggers'], 1],
    'deferred repair clears final after trigger violation' => [static fn (): mixed => $deferredBreakRepairPlan()['yielded'][0]['violations_after_triggers'], 0],
    'deferred repair still records statement phase violation evidence' => [static fn (): mixed => $deferredBreakRepairPlan()['foreign_key_violations'][0]['phase'], 'statement'],
    'deferred repair final child key repaired' => [static fn (): mixed => $deferredBreakRepairPlan()['child'][2]['option_id'], 303],

    'star returning nests statement row' => [static fn (): mixed => $run([['option_id' => 201, 'option_name' => 'siteurl', 'option_value' => 'star', 'autoload' => 'yes', 'revision' => 1]], null, null, ['*'])['yielded'][0]['returning']['*']['option_value'], 'star'],
    'aliased new column returning works' => [static fn (): mixed => $run([['option_id' => 202, 'option_name' => 'siteurl', 'option_value' => 'alias', 'autoload' => 'yes', 'revision' => 1]], null, null, [['expr' => 'new.option_value', 'as' => 'value_after_before_triggers']])['yielded'][0]['returning']['value_after_before_triggers'], 'alias'],
    'plain column returning works' => [static fn (): mixed => $run([['option_id' => 203, 'option_name' => 'siteurl', 'option_value' => 'plain', 'autoload' => 'yes', 'revision' => 1]], null, null, ['option_value'])['yielded'][0]['returning']['option_value'], 'plain'],
    'excluded column returning works' => [static fn (): mixed => $run([['option_id' => 204, 'option_name' => 'siteurl', 'option_value' => 'excluded', 'autoload' => 'yes', 'revision' => 1]], null, null, [['expr' => 'excluded.option_value', 'as' => 'incoming_value']])['yielded'][0]['returning']['incoming_value'], 'excluded'],
    'callable returning sees update event' => [static fn (): mixed => $run([['option_id' => 205, 'option_name' => 'siteurl', 'option_value' => 'callable', 'autoload' => 'yes', 'revision' => 1]], null, null, [static fn (array $new, ?array $old, array $incoming, string $event): string => $event])['yielded'][0]['returning']['expr0'], 'update'],
    'callable returning sees insert event' => [static fn (): mixed => $run([['option_id' => 206, 'option_name' => 'brand_new', 'option_value' => 'callable', 'autoload' => 'yes', 'revision' => 1]], null, null, [static fn (array $new, ?array $old, array $incoming, string $event): string => $event])['yielded'][0]['returning']['expr0'], 'insert'],
    'old returning on insert throws' => [static fn (): mixed => $run([['option_id' => 207, 'option_name' => 'insert_old', 'option_value' => 'bad', 'autoload' => 'yes', 'revision' => 1]], null, [], ['old.option_id']), InvalidArgumentException::class],
    'malformed returning alias throws' => [static fn (): mixed => $run([['option_id' => 208, 'option_name' => 'siteurl', 'option_value' => 'bad', 'autoload' => 'yes', 'revision' => 1]], null, null, [['expr' => 'option_id', 'as' => 'bad-alias']]), InvalidArgumentException::class],
    'missing returning column throws' => [static fn (): mixed => $run([['option_id' => 209, 'option_name' => 'siteurl', 'option_value' => 'bad', 'autoload' => 'yes', 'revision' => 1]], null, null, ['missing_column']), InvalidArgumentException::class],
    'missing excluded returning column throws' => [static fn (): mixed => $run([['option_id' => 210, 'option_name' => 'siteurl', 'option_value' => 'bad', 'autoload' => 'yes', 'revision' => 1]], null, null, [['expr' => 'excluded.missing', 'as' => 'missing']]), InvalidArgumentException::class],
    'malformed returning entry throws' => [static fn (): mixed => $run([['option_id' => 211, 'option_name' => 'siteurl', 'option_value' => 'bad', 'autoload' => 'yes', 'revision' => 1]], null, null, [123]), InvalidArgumentException::class],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['upsert trigger returning current next26 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
