<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerReturningRecursiveUpsertCurrentSourceNextPlan;

$rows = [
    ['option_name' => 'siteurl', 'option_value' => 'https://old.test', 'revision' => 2, 'depth' => 0, 'autoload' => 'yes'],
    ['option_name' => 'plugin_seed', 'option_value' => 'seed-old', 'revision' => 5, 'depth' => 1, 'autoload' => 'no'],
];

$assignments = [
    'option_value' => static fn (array $old, array $incoming): mixed => $incoming['option_value'],
    'revision' => static fn (array $old, array $incoming): int => (int) $old['revision'] + (int) $incoming['revision'],
    'depth' => static fn (array $old, array $incoming): mixed => $incoming['depth'],
    'autoload' => static fn (array $old, array $incoming): mixed => $incoming['autoload'],
];

$triggers = [
    [
        'name' => 'wp_options_ai_recursive_child',
        'timing' => 'after',
        'event' => 'insert',
        'action' => 'upsert-parent',
        'when' => ['new.depth', '<', 3],
        'row' => [
            'option_name' => ['concat' => ['new.option_name', ':child']],
            'option_value' => ['concat' => ['new.option_value', ':child']],
            'revision' => 1,
            'depth' => ['add' => ['new.depth', 1]],
            'autoload' => 'new.autoload',
        ],
        'values' => ['name' => 'new.option_name', 'depth' => 'new.depth'],
    ],
    [
        'name' => 'wp_options_au_recursive_child',
        'timing' => 'after',
        'event' => 'update',
        'action' => 'upsert-parent',
        'when' => ['new.depth', '<', 3],
        'row' => [
            'option_name' => ['concat' => ['new.option_name', ':child']],
            'option_value' => ['concat' => ['new.option_value', ':child']],
            'revision' => 1,
            'depth' => ['add' => ['new.depth', 1]],
            'autoload' => 'new.autoload',
        ],
        'values' => ['name' => 'new.option_name', 'depth' => 'new.depth'],
    ],
];

$returning = [
    'option_name',
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'excluded.option_value', 'as' => 'incoming_value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'trigger_depth'],
    ['expr' => 'trigger', 'as' => 'source_trigger'],
];

$run = static fn (array $current = null, array $next = null, array $options = [], array $triggerSet = null): array => SQLiteTriggerReturningRecursiveUpsertCurrentSourceNextPlan::execute(
    $rows,
    $current ?? [
        ['option_name' => 'plugin_seed', 'option_value' => 'seed-current', 'revision' => 3, 'depth' => 1, 'autoload' => 'yes'],
        ['option_name' => 'fresh_plugin', 'option_value' => 'fresh-current', 'revision' => 1, 'depth' => 1, 'autoload' => 'no'],
    ],
    $next ?? [
        ['option_name' => 'plugin_seed:child', 'option_value' => 'seed-child-next', 'revision' => 4, 'depth' => 2, 'autoload' => 'yes'],
        ['option_name' => 'next_plugin', 'option_value' => 'next', 'revision' => 1, 'depth' => 1, 'autoload' => 'yes'],
    ],
    ['option_name'],
    $assignments,
    $triggerSet ?? $triggers,
    $options + ['returning' => $returning],
);

$plan = static fn (): array => $run();
$ignore = static fn (): array => $run(
    [['option_name' => 'plugin_seed', 'option_value' => 'ignored-current', 'revision' => 9, 'depth' => 1, 'autoload' => 'yes']],
    [['option_name' => 'plugin_seed', 'option_value' => 'next-after-ignore', 'revision' => 2, 'depth' => 1, 'autoload' => 'yes']],
    ['conflict_action' => 'ignore']
);
$recursiveOff = static fn (): array => $run(
    [['option_name' => 'fresh_off', 'option_value' => 'off-current', 'revision' => 1, 'depth' => 1, 'autoload' => 'no']],
    [['option_name' => 'fresh_off', 'option_value' => 'off-next', 'revision' => 2, 'depth' => 1, 'autoload' => 'yes']],
    ['recursive_triggers' => false]
);
$star = static fn (): array => $run(
    [['option_name' => 'star_current', 'option_value' => 'star', 'revision' => 1, 'depth' => 3, 'autoload' => 'no']],
    [['option_name' => 'star_current', 'option_value' => 'star-next', 'revision' => 2, 'depth' => 3, 'autoload' => 'yes']],
    ['returning' => ['*']]
);
$maxDepth = static fn (): array => $run(
    [['option_name' => 'depth_current', 'option_value' => 'depth', 'revision' => 1, 'depth' => 1, 'autoload' => 'no']],
    [['option_name' => 'depth_next', 'option_value' => 'depth-next', 'revision' => 1, 'depth' => 1, 'autoload' => 'yes']],
    ['max_depth' => 1]
);

