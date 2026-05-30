<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerForeignKeyDynamicPlan;

$repair = static fn (): array => SQLiteTriggerForeignKeyDynamicPlan::immediateInsertRepairScenario(true);
$blocked = static fn (): array => SQLiteTriggerForeignKeyDynamicPlan::immediateInsertRepairScenario(false);
$deferred = static fn (): array => SQLiteTriggerForeignKeyDynamicPlan::deferredTransactionScenario();
$deferredMissingFive = static fn (): array => SQLiteTriggerForeignKeyDynamicPlan::deferredTransactionScenario(false, true);
$nested = static fn (): array => SQLiteTriggerForeignKeyDynamicPlan::deferredSavepointScenario();
$nestedUnfixed = static fn (): array => SQLiteTriggerForeignKeyDynamicPlan::deferredSavepointScenario(false);
$outer = static fn (): array => SQLiteTriggerForeignKeyDynamicPlan::transactionSavepointScenario();
$outerRollback = static fn (): array => SQLiteTriggerForeignKeyDynamicPlan::transactionSavepointScenario(true);
$update22 = static fn (): array => SQLiteTriggerForeignKeyDynamicPlan::parentUpdateOrderScenario(22);
$updateText = static fn (): array => SQLiteTriggerForeignKeyDynamicPlan::parentUpdateOrderScenario('22');

