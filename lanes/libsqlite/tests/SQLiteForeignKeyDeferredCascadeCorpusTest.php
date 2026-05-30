<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteForeignKeyDeferredCascadePlan;

$parents = [
    ['id' => 1, 'name' => 'posts'],
    ['id' => 2, 'name' => 'plugins'],
    ['id' => 3, 'name' => 'themes'],
];
$children = [
    ['child_id' => 10, 'parent_id' => 1, 'name' => 'postmeta-a'],
    ['child_id' => 11, 'parent_id' => 1, 'name' => 'postmeta-b'],
    ['child_id' => 12, 'parent_id' => 2, 'name' => 'pluginmeta-a'],
    ['child_id' => 13, 'parent_id' => null, 'name' => 'orphan-null'],
    ['child_id' => 14, 'parent_id' => 3, 'name' => 'thememeta-a'],
];
$cascade = ['parent_key' => 'id', 'child_key' => 'parent_id', 'on_delete' => 'CASCADE', 'deferred' => true];
$deleteParentOne = [['id' => 1]];
$deleteParentsOneAndThree = [['id' => 1], ['id' => 3]];

$plan = static fn (?array $deleteKeys = null, ?array $fk = null): array => SQLiteForeignKeyDeferredCascadePlan::deleteParents(
    $parents,
    $children,
    $deleteKeys ?? $deleteParentOne,
    $fk ?? $cascade,
);

