<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDynamicTriggerForeignKeyPlan;

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

        throw new RuntimeException("Missing assertion path {$path}");
    }

    return $cursor;
};

$tests = [
    'real upstream triggerG recursive once cites single-source block' => static function (TestRunner $t): void {
        $upstream = '/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerG.test';
        $source = (string) file_get_contents($upstream);

        $t->same(true, is_file($upstream));
        $t->contains('do_execsql_test 100', $source);
        $t->contains('INSERT INTO t2 SELECT new.c*100+a FROM t1 WHERE a IN (1, 2, 3, 4);', $source);
        $t->contains('SELECT b FROM t2 ORDER BY b;', $source);
    },
    'real upstream triggerG recursive once cites join-source block' => static function (TestRunner $t): void {
        $upstream = '/home/claude/port-libs/.upstream-cache/libsqlite/test/triggerG.test';
        $source = (string) file_get_contents($upstream);

        $t->same(true, is_file($upstream));
        $t->contains('do_execsql_test 200', $source);
        $t->contains('FROM t1 AS xx, t1 AS yy', $source);
        $t->contains('WHERE xx.a IN (1,2,3,4)', $source);
        $t->contains('AND yy.a IN (2,3,4,5)', $source);
    },
];

$expectedSingleOutput = static function (int $start, int $max, array $indexed, array $filter): array {
    $selected = array_values(array_filter($indexed, static fn (int $value): bool => in_array($value, $filter, true)));
    sort($selected);
    $rows = [];
    for ($triggerValue = $start; $triggerValue <= $max; ++$triggerValue) {
        foreach ($selected as $value) {
            $rows[] = ($triggerValue * 100) + $value;
        }
    }
    sort($rows);

    return $rows;
};

$expectedJoinOutput = static function (int $start, int $max, array $indexed, array $leftFilter, array $rightFilter): array {
    $left = array_values(array_filter($indexed, static fn (int $value): bool => in_array($value, $leftFilter, true)));
    $right = array_values(array_filter($indexed, static fn (int $value): bool => in_array($value, $rightFilter, true)));
    sort($left);
    sort($right);
    $rows = [];
    for ($triggerValue = $start; $triggerValue <= $max; ++$triggerValue) {
        foreach ($left as $leftValue) {
            foreach ($right as $rightValue) {
                $rows[] = ($triggerValue * 10000) + ($leftValue * 100) + $rightValue;
            }
        }
    }
    sort($rows);

    return $rows;
};

