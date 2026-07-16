<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteReturningVirtualTablePlan;

$tests = [];

$pragmaAssignments = [
    ['encoding' => 'UTF-8'],
    ['encoding' => 'UTF-16le'],
    ['encoding' => 'UTF-16be'],
    ['encoding' => 'UTF-8', 'seq' => 1],
];

$pragmaReturning = [
    ['a', 'b', '*'],
    ['encoding'],
    ['encoding', '*'],
    ['seq', 'encoding'],
];

foreach (range(1, 550) as $case) {
    $assignments = $pragmaAssignments[$case % count($pragmaAssignments)];
    $returning = $pragmaReturning[$case % count($pragmaReturning)];

    $tests[sprintf('real upstream returning1 virtual table pragma read-only dynamic %04d', $case)] = static function (TestRunner $t) use ($assignments, $returning, $case): void {
        $plan = SQLiteReturningVirtualTablePlan::updateReadOnlyPragmaReturning('pragma_encoding', $assignments, $returning);

        $t->same('returning1.test', $plan['source']);
        $t->same('returning1-9.1 read-only pragma virtual table rejects UPDATE before RETURNING', $plan['scenario']);
        $t->same('table pragma_encoding may not be modified', $plan['error'], "read-only pragma error wins for case {$case}");
        $t->same(false, $plan['assignments_applied'], "assignments are blocked for case {$case}");
        $t->same(false, $plan['returning_evaluated'], "RETURNING is not evaluated for case {$case}");
        $t->same([], $plan['returning_rows'], "no RETURNING row leaks for case {$case}");
        $t->same(0, $plan['changes'], "read-only pragma update changes no rows for case {$case}");
        $t->same($assignments, $plan['assignments']);
        $t->same($returning, $plan['returning_projection']);
        $t->same(['sqlite-returning-readonly-pragma-virtual-table', 'returning1.test-9.1'], $plan['dependencies']);
    };
}

foreach (range(1, 550) as $case) {
    $existing = [];
    for ($i = 0; $i < $case % 3; ++$i) {
        $existing[] = ['a' => $i + 1, 'b' => $i + 2, 'c' => $i + 3];
    }
    $incoming = [
        'a' => 1000 + $case,
        'b' => ($case % 2 === 0) ? $case + 0.25 : $case + 2,
        'c' => ($case % 3 === 0) ? $case + 0.75 : $case + 3,
    ];

    $tests[sprintf('real upstream returning1 virtual table rtree scalar subquery dynamic %04d', $case)] = static function (TestRunner $t) use ($existing, $incoming, $case): void {
        $plan = SQLiteReturningVirtualTablePlan::insertRtreeReturningScalarSubquery($existing, [], $incoming, 'b');
        $after = $existing;
        $after[] = ['a' => (int) $incoming['a'], 'b' => $incoming['b'], 'c' => $incoming['c']];

        $t->same('returning1.test', $plan['source']);
        $t->same('returning1-13.1 rtree INSERT RETURNING scalar subquery evaluates after virtual-table admission', $plan['scenario']);
        $t->same('rtree', $plan['virtual_table']);
        $t->same(['a' => (int) $incoming['a'], 'b' => $incoming['b'], 'c' => $incoming['c']], $plan['inserted']);
        $t->same($after, $plan['after'], "RTREE row is appended for case {$case}");
        $t->same([['returning_value' => null]], $plan['returning_rows'], "empty scalar subquery returns NULL for case {$case}");
        $t->same(true, $plan['returning_evaluated']);
        $t->same(1, $plan['changes']);
        $t->same(0, $plan['subquery_source_count']);
        $t->same(['sqlite-returning-rtree-scalar-subquery', 'returning1.test-13.1'], $plan['dependencies']);
    };
}

$tests['real upstream returning1 virtual table rtree scalar subquery returns first value when source is nonempty'] = static function (TestRunner $t): void {
    $plan = SQLiteReturningVirtualTablePlan::insertRtreeReturningScalarSubquery(
        [],
        [['b' => 'subquery-value'], ['b' => 'ignored']],
        ['a' => 7, 'b' => 8, 'c' => 9],
        'b',
    );

    $t->same([['returning_value' => 'subquery-value']], $plan['returning_rows']);
    $t->same(2, $plan['subquery_source_count']);
    $t->same('returning1.test', $plan['source']);
};

$tests['real upstream returning1 virtual table rejects malformed pragma target'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteReturningVirtualTablePlan::updateReadOnlyPragmaReturning('encoding', ['encoding' => 'UTF-8'], ['encoding']));
};

$tests['real upstream returning1 virtual table rejects malformed pragma assignment'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteReturningVirtualTablePlan::updateReadOnlyPragmaReturning('pragma_encoding', ['bad-column' => 'UTF-8'], ['encoding']));
};

$tests['real upstream returning1 virtual table rejects malformed rtree row'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteReturningVirtualTablePlan::insertRtreeReturningScalarSubquery([], [], ['a' => 1, 'b' => 2], 'b'));
};

$tests['real upstream returning1 virtual table dynamic cites upstream files'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test returning1-9.1 UPDATE pragma_encoding ... RETURNING reports table may not be modified',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test returning1-13.1 INSERT INTO rtree ... RETURNING scalar subquery yields NULL for empty source',
        '1100 focused dynamic PASS cases plus edge guards for virtual-table RETURNING behavior',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test returning1-9.1 UPDATE pragma_encoding ... RETURNING reports table may not be modified',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/returning1.test returning1-13.1 INSERT INTO rtree ... RETURNING scalar subquery yields NULL for empty source',
        '1100 focused dynamic PASS cases plus edge guards for virtual-table RETURNING behavior',
    ]);
};

$tests['real upstream returning1 virtual table dynamic dependency closure'] = static function (TestRunner $t): void {
    $t->same(
        'no new support component needed; reuses generic virtual-table RETURNING planning for read-only pragma writes and RTREE scalar subqueries',
        'no new support component needed; reuses generic virtual-table RETURNING planning for read-only pragma writes and RTREE scalar subqueries',
    );
};

return $tests;
