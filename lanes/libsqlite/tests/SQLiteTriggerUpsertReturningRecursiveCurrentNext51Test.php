<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteRecursiveUpsertConflictYieldPlan;

$tests = [];

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
    static fn (array $new, ?array $old, array $incoming, string $event, int $depth, ?string $trigger): string => $event . ':' . ($old['option_name'] ?? 'insert') . '->' . $new['option_name'] . '@' . $depth . ':' . ($trigger ?? 'statement'),
];

$run = static fn (array $incoming, array $options = [], ?array $triggerSet = null): array => SQLiteRecursiveUpsertConflictYieldPlan::execute(
    $rows,
    $incoming,
    ['option_name'],
    $assignments,
    $triggerSet ?? $triggers,
    $options + ['returning' => $returning],
);

$recursivePlan = static fn (): array => $run([
    ['option_name' => 'plugin_seed', 'option_value' => 'seed-new', 'revision' => 3, 'depth' => 1, 'autoload' => 'yes'],
    ['option_name' => 'fresh_plugin', 'option_value' => 'fresh', 'revision' => 1, 'depth' => 1, 'autoload' => 'no'],
]);

$ignorePlan = static fn (): array => $run([
    ['option_name' => 'plugin_seed', 'option_value' => 'ignored', 'revision' => 7, 'depth' => 1, 'autoload' => 'yes'],
    ['option_name' => 'fresh_ignored_after', 'option_value' => 'fresh', 'revision' => 1, 'depth' => 1, 'autoload' => 'no'],
], ['conflict_action' => 'ignore']);

$recursiveOffPlan = static fn (): array => $run([
    ['option_name' => 'fresh_off', 'option_value' => 'off', 'revision' => 1, 'depth' => 1, 'autoload' => 'no'],
], ['recursive_triggers' => false]);

$defaultReturningPlan = static fn (): array => SQLiteRecursiveUpsertConflictYieldPlan::execute(
    $rows,
    [['option_name' => 'siteurl', 'option_value' => 'https://new.test', 'revision' => 4, 'depth' => 0, 'autoload' => 'yes']],
    ['option_name'],
    $assignments,
    $triggers,
);

$starPlan = static fn (): array => $run([
    ['option_name' => 'star_plugin', 'option_value' => 'star', 'revision' => 1, 'depth' => 3, 'autoload' => 'no'],
], ['returning' => ['*']]);

