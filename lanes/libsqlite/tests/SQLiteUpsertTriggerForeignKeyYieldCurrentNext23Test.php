<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertTriggerForeignKeyYieldPlan;

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
    [
        'name' => 'wp_options_au_meta',
        'timing' => 'after',
        'event' => 'update',
        'action' => 'insert-child',
        'row' => ['meta_id' => 'new.option_id', 'option_id' => 'new.parent_key', 'meta_key' => 'changed', 'meta_value' => 'new.option_value'],
        'values' => ['name' => 'new.option_name', 'key' => 'new.option_id'],
    ],
];

$run = static function (array $incoming, ?callable $where = null, ?array $fk = null, ?array $triggerSet = null) use ($parents, $children, $assignments, $foreignKey, $triggers): array {
    return SQLiteUpsertTriggerForeignKeyYieldPlan::execute(
        $parents,
        $children,
        $incoming,
        ['option_name'],
        $assignments,
        $fk ?? $foreignKey,
        $triggerSet ?? $triggers,
        $where,
    );
};

$mixedPlan = static fn (): array => $run([
    ['option_id' => 101, 'option_name' => 'siteurl', 'option_value' => 'https://new.test', 'autoload' => 'yes'],
    ['option_id' => 102, 'option_name' => 'fresh_plugin', 'option_value' => 'enabled', 'autoload' => 'no'],
    ['option_id' => 103, 'option_name' => 'home', 'option_value' => 'https://skip.test', 'autoload' => 'skip'],
], static fn (array $old, array $incoming): bool => $incoming['autoload'] !== 'skip');

$deferredViolationPlan = static fn (): array => $run(
    [['option_id' => 404, 'option_name' => 'blogname', 'option_value' => 'Broken Blog', 'autoload' => 'no']],
    null,
    $foreignKey,
    [[
        'name' => 'wp_options_bu_bad_meta',
        'timing' => 'before',
        'event' => 'update',
        'action' => 'update-child',
        'match' => 'old.option_id',
        'set_child_key' => 999,
        'values' => ['old_key' => 'old.option_id', 'new_key' => 'new.option_id'],
    ]]
);

