<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerBeforeCascadeSavepointPlan;

$parents = [
    ['option_id' => 1, 'option_name' => 'active_plugins', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'rewrite_rules', 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'theme_mods', 'autoload' => 'no'],
];
$meta = [
    ['meta_id' => 10, 'option_id' => 1, 'meta_key' => 'autoload'],
    ['meta_id' => 11, 'option_id' => 1, 'meta_key' => 'checksum'],
    ['meta_id' => 12, 'option_id' => 2, 'meta_key' => 'autoload'],
    ['meta_id' => 13, 'option_id' => null, 'meta_key' => 'loose'],
    ['meta_id' => 14, 'option_id' => 3, 'meta_key' => 'theme'],
];
$details = [
    ['detail_id' => 100, 'meta_id' => 10, 'label' => 'autoload-before'],
    ['detail_id' => 101, 'meta_id' => 10, 'label' => 'autoload-after'],
    ['detail_id' => 102, 'meta_id' => 11, 'label' => 'checksum-before'],
    ['detail_id' => 103, 'meta_id' => 12, 'label' => 'rewrite-before'],
    ['detail_id' => 104, 'meta_id' => null, 'label' => 'loose'],
    ['detail_id' => 105, 'meta_id' => 14, 'label' => 'theme'],
];
$optionToMeta = ['parent_key' => 'option_id', 'child_key' => 'option_id', 'on_delete' => 'CASCADE'];
$metaToDetail = ['parent_key' => 'meta_id', 'child_key' => 'meta_id', 'on_delete' => 'CASCADE'];
$beforeAudit = [[
    'name' => 'wp_options_bd_audit',
    'timing' => 'before',
    'event' => 'delete',
    'action' => 'insert-audit',
    'audit' => ['phase' => 'before', 'option' => 'old.option_name', 'option_id' => 'old.option_id', 'child_count' => 'child_count', 'detail_count' => 'grandchild_count'],
]];
$beforeMoveChildren = [[
    'name' => 'wp_options_bd_rehome_meta',
    'timing' => 'before',
    'event' => 'delete',
    'action' => 'update-child-key',
    'match' => 'old.parent_key',
    'set_child_key' => 2,
]];
$beforeDeleteChildren = [[
    'name' => 'wp_options_bd_delete_meta',
    'timing' => 'before',
    'event' => 'delete',
    'action' => 'delete-child',
    'match' => 'old.parent_key',
]];
$beforeMoveDetails = [[
    'name' => 'wp_options_bd_rehome_detail',
    'timing' => 'before',
    'event' => 'delete',
    'action' => 'update-grandchild-key',
    'match' => 10,
    'set_grandchild_key' => 12,
]];
$raiseRollback = [[
    'name' => 'wp_options_bd_guard_required',
    'timing' => 'before',
    'event' => 'delete',
    'action' => 'raise',
    'raise' => 'rollback',
    'reason' => 'protected-option-before-cascade',
    'when' => ['old.option_name', '=', 'active_plugins'],
]];
$raiseIgnore = [[
    'name' => 'wp_options_bd_ignore_theme',
    'timing' => 'before',
    'event' => 'delete',
    'action' => 'raise',
    'raise' => 'ignore',
    'reason' => 'skip-theme-mods',
    'when' => ['old.option_name', '=', 'theme_mods'],
]];

$run = static fn (
    array $deleteKeys = [['option_id' => 1]],
    array $triggers = [],
    ?array $grandchildFk = null,
    ?array $fk = null,
    string $savepoint = 'before_cascade_import',
    string $conflictAction = 'rollback',
): array => SQLiteTriggerBeforeCascadeSavepointPlan::deleteParents(
    $savepoint,
    $parents,
    $meta,
    $details,
    $deleteKeys,
    $fk ?? $optionToMeta,
    $triggers,
    $grandchildFk,
    $conflictAction,
);

