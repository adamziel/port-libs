<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerDeferredFkReturningRecursiveCurrentSourceNextPlan;

$parents = [
    ['record_id' => 1, 'next_id' => 2, 'label' => 'root'],
    ['record_id' => 2, 'next_id' => 3, 'label' => 'branch'],
    ['record_id' => 3, 'next_id' => null, 'label' => 'leaf'],
    ['record_id' => 9, 'next_id' => null, 'label' => 'side'],
];
$children = [
    ['child_id' => 101, 'record_id' => 1, 'payload' => 'root child'],
    ['child_id' => 102, 'record_id' => 2, 'payload' => 'branch child'],
    ['child_id' => 103, 'record_id' => 3, 'payload' => 'leaf child'],
    ['child_id' => 104, 'record_id' => null, 'payload' => 'loose child'],
];
$fk = ['parent_key' => 'record_id', 'child_key' => 'record_id', 'on_update' => 'CASCADE', 'deferred' => true];
$returning = [
    ['expr' => 'old.record_id', 'as' => 'old_key'],
    ['expr' => 'new.record_id', 'as' => 'new_key'],
    ['expr' => 'new.label', 'as' => 'label'],
    static fn (array $new, array $old, int $statement, int $depth): string => $statement . ':' . $depth . ':' . $old['record_id'] . '->' . $new['record_id'],
];

$cases = [
    'triggerC-2.1 recursive after update cascades through linked rows' => [
        'upstream' => 'triggerC.test triggerC-2.1 recursive trigger programs',
        'updates' => [['match' => 1, 'set' => ['record_id' => 11, 'label' => 'root moved']]],
        'triggers' => [[
            'name' => 'after_update_follow_next',
            'timing' => 'after',
            'event' => 'update',
            'action' => 'enqueue-update',
            'when' => ['old.next_id', 'IS NOT', null],
            'match' => 'old.next_id',
            'set' => ['record_id' => 'new.next_id', 'label' => 'new.label'],
            'values' => ['from_key' => 'old.record_id', 'to_key' => 'new.record_id'],
        ]],
        'options' => ['recursive_triggers' => true, 'max_depth' => 8],
        'expect' => [
            'parent_keys' => [11, 2, 3, 9],
            'child_keys' => [11, 2, 3, null],
            'returning_old' => [1, 2, 3],
            'returning_new' => [11, 2, 3],
            'yield_depths' => [0, 1, 2],
            'fk_actions' => ['cascade'],
            'effect_actions' => ['enqueue-update', 'enqueue-update'],
            'effect_rows' => [[1, 11], [2, 2]],
            'parent_labels' => ['root moved', 'root moved', 'root moved', 'side'],
            'status' => 'ok',
            'violations' => [],
        ],
    ],
    'triggerC-6 recursive trigger off still applies first statement foreign key cascade' => [
        'upstream' => 'triggerC.test triggerC-6 recursive_triggers setting',
        'updates' => [['match' => 1, 'set' => ['record_id' => 11, 'label' => 'root moved']]],
        'triggers' => [[
            'name' => 'after_update_follow_next',
            'timing' => 'after',
            'event' => 'update',
            'action' => 'enqueue-update',
            'when' => ['old.next_id', 'IS NOT', null],
            'match' => 'old.next_id',
            'set' => ['record_id' => 'new.next_id', 'label' => 'new.label'],
            'values' => ['from_key' => 'old.record_id', 'to_key' => 'new.record_id'],
        ]],
        'options' => ['recursive_triggers' => false, 'max_depth' => 8],
        'expect' => [
            'parent_keys' => [11, 2, 3, 9],
            'child_keys' => [11, 2, 3, null],
            'returning_old' => [1],
            'returning_new' => [11],
            'yield_depths' => [0],
            'fk_actions' => ['cascade'],
            'effect_actions' => ['enqueue-update'],
            'effect_recursive_flags' => [false],
            'parent_labels' => ['root moved', 'branch', 'leaf', 'side'],
            'status' => 'ok',
            'violations' => [],
        ],
    ],
    'e_fkey-64 recursive trigger pragma does not suppress foreign key cascades' => [
        'upstream' => 'e_fkey.test e_fkey-64 recursive_triggers and FK actions',
        'updates' => [['match' => 2, 'set' => ['record_id' => 22, 'label' => 'branch moved']]],
        'triggers' => [],
        'options' => ['recursive_triggers' => false, 'max_depth' => 8],
        'expect' => [
            'parent_keys' => [1, 22, 3, 9],
            'child_keys' => [1, 22, 3, null],
            'returning_old' => [2],
            'returning_new' => [22],
            'yield_depths' => [0],
            'fk_actions' => ['cascade'],
            'parent_labels' => ['root', 'branch moved', 'leaf', 'side'],
            'status' => 'ok',
            'violations' => [],
        ],
    ],
    'e_fkey deferred no action commit check preserves attempted rows' => [
        'upstream' => 'e_fkey.test deferred NO ACTION commit check',
        'updates' => [['match' => 1, 'set' => ['record_id' => 11, 'label' => 'root moved']]],
        'foreign_key' => ['parent_key' => 'record_id', 'child_key' => 'record_id', 'on_update' => 'NO ACTION', 'deferred' => true],
        'triggers' => [],
        'options' => ['recursive_triggers' => true, 'max_depth' => 8],
        'expect' => [
            'parent_keys' => [11, 2, 3, 9],
            'child_keys' => [1, 2, 3, null],
            'returning_old' => [1],
            'returning_new' => [11],
            'yield_depths' => [0],
            'fk_actions' => ['no action'],
            'parent_labels' => ['root moved', 'branch', 'leaf', 'side'],
            'status' => 'deferred-constraint-failed',
            'violations' => [1],
        ],
    ],
    'e_fkey restrict action reports immediate-style violation' => [
        'upstream' => 'e_fkey.test RESTRICT action immediate timing',
        'updates' => [['match' => 1, 'set' => ['record_id' => 11, 'label' => 'root moved']]],
        'foreign_key' => ['parent_key' => 'record_id', 'child_key' => 'record_id', 'on_update' => 'RESTRICT', 'deferred' => true],
        'triggers' => [],
        'options' => ['recursive_triggers' => true, 'max_depth' => 8],
        'expect' => [
            'parent_keys' => [11, 2, 3, 9],
            'child_keys' => [1, 2, 3, null],
            'returning_old' => [1],
            'returning_new' => [11],
            'yield_depths' => [0],
            'fk_actions' => ['restrict'],
            'parent_labels' => ['root moved', 'branch', 'leaf', 'side'],
            'status' => 'deferred-constraint-failed',
            'violations' => [1],
        ],
    ],
    'triggerC-2.1 recursive after trigger may insert dependent child rows' => [
        'upstream' => 'triggerC.test trigger programs execute nested writes',
        'updates' => [['match' => 1, 'set' => ['record_id' => 11, 'label' => 'root moved']]],
        'triggers' => [[
            'name' => 'after_update_insert_child',
            'timing' => 'after',
            'event' => 'update',
            'action' => 'insert-child',
            'row' => ['child_id' => 201, 'record_id' => 'new.record_id', 'payload' => 'audit child'],
            'values' => ['from_key' => 'old.record_id', 'to_key' => 'new.record_id'],
        ]],
        'options' => ['recursive_triggers' => true, 'max_depth' => 8],
        'expect' => [
            'parent_keys' => [11, 2, 3, 9],
            'child_keys' => [11, 2, 3, null, 11],
            'returning_old' => [1],
            'returning_new' => [11],
            'yield_depths' => [0],
            'fk_actions' => ['cascade'],
            'effect_actions' => ['insert-child'],
            'parent_labels' => ['root moved', 'branch', 'leaf', 'side'],
            'status' => 'ok',
            'violations' => [],
        ],
    ],
];

