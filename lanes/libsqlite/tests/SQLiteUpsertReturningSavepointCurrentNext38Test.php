<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertReturningSavepointPlan;

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

$assignments = [
    'option_id' => static fn (array $old, array $incoming): mixed => $incoming['option_id'],
    'option_value' => static fn (array $old, array $incoming): mixed => $incoming['option_value'],
    'autoload' => static fn (array $old, array $incoming): mixed => $incoming['autoload'],
];

$foreignKey = ['parent_key' => 'option_id', 'child_key' => 'option_id', 'deferred' => true];
$returning = [
    ['expr' => 'new.option_name', 'as' => 'name'],
    ['expr' => 'new.option_id', 'as' => 'id'],
    ['expr' => 'old.option_id', 'as' => 'old_id'],
    ['expr' => 'excluded.option_id', 'as' => 'excluded_id'],
    static fn (array $new, ?array $old, array $incoming, string $event): string => $event . ':' . ($old === null ? 'insert' : 'update') . ':' . $incoming['option_name'],
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
        'name' => 'wp_options_au_meta',
        'timing' => 'after',
        'event' => 'update',
        'action' => 'insert-child',
        'row' => ['meta_id' => 'new.option_id', 'option_id' => 'new.parent_key', 'meta_key' => 'changed', 'meta_value' => 'new.option_value'],
        'values' => ['name' => 'new.option_name', 'key' => 'new.option_id'],
    ],
];

$run = static function (array $incoming, ?callable $where = null, ?array $fk = null, ?array $triggerSet = null, ?array $projection = null, string $savepoint = 'wp_import') use ($parents, $children, $assignments, $foreignKey, $triggers, $returning): array {
    return SQLiteUpsertReturningSavepointPlan::execute(
        $parents,
        $children,
        $incoming,
        ['option_name'],
        $assignments,
        $fk ?? $foreignKey,
        $triggerSet ?? $triggers,
        $where,
        $projection ?? $returning,
        $savepoint,
    );
};

$mixed = static fn (): array => $run([
    ['option_id' => 101, 'option_name' => 'siteurl', 'option_value' => 'https://new.test', 'autoload' => 'yes'],
    ['option_id' => 102, 'option_name' => 'home', 'option_value' => 'https://skip.test', 'autoload' => 'skip'],
    ['option_id' => 103, 'option_name' => 'blogname', 'option_value' => 'New Blog', 'autoload' => 'no'],
], static fn (array $old, array $incoming): bool => $incoming['autoload'] !== 'skip');

$insertProjection = [
    ['expr' => 'new.option_name', 'as' => 'name'],
    ['expr' => 'new.option_id', 'as' => 'id'],
    ['expr' => 'excluded.option_value', 'as' => 'incoming_value'],
    static fn (array $new, ?array $old, array $incoming, string $event): string => $event . ':' . ($old === null ? 'insert' : 'update') . ':' . $new['option_name'],
];

$insertMixed = static fn (): array => $run([
    ['option_id' => 201, 'option_name' => 'fresh_one', 'option_value' => 'one', 'autoload' => 'no'],
    ['option_id' => 202, 'option_name' => 'fresh_two', 'option_value' => 'two', 'autoload' => 'yes'],
], null, null, [], $insertProjection, 'fresh_import');

$rollback = static fn (): array => $run(
    [
        ['option_id' => 301, 'option_name' => 'siteurl', 'option_value' => 'https://first.test', 'autoload' => 'yes'],
        ['option_id' => 302, 'option_name' => 'home', 'option_value' => 'https://bad.test', 'autoload' => 'yes'],
        ['option_id' => 303, 'option_name' => 'blogname', 'option_value' => 'never committed', 'autoload' => 'no'],
    ],
    null,
    ['parent_key' => 'option_id', 'child_key' => 'option_id', 'deferred' => false],
    [
        [
            'name' => 'wp_options_bu_bad_home_meta',
            'timing' => 'before',
            'event' => 'update',
            'action' => 'update-child',
            'when' => ['new.option_name', '=', 'home'],
            'match' => 'old.option_id',
            'set_child_key' => 999,
            'values' => ['old_key' => 'old.option_id', 'new_key' => 'new.option_id'],
        ],
        $triggers[0],
        $triggers[1],
    ],
);

$deferred = static fn (): array => $run(
    [
        ['option_id' => 401, 'option_name' => 'home', 'option_value' => 'https://deferred.test', 'autoload' => 'yes'],
    ],
    null,
    ['parent_key' => 'option_id', 'child_key' => 'option_id', 'deferred' => true],
    [[
        'name' => 'wp_options_bu_orphan_then_commit',
        'timing' => 'before',
        'event' => 'update',
        'action' => 'update-child',
        'match' => 'old.option_id',
        'set_child_key' => 999,
        'values' => ['old_key' => 'old.option_id', 'new_key' => 'new.option_id'],
    ]],
);

