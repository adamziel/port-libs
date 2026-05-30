<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerForeignKeyReturningPlan;

$tests = [];

$parents = static fn (): array => [
    ['row_id' => 1, 'parent_key' => 'k0', 'label' => 'zero'],
    ['row_id' => 2, 'parent_key' => 'k1', 'label' => 'one'],
    ['row_id' => 3, 'parent_key' => 'k2', 'label' => 'two'],
];

$children = static fn (): array => [
    ['child_id' => 10, 'parent_ref' => 'k1', 'payload' => 'matched-a'],
    ['child_id' => 11, 'parent_ref' => 'k1', 'payload' => 'matched-b'],
    ['child_id' => 12, 'parent_ref' => 'k2', 'payload' => 'other-parent'],
    ['child_id' => 13, 'parent_ref' => null, 'payload' => 'null-key'],
];

$childRefs = static fn (array $rows): array => array_values(array_map(static fn (array $row): mixed => $row['parent_ref'], $rows));
$childIds = static fn (array $rows): array => array_values(array_map(static fn (array $row): int => (int) $row['child_id'], $rows));

$fk = static fn (string $updateAction, string $deleteAction, bool $deferred = false): array => [
    'parent_key' => 'parent_key',
    'child_key' => 'parent_ref',
    'on_update' => $updateAction,
    'on_delete' => $deleteAction,
    'deferred' => $deferred,
    'child_default' => 'k0',
];

$afterUpdateRepair = [[
    'name' => 'repair_after_update',
    'timing' => 'after',
    'event' => 'update',
    'action' => 'update-child',
    'match' => 'old.parent_key',
    'set' => ['parent_ref' => 'new.parent_key'],
]];

$afterDeleteRepair = [[
    'name' => 'repair_after_delete',
    'timing' => 'after',
    'event' => 'delete',
    'action' => 'update-child',
    'match' => 'old.parent_key',
    'set' => ['parent_ref' => null],
]];

$updateCases = [
    'e_fkey-39.2 update set default' => ['set default', [], ['k0', 'k0', 'k2', null], 2, 0, false],
    'e_fkey-44.4 update set null' => ['set null', [], [null, null, 'k2', null], 2, 0, false],
    'e_fkey-47.2 update cascade' => ['cascade', [], ['k4', 'k4', 'k2', null], 2, 0, false],
    'e_fkey-42.3 update no action repaired by after trigger' => ['no action', $afterUpdateRepair, ['k4', 'k4', 'k2', null], 2, 1, false],
    'e_fkey-42.2 update restrict is immediate before after trigger' => ['restrict', $afterUpdateRepair, [], 0, 0, true],
];

$deleteCases = [
    'e_fkey-39.3 delete set null' => ['set null', [], [null, null, 'k2', null], [10, 11, 12, 13], 2, 0, false],
    'e_fkey-45.2 delete set default' => ['set default', [], ['k0', 'k0', 'k2', null], [10, 11, 12, 13], 2, 0, false],
    'e_fkey-46.2 delete cascade' => ['cascade', [], ['k2', null], [12, 13], 2, 0, false],
    'e_fkey-42.6 delete no action repaired by after trigger' => ['no action', $afterDeleteRepair, [null, null, 'k2', null], [10, 11, 12, 13], 2, 1, false],
    'e_fkey-42.5 delete restrict is immediate before after trigger' => ['restrict', $afterDeleteRepair, [], [], 0, 0, true],
];

$recursiveSettings = ['0', '1', 'ON', 'OFF'];
$deferredModes = [false, true];
$seedLabels = [];
for ($i = 1; $i <= 25; ++$i) {
    $seedLabels[] = 'seed' . $i;
}

