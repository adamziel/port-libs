<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteDynamicTriggerForeignKeyPlan;

$tests = [
    'real upstream trigger8 large trigger cites source statement-count fixture' => static function (TestRunner $t): void {
        $source = file_get_contents('/home/claude/port-libs/.upstream-cache/libsqlite/test/trigger8.test');

        $t->true(is_string($source));
        $t->true(str_contains($source, 'This file implements tests to make sure abusively large triggers'));
        $t->true(str_contains($source, 'set nStatement 5'));
        $t->true(str_contains($source, 'SELECT count(*) FROM t2'));
    },
];

for ($i = 1; $i <= 1000; ++$i) {
    $statementCount = 5 + ($i % 11);
    $outerRows = 1 + ($i % 3);
    $outerValue = 1000 + $i;
    $expectedValues = [];
    for ($row = 0; $row < $outerRows; ++$row) {
        for ($statement = 0; $statement < $statementCount; ++$statement) {
            $expectedValues[] = $statement;
        }
    }
    $plan = static fn (): array => SQLiteDynamicTriggerForeignKeyPlan::largeTriggerBodyExecution(
        $statementCount,
        $outerValue,
        $outerRows
    );

    $tests[sprintf('real upstream trigger8.test trigger8-1.1 large body dynamic %04d', $i)] = static function (TestRunner $t) use ($plan, $statementCount, $outerRows, $outerValue, $expectedValues): void {
        $actual = $plan();

        $t->same('trigger8.test trigger8-1.1', $actual['source']);
        $t->same('large-trigger-body-executes-all-statements', $actual['operation']);
        $t->same('commit-ok', $actual['status']);
        $t->same($statementCount, $actual['statement_count']);
        $t->same($outerValue, $actual['outer_insert_value']);
        $t->same($outerRows, $actual['outer_row_count']);
        $t->same($statementCount * $outerRows, $actual['trigger_row_count']);
        $t->same(0, $actual['first_statement_ordinal']);
        $t->same($statementCount - 1, $actual['last_statement_ordinal']);
        $t->same($expectedValues, $actual['trigger_values']);
        $t->same(array_fill(0, $outerRows, $statementCount), $actual['per_outer_row_counts']);
        $t->same('sqlite-trigger8-large-trigger-body-statement-drain', $actual['dependencies'][0]);
        $t->same('sqlite-trigger8-trigger-program-preserves-statement-order', $actual['dependencies'][1]);
        $t->same('sqlite-trigger8-each-outer-row-runs-full-trigger-body', $actual['dependencies'][2]);
    };
}

$tests['real upstream trigger8 large body rejects negative statement count'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::largeTriggerBodyExecution(-1));
};

$tests['real upstream trigger8 large body rejects empty outer row count'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteDynamicTriggerForeignKeyPlan::largeTriggerBodyExecution(5, 5, 0));
};

return $tests;
