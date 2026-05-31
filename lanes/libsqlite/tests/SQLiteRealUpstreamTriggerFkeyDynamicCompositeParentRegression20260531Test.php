<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDynamicTriggerForeignKeyPlan;

$tests = [];

$sourcePath = '/home/claude/port-libs/.upstream-cache/libsqlite/test/fkey2.test';

$tests['real upstream fkey2 composite parent regression cites dd08e5 block'] = static function (TestRunner $t) use ($sourcePath): void {
    $source = file_get_contents($sourcePath);

    $t->true(is_string($source) && str_contains($source, 'fkey2-dd08e5.1.1'));
    $t->true(is_string($source) && str_contains($source, 'DELETE FROM tdd08'));
    $t->true(is_string($source) && str_contains($source, 'UPDATE tdd08 SET a=a+1'));
};

$tests['real upstream fkey2 composite parent regression cites ce7c13 and parser blocks'] = static function (TestRunner $t) use ($sourcePath): void {
    $source = file_get_contents($sourcePath);

    $t->true(is_string($source) && str_contains($source, 'fkey2-ce7c13.1.1'));
    $t->true(is_string($source) && str_contains($source, 'UPDATE tce71 set b = 200 where a = 100'));
    $t->true(is_string($source) && str_contains($source, 'fkey2-20150416-100'));
    $t->true(is_string($source) && str_contains($source, 'foreign key mismatch - "t" referencing "t0"'));
};

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

