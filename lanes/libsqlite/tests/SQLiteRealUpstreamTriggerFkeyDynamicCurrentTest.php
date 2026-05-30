<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTriggerReturningFkSavepointCurrentNextPlan;
use PortLibs\LibSqlite\SQLiteTriggerReturningRecursiveFkCurrentSourceNextPlan;

$tests = [];

/**
 * Real upstream source coverage:
 * - fkey2.test fkey2-1.1/fkey2-1.2 immediate and deferred FK checks.
 * - fkey6.test fkey6-1.2..1.10 ON UPDATE action behavior.
 * - trigger1.test trigger1-3.*, trigger1-10.*, trigger1-17..24 trigger timing/errors.
 * - trigger4.test trigger4-1..7 recursive trigger effects.
 * - trigger7.test trigger7-1..3 trigger plus FK interaction.
 */

$makeUpdateFixture = static function (int $case): array {
    $base = $case * 10;

    return [
        [
            ['setting_id' => $base + 1, 'key_name' => 'alpha_' . $case, 'key_value' => 'old-a', 'load_policy' => 'eager', 'revision' => 1],
            ['setting_id' => $base + 2, 'key_name' => 'beta_' . $case, 'key_value' => 'old-b', 'load_policy' => 'lazy', 'revision' => 2],
            ['setting_id' => $base + 3, 'key_name' => 'gamma_' . $case, 'key_value' => 'old-c', 'load_policy' => 'guard', 'revision' => 3],
            ['setting_id' => $base + 4, 'key_name' => 'delta_' . $case, 'key_value' => 'old-d', 'load_policy' => 'skip', 'revision' => 4],
        ],
        [
            ['meta_id' => $base + 101, 'setting_id' => $base + 1, 'meta_key' => 'owner'],
            ['meta_id' => $base + 102, 'setting_id' => $base + 2, 'meta_key' => 'owner'],
            ['meta_id' => $base + 103, 'setting_id' => $base + 3, 'meta_key' => 'owner'],
            ['meta_id' => $base + 104, 'setting_id' => $base + 4, 'meta_key' => 'owner'],
        ],
        [
            'setting_id' => static fn (array $old): int => (int) $old['setting_id'] + 1000 + $case,
            'key_value' => static fn (array $old): string => 'next-' . $old['key_name'],
            'revision' => static fn (array $old): int => (int) $old['revision'] + 1,
        ],
        [
            [
                'name' => 'app_settings_before_update_rewrite',
                'timing' => 'before',
                'event' => 'update',
                'action' => 'set-new',
                'when' => ['new.load_policy', '=', 'eager'],
                'set' => ['key_value' => 'concat:rewritten::new.key_name'],
                'values' => ['old_key' => 'old.setting_id', 'new_key' => 'new.setting_id', 'value' => 'new.key_value'],
            ],
            [
                'name' => 'app_settings_before_update_ignore',
                'timing' => 'before',
                'event' => 'update',
                'action' => 'raise',
                'when' => ['new.load_policy', '=', 'skip'],
                'raise' => 'ignore',
                'reason' => 'row-ignored',
                'values' => ['name' => 'new.key_name'],
            ],
            [
                'name' => 'app_settings_after_update_rollback',
                'timing' => 'after',
                'event' => 'update',
                'action' => 'raise',
                'when' => ['new.load_policy', '=', 'guard'],
                'raise' => 'rollback',
                'reason' => 'trigger-requested-rollback',
                'values' => ['name' => 'new.key_name'],
            ],
        ],
    ];
};

