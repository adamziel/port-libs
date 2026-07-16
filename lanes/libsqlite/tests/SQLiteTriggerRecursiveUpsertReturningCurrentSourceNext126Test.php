<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveUpsertReturningCurrentSourceNextPlan;

$rows126 = [
    ['key_name' => 'base_url', 'key_value' => 'https://old.test', 'revision' => 1, 'depth' => 0, 'load_policy' => 'yes'],
    ['key_name' => 'module_seed', 'key_value' => 'seed-old', 'revision' => 4, 'depth' => 1, 'load_policy' => 'no'],
];

$assign126 = [
    'key_value' => static fn (array $old, array $incoming): mixed => $incoming['key_value'],
    'revision' => static fn (array $old, array $incoming): int => (int) $old['revision'] + (int) $incoming['revision'],
    'depth' => static fn (array $old, array $incoming): mixed => $incoming['depth'],
    'load_policy' => static fn (array $old, array $incoming): mixed => $incoming['load_policy'],
];

$triggers126 = [
    [
        'name' => 'app_settings_ai_recursive_child_126',
        'timing' => 'after',
        'event' => 'insert',
        'action' => 'upsert-parent',
        'when' => ['new.depth', '<', 3],
        'row' => [
            'key_name' => ['concat' => ['new.key_name', ':child']],
            'key_value' => ['concat' => ['new.key_value', ':child']],
            'revision' => 1,
            'depth' => ['add' => ['new.depth', 1]],
            'load_policy' => 'new.load_policy',
        ],
        'values' => ['name' => 'new.key_name', 'depth' => 'new.depth'],
    ],
    [
        'name' => 'app_settings_au_recursive_child_126',
        'timing' => 'after',
        'event' => 'update',
        'action' => 'upsert-parent',
        'when' => ['new.depth', '<', 3],
        'row' => [
            'key_name' => ['concat' => ['new.key_name', ':child']],
            'key_value' => ['concat' => ['new.key_value', ':child']],
            'revision' => 1,
            'depth' => ['add' => ['new.depth', 1]],
            'load_policy' => 'new.load_policy',
        ],
        'values' => ['name' => 'new.key_name', 'depth' => 'new.depth'],
    ],
];

