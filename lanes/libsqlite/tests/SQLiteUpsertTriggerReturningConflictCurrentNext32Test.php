<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertTriggerForeignKeyYieldPlan;

$tests = [];

$parents = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'slug' => 'core-site', 'slot' => 'primary', 'autoload' => 'yes', 'option_value' => 'https://old.test', 'revision' => 5],
    ['option_id' => 2, 'option_name' => 'home', 'slug' => 'core-home', 'slot' => 'secondary', 'autoload' => 'yes', 'option_value' => 'https://home.test', 'revision' => 3],
    ['option_id' => 3, 'option_name' => 'blogname', 'slug' => 'display-name', 'slot' => 'display', 'autoload' => 'no', 'option_value' => 'Old Blog', 'revision' => 2],
];

$children = [
    ['meta_id' => 10, 'option_id' => 1, 'meta_key' => 'source', 'meta_value' => 'core'],
    ['meta_id' => 11, 'option_id' => 2, 'meta_key' => 'source', 'meta_value' => 'core'],
    ['meta_id' => 12, 'option_id' => 3, 'meta_key' => 'source', 'meta_value' => 'core'],
];

$assignments = [
    'option_id' => static fn (array $old, array $incoming): mixed => $incoming['option_id'],
    'slug' => static fn (array $old, array $incoming): mixed => $incoming['slug'],
    'slot' => static fn (array $old, array $incoming): mixed => $incoming['slot'],
    'autoload' => static fn (array $old, array $incoming): mixed => $incoming['autoload'],
    'option_value' => static fn (array $old, array $incoming): mixed => $incoming['option_value'],
    'revision' => static fn (array $old, array $incoming): int => (int) $old['revision'] + (int) $incoming['revision'],
];

$foreignKey = ['parent_key' => 'option_id', 'child_key' => 'option_id', 'deferred' => true];
$uniqueConstraints = [['option_name'], ['slug'], ['slot'], ['autoload', 'slot']];

$returning = [
    'option_name',
    'slug',
    'slot',
    ['expr' => 'new.revision', 'as' => 'statement_revision'],
    ['expr' => 'excluded.slug', 'as' => 'excluded_slug'],
];

$baseTriggers = [
    [
        'name' => 'wp_options_bu_meta_rekey',
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
        'row' => ['meta_id' => 'new.option_id', 'option_id' => 'new.parent_key', 'meta_key' => 'slug', 'meta_value' => 'new.slug'],
        'values' => ['name' => 'new.option_name', 'key' => 'new.option_id'],
    ],
];

$run = static function (array $incoming, ?array $triggers = null, ?callable $where = null, ?array $constraints = null, ?array $projection = null) use ($parents, $children, $assignments, $foreignKey, $baseTriggers, $uniqueConstraints, $returning): array {
    return SQLiteUpsertTriggerForeignKeyYieldPlan::execute(
        $parents,
        $children,
        $incoming,
        ['option_name'],
        $assignments,
        $foreignKey,
        $triggers ?? $baseTriggers,
        $where,
        $projection ?? $returning,
        $constraints ?? $uniqueConstraints,
    );
};

$cleanPlan = static fn (): array => $run([
    ['option_id' => 101, 'option_name' => 'siteurl', 'slug' => 'core-site-next', 'slot' => 'primary-next', 'autoload' => 'manual', 'option_value' => 'https://new.test', 'revision' => 4],
    ['option_id' => 102, 'option_name' => 'fresh_plugin', 'slug' => 'plugin-fresh', 'slot' => 'plugin-slot', 'autoload' => 'plugin', 'option_value' => 'enabled', 'revision' => 1],
    ['option_id' => 103, 'option_name' => 'theme_mods', 'slug' => null, 'slot' => 'json-slot', 'autoload' => null, 'option_value' => '{}', 'revision' => 1],
]);

$repeatPlan = static fn (): array => $run([
    ['option_id' => 201, 'option_name' => 'fresh_repeat', 'slug' => 'repeat-a', 'slot' => 'repeat-slot-a', 'autoload' => 'repeat-auto', 'option_value' => 'one', 'revision' => 1],
    ['option_id' => 202, 'option_name' => 'fresh_repeat', 'slug' => 'repeat-b', 'slot' => 'repeat-slot-b', 'autoload' => 'repeat-auto-b', 'option_value' => 'two', 'revision' => 2],
]);

$skipPlan = static fn (): array => $run([
    ['option_id' => 301, 'option_name' => 'siteurl', 'slug' => 'core-home', 'slot' => 'secondary', 'autoload' => 'yes', 'option_value' => 'would conflict if updated', 'revision' => 1],
    ['option_id' => 302, 'option_name' => 'fresh_after_skip', 'slug' => 'after-skip', 'slot' => 'after-skip-slot', 'autoload' => 'after-skip-auto', 'option_value' => 'ok', 'revision' => 1],
], null, static fn (array $old, array $incoming): bool => $incoming['option_value'] !== 'would conflict if updated');