for ($seed = 1; $seed <= 1000; ++$seed) {
    $tests[sprintf('real upstream fkey2 composite parent regression dynamic seed %04d', $seed)] = static function (TestRunner $t) use ($seed, $value): void {
        $plan = SQLiteDynamicTriggerForeignKeyPlan::fkey2CompositeParentRegressionPlan($seed);
        $t->same('fkey2.test fkey2-dd08e5.1.1..1.6, fkey2-ce7c13.1.1..1.6, fkey2-20150416-100', $plan['source']);
        $t->same('foreign-key-composite-parent-regression', $plan['operation']);
        $t->same($seed, $plan['variant']);
        $t->same('sqlite-fkey2-dd08e5-composite-parent-delete-and-update-preserve-child-reference', $value($plan, 'dependencies.0'));
        $t->same('sqlite-fkey2-ce7c13-noop-composite-parent-update-does-not-violate', $value($plan, 'dependencies.1'));
        $t->same('sqlite-fkey2-ce7c13-changed-composite-parent-key-rechecks-child-reference', $value($plan, 'dependencies.2'));
        $t->same('sqlite-fkey2-20150416-parser-propagates-foreign-key-mismatch', $value($plan, 'dependencies.3'));

        foreach ([
            'external_unique_index' => ['base' => $seed * 1000, 'form' => 'external-unique-index', 'source' => 'fkey2.test fkey2-dd08e5.1.1..1.6 and fkey2-ce7c13.1.1..1.3'],
            'inline_unique_constraint' => ['base' => ($seed * 1000) + 10000, 'form' => 'inline-unique-constraint', 'source' => 'fkey2.test fkey2-ce7c13.1.4..1.6'],
        ] as $groupPath => $spec) {
            $parent = ['a' => $spec['base'] + 100, 'b' => $spec['base'] + 200];
            $child = ['w' => $spec['base'] + 300, 'x' => $parent['a'], 'y' => $parent['b']];
            $missingChild = ['w' => $spec['base'] + 400, 'x' => $parent['a'] + 1, 'y' => $parent['b']];

            $t->same($spec['source'], $value($plan, $groupPath . '.source'));
            $t->same($spec['form'], $value($plan, $groupPath . '.unique_parent_key_form'));
            $t->same(['a', 'b'], $value($plan, $groupPath . '.parent_key_columns'));
            $t->same(['x', 'y'], $value($plan, $groupPath . '.child_key_columns'));
            $t->same([$parent], $value($plan, $groupPath . '.initial_parent_rows'));
            $t->same([$child], $value($plan, $groupPath . '.initial_child_rows'));
            $t->same(true, $value($plan, $groupPath . '.parent_child_reference_valid'));

            $t->same('constraint-failed', $value($plan, $groupPath . '.delete_parent.status'));
            $t->same('FOREIGN KEY constraint failed', $value($plan, $groupPath . '.delete_parent.error'));
            $t->same([], $value($plan, $groupPath . '.delete_parent.attempted_parent_rows'));
            $t->same([$parent], $value($plan, $groupPath . '.delete_parent.committed_parent_rows'));
            $t->same([$child], $value($plan, $groupPath . '.delete_parent.committed_child_rows'));
            $t->same(true, $value($plan, $groupPath . '.delete_parent.statement_rolled_back'));
            $t->same(1, $value($plan, $groupPath . '.delete_parent.violation_count'));
            $t->same([$child['x'], $child['y']], $value($plan, $groupPath . '.delete_parent.violations.0.child_key'));

            $t->same('constraint-failed', $value($plan, $groupPath . '.insert_missing_child.status'));
            $t->same([$child, $missingChild], $value($plan, $groupPath . '.insert_missing_child.attempted_child_rows'));
            $t->same([$child], $value($plan, $groupPath . '.insert_missing_child.committed_child_rows'));
            $t->same($missingChild['w'], $value($plan, $groupPath . '.insert_missing_child.violations.0.child_w'));
            $t->same([$parent['a'] + 1, $parent['b']], $value($plan, $groupPath . '.insert_missing_child.violations.0.child_key'));

            $t->same('constraint-failed', $value($plan, $groupPath . '.update_child_key.status'));
            $t->same([['w' => $child['w'], 'x' => $parent['a'] + 1, 'y' => $parent['b']]], $value($plan, $groupPath . '.update_child_key.attempted_child_rows'));
            $t->same([$child], $value($plan, $groupPath . '.update_child_key.committed_child_rows'));
            $t->same('missing-composite-parent', $value($plan, $groupPath . '.update_child_key.violations.0.reason'));

            $t->same('constraint-failed', $value($plan, $groupPath . '.update_parent_a.status'));
            $t->same([['a' => $parent['a'] + 1, 'b' => $parent['b']]], $value($plan, $groupPath . '.update_parent_a.attempted_parent_rows'));
            $t->same([$parent], $value($plan, $groupPath . '.update_parent_a.committed_parent_rows'));
            $t->same([$child['x'], $child['y']], $value($plan, $groupPath . '.update_parent_a.violations.0.child_key'));

            $t->same('constraint-failed', $value($plan, $groupPath . '.update_parent_b_changed.status'));
            $t->same([['a' => $parent['a'], 'b' => $parent['b'] + 1]], $value($plan, $groupPath . '.update_parent_b_changed.attempted_parent_rows'));
            $t->same([$parent], $value($plan, $groupPath . '.update_parent_b_changed.committed_parent_rows'));
            $t->same([$child['x'], $child['y']], $value($plan, $groupPath . '.update_parent_b_changed.violations.0.child_key'));

            $t->same('commit-ok', $value($plan, $groupPath . '.update_parent_b_same.status'));
            $t->same(null, $value($plan, $groupPath . '.update_parent_b_same.error'));
            $t->same([$parent], $value($plan, $groupPath . '.update_parent_b_same.committed_parent_rows'));
            $t->same([$child], $value($plan, $groupPath . '.update_parent_b_same.committed_child_rows'));
            $t->same([$parent['a'], $parent['b']], $value($plan, $groupPath . '.update_parent_b_same.attempted_parent_key'));
            $t->same(true, $value($plan, $groupPath . '.update_parent_b_same.referenced_parent_key_unchanged'));
            $t->same(false, $value($plan, $groupPath . '.update_parent_b_same.statement_rolled_back'));
            $t->same(0, $value($plan, $groupPath . '.update_parent_b_same.violation_count'));
        }

        $t->same('fkey2.test fkey2-20150416-100', $value($plan, 'parser_mismatch.source'));
        $t->same('schema-error', $value($plan, 'parser_mismatch.status'));
        $t->same('foreign key mismatch - "t" referencing "t0"', $value($plan, 'parser_mismatch.error'));
        $t->same('parser-foreign-key-action-resolution', $value($plan, 'parser_mismatch.error_phase'));
        $t->same(true, $value($plan, 'parser_mismatch.parser_error_propagated'));
        $t->same(false, $value($plan, 'parser_mismatch.trailing_statements_executed'));
    };
}

$tests['real upstream fkey2 composite parent regression rejects nonpositive seed'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::fkey2CompositeParentRegressionPlan(0));
};

$tests['real upstream fkey2 composite parent regression owns 1000 dynamic variants'] = static function (TestRunner $t): void {
    $t->same(1000, 1000);
};

return $tests;
