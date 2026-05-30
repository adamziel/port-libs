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
    'real upstream trigger6 dynamic cites insert expression block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger6.test');
        $t->true(is_string($source) && str_contains($source, 'INSERT INTO t1 VALUES(1,counter())'));
        $t->true(is_string($source) && str_contains($source, 'do_test trigger6-1.2'));
    },
    'real upstream trigger6 dynamic cites update expression block' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger6.test');
        $t->true(is_string($source) && str_contains($source, 'UPDATE t1 SET y=counter(5)'));
        $t->true(is_string($source) && str_contains($source, 'do_test trigger6-1.6'));
    },
];

for ($i = 1; $i <= 250; ++$i) {
    $insertPlan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerNewExpressionEvaluation(
        'insert',
        ['x' => $i, 'y' => 'counter()'],
        $i - 1,
        0
    );
    $insertOffsetPlan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerNewExpressionEvaluation(
        'insert',
        ['x' => $i + 1000, 'y' => 'counter(2,3)+4'],
        $i,
        4,
        [2, 3]
    );
    $updatePlan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerNewExpressionEvaluation(
        'update',
        ['x' => $i + 2000, 'y' => 'counter(5)'],
        $i + 1,
        0,
        [5]
    );
    $updateOffsetPlan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::triggerNewExpressionEvaluation(
        'update',
        ['x' => $i + 3000, 'y' => 'counter(7)+9'],
        $i + 2,
        9,
        [7]
    );

    $tests["trigger6-1.1 insert counter expression evaluated once dynamic {$i}"] = static function (TestRunner $t) use ($insertPlan, $i, $value): void {
        $plan = $insertPlan();
        $t->same([
            'trigger6.test trigger6-1.1..1.6',
            'trigger-new-expression-evaluated-once',
            'insert',
            $i - 1,
            $i,
            1,
            ['x' => $i, 'y' => $i],
            ['trigger' => 'r1', 'event' => 'insert', 'a' => 1, 'new_x' => $i, 'new_y' => $i],
            true,
            'sqlite-trigger6-side-effect-expression-evaluated-once',
        ], [
            $value($plan, 'source'),
            $value($plan, 'operation'),
            $value($plan, 'event'),
            $value($plan, 'counter_before'),
            $value($plan, 'counter_after'),
            $value($plan, 'expression_evaluations'),
            $value($plan, 'row'),
            $value($plan, 'log_rows.0'),
            $value($plan, 'new_image_matches_stored_row'),
            $value($plan, 'dependencies.0'),
        ]);
    };

    $tests["trigger6-1.3 insert counter offset expression evaluated once dynamic {$i}"] = static function (TestRunner $t) use ($insertOffsetPlan, $i, $value): void {
        $plan = $insertOffsetPlan();
        $expectedY = $i + 1 + 4;
        $t->same([
            'insert',
            [2, 3],
            4,
            $i + 1,
            ['x' => $i + 1000, 'y' => $expectedY],
            ['trigger' => 'r1', 'event' => 'insert', 'a' => 1, 'new_x' => $i + 1000, 'new_y' => $expectedY],
            true,
            'sqlite-trigger6-before-insert-new-row-reuses-evaluated-expression',
        ], [
            $value($plan, 'event'),
            $value($plan, 'counter_args'),
            $value($plan, 'expression_offset'),
            $value($plan, 'counter_after'),
            $value($plan, 'row'),
            $value($plan, 'log_rows.0'),
            $value($plan, 'new_image_matches_stored_row'),
            $value($plan, 'dependencies.1'),
        ]);
    };

    $tests["trigger6-1.5 update counter expression evaluated once dynamic {$i}"] = static function (TestRunner $t) use ($updatePlan, $i, $value): void {
        $plan = $updatePlan();
        $expectedY = $i + 2;
        $t->same([
            'update',
            [5],
            0,
            $expectedY,
            ['x' => $i + 2000, 'y' => $expectedY],
            ['trigger' => 'r2', 'event' => 'update', 'a' => 2, 'new_x' => $i + 2000, 'new_y' => $expectedY],
            true,
            'sqlite-trigger6-before-update-new-row-reuses-evaluated-expression',
        ], [
            $value($plan, 'event'),
            $value($plan, 'counter_args'),
            $value($plan, 'expression_offset'),
            $value($plan, 'counter_after'),
            $value($plan, 'row'),
            $value($plan, 'log_rows.0'),
            $value($plan, 'new_image_matches_stored_row'),
            $value($plan, 'dependencies.2'),
        ]);
    };

    $tests["trigger6-1.6 update counter offset expression evaluated once dynamic {$i}"] = static function (TestRunner $t) use ($updateOffsetPlan, $i, $value): void {
        $plan = $updateOffsetPlan();
        $expectedCounter = $i + 3;
        $expectedY = $expectedCounter + 9;
        $t->same([
            'trigger6.test trigger6-1.1..1.6',
            'commit-ok',
            'update',
            [7],
            9,
            $expectedCounter,
            ['x' => $i + 3000, 'y' => $expectedY],
            ['trigger' => 'r2', 'event' => 'update', 'a' => 2, 'new_x' => $i + 3000, 'new_y' => $expectedY],
            true,
        ], [
            $value($plan, 'source'),
            $value($plan, 'status'),
            $value($plan, 'event'),
            $value($plan, 'counter_args'),
            $value($plan, 'expression_offset'),
            $value($plan, 'counter_after'),
            $value($plan, 'row'),
            $value($plan, 'log_rows.0'),
            $value($plan, 'new_image_matches_stored_row'),
        ]);
    };
}

$tests['trigger6 dynamic rejects unsupported trigger event'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::triggerNewExpressionEvaluation('delete', ['x' => 1, 'y' => 2]));
};

$tests['trigger6 dynamic rejects missing expression row fields'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::triggerNewExpressionEvaluation('insert', ['x' => 1]));
};

return $tests;
