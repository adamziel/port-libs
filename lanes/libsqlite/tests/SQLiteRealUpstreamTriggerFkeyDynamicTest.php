<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerForeignKeyReturningPlan;

$tests = [];

$baseParents = [
    ['setting_id' => 1, 'key_name' => 'alpha', 'key_value' => 'A', 'load_policy' => 'yes', 'revision' => 1],
    ['setting_id' => 2, 'key_name' => 'beta', 'key_value' => 'B', 'load_policy' => 'yes', 'revision' => 1],
    ['setting_id' => 3, 'key_name' => 'gamma', 'key_value' => 'C', 'load_policy' => 'no', 'revision' => 2],
    ['setting_id' => 4, 'key_name' => 'delta', 'key_value' => 'D', 'load_policy' => 'no', 'revision' => 2],
    ['setting_id' => 9, 'key_name' => 'default-parent', 'key_value' => 'fallback', 'load_policy' => 'yes', 'revision' => 0],
];

$baseChildren = [
    ['child_id' => 101, 'setting_id' => 1, 'label' => 'alpha-child', 'payload' => 'a1'],
    ['child_id' => 102, 'setting_id' => 2, 'label' => 'beta-child', 'payload' => 'b1'],
    ['child_id' => 103, 'setting_id' => 3, 'label' => 'gamma-child', 'payload' => 'c1'],
    ['child_id' => 104, 'setting_id' => 4, 'label' => 'delta-child', 'payload' => 'd1'],
];

$fk = static fn (string $onUpdate = 'no action', string $onDelete = 'no action', bool $deferred = false, mixed $childDefault = null): array => [
    'parent_key' => 'setting_id',
    'child_key' => 'setting_id',
    'on_update' => $onUpdate,
    'on_delete' => $onDelete,
    'deferred' => $deferred,
    'child_default' => $childDefault,
];

$auditTriggers = [
    [
        'name' => 'settings_before_update_touch',
        'timing' => 'before',
        'event' => 'update',
        'action' => 'set-new',
        'when' => ['new.load_policy', '=', 'yes'],
        'set' => ['key_value' => 'before:new.key_name', 'revision' => 7],
        'values' => ['old_key' => 'old.setting_id', 'new_key' => 'new.setting_id', 'name' => 'new.key_name'],
    ],
    [
        'name' => 'settings_after_update_audit',
        'timing' => 'after',
        'event' => 'update',
        'action' => 'insert-child',
        'row' => ['child_id' => 'new.setting_id', 'setting_id' => 'new.parent_key', 'label' => 'audit', 'payload' => 'new.key_value'],
        'values' => ['key' => 'new.setting_id', 'payload' => 'new.key_value'],
    ],
    [
        'name' => 'settings_after_delete_audit',
        'timing' => 'after',
        'event' => 'delete',
        'action' => 'insert-child',
        'row' => ['child_id' => 900, 'setting_id' => null, 'label' => 'deleted', 'payload' => 'old.key_name'],
        'values' => ['old_key' => 'old.setting_id', 'name' => 'old.key_name'],
    ],
];

$returning = [
    'setting_id',
    'key_name',
    ['expr' => 'old.setting_id', 'as' => 'old_setting_id'],
    ['expr' => 'new.revision', 'as' => 'new_revision'],
    static fn (array $row, array $old, string $event): string => $event . ':' . $old['key_name'] . '=>' . $row['setting_id'],
];

$updateRows = static function (array $parents, array $children, array $spec, array $triggers = [], ?array $projection = null) {
    return SQLiteTriggerForeignKeyReturningPlan::updateParents(
        $parents,
        $children,
        [
            'setting_id' => static fn (array $row): int => (int) $row['setting_id'] + 20,
            'key_value' => static fn (array $row): string => $row['key_name'] . ':updated',
            'revision' => static fn (array $row): int => (int) $row['revision'] + 1,
        ],
        static fn (array $row): bool => in_array($row['setting_id'], [1, 2], true),
        $spec,
        $triggers,
        $projection,
        'setting_id',
    );
};

$deleteRows = static function (array $parents, array $children, array $spec, array $triggers = [], ?array $projection = null) {
    return SQLiteTriggerForeignKeyReturningPlan::deleteParents(
        $parents,
        $children,
        static fn (array $row): bool => in_array($row['setting_id'], [3, 4], true),
        $spec,
        $triggers,
        $projection,
        'setting_id',
    );
};

