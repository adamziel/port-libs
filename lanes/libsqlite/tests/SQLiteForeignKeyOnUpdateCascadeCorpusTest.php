<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteForeignKeyOnUpdateCascadePlan;

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
$updateOne = [['old' => 1, 'new' => 101]];
$updateOneAndThree = [['old' => 1, 'new' => 101], ['old' => 3, 'new' => 103]];

$plan = static fn (?array $updates = null, ?array $fk = null): array => SQLiteForeignKeyOnUpdateCascadePlan::updateParents(
    $parents,
    $children,
    $updates ?? $updateOne,
    $fk ?? $cascade,
);

$tests = [
    'foreign key on update cascade rewrites parent key' => static function (TestRunner $t) use ($plan): void {
        $t->same([101, 2, 3], array_column($plan()['parent'], 'id'));
    },
    'foreign key on update cascade records deferred parent update' => static function (TestRunner $t) use ($plan): void {
        $t->same('update-parent', $plan()['deferred'][0]['operation']);
    },
    'foreign key on update cascade preserves deferred flag' => static function (TestRunner $t) use ($plan): void {
        $t->same(true, $plan()['deferred'][0]['deferred']);
    },
    'foreign key on update cascade normalizes action' => static function (TestRunner $t) use ($plan): void {
        $t->same('cascade', $plan()['deferred'][0]['action']);
    },
    'foreign key on update cascade records old key' => static function (TestRunner $t) use ($plan): void {
        $t->same(1, $plan()['deferred'][0]['old_key']);
    },
    'foreign key on update cascade records new key' => static function (TestRunner $t) use ($plan): void {
        $t->same(101, $plan()['deferred'][0]['new_key']);
    },
    'foreign key on update cascade updates first matching child' => static function (TestRunner $t) use ($plan): void {
        $t->same(101, $plan()['child'][0]['parent_id']);
    },
    'foreign key on update cascade updates second matching child' => static function (TestRunner $t) use ($plan): void {
        $t->same(101, $plan()['child'][1]['parent_id']);
    },
    'foreign key on update cascade preserves unrelated child key' => static function (TestRunner $t) use ($plan): void {
        $t->same(2, $plan()['child'][2]['parent_id']);
    },
    'foreign key on update cascade preserves null child key' => static function (TestRunner $t) use ($plan): void {
        $t->same(null, $plan()['child'][3]['parent_id']);
    },
    'foreign key on update cascade preserves later parent child key' => static function (TestRunner $t) use ($plan): void {
        $t->same(3, $plan()['child'][4]['parent_id']);
    },
    'foreign key on update cascade preserves child row order' => static function (TestRunner $t) use ($plan): void {
        $t->same([10, 11, 12, 13, 14], array_column($plan()['child'], 'child_id'));
    },
    'foreign key on update cascade records first child action' => static function (TestRunner $t) use ($plan): void {
        $t->same('cascade-update-child', $plan()['commit_actions'][0]['action']);
    },
    'foreign key on update cascade records first action old key' => static function (TestRunner $t) use ($plan): void {
        $t->same(1, $plan()['commit_actions'][0]['old_key']);
    },
    'foreign key on update cascade records first action new key' => static function (TestRunner $t) use ($plan): void {
        $t->same(101, $plan()['commit_actions'][0]['new_key']);
    },
    'foreign key on update cascade records second child action' => static function (TestRunner $t) use ($plan): void {
        $t->same('cascade-update-child', $plan()['commit_actions'][1]['action']);
    },
    'foreign key on update cascade reports parent and child changes' => static function (TestRunner $t) use ($plan): void {
        $t->same(3, $plan()['changes']);
    },
    'foreign key on update cascade has no violation' => static function (TestRunner $t) use ($plan): void {
        $t->same([], $plan()['violations']);
    },
    'foreign key on update cascade handles multiple parent groups' => static function (TestRunner $t) use ($plan, $updateOneAndThree): void {
        $t->same([101, 2, 103], array_column($plan($updateOneAndThree)['parent'], 'id'));
    },
    'foreign key on update cascade rewrites multiple child groups' => static function (TestRunner $t) use ($plan, $updateOneAndThree): void {
        $t->same([101, 101, 2, null, 103], array_column($plan($updateOneAndThree)['child'], 'parent_id'));
    },
    'foreign key on update cascade reports multiple change count' => static function (TestRunner $t) use ($plan, $updateOneAndThree): void {
        $t->same(5, $plan($updateOneAndThree)['changes']);
    },
    'foreign key on update cascade records multiple deferred events' => static function (TestRunner $t) use ($plan, $updateOneAndThree): void {
        $t->same([1, 3], array_column($plan($updateOneAndThree)['deferred'], 'old_key'));
    },
    'foreign key on update cascade missing update key is no-op' => static function (TestRunner $t) use ($plan): void {
        $t->same([1, 2, 3], array_column($plan([['old' => 99, 'new' => 199]])['parent'], 'id'));
    },
    'foreign key on update cascade missing update key creates no deferred event' => static function (TestRunner $t) use ($plan): void {
        $t->same([], $plan([['old' => 99, 'new' => 199]])['deferred']);
    },
    'foreign key on update cascade same key is no-op' => static function (TestRunner $t) use ($plan): void {
        $t->same([1, 2, 3], array_column($plan([['old' => 1, 'new' => 1]])['parent'], 'id'));
    },
    'foreign key on update cascade same key changes nothing' => static function (TestRunner $t) use ($plan): void {
        $t->same(0, $plan([['old' => 1, 'new' => 1]])['changes']);
    },
    'foreign key on update set null rewrites matching children to null' => static function (TestRunner $t) use ($plan, $cascade): void {
        $fk = $cascade;
        $fk['on_update'] = 'SET NULL';
        $t->same([null, null, 2, null, 3], array_column($plan(null, $fk)['child'], 'parent_id'));
    },
    'foreign key on update set null keeps child row count' => static function (TestRunner $t) use ($plan, $cascade): void {
        $fk = $cascade;
        $fk['on_update'] = 'SET NULL';
        $t->same(5, count($plan(null, $fk)['child']));
    },
    'foreign key on update set null records rewrite actions' => static function (TestRunner $t) use ($plan, $cascade): void {
        $fk = $cascade;
        $fk['on_update'] = 'SET NULL';
        $t->same(['set-null-child', 'set-null-child'], array_column($plan(null, $fk)['commit_actions'], 'action'));
    },
    'foreign key on update set null reports parent and child changes' => static function (TestRunner $t) use ($plan, $cascade): void {
        $fk = $cascade;
        $fk['on_update'] = 'SET NULL';
        $t->same(3, $plan(null, $fk)['changes']);
    },
    'foreign key on update set default rewrites matching children' => static function (TestRunner $t) use ($plan, $cascade): void {
        $fk = $cascade;
        $fk['on_update'] = 'SET DEFAULT';
        $fk['child_default'] = 0;
        $t->same([0, 0, 2, null, 3], array_column($plan(null, $fk)['child'], 'parent_id'));
    },
    'foreign key on update set default records default values' => static function (TestRunner $t) use ($plan, $cascade): void {
        $fk = $cascade;
        $fk['on_update'] = 'SET DEFAULT';
        $fk['child_default'] = 0;
        $t->same([0, 0], array_column($plan(null, $fk)['commit_actions'], 'default'));
    },
    'foreign key on update set default can satisfy existing default parent' => static function (TestRunner $t) use ($parents, $children, $cascade, $updateOne): void {
        $fk = $cascade;
        $fk['on_update'] = 'SET DEFAULT';
        $fk['child_default'] = 0;
        $rows = array_merge([['id' => 0, 'name' => 'default']], $parents);
        $result = SQLiteForeignKeyOnUpdateCascadePlan::updateParents($rows, $children, $updateOne, $fk);
        $t->same([], $result['violations']);
    },
    'foreign key on update set default reports violation when default parent missing' => static function (TestRunner $t) use ($plan, $cascade): void {
        $fk = $cascade;
        $fk['on_update'] = 'SET DEFAULT';
        $fk['child_default'] = 0;
        $t->same([0, 0], array_column($plan(null, $fk)['violations'], 'child_key'));
    },
    'foreign key on update no action preserves child rows' => static function (TestRunner $t) use ($plan, $cascade): void {
        $fk = $cascade;
        $fk['on_update'] = 'NO ACTION';
        $t->same([1, 1, 2, null, 3], array_column($plan(null, $fk)['child'], 'parent_id'));
    },
    'foreign key on update no action reports deferred violations' => static function (TestRunner $t) use ($plan, $cascade): void {
        $fk = $cascade;
        $fk['on_update'] = 'NO ACTION';
        $t->same([1, 1], array_column($plan(null, $fk)['violations'], 'child_key'));
    },
    'foreign key on update no action counts only parent changes' => static function (TestRunner $t) use ($plan, $cascade): void {
        $fk = $cascade;
        $fk['on_update'] = 'NO ACTION';
        $t->same(1, $plan(null, $fk)['changes']);
    },
    'foreign key on update restrict raises before deferred commit' => static function (TestRunner $t) use ($plan, $cascade): void {
        $fk = $cascade;
        $fk['on_update'] = 'RESTRICT';
        $t->throws(InvalidArgumentException::class, static fn () => $plan(null, $fk));
    },
    'foreign key on update restrict allows unreferenced parent update' => static function (TestRunner $t) use ($parents, $children, $cascade): void {
        $fk = $cascade;
        $fk['on_update'] = 'RESTRICT';
        $rows = array_merge($parents, [['id' => 4, 'name' => 'unused']]);
        $result = SQLiteForeignKeyOnUpdateCascadePlan::updateParents($rows, $children, [['old' => 4, 'new' => 104]], $fk);
        $t->same([1, 2, 3, 104], array_column($result['parent'], 'id'));
    },
    'foreign key on update rollback preview restores parent rows' => static function (TestRunner $t) use ($parents, $children, $updateOne, $cascade): void {
        $preview = SQLiteForeignKeyOnUpdateCascadePlan::updateParentsWithRollbackPreview($parents, $children, $updateOne, $cascade);
        $t->same([1, 2, 3], array_column($preview['rollback']['parent'], 'id'));
    },
    'foreign key on update rollback preview restores child rows' => static function (TestRunner $t) use ($parents, $children, $updateOne, $cascade): void {
        $preview = SQLiteForeignKeyOnUpdateCascadePlan::updateParentsWithRollbackPreview($parents, $children, $updateOne, $cascade);
        $t->same([1, 1, 2, null, 3], array_column($preview['rollback']['child'], 'parent_id'));
    },
    'foreign key on update rollback preview clears deferred queue' => static function (TestRunner $t) use ($parents, $children, $updateOne, $cascade): void {
        $preview = SQLiteForeignKeyOnUpdateCascadePlan::updateParentsWithRollbackPreview($parents, $children, $updateOne, $cascade);
        $t->same([], $preview['rollback']['deferred']);
    },
    'foreign key on update rollback preview records rollback action' => static function (TestRunner $t) use ($parents, $children, $updateOne, $cascade): void {
        $preview = SQLiteForeignKeyOnUpdateCascadePlan::updateParentsWithRollbackPreview($parents, $children, $updateOne, $cascade);
        $t->same('rollback', $preview['rollback']['commit_actions'][0]['action']);
    },
    'foreign key on update rejects malformed parent key' => static function (TestRunner $t) use ($plan, $cascade): void {
        $fk = $cascade;
        $fk['parent_key'] = 'bad-key';
        $t->throws(InvalidArgumentException::class, static fn () => $plan(null, $fk));
    },
    'foreign key on update rejects malformed child key' => static function (TestRunner $t) use ($plan, $cascade): void {
        $fk = $cascade;
        $fk['child_key'] = '1bad';
        $t->throws(InvalidArgumentException::class, static fn () => $plan(null, $fk));
    },
    'foreign key on update rejects unsupported action' => static function (TestRunner $t) use ($plan, $cascade): void {
        $fk = $cascade;
        $fk['on_update'] = 'explode';
        $t->throws(InvalidArgumentException::class, static fn () => $plan(null, $fk));
    },
    'foreign key on update rejects missing update old key' => static function (TestRunner $t) use ($plan): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan([['new' => 101]]));
    },
    'foreign key on update rejects missing update new key' => static function (TestRunner $t) use ($plan): void {
        $t->throws(InvalidArgumentException::class, static fn () => $plan([['old' => 1]]));
    },
    'foreign key on update rejects missing parent column' => static function (TestRunner $t) use ($parents, $children, $updateOne, $cascade): void {
        $broken = $parents;
        unset($broken[0]['id']);
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteForeignKeyOnUpdateCascadePlan::updateParents($broken, $children, $updateOne, $cascade));
    },
    'foreign key on update rejects missing child column' => static function (TestRunner $t) use ($parents, $children, $updateOne, $cascade): void {
        $broken = $children;
        unset($broken[0]['parent_id']);
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteForeignKeyOnUpdateCascadePlan::updateParents($parents, $broken, $updateOne, $cascade));
    },
];

return $tests;
