<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelfReferentialForeignKeyActionPlan;

$treeRows = [
    ['record_id' => 1, 'parent_id' => null, 'label' => 'root'],
    ['record_id' => 2, 'parent_id' => 1, 'label' => 'left'],
    ['record_id' => 3, 'parent_id' => 1, 'label' => 'right'],
    ['record_id' => 4, 'parent_id' => 2, 'label' => 'left-left'],
    ['record_id' => 5, 'parent_id' => 2, 'label' => 'left-right'],
    ['record_id' => 6, 'parent_id' => 3, 'label' => 'right-left'],
    ['record_id' => 7, 'parent_id' => 3, 'label' => 'right-right'],
];

$selfCascadeFk = ['key' => 'record_id', 'parent_key' => 'parent_id', 'on_delete' => 'cascade', 'deferred' => false];
$selfRestrictFk = ['key' => 'record_id', 'parent_key' => 'parent_id', 'on_delete' => 'restrict', 'deferred' => false];
$selfDeferredRestrictFk = ['key' => 'record_id', 'parent_key' => 'parent_id', 'on_delete' => 'restrict', 'deferred' => true];

$recursiveDeleteTrigger = [[
    'name' => 'tree_after_delete',
    'timing' => 'after',
    'event' => 'delete',
    'action' => 'enqueue-delete-children',
    'child_parent_key' => 'parent_id',
    'child_key' => 'record_id',
    'values' => ['deleted' => 'old.record_id', 'parent' => 'old.parent_id'],
]];

$auditTrigger = [[
    'name' => 'tree_delete_audit',
    'timing' => 'after',
    'event' => 'delete',
    'action' => 'audit',
    'values' => ['deleted' => 'old.record_id', 'parent' => 'old.parent_id'],
]];

$reinsertTrigger = [[
    'name' => 'restore_deleted_parent',
    'timing' => 'after',
    'event' => 'delete',
    'action' => 'insert-row',
    'row' => ['record_id' => 'old.record_id', 'parent_id' => 'old.parent_id', 'label' => 'restored'],
    'values' => ['deleted' => 'old.record_id'],
]];

$cascadeTree = static fn (bool $recursiveTriggers): array => SQLiteSelfReferentialForeignKeyActionPlan::deleteRows(
    $treeRows,
    [1],
    $selfCascadeFk,
    [],
    ['recursive_triggers' => $recursiveTriggers, 'current_source' => 'upstream-fkey2-4', 'next_source' => 'foreign-key-cascade'],
);

$triggerTree = static fn (bool $recursiveTriggers): array => SQLiteSelfReferentialForeignKeyActionPlan::deleteRows(
    $treeRows,
    [1],
    ['key' => 'record_id', 'parent_key' => 'parent_id', 'on_delete' => 'no action', 'deferred' => true],
    $recursiveDeleteTrigger,
    ['recursive_triggers' => $recursiveTriggers, 'current_source' => 'upstream-fkey2-4', 'next_source' => 'ordinary-trigger-recursion'],
);

$restrictDelete = static fn (bool $deferred = false, array $triggers = []) => SQLiteSelfReferentialForeignKeyActionPlan::deleteRows(
    $treeRows,
    [1],
    $deferred ? $selfDeferredRestrictFk : $selfRestrictFk,
    $triggers,
    ['recursive_triggers' => true, 'current_source' => 'upstream-fkey6-3', 'next_source' => 'defer-foreign-keys'],
);

$cascadeOff = static fn (): array => $cascadeTree(false);
$cascadeOn = static fn (): array => $cascadeTree(true);
$ordinaryOff = static fn (): array => $triggerTree(false);
$ordinaryOn = static fn (): array => $triggerTree(true);
$deferredRestrict = static fn (): array => $restrictDelete(true);
$deferredRestrictReinsert = static fn (): array => $restrictDelete(true, $reinsertTrigger);