$runUpdate = static function (int $case, string $mode) use ($makeUpdateFixture): array {
    [$parents, $children, $assignments, $triggers] = $makeUpdateFixture($case);
    $fk = ['parent_key' => 'setting_id', 'child_key' => 'setting_id'];
    $options = ['savepoint' => 'app_stmt_' . $case];
    $where = static fn (array $row): bool => $row['load_policy'] === 'eager';

    if ($mode === 'cascade') {
        $fk['on_update'] = 'cascade';
    } elseif ($mode === 'set-null') {
        $fk['on_update'] = 'set null';
        $where = static fn (array $row): bool => $row['load_policy'] === 'lazy';
    } elseif ($mode === 'deferred') {
        $fk['on_update'] = 'no action';
        $fk['deferred'] = true;
        $where = static fn (array $row): bool => $row['load_policy'] === 'guard';
        $options['conflict_action'] = 'keep-deferred';
        $triggers = [];
    } elseif ($mode === 'immediate') {
        $fk['on_update'] = 'no action';
        $triggers = [];
    } elseif ($mode === 'ignore') {
        $fk['on_update'] = 'cascade';
        $where = static fn (array $row): bool => $row['load_policy'] === 'skip';
    } elseif ($mode === 'rollback') {
        $fk['on_update'] = 'cascade';
        $where = static fn (array $row): bool => $row['load_policy'] === 'guard';
    } else {
        throw new InvalidArgumentException('unknown mode');
    }

    return SQLiteTriggerReturningFkSavepointCurrentNextPlan::update(
        $parents,
        $children,
        $assignments,
        $where,
        $fk,
        $triggers,
        [
            'setting_id',
            'key_name',
            ['expr' => 'old.setting_id', 'as' => 'old_setting_id'],
            ['expr' => 'new.revision', 'as' => 'next_revision'],
            static fn (array $row, array $old, string $event): string => $event . ':' . $old['key_name'] . '=>' . $row['setting_id'],
        ],
        $options,
    );
};

foreach (['cascade', 'set-null', 'deferred', 'immediate', 'ignore', 'rollback'] as $mode) {
    for ($case = 1; $case <= 100; $case++) {
        $tests["real upstream trigger fkey dynamic current {$case} update {$mode}"] = static function (TestRunner $t) use ($runUpdate, $case, $mode): void {
            $result = $runUpdate($case, $mode);
            $base = $case * 10;
            $newKey = $base + 1 + 1000 + $case;

            if ($mode === 'cascade') {
                $t->same('released', $result['status']);
                $t->same($newKey, $result['next_parent'][0]['setting_id']);
                $t->same($newKey, $result['next_child'][0]['setting_id']);
                $t->same('rewrittenalpha_' . $case, $result['next_parent'][0]['key_value']);
                $t->same(['cascade'], array_column($result['foreign_key_actions'], 'action'));
                $t->same([], $result['foreign_key_violations']);
                $t->same('update:alpha_' . $case . '=>' . $newKey, $result['returning_rows'][0]['expr4']);
                return;
            }

            if ($mode === 'set-null') {
                $t->same('released', $result['status']);
                $t->same(null, $result['next_child'][1]['setting_id']);
                $t->same(['set-null'], array_column($result['foreign_key_actions'], 'action'));
                $t->same($base + 2, $result['returning_rows'][0]['old_setting_id']);
                return;
            }

            if ($mode === 'deferred') {
                $t->same('deferred-violation', $result['status']);
                $t->same($base + 3 + 1000 + $case, $result['next_parent'][2]['setting_id']);
                $t->same($base + 3, $result['next_child'][2]['setting_id']);
                $t->same([true, true], array_column($result['foreign_key_violations'], 'deferred'));
                return;
            }

            if ($mode === 'immediate') {
                $t->same('rolled-back', $result['status']);
                $t->same([$base + 1, $base + 2, $base + 3, $base + 4], array_column($result['next_parent'], 'setting_id'));
                $t->same('foreign-key-constraint', $result['rollback_reason']);
                $t->same([], $result['returning_rows']);
                return;
            }

            if ($mode === 'ignore') {
                $t->same('released', $result['status']);
                $t->same('skipped', $result['yielded'][0]['status']);
                $t->same([$base + 1, $base + 2, $base + 3, $base + 4], array_column($result['next_parent'], 'setting_id'));
                $t->same([], $result['returning_rows']);
                return;
            }

            $t->same('rolled-back', $result['status']);
            $t->same('trigger-requested-rollback', $result['rollback_reason']);
            $t->same([$base + 1, $base + 2, $base + 3, $base + 4], array_column($result['next_parent'], 'setting_id'));
            $t->same([], $result['returning_rows']);
            $t->same('attempted-before-rollback', $result['yielded'][0]['status']);
        };
    }
}

