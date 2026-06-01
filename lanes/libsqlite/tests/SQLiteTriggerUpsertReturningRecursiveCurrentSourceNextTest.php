<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerUpsertReturningRecursiveCurrentSourceNextPlan;

$rows145 = [
    ['key_name' => 'base_url', 'key_value' => 'https://old.test', 'revision' => 1, 'depth' => 0, 'load_policy' => 'yes'],
    ['key_name' => 'module_seed', 'key_value' => 'seed-old', 'revision' => 4, 'depth' => 1, 'load_policy' => 'no'],
];

$assign145 = [
    'key_value' => static fn (array $old, array $incoming): mixed => $incoming['key_value'],
    'revision' => static fn (array $old, array $incoming): int => (int) $old['revision'] + (int) $incoming['revision'],
    'depth' => static fn (array $old, array $incoming): mixed => $incoming['depth'],
    'load_policy' => static fn (array $old, array $incoming): mixed => $incoming['load_policy'],
];

$triggers145 = [
    [
        'name' => 'app_settings_ai_recursive_child_145',
        'timing' => 'after',
        'event' => 'insert',
        'action' => 'upsert-parent',
        'when' => ['new.depth', '<', 3],
        'row' => [
            'key_name' => ['concat' => ['new.key_name', '_child']],
            'key_value' => ['concat' => ['new.key_value', ':child']],
            'revision' => 1,
            'depth' => ['add' => ['new.depth', 1]],
            'load_policy' => 'new.load_policy',
        ],
        'values' => ['name' => 'new.key_name', 'depth' => 'new.depth'],
    ],
    [
        'name' => 'app_settings_au_recursive_child_145',
        'timing' => 'after',
        'event' => 'update',
        'action' => 'upsert-parent',
        'when' => ['new.depth', '<', 3],
        'row' => [
            'key_name' => ['concat' => ['new.key_name', '_child']],
            'key_value' => ['concat' => ['new.key_value', ':child']],
            'revision' => 1,
            'depth' => ['add' => ['new.depth', 1]],
            'load_policy' => 'new.load_policy',
        ],
        'values' => ['name' => 'new.key_name', 'depth' => 'new.depth'],
    ],
];

$returning145 = [
    'key_name',
    ['expr' => 'new.key_value', 'as' => 'value'],
    ['expr' => 'excluded.key_value', 'as' => 'incoming_value'],
    static fn (array $new, ?array $old, array $incoming, string $event, int $depth, ?string $trigger): mixed => $old['key_value'] ?? null,
    ['expr' => 'event', 'as' => 'event_name'],
    ['expr' => 'depth', 'as' => 'trigger_depth'],
    ['expr' => 'trigger', 'as' => 'source_trigger'],
];

$run145 = static fn (array $options = [], array $current = null, array $next = null): array => SQLiteTriggerUpsertReturningRecursiveCurrentSourceNextPlan::execute(
    $rows145,
    $current ?? [
        ['key_name' => 'module_seed', 'key_value' => 'seed-current', 'revision' => 2, 'depth' => 1, 'load_policy' => 'yes'],
        ['key_name' => 'fresh_module', 'key_value' => 'fresh-current', 'revision' => 1, 'depth' => 1, 'load_policy' => 'no'],
    ],
    $next ?? [
        ['key_name' => 'module_seed_child', 'key_value' => 'seed-child-next', 'revision' => 3, 'depth' => 2, 'load_policy' => 'yes'],
        ['key_name' => 'fresh_module', 'key_value' => 'fresh-next', 'revision' => 5, 'depth' => 1, 'load_policy' => 'yes'],
    ],
    ['key_name'],
    $assign145,
    $triggers145,
    $options + [
        'savepoint' => 'app_import_recursive_145',
        'current_source' => 'main@cookie-145',
        'next_source' => 'main@cookie-146',
        'returning' => $returning145,
    ],
);

$released145 = static fn (): array => $run145();
$rolled145 = static fn (): array => $run145(['rollback_on_returning_key' => ['fresh_module_child']]);
$ignore145 = static fn (): array => $run145(['conflict_action' => 'ignore']);
$recursiveOff145 = static fn (): array => $run145(['recursive_triggers' => false]);