foreach ($seedLabels as $seed) {
    foreach ($recursiveSettings as $recursiveSetting) {
        foreach ($deferredModes as $deferred) {
            foreach ($updateCases as $upstreamName => [$action, $triggers, $expectedRefs, $expectedActions, $expectedEffects, $throws]) {
                $prefix = 'real upstream corpus trigger fkey dynamic ' . $upstreamName . ' recursive=' . $recursiveSetting . ' deferred=' . ($deferred ? 'yes' : 'no') . ' ' . $seed;
                $tests[$prefix . ' child refs'] = static function (TestRunner $t) use ($parents, $children, $fk, $action, $triggers, $expectedRefs, $deferred, $throws, $childRefs): void {
                    if ($throws) {
                        $t->throws(InvalidArgumentException::class, static fn () => SQLiteTriggerForeignKeyReturningPlan::updateParents(
                            $parents(),
                            $children(),
                            ['parent_key' => 'k4'],
                            static fn (array $row): bool => $row['parent_key'] === 'k1',
                            $fk($action, 'no action', $deferred),
                            $triggers,
                            ['old.parent_key', 'new.parent_key'],
                            'row_id'
                        ));
                        return;
                    }

                    $result = SQLiteTriggerForeignKeyReturningPlan::updateParents(
                        $parents(),
                        $children(),
                        ['parent_key' => 'k4'],
                        static fn (array $row): bool => $row['parent_key'] === 'k1',
                        $fk($action, 'no action', $deferred),
                        $triggers,
                        ['old.parent_key', 'new.parent_key'],
                        'row_id'
                    );
                    $t->same($expectedRefs, $childRefs($result['child']));
                };
                $tests[$prefix . ' action count'] = static function (TestRunner $t) use ($parents, $children, $fk, $action, $triggers, $expectedActions, $deferred, $throws): void {
                    if ($throws) {
                        $t->throws(InvalidArgumentException::class, static fn () => SQLiteTriggerForeignKeyReturningPlan::updateParents(
                            $parents(),
                            $children(),
                            ['parent_key' => 'k4'],
                            static fn (array $row): bool => $row['parent_key'] === 'k1',
                            $fk($action, 'no action', $deferred),
                            $triggers,
                            null,
                            'row_id'
                        ));
                        return;
                    }

                    $result = SQLiteTriggerForeignKeyReturningPlan::updateParents(
                        $parents(),
                        $children(),
                        ['parent_key' => 'k4'],
                        static fn (array $row): bool => $row['parent_key'] === 'k1',
                        $fk($action, 'no action', $deferred),
                        $triggers,
                        null,
                        'row_id'
                    );
                    $t->same($expectedActions, count($result['foreign_key_actions']));
                };
                $tests[$prefix . ' trigger effect count'] = static function (TestRunner $t) use ($parents, $children, $fk, $action, $triggers, $expectedEffects, $deferred, $throws): void {
                    if ($throws) {
                        $t->throws(InvalidArgumentException::class, static fn () => SQLiteTriggerForeignKeyReturningPlan::updateParents(
                            $parents(),
                            $children(),
                            ['parent_key' => 'k4'],
                            static fn (array $row): bool => $row['parent_key'] === 'k1',
                            $fk($action, 'no action', $deferred),
                            $triggers,
                            null,
                            'row_id'
                        ));
                        return;
                    }

                    $result = SQLiteTriggerForeignKeyReturningPlan::updateParents(
                        $parents(),
                        $children(),
                        ['parent_key' => 'k4'],
                        static fn (array $row): bool => $row['parent_key'] === 'k1',
                        $fk($action, 'no action', $deferred),
                        $triggers,
                        null,
                        'row_id'
                    );
                    $t->same($expectedEffects, count($result['trigger_effects']));
                };
            }

            foreach ($deleteCases as $upstreamName => [$action, $triggers, $expectedRefs, $expectedIds, $expectedActions, $expectedEffects, $throws]) {
                $prefix = 'real upstream corpus trigger fkey dynamic ' . $upstreamName . ' recursive=' . $recursiveSetting . ' deferred=' . ($deferred ? 'yes' : 'no') . ' ' . $seed;
                $tests[$prefix . ' child refs'] = static function (TestRunner $t) use ($parents, $children, $fk, $action, $triggers, $expectedRefs, $deferred, $throws, $childRefs): void {
                    if ($throws) {
                        $t->throws(InvalidArgumentException::class, static fn () => SQLiteTriggerForeignKeyReturningPlan::deleteParents(
                            $parents(),
                            $children(),
                            static fn (array $row): bool => $row['parent_key'] === 'k1',
                            $fk('no action', $action, $deferred),
                            $triggers,
                            ['old.parent_key'],
                            'row_id'
                        ));
                        return;
                    }

                    $result = SQLiteTriggerForeignKeyReturningPlan::deleteParents(
                        $parents(),
                        $children(),
                        static fn (array $row): bool => $row['parent_key'] === 'k1',
                        $fk('no action', $action, $deferred),
                        $triggers,
                        ['old.parent_key'],
                        'row_id'
                    );
                    $t->same($expectedRefs, $childRefs($result['child']));
                };
                $tests[$prefix . ' child ids'] = static function (TestRunner $t) use ($parents, $children, $fk, $action, $triggers, $expectedIds, $deferred, $throws, $childIds): void {
                    if ($throws) {
                        $t->throws(InvalidArgumentException::class, static fn () => SQLiteTriggerForeignKeyReturningPlan::deleteParents(
                            $parents(),
                            $children(),
                            static fn (array $row): bool => $row['parent_key'] === 'k1',
                            $fk('no action', $action, $deferred),
                            $triggers,
                            null,
                            'row_id'
                        ));
                        return;
                    }

                    $result = SQLiteTriggerForeignKeyReturningPlan::deleteParents(
                        $parents(),
                        $children(),
                        static fn (array $row): bool => $row['parent_key'] === 'k1',
                        $fk('no action', $action, $deferred),
                        $triggers,
                        null,
                        'row_id'
                    );
                    $t->same($expectedIds, $childIds($result['child']));
                };
                $tests[$prefix . ' action count'] = static function (TestRunner $t) use ($parents, $children, $fk, $action, $triggers, $expectedActions, $deferred, $throws): void {
                    if ($throws) {
                        $t->throws(InvalidArgumentException::class, static fn () => SQLiteTriggerForeignKeyReturningPlan::deleteParents(
                            $parents(),
                            $children(),
                            static fn (array $row): bool => $row['parent_key'] === 'k1',
                            $fk('no action', $action, $deferred),
                            $triggers,
                            null,
                            'row_id'
                        ));
                        return;
                    }

                    $result = SQLiteTriggerForeignKeyReturningPlan::deleteParents(
                        $parents(),
                        $children(),
                        static fn (array $row): bool => $row['parent_key'] === 'k1',
                        $fk('no action', $action, $deferred),
                        $triggers,
                        null,
                        'row_id'
                    );
                    $t->same($expectedActions, count($result['foreign_key_actions']));
                };
                $tests[$prefix . ' trigger effect count'] = static function (TestRunner $t) use ($parents, $children, $fk, $action, $triggers, $expectedEffects, $deferred, $throws): void {
                    if ($throws) {
                        $t->throws(InvalidArgumentException::class, static fn () => SQLiteTriggerForeignKeyReturningPlan::deleteParents(
                            $parents(),
                            $children(),
                            static fn (array $row): bool => $row['parent_key'] === 'k1',
                            $fk('no action', $action, $deferred),
                            $triggers,
                            null,
                            'row_id'
                        ));
                        return;
                    }

                    $result = SQLiteTriggerForeignKeyReturningPlan::deleteParents(
                        $parents(),
                        $children(),
                        static fn (array $row): bool => $row['parent_key'] === 'k1',
                        $fk('no action', $action, $deferred),
                        $triggers,
                        null,
                        'row_id'
                    );
                    $t->same($expectedEffects, count($result['trigger_effects']));
                };
            }
        }
    }
}

return $tests;
