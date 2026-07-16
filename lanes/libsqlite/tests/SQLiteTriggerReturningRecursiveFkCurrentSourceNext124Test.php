<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerReturningRecursiveFkCurrentSourceNextPlan;

$parents124 = [
    ['setting_id' => 1, 'next_id' => 2, 'key_name' => 'base_url', 'key_value' => 'https://old.test'],
    ['setting_id' => 2, 'next_id' => 3, 'key_name' => 'public_url', 'key_value' => 'https://old.test/public_url'],
    ['setting_id' => 3, 'next_id' => null, 'key_name' => 'site_title', 'key_value' => 'Old Site'],
    ['setting_id' => 4, 'next_id' => null, 'key_name' => 'kept_module', 'key_value' => 'keep'],
];
$children124 = [
    ['meta_id' => 11, 'setting_id' => 1, 'meta_key' => '_origin'],
    ['meta_id' => 12, 'setting_id' => 2, 'meta_key' => '_origin'],
    ['meta_id' => 13, 'setting_id' => 2, 'meta_key' => '_thumbnail'],
    ['meta_id' => 14, 'setting_id' => 3, 'meta_key' => '_origin'],
    ['meta_id' => 15, 'setting_id' => 4, 'meta_key' => '_origin'],
];
$grandchildren124 = [
    ['detail_id' => 101, 'setting_id' => 1, 'detail' => 'base_url-origin'],
    ['detail_id' => 102, 'setting_id' => 2, 'detail' => 'public_url-origin'],
    ['detail_id' => 103, 'setting_id' => 2, 'detail' => 'public_url-thumb'],
    ['detail_id' => 104, 'setting_id' => 3, 'detail' => 'site_title-origin'],
    ['detail_id' => 105, 'setting_id' => 4, 'detail' => 'kept-origin'],
];
$fk124 = ['parent_key' => 'setting_id', 'child_key' => 'setting_id', 'grandchild_key' => 'setting_id', 'deferred' => true, 'on_delete' => 'cascade'];
$statement124 = [
    'savepoint' => 'app_recursive_delete',
    'current_source' => 'main@cookie-124',
    'next_source' => 'main@cookie-125',
    'where' => static fn (array $row): bool => $row['setting_id'] === 1,
    'trigger' => ['name' => 'app_settings_ad_recursive_delete', 'match_column' => 'setting_id', 'match_value' => 'old.next_id'],
    'returning' => [
        ['expr' => 'old.setting_id', 'as' => 'deleted_id'],
        'key_name',
        ['expr' => 'context.source', 'as' => 'source_token'],
        ['expr' => 'context.trigger_depth', 'as' => 'depth'],
        ['expr' => 'context.trigger_source', 'as' => 'trigger_source'],
        static fn (array $old, array $unused, array $context): string => $context['trigger_source'] . ':' . $old['key_name'],
    ],
];

$commit124 = static fn (): array => SQLiteTriggerReturningRecursiveFkCurrentSourceNextPlan::delete($parents124, $children124, $grandchildren124, $fk124, $statement124);
$rollback124 = static fn (): array => SQLiteTriggerReturningRecursiveFkCurrentSourceNextPlan::delete($parents124, $children124, $grandchildren124, $fk124, $statement124 + ['rollback_to_savepoint' => true]);
$nonRecursive124 = static fn (): array => SQLiteTriggerReturningRecursiveFkCurrentSourceNextPlan::delete($parents124, $children124, $grandchildren124, $fk124, $statement124 + ['recursive_triggers' => false]);