$returning126 = [
    'key_name',
    ['expr' => 'new.key_value', 'as' => 'value'],
    ['expr' => 'excluded.key_value', 'as' => 'incoming_value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'trigger_depth'],
    ['expr' => 'trigger', 'as' => 'source_trigger'],
];

$run126 = static fn (array $current = null, array $next = null, array $options = []): array => SQLiteTriggerRecursiveUpsertReturningCurrentSourceNextPlan::execute(
    $rows126,
    $current ?? [
        ['key_name' => 'module_seed', 'key_value' => 'seed-current', 'revision' => 2, 'depth' => 1, 'load_policy' => 'yes'],
        ['key_name' => 'fresh_module', 'key_value' => 'fresh-current', 'revision' => 1, 'depth' => 1, 'load_policy' => 'no'],
    ],
    $next ?? [
        ['key_name' => 'module_seed:child', 'key_value' => 'seed-child-next', 'revision' => 3, 'depth' => 2, 'load_policy' => 'yes'],
        ['key_name' => 'next_module', 'key_value' => 'next', 'revision' => 1, 'depth' => 1, 'load_policy' => 'yes'],
    ],
    ['key_name'],
    $assign126,
    $triggers126,
    $options + ['current_source' => 'main@cookie-126', 'next_source' => 'main@cookie-127', 'returning' => $returning126],
);

$plan126 = static fn (): array => $run126();
$ignore126 = static fn (): array => $run126(
    [['key_name' => 'module_seed', 'key_value' => 'ignored-current', 'revision' => 9, 'depth' => 1, 'load_policy' => 'yes']],
    [['key_name' => 'module_seed', 'key_value' => 'ignored-next', 'revision' => 2, 'depth' => 1, 'load_policy' => 'yes']],
    ['conflict_action' => 'ignore']
);
$recursiveOff126 = static fn (): array => $run126(
    [['key_name' => 'plain_module', 'key_value' => 'plain-current', 'revision' => 1, 'depth' => 1, 'load_policy' => 'no']],
    [['key_name' => 'plain_module', 'key_value' => 'plain-next', 'revision' => 2, 'depth' => 1, 'load_policy' => 'yes']],
    ['recursive_triggers' => false]
);
$star126 = static fn (): array => $run126(
    [['key_name' => 'star_module', 'key_value' => 'star-current', 'revision' => 1, 'depth' => 3, 'load_policy' => 'no']],
    [['key_name' => 'star_module', 'key_value' => 'star-next', 'revision' => 2, 'depth' => 3, 'load_policy' => 'yes']],
    ['returning' => ['*']]
);

$cases126 = [
    'status records drain before next source' => [static fn (): mixed => $plan126()['status'], 'current-source-returning-drained-before-next-source'],
    'current source token retained' => [static fn (): mixed => $plan126()['current_source'], 'main@cookie-126'],
    'next source token retained' => [static fn (): mixed => $plan126()['next_source'], 'main@cookie-127'],
    'dependency marker named' => [static fn (): mixed => $plan126()['dependencies'][0], 'sqlite-trigger-recursive-upsert-returning-current-source-next126'],
    'current source rows are original rows' => [static fn (): mixed => array_column($plan126()['current_source_rows'], 'key_name'), ['base_url', 'module_seed']],
    'next source rows include drained recursive current rows' => [static fn (): mixed => array_column($plan126()['next_source_rows'], 'key_name'), ['base_url', 'module_seed', 'module_seed:child', 'module_seed:child:child', 'fresh_module', 'fresh_module:child', 'fresh_module:child:child']],
    'final rows include next statement and recursive rows' => [static fn (): mixed => array_column($plan126()['rows'], 'key_name'), ['base_url', 'module_seed', 'module_seed:child', 'module_seed:child:child', 'fresh_module', 'fresh_module:child', 'fresh_module:child:child', 'next_module', 'next_module:child', 'next_module:child:child']],
    'current returning count' => [static fn (): mixed => count($plan126()['current_returning_rows']), 6],
    'next returning count' => [static fn (): mixed => count($plan126()['next_returning_rows']), 5],
    'yield stream count' => [static fn (): mixed => count($plan126()['yield_stream']), 11],
    'current yield stream source tokens' => [static fn (): mixed => array_values(array_unique(array_column($plan126()['current_yield_stream'], 'source_token'))), ['main@cookie-126']],
    'next yield stream source tokens' => [static fn (): mixed => array_values(array_unique(array_column($plan126()['next_yield_stream'], 'source_token'))), ['main@cookie-127']],
    'current yield stream phases' => [static fn (): mixed => array_values(array_unique(array_column($plan126()['current_yield_stream'], 'phase'))), ['current']],
    'next yield stream phases' => [static fn (): mixed => array_values(array_unique(array_column($plan126()['next_yield_stream'], 'phase'))), ['next']],
    'statement returning rows are depth zero current rows' => [static fn (): mixed => array_column(array_column($plan126()['statement_returning_rows'], 'returning'), 'key_name'), ['module_seed', 'fresh_module']],
    'recursive returning rows are current trigger rows' => [static fn (): mixed => array_column(array_column($plan126()['recursive_returning_rows'], 'returning'), 'key_name'), ['module_seed:child', 'module_seed:child:child', 'fresh_module:child', 'fresh_module:child:child']],
    'next statement returning rows include current-source update and next insert' => [static fn (): mixed => array_column(array_column($plan126()['next_statement_returning_rows'], 'returning'), 'key_name'), ['module_seed:child', 'next_module']],
    'next recursive returning rows include update child and inserts' => [static fn (): mixed => array_column(array_column($plan126()['next_recursive_returning_rows'], 'returning'), 'key_name'), ['module_seed:child:child', 'next_module:child', 'next_module:child:child']],
    'first current returning event update' => [static fn (): mixed => $plan126()['current_returning_rows'][0]['returning']['event_name'], 'update'],
    'second current returning is recursive insert' => [static fn (): mixed => $plan126()['current_returning_rows'][1]['returning']['event_name'], 'insert'],
    'next first returning updates current recursive child' => [static fn (): mixed => $plan126()['next_returning_rows'][0]['returning']['event_name'], 'update'],
    'next first returning sees next incoming value' => [static fn (): mixed => $plan126()['next_returning_rows'][0]['returning']['incoming_value'], 'seed-child-next'],
    'next first returning old key is current-source child' => [static fn (): mixed => $plan126()['next_returning_rows'][0]['old_key'], 'module_seed:child'],
    'handoff from current to next' => [static fn (): mixed => $plan126()['handoff']['from'] . '->' . $plan126()['handoff']['to'], 'main@cookie-126->main@cookie-127'],
    'handoff before count' => [static fn (): mixed => $plan126()['handoff']['before_count'], 2],
    'handoff after count' => [static fn (): mixed => $plan126()['handoff']['after_count'], 7],
    'handoff drained returning rows' => [static fn (): mixed => $plan126()['handoff']['returning_rows_drained'], 6],
    'handoff drained all current yields' => [static fn (): mixed => $plan126()['handoff']['yield_rows_drained'], 6],
    'handoff inserted keys include current recursive rows' => [static fn (): mixed => $plan126()['handoff']['inserted_keys'], ['module_seed:child', 'module_seed:child:child', 'fresh_module', 'fresh_module:child', 'fresh_module:child:child']],
    'handoff returning keys match current returning order' => [static fn (): mixed => $plan126()['handoff']['returning_keys'], ['module_seed', 'module_seed:child', 'module_seed:child:child', 'fresh_module', 'fresh_module:child', 'fresh_module:child:child']],
    'handoff next source contains returning keys' => [static fn (): mixed => $plan126()['handoff']['next_source_contains_all_returning_keys'], true],
    'changes combine current and next' => [static fn (): mixed => $plan126()['changes'], 11],
    'current max depth' => [static fn (): mixed => $plan126()['current']['max_depth_seen'], 2],
    'next max depth' => [static fn (): mixed => $plan126()['next']['max_depth_seen'], 2],
    'current trigger effects include update then insert recursion' => [static fn (): mixed => array_column($plan126()['current']['trigger_effects'], 'trigger'), ['app_settings_au_recursive_child_126', 'app_settings_ai_recursive_child_126', 'app_settings_ai_recursive_child_126', 'app_settings_ai_recursive_child_126']],
    'next trigger effects include update and insert recursion' => [static fn (): mixed => array_column($plan126()['next']['trigger_effects'], 'trigger'), ['app_settings_au_recursive_child_126', 'app_settings_ai_recursive_child_126', 'app_settings_ai_recursive_child_126']],
    'final overwritten current child value' => [static fn (): mixed => $plan126()['rows'][2]['key_value'], 'seed-child-next'],
    'final next recursive child value' => [static fn (): mixed => $plan126()['rows'][8]['key_value'], 'next:child'],
    'ignore current returning is empty' => [static fn (): mixed => $ignore126()['current_returning_rows'], []],
    'ignore next returning is empty' => [static fn (): mixed => $ignore126()['next_returning_rows'], []],
    'ignore handoff drains skipped yield only' => [static fn (): mixed => $ignore126()['handoff']['yield_rows_drained'], 1],
    'ignore handoff returning rows zero' => [static fn (): mixed => $ignore126()['handoff']['returning_rows_drained'], 0],
    'ignore next source remains base rows' => [static fn (): mixed => array_column($ignore126()['next_source_rows'], 'key_name'), ['base_url', 'module_seed']],
    'ignore yield stream records skipped visible false' => [static fn (): mixed => $ignore126()['yield_stream'][0]['returning_visible'], false],
    'recursive off current statement only' => [static fn (): mixed => array_column(array_column($recursiveOff126()['current_returning_rows'], 'returning'), 'key_name'), ['plain_module']],
    'recursive off next statement only' => [static fn (): mixed => array_column(array_column($recursiveOff126()['next_returning_rows'], 'returning'), 'key_name'), ['plain_module']],
    'recursive off handoff after count' => [static fn (): mixed => $recursiveOff126()['handoff']['after_count'], 3],
    'recursive off trigger effects suppressed' => [static fn (): mixed => $recursiveOff126()['current']['trigger_effects'][0]['result'], 'recursive-suppressed'],
    'star current returning row nests current source result' => [static fn (): mixed => $star126()['current_returning_rows'][0]['returning']['*']['key_value'], 'star-current'],
    'star next returning row nests next source result' => [static fn (): mixed => $star126()['next_returning_rows'][0]['returning']['*']['key_value'], 'star-next'],
    'bad current source token throws' => [static fn (): mixed => $run126(null, null, ['current_source' => 'bad token']), InvalidArgumentException::class],
    'bad next source token throws' => [static fn (): mixed => $run126(null, null, ['next_source' => 'bad token']), InvalidArgumentException::class],
    'empty current rows throw' => [static fn (): mixed => $run126([], null), InvalidArgumentException::class],
    'empty next rows throw' => [static fn (): mixed => $run126(null, []), InvalidArgumentException::class],
    'malformed current rows throw' => [static fn (): mixed => SQLiteTriggerRecursiveUpsertReturningCurrentSourceNextPlan::execute($rows126, ['bad' => ['key_name' => 'x']], [['key_name' => 'y']], ['key_name'], $assign126, $triggers126), InvalidArgumentException::class],
    'missing next unique column throws' => [static fn (): mixed => $run126(null, [['key_value' => 'missing']]), InvalidArgumentException::class],
    'max depth throws before next handoff' => [static fn (): mixed => $run126([['key_name' => 'depth_current', 'key_value' => 'depth', 'revision' => 1, 'depth' => 1, 'load_policy' => 'no']], null, ['max_depth' => 1]), RuntimeException::class],
];

foreach ($cases126 as $name => [$callback, $expected]) {
    $tests['trigger recursive upsert returning current source next126 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