$cases145 = [
    'released status' => [static fn (): mixed => $released145()['status'], 'recursive-upsert-returning-current-source-released-next145'],
    'released savepoint token' => [static fn (): mixed => $released145()['savepoint'], 'app_import_recursive_145'],
    'released current source token' => [static fn (): mixed => $released145()['current_source'], 'main@cookie-145'],
    'released next source token' => [static fn (): mixed => $released145()['next_source'], 'main@cookie-146'],
    'released does not roll back current' => [static fn (): mixed => $released145()['current_rolled_back'], false],
    'released next starts from current source' => [static fn (): mixed => $released145()['next_started_from'], 'current-source'],
    'released current returning names' => [static fn (): mixed => array_column(array_column($released145()['current_returning_rows'], 'returning'), 'key_name'), ['module_seed', 'module_seed_child', 'module_seed_child_child', 'fresh_module', 'fresh_module_child', 'fresh_module_child_child']],
    'released next returning names' => [static fn (): mixed => array_column(array_column($released145()['next_returning_rows'], 'returning'), 'key_name'), ['module_seed_child', 'module_seed_child_child', 'fresh_module', 'fresh_module_child', 'fresh_module_child_child']],
    'released combined returning count' => [static fn (): mixed => count($released145()['returning_rows']), 11],
    'released current rows include recursive fresh child' => [static fn (): mixed => in_array('fresh_module_child', array_column($released145()['current_attempt_rows'], 'key_name'), true), true],
    'released next rows update fresh module before recursive child' => [static fn (): mixed => $released145()['next_rows'][4]['key_value'], 'fresh-next'],
    'released current first event update' => [static fn (): mixed => $released145()['current_returning_rows'][0]['returning']['event_name'], 'update'],
    'released current recursive child event insert' => [static fn (): mixed => $released145()['current_returning_rows'][1]['returning']['event_name'], 'insert'],
    'released next first event updates current recursive child' => [static fn (): mixed => $released145()['next_returning_rows'][0]['returning']['event_name'], 'update'],
    'released next first old value is current recursive child' => [static fn (): mixed => $released145()['next_returning_rows'][0]['returning']['expr3'], 'seed-current:child'],
    'released attempted current changes' => [static fn (): mixed => $released145()['attempted_current_changes'], 6],
    'released current changes' => [static fn (): mixed => $released145()['current_changes'], 6],
    'released next changes' => [static fn (): mixed => $released145()['next_changes'], 5],
    'released committed changes' => [static fn (): mixed => $released145()['committed_changes'], 11],
    'released yield stream source order' => [static fn (): mixed => array_values(array_unique(array_column($released145()['yield_stream'], 'source_token'))), ['main@cookie-145', 'main@cookie-146']],
    'released current trigger depths' => [static fn (): mixed => array_values(array_unique(array_column($released145()['current_yield_stream'], 'depth'))), [0, 1, 2]],
    'released summary new keys' => [static fn (): mixed => $released145()['recursive_summary']['attempted_current_new_keys'], ['module_seed_child', 'module_seed_child_child', 'fresh_module', 'fresh_module_child', 'fresh_module_child_child']],
    'released summary next new keys empty because all next rows update or recurse existing descendants' => [static fn (): mixed => $released145()['recursive_summary']['next_new_keys'], []],
    'released dependency marker' => [static fn (): mixed => in_array('sqlite-trigger-upsert-returning-recursive-current-source-next145', $released145()['dependencies'], true), true],

    'rolled status' => [static fn (): mixed => $rolled145()['status'], 'recursive-upsert-returning-current-source-rolled-back-next145'],
    'rolled current returning suppressed' => [static fn (): mixed => $rolled145()['current_returning_rows'], []],
    'rolled attempted current returning retained' => [static fn (): mixed => array_column(array_column($rolled145()['attempted_current_returning_rows'], 'returning'), 'key_name'), ['module_seed', 'module_seed_child', 'module_seed_child_child', 'fresh_module', 'fresh_module_child', 'fresh_module_child_child']],
    'rolled barrier key' => [static fn (): mixed => $rolled145()['rollback_barrier']['returning_key'], 'fresh_module_child'],
    'rolled barrier depth' => [static fn (): mixed => $rolled145()['rollback_barrier']['depth'], 1],
    'rolled next starts from savepoint' => [static fn (): mixed => $rolled145()['next_started_from'], 'savepoint'],
    'rolled next start rows are savepoint rows' => [static fn (): mixed => array_column($rolled145()['next_start_rows'], 'key_name'), ['base_url', 'module_seed']],
    'rolled current changes reset' => [static fn (): mixed => $rolled145()['current_changes'], 0],
    'rolled attempted current changes still counted' => [static fn (): mixed => $rolled145()['attempted_current_changes'], 6],
    'rolled next changes' => [static fn (): mixed => $rolled145()['next_changes'], 5],
    'rolled committed changes' => [static fn (): mixed => $rolled145()['committed_changes'], 5],
    'rolled next returning names replay from savepoint' => [static fn (): mixed => array_column(array_column($rolled145()['next_returning_rows'], 'returning'), 'key_name'), ['module_seed_child', 'module_seed_child_child', 'fresh_module', 'fresh_module_child', 'fresh_module_child_child']],
    'rolled next first event inserts replayed child' => [static fn (): mixed => $rolled145()['next_returning_rows'][0]['returning']['event_name'], 'insert'],
    'rolled replayed child has no old value' => [static fn (): mixed => $rolled145()['next_returning_rows'][0]['returning']['expr3'], null],
    'rolled summary replay flag proves barrier key replayed by next source' => [static fn (): mixed => $rolled145()['recursive_summary']['next_replayed_current_key'], true],
    'rolled yield stream contains only next source' => [static fn (): mixed => array_values(array_unique(array_column($rolled145()['yield_stream'], 'source_token'))), ['main@cookie-146']],
    'rolled attempted current stream still names current source' => [static fn (): mixed => array_values(array_unique(array_column($rolled145()['attempted_current_yield_stream'], 'source_token'))), ['main@cookie-145']],
    'rolled summary current returning attempts' => [static fn (): mixed => $rolled145()['recursive_summary']['current_returning_attempts'], 6],
    'rolled summary next returning attempts' => [static fn (): mixed => $rolled145()['recursive_summary']['next_returning_attempts'], 5],

    'ignore suppresses current conflict returning but inserts fresh chain' => [static fn (): mixed => array_column(array_column($ignore145()['current_returning_rows'], 'returning'), 'key_name'), ['fresh_module', 'fresh_module_child', 'fresh_module_child_child']],
    'ignore skipped conflict is yielded but invisible' => [static fn (): mixed => $ignore145()['attempted_current_yield_stream'][0]['returning_visible'], false],
    'ignore committed changes' => [static fn (): mixed => $ignore145()['committed_changes'], 5],
    'ignore next starts from current source' => [static fn (): mixed => $ignore145()['next_started_from'], 'current-source'],
    'recursive off current statement only' => [static fn (): mixed => array_column(array_column($recursiveOff145()['current_returning_rows'], 'returning'), 'key_name'), ['module_seed', 'fresh_module']],
    'recursive off next statement only' => [static fn (): mixed => array_column(array_column($recursiveOff145()['next_returning_rows'], 'returning'), 'key_name'), ['module_seed_child', 'fresh_module']],
    'recursive off current trigger suppressed' => [static fn (): mixed => $recursiveOff145()['current']['trigger_effects'][0]['result'], 'recursive-suppressed'],
    'recursive off committed changes' => [static fn (): mixed => $recursiveOff145()['committed_changes'], 4],

    'bad savepoint throws' => [static fn (): mixed => $run145(['savepoint' => 'bad savepoint']), InvalidArgumentException::class],
    'bad current source throws' => [static fn (): mixed => $run145(['current_source' => 'bad source']), InvalidArgumentException::class],
    'bad next source throws' => [static fn (): mixed => $run145(['next_source' => 'bad source']), InvalidArgumentException::class],
    'bad rollback key throws' => [static fn (): mixed => $run145(['rollback_on_returning_key' => ['bad key']]), InvalidArgumentException::class],
    'empty current rows throw' => [static fn (): mixed => $run145([], []), InvalidArgumentException::class],
    'empty next rows throw' => [static fn (): mixed => $run145([], null, []), InvalidArgumentException::class],
    'malformed current rows throw' => [static fn (): mixed => SQLiteTriggerUpsertReturningRecursiveCurrentSourceNextPlan::execute($rows145, ['bad' => ['key_name' => 'x']], [['key_name' => 'y']], ['key_name'], $assign145, $triggers145), InvalidArgumentException::class],
    'missing unique column throws' => [static fn (): mixed => $run145([], [['key_value' => 'missing']]), InvalidArgumentException::class],
    'max depth throws' => [static fn (): mixed => $run145(['max_depth' => 1]), RuntimeException::class],
];

foreach ($cases145 as $name => [$callback, $expected]) {
    $tests['trigger upsert returning recursive current source next145 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
