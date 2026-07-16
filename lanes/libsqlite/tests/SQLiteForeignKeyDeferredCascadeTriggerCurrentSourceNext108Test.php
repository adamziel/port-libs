<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteForeignKeyDeferredCascadeTriggerCurrentSourcePlan;

$parents = [
    ['option_id' => 1, 'option_name' => 'active_plugins'],
    ['option_id' => 2, 'option_name' => 'rewrite_rules'],
    ['option_id' => 3, 'option_name' => 'theme_mods'],
];
$meta = [
    ['meta_id' => 10, 'option_id' => 1, 'meta_key' => 'autoload'],
    ['meta_id' => 11, 'option_id' => 1, 'meta_key' => 'checksum'],
    ['meta_id' => 12, 'option_id' => 2, 'meta_key' => 'autoload'],
    ['meta_id' => 13, 'option_id' => null, 'meta_key' => 'loose'],
    ['meta_id' => 14, 'option_id' => 3, 'meta_key' => 'theme'],
];
$detail = [
    ['detail_id' => 100, 'meta_id' => 10, 'label' => 'autoload-before'],
    ['detail_id' => 101, 'meta_id' => 10, 'label' => 'autoload-after'],
    ['detail_id' => 102, 'meta_id' => 11, 'label' => 'checksum-before'],
    ['detail_id' => 103, 'meta_id' => 12, 'label' => 'rewrite-before'],
    ['detail_id' => 104, 'meta_id' => null, 'label' => 'loose'],
    ['detail_id' => 105, 'meta_id' => 14, 'label' => 'theme'],
];
$fk = ['parent_key' => 'option_id', 'child_key' => 'option_id', 'on_delete' => 'CASCADE', 'deferred' => true];
$detailFk = ['parent_key' => 'meta_id', 'child_key' => 'meta_id', 'on_delete' => 'CASCADE'];
$beforeAudit = [[
    'timing' => 'before',
    'event' => 'delete',
    'action' => 'insert-audit',
    'audit' => ['phase' => 'before', 'meta_id' => 'old.meta_id', 'remaining_detail' => 'grandchild_count'],
]];
$afterAudit = [[
    'timing' => 'after',
    'event' => 'delete',
    'action' => 'insert-audit',
    'audit' => ['phase' => 'after', 'meta_id' => 'old.meta_id', 'remaining_detail' => 'grandchild_count'],
]];
$beforeMoveDetails = [[
    'timing' => 'before',
    'event' => 'delete',
    'action' => 'update-grandchild-key',
    'grandchild_key' => 'old.meta_id',
    'set_grandchild_key' => 12,
]];
$beforeDeleteDetails = [[
    'timing' => 'before',
    'event' => 'delete',
    'action' => 'delete-grandchild',
    'grandchild_key' => 'old.meta_id',
]];
$insertCurrentChild = [[
    'operation' => 'insert',
    'table' => 'child',
    'row' => ['meta_id' => 20, 'option_id' => 1, 'meta_key' => 'late-current'],
]];
$relinkCurrentChild = [[
    'operation' => 'update',
    'table' => 'child',
    'match' => ['meta_id' => 12],
    'set' => ['option_id' => 1, 'meta_key' => 'relinked-current'],
]];
$detachCurrentChild = [[
    'operation' => 'update',
    'table' => 'child',
    'match' => ['meta_id' => 10],
    'set' => ['option_id' => 2],
]];
$deleteCurrentChild = [[
    'operation' => 'delete',
    'table' => 'child',
    'match' => ['meta_id' => 11],
]];
$insertCurrentGrandchild = [[
    'operation' => 'insert',
    'table' => 'grandchild',
    'row' => ['detail_id' => 106, 'meta_id' => 10, 'label' => 'late-detail'],
]];
$updateCurrentGrandchild = [[
    'operation' => 'update',
    'table' => 'grandchild',
    'match' => ['detail_id' => 102],
    'set' => ['meta_id' => 12, 'label' => 'moved-detail'],
]];
$deleteCurrentGrandchild = [[
    'operation' => 'delete',
    'table' => 'grandchild',
    'match' => ['detail_id' => 101],
]];