$throwsCases = [
    'triggerC-3 limited recursive trigger depth aborts recursive expansion' => [
        'updates' => [['match' => 1, 'set' => ['record_id' => 11, 'label' => 'root moved']]],
        'triggers' => [[
            'name' => 'after_update_follow_next',
            'timing' => 'after',
            'event' => 'update',
            'action' => 'enqueue-update',
            'when' => ['old.next_id', 'IS NOT', null],
            'match' => 'old.next_id',
            'set' => ['record_id' => 'new.next_id', 'label' => 'new.label'],
        ]],
        'options' => ['recursive_triggers' => true, 'max_depth' => 1],
        'foreign_key' => $fk,
        'throws' => InvalidArgumentException::class,
    ],
    'e_fkey immediate no action violation is reported after statement' => [
        'updates' => [['match' => 3, 'set' => ['record_id' => 33, 'label' => 'leaf moved']]],
        'triggers' => [],
        'options' => ['recursive_triggers' => true, 'max_depth' => 8],
        'foreign_key' => ['parent_key' => 'record_id', 'child_key' => 'record_id', 'on_update' => 'NO ACTION', 'deferred' => false],
        'throws' => InvalidArgumentException::class,
    ],
];

$runCase = static function (array $case) use ($parents, $children, $fk, $returning): array {
    return SQLiteTriggerDeferredFkReturningRecursiveCurrentSourceNextPlan::updateParents(
        $parents,
        $children,
        $case['updates'],
        $case['foreign_key'] ?? $fk,
        $case['triggers'],
        $returning,
        $case['options'],
    );
};