$tests = [
    'foreign key deferred cascade corpus deletes parent at statement boundary' => static function (TestRunner $t) use ($plan): void {
        $t->same([2, 3], array_column($plan()['parent'], 'id'));
    },
    'foreign key deferred cascade corpus records deferred parent delete event' => static function (TestRunner $t) use ($plan): void {
        $t->same('delete-parent', $plan()['deferred'][0]['operation']);
    },
    'foreign key deferred cascade corpus preserves deferred flag' => static function (TestRunner $t) use ($plan): void {
        $t->same(true, $plan()['deferred'][0]['deferred']);
    },
    'foreign key deferred cascade corpus normalizes action' => static function (TestRunner $t) use ($plan): void {
        $t->same('cascade', $plan()['deferred'][0]['action']);
    },
    'foreign key deferred cascade corpus removes first matching child' => static function (TestRunner $t) use ($plan): void {
        $t->same(false, in_array(10, array_column($plan()['child'], 'child_id'), true));
    },
    'foreign key deferred cascade corpus removes second matching child' => static function (TestRunner $t) use ($plan): void {
        $t->same(false, in_array(11, array_column($plan()['child'], 'child_id'), true));
    },
    'foreign key deferred cascade corpus preserves unrelated child' => static function (TestRunner $t) use ($plan): void {
        $t->same(true, in_array(12, array_column($plan()['child'], 'child_id'), true));
    },
    'foreign key deferred cascade corpus preserves null child key' => static function (TestRunner $t) use ($plan): void {
        $t->same(true, in_array(13, array_column($plan()['child'], 'child_id'), true));
    },
    'foreign key deferred cascade corpus preserves later parent child' => static function (TestRunner $t) use ($plan): void {
        $t->same(true, in_array(14, array_column($plan()['child'], 'child_id'), true));
    },
    'foreign key deferred cascade corpus orders remaining children' => static function (TestRunner $t) use ($plan): void {
        $t->same([12, 13, 14], array_column($plan()['child'], 'child_id'));
    },
    'foreign key deferred cascade corpus records first cascade action' => static function (TestRunner $t) use ($plan): void {
        $t->same('cascade-delete-child', $plan()['commit_actions'][0]['action']);
    },
    'foreign key deferred cascade corpus records second cascade child key' => static function (TestRunner $t) use ($plan): void {
        $t->same(1, $plan()['commit_actions'][1]['child_key']);
    },
    'foreign key deferred cascade corpus reports parent and child changes' => static function (TestRunner $t) use ($plan): void {
        $t->same(3, $plan()['changes']);
    },
    'foreign key deferred cascade corpus has no commit violation' => static function (TestRunner $t) use ($plan): void {
        $t->same([], $plan()['violations']);
    },
    'foreign key deferred cascade corpus deletes multiple parent groups' => static function (TestRunner $t) use ($plan, $deleteParentsOneAndThree): void {
        $t->same([2], array_column($plan($deleteParentsOneAndThree)['parent'], 'id'));
    },
    'foreign key deferred cascade corpus removes multiple parent children' => static function (TestRunner $t) use ($plan, $deleteParentsOneAndThree): void {
        $t->same([12, 13], array_column($plan($deleteParentsOneAndThree)['child'], 'child_id'));
    },
    'foreign key deferred cascade corpus reports multiple parent change count' => static function (TestRunner $t) use ($plan, $deleteParentsOneAndThree): void {
        $t->same(5, $plan($deleteParentsOneAndThree)['changes']);
    },
    'foreign key deferred cascade corpus missing delete key is a no-op' => static function (TestRunner $t) use ($plan): void {
        $t->same([1, 2, 3], array_column($plan([['id' => 99]])['parent'], 'id'));
    },
    'foreign key deferred cascade corpus missing delete key creates no deferred event' => static function (TestRunner $t) use ($plan): void {
        $t->same([], $plan([['id' => 99]])['deferred']);
    },
    'foreign key deferred set null rewrites matching child keys' => static function (TestRunner $t) use ($plan, $cascade): void {
        $fk = $cascade;
        $fk['on_delete'] = 'SET NULL';
        $t->same([null, null, 2, null, 3], array_column($plan([['id' => 1]], $fk)['child'], 'parent_id'));
    },
    'foreign key deferred set null keeps child row count' => static function (TestRunner $t) use ($plan, $cascade): void {
        $fk = $cascade;
        $fk['on_delete'] = 'SET NULL';
        $t->same(5, count($plan([['id' => 1]], $fk)['child']));
    },
    'foreign key deferred set null records rewrite actions' => static function (TestRunner $t) use ($plan, $cascade): void {
        $fk = $cascade;
        $fk['on_delete'] = 'SET NULL';
        $t->same(['set-null-child', 'set-null-child'], array_column($plan([['id' => 1]], $fk)['commit_actions'], 'action'));
    },
    'foreign key deferred set default rewrites matching child keys' => static function (TestRunner $t) use ($plan, $cascade): void {
        $fk = $cascade;
        $fk['on_delete'] = 'SET DEFAULT';
        $fk['child_default'] = 0;
        $t->same([0, 0, 2, null, 3], array_column($plan([['id' => 1]], $fk)['child'], 'parent_id'));
    },
    'foreign key deferred set default records default value' => static function (TestRunner $t) use ($plan, $cascade): void {
        $fk = $cascade;
        $fk['on_delete'] = 'SET DEFAULT';
        $fk['child_default'] = 0;
        $t->same([0, 0], array_column($plan([['id' => 1]], $fk)['commit_actions'], 'default'));
    },
    'foreign key deferred no action preserves child rows' => static function (TestRunner $t) use ($plan, $cascade): void {
        $fk = $cascade;
        $fk['on_delete'] = 'NO ACTION';
        $t->same([10, 11, 12, 13, 14], array_column($plan([['id' => 1]], $fk)['child'], 'child_id'));
    },
    'foreign key deferred no action reports deferred violations' => static function (TestRunner $t) use ($plan, $cascade): void {
        $fk = $cascade;
        $fk['on_delete'] = 'NO ACTION';
        $t->same([1, 1], array_column($plan([['id' => 1]], $fk)['violations'], 'child_key'));
    },
    'foreign key deferred no action counts only parent changes' => static function (TestRunner $t) use ($plan, $cascade): void {
        $fk = $cascade;
        $fk['on_delete'] = 'NO ACTION';
        $t->same(1, $plan([['id' => 1]], $fk)['changes']);
    },
    'foreign key immediate no action raises at statement boundary' => static function (TestRunner $t) use ($plan, $cascade): void {
        $fk = $cascade;
        $fk['on_delete'] = 'NO ACTION';
        $fk['deferred'] = false;
        $t->throws(InvalidArgumentException::class, static fn () => $plan([['id' => 1]], $fk));
    },
    'foreign key immediate no action allows unreferenced parent delete' => static function (TestRunner $t) use ($parents, $children, $cascade): void {
        $fk = $cascade;
        $fk['on_delete'] = 'NO ACTION';
        $fk['deferred'] = false;
        $rows = array_merge($parents, [['id' => 4, 'name' => 'unused']]);
        $result = SQLiteForeignKeyDeferredCascadePlan::deleteParents($rows, $children, [['id' => 4]], $fk);
        $t->same([1, 2, 3], array_column($result['parent'], 'id'));
    },
    'foreign key restrict raises before deferred commit' => static function (TestRunner $t) use ($plan, $cascade): void {
        $fk = $cascade;
        $fk['on_delete'] = 'RESTRICT';
        $t->throws(InvalidArgumentException::class, static fn () => $plan([['id' => 1]], $fk));
    },
    'foreign key restrict allows unreferenced parent delete' => static function (TestRunner $t) use ($parents, $children, $cascade): void {
        $fk = $cascade;
        $fk['on_delete'] = 'RESTRICT';
        $rows = array_merge($parents, [['id' => 4, 'name' => 'unused']]);
        $result = SQLiteForeignKeyDeferredCascadePlan::deleteParents($rows, $children, [['id' => 4]], $fk);
        $t->same([1, 2, 3], array_column($result['parent'], 'id'));
    },
    'foreign key deferred rollback preview restores parent rows' => static function (TestRunner $t) use ($parents, $children, $deleteParentOne, $cascade): void {
        $preview = SQLiteForeignKeyDeferredCascadePlan::deleteParentsWithRollbackPreview($parents, $children, $deleteParentOne, $cascade);
        $t->same([1, 2, 3], array_column($preview['rollback']['parent'], 'id'));
    },
    'foreign key deferred rollback preview restores child rows' => static function (TestRunner $t) use ($parents, $children, $deleteParentOne, $cascade): void {
        $preview = SQLiteForeignKeyDeferredCascadePlan::deleteParentsWithRollbackPreview($parents, $children, $deleteParentOne, $cascade);
        $t->same([10, 11, 12, 13, 14], array_column($preview['rollback']['child'], 'child_id'));
    },
    'foreign key deferred rollback preview clears deferred queue' => static function (TestRunner $t) use ($parents, $children, $deleteParentOne, $cascade): void {
        $preview = SQLiteForeignKeyDeferredCascadePlan::deleteParentsWithRollbackPreview($parents, $children, $deleteParentOne, $cascade);
        $t->same([], $preview['rollback']['deferred']);
    },
    'foreign key deferred rollback preview records rollback action' => static function (TestRunner $t) use ($parents, $children, $deleteParentOne, $cascade): void {
        $preview = SQLiteForeignKeyDeferredCascadePlan::deleteParentsWithRollbackPreview($parents, $children, $deleteParentOne, $cascade);
        $t->same('rollback', $preview['rollback']['commit_actions'][0]['action']);
    },
    'foreign key deferred cascade rejects malformed parent key' => static function (TestRunner $t) use ($plan, $cascade): void {
        $fk = $cascade;
        $fk['parent_key'] = 'bad-key';
        $t->throws(InvalidArgumentException::class, static fn () => $plan([['id' => 1]], $fk));
    },
    'foreign key deferred cascade rejects malformed child key' => static function (TestRunner $t) use ($plan, $cascade): void {
        $fk = $cascade;
        $fk['child_key'] = '1bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan([['id' => 1]], $fk));
    },
    'foreign key deferred cascade rejects unsupported action' => static function (TestRunner $t) use ($plan, $cascade): void {
        $fk = $cascade;
        $fk['on_delete'] = 'explode';
        $t->throws(InvalidArgumentException::class, static fn () => $plan([['id' => 1]], $fk));
    },
    'foreign key deferred cascade rejects missing parent column' => static function (TestRunner $t) use ($parents, $children, $cascade): void {
        $broken = $parents;
        unset($broken[0]['id']);
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteForeignKeyDeferredCascadePlan::deleteParents($broken, $children, [['id' => 1]], $cascade));
    },
    'foreign key deferred cascade rejects missing child column' => static function (TestRunner $t) use ($parents, $children, $cascade): void {
        $broken = $children;
        unset($broken[0]['parent_id']);
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteForeignKeyDeferredCascadePlan::deleteParents($parents, $broken, [['id' => 1]], $cascade));
    },
];

return $tests;
