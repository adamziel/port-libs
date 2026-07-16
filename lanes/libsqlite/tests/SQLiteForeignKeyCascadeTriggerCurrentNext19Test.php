<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteForeignKeyCascadeTriggerPlan;

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
$auditDetail = [
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

$run = static fn (array $deleteKeys = [['option_id' => 1]], array $triggers = [], ?array $grandchildFk = null, ?array $fk = null): array => SQLiteForeignKeyCascadeTriggerPlan::deleteParents(
    $parents,
    $meta,
    $auditDetail,
    $deleteKeys,
    $fk ?? $optionToMeta,
    $triggers,
    $grandchildFk,
);

$tests = [
    'fk cascade trigger current deletes parent row' => static fn (TestRunner $t) => $t->same([2, 3], array_column($run()['parent'], 'option_id')),
    'fk cascade trigger current cascades child rows' => static fn (TestRunner $t) => $t->same([12, 13, 14], array_column($run()['child'], 'meta_id')),
    'fk cascade trigger current preserves grandchildren without child fk' => static fn (TestRunner $t) => $t->same([100, 101, 102, 103, 104, 105], array_column($run()['grandchild'], 'detail_id')),
    'fk cascade trigger current records first child action' => static fn (TestRunner $t) => $t->same('cascade-delete-child', $run()['cascade_actions'][0]['action']),
    'fk cascade trigger current records second child key' => static fn (TestRunner $t) => $t->same(1, $run()['cascade_actions'][1]['child_key']),
    'fk cascade trigger current counts parent and children' => static fn (TestRunner $t) => $t->same(3, $run()['changes']),
    'fk cascade trigger current missing parent is no op' => static fn (TestRunner $t) => $t->same([1, 2, 3], array_column($run([['option_id' => 99]])['parent'], 'option_id')),
    'fk cascade trigger current missing parent records no actions' => static fn (TestRunner $t) => $t->same([], $run([['option_id' => 99]])['cascade_actions']),
    'fk cascade trigger current no action leaves child rows' => static function (TestRunner $t) use ($optionToMeta, $run): void {
        $fk = $optionToMeta;
        $fk['on_delete'] = 'NO ACTION';
        $t->same([10, 11, 12, 13, 14], array_column($run([['option_id' => 1]], [], null, $fk)['child'], 'meta_id'));
    },
    'fk cascade trigger current no action still deletes parent' => static function (TestRunner $t) use ($optionToMeta, $run): void {
        $fk = $optionToMeta;
        $fk['on_delete'] = 'NO ACTION';
        $t->same([2, 3], array_column($run([['option_id' => 1]], [], null, $fk)['parent'], 'option_id'));
    },
    'fk cascade trigger current before audit fires for first child' => static fn (TestRunner $t) => $t->same('before', $run([['option_id' => 1]], $beforeAudit)['audit'][0]['phase']),
    'fk cascade trigger current before audit reads old child key' => static fn (TestRunner $t) => $t->same(10, $run([['option_id' => 1]], $beforeAudit)['audit'][0]['meta_id']),
    'fk cascade trigger current before audit fires per cascaded child' => static fn (TestRunner $t) => $t->same([10, 11], array_column($run([['option_id' => 1]], $beforeAudit)['audit'], 'meta_id')),
    'fk cascade trigger current before audit sees original grandchild count' => static fn (TestRunner $t) => $t->same(6, $run([['option_id' => 1]], $beforeAudit)['audit'][0]['remaining_detail']),
    'fk cascade trigger current after audit fires per cascaded child' => static fn (TestRunner $t) => $t->same([10, 11], array_column($run([['option_id' => 1]], $afterAudit)['audit'], 'meta_id')),
    'fk cascade trigger current before after audit order follows each child delete' => static function (TestRunner $t) use ($run, $beforeAudit, $afterAudit): void {
        $t->same(['before', 'after', 'before', 'after'], array_column($run([['option_id' => 1]], array_merge($beforeAudit, $afterAudit))['audit'], 'phase'));
    },
    'fk cascade trigger current trigger effects include before audit rows' => static fn (TestRunner $t) => $t->same([1, 1], array_column($run([['option_id' => 1]], $beforeAudit)['trigger_effects'], 'rows')),
    'fk cascade trigger current trigger changes count audit rows' => static fn (TestRunner $t) => $t->same(5, $run([['option_id' => 1]], $beforeAudit)['changes']),
    'fk cascade trigger current grandchild cascade removes details' => static fn (TestRunner $t) => $t->same([103, 104, 105], array_column($run([['option_id' => 1]], [], $metaToDetail)['grandchild'], 'detail_id')),
    'fk cascade trigger current grandchild cascade records actions' => static fn (TestRunner $t) => $t->same(['cascade-delete-child', 'cascade-delete-grandchild', 'cascade-delete-grandchild', 'cascade-delete-child', 'cascade-delete-grandchild'], array_column($run([['option_id' => 1]], [], $metaToDetail)['cascade_actions'], 'action')),
    'fk cascade trigger current grandchild cascade counts all rows' => static fn (TestRunner $t) => $t->same(6, $run([['option_id' => 1]], [], $metaToDetail)['changes']),
    'fk cascade trigger current after audit sees post grandchild cascade count' => static fn (TestRunner $t) => $t->same(4, $run([['option_id' => 1]], $afterAudit, $metaToDetail)['audit'][0]['remaining_detail']),
    'fk cascade trigger current after audit sees second post cascade count' => static fn (TestRunner $t) => $t->same(3, $run([['option_id' => 1]], $afterAudit, $metaToDetail)['audit'][1]['remaining_detail']),
    'fk cascade trigger current before trigger moves details before grandchild cascade' => static fn (TestRunner $t) => $t->same([100, 101, 102, 103, 104, 105], array_column($run([['option_id' => 1]], $beforeMoveDetails, $metaToDetail)['grandchild'], 'detail_id')),
    'fk cascade trigger current before move prevents grandchild cascade actions' => static fn (TestRunner $t) => $t->same(['cascade-delete-child', 'cascade-delete-child'], array_column($run([['option_id' => 1]], $beforeMoveDetails, $metaToDetail)['cascade_actions'], 'action')),
    'fk cascade trigger current before move records updated rows' => static fn (TestRunner $t) => $t->same([2, 1], array_column($run([['option_id' => 1]], $beforeMoveDetails, $metaToDetail)['trigger_effects'], 'rows')),
    'fk cascade trigger current before move changes include detail updates' => static fn (TestRunner $t) => $t->same(6, $run([['option_id' => 1]], $beforeMoveDetails, $metaToDetail)['changes']),
    'fk cascade trigger current before delete removes details before grandchild cascade' => static fn (TestRunner $t) => $t->same([103, 104, 105], array_column($run([['option_id' => 1]], $beforeDeleteDetails, $metaToDetail)['grandchild'], 'detail_id')),
    'fk cascade trigger current before delete leaves no grandchild cascade actions' => static fn (TestRunner $t) => $t->same(['cascade-delete-child', 'cascade-delete-child'], array_column($run([['option_id' => 1]], $beforeDeleteDetails, $metaToDetail)['cascade_actions'], 'action')),
    'fk cascade trigger current before delete records deleted detail rows' => static fn (TestRunner $t) => $t->same([2, 1], array_column($run([['option_id' => 1]], $beforeDeleteDetails, $metaToDetail)['trigger_effects'], 'rows')),
    'fk cascade trigger current before delete changes include detail deletes' => static fn (TestRunner $t) => $t->same(6, $run([['option_id' => 1]], $beforeDeleteDetails, $metaToDetail)['changes']),
    'fk cascade trigger current multiple parent deletes remove two groups' => static fn (TestRunner $t) => $t->same([2], array_column($run([['option_id' => 1], ['option_id' => 3]])['parent'], 'option_id')),
    'fk cascade trigger current multiple parent deletes keep loose child' => static fn (TestRunner $t) => $t->same([12, 13], array_column($run([['option_id' => 1], ['option_id' => 3]])['child'], 'meta_id')),
    'fk cascade trigger current multiple parent grandchild cascade keeps unrelated' => static fn (TestRunner $t) => $t->same([103, 104], array_column($run([['option_id' => 1], ['option_id' => 3]], [], $metaToDetail)['grandchild'], 'detail_id')),
    'fk cascade trigger current multiple parent changes include grandchildren' => static fn (TestRunner $t) => $t->same(9, $run([['option_id' => 1], ['option_id' => 3]], [], $metaToDetail)['changes']),
    'fk cascade trigger current ignores insert triggers' => static fn (TestRunner $t) => $t->same([], $run([['option_id' => 1]], [['timing' => 'before', 'event' => 'insert', 'action' => 'insert-audit']])['trigger_effects']),
    'fk cascade trigger current ignores update triggers' => static fn (TestRunner $t) => $t->same([], $run([['option_id' => 1]], [['timing' => 'after', 'event' => 'update', 'action' => 'insert-audit']])['trigger_effects']),
    'fk cascade trigger current nonmatching grandchild update has zero rows' => static fn (TestRunner $t) => $t->same(0, $run([['option_id' => 1]], [['timing' => 'before', 'event' => 'delete', 'action' => 'update-grandchild-key', 'grandchild_key' => 999, 'set_grandchild_key' => 12]], $metaToDetail)['trigger_effects'][0]['rows']),
    'fk cascade trigger current nonmatching grandchild delete has zero rows' => static fn (TestRunner $t) => $t->same(0, $run([['option_id' => 1]], [['timing' => 'before', 'event' => 'delete', 'action' => 'delete-grandchild', 'grandchild_key' => 999]], $metaToDetail)['trigger_effects'][0]['rows']),
    'fk cascade trigger current explicit old value can move to old option id' => static fn (TestRunner $t) => $t->same([1, 1, 1, 12, null, 14], array_column($run([['option_id' => 1]], [['timing' => 'before', 'event' => 'delete', 'action' => 'update-grandchild-key', 'grandchild_key' => 'old.meta_id', 'set_grandchild_key' => 'old.option_id']], $metaToDetail)['grandchild'], 'meta_id')),
    'fk cascade trigger current malformed parent key rejected' => static function (TestRunner $t) use ($optionToMeta, $run): void {
        $fk = $optionToMeta;
        $fk['parent_key'] = 'bad-key';
        $t->throws(InvalidArgumentException::class, static fn () => $run([['option_id' => 1]], [], null, $fk));
    },
    'fk cascade trigger current malformed child key rejected' => static function (TestRunner $t) use ($optionToMeta, $run): void {
        $fk = $optionToMeta;
        $fk['child_key'] = '1bad';
        $t->throws(InvalidArgumentException::class, static fn () => $run([['option_id' => 1]], [], null, $fk));
    },
    'fk cascade trigger current unsupported parent action rejected' => static function (TestRunner $t) use ($optionToMeta, $run): void {
        $fk = $optionToMeta;
        $fk['on_delete'] = 'SET NULL';
        $t->throws(InvalidArgumentException::class, static fn () => $run([['option_id' => 1]], [], null, $fk));
    },
    'fk cascade trigger current malformed grandchild parent key rejected' => static function (TestRunner $t) use ($metaToDetail, $run): void {
        $fk = $metaToDetail;
        $fk['parent_key'] = 'bad-key';
        $t->throws(InvalidArgumentException::class, static fn () => $run([['option_id' => 1]], [], $fk));
    },
    'fk cascade trigger current unsupported grandchild action rejected' => static function (TestRunner $t) use ($metaToDetail, $run): void {
        $fk = $metaToDetail;
        $fk['on_delete'] = 'RESTRICT';
        $t->throws(InvalidArgumentException::class, static fn () => $run([['option_id' => 1]], [], $fk));
    },
    'fk cascade trigger current grandchild trigger requires fk' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run([['option_id' => 1]], $beforeMoveDetails)),
    'fk cascade trigger current unsupported child trigger action rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run([['option_id' => 1]], [['timing' => 'before', 'event' => 'delete', 'action' => 'sideways']], $metaToDetail)),
    'fk cascade trigger current missing delete key column rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run([['missing' => 1]])),
    'fk cascade trigger current missing parent row column rejected' => static function (TestRunner $t) use ($meta, $auditDetail, $optionToMeta): void {
        $broken = [['option_name' => 'active_plugins']];
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteForeignKeyCascadeTriggerPlan::deleteParents($broken, $meta, $auditDetail, [['option_id' => 1]], $optionToMeta));
    },
    'fk cascade trigger current missing child row column rejected' => static function (TestRunner $t) use ($parents, $auditDetail, $optionToMeta): void {
        $broken = [['meta_id' => 10]];
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteForeignKeyCascadeTriggerPlan::deleteParents($parents, $broken, $auditDetail, [['option_id' => 1]], $optionToMeta));
    },
    'fk cascade trigger current missing grandchild row column rejected' => static function (TestRunner $t) use ($parents, $meta, $optionToMeta, $metaToDetail): void {
        $broken = [['detail_id' => 100]];
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteForeignKeyCascadeTriggerPlan::deleteParents($parents, $meta, $broken, [['option_id' => 1]], $optionToMeta, [], $metaToDetail));
    },
    'fk cascade trigger current audit row can read old meta key' => static fn (TestRunner $t) => $t->same('autoload', $run([['option_id' => 1]], [['timing' => 'before', 'event' => 'delete', 'action' => 'insert-audit', 'audit' => ['key' => 'old.meta_key']]])['audit'][0]['key']),
    'fk cascade trigger current after audit with moved details sees updated count' => static fn (TestRunner $t) => $t->same(6, $run([['option_id' => 1]], array_merge($beforeMoveDetails, $afterAudit), $metaToDetail)['audit'][0]['remaining_detail']),
    'fk cascade trigger current action order keeps child before grandchildren' => static fn (TestRunner $t) => $t->same(['cascade-delete-child', 'cascade-delete-grandchild'], array_slice(array_column($run([['option_id' => 1]], [], $metaToDetail)['cascade_actions'], 'action'), 0, 2)),
    'fk cascade trigger current remaining child order is stable' => static fn (TestRunner $t) => $t->same(['autoload', 'loose', 'theme'], array_column($run()['child'], 'meta_key')),
    'fk cascade trigger current remaining grandchild order is stable after cascade' => static fn (TestRunner $t) => $t->same(['rewrite-before', 'loose', 'theme'], array_column($run([['option_id' => 1]], [], $metaToDetail)['grandchild'], 'label')),
    'fk cascade trigger current cascade action keeps deleted child payload' => static fn (TestRunner $t) => $t->same('autoload', $run()['cascade_actions'][0]['child']['meta_key']),
];

return $tests;
