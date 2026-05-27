<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRecursiveUpsertConflictYieldPlan;

$rows = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'old-site', 'autoload' => 'yes', 'level' => 0],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'old-home', 'autoload' => 'yes', 'level' => 0],
    ['option_id' => 3, 'option_name' => 'theme_mods_parent', 'option_value' => 'parent-old', 'autoload' => 'no', 'level' => 0],
    ['option_id' => 4, 'option_name' => 'theme_mods_child', 'option_value' => 'child-old', 'autoload' => 'no', 'level' => 0],
];

$assignments = [
    'option_value' => static fn (array $old, array $incoming): mixed => $incoming['option_value'],
    'autoload' => static fn (array $old, array $incoming): mixed => $incoming['autoload'] ?? $old['autoload'],
    'level' => static fn (array $old, array $incoming): mixed => $incoming['level'] ?? $old['level'],
];

$triggers = [
    [
        'name' => 'wp_options_au_touch_home',
        'timing' => 'after',
        'event' => 'update',
        'action' => 'upsert-parent',
        'when' => ['new.option_name', '=', 'siteurl'],
        'row' => [
            'option_id' => 2,
            'option_name' => 'home',
            'option_value' => ['concat' => ['new.option_value', '/home']],
            'autoload' => 'yes',
            'level' => ['add' => ['new.level', 1]],
        ],
        'values' => ['seen' => 'new.option_name', 'next' => 'home'],
    ],
    [
        'name' => 'wp_options_au_touch_child',
        'timing' => 'after',
        'event' => 'update',
        'action' => 'upsert-parent',
        'when' => ['new.option_name', '=', 'theme_mods_parent'],
        'row' => [
            'option_id' => 4,
            'option_name' => 'theme_mods_child',
            'option_value' => ['concat' => ['new.option_value', ':child']],
            'autoload' => 'no',
            'level' => ['add' => ['new.level', 1]],
        ],
        'values' => ['seen' => 'new.option_name', 'next' => 'theme_mods_child'],
    ],
    [
        'name' => 'wp_options_au_child_audit',
        'timing' => 'after',
        'event' => 'update',
        'action' => 'audit',
        'when' => ['new.option_name', '=', 'theme_mods_child'],
        'values' => ['seen' => 'new.option_name', 'value' => 'new.option_value'],
    ],
];

$run = static fn (array $incoming, array $options = [], ?array $triggerSet = null): array => SQLiteRecursiveUpsertConflictYieldPlan::execute(
    $rows,
    $incoming,
    ['option_name'],
    $assignments,
    $triggerSet ?? $triggers,
    $options,
);

$recursivePlan = static fn (): array => $run([
    ['option_id' => 101, 'option_name' => 'siteurl', 'option_value' => 'https://new.test', 'autoload' => 'yes', 'level' => 0],
    ['option_id' => 103, 'option_name' => 'theme_mods_parent', 'option_value' => 'parent-new', 'autoload' => 'no', 'level' => 0],
    ['option_id' => 104, 'option_name' => 'fresh_plugin_flag', 'option_value' => 'enabled', 'autoload' => 'no', 'level' => 0],
]);

$suppressedPlan = static fn (): array => $run([
    ['option_id' => 201, 'option_name' => 'siteurl', 'option_value' => 'https://suppressed.test', 'autoload' => 'yes', 'level' => 0],
], ['recursive_triggers' => false]);

$ignorePlan = static fn (): array => $run([
    ['option_id' => 301, 'option_name' => 'siteurl', 'option_value' => 'ignored-site', 'autoload' => 'yes', 'level' => 0],
], ['conflict_action' => 'ignore']);

$nullUniquePlan = static fn (): array => $run([
    ['option_id' => 401, 'option_name' => null, 'option_value' => 'null-a', 'autoload' => 'no', 'level' => 0],
    ['option_id' => 402, 'option_name' => null, 'option_value' => 'null-b', 'autoload' => 'no', 'level' => 0],
]);

