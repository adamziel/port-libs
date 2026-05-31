<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDeferredForeignKeyRestrictPlan;

$parents = [
    ['setting_id' => 1, 'label' => 'one'],
    ['setting_id' => 2, 'label' => 'two'],
];
$children = [
    ['child_name' => 'i', 'parent_setting_id' => 1],
];
$foreignKey = [
    'parent_key' => 'setting_id',
    'child_key' => 'parent_setting_id',
    'on_update' => 'restrict',
    'on_delete' => 'restrict',
];

$updateMinusOne = static fn (mixed $key): int => (int) $key - 1;
$afterDeleteRepair = [
    'name' => 'parent_after_delete_repair',
    'timing' => 'after',
    'event' => 'delete',
    'action' => 'insert-parent',
    'row' => ['setting_id' => 'old.setting_id', 'label' => 'deleted!'],
];

$immediateUpdate = static fn (): array => SQLiteDeferredForeignKeyRestrictPlan::updateParentKeys($parents, $children, $foreignKey, $updateMinusOne);
$deferredUpdate = static fn (): array => SQLiteDeferredForeignKeyRestrictPlan::updateParentKeys($parents, $children, $foreignKey, $updateMinusOne, ['defer_foreign_keys' => true]);
$secondDeferredUpdate = static fn (): array => SQLiteDeferredForeignKeyRestrictPlan::updateParentKeys($deferredUpdate()['parents'], $children, $foreignKey, $updateMinusOne, ['defer_foreign_keys' => true]);
$immediateDelete = static fn (): array => SQLiteDeferredForeignKeyRestrictPlan::deleteParents($parents, $children, $foreignKey, static fn (array $row): bool => $row['setting_id'] === 1, ['trigger' => $afterDeleteRepair]);
$deferredDelete = static fn (): array => SQLiteDeferredForeignKeyRestrictPlan::deleteParents($parents, $children, $foreignKey, static fn (array $row): bool => $row['setting_id'] === 1, ['defer_foreign_keys' => true, 'trigger' => $afterDeleteRepair]);

