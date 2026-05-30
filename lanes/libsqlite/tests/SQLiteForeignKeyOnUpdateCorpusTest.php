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
$cascade = ['parent_key' => 'id', 'child_key' => 'parent_id', 'on_update' => 'CASCADE', 'deferred' => true];
$updateOne = [['id' => 1, 'new_id' => 101, 'name' => 'posts-moved']];
$updateOneAndThree = [['id' => 1, 'new_id' => 101], ['id' => 3, 'new_id' => 303]];

$plan = static fn (?array $updates = null, ?array $fk = null): array => SQLiteForeignKeyDeferredCascadePlan::updateParents(
    $parents,
    $children,
    $updates ?? $updateOne,
    $fk ?? $cascade,
);

$tests = [
    'foreign key on update cascade rewrites parent key' => static function (TestRunner $t) use ($plan): void {
        $t->same([101, 2, 3], array_column($plan()['parent'], 'id'));
    },
    'foreign key on update cascade updates parent payload column' => static function (TestRunner $t) use ($plan): void {
        $t->same('posts-moved', $plan()['parent'][0]['name']);
    },
    'foreign key on update cascade records deferred parent event' => static function (TestRunner $t) use ($plan): void {
        $t->same('update-parent', $plan()['deferred'][0]['operation']);
    },
    'foreign key on update cascade records old parent key' => static function (TestRunner $t) use ($plan): void {
        $t->same(1, $plan()['deferred'][0]['old_parent_key']);
    },
    'foreign key on update cascade records new parent key' => static function (TestRunner $t) use ($plan): void {
        $t->same(101, $plan()['deferred'][0]['new_parent_key']);
    },
    'foreign key on update cascade preserves deferred flag' => static function (TestRunner $t) use ($plan): void {
        $t->same(true, $plan()['deferred'][0]['deferred']);
    },
    'foreign key on update cascade normalizes action' => static function (TestRunner $t) use ($plan): void {
        $t->same('cascade', $plan()['deferred'][0]['action']);
    },
    'foreign key on update cascade rewrites first matching child' => static function (TestRunner $t) use ($plan): void {
        $t->same(101, $plan()['child'][0]['parent_id']);
    },
    'foreign key on update cascade rewrites second matching child' => static function (TestRunner $t) use ($plan): void {
        $t->same(101, $plan()['child'][1]['parent_id']);
    },
    'foreign key on update cascade preserves unrelated child key' => static function (TestRunner $t) use ($plan): void {
        $t->same(2, $plan()['child'][2]['parent_id']);
    },
    'foreign key on update cascade preserves null child key' => static function (TestRunner $t) use ($plan): void {
        $t->same(null, $plan()['child'][3]['parent_id']);
    },
    'foreign key on update cascade preserves later child key' => static function (TestRunner $t) use ($plan): void {
        $t->same(3, $plan()['child'][4]['parent_id']);
    },
    'foreign key on update cascade keeps child row order' => static function (TestRunner $t) use ($plan): void {
        $t->same([10, 11, 12, 13, 14], array_column($plan()['child'], 'child_id'));
    },
    'foreign key on update cascade records first child update action' => static function (TestRunner $t) use ($plan): void {
        $t->same('cascade-update-child', $plan()['commit_actions'][0]['action']);
    },
    'foreign key on update cascade records second old child key' => static function (TestRunner $t) use ($plan): void {
        $t->same(1, $plan()['commit_actions'][1]['old_child_key']);
    },
    'foreign key on update cascade records second new child key' => static function (TestRunner $t) use ($plan): void {
        $t->same(101, $plan()['commit_actions'][1]['new_child_key']);
    },
    'foreign key on update cascade reports parent plus child changes' => static function (TestRunner $t) use ($plan): void {
        $t->same(3, $plan()['changes']);
    },
    'foreign key on update cascade has no commit violation' => static function (TestRunner $t) use ($plan): void {
        $t->same([], $plan()['violations']);
    },
    'foreign key on update cascade rewrites multiple parent groups' => static function (TestRunner $t) use ($plan, $updateOneAndThree): void {
        $t->same([101, 2, 303], array_column($plan($updateOneAndThree)['parent'], 'id'));
    },
    'foreign key on update cascade rewrites multiple child groups' => static function (TestRunner $t) use ($plan, $updateOneAndThree): void {
        $t->same([101, 101, 2, null, 303], array_column($plan($updateOneAndThree)['child'], 'parent_id'));
    },
    'foreign key on update cascade reports multiple parent change count' => static function (TestRunner $t) use ($plan, $updateOneAndThree): void {
        $t->same(5, $plan($updateOneAndThree)['changes']);
    },
    'foreign key on update cascade missing update key is a no-op' => static function (TestRunner $t) use ($plan): void {
        $t->same([1, 2, 3], array_column($plan([['id' => 99, 'new_id' => 199]])['parent'], 'id'));
    },
    'foreign key on update cascade missing update key creates no deferred event' => static function (TestRunner $t) use ($plan): void {
        $t->same([], $plan([['id' => 99, 'new_id' => 199]])['deferred']);
    },
    'foreign key on update cascade unchanged parent key updates payload only' => static function (TestRunner $t) use ($plan): void {
        $t->same('posts-renamed', $plan([['id' => 1, 'new_id' => 1, 'name' => 'posts-renamed']])['parent'][0]['name']);
    },
    'foreign key on update cascade unchanged parent key does not touch child rows' => static function (TestRunner $t) use ($plan): void {
        $t->same([1, 1, 2, null, 3], array_column($plan([['id' => 1, 'new_id' => 1, 'name' => 'posts-renamed']])['child'], 'parent_id'));
    },
    'foreign key on update cascade unchanged parent key has no deferred action' => static function (TestRunner $t) use ($plan): void {
        $t->same([], $plan([['id' => 1, 'new_id' => 1, 'name' => 'posts-renamed']])['deferred']);
    },
    'foreign key on update supports generic new key field' => static function (TestRunner $t) use ($plan): void {
        $t->same([201, 2, 3], array_column($plan([['id' => 1, 'new' => 201]])['parent'], 'id'));
    },
    'foreign key on update set null rewrites matching child keys' => static function (TestRunner $t) use ($plan, $cascade): void {
        $fk = $cascade;
        $fk['on_update'] = 'SET NULL';
        $t->same([null, null, 2, null, 3], array_column($plan([['id' => 1, 'new_id' => 101]], $fk)['child'], 'parent_id'));
    },
    'foreign key on update set null keeps child row count' => static function (TestRunner $t) use ($plan, $cascade): void {
        $fk = $cascade;
        $fk['on_update'] = 'SET NULL';
        $t->same(5, count($plan([['id' => 1, 'new_id' => 101]], $fk)['child']));
    },
    'foreign key on update set null records rewrite actions' => static function (TestRunner $t) use ($plan, $cascade): void {
        $fk = $cascade;
        $fk['on_update'] = 'SET NULL';
        $t->same(['set-null-child', 'set-null-child'], array_column($plan([['id' => 1, 'new_id' => 101]], $fk)['commit_actions'], 'action'));
    },
    'foreign key on update set default rewrites matching child keys' => static function (TestRunner $t) use ($plan, $cascade): void {
        $fk = $cascade;
        $fk['on_update'] = 'SET DEFAULT';
        $fk['child_default'] = 0;
        $t->same([0, 0, 2, null, 3], array_column($plan([['id' => 1, 'new_id' => 101]], $fk)['child'], 'parent_id'));
    },
    'foreign key on update set default records default value' => static function (TestRunner $t) use ($plan, $cascade): void {
        $fk = $cascade;
        $fk['on_update'] = 'SET DEFAULT';
        $fk['child_default'] = 0;
        $t->same([0, 0], array_column($plan([['id' => 1, 'new_id' => 101]], $fk)['commit_actions'], 'default'));
    },
    'foreign key on update no action preserves child rows' => static function (TestRunner $t) use ($plan, $cascade): void {
        $fk = $cascade;
        $fk['on_update'] = 'NO ACTION';
        $t->same([1, 1, 2, null, 3], array_column($plan([['id' => 1, 'new_id' => 101]], $fk)['child'], 'parent_id'));
    },
    'foreign key on update no action reports deferred violations' => static function (TestRunner $t) use ($plan, $cascade): void {
        $fk = $cascade;
        $fk['on_update'] = 'NO ACTION';
        $t->same([1, 1], array_column($plan([['id' => 1, 'new_id' => 101]], $fk)['violations'], 'child_key'));
    },
    'foreign key on update no action counts only parent changes' => static function (TestRunner $t) use ($plan, $cascade): void {
        $fk = $cascade;
        $fk['on_update'] = 'NO ACTION';
        $t->same(1, $plan([['id' => 1, 'new_id' => 101]], $fk)['changes']);
    },
    'foreign key on update immediate no action raises at statement boundary' => static function (TestRunner $t) use ($plan, $cascade): void {
        $fk = $cascade;
        $fk['on_update'] = 'NO ACTION';
        $fk['deferred'] = false;
        $t->throws(InvalidArgumentException::class, static fn () => $plan([['id' => 1, 'new_id' => 101]], $fk));
    },
    'foreign key on update immediate no action allows unreferenced parent update' => static function (TestRunner $t) use ($parents, $children, $cascade): void {
        $fk = $cascade;
        $fk['on_update'] = 'NO ACTION';
        $fk['deferred'] = false;
        $rows = array_merge($parents, [['id' => 4, 'name' => 'unused']]);
        $result = SQLiteForeignKeyDeferredCascadePlan::updateParents($rows, $children, [['id' => 4, 'new_id' => 404]], $fk);
        $t->same([1, 2, 3, 404], array_column($result['parent'], 'id'));
    },
    'foreign key on update restrict raises before deferred commit' => static function (TestRunner $t) use ($plan, $cascade): void {
        $fk = $cascade;
        $fk['on_update'] = 'RESTRICT';
        $t->throws(InvalidArgumentException::class, static fn () => $plan([['id' => 1, 'new_id' => 101]], $fk));
    },
    'foreign key on update restrict allows unreferenced parent update' => static function (TestRunner $t) use ($parents, $children, $cascade): void {
        $fk = $cascade;
        $fk['on_update'] = 'RESTRICT';
        $rows = array_merge($parents, [['id' => 4, 'name' => 'unused']]);
        $result = SQLiteForeignKeyDeferredCascadePlan::updateParents($rows, $children, [['id' => 4, 'new_id' => 404]], $fk);
        $t->same([1, 2, 3, 404], array_column($result['parent'], 'id'));
    },
    'foreign key on update rollback preview restores parent rows' => static function (TestRunner $t) use ($parents, $children, $updateOne, $cascade): void {
        $preview = SQLiteForeignKeyDeferredCascadePlan::updateParentsWithRollbackPreview($parents, $children, $updateOne, $cascade);
        $t->same([1, 2, 3], array_column($preview['rollback']['parent'], 'id'));
    },
    'foreign key on update rollback preview restores child rows' => static function (TestRunner $t) use ($parents, $children, $updateOne, $cascade): void {
        $preview = SQLiteForeignKeyDeferredCascadePlan::updateParentsWithRollbackPreview($parents, $children, $updateOne, $cascade);
        $t->same([1, 1, 2, null, 3], array_column($preview['rollback']['child'], 'parent_id'));
    },
    'foreign key on update rollback preview clears deferred queue' => static function (TestRunner $t) use ($parents, $children, $updateOne, $cascade): void {
        $preview = SQLiteForeignKeyDeferredCascadePlan::updateParentsWithRollbackPreview($parents, $children, $updateOne, $cascade);
        $t->same([], $preview['rollback']['deferred']);
    },
    'foreign key on update rollback preview records rollback action' => static function (TestRunner $t) use ($parents, $children, $updateOne, $cascade): void {
        $preview = SQLiteForeignKeyDeferredCascadePlan::updateParentsWithRollbackPreview($parents, $children, $updateOne, $cascade);
        $t->same('rollback-update', $preview['rollback']['commit_actions'][0]['action']);
    },
    'foreign key on update rejects malformed parent key' => static function (TestRunner $t) use ($plan, $cascade): void {
        $fk = $cascade;
        $fk['parent_key'] = 'bad-key';
        $t->throws(InvalidArgumentException::class, static fn () => $plan([['id' => 1, 'new_id' => 101]], $fk));
    },
    'foreign key on update rejects malformed child key' => static function (TestRunner $t) use ($plan, $cascade): void {
        $fk = $cascade;
        $fk['child_key'] = '1bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan([['id' => 1, 'new_id' => 101]], $fk));
    },
    'foreign key on update rejects unsupported action' => static function (TestRunner $t) use ($plan, $cascade): void {
        $fk = $cascade;
        $fk['on_update'] = 'explode';
        $t->throws(InvalidArgumentException::class, static fn () => $plan([['id' => 1, 'new_id' => 101]], $fk));
    },
    'foreign key on update rejects missing parent column' => static function (TestRunner $t) use ($parents, $children, $cascade): void {
        $broken = $parents;
        unset($broken[0]['id']);
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteForeignKeyDeferredCascadePlan::updateParents($broken, $children, [['id' => 1, 'new_id' => 101]], $cascade));
    },
    'foreign key on update rejects missing child column' => static function (TestRunner $t) use ($parents, $children, $cascade): void {
        $broken = $children;
        unset($broken[0]['parent_id']);
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteForeignKeyDeferredCascadePlan::updateParents($parents, $broken, [['id' => 1, 'new_id' => 101]], $cascade));
    },
];

return $tests;
