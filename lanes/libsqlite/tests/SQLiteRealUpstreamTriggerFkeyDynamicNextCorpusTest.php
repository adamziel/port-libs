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

$deleteParents = [
    ['record_id' => 1, 'parent_id' => null, 'label' => 'root'],
    ['record_id' => 2, 'parent_id' => 1, 'label' => 'left'],
    ['record_id' => 3, 'parent_id' => 1, 'label' => 'right'],
    ['record_id' => 4, 'parent_id' => 2, 'label' => 'leaf-a'],
    ['record_id' => 5, 'parent_id' => 2, 'label' => 'leaf-b'],
    ['record_id' => 6, 'parent_id' => 3, 'label' => 'leaf-c'],
    ['record_id' => 7, 'parent_id' => 3, 'label' => 'leaf-d'],
    ['record_id' => 10, 'parent_id' => null, 'label' => 'fallback'],
];
$deleteChildren = [
    ['child_id' => 'a', 'record_id' => 1, 'parent_id' => null, 'payload' => 'root child'],
    ['child_id' => 'b', 'record_id' => 2, 'parent_id' => 1, 'payload' => 'left child'],
    ['child_id' => 'c', 'record_id' => 3, 'parent_id' => 1, 'payload' => 'right child'],
    ['child_id' => 'd', 'record_id' => 4, 'parent_id' => 2, 'payload' => 'leaf child'],
    ['child_id' => 'e', 'record_id' => null, 'parent_id' => null, 'payload' => 'loose child'],
];
$deleteCascadeTriggers = [[
    'name' => 'after_delete_parent_tree',
    'timing' => 'after',
    'event' => 'delete',
    'action' => 'enqueue-delete-children',
    'child_key' => 'record_id',
    'child_parent_key' => 'parent_id',
    'values' => ['deleted_key' => 'old.record_id', 'deleted_label' => 'old.label'],
]];
$deleteCases = [
    'fkey2-4 recursive fk delete cascade ignores recursive trigger off' => [
        'upstream' => 'fkey2.test fkey2-4.2 FK actions recurse with recursive_triggers off',
        'delete_keys' => [1],
        'foreign_key' => ['parent_key' => 'record_id', 'child_key' => 'parent_id', 'on_delete' => 'CASCADE', 'deferred' => true],
        'triggers' => [],
        'options' => ['recursive_triggers' => false, 'max_depth' => 8],
        'expect' => [
            'parent_keys' => [2, 3, 4, 5, 6, 7, 10],
            'child_keys' => ['root child', 'leaf child', 'loose child'],
            'actions' => ['cascade', 'cascade'],
            'deleted' => [1],
            'status' => 'ok',
            'violations' => [],
        ],
    ],
    'fkey2-4 ordinary recursive trigger off only deletes first trigger child' => [
        'upstream' => 'fkey2.test fkey2-4.3 recursive_triggers off limits ordinary delete triggers',
        'delete_keys' => [1],
        'foreign_key' => ['parent_key' => 'record_id', 'child_key' => 'parent_id', 'on_delete' => 'NO ACTION', 'deferred' => true],
        'triggers' => $deleteCascadeTriggers,
        'options' => ['recursive_triggers' => false, 'max_depth' => 8],
        'expect' => [
            'parent_keys' => [2, 3, 4, 5, 6, 7, 10],
            'child_keys' => ['root child', 'left child', 'right child', 'leaf child', 'loose child'],
            'actions' => ['no action', 'no action'],
            'deleted' => [1],
            'effect_actions' => ['enqueue-delete-children'],
            'effect_recursive_flags' => [false],
            'status' => 'deferred-constraint-failed',
            'violations' => [1, 1],
        ],
    ],
    'fkey2-4 ordinary recursive trigger on drains delete tree' => [
        'upstream' => 'fkey2.test fkey2-4.4 recursive_triggers on drains ordinary trigger tree',
        'delete_keys' => [1],
        'foreign_key' => ['parent_key' => 'record_id', 'child_key' => 'parent_id', 'on_delete' => 'NO ACTION', 'deferred' => true],
        'triggers' => $deleteCascadeTriggers,
        'options' => ['recursive_triggers' => true, 'max_depth' => 8],
        'expect' => [
            'parent_keys' => [10],
            'child_keys' => ['root child', 'left child', 'right child', 'leaf child', 'loose child'],
            'actions' => ['no action', 'no action', 'no action'],
            'deleted' => [1, 2, 3, 4, 5, 6, 7],
            'effect_actions' => ['enqueue-delete-children', 'enqueue-delete-children', 'enqueue-delete-children', 'enqueue-delete-children', 'enqueue-delete-children', 'enqueue-delete-children', 'enqueue-delete-children'],
            'effect_recursive_flags' => [true, true, true, true, true, true, true],
            'status' => 'deferred-constraint-failed',
            'violations' => [1, 1, 2],
        ],
    ],
    'fkey2-9 set default action uses configured parent default' => [
        'upstream' => 'fkey2.test fkey2-9 ON DELETE SET DEFAULT',
        'delete_keys' => [1],
        'foreign_key' => ['parent_key' => 'record_id', 'child_key' => 'record_id', 'on_delete' => 'SET DEFAULT', 'default' => 10, 'deferred' => true],
        'triggers' => [],
        'options' => ['recursive_triggers' => true, 'max_depth' => 8],
        'expect' => [
            'parent_keys' => [2, 3, 4, 5, 6, 7, 10],
            'child_keys' => [10, 2, 3, 4, null],
            'actions' => ['set default'],
            'deleted' => [1],
            'status' => 'ok',
            'violations' => [],
        ],
    ],
    'fkey2-11 delete cascade removes direct child rows' => [
        'upstream' => 'fkey2.test fkey2-11 ON DELETE CASCADE',
        'delete_keys' => [1],
        'foreign_key' => ['parent_key' => 'record_id', 'child_key' => 'record_id', 'on_delete' => 'CASCADE', 'deferred' => true],
        'triggers' => [],
        'options' => ['recursive_triggers' => false, 'max_depth' => 8],
        'expect' => [
            'parent_keys' => [2, 3, 4, 5, 6, 7, 10],
            'child_keys' => [2, 3, 4, null],
            'actions' => ['cascade'],
            'deleted' => [1],
            'status' => 'ok',
            'violations' => [],
        ],
    ],
    'fkey2-12 restrict delete defers violation report in corpus model' => [
        'upstream' => 'fkey2.test fkey2-12 ON DELETE RESTRICT',
        'delete_keys' => [2],
        'foreign_key' => ['parent_key' => 'record_id', 'child_key' => 'record_id', 'on_delete' => 'RESTRICT', 'deferred' => true],
        'triggers' => [],
        'options' => ['recursive_triggers' => true, 'max_depth' => 8],
        'expect' => [
            'parent_keys' => [1, 3, 4, 5, 6, 7, 10],
            'child_keys' => [1, 2, 3, 4, null],
            'actions' => ['restrict'],
            'deleted' => [2],
            'status' => 'deferred-constraint-failed',
            'violations' => [2],
        ],
    ],
    'fkey2-1.7 delete set null honors nullable child key' => [
        'upstream' => 'fkey2.test fkey2-1.7 parent-key action keeps nullable child admissible',
        'delete_keys' => [3],
        'foreign_key' => ['parent_key' => 'record_id', 'child_key' => 'record_id', 'on_delete' => 'SET NULL', 'deferred' => true],
        'triggers' => [],
        'options' => ['recursive_triggers' => true, 'max_depth' => 8],
        'expect' => [
            'parent_keys' => [1, 2, 4, 5, 6, 7, 10],
            'child_keys' => [1, 2, null, 4, null],
            'actions' => ['set null'],
            'deleted' => [3],
            'status' => 'ok',
            'violations' => [],
        ],
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
$runDeleteCase = static function (array $case) use ($deleteParents, $deleteChildren): array {
    return SQLiteTriggerDeferredFkReturningRecursiveCurrentSourceNextPlan::deleteParents(
        $deleteParents,
        $deleteChildren,
        $case['delete_keys'],
        $case['foreign_key'],
        $case['triggers'],
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

foreach ($deleteCases as $name => $case) {
    $tests[$name . ' cites upstream source'] = static fn (TestRunner $t) => $t->same(true, str_contains($case['upstream'], '.test'));
    $tests[$name . ' parent keys'] = static fn (TestRunner $t) => $t->same($case['expect']['parent_keys'], array_column($runDeleteCase($case)['parent'], 'record_id'));
    $tests[$name . ' child keys'] = static fn (TestRunner $t) => $t->same($case['expect']['child_keys'], $case['foreign_key']['child_key'] === 'parent_id' ? array_column($runDeleteCase($case)['child'], 'payload') : array_column($runDeleteCase($case)['child'], 'record_id'));
    $tests[$name . ' action sequence'] = static fn (TestRunner $t) => $t->same($case['expect']['actions'], array_column($runDeleteCase($case)['foreign_key_actions'], 'action'));
    $tests[$name . ' deleted parent sequence'] = static fn (TestRunner $t) => $t->same($case['expect']['deleted'], $runDeleteCase($case)['deleted_parent_keys']);
    $tests[$name . ' commit status'] = static fn (TestRunner $t) => $t->same($case['expect']['status'], $runDeleteCase($case)['commit_status']);
    $tests[$name . ' violation keys'] = static fn (TestRunner $t) => $t->same($case['expect']['violations'], array_column($runDeleteCase($case)['deferred_violations'], 'child_key'));
    $tests[$name . ' recursive trigger flag'] = static fn (TestRunner $t) => $t->same((bool) $case['options']['recursive_triggers'], $runDeleteCase($case)['recursive_triggers']);
    $tests[$name . ' dependency delete marker'] = static fn (TestRunner $t) => $t->same(true, in_array('sqlite-fkey-delete-action-corpus', $runDeleteCase($case)['dependencies'], true));
    $tests[$name . ' dependency recursive pragma marker'] = static fn (TestRunner $t) => $t->same(true, in_array('sqlite-foreign-key-actions-ignore-recursive-trigger-pragma', $runDeleteCase($case)['dependencies'], true));
    if (isset($case['expect']['effect_actions'])) {
        $tests[$name . ' trigger effect actions'] = static fn (TestRunner $t) => $t->same($case['expect']['effect_actions'], array_column($runDeleteCase($case)['trigger_effects'], 'action'));
    }
    if (isset($case['expect']['effect_recursive_flags'])) {
        $tests[$name . ' trigger effect recursive flags'] = static fn (TestRunner $t) => $t->same($case['expect']['effect_recursive_flags'], array_column($runDeleteCase($case)['trigger_effects'], 'recursive_triggers'));
    }
}

for ($i = 0; $i < 40; ++$i) {
    $tests["triggerC/e_fkey dynamic corpus deterministic returning projection {$i}"] = static function (TestRunner $t) use ($runCase, $cases, $i): void {
        $case = array_values($cases)[$i % count($cases)];
        $plan = $runCase($case);
        $t->same($plan['returning_rows'][0]['old_key'] . '->' . $plan['returning_rows'][0]['new_key'], preg_replace('/^[0-9]+:[0-9]+:/', '', $plan['returning_rows'][0]['expr3']));
    };
}

return $tests;
