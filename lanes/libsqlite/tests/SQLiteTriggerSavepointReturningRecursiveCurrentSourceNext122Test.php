<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerSavepointReturningRecursiveCurrentSourceNextPlan;

$rows122 = [
    ['option_name' => 'siteurl', 'option_value' => 'https://old.test', 'revision' => 2, 'depth' => 0, 'autoload' => 'yes'],
    ['option_name' => 'plugin_seed', 'option_value' => 'seed-old', 'revision' => 5, 'depth' => 1, 'autoload' => 'no'],
];

$assignments122 = [
    'option_value' => static fn (array $old, array $incoming): mixed => $incoming['option_value'],
    'revision' => static fn (array $old, array $incoming): int => (int) $old['revision'] + (int) $incoming['revision'],
    'depth' => static fn (array $old, array $incoming): mixed => $incoming['depth'],
    'autoload' => static fn (array $old, array $incoming): mixed => $incoming['autoload'],
];

$triggers122 = [
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

$returning122 = [
    'option_name',
    ['expr' => 'new.option_value', 'as' => 'value'],
    ['expr' => 'excluded.option_value', 'as' => 'incoming_value'],
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'trigger_depth'],
    ['expr' => 'trigger', 'as' => 'source_trigger'],
];

$run122 = static fn (array $options = [], array $current = null, array $next = null): array => SQLiteTriggerSavepointReturningRecursiveCurrentSourceNextPlan::execute(
    $rows122,
    $current ?? [
        ['option_name' => 'plugin_seed', 'option_value' => 'seed-current', 'revision' => 3, 'depth' => 1, 'autoload' => 'yes'],
        ['option_name' => 'fresh_plugin', 'option_value' => 'fresh-current', 'revision' => 1, 'depth' => 1, 'autoload' => 'no'],
    ],
    $next ?? [
        ['option_name' => 'plugin_seed:child', 'option_value' => 'seed-child-next', 'revision' => 4, 'depth' => 2, 'autoload' => 'yes'],
        ['option_name' => 'next_plugin', 'option_value' => 'next', 'revision' => 1, 'depth' => 1, 'autoload' => 'yes'],
    ],
    ['option_name'],
    $assignments122,
    $triggers122,
    $options + ['savepoint' => 'wp_trigger_batch', 'returning' => $returning122],
);

$rolled122 = static fn (): array => $run122();
$released122 = static fn (): array => $run122(['rollback_to' => false]);
$ignored122 = static fn (): array => $run122(['conflict_action' => 'ignore']);
$nonRecursive122 = static fn (): array => $run122(['recursive_triggers' => false]);

