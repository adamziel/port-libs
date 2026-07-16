<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteForeignKeyCascadeUpdateTriggerPlan;

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
$details = [
    ['detail_id' => 100, 'option_id' => 1, 'label' => 'autoload-before'],
    ['detail_id' => 101, 'option_id' => 1, 'label' => 'autoload-after'],
    ['detail_id' => 102, 'option_id' => 2, 'label' => 'rewrite-before'],
    ['detail_id' => 103, 'option_id' => null, 'label' => 'loose'],
    ['detail_id' => 104, 'option_id' => 3, 'label' => 'theme'],
];
$optionToMeta = ['parent_key' => 'option_id', 'child_key' => 'option_id', 'on_update' => 'CASCADE'];
$metaToDetail = ['parent_key' => 'option_id', 'child_key' => 'option_id', 'on_update' => 'CASCADE'];
$beforeAudit = [[
    'timing' => 'before',
    'event' => 'update',
    'action' => 'insert-audit',
    'audit' => ['phase' => 'before', 'meta_id' => 'old.meta_id', 'old_option' => 'old.option_id', 'new_option' => 'new.option_id', 'detail_count' => 'grandchild_count'],
]];
$afterAudit = [[
    'timing' => 'after',
    'event' => 'update',
    'action' => 'insert-audit',
    'audit' => ['phase' => 'after', 'meta_id' => 'new.meta_id', 'old_option' => 'old.option_id', 'new_option' => 'new.option_id', 'detail_count' => 'grandchild_count'],
]];
$beforeMoveDetails = [[
    'timing' => 'before',
    'event' => 'update',
    'action' => 'update-grandchild-key',
    'grandchild_key' => 'old.option_id',
    'set_grandchild_key' => 'new.option_id',
]];
$afterDeleteNewDetails = [[
    'timing' => 'after',
    'event' => 'update',
    'action' => 'delete-grandchild',
    'grandchild_key' => 'new.option_id',
]];

$run = static fn (
    array $updates = [['option_id' => 1, 'new_option_id' => 20]],
    array $triggers = [],
    ?array $grandchildFk = null,
    ?array $fk = null,
): array => SQLiteForeignKeyCascadeUpdateTriggerPlan::updateParentKeys(
    $parents,
    $meta,
    $details,
    $updates,
    $fk ?? $optionToMeta,
    $triggers,
    $grandchildFk,
);