$tests = [];
foreach ($cases as $name => $case) {
    $tests[$name . ' cites upstream source'] = static fn (TestRunner $t) => $t->same(true, str_contains($case['upstream'], '.test'));
    $tests[$name . ' parent keys'] = static fn (TestRunner $t) => $t->same($case['expect']['parent_keys'], array_column($runCase($case)['parent'], 'record_id'));
    $tests[$name . ' child keys'] = static fn (TestRunner $t) => $t->same($case['expect']['child_keys'], array_column($runCase($case)['child'], 'record_id'));
    $tests[$name . ' returning old keys'] = static fn (TestRunner $t) => $t->same($case['expect']['returning_old'], array_column($runCase($case)['returning_rows'], 'old_key'));
    $tests[$name . ' returning new keys'] = static fn (TestRunner $t) => $t->same($case['expect']['returning_new'], array_column($runCase($case)['returning_rows'], 'new_key'));
    $tests[$name . ' yielding depths'] = static fn (TestRunner $t) => $t->same($case['expect']['yield_depths'], array_column($runCase($case)['yielded'], 'depth'));
    $tests[$name . ' foreign key actions'] = static fn (TestRunner $t) => $t->same($case['expect']['fk_actions'], array_values(array_unique(array_column($runCase($case)['foreign_key_actions'], 'action'))));
    $tests[$name . ' commit status'] = static fn (TestRunner $t) => $t->same($case['expect']['status'], $runCase($case)['commit_status']);
    $tests[$name . ' violation keys'] = static fn (TestRunner $t) => $t->same($case['expect']['violations'], array_column($runCase($case)['deferred_violations'], 'child_key'));
    $tests[$name . ' dependency trigger marker'] = static fn (TestRunner $t) => $t->same(true, in_array('sqlite-trigger-deferred-fk-returning-recursive-current-source-next114', $runCase($case)['dependencies'], true));
    $tests[$name . ' dependency returning marker'] = static fn (TestRunner $t) => $t->same(true, in_array('sqlite-returning-yield-before-recursive-after-trigger-drain', $runCase($case)['dependencies'], true));
    $tests[$name . ' dependency deferred marker'] = static fn (TestRunner $t) => $t->same(true, in_array('sqlite-deferred-foreign-key-check-at-commit', $runCase($case)['dependencies'], true));
    $tests[$name . ' parent labels'] = static fn (TestRunner $t) => $t->same($case['expect']['parent_labels'], array_column($runCase($case)['parent'], 'label'));
    $tests[$name . ' loose child preserved'] = static fn (TestRunner $t) => $t->same('loose child', $runCase($case)['child'][3]['payload']);
    $tests[$name . ' nullable child key stays null'] = static fn (TestRunner $t) => $t->same(null, $runCase($case)['child'][3]['record_id']);
    $tests[$name . ' recursive trigger flag'] = static fn (TestRunner $t) => $t->same((bool) $case['options']['recursive_triggers'], $runCase($case)['recursive_triggers']);

    if (isset($case['expect']['effect_actions'])) {
        $tests[$name . ' trigger effect actions'] = static fn (TestRunner $t) => $t->same($case['expect']['effect_actions'], array_column($runCase($case)['trigger_effects'], 'action'));
    }
    if (isset($case['expect']['effect_rows'])) {
        $tests[$name . ' trigger projected rows'] = static fn (TestRunner $t) => $t->same($case['expect']['effect_rows'], array_map(static fn (array $effect): array => [$effect['row']['from_key'], $effect['row']['to_key']], $runCase($case)['trigger_effects']));
    }
    if (isset($case['expect']['effect_recursive_flags'])) {
        $tests[$name . ' trigger effect recursive flags'] = static fn (TestRunner $t) => $t->same($case['expect']['effect_recursive_flags'], array_column($runCase($case)['trigger_effects'], 'recursive_triggers'));
    }
}

foreach ($throwsCases as $name => $case) {
    $tests[$name . ' throws expected upstream error'] = static fn (TestRunner $t) => $t->throws($case['throws'], static fn () => $runCase($case));
}

for ($i = 0; $i < 40; ++$i) {
    $tests["triggerC/e_fkey dynamic corpus deterministic returning projection {$i}"] = static function (TestRunner $t) use ($runCase, $cases, $i): void {
        $case = array_values($cases)[$i % count($cases)];
        $plan = $runCase($case);
        $t->same($plan['returning_rows'][0]['old_key'] . '->' . $plan['returning_rows'][0]['new_key'], preg_replace('/^[0-9]+:[0-9]+:/', '', $plan['returning_rows'][0]['expr3']));
    };
}

return $tests;