$cases = [
    'mixed savepoint name is retained' => [static fn (): mixed => $mixed()['savepoint'], 'wp_import'],
    'mixed statement row count includes skipped row' => [static fn (): mixed => $mixed()['statement_rows'], 3],
    'mixed not rolled back' => [static fn (): mixed => $mixed()['rolled_back'], false],
    'mixed rollback reason is null' => [static fn (): mixed => $mixed()['rollback_reason'], null],
    'mixed rollback ordinal is null' => [static fn (): mixed => $mixed()['rolled_back_at_ordinal'], null],
    'mixed changes exclude skipped row' => [static fn (): mixed => $mixed()['changes'], 2],
    'mixed returning row count excludes skipped row' => [static fn (): mixed => count($mixed()['returning_rows']), 2],
    'mixed returning names follow changed rows' => [static fn (): mixed => array_column($mixed()['returning_rows'], 'name'), ['siteurl', 'blogname']],
    'mixed returning ids follow new row image' => [static fn (): mixed => array_column($mixed()['returning_rows'], 'id'), [101, 103]],
    'mixed returning old ids expose conflict rows' => [static fn (): mixed => array_column($mixed()['returning_rows'], 'old_id'), [1, 3]],
    'mixed returning excluded ids expose incoming rows' => [static fn (): mixed => array_column($mixed()['returning_rows'], 'excluded_id'), [101, 103]],
    'mixed callable returning labels update events' => [static fn (): mixed => array_column($mixed()['returning_rows'], 'expr4'), ['update:update:siteurl', 'update:update:blogname']],
    'mixed attempted yield statuses include skipped' => [static fn (): mixed => array_column($mixed()['attempted_yields'], 'status'), ['changed', 'skipped', 'changed']],
    'mixed attempted yield ordinals are statement ordinals' => [static fn (): mixed => array_column($mixed()['attempted_yields'], 'ordinal'), [0, 1, 2]],
    'mixed attempted yield statement ordinals mirror ordinals' => [static fn (): mixed => array_column($mixed()['attempted_yields'], 'statement_ordinal'), [0, 1, 2]],
    'mixed skipped yield has null returning' => [static fn (): mixed => $mixed()['attempted_yields'][1]['returning'], null],
    'mixed changed yield includes returning name' => [static fn (): mixed => $mixed()['attempted_yields'][0]['returning']['name'], 'siteurl'],
    'mixed parent keys updated around skipped row' => [static fn (): mixed => array_column($mixed()['parent'], 'option_id'), [101, 2, 103]],
    'mixed skipped home value is original' => [static fn (): mixed => $mixed()['parent'][1]['option_value'], 'https://home.test'],
    'mixed child keys follow trigger rekeys' => [static fn (): mixed => array_column($mixed()['child'], 'option_id'), [101, 2, 103, 101, 103]],
    'mixed child meta keys append changed rows' => [static fn (): mixed => array_column($mixed()['child'], 'meta_key'), ['source', 'source', 'source', 'changed', 'changed']],
    'mixed trigger statement ordinals skip skipped row' => [static fn (): mixed => array_column($mixed()['trigger_effects'], 'statement_ordinal'), [0, 0, 2, 2]],
    'mixed trigger names repeat update pair' => [static fn (): mixed => array_column($mixed()['trigger_effects'], 'trigger'), ['wp_options_bu_rekey_meta', 'wp_options_au_meta', 'wp_options_bu_rekey_meta', 'wp_options_au_meta']],
    'mixed trigger old key first update' => [static fn (): mixed => $mixed()['trigger_effects'][0]['row']['old_key'], 1],
    'mixed trigger new key second update' => [static fn (): mixed => $mixed()['trigger_effects'][2]['row']['new_key'], 103],
    'mixed foreign key violations empty' => [static fn (): mixed => $mixed()['foreign_key_violations'], []],

    'insert projection savepoint is retained' => [static fn (): mixed => $insertMixed()['savepoint'], 'fresh_import'],
    'insert projection changes both rows' => [static fn (): mixed => $insertMixed()['changes'], 2],
    'insert projection parent appends names' => [static fn (): mixed => array_column($insertMixed()['parent'], 'option_name'), ['siteurl', 'home', 'blogname', 'fresh_one', 'fresh_two']],
    'insert projection returning names' => [static fn (): mixed => array_column($insertMixed()['returning_rows'], 'name'), ['fresh_one', 'fresh_two']],
    'insert projection returning ids' => [static fn (): mixed => array_column($insertMixed()['returning_rows'], 'id'), [201, 202]],
    'insert projection incoming values' => [static fn (): mixed => array_column($insertMixed()['returning_rows'], 'incoming_value'), ['one', 'two']],
    'insert projection callable insert labels' => [static fn (): mixed => array_column($insertMixed()['returning_rows'], 'expr3'), ['insert:insert:fresh_one', 'insert:insert:fresh_two']],
    'insert projection attempted events are insert' => [static fn (): mixed => array_column($insertMixed()['attempted_yields'], 'event'), ['insert', 'insert']],
    'insert projection no trigger effects' => [static fn (): mixed => $insertMixed()['trigger_effects'], []],
    'insert projection no rollback' => [static fn (): mixed => $insertMixed()['rolled_back'], false],

    'rollback rolls back statement' => [static fn (): mixed => $rollback()['rolled_back'], true],
    'rollback reason names immediate constraint' => [static fn (): mixed => str_contains((string) $rollback()['rollback_reason'], 'immediate constraint failed'), true],
    'rollback ordinal is failing row' => [static fn (): mixed => $rollback()['rolled_back_at_ordinal'], 1],
    'rollback statement row count preserved' => [static fn (): mixed => $rollback()['statement_rows'], 3],
    'rollback changes reset to zero' => [static fn (): mixed => $rollback()['changes'], 0],
    'rollback returning rows suppressed' => [static fn (): mixed => $rollback()['returning_rows'], []],
    'rollback parent keys restored' => [static fn (): mixed => array_column($rollback()['parent'], 'option_id'), [1, 2, 3]],
    'rollback parent values restored' => [static fn (): mixed => array_column($rollback()['parent'], 'option_value'), ['https://old.test', 'https://home.test', 'Old Blog']],
    'rollback child keys restored' => [static fn (): mixed => array_column($rollback()['child'], 'option_id'), [1, 2, 3]],
    'rollback attempted yields keep prior changed row' => [static fn (): mixed => array_column($rollback()['attempted_yields'], 'status'), ['changed']],
    'rollback attempted yield ordinal is prior row' => [static fn (): mixed => $rollback()['attempted_yields'][0]['ordinal'], 0],
    'rollback attempted yield returning is diagnostic only' => [static fn (): mixed => $rollback()['attempted_yields'][0]['returning']['name'], 'siteurl'],
    'rollback trigger effects include prior successful row only' => [static fn (): mixed => array_column($rollback()['trigger_effects'], 'statement_ordinal'), [0, 0]],
    'rollback trigger effect names prior row only' => [static fn (): mixed => array_column($rollback()['trigger_effects'], 'trigger'), ['wp_options_bu_rekey_meta', 'wp_options_au_meta']],
    'rollback violations empty because immediate failure aborted row' => [static fn (): mixed => $rollback()['foreign_key_violations'], []],

    'deferred violation does not roll back current savepoint' => [static fn (): mixed => $deferred()['rolled_back'], false],
    'deferred violation changes row' => [static fn (): mixed => $deferred()['changes'], 1],
    'deferred violation returning row emitted' => [static fn (): mixed => $deferred()['returning_rows'][0]['name'], 'home'],
    'deferred violation returning old id' => [static fn (): mixed => $deferred()['returning_rows'][0]['old_id'], 2],
    'deferred violation parent key committed pending outer check' => [static fn (): mixed => $deferred()['parent'][1]['option_id'], 401],
    'deferred violation child key remains orphaned' => [static fn (): mixed => $deferred()['child'][1]['option_id'], 999],
    'deferred violation recorded statement phase' => [static fn (): mixed => $deferred()['foreign_key_violations'][0]['phase'], 'statement'],
    'deferred violation tagged statement ordinal' => [static fn (): mixed => $deferred()['foreign_key_violations'][0]['statement_ordinal'], 0],
    'deferred violation yielded before-trigger count' => [static fn (): mixed => $deferred()['attempted_yields'][0]['violations_before_after_triggers'], 1],
    'deferred violation yielded after-trigger count' => [static fn (): mixed => $deferred()['attempted_yields'][0]['violations_after_triggers'], 1],

    'empty savepoint name throws' => [static fn (): mixed => $run([], null, null, null, null, ''), InvalidArgumentException::class],
    'insert projection old reference throws and rolls back' => [static fn (): mixed => $run([['option_id' => 501, 'option_name' => 'fresh_old', 'option_value' => 'x', 'autoload' => 'no']], null, null, [], [['expr' => 'old.option_id', 'as' => 'old_id']])['rolled_back'], true],
    'insert projection old reference suppresses returning' => [static fn (): mixed => $run([['option_id' => 501, 'option_name' => 'fresh_old', 'option_value' => 'x', 'autoload' => 'no']], null, null, [], [['expr' => 'old.option_id', 'as' => 'old_id']])['returning_rows'], []],
    'missing unique column rolls back' => [static fn (): mixed => $run([['option_id' => 502, 'option_value' => 'x', 'autoload' => 'no']])['rolled_back'], true],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['upsert returning savepoint current next38 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