$cases = [
    'immediate repair status' => [static fn (): mixed => $repair()['status'], 'statement-ok'],
    'immediate repair inserts child' => [static fn (): mixed => $repair()['prince'], [['c' => 1, 'd' => 2]]],
    'immediate repair inserts parent' => [static fn (): mixed => $repair()['king'], [['a' => 1, 'b' => null]]],
    'immediate repair event order' => [static fn (): mixed => array_column($repair()['events'], 'step'), ['insert-child', 'after-insert-trigger']],
    'immediate repair trigger name' => [static fn (): mixed => $repair()['events'][1]['trigger'], 'kt'],
    'immediate repair changes' => [static fn (): mixed => $repair()['changes'], 2],
    'immediate repair upstream source' => [static fn (): mixed => $repair()['upstream'], 'e_fkey.test e_fkey-31.2/e_fkey-31.3'],
    'immediate blocked status' => [static fn (): mixed => $blocked()['status'], 'constraint-failed'],
    'immediate blocked child rolled back' => [static fn (): mixed => $blocked()['prince'], []],
    'immediate blocked parent unchanged' => [static fn (): mixed => $blocked()['king'], []],
    'immediate blocked violation key' => [static fn (): mixed => $blocked()['violations'][0]['child_key'], 1],
    'immediate blocked event order' => [static fn (): mixed => array_column($blocked()['events'], 'step'), ['insert-child', 'statement-rollback']],

    'deferred first commit blocked' => [static fn (): mixed => $deferred()['commit_attempts'][0]['status'], 'commit-blocked'],
    'deferred first commit violation keys' => [static fn (): mixed => array_column($deferred()['commit_attempts'][0]['violations'], 'child_key'), [5, 10]],
    'deferred second commit still blocked' => [static fn (): mixed => $deferred()['commit_attempts'][1]['status'], 'commit-blocked'],
    'deferred second commit remaining key' => [static fn (): mixed => array_column($deferred()['commit_attempts'][1]['violations'], 'child_key'), [5]],
    'deferred final commit ok' => [static fn (): mixed => $deferred()['commit_attempts'][2]['status'], 'commit-ok'],
    'deferred final violations empty' => [static fn (): mixed => $deferred()['commit_attempts'][2]['violations'], []],
    'deferred parents sorted' => [static fn (): mixed => array_column($deferred()['parents'], 'k'), [5, 10]],
    'deferred children persist while violated' => [static fn (): mixed => array_column($deferred()['children'], 'c'), [5, 10]],
    'deferred event order' => [static fn (): mixed => array_column($deferred()['events'], 'step'), ['insert-deferred-child', 'insert-deferred-child', 'repair-parent', 'repair-parent']],
    'deferred missing five remains blocked' => [static fn (): mixed => $deferredMissingFive()['commit_attempts'][2]['status'], 'commit-blocked'],
    'deferred missing five key retained' => [static fn (): mixed => array_column($deferredMissingFive()['commit_attempts'][2]['violations'], 'child_key'), [5]],
    'deferred upstream source' => [static fn (): mixed => $deferred()['upstream'], 'e_fkey.test e_fkey-32.1..32.9'],

    'nested release status' => [static fn (): mixed => $nested()['release_status'], 'ok'],
    'nested first commit blocked' => [static fn (): mixed => $nested()['commit_attempts'][0]['status'], 'commit-blocked'],
    'nested first violation key' => [static fn (): mixed => $nested()['commit_attempts'][0]['violations'][0]['child_key'], 5],
    'nested repaired commit ok' => [static fn (): mixed => $nested()['commit_attempts'][1]['status'], 'commit-ok'],
    'nested repaired rows' => [static fn (): mixed => $nested()['rows'], [['a' => 1, 'b' => 1], ['a' => 2, 'b' => 2], ['a' => 3, 'b' => 3], ['a' => 5, 'b' => 5]]],
    'nested release event status' => [static fn (): mixed => $nested()['events'][3]['status'], 'ok-nested-deferred-open'],
    'nested unfixed remains blocked' => [static fn (): mixed => $nestedUnfixed()['commit_attempts'][1]['status'], 'commit-blocked'],
    'nested unfixed row retained' => [static fn (): mixed => $nestedUnfixed()['rows'][3], ['a' => 4, 'b' => 5]],
    'nested upstream source' => [static fn (): mixed => $nested()['upstream'], 'e_fkey.test e_fkey-36.1..36.4'],

    'transaction savepoint release blocked' => [static fn (): mixed => $outer()['release_status'], 'release-blocked'],
    'transaction savepoint violation key' => [static fn (): mixed => $outer()['violations'][0]['child_key'], 7],
    'transaction savepoint keeps inserted row after failed release' => [static fn (): mixed => $outer()['rows'][3], ['a' => 6, 'b' => 7]],
    'transaction savepoint event order' => [static fn (): mixed => array_column($outer()['events'], 'step'), ['savepoint', 'savepoint', 'insert', 'release', 'release']],
    'transaction savepoint rollback restores rows' => [static fn (): mixed => $outerRollback()['rows'], [['a' => 1, 'b' => 1], ['a' => 2, 'b' => 2], ['a' => 3, 'b' => 3]]],
    'transaction savepoint rollback clears violations' => [static fn (): mixed => $outerRollback()['violations'], []],
    'transaction savepoint rollback final event' => [static fn (): mixed => $outerRollback()['events'][6]['status'], 'release-ok'],
    'transaction savepoint upstream source' => [static fn (): mixed => $outer()['upstream'], 'e_fkey.test e_fkey-37.1..37.6'],

    'parent update event order' => [static fn (): mixed => $update22()['event_order'], ['before-trigger', 'update-parent-row', 'foreign-key-set-default', 'after-trigger']],
    'parent update before trigger inserted difference' => [static fn (): mixed => $update22()['events'][0]['inserted_parent'], 21],
    'parent update updates parent row before fk action' => [static fn (): mixed => $update22()['events'][1], ['step' => 'update-parent-row', 'old' => 1, 'new' => 22]],
    'parent update set default sees current max before after trigger' => [static fn (): mixed => $update22()['events'][2]['child_value'], 22],
    'parent update after trigger inserted sum' => [static fn (): mixed => $update22()['events'][3]['inserted_parent'], 23],
    'parent update parent row order' => [static fn (): mixed => $update22()['parent'], [['x' => 22], ['x' => 21], ['x' => 23]]],
    'parent update child default' => [static fn (): mixed => $update22()['child'], [['a' => 22]]],
    'parent update text arithmetic before trigger' => [static fn (): mixed => $updateText()['events'][0]['inserted_parent'], 21],
    'parent update text arithmetic after trigger' => [static fn (): mixed => $updateText()['events'][3]['inserted_parent'], 23],
    'parent update upstream source' => [static fn (): mixed => $update22()['upstream'], 'e_fkey.test e_fkey-51.1..51.3'],
];

$tests = [];
foreach ($cases as $name => [$callback, $expected]) {
    $tests['upstream trigger foreign key dynamic corpus ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

return $tests;
