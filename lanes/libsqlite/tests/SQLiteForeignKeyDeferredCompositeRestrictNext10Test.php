<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteForeignKeyDeferredCascadePlan;

$parents = [
    ['site_id' => 1, 'option_name' => 'active_plugins', 'label' => 'site plugins'],
    ['site_id' => 1, 'option_name' => 'theme_mods', 'label' => 'site theme'],
    ['site_id' => 2, 'option_name' => 'active_plugins', 'label' => 'network plugins'],
    ['site_id' => 3, 'option_name' => 'rewrite_rules', 'label' => 'rewrite rules'],
];
$children = [
    ['meta_id' => 10, 'child_site_id' => 1, 'child_option_name' => 'active_plugins', 'payload' => 'plugin-a'],
    ['meta_id' => 11, 'child_site_id' => 1, 'child_option_name' => 'active_plugins', 'payload' => 'plugin-b'],
    ['meta_id' => 12, 'child_site_id' => 1, 'child_option_name' => 'theme_mods', 'payload' => 'theme'],
    ['meta_id' => 13, 'child_site_id' => 2, 'child_option_name' => 'active_plugins', 'payload' => 'network-plugin'],
    ['meta_id' => 14, 'child_site_id' => null, 'child_option_name' => 'active_plugins', 'payload' => 'null-site'],
    ['meta_id' => 15, 'child_site_id' => 1, 'child_option_name' => null, 'payload' => 'null-option'],
    ['meta_id' => 16, 'child_site_id' => 9, 'child_option_name' => 'missing', 'payload' => 'broken'],
];
$compositeFk = [
    'parent_key' => ['site_id', 'option_name'],
    'child_key' => ['child_site_id', 'child_option_name'],
    'on_delete' => 'RESTRICT',
    'on_update' => 'RESTRICT',
    'deferred' => true,
];
$deletePluginKey = [['site_id' => 1, 'option_name' => 'active_plugins']];
$deleteRewriteKey = [['site_id' => 3, 'option_name' => 'rewrite_rules']];

$deletePlan = static fn (array $deleteKeys, array $fk): array => SQLiteForeignKeyDeferredCascadePlan::deleteParents(
    $parents,
    $children,
    $deleteKeys,
    $fk,
);
$updatePlan = static fn (array $updates, array $fk): array => SQLiteForeignKeyDeferredCascadePlan::updateParents(
    $parents,
    $children,
    $updates,
    $fk,
);

