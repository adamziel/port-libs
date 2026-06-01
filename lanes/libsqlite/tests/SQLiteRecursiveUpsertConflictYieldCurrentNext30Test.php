<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRecursiveUpsertConflictYieldPlan;

$rows = [
    ['setting_id' => 1, 'key_name' => 'base_url', 'key_value' => 'old-site', 'load_policy' => 'yes', 'level' => 0],
    ['setting_id' => 2, 'key_name' => 'landing_page', 'key_value' => 'old-landing_page', 'load_policy' => 'yes', 'level' => 0],
    ['setting_id' => 3, 'key_name' => 'layout_settings_parent', 'key_value' => 'parent-old', 'load_policy' => 'no', 'level' => 0],
    ['setting_id' => 4, 'key_name' => 'layout_settings_child', 'key_value' => 'child-old', 'load_policy' => 'no', 'level' => 0],
];

$assignments = [
    'key_value' => static fn (array $old, array $incoming): mixed => $incoming['key_value'],
    'load_policy' => static fn (array $old, array $incoming): mixed => $incoming['load_policy'] ?? $old['load_policy'],
    'level' => static fn (array $old, array $incoming): mixed => $incoming['level'] ?? $old['level'],
];

$triggers = [
    [
        'name' => 'app_settings_au_touch_home',
        'timing' => 'after',
        'event' => 'update',
        'action' => 'upsert-parent',
        'when' => ['new.key_name', '=', 'base_url'],
        'row' => [
            'setting_id' => 2,
            'key_name' => 'landing_page',
            'key_value' => ['concat' => ['new.key_value', '/landing_page']],
            'load_policy' => 'yes',
            'level' => ['add' => ['new.level', 1]],
        ],
        'values' => ['seen' => 'new.key_name', 'next' => 'landing_page'],
    ],
    [
        'name' => 'app_settings_au_touch_child',
        'timing' => 'after',
        'event' => 'update',
        'action' => 'upsert-parent',
        'when' => ['new.key_name', '=', 'layout_settings_parent'],
        'row' => [
            'setting_id' => 4,
            'key_name' => 'layout_settings_child',
            'key_value' => ['concat' => ['new.key_value', ':child']],
            'load_policy' => 'no',
            'level' => ['add' => ['new.level', 1]],
        ],
        'values' => ['seen' => 'new.key_name', 'next' => 'layout_settings_child'],
    ],
    [
        'name' => 'app_settings_au_child_audit',
        'timing' => 'after',
        'event' => 'update',
        'action' => 'audit',
        'when' => ['new.key_name', '=', 'layout_settings_child'],
        'values' => ['seen' => 'new.key_name', 'value' => 'new.key_value'],
    ],
];

$run = static fn (array $incoming, array $options = [], ?array $triggerSet = null): array => SQLiteRecursiveUpsertConflictYieldPlan::execute(
    $rows,
    $incoming,
    ['key_name'],
    $assignments,
    $triggerSet ?? $triggers,
    $options,
);

$recursivePlan = static fn (): array => $run([
    ['setting_id' => 101, 'key_name' => 'base_url', 'key_value' => 'https://new.test', 'load_policy' => 'yes', 'level' => 0],
    ['setting_id' => 103, 'key_name' => 'layout_settings_parent', 'key_value' => 'parent-new', 'load_policy' => 'no', 'level' => 0],
    ['setting_id' => 104, 'key_name' => 'fresh_module_flag', 'key_value' => 'enabled', 'load_policy' => 'no', 'level' => 0],
]);

$suppressedPlan = static fn (): array => $run([
    ['setting_id' => 201, 'key_name' => 'base_url', 'key_value' => 'https://suppressed.test', 'load_policy' => 'yes', 'level' => 0],
], ['recursive_triggers' => false]);

$ignorePlan = static fn (): array => $run([
    ['setting_id' => 301, 'key_name' => 'base_url', 'key_value' => 'ignored-site', 'load_policy' => 'yes', 'level' => 0],
], ['conflict_action' => 'ignore']);

$nullUniquePlan = static fn (): array => $run([
    ['setting_id' => 401, 'key_name' => null, 'key_value' => 'null-a', 'load_policy' => 'no', 'level' => 0],
    ['setting_id' => 402, 'key_name' => null, 'key_value' => 'null-b', 'load_policy' => 'no', 'level' => 0],
]);

