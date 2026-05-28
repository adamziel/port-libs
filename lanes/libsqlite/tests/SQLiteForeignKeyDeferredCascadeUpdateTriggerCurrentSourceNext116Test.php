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
    ['meta_id' => 12, 'option_id' => 2, 'meta_key' => 'rewrite'],
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
$fk = ['parent_key' => 'option_id', 'child_key' => 'option_id', 'on_update' => 'CASCADE', 'deferred' => true];
$detailFk = ['parent_key' => 'meta_id', 'child_key' => 'meta_id', 'on_update' => 'CASCADE'];
$beforeAudit = [[
    'timing' => 'before',
    'event' => 'update',
    'action' => 'insert-audit',
    'audit' => ['phase' => 'before', 'old_option' => 'old.option_id', 'new_option' => 'new.option_id', 'meta_id' => 'old.meta_id', 'detail_count' => 'grandchild_count'],
]];
$afterAudit = [[
    'timing' => 'after',
    'event' => 'update',
    'action' => 'insert-audit',
    'audit' => ['phase' => 'after', 'old_option' => 'old.option_id', 'new_option' => 'new.option_id', 'meta_id' => 'new.meta_id', 'detail_count' => 'grandchild_count'],
]];
$beforeMoveDetail = [[
    'timing' => 'before',
    'event' => 'update',
    'action' => 'update-grandchild-key',
    'grandchild_key' => 'old.meta_id',
    'set_grandchild_key' => 12,
]];
$afterMoveDetail = [[
    'timing' => 'after',
    'event' => 'update',
    'action' => 'update-grandchild-key',
    'grandchild_key' => 'old.meta_id',
    'set_grandchild_key' => 'new.meta_id',
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

$run = static fn (
    array $updates = [['option_id' => 1, 'new_option_id' => 101, 'option_name' => 'active_plugins_migrated']],
    array $triggers = [],
    ?array $grandchildFk = null,
    array $ops = [],
    ?array $foreignKey = null,
): array => SQLiteForeignKeyDeferredCascadeTriggerCurrentSourcePlan::updateParents(
    $parents,
    $meta,
    $detail,
    $updates,
    $foreignKey ?? $fk,
    $triggers,
    $grandchildFk,
    $ops,
);

$tests = [
    'foreign key deferred cascade update trigger current source next116 updates parent at statement' => static fn (TestRunner $t) => $t->same([101, 2, 3], array_column($run()['after_statement']['parent'], 'option_id')),
    'foreign key deferred cascade update trigger current source next116 keeps child keys before commit' => static fn (TestRunner $t) => $t->same([1, 1, 2, null, 3], array_column($run()['after_statement']['child'], 'option_id')),
    'foreign key deferred cascade update trigger current source next116 queues deferred parent update' => static fn (TestRunner $t) => $t->same('deferred-update-parent', $run()['deferred'][0]['operation']),
    'foreign key deferred cascade update trigger current source next116 records old key' => static fn (TestRunner $t) => $t->same(1, $run()['deferred'][0]['old_parent_key']),
    'foreign key deferred cascade update trigger current source next116 records new key' => static fn (TestRunner $t) => $t->same(101, $run()['deferred'][0]['new_parent_key']),
    'foreign key deferred cascade update trigger current source next116 preserves deferred flag' => static fn (TestRunner $t) => $t->same(true, $run()['deferred'][0]['deferred']),
    'foreign key deferred cascade update trigger current source next116 normalizes cascade action' => static fn (TestRunner $t) => $t->same('cascade', $run()['deferred'][0]['action']),
    'foreign key deferred cascade update trigger current source next116 cascades child keys at commit' => static fn (TestRunner $t) => $t->same([101, 101, 2, null, 3], array_column($run()['after_commit']['child'], 'option_id')),
    'foreign key deferred cascade update trigger current source next116 leaves grandchildren without fk' => static fn (TestRunner $t) => $t->same([10, 10, 11, 12, null, 14], array_column($run()['after_commit']['grandchild'], 'meta_id')),
    'foreign key deferred cascade update trigger current source next116 records first cascade update' => static fn (TestRunner $t) => $t->same('deferred-cascade-update-child', $run()['cascade_actions'][0]['action']),
    'foreign key deferred cascade update trigger current source next116 records old child key' => static fn (TestRunner $t) => $t->same(1, $run()['cascade_actions'][0]['old_child_key']),
    'foreign key deferred cascade update trigger current source next116 records new child key' => static fn (TestRunner $t) => $t->same(101, $run()['cascade_actions'][0]['new_child_key']),
    'foreign key deferred cascade update trigger current source next116 counts parent and child updates' => static fn (TestRunner $t) => $t->same(3, $run()['changes']),
    'foreign key deferred cascade update trigger current source next116 before audit sees old and new keys' => static fn (TestRunner $t) => $t->same([[1, 101], [1, 101]], array_map(static fn (array $row): array => [$row['old_option'], $row['new_option']], $run([['option_id' => 1, 'new_option_id' => 101]], $beforeAudit)['audit'])),
    'foreign key deferred cascade update trigger current source next116 after audit fires per updated child' => static fn (TestRunner $t) => $t->same([10, 11], array_column($run([['option_id' => 1, 'new_option_id' => 101]], $afterAudit)['audit'], 'meta_id')),
    'foreign key deferred cascade update trigger current source next116 before and after audits interleave' => static fn (TestRunner $t) => $t->same(['before', 'after', 'before', 'after'], array_column($run([['option_id' => 1, 'new_option_id' => 101]], array_merge($beforeAudit, $afterAudit))['audit'], 'phase')),
    'foreign key deferred cascade update trigger current source next116 grandchild cascade follows child key updates' => static fn (TestRunner $t) => $t->same([10, 10, 11, 12, null, 14], array_column($run([['option_id' => 1, 'new_option_id' => 101]], [], $detailFk)['after_commit']['grandchild'], 'meta_id')),
    'foreign key deferred cascade update trigger current source next116 grandchild update actions are recorded' => static fn (TestRunner $t) => $t->same(['deferred-cascade-update-child', 'deferred-cascade-update-grandchild', 'deferred-cascade-update-grandchild', 'deferred-cascade-update-child', 'deferred-cascade-update-grandchild'], array_column($run([['option_id' => 1, 'new_option_id' => 101]], [], $detailFk)['cascade_actions'], 'action')),
    'foreign key deferred cascade update trigger current source next116 before trigger can move grandchild away' => static fn (TestRunner $t) => $t->same([12, 12, 12, 12, null, 14], array_column($run([['option_id' => 1, 'new_option_id' => 101]], $beforeMoveDetail, $detailFk)['after_commit']['grandchild'], 'meta_id')),
    'foreign key deferred cascade update trigger current source next116 before trigger suppresses grandchild cascade rows' => static fn (TestRunner $t) => $t->same(['deferred-cascade-update-child', 'deferred-cascade-update-child'], array_column($run([['option_id' => 1, 'new_option_id' => 101]], $beforeMoveDetail, $detailFk)['cascade_actions'], 'action')),
    'foreign key deferred cascade update trigger current source next116 after trigger runs after grandchild cascade' => static fn (TestRunner $t) => $t->same([10, 10, 11, 12, null, 14], array_column($run([['option_id' => 1, 'new_option_id' => 101]], $afterMoveDetail, $detailFk)['after_commit']['grandchild'], 'meta_id')),
    'foreign key deferred cascade update trigger current source next116 current child insert is visible before commit' => static fn (TestRunner $t) => $t->same([10, 11, 12, 13, 14, 20], array_column($run([['option_id' => 1, 'new_option_id' => 101]], [], null, $insertCurrentChild)['before_commit']['child'], 'meta_id')),
    'foreign key deferred cascade update trigger current source next116 current child insert is cascaded' => static fn (TestRunner $t) => $t->same([101, 101, 2, null, 3, 101], array_column($run([['option_id' => 1, 'new_option_id' => 101]], [], null, $insertCurrentChild)['after_commit']['child'], 'option_id')),
    'foreign key deferred cascade update trigger current source next116 current child insert records action' => static fn (TestRunner $t) => $t->same('insert-current-child', $run([['option_id' => 1, 'new_option_id' => 101]], [], null, $insertCurrentChild)['current_source_actions'][0]['action']),
    'foreign key deferred cascade update trigger current source next116 current child insert increases change count' => static fn (TestRunner $t) => $t->same(5, $run([['option_id' => 1, 'new_option_id' => 101]], [], null, $insertCurrentChild)['changes']),
    'foreign key deferred cascade update trigger current source next116 current child relink into old parent cascades' => static fn (TestRunner $t) => $t->same([101, 101, 101, null, 3], array_column($run([['option_id' => 1, 'new_option_id' => 101]], [], null, $relinkCurrentChild)['after_commit']['child'], 'option_id')),
    'foreign key deferred cascade update trigger current source next116 current child relink records rows' => static fn (TestRunner $t) => $t->same(1, $run([['option_id' => 1, 'new_option_id' => 101]], [], null, $relinkCurrentChild)['current_source_actions'][0]['rows']),
    'foreign key deferred cascade update trigger current source next116 current child detach escapes update' => static fn (TestRunner $t) => $t->same([2, 101, 2, null, 3], array_column($run([['option_id' => 1, 'new_option_id' => 101]], [], null, $detachCurrentChild)['after_commit']['child'], 'option_id')),
    'foreign key deferred cascade update trigger current source next116 current child detach before commit updates key' => static fn (TestRunner $t) => $t->same(2, $run([['option_id' => 1, 'new_option_id' => 101]], [], null, $detachCurrentChild)['before_commit']['child'][0]['option_id']),
    'foreign key deferred cascade update trigger current source next116 current grandchild insert joins cascade' => static fn (TestRunner $t) => $t->same([10, 10, 11, 12, null, 14, 10], array_column($run([['option_id' => 1, 'new_option_id' => 101]], [], $detailFk, $insertCurrentGrandchild)['after_commit']['grandchild'], 'meta_id')),
    'foreign key deferred cascade update trigger current source next116 current grandchild insert visible to trigger' => static fn (TestRunner $t) => $t->same(7, $run([['option_id' => 1, 'new_option_id' => 101]], $beforeAudit, $detailFk, $insertCurrentGrandchild)['audit'][0]['detail_count']),
    'foreign key deferred cascade update trigger current source next116 current grandchild update can escape cascade' => static fn (TestRunner $t) => $t->same([10, 10, 12, 12, null, 14], array_column($run([['option_id' => 1, 'new_option_id' => 101]], [], $detailFk, $updateCurrentGrandchild)['after_commit']['grandchild'], 'meta_id')),
    'foreign key deferred cascade update trigger current source next116 current grandchild update records rows' => static fn (TestRunner $t) => $t->same(1, $run([['option_id' => 1, 'new_option_id' => 101]], [], $detailFk, $updateCurrentGrandchild)['current_source_actions'][0]['rows']),
    'foreign key deferred cascade update trigger current source next116 no action reports violation' => static function (TestRunner $t) use ($fk, $run): void {
        $noAction = $fk;
        $noAction['on_update'] = 'NO ACTION';
        $t->same('referenced-parent-updated-at-deferred-commit', $run([['option_id' => 1, 'new_option_id' => 101]], [], null, [], $noAction)['violations'][0]['reason']);
    },
    'foreign key deferred cascade update trigger current source next116 no action keeps child keys' => static function (TestRunner $t) use ($fk, $run): void {
        $noAction = $fk;
        $noAction['on_update'] = 'NO ACTION';
        $t->same([1, 1, 2, null, 3], array_column($run([['option_id' => 1, 'new_option_id' => 101]], [], null, [], $noAction)['after_commit']['child'], 'option_id'));
    },
    'foreign key deferred cascade update trigger current source next116 restrict blocks immediately' => static function (TestRunner $t) use ($fk, $run): void {
        $restrict = $fk;
        $restrict['on_update'] = 'RESTRICT';
        $t->throws(InvalidArgumentException::class, static fn () => $run([['option_id' => 1, 'new_option_id' => 101]], [], null, [], $restrict));
    },
    'foreign key deferred cascade update trigger current source next116 missing update key is no op' => static fn (TestRunner $t) => $t->same([1, 2, 3], array_column($run([['option_id' => 99, 'new_option_id' => 199]])['after_commit']['parent'], 'option_id')),
    'foreign key deferred cascade update trigger current source next116 missing update key has no deferred event' => static fn (TestRunner $t) => $t->same([], $run([['option_id' => 99, 'new_option_id' => 199]])['deferred']),
    'foreign key deferred cascade update trigger current source next116 same key updates parent only' => static fn (TestRunner $t) => $t->same(1, $run([['option_id' => 1, 'new_option_id' => 1, 'option_name' => 'same']])['changes']),
    'foreign key deferred cascade update trigger current source next116 same key has no cascade actions' => static fn (TestRunner $t) => $t->same([], $run([['option_id' => 1, 'new_option_id' => 1, 'option_name' => 'same']])['cascade_actions']),
    'foreign key deferred cascade update trigger current source next116 multiple parent updates cascade both groups' => static fn (TestRunner $t) => $t->same([101, 101, 202, null, 3], array_column($run([['option_id' => 1, 'new_option_id' => 101], ['option_id' => 2, 'new_option_id' => 202]])['after_commit']['child'], 'option_id')),
    'foreign key deferred cascade update trigger current source next116 dependencies name update source' => static fn (TestRunner $t) => $t->same(true, in_array('sqlite-cascade-update-trigger-current-source', $run()['dependencies'], true)),
    'foreign key deferred cascade update trigger current source next116 child order is stable' => static fn (TestRunner $t) => $t->same(['autoload', 'checksum', 'rewrite', 'loose', 'theme'], array_column($run()['after_commit']['child'], 'meta_key')),
    'foreign key deferred cascade update trigger current source next116 grandchild order is stable' => static fn (TestRunner $t) => $t->same(['autoload-before', 'autoload-after', 'checksum-before', 'rewrite-before', 'loose', 'theme'], array_column($run([['option_id' => 1, 'new_option_id' => 101]], [], $detailFk)['after_commit']['grandchild'], 'label')),
    'foreign key deferred cascade update trigger current source next116 cascade action keeps old child payload' => static fn (TestRunner $t) => $t->same('autoload', $run()['cascade_actions'][0]['old_child']['meta_key']),
    'foreign key deferred cascade update trigger current source next116 ignores delete trigger' => static fn (TestRunner $t) => $t->same([], $run([['option_id' => 1, 'new_option_id' => 101]], [['timing' => 'before', 'event' => 'delete', 'action' => 'insert-audit']])['trigger_effects']),
    'foreign key deferred cascade update trigger current source next116 update trigger requires grandchild fk for grandchild action' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run([['option_id' => 1, 'new_option_id' => 101]], $beforeMoveDetail)),
    'foreign key deferred cascade update trigger current source next116 current grandchild op requires fk' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run([['option_id' => 1, 'new_option_id' => 101]], [], null, $insertCurrentGrandchild)),
    'foreign key deferred cascade update trigger current source next116 unsupported update trigger rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run([['option_id' => 1, 'new_option_id' => 101]], [['timing' => 'before', 'event' => 'update', 'action' => 'delete-grandchild']], $detailFk)),
    'foreign key deferred cascade update trigger current source next116 unsupported current operation rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run([['option_id' => 1, 'new_option_id' => 101]], [], null, [['operation' => 'replace', 'table' => 'child', 'row' => ['meta_id' => 30, 'option_id' => 1]]])) ,
    'foreign key deferred cascade update trigger current source next116 malformed parent key rejected' => static function (TestRunner $t) use ($fk, $run): void {
        $bad = $fk;
        $bad['parent_key'] = 'bad-key';
        $t->throws(InvalidArgumentException::class, static fn () => $run([['option_id' => 1, 'new_option_id' => 101]], [], null, [], $bad));
    },
    'foreign key deferred cascade update trigger current source next116 malformed child key rejected' => static function (TestRunner $t) use ($fk, $run): void {
        $bad = $fk;
        $bad['child_key'] = '1bad';
        $t->throws(InvalidArgumentException::class, static fn () => $run([['option_id' => 1, 'new_option_id' => 101]], [], null, [], $bad));
    },
    'foreign key deferred cascade update trigger current source next116 unsupported fk action rejected' => static function (TestRunner $t) use ($fk, $run): void {
        $bad = $fk;
        $bad['on_update'] = 'SET NULL';
        $t->throws(InvalidArgumentException::class, static fn () => $run([['option_id' => 1, 'new_option_id' => 101]], [], null, [], $bad));
    },
    'foreign key deferred cascade update trigger current source next116 missing child row column rejected' => static function (TestRunner $t) use ($parents, $detail, $fk): void {
        $broken = [['meta_id' => 10]];
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteForeignKeyDeferredCascadeTriggerCurrentSourcePlan::updateParents($parents, $broken, $detail, [['option_id' => 1, 'new_option_id' => 101]], $fk));
    },
    'foreign key deferred cascade update trigger current source next116 missing grandchild row column rejected' => static function (TestRunner $t) use ($parents, $meta, $fk, $detailFk): void {
        $broken = [['detail_id' => 100]];
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteForeignKeyDeferredCascadeTriggerCurrentSourcePlan::updateParents($parents, $meta, $broken, [['option_id' => 1, 'new_option_id' => 101]], $fk, [], $detailFk));
    },
];

return $tests;