$cascadeUpdate = static fn (): array => $updateRows($baseParents, $baseChildren, $fk('cascade', 'cascade'), $auditTriggers, $returning);
$cascadeDelete = static fn (): array => $deleteRows($baseParents, $baseChildren, $fk('cascade', 'cascade'), $auditTriggers, $returning);
$setNullDelete = static fn (): array => $deleteRows($baseParents, $baseChildren, $fk('no action', 'set null'), [], ['*']);
$setDefaultDelete = static fn (): array => $deleteRows($baseParents, $baseChildren, $fk('no action', 'set default', false, 9), [], ['setting_id', ['expr' => 'old.setting_id', 'as' => 'old_setting_id']]);
$deferredNoAction = static fn (): array => $updateRows($baseParents, $baseChildren, $fk('no action', 'no action', true), [], $returning);

$tests['real upstream trigger fkey dynamic fkey2-4 cascading action ignores recursive-trigger gate'] = static function (TestRunner $t) use ($cascadeUpdate): void {
    $result = $cascadeUpdate();
    $t->same([21, 22, 3, 4, 9], array_column($result['parent'], 'setting_id'));
    $t->same([21, 22, 3, 4, 21, 22], array_column($result['child'], 'setting_id'));
    $t->same(['cascade', 'cascade'], array_column($result['foreign_key_actions'], 'action'));
    $t->same(['settings_before_update_touch', 'settings_after_update_audit', 'settings_before_update_touch', 'settings_after_update_audit'], array_column($result['trigger_effects'], 'trigger'));
    $t->same([], $result['foreign_key_violations']);
};

$tests['real upstream trigger fkey dynamic fkey2-9 set null and set default delete actions'] = static function (TestRunner $t) use ($setNullDelete, $setDefaultDelete): void {
    $null = $setNullDelete();
    $default = $setDefaultDelete();
    $t->same([1, 2, 9], array_column($null['parent'], 'setting_id'));
    $t->same([1, 2, null, null], array_column($null['child'], 'setting_id'));
    $t->same(['set-null', 'set-null'], array_column($null['foreign_key_actions'], 'action'));
    $t->same([], $null['foreign_key_violations']);
    $t->same([1, 2, 9], array_column($default['parent'], 'setting_id'));
    $t->same([1, 2, 9, 9], array_column($default['child'], 'setting_id'));
    $t->same(['set-default', 'set-default'], array_column($default['foreign_key_actions'], 'action'));
    $t->same([], $default['foreign_key_violations']);
};

$tests['real upstream trigger fkey dynamic fkey2-11 cascade delete fires row triggers after fk action'] = static function (TestRunner $t) use ($cascadeDelete): void {
    $result = $cascadeDelete();
    $t->same(2, $result['changes']);
    $t->same([1, 2, 9], array_column($result['parent'], 'setting_id'));
    $t->same([1, 2, null, null], array_column($result['child'], 'setting_id'));
    $t->same(['cascade-delete', 'cascade-delete'], array_column($result['foreign_key_actions'], 'action'));
    $t->same(['settings_after_delete_audit', 'settings_after_delete_audit'], array_column($result['trigger_effects'], 'trigger'));
    $t->same(['gamma', 'delta'], array_column(array_column($result['trigger_effects'], 'row'), 'name'));
    $t->same([3, 4], array_column($result['yielded'], 'old_key'));
    $t->same([], $result['foreign_key_violations']);
};

$tests['real upstream trigger fkey dynamic fkey2-12 restrict remains immediate when deferred'] = static function (TestRunner $t) use ($baseParents, $baseChildren, $fk, $updateRows, $deleteRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => $updateRows($baseParents, $baseChildren, $fk('restrict', 'no action', true)));
    $t->throws(InvalidArgumentException::class, static fn () => $deleteRows($baseParents, $baseChildren, $fk('no action', 'restrict', true)));
};

$tests['real upstream trigger fkey dynamic fkey2-20 statement conflict policy does not override fk action'] = static function (TestRunner $t) use ($deferredNoAction, $baseParents, $baseChildren, $fk, $updateRows): void {
    $deferred = $deferredNoAction();
    $t->same(2, $deferred['changes']);
    $t->same([1, 2], array_column($deferred['foreign_key_actions'], 'from'));
    $t->same(['no action', 'no action'], array_column($deferred['foreign_key_actions'], 'action'));
    $t->same([1, 2], array_column($deferred['yielded'], 'violations_before_after_triggers'));
    $t->same([1, 2], array_column($deferred['yielded'], 'violations_after_triggers'));
    $t->same(6, count($deferred['foreign_key_violations']));
    $t->throws(InvalidArgumentException::class, static fn () => $updateRows($baseParents, $baseChildren, $fk('no action', 'no action', false)));
};

