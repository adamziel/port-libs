<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAutoIndexDynamicPlan;

$tests = [];

// Source truth: SQLite upstream test/autoindex4.test sections autoindex4-1.0
// through autoindex4-4.8. These cases cover automatic partial-index behavior
// around ON-vs-WHERE filtering, LEFT/RIGHT JOIN equivalence, scalar subqueries,
// ORDER BY preservation, and optimization-control parity.
foreach (SQLiteAutoIndexDynamicPlan::autoindex4PartialIndexJoinCases(1000) as $case) {
    $tests['real upstream autoindex4 partial join dynamic case ' . str_pad((string) $case['case'], 4, '0', STR_PAD_LEFT)] = static function (TestRunner $t) use ($case): void {
        $t->same('autoindex4.test autoindex4-1.0 through autoindex4-4.8', $case['source']);
        $t->true($case['case'] >= 1 && $case['case'] <= 1000);
        $t->true(in_array($case['upstream_section'], [
            'autoindex4-1.0',
            'autoindex4-1.2/1.2-rj',
            'autoindex4-1.3/1.3-rj',
            'autoindex4-1.4/1.4-rj',
            'autoindex4-2.0',
            'autoindex4-3.0/3.1',
            'autoindex4-3.10/3.11',
            'autoindex4-4.1',
            'autoindex4-4.2',
            'autoindex4-4.5.1',
            'autoindex4-4.5.2',
            'autoindex4-4.6',
        ], true));
        $t->true($case['scenario'] !== '');
        $t->true($case['join_kind'] !== '');
        $t->same('ok', $case['integrity']);
        $t->true(array_values($case['result_rows']) === $case['result_rows']);

        foreach ($case['result_rows'] as $row) {
            $t->true(array_values($row) === $row);
        }

        if ($case['upstream_section'] === 'autoindex4-1.0') {
            $t->same('JOIN', $case['join_kind']);
            $t->same('a=234 AND x=987', $case['on_clause']);
            $t->same(true, $case['automatic_index']);
            $t->same(true, $case['order_by_preserved']);
            $t->same([
                [234, 'def', 987, 'rqp', '|'],
                [234, 'def', 987, 'zyx', '|'],
                [234, 'ghi', 987, 'rqp', '|'],
                [234, 'ghi', 987, 'zyx', '|'],
            ], $case['result_rows']);
        }

        if ($case['upstream_section'] === 'autoindex4-1.2/1.2-rj') {
            $t->same(true, $case['right_join_equivalent']);
            $t->same('', $case['where_clause']);
            $t->same(4, count($case['result_rows']));
            $t->same([123, 'abc', null, null, '|'], $case['result_rows'][0]);
            $t->same([345, 'jkl', null, null, '|'], $case['result_rows'][3]);
        }

        if ($case['upstream_section'] === 'autoindex4-1.3/1.3-rj') {
            $t->same('a=234', $case['where_clause']);
            $t->same(true, $case['right_join_equivalent']);
            $t->same([[234, 'def', null, null, '|'], [234, 'ghi', null, null, '|']], $case['result_rows']);
        }

        if ($case['upstream_section'] === 'autoindex4-1.4/1.4-rj') {
            $t->same('a=234 AND x=555', $case['where_clause']);
            $t->same(false, $case['uses_partial_autoindex']);
            $t->same([], $case['result_rows']);
        }

        if ($case['upstream_section'] === 'autoindex4-2.0') {
            $t->same('SCALAR SUBQUERY', $case['join_kind']);
            $t->same([[1, 123, 654, '|'], [0, 555, 444, '|'], [4, 234, 987, '|']], $case['result_rows']);
            $t->true(str_contains($case['detail'], 'count(*)'));
        }

        if ($case['upstream_section'] === 'autoindex4-3.0/3.1') {
            $t->same(true, $case['order_by_preserved']);
            $t->same(true, $case['right_join_equivalent']);
            $t->same([['Item1'], ['Item2']], $case['result_rows']);
            $t->true(str_contains($case['detail'], 'ORDER BY'));
        }

        if ($case['upstream_section'] === 'autoindex4-3.10/3.11') {
            $t->same(false, $case['automatic_index']);
            $t->same(true, $case['uses_partial_autoindex']);
            $t->same([['Item1'], ['Item2']], $case['result_rows']);
        }

        if ($case['upstream_section'] === 'autoindex4-4.1') {
            $t->same('y=4 OR y IS NULL', $case['where_clause']);
            $t->same([[3, 4, 3, 4]], $case['result_rows']);
        }

        if ($case['upstream_section'] === 'autoindex4-4.2') {
            $t->same(true, $case['optimization_control']);
            $t->same('coalesce(y,4)==4', $case['where_clause']);
            $t->same([[1, 2, null, null], [3, 4, 3, 4]], $case['result_rows']);
        }

        if ($case['upstream_section'] === 'autoindex4-4.5.1') {
            $t->same([null, 4, null, null], $case['result_rows'][1]);
            $t->same(2, count($case['result_rows']));
        }

        if ($case['upstream_section'] === 'autoindex4-4.5.2') {
            $t->same('y NOT IN ()', $case['where_clause']);
            $t->same(3, count($case['result_rows']));
            $t->same([1, 2, 1, 2], $case['result_rows'][0]);
        }

        if ($case['upstream_section'] === 'autoindex4-4.6') {
            $t->same('a=x AND y=4', $case['on_clause']);
            $t->same([[1, 2, null, null], [3, 4, 3, 4]], $case['result_rows']);
        }
    };
}

$tests['real upstream autoindex4 partial join dynamic corpus rejects invalid size'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteAutoIndexDynamicPlan::autoindex4PartialIndexJoinCases(0));
};

return $tests;