$repairPlan = static fn (): array => $run(
    [['option_id' => 30, 'option_name' => 'blogname', 'option_value' => 'Fixed Blog', 'autoload' => 'yes']],
    null,
    $foreignKey,
    [
        [
            'name' => 'wp_options_bu_break_meta',
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
);

$cases = [
    'mixed changes excludes skipped row' => [static fn (): mixed => $mixedPlan()['changes'], 2],
    'mixed inserted row uses before trigger key' => [static fn (): mixed => $mixedPlan()['inserted'][0]['option_id'], 20],
    'mixed inserted row name preserved' => [static fn (): mixed => $mixedPlan()['inserted'][0]['option_name'], 'fresh_plugin'],
    'mixed updated row name is siteurl' => [static fn (): mixed => $mixedPlan()['updated'][0]['option_name'], 'siteurl'],
    'mixed updated row key is changed by excluded row' => [static fn (): mixed => $mixedPlan()['updated'][0]['option_id'], 101],
    'mixed skipped row is home' => [static fn (): mixed => $mixedPlan()['skipped'][0]['option_name'], 'home'],
    'mixed parent order preserves current rows and append' => [static fn (): mixed => array_column($mixedPlan()['parent'], 'option_name'), ['siteurl', 'home', 'blogname', 'fresh_plugin']],
    'mixed parent keys include update and trigger insert key' => [static fn (): mixed => array_column($mixedPlan()['parent'], 'option_id'), [101, 2, 3, 20]],
    'mixed child keys rekey old siteurl child before update' => [static fn (): mixed => array_column($mixedPlan()['child'], 'option_id'), [101, 2, 3, 101, 20]],
    'mixed child meta rows include changed and autoload inserts' => [static fn (): mixed => array_column($mixedPlan()['child'], 'meta_key'), ['source', 'source', 'source', 'changed', 'autoload']],
    'mixed child changed meta value is new siteurl' => [static fn (): mixed => $mixedPlan()['child'][3]['meta_value'], 'https://new.test'],
    'mixed child inserted meta value is autoload' => [static fn (): mixed => $mixedPlan()['child'][4]['meta_value'], 'no'],
    'mixed trigger order follows row sequence' => [static fn (): mixed => array_column($mixedPlan()['trigger_effects'], 'trigger'), ['wp_options_bu_rekey_meta', 'wp_options_au_meta', 'wp_options_bi_alias', 'wp_options_ai_meta']],
    'mixed trigger actions show update then insert' => [static fn (): mixed => array_column($mixedPlan()['trigger_effects'], 'action'), ['update-child', 'insert-child', 'set-new', 'insert-child']],
    'mixed before update effect sees old key' => [static fn (): mixed => $mixedPlan()['trigger_effects'][0]['row']['old_key'], 1],
    'mixed before update effect sees new key' => [static fn (): mixed => $mixedPlan()['trigger_effects'][0]['row']['new_key'], 101],
    'mixed after update effect sees changed key' => [static fn (): mixed => $mixedPlan()['trigger_effects'][1]['row']['key'], 101],
    'mixed before insert effect sees trigger assigned key' => [static fn (): mixed => $mixedPlan()['trigger_effects'][2]['row']['key'], 20],
    'mixed after insert effect sees trigger assigned key' => [static fn (): mixed => $mixedPlan()['trigger_effects'][3]['row']['key'], 20],
    'mixed yielded statuses include skipped' => [static fn (): mixed => array_column($mixedPlan()['yielded'], 'status'), ['changed', 'changed', 'skipped']],
    'mixed yielded events include update insert update skip' => [static fn (): mixed => array_column($mixedPlan()['yielded'], 'event'), ['update', 'insert', 'update']],
    'mixed yielded old keys expose update and skip rows' => [static fn (): mixed => array_column($mixedPlan()['yielded'], 'old_key'), [1, null, 2]],
    'mixed yielded new keys expose final keys' => [static fn (): mixed => array_column($mixedPlan()['yielded'], 'new_key'), [101, 20, 103]],
    'mixed yielded has no violations before after triggers' => [static fn (): mixed => array_column($mixedPlan()['yielded'], 'violations_before_after_triggers'), [0, 0, 0]],
    'mixed yielded has no violations after triggers' => [static fn (): mixed => array_column($mixedPlan()['yielded'], 'violations_after_triggers'), [0, 0, 0]],
    'mixed foreign key violations empty' => [static fn (): mixed => $mixedPlan()['foreign_key_violations'], []],
    'mixed first parent value updated' => [static fn (): mixed => $mixedPlan()['parent'][0]['option_value'], 'https://new.test'],
    'mixed skipped parent value unchanged' => [static fn (): mixed => $mixedPlan()['parent'][1]['option_value'], 'https://home.test'],
    'mixed inserted parent autoload preserved' => [static fn (): mixed => $mixedPlan()['parent'][3]['autoload'], 'no'],
    'mixed child count is five' => [static fn (): mixed => count($mixedPlan()['child']), 5],
    'mixed unchanged blogname child remains valid' => [static fn (): mixed => $mixedPlan()['child'][2]['option_id'], 3],

    'deferred violation changes row' => [static fn (): mixed => $deferredViolationPlan()['changes'], 1],
    'deferred violation records before-trigger violation' => [static fn (): mixed => $deferredViolationPlan()['yielded'][0]['violations_before_after_triggers'], 1],
    'deferred violation remains after trigger' => [static fn (): mixed => $deferredViolationPlan()['yielded'][0]['violations_after_triggers'], 1],
    'deferred violation child key is orphaned' => [static fn (): mixed => $deferredViolationPlan()['foreign_key_violations'][0]['child_key'], 999],
    'deferred violation phase is statement' => [static fn (): mixed => $deferredViolationPlan()['foreign_key_violations'][0]['phase'], 'statement'],
    'deferred violation after trigger phase also recorded' => [static fn (): mixed => $deferredViolationPlan()['foreign_key_violations'][1]['phase'], 'after-trigger'],
    'deferred violation updates parent key' => [static fn (): mixed => $deferredViolationPlan()['parent'][2]['option_id'], 404],
    'deferred violation child key remains orphaned' => [static fn (): mixed => $deferredViolationPlan()['child'][0]['option_id'], 1],
    'deferred violation trigger effect old key' => [static fn (): mixed => $deferredViolationPlan()['trigger_effects'][0]['row']['old_key'], 3],
    'deferred violation trigger effect new key' => [static fn (): mixed => $deferredViolationPlan()['trigger_effects'][0]['row']['new_key'], 404],

    'repair plan changes row' => [static fn (): mixed => $repairPlan()['changes'], 1],
    'repair plan sees transient before-trigger violation' => [static fn (): mixed => $repairPlan()['yielded'][0]['violations_before_after_triggers'], 1],
    'repair plan clears after-trigger violation' => [static fn (): mixed => $repairPlan()['yielded'][0]['violations_after_triggers'], 0],
    'repair plan keeps deferred statement violation evidence' => [static fn (): mixed => $repairPlan()['foreign_key_violations'][0]['child_key'], 999],
    'repair plan final child keys all valid' => [static fn (): mixed => array_column($repairPlan()['child'], 'option_id'), [1, 2, 30]],
    'repair plan parent key updated' => [static fn (): mixed => $repairPlan()['parent'][2]['option_id'], 30],
    'repair plan fires break then repair triggers' => [static fn (): mixed => array_column($repairPlan()['trigger_effects'], 'trigger'), ['wp_options_bu_break_meta', 'wp_options_au_repair_meta']],
    'repair plan after trigger key uses repaired parent' => [static fn (): mixed => $repairPlan()['trigger_effects'][1]['row']['key'], 30],

    'immediate foreign key rejects before-trigger orphan' => [static fn (): mixed => $run([['option_id' => 404, 'option_name' => 'blogname', 'option_value' => 'Broken Blog', 'autoload' => 'no']], null, ['parent_key' => 'option_id', 'child_key' => 'option_id', 'deferred' => false], [[
        'name' => 'bad',
        'timing' => 'before',
        'event' => 'update',
        'action' => 'update-child',
        'match' => 'old.option_id',
        'set_child_key' => 999,
    ]]), InvalidArgumentException::class],
    'insert old reference throws' => [static fn (): mixed => $run([['option_id' => 501, 'option_name' => 'bad_old', 'option_value' => 'x', 'autoload' => 'no']], null, $foreignKey, [[
        'name' => 'bad_old',
        'timing' => 'before',
        'event' => 'insert',
        'action' => 'audit',
        'values' => ['old_key' => 'old.option_id'],
    ]]), InvalidArgumentException::class],
    'missing unique column throws' => [static fn (): mixed => $run([['option_id' => 502, 'option_value' => 'x', 'autoload' => 'no']]), InvalidArgumentException::class],
    'bad foreign key parent column throws' => [static fn (): mixed => $run([], null, ['parent_key' => 'bad-column', 'child_key' => 'option_id']), InvalidArgumentException::class],
    'bad trigger action throws' => [static fn (): mixed => $run([['option_id' => 503, 'option_name' => 'bad_action', 'option_value' => 'x', 'autoload' => 'no']], null, $foreignKey, [[
        'name' => 'bad_action',
        'timing' => 'before',
        'event' => 'insert',
        'action' => 'delete-parent',
    ]]), InvalidArgumentException::class],
    'bad when operator throws' => [static fn (): mixed => $run([['option_id' => 504, 'option_name' => 'bad_when', 'option_value' => 'x', 'autoload' => 'no']], null, $foreignKey, [[
        'name' => 'bad_when',
        'timing' => 'before',
        'event' => 'insert',
        'when' => ['new.option_name', 'LIKE', 'bad%'],
    ]]), InvalidArgumentException::class],
    'missing child column throws during fk scan' => [static fn (): mixed => SQLiteUpsertTriggerForeignKeyYieldPlan::execute($parents, [['meta_id' => 99]], [['option_id' => 505, 'option_name' => 'missing_child', 'option_value' => 'x', 'autoload' => 'no']], ['option_name'], $assignments, $foreignKey, []), InvalidArgumentException::class],
    'missing trigger new column throws' => [static fn (): mixed => $run([['option_id' => 506, 'option_name' => 'missing_trigger', 'option_value' => 'x', 'autoload' => 'no']], null, $foreignKey, [[
        'name' => 'missing_new',
        'timing' => 'before',
        'event' => 'insert',
        'values' => ['missing' => 'new.missing'],
    ]]), InvalidArgumentException::class],
    'where false records skipped old key' => [static fn (): mixed => $run([['option_id' => 507, 'option_name' => 'siteurl', 'option_value' => 'skip', 'autoload' => 'yes']], static fn (): bool => false)['yielded'][0]['old_key'], 1],
    'where false does not fire triggers' => [static fn (): mixed => $run([['option_id' => 508, 'option_name' => 'siteurl', 'option_value' => 'skip', 'autoload' => 'yes']], static fn (): bool => false)['trigger_effects'], []],
    'where false leaves children untouched' => [static fn (): mixed => array_column($run([['option_id' => 509, 'option_name' => 'siteurl', 'option_value' => 'skip', 'autoload' => 'yes']], static fn (): bool => false)['child'], 'option_id'), [1, 2, 3]],
    'null unique incoming inserts instead of conflicting' => [static fn (): mixed => $run([['option_id' => 510, 'option_name' => null, 'option_value' => 'null-name', 'autoload' => 'no']])['inserted'][0]['option_id'], 510],
    'null current unique row does not conflict' => [static fn (): mixed => SQLiteUpsertTriggerForeignKeyYieldPlan::execute([['option_id' => 7, 'option_name' => null, 'option_value' => 'old', 'autoload' => 'no']], [], [['option_id' => 8, 'option_name' => null, 'option_value' => 'new', 'autoload' => 'no']], ['option_name'], $assignments, $foreignKey, [])['changes'], 1],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['upsert trigger fk yield current next23 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