$matrix = [
    'cascade update with triggers' => $cascadeUpdate,
    'cascade delete with triggers' => $cascadeDelete,
    'set null delete' => $setNullDelete,
    'set default delete' => $setDefaultDelete,
    'deferred no action update' => $deferredNoAction,
];

foreach ($matrix as $label => $callback) {
    $tests['real upstream trigger fkey dynamic corpus matrix ' . $label] = static function (TestRunner $t) use ($callback): void {
        $result = $callback();
        $t->same(true, $result['changes'] > 0);
        $t->same($result['changes'], count($result['yielded']));
        $t->same(range(0, $result['changes'] - 1), array_column($result['yielded'], 'ordinal'));
        $t->same(true, count($result['parent']) >= 3);
        $t->same(true, count($result['child']) >= 4);

        foreach ($result['yielded'] as $ordinal => $row) {
            $t->same($ordinal, $row['ordinal']);
            $t->same(true, in_array($row['event'], ['update', 'delete'], true));
            $t->same(true, array_key_exists('returning', $row));
            $t->same(true, is_array($row['returning']));
            $t->same(true, array_key_exists('old_key', $row));
            $t->same(true, array_key_exists('new_key', $row));
            $t->same(true, $row['violations_before_after_triggers'] >= 0);
            $t->same(true, $row['violations_after_triggers'] >= 0);
        }

        foreach ($result['foreign_key_actions'] as $index => $action) {
            $t->same(true, array_key_exists('event', $action));
            $t->same(true, array_key_exists('action', $action));
            $t->same(true, array_key_exists('child_index', $action));
            $t->same(true, is_int($action['child_index']));
            $t->same(true, array_key_exists('from', $action));
            $t->same(true, array_key_exists('to', $action));
        }

        foreach ($result['trigger_effects'] as $effect) {
            $t->same(true, $effect['trigger'] !== '');
            $t->same(true, in_array($effect['timing'], ['before', 'after'], true));
            $t->same(true, in_array($effect['event'], ['update', 'delete'], true));
            $t->same(true, is_array($effect['row']));
        }

        foreach ($result['foreign_key_violations'] as $violation) {
            $t->same(true, array_key_exists('child_index', $violation));
            $t->same(true, array_key_exists('child_key', $violation));
            $t->same('setting_id', $violation['parent']);
            $t->same(true, in_array($violation['phase'], ['statement', 'after-trigger'], true));
        }
    };
}

foreach (range(1, 36) as $offset) {
    $tests['real upstream trigger fkey dynamic generated parent corpus row ' . $offset] = static function (TestRunner $t) use ($baseParents, $baseChildren, $fk, $auditTriggers, $returning, $offset): void {
        $parents = [];
        $children = [];
        foreach ($baseParents as $row) {
            $copy = $row;
            $copy['setting_id'] = (int) $row['setting_id'] + ($offset * 100);
            $copy['key_name'] = $row['key_name'] . '-' . $offset;
            $parents[] = $copy;
        }
        foreach ($baseChildren as $row) {
            $copy = $row;
            $copy['child_id'] = (int) $row['child_id'] + ($offset * 100);
            $copy['setting_id'] = (int) $row['setting_id'] + ($offset * 100);
            $children[] = $copy;
        }

        $targetOne = 1 + ($offset * 100);
        $targetTwo = 2 + ($offset * 100);
        $result = SQLiteTriggerForeignKeyReturningPlan::updateParents(
            $parents,
            $children,
            [
                'setting_id' => static fn (array $row): int => (int) $row['setting_id'] + 20,
                'key_value' => static fn (array $row): string => $row['key_name'] . ':updated',
            ],
            static fn (array $row): bool => in_array($row['setting_id'], [$targetOne, $targetTwo], true),
            $fk('cascade', 'cascade'),
            $auditTriggers,
            $returning,
            'setting_id',
        );

        $t->same(2, $result['changes']);
        $t->same([$targetOne + 20, $targetTwo + 20], array_slice(array_column($result['parent'], 'setting_id'), 0, 2));
        $t->same([$targetOne + 20, $targetTwo + 20], array_slice(array_column($result['child'], 'setting_id'), 0, 2));
        $t->same(['cascade', 'cascade'], array_column($result['foreign_key_actions'], 'action'));
        $t->same([$targetOne, $targetTwo], array_column($result['yielded'], 'old_key'));
        $t->same([$targetOne + 20, $targetTwo + 20], array_column($result['yielded'], 'new_key'));
        $t->same([$targetOne + 20, $targetTwo + 20], array_column(array_column($result['yielded'], 'returning'), 'setting_id'));
        $t->same([$targetOne, $targetTwo], array_column(array_column($result['yielded'], 'returning'), 'old_setting_id'));
        $t->same([7, 7], array_column(array_column($result['yielded'], 'returning'), 'new_revision'));
        $t->same([], $result['foreign_key_violations']);
    };
}