$cases = [
    'recursive plan changes statement plus recursive rows' => [static fn (): mixed => $recursivePlan()['changes'], 5],
    'recursive plan max depth includes trigger upserts' => [static fn (): mixed => $recursivePlan()['max_depth_seen'], 1],
    'recursive plan preserves row count with one insert' => [static fn (): mixed => count($recursivePlan()['rows']), 5],
    'recursive plan row order keeps current rows then insert' => [static fn (): mixed => array_column($recursivePlan()['rows'], 'key_name'), ['base_url', 'landing_page', 'layout_settings_parent', 'layout_settings_child', 'fresh_module_flag']],
    'recursive plan updates base_url value' => [static fn (): mixed => $recursivePlan()['rows'][0]['key_value'], 'https://new.test'],
    'recursive plan recursive landing_page conflict sees current changed base_url' => [static fn (): mixed => $recursivePlan()['rows'][1]['key_value'], 'https://new.test/landing_page'],
    'recursive plan updates theme parent' => [static fn (): mixed => $recursivePlan()['rows'][2]['key_value'], 'parent-new'],
    'recursive plan recursive child conflict sees current parent' => [static fn (): mixed => $recursivePlan()['rows'][3]['key_value'], 'parent-new:child'],
    'recursive plan inserts fresh row' => [static fn (): mixed => $recursivePlan()['rows'][4]['key_name'], 'fresh_module_flag'],
    'recursive plan inserted list contains only fresh row' => [static fn (): mixed => array_column($recursivePlan()['inserted'], 'key_name'), ['fresh_module_flag']],
    'recursive plan updated list includes recursive conflicts' => [static fn (): mixed => array_column($recursivePlan()['updated'], 'key_name'), ['base_url', 'landing_page', 'layout_settings_parent', 'layout_settings_child']],
    'recursive plan skipped list is empty' => [static fn (): mixed => $recursivePlan()['skipped'], []],
    'recursive plan yielded statuses all changed' => [static fn (): mixed => array_column($recursivePlan()['yielded'], 'status'), ['changed', 'changed', 'changed', 'changed', 'changed']],
    'recursive plan yielded source sequence marks trigger rows' => [static fn (): mixed => array_column($recursivePlan()['yielded'], 'source'), ['statement', 'trigger', 'statement', 'trigger', 'statement']],
    'recursive plan yielded trigger names for recursive rows' => [static fn (): mixed => array_column($recursivePlan()['yielded'], 'trigger'), [null, 'app_settings_au_touch_home', null, 'app_settings_au_touch_child', null]],
    'recursive plan yielded events include update and insert' => [static fn (): mixed => array_column($recursivePlan()['yielded'], 'event'), ['update', 'update', 'update', 'update', 'insert']],
    'recursive plan yielded depths include recursive children' => [static fn (): mixed => array_column($recursivePlan()['yielded'], 'depth'), [0, 1, 0, 1, 0]],
    'recursive plan yielded old keys expose current conflicts' => [static fn (): mixed => array_column($recursivePlan()['yielded'], 'old_key'), ['base_url', 'landing_page', 'layout_settings_parent', 'layout_settings_child', null]],
    'recursive plan yielded new values are final statement order' => [static fn (): mixed => array_column($recursivePlan()['yielded'], 'new_value'), ['https://new.test', 'https://new.test/landing_page', 'parent-new', 'parent-new:child', 'enabled']],
    'recursive plan trigger effects include two recursive upserts and audit' => [static fn (): mixed => array_column($recursivePlan()['trigger_effects'], 'result'), ['recursive-upsert', 'recursive-upsert', 'audit']],
    'recursive plan trigger effect names are stable' => [static fn (): mixed => array_column($recursivePlan()['trigger_effects'], 'trigger'), ['app_settings_au_touch_home', 'app_settings_au_touch_child', 'app_settings_au_child_audit']],
    'recursive plan first effect row records source key' => [static fn (): mixed => $recursivePlan()['trigger_effects'][0]['row']['seen'], 'base_url'],
    'recursive plan first effect row records target key' => [static fn (): mixed => $recursivePlan()['trigger_effects'][0]['row']['next'], 'landing_page'],
    'recursive plan audit sees recursive child row' => [static fn (): mixed => $recursivePlan()['trigger_effects'][2]['row']['seen'], 'layout_settings_child'],
    'recursive plan audit sees recursive child value' => [static fn (): mixed => $recursivePlan()['trigger_effects'][2]['row']['value'], 'parent-new:child'],
    'recursive plan recursive row level is incremented' => [static fn (): mixed => $recursivePlan()['rows'][1]['level'], 1],
    'recursive plan second recursive row level is incremented' => [static fn (): mixed => $recursivePlan()['rows'][3]['level'], 1],
    'recursive plan unchanged load_policy for base_url is yes' => [static fn (): mixed => $recursivePlan()['rows'][0]['load_policy'], 'yes'],
    'recursive plan child load_policy remains no' => [static fn (): mixed => $recursivePlan()['rows'][3]['load_policy'], 'no'],
    'recursive plan fresh load_policy remains no' => [static fn (): mixed => $recursivePlan()['rows'][4]['load_policy'], 'no'],

    'suppressed plan changes only statement row' => [static fn (): mixed => $suppressedPlan()['changes'], 1],
    'suppressed plan leaves landing_page unchanged' => [static fn (): mixed => $suppressedPlan()['rows'][1]['key_value'], 'old-landing_page'],
    'suppressed plan records suppressed trigger effect' => [static fn (): mixed => $suppressedPlan()['trigger_effects'][0]['result'], 'recursive-suppressed'],
    'suppressed plan yields only statement row' => [static fn (): mixed => count($suppressedPlan()['yielded']), 1],
    'suppressed plan max depth remains zero' => [static fn (): mixed => $suppressedPlan()['max_depth_seen'], 0],
    'suppressed plan no skipped rows' => [static fn (): mixed => $suppressedPlan()['skipped'], []],

    'ignore plan changes zero' => [static fn (): mixed => $ignorePlan()['changes'], 0],
    'ignore plan records skipped conflict' => [static fn (): mixed => array_column($ignorePlan()['yielded'], 'status'), ['skipped']],
    'ignore plan skipped row is incoming conflict' => [static fn (): mixed => $ignorePlan()['skipped'][0]['key_value'], 'ignored-site'],
    'ignore plan does not fire triggers' => [static fn (): mixed => $ignorePlan()['trigger_effects'], []],
    'ignore plan leaves base_url unchanged' => [static fn (): mixed => $ignorePlan()['rows'][0]['key_value'], 'old-site'],

    'null unique plan inserts both null names' => [static fn (): mixed => array_column($nullUniquePlan()['inserted'], 'setting_id'), [401, 402]],
    'null unique plan changes both rows' => [static fn (): mixed => $nullUniquePlan()['changes'], 2],
    'null unique plan appends null rows' => [static fn (): mixed => array_slice(array_column($nullUniquePlan()['rows'], 'key_value'), -2), ['null-a', 'null-b']],
    'null unique plan yields insert events' => [static fn (): mixed => array_column($nullUniquePlan()['yielded'], 'event'), ['insert', 'insert']],

    'depth limit rejects recursive trigger conflict' => [static fn (): mixed => $run([['setting_id' => 501, 'key_name' => 'base_url', 'key_value' => 'too-deep', 'load_policy' => 'yes', 'level' => 0]], ['max_depth' => 0]), RuntimeException::class],
    'missing unique column throws' => [static fn (): mixed => $run([['setting_id' => 502, 'key_value' => 'missing', 'load_policy' => 'no', 'level' => 0]]), InvalidArgumentException::class],
    'empty unique columns throws' => [static fn (): mixed => SQLiteRecursiveUpsertConflictYieldPlan::execute($rows, [], [], $assignments, []), InvalidArgumentException::class],
    'bad unique column name throws' => [static fn (): mixed => SQLiteRecursiveUpsertConflictYieldPlan::execute($rows, [], ['bad-name'], $assignments, []), InvalidArgumentException::class],
    'bad assignment column throws' => [static fn (): mixed => SQLiteRecursiveUpsertConflictYieldPlan::execute($rows, [['key_name' => 'base_url', 'key_value' => 'x']], ['key_name'], ['bad-name' => static fn (): string => 'x'], []), InvalidArgumentException::class],
    'bad trigger action throws' => [static fn (): mixed => $run([['setting_id' => 503, 'key_name' => 'base_url', 'key_value' => 'x', 'load_policy' => 'yes', 'level' => 0]], [], [[
        'name' => 'bad',
        'timing' => 'after',
        'event' => 'update',
        'action' => 'delete-parent',
    ]]), InvalidArgumentException::class],
    'bad when operator throws' => [static fn (): mixed => $run([['setting_id' => 504, 'key_name' => 'base_url', 'key_value' => 'x', 'load_policy' => 'yes', 'level' => 0]], [], [[
        'name' => 'bad_when',
        'timing' => 'after',
        'event' => 'update',
        'action' => 'audit',
        'when' => ['new.key_name', 'LIKE', 'site%'],
    ]]), InvalidArgumentException::class],
    'insert trigger old reference throws' => [static fn (): mixed => $run([['setting_id' => 505, 'key_name' => 'new_option', 'key_value' => 'x', 'load_policy' => 'no', 'level' => 0]], [], [[
        'name' => 'bad_old',
        'timing' => 'after',
        'event' => 'insert',
        'action' => 'audit',
        'values' => ['old' => 'old.key_name'],
    ]]), InvalidArgumentException::class],
    'bad max depth throws' => [static fn (): mixed => $run([], ['max_depth' => -1]), InvalidArgumentException::class],
    'bad conflict action throws' => [static fn (): mixed => $run([], ['conflict_action' => 'replace']), InvalidArgumentException::class],
    'malformed projection column throws' => [static fn (): mixed => $run([['setting_id' => 506, 'key_name' => 'base_url', 'key_value' => 'x', 'load_policy' => 'yes', 'level' => 0]], [], [[
        'name' => 'bad_row',
        'timing' => 'after',
        'event' => 'update',
        'action' => 'upsert-parent',
        'row' => ['bad-name' => 'new.key_name'],
    ]]), InvalidArgumentException::class],
    'missing new column throws' => [static fn (): mixed => $run([['setting_id' => 507, 'key_name' => 'base_url', 'key_value' => 'x', 'load_policy' => 'yes', 'level' => 0]], [], [[
        'name' => 'missing',
        'timing' => 'after',
        'event' => 'update',
        'action' => 'upsert-parent',
        'row' => ['key_name' => 'new.missing'],
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
