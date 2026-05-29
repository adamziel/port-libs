<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerRecursiveDeferredReturningCurrentSourceNextPlan;

$parents = [
    ['option_id' => 1, 'next_id' => 2, 'option_name' => 'siteurl', 'option_value' => 'https://old.test', 'revision' => 1],
    ['option_id' => 2, 'next_id' => 3, 'option_name' => 'home', 'option_value' => 'https://old.test/home', 'revision' => 1],
    ['option_id' => 3, 'next_id' => null, 'option_name' => 'blogname', 'option_value' => 'Old Site', 'revision' => 1],
];
$children = [
    ['meta_id' => 11, 'option_id' => 1, 'meta_key' => '_origin'],
    ['meta_id' => 12, 'option_id' => 2, 'meta_key' => '_origin'],
    ['meta_id' => 13, 'option_id' => 3, 'meta_key' => '_origin'],
];
$fk = ['parent_key' => 'option_id', 'child_key' => 'option_id', 'deferred' => true];
$statement = [
    'savepoint' => 'wp_options_refresh',
    'current_source' => 'main@schema-cookie-9',
    'next_source' => 'main@schema-cookie-10',
    'where' => static fn (array $row): bool => $row['option_id'] === 1,
    'assignments' => [
        'option_id' => static fn (array $row, int $depth, string $source): int => (int) $row['option_id'] + 100 + $depth,
        'revision' => static fn (array $row, int $depth, string $source): int => (int) $row['revision'] + 1 + $depth,
        'option_value' => static fn (array $row, int $depth, string $source): string => (string) $row['option_value'] . '#' . $source . ':' . $depth,
    ],
    'returning' => [
        ['expr' => 'old.option_id', 'as' => 'old_id'],
        ['expr' => 'new.option_id', 'as' => 'new_id'],
        ['expr' => 'context.source', 'as' => 'source_token'],
        ['expr' => 'context.trigger_depth', 'as' => 'depth'],
        'option_name',
        'revision',
        static fn (array $new, array $old, array $context): string => $context['trigger_source'] . ':' . $old['option_id'] . '>' . $new['option_id'],
    ],
    'trigger' => ['name' => 'wp_options_recursive_rekey', 'match_column' => 'option_id', 'match_value' => 'old.next_id'],
    'rollback_on_deferred_violation' => true,
];

$rollbackPlan = static fn (): array => SQLiteTriggerRecursiveDeferredReturningCurrentSourceNextPlan::update($parents, $children, $fk, $statement);
$blockedPlan = static fn (): array => SQLiteTriggerRecursiveDeferredReturningCurrentSourceNextPlan::update($parents, $children, $fk, array_replace($statement, ['rollback_on_deferred_violation' => false]));
$commitPlan = static fn (): array => SQLiteTriggerRecursiveDeferredReturningCurrentSourceNextPlan::update($parents, [], $fk, $statement);
$recursiveOffPlan = static fn (): array => SQLiteTriggerRecursiveDeferredReturningCurrentSourceNextPlan::update($parents, $children, $fk, $statement + ['recursive_triggers' => false]);