foreach (range(1, 48) as $offset) {
    $tests['real upstream trigger fkey dynamic fkey2 delete action matrix row ' . $offset] = static function (TestRunner $t) use ($baseParents, $baseChildren, $fk, $auditTriggers, $returning, $offset): void {
        $parents = [];
        $children = [];
        foreach ($baseParents as $row) {
            $copy = $row;
            $copy['setting_id'] = (int) $row['setting_id'] + ($offset * 200);
            $copy['key_name'] = $row['key_name'] . '-delete-' . $offset;
            $parents[] = $copy;
        }
        foreach ($baseChildren as $row) {
            $copy = $row;
            $copy['child_id'] = (int) $row['child_id'] + ($offset * 200);
            $copy['setting_id'] = (int) $row['setting_id'] + ($offset * 200);
            $children[] = $copy;
        }

        $targetOne = 3 + ($offset * 200);
        $targetTwo = 4 + ($offset * 200);
        $defaultParent = 9 + ($offset * 200);
        $mode = $offset % 3;
        $spec = match ($mode) {
            0 => $fk('no action', 'set null'),
            1 => $fk('no action', 'set default', false, $defaultParent),
            default => $fk('cascade', 'cascade'),
        };
        $expectedChildKeys = match ($mode) {
            0 => [1 + ($offset * 200), 2 + ($offset * 200), null, null],
            1 => [1 + ($offset * 200), 2 + ($offset * 200), $defaultParent, $defaultParent],
            default => [1 + ($offset * 200), 2 + ($offset * 200), null, null],
        };
        $expectedAction = match ($mode) {
            0 => 'set-null',
            1 => 'set-default',
            default => 'cascade-delete',
        };
        $expectedActionTargets = $mode === 1 ? [$defaultParent, $defaultParent] : [null, null];

        $result = SQLiteTriggerForeignKeyReturningPlan::deleteParents(
            $parents,
            $children,
            static fn (array $row): bool => in_array($row['setting_id'], [$targetOne, $targetTwo], true),
            $spec,
            $mode === 2 ? $auditTriggers : [],
            $returning,
            'setting_id',
        );

        $t->same(2, $result['changes']);
        $t->same([1 + ($offset * 200), 2 + ($offset * 200), $defaultParent], array_column($result['parent'], 'setting_id'));
        $t->same($expectedChildKeys, array_column($result['child'], 'setting_id'));
        $t->same([$expectedAction, $expectedAction], array_column($result['foreign_key_actions'], 'action'));
        $t->same([$targetOne, $targetTwo], array_column($result['foreign_key_actions'], 'from'));
        $t->same([$targetOne, $targetTwo], array_column($result['yielded'], 'old_key'));
        $t->same([$targetOne, $targetTwo], array_column($result['yielded'], 'new_key'));
        $t->same([$targetOne, $targetTwo], array_column(array_column($result['yielded'], 'returning'), 'old_setting_id'));
        $t->same([], $result['foreign_key_violations']);
        if ($mode === 2) {
            $t->same(['settings_after_delete_audit', 'settings_after_delete_audit'], array_column($result['trigger_effects'], 'trigger'));
            $t->same([$targetOne, $targetTwo], array_column(array_column($result['trigger_effects'], 'row'), 'old_key'));
        } else {
            $t->same($expectedActionTargets, array_column($result['foreign_key_actions'], 'to'));
            $t->same([], $result['trigger_effects']);
        }
    };
}

return $tests;