$run = static fn (
    array $deleteKeys = [['option_id' => 1]],
    array $triggers = [],
    ?array $grandchildFk = null,
    array $ops = [],
    ?array $foreignKey = null,
): array => SQLiteForeignKeyDeferredCascadeTriggerCurrentSourcePlan::deleteParents(
    $parents,
    $meta,
    $detail,
    $deleteKeys,
    $foreignKey ?? $fk,
    $triggers,
    $grandchildFk,
    $ops,
);

$tests = [
    'foreign key deferred cascade trigger current source next108 deletes parent at statement' => static fn (TestRunner $t) => $t->same([2, 3], array_column($run()['after_statement']['parent'], 'option_id')),
    'foreign key deferred cascade trigger current source next108 keeps children before commit' => static fn (TestRunner $t) => $t->same([10, 11, 12, 13, 14], array_column($run()['after_statement']['child'], 'meta_id')),
    'foreign key deferred cascade trigger current source next108 queues deferred parent delete' => static fn (TestRunner $t) => $t->same('deferred-delete-parent', $run()['deferred'][0]['operation']),
    'foreign key deferred cascade trigger current source next108 preserves deferred flag' => static fn (TestRunner $t) => $t->same(true, $run()['deferred'][0]['deferred']),
    'foreign key deferred cascade trigger current source next108 records normalized action' => static fn (TestRunner $t) => $t->same('cascade', $run()['deferred'][0]['action']),
    'foreign key deferred cascade trigger current source next108 deletes cascaded children at commit' => static fn (TestRunner $t) => $t->same([12, 13, 14], array_column($run()['after_commit']['child'], 'meta_id')),
    'foreign key deferred cascade trigger current source next108 leaves grandchild rows without fk' => static fn (TestRunner $t) => $t->same([100, 101, 102, 103, 104, 105], array_column($run()['after_commit']['grandchild'], 'detail_id')),
    'foreign key deferred cascade trigger current source next108 records first cascade action' => static fn (TestRunner $t) => $t->same('deferred-cascade-delete-child', $run()['cascade_actions'][0]['action']),
    'foreign key deferred cascade trigger current source next108 records second child key' => static fn (TestRunner $t) => $t->same(1, $run()['cascade_actions'][1]['child_key']),
    'foreign key deferred cascade trigger current source next108 counts parent and child deletes' => static fn (TestRunner $t) => $t->same(3, $run()['changes']),
    'foreign key deferred cascade trigger current source next108 before audit sees current source count' => static fn (TestRunner $t) => $t->same(6, $run([['option_id' => 1]], $beforeAudit)['audit'][0]['remaining_detail']),
    'foreign key deferred cascade trigger current source next108 before audit reads old child row' => static fn (TestRunner $t) => $t->same(10, $run([['option_id' => 1]], $beforeAudit)['audit'][0]['meta_id']),
    'foreign key deferred cascade trigger current source next108 after audit fires per child' => static fn (TestRunner $t) => $t->same([10, 11], array_column($run([['option_id' => 1]], $afterAudit)['audit'], 'meta_id')),
    'foreign key deferred cascade trigger current source next108 before after audit interleaves per child' => static fn (TestRunner $t) => $t->same(['before', 'after', 'before', 'after'], array_column($run([['option_id' => 1]], array_merge($beforeAudit, $afterAudit))['audit'], 'phase')),
    'foreign key deferred cascade trigger current source next108 grandchild cascade deletes dependent rows' => static fn (TestRunner $t) => $t->same([103, 104, 105], array_column($run([['option_id' => 1]], [], $detailFk)['after_commit']['grandchild'], 'detail_id')),
    'foreign key deferred cascade trigger current source next108 grandchild cascade action order' => static fn (TestRunner $t) => $t->same(['deferred-cascade-delete-child', 'deferred-cascade-delete-grandchild', 'deferred-cascade-delete-grandchild', 'deferred-cascade-delete-child', 'deferred-cascade-delete-grandchild'], array_column($run([['option_id' => 1]], [], $detailFk)['cascade_actions'], 'action')),
    'foreign key deferred cascade trigger current source next108 after audit sees post grandchild count' => static fn (TestRunner $t) => $t->same([4, 3], array_column($run([['option_id' => 1]], $afterAudit, $detailFk)['audit'], 'remaining_detail')),
    'foreign key deferred cascade trigger current source next108 before move prevents grandchild cascade' => static fn (TestRunner $t) => $t->same(['deferred-cascade-delete-child', 'deferred-cascade-delete-child'], array_column($run([['option_id' => 1]], $beforeMoveDetails, $detailFk)['cascade_actions'], 'action')),
    'foreign key deferred cascade trigger current source next108 before move records updated details' => static fn (TestRunner $t) => $t->same([2, 1], array_column($run([['option_id' => 1]], $beforeMoveDetails, $detailFk)['trigger_effects'], 'rows')),
    'foreign key deferred cascade trigger current source next108 before delete removes details before cascade' => static fn (TestRunner $t) => $t->same([103, 104, 105], array_column($run([['option_id' => 1]], $beforeDeleteDetails, $detailFk)['after_commit']['grandchild'], 'detail_id')),
    'foreign key deferred cascade trigger current source next108 before delete suppresses grandchild cascade actions' => static fn (TestRunner $t) => $t->same(['deferred-cascade-delete-child', 'deferred-cascade-delete-child'], array_column($run([['option_id' => 1]], $beforeDeleteDetails, $detailFk)['cascade_actions'], 'action')),
    'foreign key deferred cascade trigger current source next108 current child insert visible before commit' => static fn (TestRunner $t) => $t->same([10, 11, 12, 13, 14, 20], array_column($run([['option_id' => 1]], [], null, $insertCurrentChild)['before_commit']['child'], 'meta_id')),
    'foreign key deferred cascade trigger current source next108 current child insert is cascaded' => static fn (TestRunner $t) => $t->same([12, 13, 14], array_column($run([['option_id' => 1]], [], null, $insertCurrentChild)['after_commit']['child'], 'meta_id')),
    'foreign key deferred cascade trigger current source next108 current child insert records current action' => static fn (TestRunner $t) => $t->same('insert-current-child', $run([['option_id' => 1]], [], null, $insertCurrentChild)['current_source_actions'][0]['action']),
    'foreign key deferred cascade trigger current source next108 current child insert raises cascade count' => static fn (TestRunner $t) => $t->same(5, $run([['option_id' => 1]], [], null, $insertCurrentChild)['changes']),
    'foreign key deferred cascade trigger current source next108 current child relink into deleted parent is cascaded' => static fn (TestRunner $t) => $t->same([13, 14], array_column($run([['option_id' => 1]], [], null, $relinkCurrentChild)['after_commit']['child'], 'meta_id')),
    'foreign key deferred cascade trigger current source next108 current child relink action records rows' => static fn (TestRunner $t) => $t->same(1, $run([['option_id' => 1]], [], null, $relinkCurrentChild)['current_source_actions'][0]['rows']),
    'foreign key deferred cascade trigger current source next108 current child relink changes include update' => static fn (TestRunner $t) => $t->same(5, $run([['option_id' => 1]], [], null, $relinkCurrentChild)['changes']),
    'foreign key deferred cascade trigger current source next108 current child detach escapes cascade' => static fn (TestRunner $t) => $t->same([10, 12, 13, 14], array_column($run([['option_id' => 1]], [], null, $detachCurrentChild)['after_commit']['child'], 'meta_id')),
    'foreign key deferred cascade trigger current source next108 current child detach before commit updates key' => static fn (TestRunner $t) => $t->same(2, $run([['option_id' => 1]], [], null, $detachCurrentChild)['before_commit']['child'][0]['option_id']),
    'foreign key deferred cascade trigger current source next108 current child delete before commit reduces cascade' => static fn (TestRunner $t) => $t->same([12, 13, 14], array_column($run([['option_id' => 1]], [], null, $deleteCurrentChild)['after_commit']['child'], 'meta_id')),
    'foreign key deferred cascade trigger current source next108 current child delete records deleted row count' => static fn (TestRunner $t) => $t->same(1, $run([['option_id' => 1]], [], null, $deleteCurrentChild)['current_source_actions'][0]['rows']),
    'foreign key deferred cascade trigger current source next108 current grandchild insert is visible to before trigger' => static fn (TestRunner $t) => $t->same(7, $run([['option_id' => 1]], $beforeAudit, $detailFk, $insertCurrentGrandchild)['audit'][0]['remaining_detail']),
    'foreign key deferred cascade trigger current source next108 current grandchild insert is cascaded with child' => static fn (TestRunner $t) => $t->same([103, 104, 105], array_column($run([['option_id' => 1]], [], $detailFk, $insertCurrentGrandchild)['after_commit']['grandchild'], 'detail_id')),
    'foreign key deferred cascade trigger current source next108 current grandchild insert records current action' => static fn (TestRunner $t) => $t->same('insert-current-grandchild', $run([['option_id' => 1]], [], $detailFk, $insertCurrentGrandchild)['current_source_actions'][0]['action']),
    'foreign key deferred cascade trigger current source next108 current grandchild update can escape cascade' => static fn (TestRunner $t) => $t->same([102, 103, 104, 105], array_column($run([['option_id' => 1]], [], $detailFk, $updateCurrentGrandchild)['after_commit']['grandchild'], 'detail_id')),
    'foreign key deferred cascade trigger current source next108 current grandchild update records rows' => static fn (TestRunner $t) => $t->same(1, $run([['option_id' => 1]], [], $detailFk, $updateCurrentGrandchild)['current_source_actions'][0]['rows']),
    'foreign key deferred cascade trigger current source next108 current grandchild delete reduces trigger count' => static fn (TestRunner $t) => $t->same(5, $run([['option_id' => 1]], $beforeAudit, $detailFk, $deleteCurrentGrandchild)['audit'][0]['remaining_detail']),
    'foreign key deferred cascade trigger current source next108 current grandchild delete records rows' => static fn (TestRunner $t) => $t->same(1, $run([['option_id' => 1]], [], $detailFk, $deleteCurrentGrandchild)['current_source_actions'][0]['rows']),
    'foreign key deferred cascade trigger current source next108 no action reports deferred violation' => static function (TestRunner $t) use ($fk, $run): void {
        $noAction = $fk;
        $noAction['on_delete'] = 'NO ACTION';
        $t->same('referenced-parent-deleted-at-deferred-commit', $run([['option_id' => 1]], [], null, [], $noAction)['violations'][0]['reason']);
    },
    'foreign key deferred cascade trigger current source next108 no action keeps child rows' => static function (TestRunner $t) use ($fk, $run): void {
        $noAction = $fk;
        $noAction['on_delete'] = 'NO ACTION';
        $t->same([10, 11, 12, 13, 14], array_column($run([['option_id' => 1]], [], null, [], $noAction)['after_commit']['child'], 'meta_id'));
    },
    'foreign key deferred cascade trigger current source next108 restrict blocks immediately' => static function (TestRunner $t) use ($fk, $run): void {
        $restrict = $fk;
        $restrict['on_delete'] = 'RESTRICT';
        $t->throws(InvalidArgumentException::class, static fn () => $run([['option_id' => 1]], [], null, [], $restrict));
    },
    'foreign key deferred cascade trigger current source next108 missing delete key is no op' => static fn (TestRunner $t) => $t->same([1, 2, 3], array_column($run([['option_id' => 99]])['after_commit']['parent'], 'option_id')),
    'foreign key deferred cascade trigger current source next108 missing delete key has no deferred event' => static fn (TestRunner $t) => $t->same([], $run([['option_id' => 99]])['deferred']),
    'foreign key deferred cascade trigger current source next108 multiple parent deletes keep middle group only' => static fn (TestRunner $t) => $t->same([2], array_column($run([['option_id' => 1], ['option_id' => 3]])['after_commit']['parent'], 'option_id')),
    'foreign key deferred cascade trigger current source next108 multiple parent deletes keep loose child' => static fn (TestRunner $t) => $t->same([12, 13], array_column($run([['option_id' => 1], ['option_id' => 3]])['after_commit']['child'], 'meta_id')),
    'foreign key deferred cascade trigger current source next108 multiple parent deletes cascade details' => static fn (TestRunner $t) => $t->same([103, 104], array_column($run([['option_id' => 1], ['option_id' => 3]], [], $detailFk)['after_commit']['grandchild'], 'detail_id')),
    'foreign key deferred cascade trigger current source next108 dependencies name current source' => static fn (TestRunner $t) => $t->same(true, in_array('sqlite-current-source-before-deferred-commit', $run()['dependencies'], true)),
    'foreign key deferred cascade trigger current source next108 remaining child order is stable' => static fn (TestRunner $t) => $t->same(['autoload', 'loose', 'theme'], array_column($run()['after_commit']['child'], 'meta_key')),
    'foreign key deferred cascade trigger current source next108 remaining grandchild order is stable' => static fn (TestRunner $t) => $t->same(['rewrite-before', 'loose', 'theme'], array_column($run([['option_id' => 1]], [], $detailFk)['after_commit']['grandchild'], 'label')),
    'foreign key deferred cascade trigger current source next108 cascade action keeps deleted child payload' => static fn (TestRunner $t) => $t->same('autoload', $run()['cascade_actions'][0]['child']['meta_key']),
    'foreign key deferred cascade trigger current source next108 ignores non delete trigger' => static fn (TestRunner $t) => $t->same([], $run([['option_id' => 1]], [['timing' => 'before', 'event' => 'update', 'action' => 'insert-audit']])['trigger_effects']),
    'foreign key deferred cascade trigger current source next108 child trigger requires grandchild fk' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run([['option_id' => 1]], $beforeMoveDetails)),
    'foreign key deferred cascade trigger current source next108 current grandchild op requires fk' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run([['option_id' => 1]], [], null, $insertCurrentGrandchild)),
    'foreign key deferred cascade trigger current source next108 unsupported current operation rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run([['option_id' => 1]], [], null, [['operation' => 'replace', 'table' => 'child', 'row' => ['meta_id' => 30, 'option_id' => 1]]])) ,
    'foreign key deferred cascade trigger current source next108 malformed parent key rejected' => static function (TestRunner $t) use ($fk, $run): void {
        $bad = $fk;
        $bad['parent_key'] = 'bad-key';
        $t->throws(InvalidArgumentException::class, static fn () => $run([['option_id' => 1]], [], null, [], $bad));
    },
    'foreign key deferred cascade trigger current source next108 malformed child key rejected' => static function (TestRunner $t) use ($fk, $run): void {
        $bad = $fk;
        $bad['child_key'] = '1bad';
        $t->throws(InvalidArgumentException::class, static fn () => $run([['option_id' => 1]], [], null, [], $bad));
    },
    'foreign key deferred cascade trigger current source next108 unsupported fk action rejected' => static function (TestRunner $t) use ($fk, $run): void {
        $bad = $fk;
        $bad['on_delete'] = 'SET NULL';
        $t->throws(InvalidArgumentException::class, static fn () => $run([['option_id' => 1]], [], null, [], $bad));
    },
    'foreign key deferred cascade trigger current source next108 missing child row column rejected' => static function (TestRunner $t) use ($parents, $detail, $fk): void {
        $broken = [['meta_id' => 10]];
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteForeignKeyDeferredCascadeTriggerCurrentSourcePlan::deleteParents($parents, $broken, $detail, [['option_id' => 1]], $fk));
    },
    'foreign key deferred cascade trigger current source next108 missing grandchild row column rejected' => static function (TestRunner $t) use ($parents, $meta, $fk, $detailFk): void {
        $broken = [['detail_id' => 100]];
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteForeignKeyDeferredCascadeTriggerCurrentSourcePlan::deleteParents($parents, $meta, $broken, [['option_id' => 1]], $fk, [], $detailFk));
    },
];

return $tests;