return [
    'trigger recursive deferred returning current source next121 rollback status' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same('rolled-back', $rollbackPlan()['status']);
    },
    'trigger recursive deferred returning current source next121 savepoint' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same('wp_options_refresh', $rollbackPlan()['savepoint']);
    },
    'trigger recursive deferred returning current source next121 current source token' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same('main@schema-cookie-9', $rollbackPlan()['current_source']);
    },
    'trigger recursive deferred returning current source next121 rollback keeps next source current' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same('main@schema-cookie-9', $rollbackPlan()['next_source']);
    },
    'trigger recursive deferred returning current source next121 returning source token' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same('main@schema-cookie-9', $rollbackPlan()['returning_source']);
    },
    'trigger recursive deferred returning current source next121 recursive enabled' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same(true, $rollbackPlan()['recursive_triggers']);
    },
    'trigger recursive deferred returning current source next121 current rowids' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same([101, 103, 105], $rollbackPlan()['current_rowids']);
    },
    'trigger recursive deferred returning current source next121 next rowids restored' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same([1, 2, 3], $rollbackPlan()['next_rowids']);
    },
    'trigger recursive deferred returning current source next121 current parent revisions' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same([2, 3, 4], array_column($rollbackPlan()['current_parent'], 'revision'));
    },
    'trigger recursive deferred returning current source next121 next parent restored' => static function (TestRunner $t) use ($rollbackPlan, $parents): void {
        $t->same($parents, $rollbackPlan()['next_parent']);
    },
    'trigger recursive deferred returning current source next121 next child restored' => static function (TestRunner $t) use ($rollbackPlan, $children): void {
        $t->same($children, $rollbackPlan()['next_child']);
    },
    'trigger recursive deferred returning current source next121 current returning count' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same(1, count($rollbackPlan()['current_returning_rows']));
    },
    'trigger recursive deferred returning current source next121 current returning old id' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same(1, $rollbackPlan()['current_returning_rows'][0]['old_id']);
    },
    'trigger recursive deferred returning current source next121 current returning new id' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same(101, $rollbackPlan()['current_returning_rows'][0]['new_id']);
    },
    'trigger recursive deferred returning current source next121 current returning source' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same('main@schema-cookie-9', $rollbackPlan()['current_returning_rows'][0]['source_token']);
    },
    'trigger recursive deferred returning current source next121 current returning depth' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same(0, $rollbackPlan()['current_returning_rows'][0]['depth']);
    },
    'trigger recursive deferred returning current source next121 current returning option name' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same('siteurl', $rollbackPlan()['current_returning_rows'][0]['option_name']);
    },
    'trigger recursive deferred returning current source next121 current returning revision' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same(2, $rollbackPlan()['current_returning_rows'][0]['revision']);
    },
    'trigger recursive deferred returning current source next121 callable returning source' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same('statement:1>101', $rollbackPlan()['current_returning_rows'][0]['expr6']);
    },
    'trigger recursive deferred returning current source next121 next returning suppressed' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same([], $rollbackPlan()['next_returning_rows']);
    },
    'trigger recursive deferred returning current source next121 suppression flag' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same(true, $rollbackPlan()['yield_suppressed_by_deferred_rollback']);
    },
    'trigger recursive deferred returning current source next121 boundary' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same('current-yield-next-rollback', $rollbackPlan()['yield_boundary']);
    },
    'trigger recursive deferred returning current source next121 attempted rows include trigger rows' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same(3, count($rollbackPlan()['attempted_returning_rows']));
    },
    'trigger recursive deferred returning current source next121 attempted source tokens' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same(['main@schema-cookie-9', 'main@schema-cookie-9', 'main@schema-cookie-9'], array_column($rollbackPlan()['attempted_returning_rows'], 'source'));
    },
    'trigger recursive deferred returning current source next121 attempted depths' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same([0, 1, 2], array_column($rollbackPlan()['attempted_returning_rows'], 'trigger_depth'));
    },
    'trigger recursive deferred returning current source next121 attempted trigger sources' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same(['statement', 'wp_options_recursive_rekey', 'wp_options_recursive_rekey'], array_column($rollbackPlan()['attempted_returning_rows'], 'trigger_source'));
    },
    'trigger recursive deferred returning current source next121 trigger returning count' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same(2, count($rollbackPlan()['trigger_returning_rows']));
    },
    'trigger recursive deferred returning current source next121 trigger keys' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same([103, 105], array_column($rollbackPlan()['trigger_returning_rows'], 'new_key'));
    },
    'trigger recursive deferred returning current source next121 trigger name carried' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same('wp_options_recursive_rekey', $rollbackPlan()['trigger_returning_rows'][0]['trigger']);
    },
    'trigger recursive deferred returning current source next121 violation count' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same(3, count($rollbackPlan()['foreign_key_violations']));
    },
    'trigger recursive deferred returning current source next121 violation child keys' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same([1, 2, 3], array_column($rollbackPlan()['foreign_key_violations'], 'child_key'));
    },
    'trigger recursive deferred returning current source next121 violation phase' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same(['deferred-commit', 'deferred-commit', 'deferred-commit'], array_column($rollbackPlan()['foreign_key_violations'], 'phase'));
    },
    'trigger recursive deferred returning current source next121 current changes include recursion' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same(3, $rollbackPlan()['current_changes']);
    },
    'trigger recursive deferred returning current source next121 next changes reset' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same(0, $rollbackPlan()['next_changes']);
    },
    'trigger recursive deferred returning current source next121 dependency returning source' => static function (TestRunner $t) use ($rollbackPlan): void {
        $t->same(true, in_array('sqlite-returning-current-source-before-deferred-check', $rollbackPlan()['dependencies'], true));
    },
    'trigger recursive deferred returning current source next121 blocked status' => static function (TestRunner $t) use ($blockedPlan): void {
        $t->same('deferred-commit-blocked', $blockedPlan()['status']);
    },
    'trigger recursive deferred returning current source next121 blocked next source advances' => static function (TestRunner $t) use ($blockedPlan): void {
        $t->same('main@schema-cookie-10', $blockedPlan()['next_source']);
    },
    'trigger recursive deferred returning current source next121 blocked returning visible' => static function (TestRunner $t) use ($blockedPlan): void {
        $t->same(1, count($blockedPlan()['next_returning_rows']));
    },
    'trigger recursive deferred returning current source next121 blocked rowids remain changed' => static function (TestRunner $t) use ($blockedPlan): void {
        $t->same([101, 103, 105], $blockedPlan()['next_rowids']);
    },
    'trigger recursive deferred returning current source next121 blocked changes remain' => static function (TestRunner $t) use ($blockedPlan): void {
        $t->same(3, $blockedPlan()['next_changes']);
    },
    'trigger recursive deferred returning current source next121 blocked boundary' => static function (TestRunner $t) use ($blockedPlan): void {
        $t->same('current-yield-next-blocked', $blockedPlan()['yield_boundary']);
    },
    'trigger recursive deferred returning current source next121 commit status' => static function (TestRunner $t) use ($commitPlan): void {
        $t->same('commit-ok', $commitPlan()['status']);
    },
    'trigger recursive deferred returning current source next121 commit next source advances' => static function (TestRunner $t) use ($commitPlan): void {
        $t->same('main@schema-cookie-10', $commitPlan()['next_source']);
    },
    'trigger recursive deferred returning current source next121 commit returns row' => static function (TestRunner $t) use ($commitPlan): void {
        $t->same('siteurl', $commitPlan()['next_returning_rows'][0]['option_name']);
    },
    'trigger recursive deferred returning current source next121 commit no violations' => static function (TestRunner $t) use ($commitPlan): void {
        $t->same([], $commitPlan()['foreign_key_violations']);
    },
    'trigger recursive deferred returning current source next121 recursive off rowids' => static function (TestRunner $t) use ($recursiveOffPlan): void {
        $t->same([101, 2, 3], $recursiveOffPlan()['current_rowids']);
    },
    'trigger recursive deferred returning current source next121 recursive off trigger rows empty' => static function (TestRunner $t) use ($recursiveOffPlan): void {
        $t->same([], $recursiveOffPlan()['trigger_returning_rows']);
    },
    'trigger recursive deferred returning current source next121 recursive off attempted count' => static function (TestRunner $t) use ($recursiveOffPlan): void {
        $t->same(1, count($recursiveOffPlan()['attempted_returning_rows']));
    },
    'trigger recursive deferred returning current source next121 recursive off changes' => static function (TestRunner $t) use ($recursiveOffPlan): void {
        $t->same(1, $recursiveOffPlan()['current_changes']);
    },
    'trigger recursive deferred returning current source next121 missing where throws' => static function (TestRunner $t) use ($parents, $children, $fk, $statement): void {
        $bad = $statement;
        unset($bad['where']);
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteTriggerRecursiveDeferredReturningCurrentSourceNextPlan::update($parents, $children, $fk, $bad));
    },
    'trigger recursive deferred returning current source next121 empty assignments throws' => static function (TestRunner $t) use ($parents, $children, $fk, $statement): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteTriggerRecursiveDeferredReturningCurrentSourceNextPlan::update($parents, $children, $fk, array_replace($statement, ['assignments' => []])));
    },
    'trigger recursive deferred returning current source next121 bad source throws' => static function (TestRunner $t) use ($parents, $children, $fk, $statement): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteTriggerRecursiveDeferredReturningCurrentSourceNextPlan::update($parents, $children, $fk, array_replace($statement, ['current_source' => 'bad source'])));
    },
    'trigger recursive deferred returning current source next121 max depth throws' => static function (TestRunner $t) use ($parents, $children, $fk, $statement): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteTriggerRecursiveDeferredReturningCurrentSourceNextPlan::update($parents, $children, $fk, $statement + ['max_depth' => 1]));
    },
    'trigger recursive deferred returning current source next121 bad returning column throws' => static function (TestRunner $t) use ($parents, $children, $fk, $statement): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteTriggerRecursiveDeferredReturningCurrentSourceNextPlan::update($parents, $children, $fk, array_replace($statement, ['returning' => ['missing_column']])));
    },
    'trigger recursive deferred returning current source next121 bad alias throws' => static function (TestRunner $t) use ($parents, $children, $fk, $statement): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteTriggerRecursiveDeferredReturningCurrentSourceNextPlan::update($parents, $children, $fk, array_replace($statement, ['returning' => [['expr' => 'new.option_id', 'as' => 'bad-alias']]])));
    },
];