$cases = [
    'recursive changes include update plus recursive children and fresh tree' => [static fn (): mixed => $recursivePlan()['changes'], 6],
    'recursive max depth seen includes two trigger levels' => [static fn (): mixed => $recursivePlan()['max_depth_seen'], 2],
    'recursive final row names include trigger generated children' => [static fn (): mixed => array_column($recursivePlan()['rows'], 'option_name'), ['siteurl', 'plugin_seed', 'plugin_seed:child', 'plugin_seed:child:child', 'fresh_plugin', 'fresh_plugin:child', 'fresh_plugin:child:child']],
    'recursive final values include statement update and child suffixes' => [static fn (): mixed => array_column($recursivePlan()['rows'], 'option_value'), ['https://old.test', 'seed-new', 'seed-new:child', 'seed-new:child:child', 'fresh', 'fresh:child', 'fresh:child:child']],
    'recursive inserted rows are trigger children and fresh rows' => [static fn (): mixed => array_column($recursivePlan()['inserted'], 'option_name'), ['plugin_seed:child', 'plugin_seed:child:child', 'fresh_plugin', 'fresh_plugin:child', 'fresh_plugin:child:child']],
    'recursive updated rows contain current plugin seed' => [static fn (): mixed => array_column($recursivePlan()['updated'], 'option_name'), ['plugin_seed']],
    'recursive skipped rows empty' => [static fn (): mixed => $recursivePlan()['skipped'], []],
    'recursive yielded rows are current-row order with recursive children' => [static fn (): mixed => array_column($recursivePlan()['yielded'], 'new_key'), ['plugin_seed', 'plugin_seed:child', 'plugin_seed:child:child', 'fresh_plugin', 'fresh_plugin:child', 'fresh_plugin:child:child']],
    'recursive yielded statuses changed for all rows' => [static fn (): mixed => array_column($recursivePlan()['yielded'], 'status'), ['changed', 'changed', 'changed', 'changed', 'changed', 'changed']],
    'recursive yielded events distinguish update and inserts' => [static fn (): mixed => array_column($recursivePlan()['yielded'], 'event'), ['update', 'insert', 'insert', 'insert', 'insert', 'insert']],
    'recursive yielded sources mark statement and trigger rows' => [static fn (): mixed => array_column($recursivePlan()['yielded'], 'source'), ['statement', 'trigger', 'trigger', 'statement', 'trigger', 'trigger']],
    'recursive yielded trigger names include update trigger first' => [static fn (): mixed => array_column($recursivePlan()['yielded'], 'trigger'), [null, 'wp_options_au_recursive_child', 'wp_options_ai_recursive_child', null, 'wp_options_ai_recursive_child', 'wp_options_ai_recursive_child']],
    'recursive yielded depths are current trigger depths' => [static fn (): mixed => array_column($recursivePlan()['yielded'], 'depth'), [0, 1, 2, 0, 1, 2]],
    'recursive top update old value is seed old' => [static fn (): mixed => $recursivePlan()['yielded'][0]['old_value'], 'seed-old'],
    'recursive top update incoming key is plugin seed' => [static fn (): mixed => $recursivePlan()['yielded'][0]['incoming_key'], 'plugin_seed'],
    'recursive child incoming key is projected child name' => [static fn (): mixed => $recursivePlan()['yielded'][1]['incoming_key'], 'plugin_seed:child'],
    'recursive returning row count matches changed rows' => [static fn (): mixed => count($recursivePlan()['returning']), 6],
    'recursive returning names preserve yield order' => [static fn (): mixed => array_column($recursivePlan()['returning'], 'option_name'), ['plugin_seed', 'plugin_seed:child', 'plugin_seed:child:child', 'fresh_plugin', 'fresh_plugin:child', 'fresh_plugin:child:child']],
    'recursive top returning value is assigned current row' => [static fn (): mixed => $recursivePlan()['returning'][0]['value'], 'seed-new'],
    'recursive top returning incoming value is excluded row' => [static fn (): mixed => $recursivePlan()['returning'][0]['incoming_value'], 'seed-new'],
    'recursive first child returning incoming value is trigger projection' => [static fn (): mixed => $recursivePlan()['returning'][1]['incoming_value'], 'seed-new:child'],
    'recursive second child returning incoming value is nested projection' => [static fn (): mixed => $recursivePlan()['returning'][2]['incoming_value'], 'seed-new:child:child'],
    'recursive returning event names follow each row' => [static fn (): mixed => array_column($recursivePlan()['returning'], 'event_name'), ['update', 'insert', 'insert', 'insert', 'insert', 'insert']],
    'recursive returning trigger depths follow yields' => [static fn (): mixed => array_column($recursivePlan()['returning'], 'trigger_depth'), [0, 1, 2, 0, 1, 2]],
    'recursive returning source triggers include null for statement rows' => [static fn (): mixed => array_column($recursivePlan()['returning'], 'source_trigger'), [null, 'wp_options_au_recursive_child', 'wp_options_ai_recursive_child', null, 'wp_options_ai_recursive_child', 'wp_options_ai_recursive_child']],
    'recursive callable returning labels top update' => [static fn (): mixed => $recursivePlan()['returning'][0]['expr6'], 'update:plugin_seed->plugin_seed@0:statement'],
    'recursive callable returning labels update child' => [static fn (): mixed => $recursivePlan()['returning'][1]['expr6'], 'insert:insert->plugin_seed:child@1:wp_options_au_recursive_child'],
    'recursive callable returning labels nested insert child' => [static fn (): mixed => $recursivePlan()['returning'][2]['expr6'], 'insert:insert->plugin_seed:child:child@2:wp_options_ai_recursive_child'],
    'recursive trigger effects include two update-side children and two insert-side children' => [static fn (): mixed => array_column($recursivePlan()['trigger_effects'], 'trigger'), ['wp_options_au_recursive_child', 'wp_options_ai_recursive_child', 'wp_options_ai_recursive_child', 'wp_options_ai_recursive_child']],
    'recursive trigger effects depths include nested trigger levels' => [static fn (): mixed => array_column($recursivePlan()['trigger_effects'], 'depth'), [0, 1, 0, 1]],
    'recursive trigger effect rows record source names' => [static fn (): mixed => array_column(array_column($recursivePlan()['trigger_effects'], 'row'), 'name'), ['plugin_seed', 'plugin_seed:child', 'fresh_plugin', 'fresh_plugin:child']],

    'ignore changes include only fresh insert tree' => [static fn (): mixed => $ignorePlan()['changes'], 3],
    'ignore skipped rows contain current conflict' => [static fn (): mixed => array_column($ignorePlan()['skipped'], 'option_name'), ['plugin_seed']],
    'ignore yielded statuses include skipped then changed rows' => [static fn (): mixed => array_column($ignorePlan()['yielded'], 'status'), ['skipped', 'changed', 'changed', 'changed']],
    'ignore skipped returning is null' => [static fn (): mixed => $ignorePlan()['yielded'][0]['returning'], null],
    'ignore skipped old value is current row' => [static fn (): mixed => $ignorePlan()['yielded'][0]['old_value'], 'seed-old'],
    'ignore returning rows omit skipped row' => [static fn (): mixed => array_column($ignorePlan()['returning'], 'option_name'), ['fresh_ignored_after', 'fresh_ignored_after:child', 'fresh_ignored_after:child:child']],
    'ignore later fresh row still recurses' => [static fn (): mixed => $ignorePlan()['max_depth_seen'], 2],
    'ignore final plugin seed remains old' => [static fn (): mixed => $ignorePlan()['rows'][1]['option_value'], 'seed-old'],

    'recursive off inserts only statement row' => [static fn (): mixed => array_column($recursiveOffPlan()['rows'], 'option_name'), ['siteurl', 'plugin_seed', 'fresh_off']],
    'recursive off changes one row' => [static fn (): mixed => $recursiveOffPlan()['changes'], 1],
    'recursive off max depth remains zero' => [static fn (): mixed => $recursiveOffPlan()['max_depth_seen'], 0],
    'recursive off trigger effect records suppression' => [static fn (): mixed => $recursiveOffPlan()['trigger_effects'][0]['result'], 'recursive-suppressed'],
    'recursive off yielded only statement depth zero' => [static fn (): mixed => array_column($recursiveOffPlan()['yielded'], 'depth'), [0]],
    'recursive off returning source trigger is null' => [static fn (): mixed => $recursiveOffPlan()['returning'][0]['source_trigger'], null],

    'default returning without projection exposes full row' => [static fn (): mixed => $defaultReturningPlan()['returning'][0]['option_value'], 'https://new.test'],
    'default returning includes assigned revision' => [static fn (): mixed => $defaultReturningPlan()['returning'][0]['revision'], 6],
    'star returning nests full row' => [static fn (): mixed => $starPlan()['returning'][0]['*']['option_name'], 'star_plugin'],
    'star returning preserves row value' => [static fn (): mixed => $starPlan()['yielded'][0]['returning']['*']['option_value'], 'star'],

    'old returning update works' => [static fn (): mixed => $run([['option_name' => 'siteurl', 'option_value' => 'new', 'revision' => 1, 'depth' => 3, 'autoload' => 'yes']], ['returning' => [['expr' => 'old.option_value', 'as' => 'old_value']]])['returning'][0]['old_value'], 'https://old.test'],
    'old returning insert throws' => [static fn (): mixed => $run([['option_name' => 'insert_old_bad', 'option_value' => 'bad', 'revision' => 1, 'depth' => 3, 'autoload' => 'no']], ['returning' => ['old.option_value']]), InvalidArgumentException::class],
    'missing returning column throws' => [static fn (): mixed => $run([['option_name' => 'missing_bad', 'option_value' => 'bad', 'revision' => 1, 'depth' => 3, 'autoload' => 'no']], ['returning' => ['missing']]), InvalidArgumentException::class],
    'missing excluded returning column throws' => [static fn (): mixed => $run([['option_name' => 'missing_excluded_bad', 'option_value' => 'bad', 'revision' => 1, 'depth' => 3, 'autoload' => 'no']], ['returning' => [['expr' => 'excluded.missing', 'as' => 'missing']]]), InvalidArgumentException::class],
    'malformed returning alias throws' => [static fn (): mixed => $run([['option_name' => 'bad_alias', 'option_value' => 'bad', 'revision' => 1, 'depth' => 3, 'autoload' => 'no']], ['returning' => [['expr' => 'option_name', 'as' => 'bad-alias']]]), InvalidArgumentException::class],
    'malformed returning entry throws' => [static fn (): mixed => $run([['option_name' => 'bad_entry', 'option_value' => 'bad', 'revision' => 1, 'depth' => 3, 'autoload' => 'no']], ['returning' => [123]]), InvalidArgumentException::class],
    'malformed returning projection throws' => [static fn (): mixed => $run([], ['returning' => ['name' => 'option_name']]), InvalidArgumentException::class],
    'empty returning expression throws' => [static fn (): mixed => $run([['option_name' => 'empty_expr', 'option_value' => 'bad', 'revision' => 1, 'depth' => 3, 'autoload' => 'no']], ['returning' => [['expr' => '', 'as' => 'empty']]]), InvalidArgumentException::class],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['trigger upsert returning recursive current next51 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