$tests = [
    'composite deferred restrict delete raises before commit' => static function (TestRunner $t) use ($deletePlan, $deletePluginKey, $compositeFk): void {
        $t->throws(InvalidArgumentException::class, static fn () => $deletePlan($deletePluginKey, $compositeFk));
    },
    'composite deferred restrict delete allows unreferenced composite parent' => static function (TestRunner $t) use ($deletePlan, $deleteRewriteKey, $compositeFk): void {
        $t->same([[3, 'rewrite_rules']], array_column($deletePlan($deleteRewriteKey, $compositeFk)['deferred'], 'parent_key'));
    },
    'composite deferred restrict delete removes only unreferenced parent row' => static function (TestRunner $t) use ($deletePlan, $deleteRewriteKey, $compositeFk): void {
        $t->same([1, 1, 2], array_column($deletePlan($deleteRewriteKey, $compositeFk)['parent'], 'site_id'));
    },
    'composite deferred restrict delete preserves child rows when no match exists' => static function (TestRunner $t) use ($deletePlan, $deleteRewriteKey, $compositeFk): void {
        $t->same([10, 11, 12, 13, 14, 15, 16], array_column($deletePlan($deleteRewriteKey, $compositeFk)['child'], 'meta_id'));
    },
    'composite deferred restrict delete counts only parent change' => static function (TestRunner $t) use ($deletePlan, $deleteRewriteKey, $compositeFk): void {
        $t->same(1, $deletePlan($deleteRewriteKey, $compositeFk)['changes']);
    },
    'composite deferred no action records two full-key delete violations' => static function (TestRunner $t) use ($deletePlan, $deletePluginKey, $compositeFk): void {
        $fk = $compositeFk;
        $fk['on_delete'] = 'NO ACTION';
        $t->same([[1, 'active_plugins'], [1, 'active_plugins'], [9, 'missing']], array_column($deletePlan($deletePluginKey, $fk)['violations'], 'child_key'));
    },
    'composite deferred no action ignores partial null child site' => static function (TestRunner $t) use ($deletePlan, $deletePluginKey, $compositeFk): void {
        $fk = $compositeFk;
        $fk['on_delete'] = 'NO ACTION';
        $t->same(false, in_array([null, 'active_plugins'], array_column($deletePlan($deletePluginKey, $fk)['violations'], 'child_key'), true));
    },
    'composite deferred no action ignores partial null child option' => static function (TestRunner $t) use ($deletePlan, $deletePluginKey, $compositeFk): void {
        $fk = $compositeFk;
        $fk['on_delete'] = 'NO ACTION';
        $t->same(false, in_array([1, null], array_column($deletePlan($deletePluginKey, $fk)['violations'], 'child_key'), true));
    },
    'composite deferred cascade deletes both matching child rows' => static function (TestRunner $t) use ($deletePlan, $deletePluginKey, $compositeFk): void {
        $fk = $compositeFk;
        $fk['on_delete'] = 'CASCADE';
        $t->same([12, 13, 14, 15, 16], array_column($deletePlan($deletePluginKey, $fk)['child'], 'meta_id'));
    },
    'composite deferred cascade records composite child keys' => static function (TestRunner $t) use ($deletePlan, $deletePluginKey, $compositeFk): void {
        $fk = $compositeFk;
        $fk['on_delete'] = 'CASCADE';
        $t->same([[1, 'active_plugins'], [1, 'active_plugins']], array_column($deletePlan($deletePluginKey, $fk)['commit_actions'], 'child_key'));
    },
    'composite deferred cascade leaves partial null children untouched' => static function (TestRunner $t) use ($deletePlan, $deletePluginKey, $compositeFk): void {
        $fk = $compositeFk;
        $fk['on_delete'] = 'CASCADE';
        $t->same(['null-site', 'null-option'], array_column(array_slice($deletePlan($deletePluginKey, $fk)['child'], 2, 2), 'payload'));
    },
    'composite deferred cascade reports parent plus child changes' => static function (TestRunner $t) use ($deletePlan, $deletePluginKey, $compositeFk): void {
        $fk = $compositeFk;
        $fk['on_delete'] = 'CASCADE';
        $t->same(3, $deletePlan($deletePluginKey, $fk)['changes']);
    },
    'composite deferred set null clears first child key column' => static function (TestRunner $t) use ($deletePlan, $deletePluginKey, $compositeFk): void {
        $fk = $compositeFk;
        $fk['on_delete'] = 'SET NULL';
        $t->same([null, null], array_column(array_slice($deletePlan($deletePluginKey, $fk)['child'], 0, 2), 'child_site_id'));
    },
    'composite deferred set null clears second child key column' => static function (TestRunner $t) use ($deletePlan, $deletePluginKey, $compositeFk): void {
        $fk = $compositeFk;
        $fk['on_delete'] = 'SET NULL';
        $t->same([null, null], array_column(array_slice($deletePlan($deletePluginKey, $fk)['child'], 0, 2), 'child_option_name'));
    },
    'composite deferred set null preserves unrelated composite child key' => static function (TestRunner $t) use ($deletePlan, $deletePluginKey, $compositeFk): void {
        $fk = $compositeFk;
        $fk['on_delete'] = 'SET NULL';
        $t->same([1, 'theme_mods'], [$deletePlan($deletePluginKey, $fk)['child'][2]['child_site_id'], $deletePlan($deletePluginKey, $fk)['child'][2]['child_option_name']]);
    },
    'composite deferred set default writes supplied vector default' => static function (TestRunner $t) use ($deletePlan, $deletePluginKey, $compositeFk): void {
        $fk = $compositeFk;
        $fk['on_delete'] = 'SET DEFAULT';
        $fk['child_default'] = [0, 'missing'];
        $t->same([[0, 'missing'], [0, 'missing']], array_map(static fn (array $row): array => [$row['child_site_id'], $row['child_option_name']], array_slice($deletePlan($deletePluginKey, $fk)['child'], 0, 2)));
    },
    'composite deferred set default records vector default' => static function (TestRunner $t) use ($deletePlan, $deletePluginKey, $compositeFk): void {
        $fk = $compositeFk;
        $fk['on_delete'] = 'SET DEFAULT';
        $fk['child_default'] = [0, 'missing'];
        $t->same([[0, 'missing'], [0, 'missing']], array_column($deletePlan($deletePluginKey, $fk)['commit_actions'], 'default'));
    },
    'composite deferred set default preserves child row count' => static function (TestRunner $t) use ($deletePlan, $deletePluginKey, $compositeFk): void {
        $fk = $compositeFk;
        $fk['on_delete'] = 'SET DEFAULT';
        $fk['child_default'] = [0, 'missing'];
        $t->same(7, count($deletePlan($deletePluginKey, $fk)['child']));
    },
    'composite deferred update restrict raises before commit' => static function (TestRunner $t) use ($updatePlan, $compositeFk): void {
        $t->throws(InvalidArgumentException::class, static fn () => $updatePlan([['site_id' => 1, 'option_name' => 'active_plugins', 'new_site_id' => 1, 'new_option_name' => 'active_plugins_renamed']], $compositeFk));
    },
    'composite deferred update restrict allows unreferenced composite parent' => static function (TestRunner $t) use ($updatePlan, $compositeFk): void {
        $result = $updatePlan([['site_id' => 3, 'option_name' => 'rewrite_rules', 'new_site_id' => 3, 'new_option_name' => 'rewrites_v2']], $compositeFk);
        $t->same('rewrites_v2', $result['parent'][3]['option_name']);
    },
    'composite deferred update restrict records old composite key' => static function (TestRunner $t) use ($updatePlan, $compositeFk): void {
        $result = $updatePlan([['site_id' => 3, 'option_name' => 'rewrite_rules', 'new_site_id' => 3, 'new_option_name' => 'rewrites_v2']], $compositeFk);
        $t->same([[3, 'rewrite_rules']], array_column($result['deferred'], 'old_parent_key'));
    },
    'composite deferred update restrict records new composite key' => static function (TestRunner $t) use ($updatePlan, $compositeFk): void {
        $result = $updatePlan([['site_id' => 3, 'option_name' => 'rewrite_rules', 'new_site_id' => 3, 'new_option_name' => 'rewrites_v2']], $compositeFk);
        $t->same([[3, 'rewrites_v2']], array_column($result['deferred'], 'new_parent_key'));
    },
    'composite deferred update cascade rewrites first child key column' => static function (TestRunner $t) use ($updatePlan, $compositeFk): void {
        $fk = $compositeFk;
        $fk['on_update'] = 'CASCADE';
        $result = $updatePlan([['site_id' => 2, 'option_name' => 'active_plugins', 'new_site_id' => 20, 'new_option_name' => 'active_plugins']], $fk);
        $t->same(20, $result['child'][3]['child_site_id']);
    },
    'composite deferred update cascade rewrites second child key column' => static function (TestRunner $t) use ($updatePlan, $compositeFk): void {
        $fk = $compositeFk;
        $fk['on_update'] = 'CASCADE';
        $result = $updatePlan([['site_id' => 2, 'option_name' => 'active_plugins', 'new_site_id' => 20, 'new_option_name' => 'plugins_v2']], $fk);
        $t->same('plugins_v2', $result['child'][3]['child_option_name']);
    },
    'composite deferred update cascade records old composite child key' => static function (TestRunner $t) use ($updatePlan, $compositeFk): void {
        $fk = $compositeFk;
        $fk['on_update'] = 'CASCADE';
        $result = $updatePlan([['site_id' => 2, 'option_name' => 'active_plugins', 'new_site_id' => 20, 'new_option_name' => 'plugins_v2']], $fk);
        $t->same([[2, 'active_plugins']], array_column($result['commit_actions'], 'old_child_key'));
    },
    'composite deferred update cascade records new composite child key' => static function (TestRunner $t) use ($updatePlan, $compositeFk): void {
        $fk = $compositeFk;
        $fk['on_update'] = 'CASCADE';
        $result = $updatePlan([['site_id' => 2, 'option_name' => 'active_plugins', 'new_site_id' => 20, 'new_option_name' => 'plugins_v2']], $fk);
        $t->same([[20, 'plugins_v2']], array_column($result['commit_actions'], 'new_child_key'));
    },
    'composite deferred update cascade leaves partial null child site unchanged' => static function (TestRunner $t) use ($updatePlan, $compositeFk): void {
        $fk = $compositeFk;
        $fk['on_update'] = 'CASCADE';
        $result = $updatePlan([['site_id' => 1, 'option_name' => 'active_plugins', 'new_site_id' => 10, 'new_option_name' => 'plugins_v2']], $fk);
        $t->same(null, $result['child'][4]['child_site_id']);
    },
    'composite deferred update cascade leaves partial null child option unchanged' => static function (TestRunner $t) use ($updatePlan, $compositeFk): void {
        $fk = $compositeFk;
        $fk['on_update'] = 'CASCADE';
        $result = $updatePlan([['site_id' => 1, 'option_name' => 'active_plugins', 'new_site_id' => 10, 'new_option_name' => 'plugins_v2']], $fk);
        $t->same(null, $result['child'][5]['child_option_name']);
    },
    'composite deferred update set null clears both child columns' => static function (TestRunner $t) use ($updatePlan, $compositeFk): void {
        $fk = $compositeFk;
        $fk['on_update'] = 'SET NULL';
        $result = $updatePlan([['site_id' => 2, 'option_name' => 'active_plugins', 'new_site_id' => 20, 'new_option_name' => 'plugins_v2']], $fk);
        $t->same([null, null], [$result['child'][3]['child_site_id'], $result['child'][3]['child_option_name']]);
    },
    'composite deferred update set default writes vector default' => static function (TestRunner $t) use ($updatePlan, $compositeFk): void {
        $fk = $compositeFk;
        $fk['on_update'] = 'SET DEFAULT';
        $fk['child_default'] = [0, 'missing'];
        $result = $updatePlan([['site_id' => 2, 'option_name' => 'active_plugins', 'new_site_id' => 20, 'new_option_name' => 'plugins_v2']], $fk);
        $t->same([0, 'missing'], [$result['child'][3]['child_site_id'], $result['child'][3]['child_option_name']]);
    },
    'composite deferred update no action reports missing parent after update' => static function (TestRunner $t) use ($updatePlan, $compositeFk): void {
        $fk = $compositeFk;
        $fk['on_update'] = 'NO ACTION';
        $result = $updatePlan([['site_id' => 2, 'option_name' => 'active_plugins', 'new_site_id' => 20, 'new_option_name' => 'plugins_v2']], $fk);
        $t->same([[2, 'active_plugins'], [9, 'missing']], array_column($result['violations'], 'child_key'));
    },
    'composite deferred update no action counts only parent update' => static function (TestRunner $t) use ($updatePlan, $compositeFk): void {
        $fk = $compositeFk;
        $fk['on_update'] = 'NO ACTION';
        $result = $updatePlan([['site_id' => 2, 'option_name' => 'active_plugins', 'new_site_id' => 20, 'new_option_name' => 'plugins_v2']], $fk);
        $t->same(1, $result['changes']);
    },
    'composite deferred update no action ignores partial null children' => static function (TestRunner $t) use ($updatePlan, $compositeFk): void {
        $fk = $compositeFk;
        $fk['on_update'] = 'NO ACTION';
        $result = $updatePlan([['site_id' => 1, 'option_name' => 'active_plugins', 'new_site_id' => 10, 'new_option_name' => 'plugins_v2']], $fk);
        $t->same(false, in_array([1, null], array_column($result['violations'], 'child_key'), true));
    },
    'composite deferred rollback preview restores composite parent keys' => static function (TestRunner $t) use ($parents, $children, $compositeFk): void {
        $fk = $compositeFk;
        $fk['on_delete'] = 'CASCADE';
        $preview = SQLiteForeignKeyDeferredCascadePlan::deleteParentsWithRollbackPreview($parents, $children, [['site_id' => 1, 'option_name' => 'active_plugins']], $fk);
        $t->same(['active_plugins', 'theme_mods', 'active_plugins', 'rewrite_rules'], array_column($preview['rollback']['parent'], 'option_name'));
    },
    'composite deferred update rollback preview restores composite child keys' => static function (TestRunner $t) use ($parents, $children, $compositeFk): void {
        $fk = $compositeFk;
        $fk['on_update'] = 'CASCADE';
        $preview = SQLiteForeignKeyDeferredCascadePlan::updateParentsWithRollbackPreview($parents, $children, [['site_id' => 2, 'option_name' => 'active_plugins', 'new_site_id' => 20, 'new_option_name' => 'plugins_v2']], $fk);
        $t->same([2, 'active_plugins'], [$preview['rollback']['child'][3]['child_site_id'], $preview['rollback']['child'][3]['child_option_name']]);
    },
    'composite deferred key count mismatch is rejected' => static function (TestRunner $t) use ($deletePlan): void {
        $t->throws(InvalidArgumentException::class, static fn () => $deletePlan([['site_id' => 1, 'option_name' => 'active_plugins']], ['parent_key' => ['site_id'], 'child_key' => ['child_site_id', 'child_option_name'], 'on_delete' => 'CASCADE']));
    },
    'composite deferred duplicate parent columns are rejected' => static function (TestRunner $t) use ($deletePlan): void {
        $t->throws(InvalidArgumentException::class, static fn () => $deletePlan([['site_id' => 1, 'option_name' => 'active_plugins']], ['parent_key' => ['site_id', 'site_id'], 'child_key' => ['child_site_id', 'child_option_name'], 'on_delete' => 'CASCADE']));
    },
    'composite deferred malformed child column is rejected' => static function (TestRunner $t) use ($deletePlan): void {
        $t->throws(InvalidArgumentException::class, static fn () => $deletePlan([['site_id' => 1, 'option_name' => 'active_plugins']], ['parent_key' => ['site_id', 'option_name'], 'child_key' => ['child-site', 'child_option_name'], 'on_delete' => 'CASCADE']));
    },
    'composite deferred delete key missing second column is rejected' => static function (TestRunner $t) use ($deletePlan, $compositeFk): void {
        $fk = $compositeFk;
        $fk['on_delete'] = 'CASCADE';
        $t->throws(InvalidArgumentException::class, static fn () => $deletePlan([['site_id' => 1]], $fk));
    },
    'composite deferred update key missing new second column is rejected' => static function (TestRunner $t) use ($updatePlan, $compositeFk): void {
        $fk = $compositeFk;
        $fk['on_update'] = 'CASCADE';
        $t->throws(InvalidArgumentException::class, static fn () => $updatePlan([['site_id' => 2, 'option_name' => 'active_plugins', 'new_site_id' => 20]], $fk));
    },
];

return $tests;