$cases = [
    'status records current returning handoff' => [static fn (): mixed => $plan()['status'], 'current-returning-recursive-upsert-next-source-applied'],
    'dependencies name next118 behavior' => [static fn (): mixed => $plan()['dependencies'][0], 'sqlite-recursive-upsert-trigger-returning-current-source-next118'],
    'current source starts with base rows' => [static fn (): mixed => array_column($plan()['current_source_rows'], 'option_name'), ['siteurl', 'plugin_seed']],
    'next source starts from current result rows' => [static fn (): mixed => array_column($plan()['next_source_rows'], 'option_name'), ['siteurl', 'plugin_seed', 'plugin_seed:child', 'plugin_seed:child:child', 'fresh_plugin', 'fresh_plugin:child', 'fresh_plugin:child:child']],
    'final rows include next updates and recursive inserts' => [static fn (): mixed => array_column($plan()['rows'], 'option_name'), ['siteurl', 'plugin_seed', 'plugin_seed:child', 'plugin_seed:child:child', 'fresh_plugin', 'fresh_plugin:child', 'fresh_plugin:child:child', 'next_plugin', 'next_plugin:child', 'next_plugin:child:child']],
    'final plugin seed remains current value' => [static fn (): mixed => $plan()['rows'][1]['option_value'], 'seed-current'],
    'final plugin seed child is overwritten by next source' => [static fn (): mixed => $plan()['rows'][2]['option_value'], 'seed-child-next'],
    'final next plugin child is recursive next value' => [static fn (): mixed => $plan()['rows'][8]['option_value'], 'next:child'],
    'combined changes include current six plus next five' => [static fn (): mixed => $plan()['changes'], 11],
    'current changes include update plus recursive current trees' => [static fn (): mixed => $plan()['current']['changes'], 6],
    'next changes include child update cascade and next tree' => [static fn (): mixed => $plan()['next']['changes'], 5],
    'current returning count matches current changes' => [static fn (): mixed => count($plan()['current_returning']), 6],
    'next returning count matches next changes' => [static fn (): mixed => count($plan()['next_returning']), 5],
    'current returning names preserve statement and recursive order' => [static fn (): mixed => array_column($plan()['current_returning'], 'option_name'), ['plugin_seed', 'plugin_seed:child', 'plugin_seed:child:child', 'fresh_plugin', 'fresh_plugin:child', 'fresh_plugin:child:child']],
    'next returning names preserve current-source cascade then next recursion' => [static fn (): mixed => array_column($plan()['next_returning'], 'option_name'), ['plugin_seed:child', 'plugin_seed:child:child', 'next_plugin', 'next_plugin:child', 'next_plugin:child:child']],
    'current returning event names distinguish update and insert' => [static fn (): mixed => array_column($plan()['current_returning'], 'event_name'), ['update', 'insert', 'insert', 'insert', 'insert', 'insert']],
    'next returning first event is update of current child' => [static fn (): mixed => $plan()['next_returning'][0]['event_name'], 'update'],
    'next returning later events include recursive update then inserts' => [static fn (): mixed => array_slice(array_column($plan()['next_returning'], 'event_name'), 1), ['update', 'insert', 'insert', 'insert']],
    'current returning trigger depths include recursive rows' => [static fn (): mixed => array_column($plan()['current_returning'], 'trigger_depth'), [0, 1, 2, 0, 1, 2]],
    'next returning trigger depths restart and recurse from next statement' => [static fn (): mixed => array_column($plan()['next_returning'], 'trigger_depth'), [0, 1, 0, 1, 2]],
    'current source triggers mark statement and trigger rows' => [static fn (): mixed => array_column($plan()['current_returning'], 'source_trigger'), [null, 'wp_options_au_recursive_child', 'wp_options_ai_recursive_child', null, 'wp_options_ai_recursive_child', 'wp_options_ai_recursive_child']],
    'next source triggers mark updated current child as statement' => [static fn (): mixed => $plan()['next_returning'][0]['source_trigger'], null],
    'current yield edge phases are current' => [static fn (): mixed => array_values(array_unique(array_column($plan()['current_yield_edges'], 'phase'))), ['current']],
    'next yield edge phases are next' => [static fn (): mixed => array_values(array_unique(array_column($plan()['next_yield_edges'], 'phase'))), ['next']],
    'current yield edges count six' => [static fn (): mixed => count($plan()['current_yield_edges']), 6],
    'next yield edges count five' => [static fn (): mixed => count($plan()['next_yield_edges']), 5],
    'current first edge uses old conflict row as source' => [static fn (): mixed => $plan()['current_yield_edges'][0]['current_source_key'], 'plugin_seed'],
    'current first edge returns updated plugin seed' => [static fn (): mixed => $plan()['current_yield_edges'][0]['returning_key'], 'plugin_seed'],
    'current first recursive edge source is trigger projection' => [static fn (): mixed => $plan()['current_yield_edges'][1]['current_source_key'], 'plugin_seed:child'],
    'next first edge uses current child row as source' => [static fn (): mixed => $plan()['next_yield_edges'][0]['current_source_key'], 'plugin_seed:child'],
    'next first edge returns current child row' => [static fn (): mixed => $plan()['next_yield_edges'][0]['returning_key'], 'plugin_seed:child'],
    'next second edge updates recursive child from current source' => [static fn (): mixed => $plan()['next_yield_edges'][1]['current_source_key'], 'plugin_seed:child:child'],
    'next third edge inserts next plugin from next source' => [static fn (): mixed => $plan()['next_yield_edges'][2]['current_source_key'], 'next_plugin'],
    'next recursive edge records source trigger' => [static fn (): mixed => $plan()['next_yield_edges'][3]['trigger'], 'wp_options_ai_recursive_child'],
    'current trigger effects include update then nested inserts' => [static fn (): mixed => array_column($plan()['current']['trigger_effects'], 'trigger'), ['wp_options_au_recursive_child', 'wp_options_ai_recursive_child', 'wp_options_ai_recursive_child', 'wp_options_ai_recursive_child']],
    'next trigger effects include update-side and insert-side recursion' => [static fn (): mixed => array_column($plan()['next']['trigger_effects'], 'trigger'), ['wp_options_au_recursive_child', 'wp_options_ai_recursive_child', 'wp_options_ai_recursive_child']],
    'next source rows contain current recursive fresh child before next starts' => [static fn (): mixed => in_array('fresh_plugin:child:child', array_column($plan()['next_source_rows'], 'option_name'), true), true],
    'next source row count is current final row count' => [static fn (): mixed => count($plan()['next_source_rows']), 7],
    'current max depth seen is two' => [static fn (): mixed => $plan()['current']['max_depth_seen'], 2],
    'next max depth seen is two' => [static fn (): mixed => $plan()['next']['max_depth_seen'], 2],

    'ignore current source records skipped conflict' => [static fn (): mixed => array_column($ignore()['current']['skipped'], 'option_name'), ['plugin_seed']],
    'ignore current returning empty' => [static fn (): mixed => $ignore()['current_returning'], []],
    'ignore next source is original base rows' => [static fn (): mixed => array_column($ignore()['next_source_rows'], 'option_value'), ['https://old.test', 'seed-old']],
    'ignore next source also skips plugin seed conflict' => [static fn (): mixed => $ignore()['next_returning'], []],
    'ignore combined changes remain zero because both sources conflict' => [static fn (): mixed => $ignore()['changes'], 0],
    'ignore yielded skipped edge has null returning' => [static fn (): mixed => $ignore()['current_yield_edges'][0]['returning'], null],
    'ignore yielded skipped edge keeps current row key' => [static fn (): mixed => $ignore()['current_yield_edges'][0]['current_source_key'], 'plugin_seed'],

    'recursive off current inserts only statement row' => [static fn (): mixed => array_column($recursiveOff()['current_returning'], 'option_name'), ['fresh_off']],
    'recursive off next updates statement row only' => [static fn (): mixed => array_column($recursiveOff()['next_returning'], 'option_name'), ['fresh_off']],
    'recursive off trigger effects record suppression' => [static fn (): mixed => $recursiveOff()['current']['trigger_effects'][0]['result'], 'recursive-suppressed'],
    'recursive off next source includes current inserted row' => [static fn (): mixed => array_column($recursiveOff()['next_source_rows'], 'option_name'), ['siteurl', 'plugin_seed', 'fresh_off']],
    'recursive off combined changes are two' => [static fn (): mixed => $recursiveOff()['changes'], 2],

    'star current returning nests full row' => [static fn (): mixed => $star()['current_returning'][0]['*']['option_name'], 'star_current'],
    'star next returning sees current source row' => [static fn (): mixed => $star()['next_returning'][0]['*']['option_value'], 'star-next'],
    'max depth rejects nested current recursion before next source' => [static fn (): mixed => $maxDepth(), RuntimeException::class],
    'empty current source rejected' => [static fn (): mixed => $run([], null), InvalidArgumentException::class],
    'empty next source rejected' => [static fn (): mixed => $run(null, []), InvalidArgumentException::class],
    'malformed current source rejected' => [static fn (): mixed => SQLiteTriggerReturningRecursiveUpsertCurrentSourceNextPlan::execute($rows, ['bad' => ['option_name' => 'x']], [['option_name' => 'y']], ['option_name'], $assignments, $triggers), InvalidArgumentException::class],
    'missing next unique column rejected by next source' => [static fn (): mixed => $run(null, [['option_value' => 'missing']]), InvalidArgumentException::class],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['trigger returning recursive upsert current source next118 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