$cases122 = [
    'savepoint name is retained' => [static fn (): mixed => $rolled122()['savepoint'], 'wp_trigger_batch'],
    'rolled back by default' => [static fn (): mixed => $rolled122()['rolled_back'], true],
    'rollback status records returning yield ordering' => [static fn (): mixed => $rolled122()['status'], 'rolled-back-to-savepoint-after-returning-yield'],
    'rollback restores original row image' => [static fn (): mixed => $rolled122()['rows'], $rows122],
    'current rows mirror rollback image' => [static fn (): mixed => $rolled122()['current_rows'], $rows122],
    'attempted rows preserve recursive current and next source results' => [static fn (): mixed => array_column($rolled122()['attempted_rows'], 'option_name'), ['siteurl', 'plugin_seed', 'plugin_seed:child', 'plugin_seed:child:child', 'fresh_plugin', 'fresh_plugin:child', 'fresh_plugin:child:child', 'next_plugin', 'next_plugin:child', 'next_plugin:child:child']],
    'attempted child row uses next source value' => [static fn (): mixed => $rolled122()['attempted_rows'][2]['option_value'], 'seed-child-next'],
    'attempted next recursive child uses trigger value' => [static fn (): mixed => $rolled122()['attempted_rows'][8]['option_value'], 'next:child'],
    'current source rows are savepoint rows' => [static fn (): mixed => array_column($rolled122()['current_source_rows'], 'option_name'), ['siteurl', 'plugin_seed']],
    'next source rows are attempted current result' => [static fn (): mixed => array_column($rolled122()['next_source_rows'], 'option_name'), ['siteurl', 'plugin_seed', 'plugin_seed:child', 'plugin_seed:child:child', 'fresh_plugin', 'fresh_plugin:child', 'fresh_plugin:child:child']],
    'current source count records base rows' => [static fn (): mixed => $rolled122()['current_source_count'], 2],
    'next source count records current attempted rows' => [static fn (): mixed => $rolled122()['next_source_count'], 7],
    'rollback suppresses committed current returning rows' => [static fn (): mixed => $rolled122()['current_returning_rows'], []],
    'rollback suppresses committed next returning rows' => [static fn (): mixed => $rolled122()['next_returning_rows'], []],
    'rollback suppresses committed combined returning rows' => [static fn (): mixed => $rolled122()['returning_rows'], []],
    'attempted current returning names survive as diagnostics' => [static fn (): mixed => array_column($rolled122()['attempted_current_returning_rows'], 'option_name'), ['plugin_seed', 'plugin_seed:child', 'plugin_seed:child:child', 'fresh_plugin', 'fresh_plugin:child', 'fresh_plugin:child:child']],
    'attempted next returning names survive as diagnostics' => [static fn (): mixed => array_column($rolled122()['attempted_next_returning_rows'], 'option_name'), ['plugin_seed:child', 'plugin_seed:child:child', 'next_plugin', 'next_plugin:child', 'next_plugin:child:child']],
    'attempted combined returning count' => [static fn (): mixed => count($rolled122()['attempted_returning_rows']), 11],
    'discarded returning count matches attempted yields' => [static fn (): mixed => $rolled122()['discarded_returning_count'], 11],
    'attempted changes record current and next phases' => [static fn (): mixed => $rolled122()['attempted_changes'], 11],
    'rollback changes reset to zero' => [static fn (): mixed => $rolled122()['changes'], 0],
    'savepoint preserved after rollback' => [static fn (): mixed => $rolled122()['savepoint_preserved'], true],
    'discarded rows include appended recursive rows' => [static fn (): mixed => count($rolled122()['discarded_rows']), 9],
    'discarded first changed row has savepoint image' => [static fn (): mixed => $rolled122()['discarded_rows'][0]['savepoint_row']['option_value'], 'seed-old'],
    'discarded appended row has null savepoint image' => [static fn (): mixed => $rolled122()['discarded_rows'][1]['savepoint_row'], null],
    'yield stream includes current and next phases' => [static fn (): mixed => array_values(array_unique(array_column($rolled122()['yield_stream'], 'phase'))), ['current', 'next']],
    'yield stream count matches attempted changes' => [static fn (): mixed => count($rolled122()['yield_stream']), 11],
    'yield stream records savepoint name' => [static fn (): mixed => array_values(array_unique(array_column($rolled122()['yield_stream'], 'savepoint'))), ['wp_trigger_batch']],
    'yield stream marks rollback after yield' => [static fn (): mixed => array_values(array_unique(array_column($rolled122()['yield_stream'], 'rolled_back_after_yield'))), [true]],
    'current first yield uses old conflict row' => [static fn (): mixed => $rolled122()['yield_stream'][0]['current_source_key'], 'plugin_seed'],
    'next first yield uses current recursive child source' => [static fn (): mixed => $rolled122()['yield_stream'][6]['current_source_key'], 'plugin_seed:child'],
    'next third yield inserts next source row' => [static fn (): mixed => $rolled122()['yield_stream'][8]['current_source_key'], 'next_plugin'],
    'attempted current trigger effects include recursive update and inserts' => [static fn (): mixed => array_column($rolled122()['current_attempt']['trigger_effects'], 'trigger'), ['wp_options_au_recursive_child', 'wp_options_ai_recursive_child', 'wp_options_ai_recursive_child', 'wp_options_ai_recursive_child']],
    'attempted next trigger effects include recursive update and inserts' => [static fn (): mixed => array_column($rolled122()['next_attempt']['trigger_effects'], 'trigger'), ['wp_options_au_recursive_child', 'wp_options_ai_recursive_child', 'wp_options_ai_recursive_child']],
    'dependencies include next118 engine marker' => [static fn (): mixed => in_array('sqlite-recursive-upsert-trigger-returning-current-source-next118', $rolled122()['dependencies'], true), true],
    'dependencies include next122 savepoint marker' => [static fn (): mixed => in_array('sqlite-trigger-savepoint-returning-recursive-current-source-next122', $rolled122()['dependencies'], true), true],
    'dependencies include yield before rollback marker' => [static fn (): mixed => in_array('sqlite-returning-yield-before-savepoint-rollback-recursive-trigger', $rolled122()['dependencies'], true), true],
    'dependencies include current source rollback marker' => [static fn (): mixed => in_array('sqlite-current-source-next-source-restored-by-rollback-to', $rolled122()['dependencies'], true), true],

    'release status records committed yield' => [static fn (): mixed => $released122()['status'], 'released-after-returning-yield'],
    'release keeps final attempted rows' => [static fn (): mixed => array_column($released122()['rows'], 'option_name'), array_column($rolled122()['attempted_rows'], 'option_name')],
    'release current returning rows committed' => [static fn (): mixed => array_column($released122()['current_returning_rows'], 'option_name'), ['plugin_seed', 'plugin_seed:child', 'plugin_seed:child:child', 'fresh_plugin', 'fresh_plugin:child', 'fresh_plugin:child:child']],
    'release next returning rows committed' => [static fn (): mixed => array_column($released122()['next_returning_rows'], 'option_name'), ['plugin_seed:child', 'plugin_seed:child:child', 'next_plugin', 'next_plugin:child', 'next_plugin:child:child']],
    'release combined returning count' => [static fn (): mixed => count($released122()['returning_rows']), 11],
    'release discarded returning count zero' => [static fn (): mixed => $released122()['discarded_returning_count'], 0],
    'release changes equal attempted changes' => [static fn (): mixed => $released122()['changes'], 11],
    'release savepoint not preserved' => [static fn (): mixed => $released122()['savepoint_preserved'], false],
    'release yield stream not rollback marked' => [static fn (): mixed => array_values(array_unique(array_column($released122()['yield_stream'], 'rolled_back_after_yield'))), [false]],

    'ignore rollback attempted changes still includes non-conflicting inserts' => [static fn (): mixed => $ignored122()['attempted_changes'], 8],
    'ignore rollback discards non-conflicting insert returning rows' => [static fn (): mixed => $ignored122()['discarded_returning_count'], 8],
    'ignore yield stream keeps skipped current phase edge' => [static fn (): mixed => $ignored122()['yield_stream'][0]['status'], 'skipped'],
    'ignore rows remain original after rollback' => [static fn (): mixed => $ignored122()['rows'], $rows122],
    'non recursive attempted returning count includes explicit next source rows' => [static fn (): mixed => count($nonRecursive122()['attempted_returning_rows']), 4],
    'non recursive attempted rows suppress trigger children but keep explicit source rows' => [static fn (): mixed => array_column($nonRecursive122()['attempted_rows'], 'option_name'), ['siteurl', 'plugin_seed', 'fresh_plugin', 'plugin_seed:child', 'next_plugin']],
    'bad savepoint throws' => [static fn (): mixed => $run122(['savepoint' => 'bad-name']), InvalidArgumentException::class],
];

foreach ($cases122 as $name => [$callback, $expected]) {
    $tests['trigger savepoint returning recursive current source next122 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