$beforeSlugConflict = static fn (): array => $run([
    ['option_id' => 401, 'option_name' => 'fresh_slug_alias', 'slug' => 'safe-before', 'slot' => 'safe-slot', 'autoload' => 'safe-auto', 'option_value' => 'bad', 'revision' => 1],
], [[
    'name' => 'wp_options_bi_slug_alias',
    'timing' => 'before',
    'event' => 'insert',
    'action' => 'set-new',
    'set' => ['slug' => 'core-home'],
]]);

$afterSlugConflict = static fn (): array => $run([
    ['option_id' => 402, 'option_name' => 'siteurl', 'slug' => 'site-ok', 'slot' => 'site-ok-slot', 'autoload' => 'site-ok-auto', 'option_value' => 'bad', 'revision' => 1],
], [[
    'name' => 'wp_options_au_slug_collision',
    'timing' => 'after',
    'event' => 'update',
    'action' => 'set-new',
    'set' => ['slug' => 'core-home'],
]]);

$sameStatementPlan = static fn (): array => $run([
    ['option_id' => 501, 'option_name' => 'inserted_slug', 'slug' => 'same-slug', 'slot' => 'same-slot', 'autoload' => 'same-auto', 'option_value' => 'ok', 'revision' => 1],
    ['option_id' => 502, 'option_name' => 'siteurl', 'slug' => 'same-slug', 'slot' => 'site-later-slot', 'autoload' => 'site-later-auto', 'option_value' => 'bad', 'revision' => 1],
]);

$compositePlan = static fn (): array => $run([
    ['option_id' => 601, 'option_name' => 'nullable_one', 'slug' => null, 'slot' => 'json-slot', 'autoload' => null, 'option_value' => 'ok', 'revision' => 1],
    ['option_id' => 602, 'option_name' => 'nullable_two', 'slug' => null, 'slot' => 'json-slot-2', 'autoload' => null, 'option_value' => 'ok', 'revision' => 1],
], null, null, [['option_name'], ['slug'], ['autoload', 'slot']]);