$cases = [
    'recursive plan changes statement plus recursive rows' => [static fn (): mixed => $recursivePlan()['changes'], 5],
    'recursive plan max depth includes trigger upserts' => [static fn (): mixed => $recursivePlan()['max_depth_seen'], 1],
    'recursive plan preserves row count with one insert' => [static fn (): mixed => count($recursivePlan()['rows']), 5],
    'recursive plan row order keeps current rows then insert' => [static fn (): mixed => array_column($recursivePlan()['rows'], 'option_name'), ['siteurl', 'home', 'theme_mods_parent', 'theme_mods_child', 'fresh_plugin_flag']],
    'recursive plan updates siteurl value' => [static fn (): mixed => $recursivePlan()['rows'][0]['option_value'], 'https://new.test'],
    'recursive plan recursive home conflict sees current changed siteurl' => [static fn (): mixed => $recursivePlan()['rows'][1]['option_value'], 'https://new.test/home'],
    'recursive plan updates theme parent' => [static fn (): mixed => $recursivePlan()['rows'][2]['option_value'], 'parent-new'],
    'recursive plan recursive child conflict sees current parent' => [static fn (): mixed => $recursivePlan()['rows'][3]['option_value'], 'parent-new:child'],
    'recursive plan inserts fresh row' => [static fn (): mixed => $recursivePlan()['rows'][4]['option_name'], 'fresh_plugin_flag'],
    'recursive plan inserted list contains only fresh row' => [static fn (): mixed => array_column($recursivePlan()['inserted'], 'option_name'), ['fresh_plugin_flag']],
    'recursive plan updated list includes recursive conflicts' => [static fn (): mixed => array_column($recursivePlan()['updated'], 'option_name'), ['siteurl', 'home', 'theme_mods_parent', 'theme_mods_child']],
    'recursive plan skipped list is empty' => [static fn (): mixed => $recursivePlan()['skipped'], []],
    'recursive plan yielded statuses all changed' => [static fn (): mixed => array_column($recursivePlan()['yielded'], 'status'), ['changed', 'changed', 'changed', 'changed', 'changed']],
    'recursive plan yielded source sequence marks trigger rows' => [static fn (): mixed => array_column($recursivePlan()['yielded'], 'source'), ['statement', 'trigger', 'statement', 'trigger', 'statement']],
    'recursive plan yielded trigger names for recursive rows' => [static fn (): mixed => array_column($recursivePlan()['yielded'], 'trigger'), [null, 'wp_options_au_touch_home', null, 'wp_options_au_touch_child', null]],
    'recursive plan yielded events include update and insert' => [static fn (): mixed => array_column($recursivePlan()['yielded'], 'event'), ['update', 'update', 'update', 'update', 'insert']],
    'recursive plan yielded depths include recursive children' => [static fn (): mixed => array_column($recursivePlan()['yielded'], 'depth'), [0, 1, 0, 1, 0]],
    'recursive plan yielded old keys expose current conflicts' => [static fn (): mixed => array_column($recursivePlan()['yielded'], 'old_key'), ['siteurl', 'home', 'theme_mods_parent', 'theme_mods_child', null]],
    'recursive plan yielded new values are final statement order' => [static fn (): mixed => array_column($recursivePlan()['yielded'], 'new_value'), ['https://new.test', 'https://new.test/home', 'parent-new', 'parent-new:child', 'enabled']],
    'recursive plan trigger effects include two recursive upserts and audit' => [static fn (): mixed => array_column($recursivePlan()['trigger_effects'], 'result'), ['recursive-upsert', 'recursive-upsert', 'audit']],
    'recursive plan trigger effect names are stable' => [static fn (): mixed => array_column($recursivePlan()['trigger_effects'], 'trigger'), ['wp_options_au_touch_home', 'wp_options_au_touch_child', 'wp_options_au_child_audit']],
    'recursive plan first effect row records source key' => [static fn (): mixed => $recursivePlan()['trigger_effects'][0]['row']['seen'], 'siteurl'],
    'recursive plan first effect row records target key' => [static fn (): mixed => $recursivePlan()['trigger_effects'][0]['row']['next'], 'home'],
    'recursive plan audit sees recursive child row' => [static fn (): mixed => $recursivePlan()['trigger_effects'][2]['row']['seen'], 'theme_mods_child'],
    'recursive plan audit sees recursive child value' => [static fn (): mixed => $recursivePlan()['trigger_effects'][2]['row']['value'], 'parent-new:child'],
    'recursive plan recursive row level is incremented' => [static fn (): mixed => $recursivePlan()['rows'][1]['level'], 1],
    'recursive plan second recursive row level is incremented' => [static fn (): mixed => $recursivePlan()['rows'][3]['level'], 1],
    'recursive plan unchanged autoload for siteurl is yes' => [static fn (): mixed => $recursivePlan()['rows'][0]['autoload'], 'yes'],
    'recursive plan child autoload remains no' => [static fn (): mixed => $recursivePlan()['rows'][3]['autoload'], 'no'],
    'recursive plan fresh autoload remains no' => [static fn (): mixed => $recursivePlan()['rows'][4]['autoload'], 'no'],

    'suppressed plan changes only statement row' => [static fn (): mixed => $suppressedPlan()['changes'], 1],
    'suppressed plan leaves home unchanged' => [static fn (): mixed => $suppressedPlan()['rows'][1]['option_value'], 'old-home'],
    'suppressed plan records suppressed trigger effect' => [static fn (): mixed => $suppressedPlan()['trigger_effects'][0]['result'], 'recursive-suppressed'],
    'suppressed plan yields only statement row' => [static fn (): mixed => count($suppressedPlan()['yielded']), 1],
    'suppressed plan max depth remains zero' => [static fn (): mixed => $suppressedPlan()['max_depth_seen'], 0],
    'suppressed plan no skipped rows' => [static fn (): mixed => $suppressedPlan()['skipped'], []],

    'ignore plan changes zero' => [static fn (): mixed => $ignorePlan()['changes'], 0],
    'ignore plan records skipped conflict' => [static fn (): mixed => array_column($ignorePlan()['yielded'], 'status'), ['skipped']],
    'ignore plan skipped row is incoming conflict' => [static fn (): mixed => $ignorePlan()['skipped'][0]['option_value'], 'ignored-site'],
    'ignore plan does not fire triggers' => [static fn (): mixed => $ignorePlan()['trigger_effects'], []],
    'ignore plan leaves siteurl unchanged' => [static fn (): mixed => $ignorePlan()['rows'][0]['option_value'], 'old-site'],

    'null unique plan inserts both null names' => [static fn (): mixed => array_column($nullUniquePlan()['inserted'], 'option_id'), [401, 402]],
    'null unique plan changes both rows' => [static fn (): mixed => $nullUniquePlan()['changes'], 2],
    'null unique plan appends null rows' => [static fn (): mixed => array_slice(array_column($nullUniquePlan()['rows'], 'option_value'), -2), ['null-a', 'null-b']],
    'null unique plan yields insert events' => [static fn (): mixed => array_column($nullUniquePlan()['yielded'], 'event'), ['insert', 'insert']],

    'depth limit rejects recursive trigger conflict' => [static fn (): mixed => $run([['option_id' => 501, 'option_name' => 'siteurl', 'option_value' => 'too-deep', 'autoload' => 'yes', 'level' => 0]], ['max_depth' => 0]), RuntimeException::class],
    'missing unique column throws' => [static fn (): mixed => $run([['option_id' => 502, 'option_value' => 'missing', 'autoload' => 'no', 'level' => 0]]), InvalidArgumentException::class],
    'empty unique columns throws' => [static fn (): mixed => SQLiteRecursiveUpsertConflictYieldPlan::execute($rows, [], [], $assignments, []), InvalidArgumentException::class],
    'bad unique column name throws' => [static fn (): mixed => SQLiteRecursiveUpsertConflictYieldPlan::execute($rows, [], ['bad-name'], $assignments, []), InvalidArgumentException::class],
    'bad assignment column throws' => [static fn (): mixed => SQLiteRecursiveUpsertConflictYieldPlan::execute($rows, [['option_name' => 'siteurl', 'option_value' => 'x']], ['option_name'], ['bad-name' => static fn (): string => 'x'], []), InvalidArgumentException::class],
    'bad trigger action throws' => [static fn (): mixed => $run([['option_id' => 503, 'option_name' => 'siteurl', 'option_value' => 'x', 'autoload' => 'yes', 'level' => 0]], [], [[
        'name' => 'bad',
        'timing' => 'after',
        'event' => 'update',
        'action' => 'delete-parent',
    ]]), InvalidArgumentException::class],
    'bad when operator throws' => [static fn (): mixed => $run([['option_id' => 504, 'option_name' => 'siteurl', 'option_value' => 'x', 'autoload' => 'yes', 'level' => 0]], [], [[
        'name' => 'bad_when',
        'timing' => 'after',
        'event' => 'update',
        'action' => 'audit',
        'when' => ['new.option_name', 'LIKE', 'site%'],
    ]]), InvalidArgumentException::class],
    'insert trigger old reference throws' => [static fn (): mixed => $run([['option_id' => 505, 'option_name' => 'new_option', 'option_value' => 'x', 'autoload' => 'no', 'level' => 0]], [], [[
        'name' => 'bad_old',
        'timing' => 'after',
        'event' => 'insert',
        'action' => 'audit',
        'values' => ['old' => 'old.option_name'],
    ]]), InvalidArgumentException::class],
    'bad max depth throws' => [static fn (): mixed => $run([], ['max_depth' => -1]), InvalidArgumentException::class],
    'bad conflict action throws' => [static fn (): mixed => $run([], ['conflict_action' => 'replace']), InvalidArgumentException::class],
    'malformed projection column throws' => [static fn (): mixed => $run([['option_id' => 506, 'option_name' => 'siteurl', 'option_value' => 'x', 'autoload' => 'yes', 'level' => 0]], [], [[
        'name' => 'bad_row',
        'timing' => 'after',
        'event' => 'update',
        'action' => 'upsert-parent',
        'row' => ['bad-name' => 'new.option_name'],
    ]]), InvalidArgumentException::class],
    'missing new column throws' => [static fn (): mixed => $run([['option_id' => 507, 'option_name' => 'siteurl', 'option_value' => 'x', 'autoload' => 'yes', 'level' => 0]], [], [[
        'name' => 'missing',
        'timing' => 'after',
        'event' => 'update',
        'action' => 'upsert-parent',
        'row' => ['option_name' => 'new.missing'],
    ]]), InvalidArgumentException::class],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['recursive upsert conflict yield current next30 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
