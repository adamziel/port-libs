<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerForeignKeyInteractionPlan;

$parents = [
    ['id' => 1, 'name' => 'active_plugins'],
    ['id' => 2, 'name' => 'rewrite_rules'],
    ['id' => 3, 'name' => 'theme_mods'],
    ['id' => 4, 'name' => 'transient_timeout'],
];
$children = [
    ['child_id' => 10, 'parent_id' => 1, 'meta_key' => 'a'],
    ['child_id' => 11, 'parent_id' => 1, 'meta_key' => 'b'],
    ['child_id' => 12, 'parent_id' => 2, 'meta_key' => 'c'],
    ['child_id' => 13, 'parent_id' => 3, 'meta_key' => 'd'],
    ['child_id' => 14, 'parent_id' => null, 'meta_key' => 'loose'],
];
$cascade = ['parent_key' => 'id', 'child_key' => 'parent_id', 'on_delete' => 'CASCADE'];
$run = static fn (array $deleteKeys, array $fk, array $triggers = []): array => SQLiteTriggerForeignKeyInteractionPlan::deleteParents(
    $parents,
    $children,
    $deleteKeys,
    $fk,
    $triggers,
);

$beforeMove = [[
    'timing' => 'before',
    'event' => 'delete',
    'action' => 'update-child-key',
    'child_key' => 'old.parent_key',
    'set_child_key' => 2,
]];
$beforeDelete = [[
    'timing' => 'before',
    'event' => 'delete',
    'action' => 'delete-child',
    'child_key' => 'old.parent_key',
]];
$beforeAudit = [[
    'timing' => 'before',
    'event' => 'delete',
    'action' => 'insert-audit',
    'audit' => ['phase' => 'before', 'name' => 'old.name', 'remaining' => 'child_count'],
]];
$afterAudit = [[
    'timing' => 'after',
    'event' => 'delete',
    'action' => 'insert-audit',
    'audit' => ['phase' => 'after', 'id' => 'old.id', 'remaining' => 'child_count'],
]];