$makeDeleteFixture = static function (int $case): array {
    $base = $case * 100;
    $parents = [];
    $children = [];
    $grandchildren = [];
    for ($i = 1; $i <= 5; $i++) {
        $parents[] = [
            'setting_id' => $base + $i,
            'next_id' => $i < 5 ? $base + $i + 1 : null,
            'key_name' => 'node_' . $case . '_' . $i,
            'key_value' => 'value_' . $i,
        ];
        $children[] = ['meta_id' => $base + 50 + $i, 'setting_id' => $base + $i, 'meta_key' => 'child_' . $i];
        $grandchildren[] = ['detail_id' => $base + 80 + $i, 'setting_id' => $base + $i, 'detail' => 'grand_' . $i];
    }

    return [$parents, $children, $grandchildren];
};

$runDelete = static function (int $case, string $mode) use ($makeDeleteFixture): array {
    [$parents, $children, $grandchildren] = $makeDeleteFixture($case);
    $base = $case * 100;
    $statement = [
        'savepoint' => 'app_recursive_' . $case,
        'current_source' => 'main@trigger-fkey-' . $case,
        'next_source' => 'main@trigger-fkey-' . ($case + 1),
        'where' => static fn (array $row): bool => $row['setting_id'] === $base + 1,
        'trigger' => ['name' => 'app_settings_after_delete_chain', 'match_column' => 'setting_id', 'match_value' => 'old.next_id'],
        'returning' => [
            ['expr' => 'old.setting_id', 'as' => 'deleted_id'],
            'key_name',
            ['expr' => 'context.trigger_depth', 'as' => 'depth'],
            ['expr' => 'context.trigger_source', 'as' => 'source_name'],
        ],
    ];
    if ($mode === 'rollback') {
        $statement['rollback_to_savepoint'] = true;
    } elseif ($mode === 'non-recursive') {
        $statement['recursive_triggers'] = false;
    } elseif ($mode === 'depth-limit') {
        $statement['max_depth'] = 2;
    } elseif ($mode !== 'commit') {
        throw new InvalidArgumentException('unknown delete mode');
    }

    return SQLiteTriggerReturningRecursiveFkCurrentSourceNextPlan::delete(
        $parents,
        $children,
        $grandchildren,
        ['parent_key' => 'setting_id', 'child_key' => 'setting_id', 'grandchild_key' => 'setting_id', 'deferred' => true, 'on_delete' => 'cascade'],
        $statement,
    );
};

foreach (['commit', 'rollback', 'non-recursive', 'depth-limit'] as $mode) {
    for ($case = 1; $case <= 150; $case++) {
        $tests["real upstream trigger fkey dynamic current {$case} delete {$mode}"] = static function (TestRunner $t) use ($runDelete, $case, $mode): void {
            $base = $case * 100;
            if ($mode === 'depth-limit') {
                $t->throws(InvalidArgumentException::class, static fn (): array => $runDelete($case, $mode));
                return;
            }

            $result = $runDelete($case, $mode);
            if ($mode === 'commit') {
                $t->same('current-yield-next-commit', $result['status']);
                $t->same([$base + 1, $base + 2, $base + 3, $base + 4, $base + 5], $result['deleted_parent_keys']);
                $t->same([], $result['next_parent_keys']);
                $t->same(15, $result['next_changes']);
                $t->same([0], array_column($result['current_returning_rows'], 'depth'));
                $t->same([1, 2, 3, 4], array_column(array_column($result['trigger_returning_rows'], 'returning'), 'depth'));
                return;
            }

            if ($mode === 'rollback') {
                $t->same('rolled-back-to-savepoint-after-returning-yield', $result['status']);
                $t->same([$base + 1, $base + 2, $base + 3, $base + 4, $base + 5], $result['next_parent_keys']);
                $t->same(0, $result['next_changes']);
                $t->same([], $result['next_returning_rows']);
                $t->same(true, $result['yield_suppressed_by_savepoint']);
                return;
            }

            $t->same('current-yield-next-commit', $result['status']);
            $t->same([$base + 1], $result['deleted_parent_keys']);
            $t->same([$base + 2, $base + 3, $base + 4, $base + 5], $result['next_parent_keys']);
            $t->same([], $result['trigger_returning_rows']);
            $t->same(3, $result['next_changes']);
        };
    }
}

return $tests;