$cases = [
    'clean changes include update and two inserts' => [static fn (): mixed => $cleanPlan()['changes'], 3],
    'clean yielded statuses changed for every row' => [static fn (): mixed => array_column($cleanPlan()['yielded'], 'status'), ['changed', 'changed', 'changed']],
    'clean yielded events update insert insert' => [static fn (): mixed => array_column($cleanPlan()['yielded'], 'event'), ['update', 'insert', 'insert']],
    'clean returning names preserve statement order' => [static fn (): mixed => array_column(array_column($cleanPlan()['yielded'], 'returning'), 'option_name'), ['siteurl', 'fresh_plugin', 'theme_mods']],
    'clean returning slug uses changed current row' => [static fn (): mixed => $cleanPlan()['yielded'][0]['returning']['slug'], 'core-site-next'],
    'clean returning excluded slug is incoming slug' => [static fn (): mixed => $cleanPlan()['yielded'][0]['returning']['excluded_slug'], 'core-site-next'],
    'clean returning revision includes current plus incoming' => [static fn (): mixed => $cleanPlan()['yielded'][0]['returning']['statement_revision'], 9],
    'clean parent names preserve current order plus appends' => [static fn (): mixed => array_column($cleanPlan()['parent'], 'option_name'), ['siteurl', 'home', 'blogname', 'fresh_plugin', 'theme_mods']],
    'clean parent slugs include null insert' => [static fn (): mixed => array_column($cleanPlan()['parent'], 'slug'), ['core-site-next', 'core-home', 'display-name', 'plugin-fresh', null]],
    'clean parent slots include unique replacements' => [static fn (): mixed => array_column($cleanPlan()['parent'], 'slot'), ['primary-next', 'secondary', 'display', 'plugin-slot', 'json-slot']],
    'clean inserted rows are fresh plugin and theme mods' => [static fn (): mixed => array_column($cleanPlan()['inserted'], 'option_name'), ['fresh_plugin', 'theme_mods']],
    'clean updated row is siteurl' => [static fn (): mixed => array_column($cleanPlan()['updated'], 'option_name'), ['siteurl']],
    'clean skipped rows empty' => [static fn (): mixed => $cleanPlan()['skipped'], []],
    'clean meta rekey tracks updated option id' => [static fn (): mixed => array_column($cleanPlan()['child'], 'option_id'), [101, 2, 3, 102, 103]],
    'clean trigger effects include update rekey and insert meta rows' => [static fn (): mixed => array_column($cleanPlan()['trigger_effects'], 'trigger'), ['wp_options_bu_meta_rekey', 'wp_options_ai_meta', 'wp_options_ai_meta']],
    'clean first trigger old key is original siteurl' => [static fn (): mixed => $cleanPlan()['trigger_effects'][0]['row']['old_key'], 1],
    'clean first trigger new key is incoming siteurl' => [static fn (): mixed => $cleanPlan()['trigger_effects'][0]['row']['new_key'], 101],
    'clean first insert trigger sees plugin key' => [static fn (): mixed => $cleanPlan()['trigger_effects'][1]['row']['key'], 102],
    'clean second insert trigger sees theme key' => [static fn (): mixed => $cleanPlan()['trigger_effects'][2]['row']['key'], 103],
    'clean no fk violations before after triggers' => [static fn (): mixed => array_column($cleanPlan()['yielded'], 'violations_before_after_triggers'), [0, 0, 0]],
    'clean no fk violations after triggers' => [static fn (): mixed => array_column($cleanPlan()['yielded'], 'violations_after_triggers'), [0, 0, 0]],
    'clean foreign key violations empty' => [static fn (): mixed => $cleanPlan()['foreign_key_violations'], []],

    'repeat first row inserts' => [static fn (): mixed => $repeatPlan()['inserted'][0]['option_value'], 'one'],
    'repeat second row updates current inserted row' => [static fn (): mixed => $repeatPlan()['updated'][0]['option_value'], 'two'],
    'repeat changes count insert plus update' => [static fn (): mixed => $repeatPlan()['changes'], 2],
    'repeat returning order includes both statement rows' => [static fn (): mixed => array_column(array_column($repeatPlan()['yielded'], 'returning'), 'slug'), ['repeat-a', 'repeat-b']],
    'repeat final table has one repeated option name' => [static fn (): mixed => count(array_filter($repeatPlan()['parent'], static fn (array $row): bool => $row['option_name'] === 'fresh_repeat')), 1],
    'repeat final slug is second row slug' => [static fn (): mixed => array_values(array_filter($repeatPlan()['parent'], static fn (array $row): bool => $row['option_name'] === 'fresh_repeat'))[0]['slug'], 'repeat-b'],
    'repeat child rows include rekeyed current key' => [static fn (): mixed => array_column($repeatPlan()['child'], 'option_id'), [1, 2, 3, 202]],
    'repeat update trigger rekeys inserted child to second key' => [static fn (): mixed => array_values(array_filter($repeatPlan()['child'], static fn (array $row): bool => $row['meta_key'] === 'slug'))[0]['option_id'], 202],

    'skip omits conflict row from returning' => [static fn (): mixed => array_column(array_filter(array_column($skipPlan()['yielded'], 'returning')), 'option_name'), ['fresh_after_skip']],
    'skip records skipped incoming row' => [static fn (): mixed => array_column($skipPlan()['skipped'], 'option_name'), ['siteurl']],
    'skip does not run secondary unique checks for skipped update' => [static fn (): mixed => $skipPlan()['parent'][0]['slug'], 'core-site'],
    'skip still inserts later safe row' => [static fn (): mixed => $skipPlan()['inserted'][0]['option_name'], 'fresh_after_skip'],
    'skip changes exclude skipped conflict' => [static fn (): mixed => $skipPlan()['changes'], 1],
    'skip yielded old key exposes skipped current row' => [static fn (): mixed => $skipPlan()['yielded'][0]['old_key'], 1],
    'skip yielded skipped row has null returning' => [static fn (): mixed => $skipPlan()['yielded'][0]['returning'], null],
    'skip trigger effects only include later insert' => [static fn (): mixed => array_column($skipPlan()['trigger_effects'], 'trigger'), ['wp_options_ai_meta']],

    'before trigger slug collision aborts against current row' => [static fn (): mixed => $beforeSlugConflict(), InvalidArgumentException::class],
    'after trigger slug collision aborts against current row' => [static fn (): mixed => $afterSlugConflict(), InvalidArgumentException::class],
    'insert slug collision aborts against current row' => [static fn (): mixed => $run([['option_id' => 701, 'option_name' => 'fresh_bad_slug', 'slug' => 'core-home', 'slot' => 'fresh-bad-slot', 'autoload' => 'fresh-bad-auto', 'option_value' => 'bad', 'revision' => 1]]), InvalidArgumentException::class],
    'insert slot collision aborts against current row' => [static fn (): mixed => $run([['option_id' => 702, 'option_name' => 'fresh_bad_slot', 'slug' => 'fresh-bad-slot', 'slot' => 'secondary', 'autoload' => 'fresh-bad-auto', 'option_value' => 'bad', 'revision' => 1]]), InvalidArgumentException::class],
    'update slug collision aborts against current row' => [static fn (): mixed => $run([['option_id' => 703, 'option_name' => 'siteurl', 'slug' => 'core-home', 'slot' => 'site-safe-slot', 'autoload' => 'site-safe-auto', 'option_value' => 'bad', 'revision' => 1]]), InvalidArgumentException::class],
    'update slot collision aborts against current row' => [static fn (): mixed => $run([['option_id' => 704, 'option_name' => 'siteurl', 'slug' => 'site-safe-slug', 'slot' => 'display', 'autoload' => 'site-safe-auto', 'option_value' => 'bad', 'revision' => 1]]), InvalidArgumentException::class],
    'same statement inserted slug conflicts with later update' => [static fn (): mixed => $sameStatementPlan(), InvalidArgumentException::class],
    'same statement later insert conflicts with earlier update slot' => [static fn (): mixed => $run([['option_id' => 705, 'option_name' => 'siteurl', 'slug' => 'site-new', 'slot' => 'site-new-slot', 'autoload' => 'site-new-auto', 'option_value' => 'ok', 'revision' => 1], ['option_id' => 706, 'option_name' => 'new_later', 'slug' => 'new-later', 'slot' => 'site-new-slot', 'autoload' => 'new-later-auto', 'option_value' => 'bad', 'revision' => 1]]), InvalidArgumentException::class],
    'same statement second insert conflicts with first insert slug' => [static fn (): mixed => $run([['option_id' => 707, 'option_name' => 'first_insert', 'slug' => 'dup-insert', 'slot' => 'first-insert-slot', 'autoload' => 'first-insert-auto', 'option_value' => 'ok', 'revision' => 1], ['option_id' => 708, 'option_name' => 'second_insert', 'slug' => 'dup-insert', 'slot' => 'second-insert-slot', 'autoload' => 'second-insert-auto', 'option_value' => 'bad', 'revision' => 1]]), InvalidArgumentException::class],

    'composite null slug rows are allowed' => [static fn (): mixed => $compositePlan()['changes'], 2],
    'composite null slug returning names' => [static fn (): mixed => array_column(array_column($compositePlan()['yielded'], 'returning'), 'option_name'), ['nullable_one', 'nullable_two']],
    'composite partial null unique does not conflict' => [static fn (): mixed => array_column($compositePlan()['parent'], 'option_name'), ['siteurl', 'home', 'blogname', 'nullable_one', 'nullable_two']],
    'composite non-null autoload slot detects conflict' => [static fn (): mixed => $run([['option_id' => 709, 'option_name' => 'bad_composite', 'slug' => 'bad-composite', 'slot' => 'primary', 'autoload' => 'yes', 'option_value' => 'bad', 'revision' => 1]], null, null, [['option_name'], ['autoload', 'slot']]), InvalidArgumentException::class],

    'unique constraints reject empty list' => [static fn (): mixed => $run([], null, null, []), InvalidArgumentException::class],
    'unique constraints reject associative list' => [static fn (): mixed => $run([], null, null, ['slug' => ['slug']]), InvalidArgumentException::class],
    'unique constraints reject scalar constraint' => [static fn (): mixed => $run([], null, null, ['slug']), InvalidArgumentException::class],
    'unique constraints reject empty constraint columns' => [static fn (): mixed => $run([], null, null, [[]]), InvalidArgumentException::class],
    'unique constraints reject malformed column' => [static fn (): mixed => $run([], null, null, [['bad-column']]), InvalidArgumentException::class],
    'missing candidate secondary column aborts when checked' => [static fn (): mixed => SQLiteUpsertTriggerForeignKeyYieldPlan::execute($parents, $children, [['option_id' => 710, 'option_name' => 'missing_slug', 'slot' => 'missing-slot', 'autoload' => 'missing-auto', 'option_value' => 'bad', 'revision' => 1]], ['option_name'], $assignments, $foreignKey, [], null, $returning, $uniqueConstraints), InvalidArgumentException::class],
    'missing current secondary column aborts when checked' => [static fn (): mixed => SQLiteUpsertTriggerForeignKeyYieldPlan::execute([['option_id' => 1, 'option_name' => 'siteurl']], [], [['option_id' => 711, 'option_name' => 'fresh', 'slug' => 'fresh', 'slot' => 'fresh-slot', 'autoload' => 'fresh-auto', 'option_value' => 'bad', 'revision' => 1]], ['option_name'], $assignments, $foreignKey, [], null, $returning, [['option_name'], ['slug']]), InvalidArgumentException::class],
    'default unique constraint preserves prior conflict-target only behavior' => [static fn (): mixed => SQLiteUpsertTriggerForeignKeyYieldPlan::execute($parents, $children, [['option_id' => 712, 'option_name' => 'new_default', 'slug' => 'core-home', 'slot' => 'secondary', 'autoload' => 'yes', 'option_value' => 'allowed', 'revision' => 1]], ['option_name'], $assignments, $foreignKey, [])['changes'], 1],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['upsert trigger returning conflict current next32 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