$tests = [
    'real upstream fkey2-4 foreign key cascade ignores recursive trigger pragma off parent rows' => [static fn (): mixed => array_column($cascadeOff()['parent'], 'record_id'), []],
    'real upstream fkey2-4 foreign key cascade ignores recursive trigger pragma off child rows' => [static fn (): mixed => array_column($cascadeOff()['child'], 'record_id'), []],
    'real upstream fkey2-4 foreign key cascade off deletes all descendant parents' => [static fn (): mixed => $cascadeOff()['deleted_parent_keys'], [1]],
    'real upstream fkey2-4 foreign key cascade off records six cascade actions' => [static fn (): mixed => count($cascadeOff()['foreign_key_actions']), 6],
    'real upstream fkey2-4 foreign key cascade off action names' => [static fn (): mixed => array_unique(array_column($cascadeOff()['foreign_key_actions'], 'action')), ['cascade']],
    'real upstream fkey2-4 foreign key cascade off cascades child keys in tree order' => [static fn (): mixed => array_column($cascadeOff()['foreign_key_actions'], 'child_key'), [2, 4, 5, 3, 6, 7]],
    'real upstream fkey2-4 foreign key cascade off has no trigger effects' => [static fn (): mixed => $cascadeOff()['trigger_effects'], []],
    'real upstream fkey2-4 foreign key cascade off commit ok' => [static fn (): mixed => $cascadeOff()['commit_status'], 'ok'],
    'real upstream fkey2-4 foreign key cascade off dependency marker' => [static fn (): mixed => $cascadeOff()['dependencies'][1], 'sqlite-foreign-key-actions-ignore-recursive-trigger-pragma'],
    'real upstream fkey2-4 foreign key cascade off source label' => [static fn (): mixed => $cascadeOff()['current_source'], 'upstream-fkey2-4'],
    'real upstream fkey2-4 foreign key cascade off next label' => [static fn (): mixed => $cascadeOff()['next_source'], 'foreign-key-cascade'],
    'real upstream fkey2-4 foreign key cascade off recursive flag false' => [static fn (): mixed => $cascadeOff()['recursive_triggers'], false],

    'real upstream fkey2-4 foreign key cascade pragma on parent rows' => [static fn (): mixed => array_column($cascadeOn()['parent'], 'record_id'), []],
    'real upstream fkey2-4 foreign key cascade pragma on child rows' => [static fn (): mixed => array_column($cascadeOn()['child'], 'record_id'), []],
    'real upstream fkey2-4 foreign key cascade on records same cascade action count' => [static fn (): mixed => count($cascadeOn()['foreign_key_actions']), 6],
    'real upstream fkey2-4 foreign key cascade on child keys match off' => [static fn (): mixed => array_column($cascadeOn()['foreign_key_actions'], 'child_key'), [2, 4, 5, 3, 6, 7]],
    'real upstream fkey2-4 foreign key cascade on still has no ordinary trigger effects' => [static fn (): mixed => $cascadeOn()['trigger_effects'], []],
    'real upstream fkey2-4 foreign key cascade on commit ok' => [static fn (): mixed => $cascadeOn()['commit_status'], 'ok'],
    'real upstream fkey2-4 foreign key cascade on recursive flag true' => [static fn (): mixed => $cascadeOn()['recursive_triggers'], true],

    'real upstream fkey2-4 ordinary recursive trigger off deletes only root parent' => [static fn (): mixed => array_column($ordinaryOff()['parent'], 'record_id'), [2, 3, 4, 5, 6, 7]],
    'real upstream fkey2-4 ordinary recursive trigger off leaves grandchildren' => [static fn (): mixed => array_column($ordinaryOff()['parent'], 'label'), ['left', 'right', 'left-left', 'left-right', 'right-left', 'right-right']],
    'real upstream fkey2-4 ordinary recursive trigger off deleted keys' => [static fn (): mixed => $ordinaryOff()['deleted_parent_keys'], [1]],
    'real upstream fkey2-4 ordinary recursive trigger off trigger effects one row' => [static fn (): mixed => count($ordinaryOff()['trigger_effects']), 1],
    'real upstream fkey2-4 ordinary recursive trigger off records trigger name' => [static fn (): mixed => $ordinaryOff()['trigger_effects'][0]['trigger'], 'tree_after_delete'],
    'real upstream fkey2-4 ordinary recursive trigger off records deleted old row' => [static fn (): mixed => $ordinaryOff()['trigger_effects'][0]['row']['deleted'], 1],
    'real upstream fkey2-4 ordinary recursive trigger off queues two child violations' => [static fn (): mixed => count($ordinaryOff()['deferred_violations']), 2],
    'real upstream fkey2-4 ordinary recursive trigger off first violating child key' => [static fn (): mixed => $ordinaryOff()['deferred_violations'][0]['child_key'], 2],
    'real upstream fkey2-4 ordinary recursive trigger off commit fails deferred check' => [static fn (): mixed => $ordinaryOff()['commit_status'], 'deferred-constraint-failed'],
    'real upstream fkey2-4 ordinary recursive trigger off source label' => [static fn (): mixed => $ordinaryOff()['next_source'], 'ordinary-trigger-recursion'],

    'real upstream fkey2-4 ordinary recursive trigger on deletes every parent' => [static fn (): mixed => array_column($ordinaryOn()['parent'], 'record_id'), []],
    'real upstream fkey2-4 ordinary recursive trigger on deleted key order' => [static fn (): mixed => $ordinaryOn()['deleted_parent_keys'], [1, 2, 3, 4, 5, 6, 7]],
    'real upstream fkey2-4 ordinary recursive trigger on effect count' => [static fn (): mixed => count($ordinaryOn()['trigger_effects']), 7],
    'real upstream fkey2-4 ordinary recursive trigger on effect depths' => [static fn (): mixed => array_column($ordinaryOn()['trigger_effects'], 'depth'), [0, 1, 1, 2, 2, 2, 2]],
    'real upstream fkey2-4 ordinary recursive trigger on effect statements' => [static fn (): mixed => array_column($ordinaryOn()['trigger_effects'], 'statement'), [0, 1, 2, 3, 4, 5, 6]],
    'real upstream fkey2-4 ordinary recursive trigger on row deleted order' => [static fn (): mixed => array_column(array_column($ordinaryOn()['trigger_effects'], 'row'), 'deleted'), [1, 2, 3, 4, 5, 6, 7]],
    'real upstream fkey2-4 ordinary recursive trigger on has no violations' => [static fn (): mixed => $ordinaryOn()['deferred_violations'], []],
    'real upstream fkey2-4 ordinary recursive trigger on commit ok' => [static fn (): mixed => $ordinaryOn()['commit_status'], 'ok'],
    'real upstream fkey2-4 ordinary recursive trigger on recursive flags recorded' => [static fn (): mixed => array_unique(array_column($ordinaryOn()['trigger_effects'], 'recursive_triggers')), [true]],

    'real upstream fkey6-3 restrict delete fails immediately without defer pragma' => [static fn (): mixed => $restrictDelete(false), InvalidArgumentException::class],
    'real upstream fkey6-3 deferred restrict deletes parent statement' => [static fn (): mixed => array_column($deferredRestrict()['parent'], 'record_id'), [2, 3, 4, 5, 6, 7]],
    'real upstream fkey6-3 deferred restrict preserves remaining children before failed commit' => [static fn (): mixed => array_column($deferredRestrict()['child'], 'record_id'), [2, 3, 4, 5, 6, 7]],
    'real upstream fkey6-3 deferred restrict records restrict actions for direct children' => [static fn (): mixed => count($deferredRestrict()['foreign_key_actions']), 2],
    'real upstream fkey6-3 deferred restrict action names' => [static fn (): mixed => array_column($deferredRestrict()['foreign_key_actions'], 'action'), ['restrict', 'restrict']],
    'real upstream fkey6-3 deferred restrict action child indexes' => [static fn (): mixed => array_column($deferredRestrict()['foreign_key_actions'], 'child_index'), [0, 1]],
    'real upstream fkey6-3 deferred restrict reports child violations' => [static fn (): mixed => count($deferredRestrict()['deferred_violations']), 2],
    'real upstream fkey6-3 deferred restrict violation phase' => [static fn (): mixed => $deferredRestrict()['deferred_violations'][0]['phase'], 'commit'],
    'real upstream fkey6-3 deferred restrict violation child key' => [static fn (): mixed => $deferredRestrict()['deferred_violations'][0]['child_key'], 2],
    'real upstream fkey6-3 deferred restrict commit status fails' => [static fn (): mixed => $deferredRestrict()['commit_status'], 'deferred-constraint-failed'],
    'real upstream fkey6-3 deferred restrict records source' => [static fn (): mixed => $deferredRestrict()['current_source'], 'upstream-fkey6-3'],
    'real upstream fkey6-3 deferred restrict records next' => [static fn (): mixed => $deferredRestrict()['next_source'], 'defer-foreign-keys'],

    'real upstream fkey6-3 deferred restrict reinsert trigger parent restored before commit' => [static fn (): mixed => array_column($deferredRestrictReinsert()['parent'], 'record_id'), [2, 3, 4, 5, 6, 7, 1]],
    'real upstream fkey6-3 deferred restrict reinsert trigger adds replacement row' => [static fn (): mixed => array_column($deferredRestrictReinsert()['child'], 'record_id'), [2, 3, 4, 5, 6, 7, 1]],
    'real upstream fkey6-3 deferred restrict reinsert trigger replacement restores deleted key' => [static fn (): mixed => $deferredRestrictReinsert()['child'][6]['record_id'], 1],
    'real upstream fkey6-3 deferred restrict reinsert trigger commit succeeds like fkey6' => [static fn (): mixed => $deferredRestrictReinsert()['commit_status'], 'ok'],
    'real upstream fkey6-3 deferred restrict reinsert trigger clears violations' => [static fn (): mixed => $deferredRestrictReinsert()['deferred_violations'], []],
    'real upstream fkey6-3 deferred restrict reinsert trigger replacement label' => [static fn (): mixed => $deferredRestrictReinsert()['child'][6]['label'], 'restored'],
    'real upstream fkey6-3 deferred restrict reinsert trigger effect name' => [static fn (): mixed => $deferredRestrictReinsert()['trigger_effects'][0]['trigger'], 'restore_deleted_parent'],
    'real upstream fkey6-3 deferred restrict reinsert trigger effect action' => [static fn (): mixed => $deferredRestrictReinsert()['trigger_effects'][0]['action'], 'insert-row'],
    'real upstream fkey6-3 deferred restrict reinsert trigger effect row' => [static fn (): mixed => $deferredRestrictReinsert()['trigger_effects'][0]['row']['deleted'], 1],

    'real upstream trigger audit dynamic when false is skipped' => [static fn (): mixed => SQLiteSelfReferentialForeignKeyActionPlan::deleteRows($treeRows, [1], $selfCascadeFk, [['name' => 'skip_audit', 'timing' => 'after', 'event' => 'delete', 'action' => 'audit', 'when' => false]])['trigger_effects'], []],
    'real upstream trigger audit dynamic when true fires' => [static fn (): mixed => SQLiteSelfReferentialForeignKeyActionPlan::deleteRows($treeRows, [1], ['key' => 'record_id', 'parent_key' => 'parent_id', 'on_delete' => 'no action', 'deferred' => true], $auditTrigger)['trigger_effects'][0]['row']['deleted'], 1],
    'real upstream trigger dynamic malformed when rejected' => [static fn (): mixed => SQLiteSelfReferentialForeignKeyActionPlan::deleteRows($treeRows, [1], $selfCascadeFk, [['name' => 'bad', 'timing' => 'after', 'event' => 'delete', 'action' => 'audit', 'when' => ['old.record_id', '=']]]), InvalidArgumentException::class],
    'real upstream trigger dynamic unsupported delete action rejected' => [static fn (): mixed => SQLiteSelfReferentialForeignKeyActionPlan::deleteRows($treeRows, [1], $selfCascadeFk, [['name' => 'bad', 'timing' => 'after', 'event' => 'delete', 'action' => 'update-parent']]), InvalidArgumentException::class],
    'real upstream trigger dynamic depth limit rejected' => [static fn (): mixed => SQLiteSelfReferentialForeignKeyActionPlan::deleteRows($treeRows, [1], ['key' => 'record_id', 'parent_key' => 'parent_id', 'on_delete' => 'no action', 'deferred' => true], $recursiveDeleteTrigger, ['max_depth' => 1]), InvalidArgumentException::class],
    'real upstream trigger dynamic malformed parent key rejected' => [static fn (): mixed => SQLiteSelfReferentialForeignKeyActionPlan::deleteRows($treeRows, [1], ['key' => 'record_id', 'parent_key' => 'bad-key', 'on_delete' => 'cascade']), InvalidArgumentException::class],
    'real upstream trigger dynamic malformed child key rejected' => [static fn (): mixed => SQLiteSelfReferentialForeignKeyActionPlan::deleteRows($treeRows, [1], ['key' => '1bad', 'parent_key' => 'parent_id', 'on_delete' => 'cascade']), InvalidArgumentException::class],
    'real upstream trigger dynamic unsupported fk action rejected' => [static fn (): mixed => SQLiteSelfReferentialForeignKeyActionPlan::deleteRows($treeRows, [1], ['key' => 'record_id', 'parent_key' => 'parent_id', 'on_delete' => 'abort']), InvalidArgumentException::class],
    'real upstream trigger dynamic set null action leaves null children' => [static fn (): mixed => array_column(SQLiteSelfReferentialForeignKeyActionPlan::deleteRows($treeRows, [1], ['key' => 'record_id', 'parent_key' => 'parent_id', 'on_delete' => 'set null', 'deferred' => true])['parent'], 'record_id'), [2, 3, 4, 5, 6, 7]],
    'real upstream trigger dynamic set default action records default' => [static fn (): mixed => array_column(SQLiteSelfReferentialForeignKeyActionPlan::deleteRows($treeRows, [1], ['key' => 'record_id', 'parent_key' => 'parent_id', 'on_delete' => 'set default', 'default' => 0, 'deferred' => true])['foreign_key_actions'], 'action'), ['set default', 'set default']],
];

return array_map(
    static function (array $case): Closure {
        return static function (TestRunner $t) use ($case): void {
            [$actual, $expected] = $case;
            if ($expected === InvalidArgumentException::class) {
                $t->throws(InvalidArgumentException::class, $actual);

                return;
            }

            $t->same($expected, $actual());
        };
    },
    $tests,
);