$cases = [
    'before cascade current next35 deletes parent row' => [static fn (): mixed => array_column($run()['parent'], 'option_id'), [2, 3]],
    'before cascade current next35 current parent mirrors result' => [static fn (): mixed => array_column($run()['current_parent'], 'option_id'), [2, 3]],
    'before cascade current next35 attempted parent mirrors result' => [static fn (): mixed => array_column($run()['attempted_parent'], 'option_id'), [2, 3]],
    'before cascade current next35 cascades matching children' => [static fn (): mixed => array_column($run()['child'], 'meta_id'), [12, 13, 14]],
    'before cascade current next35 records child cascade actions' => [static fn (): mixed => array_column($run()['cascade_actions'], 'action'), ['cascade-delete-child', 'cascade-delete-child']],
    'before cascade current next35 counts parent and child deletes' => [static fn (): mixed => $run()['changes'], 3],
    'before cascade current next35 no rollback by default' => [static fn (): mixed => $run()['rolled_back'], false],
    'before cascade current next35 savepoint not preserved after commit path' => [static fn (): mixed => $run()['savepoint_preserved'], false],
    'before cascade current next35 dependencies include savepoint yield' => [static fn (): mixed => in_array('sqlite-savepoint-current-next-yield', $run()['dependencies'], true), true],
    'before cascade current next35 missing parent is no op' => [static fn (): mixed => array_column($run([['option_id' => 404]])['parent'], 'option_id'), [1, 2, 3]],

    'before audit fires before cascade' => [static fn (): mixed => $run([['option_id' => 1]], $beforeAudit)['audit'][0]['phase'], 'before'],
    'before audit reads old parent name' => [static fn (): mixed => $run([['option_id' => 1]], $beforeAudit)['audit'][0]['option'], 'active_plugins'],
    'before audit sees all children before cascade' => [static fn (): mixed => $run([['option_id' => 1]], $beforeAudit)['audit'][0]['child_count'], 5],
    'before audit sees all details before cascade' => [static fn (): mixed => $run([['option_id' => 1]], $beforeAudit)['audit'][0]['detail_count'], 6],
    'before audit records trigger effect' => [static fn (): mixed => $run([['option_id' => 1]], $beforeAudit)['trigger_effects'][0]['trigger'], 'wp_options_bd_audit'],
    'before audit changes include audit row' => [static fn (): mixed => $run([['option_id' => 1]], $beforeAudit)['changes'], 4],
    'before audit effect precedes cascade actions' => [static fn (): mixed => $run([['option_id' => 1]], $beforeAudit)['trigger_effects'][0]['action'], 'insert-audit'],

    'before move child prevents child cascade' => [static fn (): mixed => array_column($run([['option_id' => 1]], $beforeMoveChildren)['child'], 'option_id'), [2, 2, 2, null, 3]],
    'before move child has no cascade actions' => [static fn (): mixed => $run([['option_id' => 1]], $beforeMoveChildren)['cascade_actions'], []],
    'before move child records updated rows' => [static fn (): mixed => $run([['option_id' => 1]], $beforeMoveChildren)['trigger_effects'][0]['rows'], 2],
    'before move child records matched key' => [static fn (): mixed => $run([['option_id' => 1]], $beforeMoveChildren)['trigger_effects'][0]['matched_child_key'], 1],
    'before move child changes include update and parent delete' => [static fn (): mixed => $run([['option_id' => 1]], $beforeMoveChildren)['changes'], 3],
    'before move child keeps details because cascade was avoided' => [static fn (): mixed => array_column($run([['option_id' => 1]], $beforeMoveChildren, $metaToDetail)['grandchild'], 'detail_id'), [100, 101, 102, 103, 104, 105]],

    'before delete child consumes children before cascade' => [static fn (): mixed => array_column($run([['option_id' => 1]], $beforeDeleteChildren)['child'], 'meta_id'), [12, 13, 14]],
    'before delete child leaves no cascade actions' => [static fn (): mixed => $run([['option_id' => 1]], $beforeDeleteChildren)['cascade_actions'], []],
    'before delete child records deleted rows' => [static fn (): mixed => $run([['option_id' => 1]], $beforeDeleteChildren)['trigger_effects'][0]['rows'], 2],
    'before delete child changes include trigger deletes' => [static fn (): mixed => $run([['option_id' => 1]], $beforeDeleteChildren)['changes'], 3],

    'grandchild cascade removes details after child cascade' => [static fn (): mixed => array_column($run([['option_id' => 1]], [], $metaToDetail)['grandchild'], 'detail_id'), [103, 104, 105]],
    'grandchild cascade records child then details' => [static fn (): mixed => array_column($run([['option_id' => 1]], [], $metaToDetail)['cascade_actions'], 'action'), ['cascade-delete-child', 'cascade-delete-grandchild', 'cascade-delete-grandchild', 'cascade-delete-child', 'cascade-delete-grandchild']],
    'grandchild cascade counts all deletes' => [static fn (): mixed => $run([['option_id' => 1]], [], $metaToDetail)['changes'], 6],
    'before moved grandchild avoids first child detail cascade' => [static fn (): mixed => array_column($run([['option_id' => 1]], $beforeMoveDetails, $metaToDetail)['grandchild'], 'meta_id'), [12, 12, 12, null, 14]],
    'before moved grandchild records updated details' => [static fn (): mixed => $run([['option_id' => 1]], $beforeMoveDetails, $metaToDetail)['trigger_effects'][0]['rows'], 2],
    'before moved grandchild still cascades checksum detail' => [static fn (): mixed => array_column($run([['option_id' => 1]], $beforeMoveDetails, $metaToDetail)['cascade_actions'], 'action'), ['cascade-delete-child', 'cascade-delete-child', 'cascade-delete-grandchild']],

    'raise rollback restores current parents' => [static fn (): mixed => array_column($run([['option_id' => 1]], $raiseRollback)['current_parent'], 'option_id'), [1, 2, 3]],
    'raise rollback restores current children' => [static fn (): mixed => array_column($run([['option_id' => 1]], $raiseRollback)['current_child'], 'meta_id'), [10, 11, 12, 13, 14]],
    'raise rollback restores current grandchildren' => [static fn (): mixed => array_column($run([['option_id' => 1]], $raiseRollback)['current_grandchild'], 'detail_id'), [100, 101, 102, 103, 104, 105]],
    'raise rollback marks rolled back' => [static fn (): mixed => $run([['option_id' => 1]], $raiseRollback)['rolled_back'], true],
    'raise rollback marks aborted' => [static fn (): mixed => $run([['option_id' => 1]], $raiseRollback)['aborted'], true],
    'raise rollback records savepoint scope' => [static fn (): mixed => $run([['option_id' => 1]], $raiseRollback)['rollback_scope'], 'savepoint'],
    'raise rollback records reason' => [static fn (): mixed => $run([['option_id' => 1]], $raiseRollback)['rollback_reason'], 'protected-option-before-cascade'],
    'raise rollback preserves savepoint' => [static fn (): mixed => $run([['option_id' => 1]], $raiseRollback)['savepoint_preserved'], true],
    'raise rollback appends rollback effect' => [static fn (): mixed => $run([['option_id' => 1]], $raiseRollback)['trigger_effects'][0]['action'], 'rollback-to-current-savepoint'],
    'raise rollback zeroes changes' => [static fn (): mixed => $run([['option_id' => 1]], $raiseRollback)['changes'], 0],
    'raise rollback attempted state remains original when raised before write' => [static fn (): mixed => array_column($run([['option_id' => 1]], $raiseRollback)['attempted_parent'], 'option_id'), [1, 2, 3]],

    'raise ignore skips matching parent' => [static fn (): mixed => array_column($run([['option_id' => 3]], $raiseIgnore)['parent'], 'option_id'), [1, 2, 3]],
    'raise ignore records skipped parent' => [static fn (): mixed => $run([['option_id' => 3]], $raiseIgnore)['skipped'][0]['parent_key'], 3],
    'raise ignore keeps changes zero' => [static fn (): mixed => $run([['option_id' => 3]], $raiseIgnore)['changes'], 0],
    'raise ignore does not mark rollback' => [static fn (): mixed => $run([['option_id' => 3]], $raiseIgnore)['rolled_back'], false],
    'raise ignore can continue later delete' => [static fn (): mixed => array_column($run([['option_id' => 3], ['option_id' => 1]], $raiseIgnore)['parent'], 'option_id'), [2, 3]],

    'no action foreign key deletes only parent' => [static function () use ($optionToMeta, $run): mixed {
        $fk = $optionToMeta;
        $fk['on_delete'] = 'NO ACTION';
        return array_column($run([['option_id' => 1]], [], null, $fk)['child'], 'meta_id');
    }, [10, 11, 12, 13, 14]],
    'multiple deletes cascade both groups' => [static fn (): mixed => array_column($run([['option_id' => 1], ['option_id' => 3]])['child'], 'meta_id'), [12, 13]],
    'multiple deletes with grandchild fk keep unrelated details' => [static fn (): mixed => array_column($run([['option_id' => 1], ['option_id' => 3]], [], $metaToDetail)['grandchild'], 'detail_id'), [103, 104]],
    'multiple deletes count parent child grandchild rows' => [static fn (): mixed => $run([['option_id' => 1], ['option_id' => 3]], [], $metaToDetail)['changes'], 9],

    'false when trigger is ignored' => [static fn (): mixed => $run([['option_id' => 1]], [[
        'timing' => 'before',
        'event' => 'delete',
        'action' => 'delete-child',
        'when' => false,
    ]])['trigger_effects'], []],
    'is not when can audit nonmatching option' => [static fn (): mixed => $run([['option_id' => 2]], [[
        'timing' => 'before',
        'event' => 'delete',
        'action' => 'insert-audit',
        'when' => ['old.option_name', 'is not', 'active_plugins'],
        'audit' => ['option' => 'old.option_name'],
    ]])['audit'][0]['option'], 'rewrite_rules'],
    'unsupported conflict action throws' => [static fn (): mixed => $run([['option_id' => 1]], [], null, null, 'sp', 'replace'), InvalidArgumentException::class],
    'empty savepoint throws' => [static fn (): mixed => $run([['option_id' => 1]], [], null, null, ''), InvalidArgumentException::class],
    'malformed parent key throws' => [static function () use ($optionToMeta, $run): mixed {
        $fk = $optionToMeta;
        $fk['parent_key'] = 'bad-key';
        return $run([['option_id' => 1]], [], null, $fk);
    }, InvalidArgumentException::class],
    'unsupported trigger action throws' => [static fn (): mixed => $run([['option_id' => 1]], [[
        'timing' => 'before',
        'event' => 'delete',
        'action' => 'sideways',
    ]], $metaToDetail), InvalidArgumentException::class],
    'missing old parent column throws' => [static fn (): mixed => $run([['option_id' => 1]], [[
        'timing' => 'before',
        'event' => 'delete',
        'action' => 'insert-audit',
        'audit' => ['missing' => 'old.missing'],
    ]]), InvalidArgumentException::class],
    'grandchild trigger without fk throws' => [static fn (): mixed => $run([['option_id' => 1]], $beforeMoveDetails), InvalidArgumentException::class],
];

foreach ($cases as $name => [$callback, $expected]) {
    if (is_string($expected) && is_a($expected, Throwable::class, true)) {
        $tests["trigger before cascade savepoint current next35 {$name}"] = static fn (TestRunner $t) => $t->throws($expected, $callback);
    } else {
        $tests["trigger before cascade savepoint current next35 {$name}"] = static fn (TestRunner $t) => $t->same($expected, $callback());
    }
}

return $tests;