for ($i = 1; $i <= 500; ++$i) {
    $start = 1 + ($i % 4);
    $max = $start + 2 + ($i % 5);
    $indexed = [0, 2, 3, 8, 9, 10 + ($i % 7), 20 + ($i % 11)];
    if ($i % 3 === 0) {
        $indexed[] = 1;
    }
    if ($i % 5 === 0) {
        $indexed[] = 4;
    }
    if ($i % 7 === 0) {
        $indexed[] = 5;
    }
    sort($indexed);

    $leftFilter = [1, 2, 3, 4];
    $rightFilter = [2, 3, 4, 5];

    $single = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::recursiveTriggerInsertSelectOncePlan(
        $indexed,
        $start,
        $max,
        $leftFilter
    );
    $join = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::recursiveTriggerInsertSelectOncePlan(
        $indexed,
        $start,
        $max,
        $leftFilter,
        $rightFilter
    );

    $insertedValues = range($start, $max);
    $selectedLeft = array_values(array_filter($indexed, static fn (int $value): bool => in_array($value, $leftFilter, true)));
    sort($selectedLeft);
    $selectedRight = array_values(array_filter($indexed, static fn (int $value): bool => in_array($value, $rightFilter, true)));
    sort($selectedRight);
    $expectedSingleRows = $expectedSingleOutput($start, $max, $indexed, $leftFilter);
    $expectedJoinRows = $expectedJoinOutput($start, $max, $indexed, $leftFilter, $rightFilter);

    $tests[sprintf('real upstream triggerG recursive once single-source dynamic %03d', $i)] = static function (TestRunner $t) use ($single, $value, $start, $max, $indexed, $leftFilter, $insertedValues, $selectedLeft, $expectedSingleRows): void {
        $plan = $single();

        $t->same('triggerG.test triggerG-100..110', $value($plan, 'source'));
        $t->same('recursive-trigger-insert-select-once', $value($plan, 'operation'));
        $t->same($start, $value($plan, 'initial_value'));
        $t->same($max, $value($plan, 'max_value'));
        $t->same($indexed, $value($plan, 'indexed_values'));
        $t->same($leftFilter, $value($plan, 'left_filter'));
        $t->same(null, $value($plan, 'right_filter'));
        $t->same($selectedLeft, $value($plan, 'selected_left_values'));
        $t->same(true, $value($plan, 'recursive_triggers'));
        $t->same(count($insertedValues), $value($plan, 'trigger_invocations'));
        $t->same($insertedValues, $value($plan, 'inserted_trigger_values'));
        $t->same($expectedSingleRows, $value($plan, 'trigger_output_rows'));
        $t->same(count($expectedSingleRows), $value($plan, 'output_count'));
        $t->same('sqlite-triggerG-recursive-trigger-reruns-insert-select-program', $value($plan, 'dependencies.0'));
        $t->same('sqlite-triggerG-op-once-subprogram-does-not-suppress-recursive-select', $value($plan, 'dependencies.1'));
        $t->same('sqlite-triggerG-recursive-trigger-single-source-select', $value($plan, 'dependencies.2'));
    };

    $tests[sprintf('real upstream triggerG recursive once join-source dynamic %03d', $i)] = static function (TestRunner $t) use ($join, $value, $start, $max, $indexed, $leftFilter, $rightFilter, $insertedValues, $selectedLeft, $selectedRight, $expectedJoinRows): void {
        $plan = $join();

        $t->same('triggerG.test triggerG-200', $value($plan, 'source'));
        $t->same('recursive-trigger-join-select-once', $value($plan, 'operation'));
        $t->same($start, $value($plan, 'initial_value'));
        $t->same($max, $value($plan, 'max_value'));
        $t->same($indexed, $value($plan, 'indexed_values'));
        $t->same($leftFilter, $value($plan, 'left_filter'));
        $t->same($rightFilter, $value($plan, 'right_filter'));
        $t->same($selectedLeft, $value($plan, 'selected_left_values'));
        $t->same($selectedRight, $value($plan, 'selected_right_values'));
        $t->same(true, $value($plan, 'recursive_triggers'));
        $t->same(count($insertedValues), $value($plan, 'trigger_invocations'));
        $t->same($insertedValues, $value($plan, 'inserted_trigger_values'));
        $t->same($expectedJoinRows, $value($plan, 'trigger_output_rows'));
        $t->same(count($expectedJoinRows), $value($plan, 'output_count'));
        $t->same('sqlite-triggerG-recursive-trigger-reruns-insert-select-program', $value($plan, 'dependencies.0'));
        $t->same('sqlite-triggerG-op-once-subprogram-does-not-suppress-recursive-select', $value($plan, 'dependencies.1'));
        $t->same('sqlite-triggerG-recursive-trigger-join-source-select', $value($plan, 'dependencies.2'));
    };
}

$tests['real upstream triggerG recursive once rejects inverted recursion range'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::recursiveTriggerInsertSelectOncePlan([1, 2, 3], 5, 4, [1]));
};

$tests['real upstream triggerG recursive once rejects empty left filter'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::recursiveTriggerInsertSelectOncePlan([1, 2, 3], 1, 2, []));
};

$tests['real upstream triggerG recursive once rejects empty right filter'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::recursiveTriggerInsertSelectOncePlan([1, 2, 3], 1, 2, [1], []));
};

return $tests;
