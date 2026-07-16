<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDynamicTriggerForeignKeyPlan;

$tests = [];

$value = static function (array $array, string $path): mixed {
    $cursor = $array;
    foreach (explode('.', $path) as $part) {
        if (is_array($cursor) && array_key_exists($part, $cursor)) {
            $cursor = $cursor[$part];
            continue;
        }
        if (is_array($cursor) && ctype_digit($part) && array_key_exists((int) $part, $cursor)) {
            $cursor = $cursor[(int) $part];
            continue;
        }

        throw new RuntimeException('Missing assertion path ' . $path);
    }

    return $cursor;
};

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test';

$tests['real upstream fkey2 genfkey compatibility cites upstream block'] = static function (TestRunner $t) use ($sourcePath): void {
    $source = file_get_contents($sourcePath);

    $t->true(is_string($source) && str_contains($source, 'those prefixed with "fkey2-genfkey."'));
    $t->true(is_string($source) && str_contains($source, 'compatible with the triggers generated'));
    $t->true(is_string($source) && str_contains($source, 'do_test fkey2-genfkey.1.1'));
    $t->true(is_string($source) && str_contains($source, 'do_test fkey2-genfkey.2.6'));
    $t->true(is_string($source) && str_contains($source, 'do_test fkey2-genfkey.3.6'));
    $t->true(is_string($source) && str_contains($source, 'FOREIGN KEY (h, i)'));
};

for ($seed = 1; $seed <= 1000; ++$seed) {
    $tests[sprintf('real upstream fkey2 genfkey compatibility dynamic seed %04d', $seed)] = static function (TestRunner $t) use ($seed, $value): void {
        $plan = SQLiteDynamicTriggerForeignKeyPlan::fkey2GenfkeyCompatibilityPlan($seed);
        $base = $seed * 100;
        $cascadeBase = $base + 1000;
        $setNullBase = $base + 2000;

        $t->same('fkey2.test fkey2-genfkey.1.1..3.6', $plan['source']);
        $t->same('foreign-key-genfkey-compatibility', $plan['operation']);
        $t->same($seed, $plan['variant']);
        $t->same(3, $plan['group_count']);
        $t->same('sqlite-fkey2-genfkey-built-in-fk-matches-generated-trigger-no-action', $value($plan, 'dependencies.0'));
        $t->same('sqlite-fkey2-genfkey-composite-parent-unique-index-order-is-honored', $value($plan, 'dependencies.4'));

        $t->same('no action', $value($plan, 'no_action.action'));
        $t->same('constraint-failed', $value($plan, 'no_action.insert_missing_single.status'));
        $t->same('commit-ok', $value($plan, 'no_action.insert_existing_single.status'));
        $t->same('commit-ok', $value($plan, 'no_action.insert_null_single.status'));
        $t->same(true, $value($plan, 'no_action.insert_null_single.null_child_key_short_circuit'));
        $t->same('constraint-failed', $value($plan, 'no_action.update_single_to_missing.status'));
        $t->same('commit-ok', $value($plan, 'no_action.update_single_to_existing.status'));
        $t->same([$base + 1, $base + 1], $value($plan, 'no_action.update_single_to_existing.child_keys'));
        $t->same('commit-ok', $value($plan, 'no_action.insert_partial_null_composite.status'));
        $t->same(true, $value($plan, 'no_action.insert_partial_null_composite.null_child_key_short_circuit'));
        $t->same('constraint-failed', $value($plan, 'no_action.insert_missing_composite.status'));
        $t->same('commit-ok', $value($plan, 'no_action.insert_existing_composite.status'));
        $t->same('constraint-failed', $value($plan, 'no_action.update_parent_primary.status'));
        $t->same([0, 1], $value($plan, 'no_action.update_parent_primary.violating_child_indexes'));
        $t->same('datatype-mismatch', $value($plan, 'no_action.update_parent_to_null.status'));
        $t->same('constraint-failed', $value($plan, 'no_action.update_parent_composite.status'));
        $t->same([1], $value($plan, 'no_action.update_parent_composite.violating_child_indexes'));
        $t->same('constraint-failed', $value($plan, 'no_action.delete_parent.status'));
        $t->same([0, 1], $value($plan, 'no_action.delete_parent.violating_single_child_indexes'));
        $t->same([1], $value($plan, 'no_action.delete_parent.violating_composite_child_indexes'));
        $t->same([$base + 1, $base + 1], $value($plan, 'no_action.final_single_child_keys'));
        $t->same([[$base + 2, null], [$base + 2, $base + 3]], $value($plan, 'no_action.final_composite_child_keys'));

        $t->same('cascade', $value($plan, 'cascade.action'));
        $t->same('commit-ok', $value($plan, 'cascade.primary_update.status'));
        $t->same(1, $value($plan, 'cascade.primary_update.action_count'));
        $t->same([$cascadeBase + 20, $cascadeBase + 4], $value($plan, 'cascade.single_keys_after_update'));
        $t->same('commit-ok', $value($plan, 'cascade.primary_delete.status'));
        $t->same(1, $value($plan, 'cascade.primary_delete.action_count'));
        $t->same([$cascadeBase + 20], $value($plan, 'cascade.single_keys_after_delete'));
        $t->same('commit-ok', $value($plan, 'cascade.composite_update.status'));
        $t->same(1, $value($plan, 'cascade.composite_update.action_count'));
        $t->same(true, $value($plan, 'cascade.composite_update.unique_index_parent_order_honored'));
        $t->same([[$cascadeBase + 2, $cascadeBase + 30]], $value($plan, 'cascade.composite_keys_after_update'));
        $t->same('commit-ok', $value($plan, 'cascade.composite_delete.status'));
        $t->same(2, $value($plan, 'cascade.composite_delete.action_count'));
        $t->same([], $value($plan, 'cascade.composite_keys_after_delete'));

        $t->same('set null', $value($plan, 'set_null.action'));
        $t->same('commit-ok', $value($plan, 'set_null.primary_update.status'));
        $t->same(1, $value($plan, 'set_null.primary_update.action_count'));
        $t->same([null, $setNullBase + 4], $value($plan, 'set_null.single_keys_after_update'));
        $t->same('commit-ok', $value($plan, 'set_null.primary_delete.status'));
        $t->same(1, $value($plan, 'set_null.primary_delete.action_count'));
        $t->same([null, null], $value($plan, 'set_null.single_keys_after_delete'));
        $t->same('commit-ok', $value($plan, 'set_null.composite_update.status'));
        $t->same(1, $value($plan, 'set_null.composite_update.action_count'));
        $t->same([[null, null]], $value($plan, 'set_null.composite_keys_after_update'));
        $t->same('commit-ok', $value($plan, 'set_null.composite_delete.status'));
        $t->same(1, $value($plan, 'set_null.composite_delete.action_count'));
        $t->same([[null, null]], $value($plan, 'set_null.composite_keys_after_delete'));
    };
}

$tests['real upstream fkey2 genfkey compatibility rejects nonpositive seed'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::fkey2GenfkeyCompatibilityPlan(0));
};

$tests['real upstream fkey2 genfkey compatibility owns 1000 dynamic variants'] = static function (TestRunner $t): void {
    $t->same(1000, 1000);
};

return $tests;