$cases124 = [
    'commit status' => [static fn (): mixed => $commit124()['status'], 'current-yield-next-commit'],
    'savepoint retained' => [static fn (): mixed => $commit124()['savepoint'], 'app_recursive_delete'],
    'current source token' => [static fn (): mixed => $commit124()['current_source'], 'main@cookie-124'],
    'next source advances on commit' => [static fn (): mixed => $commit124()['next_source'], 'main@cookie-125'],
    'returning source is current source' => [static fn (): mixed => $commit124()['returning_source'], 'main@cookie-124'],
    'recursive triggers enabled by default' => [static fn (): mixed => $commit124()['recursive_triggers'], true],
    'deleted parent keys include recursive chain' => [static fn (): mixed => $commit124()['deleted_parent_keys'], [1, 2, 3]],
    'current parent keys keep unrelated row' => [static fn (): mixed => $commit124()['current_parent_keys'], [4]],
    'next parent keys keep unrelated row' => [static fn (): mixed => $commit124()['next_parent_keys'], [4]],
    'current child keys keep unrelated row' => [static fn (): mixed => $commit124()['current_child_keys'], [4]],
    'next child keys keep unrelated row' => [static fn (): mixed => $commit124()['next_child_keys'], [4]],
    'current grandchild keys keep unrelated row' => [static fn (): mixed => $commit124()['current_grandchild_keys'], [4]],
    'next grandchild keys keep unrelated row' => [static fn (): mixed => $commit124()['next_grandchild_keys'], [4]],
    'statement returning count excludes recursive trigger deletes' => [static fn (): mixed => count($commit124()['current_returning_rows']), 1],
    'statement returning deleted id' => [static fn (): mixed => $commit124()['current_returning_rows'][0]['deleted_id'], 1],
    'statement returning setting name' => [static fn (): mixed => $commit124()['current_returning_rows'][0]['key_name'], 'base_url'],
    'statement returning source token' => [static fn (): mixed => $commit124()['current_returning_rows'][0]['source_token'], 'main@cookie-124'],
    'statement returning depth' => [static fn (): mixed => $commit124()['current_returning_rows'][0]['depth'], 0],
    'statement returning trigger source' => [static fn (): mixed => $commit124()['current_returning_rows'][0]['trigger_source'], 'statement'],
    'statement returning callable expression' => [static fn (): mixed => $commit124()['current_returning_rows'][0]['expr5'], 'statement:base_url'],
    'next returning visible on commit' => [static fn (): mixed => $commit124()['next_returning_rows'], $commit124()['current_returning_rows']],
    'attempted returning includes statement and trigger deletes' => [static fn (): mixed => count($commit124()['attempted_returning_rows']), 3],
    'attempted depths' => [static fn (): mixed => array_column($commit124()['attempted_returning_rows'], 'trigger_depth'), [0, 1, 2]],
    'attempted trigger sources' => [static fn (): mixed => array_column($commit124()['attempted_returning_rows'], 'trigger_source'), ['statement', 'app_settings_ad_recursive_delete', 'app_settings_ad_recursive_delete']],
    'trigger returning count' => [static fn (): mixed => count($commit124()['trigger_returning_rows']), 2],
    'trigger returning deleted ids' => [static fn (): mixed => array_column(array_column($commit124()['trigger_returning_rows'], 'returning'), 'deleted_id'), [2, 3]],
    'trigger returning names' => [static fn (): mixed => array_column(array_column($commit124()['trigger_returning_rows'], 'returning'), 'key_name'), ['public_url', 'site_title']],
    'trigger returning callable expressions' => [static fn (): mixed => array_column(array_column($commit124()['trigger_returning_rows'], 'returning'), 'expr5'), ['app_settings_ad_recursive_delete:public_url', 'app_settings_ad_recursive_delete:site_title']],
    'trigger name carried' => [static fn (): mixed => array_column($commit124()['trigger_returning_rows'], 'trigger'), ['app_settings_ad_recursive_delete', 'app_settings_ad_recursive_delete']],
    'cascade action count' => [static fn (): mixed => count($commit124()['cascade_actions']), 8],
    'cascade child action count' => [static fn (): mixed => count(array_filter($commit124()['cascade_actions'], static fn (array $row): bool => $row['action'] === 'cascade-delete-child')), 4],
    'cascade grandchild action count' => [static fn (): mixed => count(array_filter($commit124()['cascade_actions'], static fn (array $row): bool => $row['action'] === 'cascade-delete-grandchild')), 4],
    'cascade first child meta id' => [static fn (): mixed => $commit124()['cascade_actions'][0]['child']['meta_id'], 11],
    'cascade first grandchild detail id' => [static fn (): mixed => $commit124()['cascade_actions'][1]['grandchild']['detail_id'], 101],
    'no fk violations after cascade' => [static fn (): mixed => $commit124()['foreign_key_violations'], []],
    'current changes include parent child grandchild deletes' => [static fn (): mixed => $commit124()['current_changes'], 11],
    'next changes match commit' => [static fn (): mixed => $commit124()['next_changes'], 11],
    'commit boundary' => [static fn (): mixed => $commit124()['yield_boundary'], 'current-yield-next-commit'],
    'commit not suppressed by savepoint' => [static fn (): mixed => $commit124()['yield_suppressed_by_savepoint'], false],
    'dependencies include next124 marker' => [static fn (): mixed => in_array('sqlite-trigger-returning-recursive-fk-current-source-next124', $commit124()['dependencies'], true), true],
    'dependencies include returning rollback marker' => [static fn (): mixed => in_array('sqlite-returning-yield-before-recursive-fk-cascade-rollback', $commit124()['dependencies'], true), true],
    'dependencies include recursive delete cascade marker' => [static fn (): mixed => in_array('sqlite-recursive-delete-trigger-current-source-fk-cascade', $commit124()['dependencies'], true), true],
    'rollback status' => [static fn (): mixed => $rollback124()['status'], 'rolled-back-to-savepoint-after-returning-yield'],
    'rollback next source remains current' => [static fn (): mixed => $rollback124()['next_source'], 'main@cookie-124'],
    'rollback restores parent keys' => [static fn (): mixed => $rollback124()['next_parent_keys'], [1, 2, 3, 4]],
    'rollback restores child keys' => [static fn (): mixed => $rollback124()['next_child_keys'], [1, 2, 2, 3, 4]],
    'rollback restores grandchild keys' => [static fn (): mixed => $rollback124()['next_grandchild_keys'], [1, 2, 2, 3, 4]],
    'rollback suppresses next returning' => [static fn (): mixed => $rollback124()['next_returning_rows'], []],
    'rollback preserves attempted returning diagnostics' => [static fn (): mixed => count($rollback124()['attempted_returning_rows']), 3],
    'rollback resets next changes' => [static fn (): mixed => $rollback124()['next_changes'], 0],
    'rollback boundary' => [static fn (): mixed => $rollback124()['yield_boundary'], 'current-yield-next-rollback'],
    'rollback suppression flag' => [static fn (): mixed => $rollback124()['yield_suppressed_by_savepoint'], true],
    'non recursive deletes only statement row' => [static fn (): mixed => $nonRecursive124()['deleted_parent_keys'], [1]],
    'non recursive leaves later parents' => [static fn (): mixed => $nonRecursive124()['current_parent_keys'], [2, 3, 4]],
    'non recursive trigger rows empty' => [static fn (): mixed => $nonRecursive124()['trigger_returning_rows'], []],
    'non recursive attempted returning count' => [static fn (): mixed => count($nonRecursive124()['attempted_returning_rows']), 1],
    'non recursive changes include one parent cascade only' => [static fn (): mixed => $nonRecursive124()['current_changes'], 3],
    'missing where throws' => [static fn (): mixed => SQLiteTriggerReturningRecursiveFkCurrentSourceNextPlan::delete($parents124, $children124, $grandchildren124, $fk124, array_diff_key($statement124, ['where' => true])), InvalidArgumentException::class],
    'bad savepoint throws' => [static fn (): mixed => SQLiteTriggerReturningRecursiveFkCurrentSourceNextPlan::delete($parents124, $children124, $grandchildren124, $fk124, array_replace($statement124, ['savepoint' => 'bad-name'])), InvalidArgumentException::class],
    'bad source throws' => [static fn (): mixed => SQLiteTriggerReturningRecursiveFkCurrentSourceNextPlan::delete($parents124, $children124, $grandchildren124, $fk124, array_replace($statement124, ['current_source' => 'bad source'])), InvalidArgumentException::class],
    'bad on delete throws' => [static fn (): mixed => SQLiteTriggerReturningRecursiveFkCurrentSourceNextPlan::delete($parents124, $children124, $grandchildren124, ['parent_key' => 'setting_id', 'child_key' => 'setting_id', 'on_delete' => 'restrict'], $statement124), InvalidArgumentException::class],
    'bad returning column throws' => [static fn (): mixed => SQLiteTriggerReturningRecursiveFkCurrentSourceNextPlan::delete($parents124, $children124, $grandchildren124, $fk124, array_replace($statement124, ['returning' => ['missing_column']])), InvalidArgumentException::class],
    'bad returning alias throws' => [static fn (): mixed => SQLiteTriggerReturningRecursiveFkCurrentSourceNextPlan::delete($parents124, $children124, $grandchildren124, $fk124, array_replace($statement124, ['returning' => [['expr' => 'old.setting_id', 'as' => 'bad-alias']]])), InvalidArgumentException::class],
    'max depth throws' => [static fn (): mixed => SQLiteTriggerReturningRecursiveFkCurrentSourceNextPlan::delete($parents124, $children124, $grandchildren124, $fk124, $statement124 + ['max_depth' => 1]), InvalidArgumentException::class],
];

foreach ($cases124 as $name => [$callback, $expected]) {
    $tests['trigger returning recursive fk current source next124 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }
        $t->same($expected, $callback());
    };
}

return $tests;