return [
    'fk cascade update trigger next29 updates parent key' => static fn (TestRunner $t) => $t->same([20, 2, 3], array_column($run()['parent'], 'option_id')),
    'fk cascade update trigger next29 cascades child keys' => static fn (TestRunner $t) => $t->same([20, 20, 2, null, 3], array_column($run()['child'], 'option_id')),
    'fk cascade update trigger next29 preserves child order' => static fn (TestRunner $t) => $t->same([10, 11, 12, 13, 14], array_column($run()['child'], 'meta_id')),
    'fk cascade update trigger next29 preserves grandchildren without grandchild fk' => static fn (TestRunner $t) => $t->same([1, 1, 2, null, 3], array_column($run()['grandchild'], 'option_id')),
    'fk cascade update trigger next29 records two child cascades' => static fn (TestRunner $t) => $t->same(['cascade-update-child', 'cascade-update-child'], array_column($run()['cascade_actions'], 'action')),
    'fk cascade update trigger next29 records old parent key' => static fn (TestRunner $t) => $t->same(1, $run()['cascade_actions'][0]['old_parent_key']),
    'fk cascade update trigger next29 records new parent key' => static fn (TestRunner $t) => $t->same(20, $run()['cascade_actions'][0]['new_parent_key']),
    'fk cascade update trigger next29 keeps old child payload' => static fn (TestRunner $t) => $t->same(1, $run()['cascade_actions'][0]['old_child']['option_id']),
    'fk cascade update trigger next29 keeps new child payload' => static fn (TestRunner $t) => $t->same(20, $run()['cascade_actions'][0]['new_child']['option_id']),
    'fk cascade update trigger next29 counts parent and child updates' => static fn (TestRunner $t) => $t->same(3, $run()['changes']),
    'fk cascade update trigger next29 missing parent is no op' => static fn (TestRunner $t) => $t->same([1, 2, 3], array_column($run([['option_id' => 99, 'new_option_id' => 20]])['parent'], 'option_id')),
    'fk cascade update trigger next29 missing parent records no actions' => static fn (TestRunner $t) => $t->same([], $run([['option_id' => 99, 'new_option_id' => 20]])['cascade_actions']),
    'fk cascade update trigger next29 no action leaves child rows' => static function (TestRunner $t) use ($optionToMeta, $run): void {
        $fk = $optionToMeta;
        $fk['on_update'] = 'NO ACTION';
        $t->same([1, 1, 2, null, 3], array_column($run([['option_id' => 1, 'new_option_id' => 20]], [], null, $fk)['child'], 'option_id'));
    },
    'fk cascade update trigger next29 no action still updates parent' => static function (TestRunner $t) use ($optionToMeta, $run): void {
        $fk = $optionToMeta;
        $fk['on_update'] = 'NO ACTION';
        $t->same([20, 2, 3], array_column($run([['option_id' => 1, 'new_option_id' => 20]], [], null, $fk)['parent'], 'option_id'));
    },
    'fk cascade update trigger next29 unchanged parent key skips child cascade' => static fn (TestRunner $t) => $t->same([], $run([['option_id' => 1, 'new_option_id' => 1]])['cascade_actions']),
    'fk cascade update trigger next29 unchanged parent key counts parent write' => static fn (TestRunner $t) => $t->same(1, $run([['option_id' => 1, 'new_option_id' => 1]])['changes']),
    'fk cascade update trigger next29 supports generic new parent key field' => static fn (TestRunner $t) => $t->same([30, 30, 2, null, 3], array_column($run([['option_id' => 1, 'new_parent_key' => 30]])['child'], 'option_id')),
    'fk cascade update trigger next29 before audit fires for each child' => static fn (TestRunner $t) => $t->same([10, 11], array_column($run([['option_id' => 1, 'new_option_id' => 20]], $beforeAudit)['audit'], 'meta_id')),
    'fk cascade update trigger next29 before audit sees old key' => static fn (TestRunner $t) => $t->same([1, 1], array_column($run([['option_id' => 1, 'new_option_id' => 20]], $beforeAudit)['audit'], 'old_option')),
    'fk cascade update trigger next29 before audit sees new key' => static fn (TestRunner $t) => $t->same([20, 20], array_column($run([['option_id' => 1, 'new_option_id' => 20]], $beforeAudit)['audit'], 'new_option')),
    'fk cascade update trigger next29 before audit sees original detail count' => static fn (TestRunner $t) => $t->same(5, $run([['option_id' => 1, 'new_option_id' => 20]], $beforeAudit)['audit'][0]['detail_count']),
    'fk cascade update trigger next29 after audit fires for each child' => static fn (TestRunner $t) => $t->same([10, 11], array_column($run([['option_id' => 1, 'new_option_id' => 20]], $afterAudit)['audit'], 'meta_id')),
    'fk cascade update trigger next29 before after audit order follows each child' => static fn (TestRunner $t) => $t->same(['before', 'after', 'before', 'after'], array_column($run([['option_id' => 1, 'new_option_id' => 20]], array_merge($beforeAudit, $afterAudit))['audit'], 'phase')),
    'fk cascade update trigger next29 trigger effects include audit rows' => static fn (TestRunner $t) => $t->same([1, 1], array_column($run([['option_id' => 1, 'new_option_id' => 20]], $beforeAudit)['trigger_effects'], 'rows')),
    'fk cascade update trigger next29 trigger changes count audit rows' => static fn (TestRunner $t) => $t->same(5, $run([['option_id' => 1, 'new_option_id' => 20]], $beforeAudit)['changes']),
    'fk cascade update trigger next29 grandchild cascade updates matching details' => static fn (TestRunner $t) => $t->same([20, 20, 2, null, 3], array_column($run([['option_id' => 1, 'new_option_id' => 20]], [], $metaToDetail)['grandchild'], 'option_id')),
    'fk cascade update trigger next29 grandchild cascade records actions' => static fn (TestRunner $t) => $t->same(['cascade-update-child', 'cascade-update-grandchild', 'cascade-update-grandchild', 'cascade-update-child'], array_column($run([['option_id' => 1, 'new_option_id' => 20]], [], $metaToDetail)['cascade_actions'], 'action')),
    'fk cascade update trigger next29 grandchild cascade counts rows once' => static fn (TestRunner $t) => $t->same(5, $run([['option_id' => 1, 'new_option_id' => 20]], [], $metaToDetail)['changes']),
    'fk cascade update trigger next29 after audit sees post grandchild cascade count' => static fn (TestRunner $t) => $t->same(5, $run([['option_id' => 1, 'new_option_id' => 20]], $afterAudit, $metaToDetail)['audit'][0]['detail_count']),
    'fk cascade update trigger next29 before trigger move prevents grandchild cascade' => static fn (TestRunner $t) => $t->same(['cascade-update-child', 'cascade-update-child'], array_column($run([['option_id' => 1, 'new_option_id' => 20]], $beforeMoveDetails, $metaToDetail)['cascade_actions'], 'action')),
    'fk cascade update trigger next29 before trigger move updates details' => static fn (TestRunner $t) => $t->same([20, 20, 2, null, 3], array_column($run([['option_id' => 1, 'new_option_id' => 20]], $beforeMoveDetails, $metaToDetail)['grandchild'], 'option_id')),
    'fk cascade update trigger next29 before trigger move records affected rows' => static fn (TestRunner $t) => $t->same([2, 0], array_column($run([['option_id' => 1, 'new_option_id' => 20]], $beforeMoveDetails, $metaToDetail)['trigger_effects'], 'rows')),
    'fk cascade update trigger next29 before trigger move changes include detail updates' => static fn (TestRunner $t) => $t->same(5, $run([['option_id' => 1, 'new_option_id' => 20]], $beforeMoveDetails, $metaToDetail)['changes']),
    'fk cascade update trigger next29 after trigger can delete cascaded details' => static fn (TestRunner $t) => $t->same([2, null, 3], array_column($run([['option_id' => 1, 'new_option_id' => 20]], $afterDeleteNewDetails, $metaToDetail)['grandchild'], 'option_id')),
    'fk cascade update trigger next29 after trigger records deleted details once' => static fn (TestRunner $t) => $t->same([2, 0], array_column($run([['option_id' => 1, 'new_option_id' => 20]], $afterDeleteNewDetails, $metaToDetail)['trigger_effects'], 'rows')),
    'fk cascade update trigger next29 after trigger changes include deletes' => static fn (TestRunner $t) => $t->same(7, $run([['option_id' => 1, 'new_option_id' => 20]], $afterDeleteNewDetails, $metaToDetail)['changes']),
    'fk cascade update trigger next29 multiple parent updates cascade both groups' => static fn (TestRunner $t) => $t->same([20, 20, 22, null, 3], array_column($run([['option_id' => 1, 'new_option_id' => 20], ['option_id' => 2, 'new_option_id' => 22]])['child'], 'option_id')),
    'fk cascade update trigger next29 multiple parent updates cascade grandchildren' => static fn (TestRunner $t) => $t->same([20, 20, 22, null, 3], array_column($run([['option_id' => 1, 'new_option_id' => 20], ['option_id' => 2, 'new_option_id' => 22]], [], $metaToDetail)['grandchild'], 'option_id')),
    'fk cascade update trigger next29 multiple parent changes include all cascades' => static fn (TestRunner $t) => $t->same(8, $run([['option_id' => 1, 'new_option_id' => 20], ['option_id' => 2, 'new_option_id' => 22]], [], $metaToDetail)['changes']),
    'fk cascade update trigger next29 ignores delete triggers' => static fn (TestRunner $t) => $t->same([], $run([['option_id' => 1, 'new_option_id' => 20]], [['timing' => 'before', 'event' => 'delete', 'action' => 'insert-audit']])['trigger_effects']),
    'fk cascade update trigger next29 ignores insert triggers' => static fn (TestRunner $t) => $t->same([], $run([['option_id' => 1, 'new_option_id' => 20]], [['timing' => 'after', 'event' => 'insert', 'action' => 'insert-audit']])['trigger_effects']),
    'fk cascade update trigger next29 nonmatching grandchild update has zero rows' => static fn (TestRunner $t) => $t->same(0, $run([['option_id' => 1, 'new_option_id' => 20]], [['timing' => 'before', 'event' => 'update', 'action' => 'update-grandchild-key', 'grandchild_key' => 999, 'set_grandchild_key' => 20]], $metaToDetail)['trigger_effects'][0]['rows']),
    'fk cascade update trigger next29 nonmatching grandchild delete has zero rows' => static fn (TestRunner $t) => $t->same(0, $run([['option_id' => 1, 'new_option_id' => 20]], [['timing' => 'after', 'event' => 'update', 'action' => 'delete-grandchild', 'grandchild_key' => 999]], $metaToDetail)['trigger_effects'][0]['rows']),
    'fk cascade update trigger next29 explicit old value can restore details' => static fn (TestRunner $t) => $t->same([1, 1, 2, null, 3], array_column($run([['option_id' => 1, 'new_option_id' => 20]], [['timing' => 'after', 'event' => 'update', 'action' => 'update-grandchild-key', 'grandchild_key' => 'new.option_id', 'set_grandchild_key' => 'old.option_id']], $metaToDetail)['grandchild'], 'option_id')),
    'fk cascade update trigger next29 malformed parent key rejected' => static function (TestRunner $t) use ($optionToMeta, $run): void {
        $fk = $optionToMeta;
        $fk['parent_key'] = 'bad-key';
        $t->throws(InvalidArgumentException::class, static fn () => $run([['option_id' => 1, 'new_option_id' => 20]], [], null, $fk));
    },
    'fk cascade update trigger next29 malformed child key rejected' => static function (TestRunner $t) use ($optionToMeta, $run): void {
        $fk = $optionToMeta;
        $fk['child_key'] = '1bad';
        $t->throws(InvalidArgumentException::class, static fn () => $run([['option_id' => 1, 'new_option_id' => 20]], [], null, $fk));
    },
    'fk cascade update trigger next29 unsupported parent action rejected' => static function (TestRunner $t) use ($optionToMeta, $run): void {
        $fk = $optionToMeta;
        $fk['on_update'] = 'SET NULL';
        $t->throws(InvalidArgumentException::class, static fn () => $run([['option_id' => 1, 'new_option_id' => 20]], [], null, $fk));
    },
    'fk cascade update trigger next29 malformed grandchild parent key rejected' => static function (TestRunner $t) use ($metaToDetail, $run): void {
        $fk = $metaToDetail;
        $fk['parent_key'] = 'bad-key';
        $t->throws(InvalidArgumentException::class, static fn () => $run([['option_id' => 1, 'new_option_id' => 20]], [], $fk));
    },
    'fk cascade update trigger next29 unsupported grandchild action rejected' => static function (TestRunner $t) use ($metaToDetail, $run): void {
        $fk = $metaToDetail;
        $fk['on_update'] = 'RESTRICT';
        $t->throws(InvalidArgumentException::class, static fn () => $run([['option_id' => 1, 'new_option_id' => 20]], [], $fk));
    },
    'fk cascade update trigger next29 grandchild trigger requires fk' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run([['option_id' => 1, 'new_option_id' => 20]], $beforeMoveDetails)),
    'fk cascade update trigger next29 unsupported child trigger action rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run([['option_id' => 1, 'new_option_id' => 20]], [['timing' => 'before', 'event' => 'update', 'action' => 'sideways']], $metaToDetail)),
    'fk cascade update trigger next29 missing update key column rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run([['missing' => 1, 'new_option_id' => 20]])),
    'fk cascade update trigger next29 missing new key column rejected' => static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $run([['option_id' => 1]])),
    'fk cascade update trigger next29 missing parent row column rejected' => static function (TestRunner $t) use ($meta, $details, $optionToMeta): void {
        $broken = [['option_name' => 'active_plugins']];
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteForeignKeyCascadeUpdateTriggerPlan::updateParentKeys($broken, $meta, $details, [['option_id' => 1, 'new_option_id' => 20]], $optionToMeta));
    },
    'fk cascade update trigger next29 missing child row column rejected' => static function (TestRunner $t) use ($parents, $details, $optionToMeta): void {
        $broken = [['meta_id' => 10]];
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteForeignKeyCascadeUpdateTriggerPlan::updateParentKeys($parents, $broken, $details, [['option_id' => 1, 'new_option_id' => 20]], $optionToMeta));
    },
    'fk cascade update trigger next29 missing grandchild row column rejected' => static function (TestRunner $t) use ($parents, $meta, $optionToMeta, $metaToDetail): void {
        $broken = [['detail_id' => 100]];
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteForeignKeyCascadeUpdateTriggerPlan::updateParentKeys($parents, $meta, $broken, [['option_id' => 1, 'new_option_id' => 20]], $optionToMeta, [], $metaToDetail));
    },
    'fk cascade update trigger next29 audit row can read old meta key' => static fn (TestRunner $t) => $t->same('autoload', $run([['option_id' => 1, 'new_option_id' => 20]], [['timing' => 'before', 'event' => 'update', 'action' => 'insert-audit', 'audit' => ['key' => 'old.meta_key']]])['audit'][0]['key']),
    'fk cascade update trigger next29 audit row can read new option key' => static fn (TestRunner $t) => $t->same(20, $run([['option_id' => 1, 'new_option_id' => 20]], [['timing' => 'after', 'event' => 'update', 'action' => 'insert-audit', 'audit' => ['new_option' => 'new.option_id']]])['audit'][0]['new_option']),
    'fk cascade update trigger next29 action order keeps child before grandchildren' => static fn (TestRunner $t) => $t->same(['cascade-update-child', 'cascade-update-grandchild'], array_slice(array_column($run([['option_id' => 1, 'new_option_id' => 20]], [], $metaToDetail)['cascade_actions'], 'action'), 0, 2)),
    'fk cascade update trigger next29 remaining loose child is stable' => static fn (TestRunner $t) => $t->same('loose', $run()['child'][3]['meta_key']),
    'fk cascade update trigger next29 remaining grandchild order is stable' => static fn (TestRunner $t) => $t->same(['autoload-before', 'autoload-after', 'rewrite-before', 'loose', 'theme'], array_column($run([['option_id' => 1, 'new_option_id' => 20]], [], $metaToDetail)['grandchild'], 'label')),
    'fk cascade update trigger next29 cascade action keeps parent payload' => static fn (TestRunner $t) => $t->same('active_plugins', $run()['cascade_actions'][0]['parent']['option_name']),
];