$tests = [
    'trigger fk interaction cascade removes parent row' => static function (TestRunner $t) use ($run, $cascade): void {
        $t->same([2, 3, 4], array_column($run([['id' => 1]], $cascade)['parent'], 'id'));
    },
    'trigger fk interaction cascade deletes matching child rows' => static function (TestRunner $t) use ($run, $cascade): void {
        $t->same([12, 13, 14], array_column($run([['id' => 1]], $cascade)['child'], 'child_id'));
    },
    'trigger fk interaction cascade records each child delete' => static function (TestRunner $t) use ($run, $cascade): void {
        $t->same(['cascade-delete-child', 'cascade-delete-child'], array_column($run([['id' => 1]], $cascade)['foreign_key_actions'], 'action'));
    },
    'trigger fk interaction cascade reports parent plus child changes' => static function (TestRunner $t) use ($run, $cascade): void {
        $t->same(3, $run([['id' => 1]], $cascade)['changes']);
    },
    'trigger fk interaction missing parent delete is no op' => static function (TestRunner $t) use ($run, $cascade): void {
        $t->same([1, 2, 3, 4], array_column($run([['id' => 99]], $cascade)['parent'], 'id'));
    },
    'trigger fk interaction missing parent has no fk actions' => static function (TestRunner $t) use ($run, $cascade): void {
        $t->same([], $run([['id' => 99]], $cascade)['foreign_key_actions']);
    },
    'trigger fk interaction before trigger rewrites child keys before cascade' => static function (TestRunner $t) use ($run, $cascade, $beforeMove): void {
        $t->same([2, 2, 2, 3, null], array_column($run([['id' => 1]], $cascade, $beforeMove)['child'], 'parent_id'));
    },
    'trigger fk interaction before rewrite prevents cascade deletes' => static function (TestRunner $t) use ($run, $cascade, $beforeMove): void {
        $t->same([], $run([['id' => 1]], $cascade, $beforeMove)['foreign_key_actions']);
    },
    'trigger fk interaction before rewrite reports trigger rows' => static function (TestRunner $t) use ($run, $cascade, $beforeMove): void {
        $t->same(2, $run([['id' => 1]], $cascade, $beforeMove)['trigger_effects'][0]['rows']);
    },
    'trigger fk interaction before rewrite counts trigger and parent changes' => static function (TestRunner $t) use ($run, $cascade, $beforeMove): void {
        $t->same(3, $run([['id' => 1]], $cascade, $beforeMove)['changes']);
    },
    'trigger fk interaction before delete removes child rows before cascade' => static function (TestRunner $t) use ($run, $cascade, $beforeDelete): void {
        $t->same([12, 13, 14], array_column($run([['id' => 1]], $cascade, $beforeDelete)['child'], 'child_id'));
    },
    'trigger fk interaction before delete leaves no cascade work' => static function (TestRunner $t) use ($run, $cascade, $beforeDelete): void {
        $t->same([], $run([['id' => 1]], $cascade, $beforeDelete)['foreign_key_actions']);
    },
    'trigger fk interaction before delete records trigger delete effect' => static function (TestRunner $t) use ($run, $cascade, $beforeDelete): void {
        $t->same('delete-child', $run([['id' => 1]], $cascade, $beforeDelete)['trigger_effects'][0]['action']);
    },
    'trigger fk interaction before delete counts child trigger deletes' => static function (TestRunner $t) use ($run, $cascade, $beforeDelete): void {
        $t->same(3, $run([['id' => 1]], $cascade, $beforeDelete)['changes']);
    },
    'trigger fk interaction set null rewrites children after parent delete' => static function (TestRunner $t) use ($run, $cascade): void {
        $fk = $cascade;
        $fk['on_delete'] = 'SET NULL';
        $t->same([null, null, 2, 3, null], array_column($run([['id' => 1]], $fk)['child'], 'parent_id'));
    },
    'trigger fk interaction set null records fk actions' => static function (TestRunner $t) use ($run, $cascade): void {
        $fk = $cascade;
        $fk['on_delete'] = 'SET NULL';
        $t->same(['set-null-child', 'set-null-child'], array_column($run([['id' => 1]], $fk)['foreign_key_actions'], 'action'));
    },
    'trigger fk interaction set null keeps child row count' => static function (TestRunner $t) use ($run, $cascade): void {
        $fk = $cascade;
        $fk['on_delete'] = 'SET NULL';
        $t->same(5, count($run([['id' => 1]], $fk)['child']));
    },
    'trigger fk interaction set default rewrites children after parent delete' => static function (TestRunner $t) use ($run, $cascade): void {
        $fk = $cascade;
        $fk['on_delete'] = 'SET DEFAULT';
        $fk['child_default'] = 0;
        $t->same([0, 0, 2, 3, null], array_column($run([['id' => 1]], $fk)['child'], 'parent_id'));
    },
    'trigger fk interaction set default records default value' => static function (TestRunner $t) use ($run, $cascade): void {
        $fk = $cascade;
        $fk['on_delete'] = 'SET DEFAULT';
        $fk['child_default'] = 0;
        $t->same([0, 0], array_column($run([['id' => 1]], $fk)['foreign_key_actions'], 'default'));
    },
    'trigger fk interaction no action preserves children' => static function (TestRunner $t) use ($run, $cascade): void {
        $fk = $cascade;
        $fk['on_delete'] = 'NO ACTION';
        $t->same([10, 11, 12, 13, 14], array_column($run([['id' => 1]], $fk)['child'], 'child_id'));
    },
    'trigger fk interaction no action records no fk actions' => static function (TestRunner $t) use ($run, $cascade): void {
        $fk = $cascade;
        $fk['on_delete'] = 'NO ACTION';
        $t->same([], $run([['id' => 1]], $fk)['foreign_key_actions']);
    },
    'trigger fk interaction restrict raises while children still reference parent' => static function (TestRunner $t) use ($run, $cascade): void {
        $fk = $cascade;
        $fk['on_delete'] = 'RESTRICT';
        $t->throws(InvalidArgumentException::class, static fn () => $run([['id' => 1]], $fk));
    },
    'trigger fk interaction before rewrite lets restrict delete proceed' => static function (TestRunner $t) use ($run, $cascade, $beforeMove): void {
        $fk = $cascade;
        $fk['on_delete'] = 'RESTRICT';
        $t->same([2, 3, 4], array_column($run([['id' => 1]], $fk, $beforeMove)['parent'], 'id'));
    },
    'trigger fk interaction before rewrite with restrict preserves moved children' => static function (TestRunner $t) use ($run, $cascade, $beforeMove): void {
        $fk = $cascade;
        $fk['on_delete'] = 'RESTRICT';
        $t->same([10, 11, 12, 13, 14], array_column($run([['id' => 1]], $fk, $beforeMove)['child'], 'child_id'));
    },
    'trigger fk interaction before delete lets restrict delete proceed' => static function (TestRunner $t) use ($run, $cascade, $beforeDelete): void {
        $fk = $cascade;
        $fk['on_delete'] = 'RESTRICT';
        $t->same([12, 13, 14], array_column($run([['id' => 1]], $fk, $beforeDelete)['child'], 'child_id'));
    },
    'trigger fk interaction before audit sees pre fk child count' => static function (TestRunner $t) use ($run, $cascade, $beforeAudit): void {
        $t->same(5, $run([['id' => 1]], $cascade, $beforeAudit)['audit'][0]['remaining']);
    },
    'trigger fk interaction before audit can read old parent name' => static function (TestRunner $t) use ($run, $cascade, $beforeAudit): void {
        $t->same('active_plugins', $run([['id' => 1]], $cascade, $beforeAudit)['audit'][0]['name']);
    },
    'trigger fk interaction after audit sees post cascade child count' => static function (TestRunner $t) use ($run, $cascade, $afterAudit): void {
        $t->same(3, $run([['id' => 1]], $cascade, $afterAudit)['audit'][0]['remaining']);
    },
    'trigger fk interaction after audit reads old parent id' => static function (TestRunner $t) use ($run, $cascade, $afterAudit): void {
        $t->same(1, $run([['id' => 1]], $cascade, $afterAudit)['audit'][0]['id']);
    },
    'trigger fk interaction before and after audits preserve timing order' => static function (TestRunner $t) use ($run, $cascade, $beforeAudit, $afterAudit): void {
        $result = $run([['id' => 1]], $cascade, array_merge($beforeAudit, $afterAudit));
        $t->same(['before', 'after'], array_column($result['audit'], 'phase'));
    },
    'trigger fk interaction after trigger can rewrite set-null rows' => static function (TestRunner $t) use ($run, $cascade): void {
        $fk = $cascade;
        $fk['on_delete'] = 'SET NULL';
        $afterMoveNulls = [[
            'timing' => 'after',
            'event' => 'delete',
            'action' => 'update-child-key',
            'child_key' => null,
            'set_child_key' => 2,
        ]];
        $t->same([2, 2, 2, 3, 2], array_column($run([['id' => 1]], $fk, $afterMoveNulls)['child'], 'parent_id'));
    },
    'trigger fk interaction after trigger update count includes fk nulls and existing null' => static function (TestRunner $t) use ($run, $cascade): void {
        $fk = $cascade;
        $fk['on_delete'] = 'SET NULL';
        $afterMoveNulls = [[
            'timing' => 'after',
            'event' => 'delete',
            'action' => 'update-child-key',
            'child_key' => null,
            'set_child_key' => 2,
        ]];
        $t->same(3, $run([['id' => 1]], $fk, $afterMoveNulls)['trigger_effects'][0]['rows']);
    },
    'trigger fk interaction multiple parent deletes apply sequentially' => static function (TestRunner $t) use ($run, $cascade): void {
        $t->same([3, 4], array_column($run([['id' => 1], ['id' => 2]], $cascade)['parent'], 'id'));
    },
    'trigger fk interaction multiple parent deletes cascade moved children later' => static function (TestRunner $t) use ($run, $cascade, $beforeMove): void {
        $t->same([13, 14], array_column($run([['id' => 1], ['id' => 2]], $cascade, $beforeMove)['child'], 'child_id'));
    },
    'trigger fk interaction multiple parent deletes record later cascade actions' => static function (TestRunner $t) use ($run, $cascade, $beforeMove): void {
        $t->same([2, 2, 2], array_column($run([['id' => 1], ['id' => 2]], $cascade, $beforeMove)['foreign_key_actions'], 'child_key'));
    },
    'trigger fk interaction multiple parent deletes count moved and cascaded rows' => static function (TestRunner $t) use ($run, $cascade, $beforeMove): void {
        $t->same(10, $run([['id' => 1], ['id' => 2]], $cascade, $beforeMove)['changes']);
    },
    'trigger fk interaction nonmatching trigger key has no effect' => static function (TestRunner $t) use ($run, $cascade): void {
        $trigger = [[
            'timing' => 'before',
            'event' => 'delete',
            'action' => 'update-child-key',
            'child_key' => 99,
            'set_child_key' => 2,
        ]];
        $t->same(0, $run([['id' => 1]], $cascade, $trigger)['trigger_effects'][0]['rows']);
    },
    'trigger fk interaction nonmatching trigger still cascades matching children' => static function (TestRunner $t) use ($run, $cascade): void {
        $trigger = [[
            'timing' => 'before',
            'event' => 'delete',
            'action' => 'update-child-key',
            'child_key' => 99,
            'set_child_key' => 2,
        ]];
        $t->same([12, 13, 14], array_column($run([['id' => 1]], $cascade, $trigger)['child'], 'child_id'));
    },
    'trigger fk interaction ignores non delete trigger event' => static function (TestRunner $t) use ($run, $cascade): void {
        $trigger = [['timing' => 'before', 'event' => 'insert', 'action' => 'delete-child']];
        $t->same([], $run([['id' => 1]], $cascade, $trigger)['trigger_effects']);
    },
    'trigger fk interaction rejects malformed parent key' => static function (TestRunner $t) use ($run, $cascade): void {
        $fk = $cascade;
        $fk['parent_key'] = 'bad-key';
        $t->throws(InvalidArgumentException::class, static fn () => $run([['id' => 1]], $fk));
    },
    'trigger fk interaction rejects malformed child key' => static function (TestRunner $t) use ($run, $cascade): void {
        $fk = $cascade;
        $fk['child_key'] = '1bad';
        $t->throws(InvalidArgumentException::class, static fn () => $run([['id' => 1]], $fk));
    },
    'trigger fk interaction rejects unsupported fk action' => static function (TestRunner $t) use ($run, $cascade): void {
        $fk = $cascade;
        $fk['on_delete'] = 'explode';
        $t->throws(InvalidArgumentException::class, static fn () => $run([['id' => 1]], $fk));
    },
    'trigger fk interaction rejects unsupported trigger action' => static function (TestRunner $t) use ($run, $cascade): void {
        $trigger = [['timing' => 'before', 'event' => 'delete', 'action' => 'sideways']];
        $t->throws(InvalidArgumentException::class, static fn () => $run([['id' => 1]], $cascade, $trigger));
    },
    'trigger fk interaction rejects missing delete key column' => static function (TestRunner $t) use ($run, $cascade): void {
        $t->throws(InvalidArgumentException::class, static fn () => $run([['missing' => 1]], $cascade));
    },
    'trigger fk interaction rejects missing parent row column' => static function (TestRunner $t) use ($parents, $children, $cascade): void {
        $broken = $parents;
        unset($broken[0]['id']);
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteTriggerForeignKeyInteractionPlan::deleteParents($broken, $children, [['id' => 1]], $cascade, []));
    },
    'trigger fk interaction rejects missing child row column' => static function (TestRunner $t) use ($parents, $children, $cascade): void {
        $broken = $children;
        unset($broken[0]['parent_id']);
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteTriggerForeignKeyInteractionPlan::deleteParents($parents, $broken, [['id' => 1]], $cascade, []));
    },
];

return $tests;