$cases = [
    'fkey6 3.2.1 immediate update fails' => [static fn (): mixed => $immediateUpdate()['status'], 'foreign-key-failed'],
    'fkey6 3.2.1 immediate update reason is restrict' => [static fn (): mixed => $immediateUpdate()['failure_reason'], 'immediate-restrict-update'],
    'fkey6 3.2.1 immediate update rolls back parent keys' => [static fn (): mixed => array_column($immediateUpdate()['parents'], 'setting_id'), [1, 2]],
    'fkey6 3.2.1 immediate update records attempted first action' => [static fn (): mixed => $immediateUpdate()['actions'][0]['old_key'], 1],
    'fkey6 3.2.1 immediate update sees matching child' => [static fn (): mixed => $immediateUpdate()['actions'][0]['matching_child_indexes'], [0]],
    'fkey6 3.2.1 immediate update not deferred' => [static fn (): mixed => $immediateUpdate()['actions'][0]['restrict_deferred'], false],
    'fkey6 3.2.1 immediate update has zero changes' => [static fn (): mixed => $immediateUpdate()['changes'], 0],
    'fkey6 3.2.1 immediate update marks rollback' => [static fn (): mixed => $immediateUpdate()['rolled_back'], true],

    'fkey6 3.2.3 deferred update commits' => [static fn (): mixed => $deferredUpdate()['status'], 'committed'],
    'fkey6 3.2.3 deferred pragma is recorded' => [static fn (): mixed => $deferredUpdate()['defer_foreign_keys'], true],
    'fkey6 3.2.3 deferred update parent keys shift' => [static fn (): mixed => array_column($deferredUpdate()['parents'], 'setting_id'), [0, 1]],
    'fkey6 3.2.3 deferred update preserves labels' => [static fn (): mixed => array_column($deferredUpdate()['parents'], 'label'), ['one', 'two']],
    'fkey6 3.2.3 deferred update child remains attached to new parent' => [static fn (): mixed => $deferredUpdate()['children'][0]['parent_setting_id'], 1],
    'fkey6 3.2.3 deferred update first action marked deferred restrict' => [static fn (): mixed => $deferredUpdate()['actions'][0]['restrict_deferred'], true],
    'fkey6 3.2.3 deferred update second action has no matching children' => [static fn (): mixed => $deferredUpdate()['actions'][1]['matching_child_indexes'], []],
    'fkey6 3.2.3 deferred update has no commit violations' => [static fn (): mixed => $deferredUpdate()['violations'], []],
    'fkey6 3.2.3 deferred update counts two parent changes' => [static fn (): mixed => $deferredUpdate()['changes'], 2],
    'fkey6 3.2.3 deferred update dependencies include pragma' => [static fn (): mixed => in_array('sqlite-pragma-defer-foreign-keys', $deferredUpdate()['dependencies'], true), true],

    'fkey6 3.2.5 second deferred update fails at commit' => [static fn (): mixed => $secondDeferredUpdate()['status'], 'foreign-key-failed'],
    'fkey6 3.2.5 second deferred update reason is commit check' => [static fn (): mixed => $secondDeferredUpdate()['failure_reason'], 'deferred-foreign-key-commit'],
    'fkey6 3.2.5 second deferred update rolls back to prior transaction image' => [static fn (): mixed => array_column($secondDeferredUpdate()['parents'], 'setting_id'), [0, 1]],
    'fkey6 3.2.5 second deferred update attempted invalid keys' => [static fn (): mixed => array_column($secondDeferredUpdate()['attempted_parents'], 'setting_id'), [-1, 0]],
    'fkey6 3.2.5 second deferred update reports orphan child' => [static fn (): mixed => $secondDeferredUpdate()['violations'][0]['child_key'], 1],
    'fkey6 3.2.5 second deferred update has zero committed changes' => [static fn (): mixed => $secondDeferredUpdate()['changes'], 0],
    'fkey6 3.2.5 second deferred update records two attempted actions' => [static fn (): mixed => count($secondDeferredUpdate()['actions']), 2],

    'fkey6 3.3.2 immediate delete fails before trigger repair' => [static fn (): mixed => $immediateDelete()['status'], 'foreign-key-failed'],
    'fkey6 3.3.2 immediate delete reason is restrict' => [static fn (): mixed => $immediateDelete()['failure_reason'], 'immediate-restrict-delete'],
    'fkey6 3.3.2 immediate delete leaves parent rows unchanged' => [static fn (): mixed => array_column($immediateDelete()['parents'], 'label'), ['one', 'two']],
    'fkey6 3.3.2 immediate delete does not fire repair trigger' => [static fn (): mixed => $immediateDelete()['trigger_effects'], []],
    'fkey6 3.3.2 immediate delete records matching child' => [static fn (): mixed => $immediateDelete()['actions'][0]['matching_child_indexes'], [0]],

    'fkey6 3.3.4 deferred delete commits after trigger repair' => [static fn (): mixed => $deferredDelete()['status'], 'committed'],
    'fkey6 3.3.4 deferred delete leaves repaired parent key order' => [static fn (): mixed => array_column($deferredDelete()['parents'], 'setting_id'), [1, 2]],
    'fkey6 3.3.4 deferred delete replaces deleted row label' => [static fn (): mixed => array_column($deferredDelete()['parents'], 'label'), ['deleted!', 'two']],
    'fkey6 3.3.4 deferred delete keeps child valid' => [static fn (): mixed => $deferredDelete()['violations'], []],
    'fkey6 3.3.4 deferred delete records trigger name' => [static fn (): mixed => $deferredDelete()['trigger_effects'][0]['trigger'], 'parent_after_delete_repair'],
    'fkey6 3.3.4 deferred delete trigger reinserts old key' => [static fn (): mixed => $deferredDelete()['trigger_effects'][0]['new_key'], 1],
    'fkey6 3.3.4 deferred delete counts delete and trigger insert' => [static fn (): mixed => $deferredDelete()['changes'], 2],
    'fkey6 3.3.4 deferred delete marks restrict deferred' => [static fn (): mixed => $deferredDelete()['actions'][0]['restrict_deferred'], true],
    'fkey6 3.3.4 deferred delete dependencies include trigger repair' => [static fn (): mixed => in_array('sqlite-after-delete-trigger-repair', $deferredDelete()['dependencies'], true), true],

    'bad parent column throws' => [static fn (): mixed => SQLiteDeferredForeignKeyRestrictPlan::updateParentKeys($parents, $children, ['parent_key' => 'bad-name', 'child_key' => 'parent_setting_id'], $updateMinusOne), InvalidArgumentException::class],
    'bad action throws' => [static fn (): mixed => SQLiteDeferredForeignKeyRestrictPlan::updateParentKeys($parents, $children, ['parent_key' => 'setting_id', 'child_key' => 'parent_setting_id', 'on_update' => 'cascade'], $updateMinusOne), InvalidArgumentException::class],
    'bad trigger timing throws' => [static fn (): mixed => SQLiteDeferredForeignKeyRestrictPlan::deleteParents($parents, $children, $foreignKey, static fn (): bool => true, ['defer_foreign_keys' => true, 'trigger' => ['timing' => 'before', 'event' => 'delete', 'action' => 'insert-parent']]), InvalidArgumentException::class],
    'bad trigger old column throws' => [static fn (): mixed => SQLiteDeferredForeignKeyRestrictPlan::deleteParents($parents, $children, $foreignKey, static fn (): bool => true, ['defer_foreign_keys' => true, 'trigger' => ['timing' => 'after', 'event' => 'delete', 'action' => 'insert-parent', 'row' => ['setting_id' => 'old.missing']]]), InvalidArgumentException::class],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['real upstream fkey6 deferred restrict ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        if (is_string($expected) && is_a($expected, Throwable::class, true)) {
            $t->throws($expected, $callback);
            return;
        }

        $t->same($expected, $callback());
    };
}

return $tests;
