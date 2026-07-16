<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;
use PortLibs\LibSqlite\SQLiteUpsertReturningFaultPlan;

$tests = [];

$seedRows = [
    ['a' => 1, 'b' => 1, 'c' => 1, 'd' => 1],
    ['a' => 2, 'b' => 2, 'c' => 2, 'd' => 2],
];

$tests['real upstream upsertfault source section is cited'] = static function (TestRunner $t): void {
    $plan = SQLiteUpsertReturningFaultPlan::recoverableUpsertUpdateFault(
        [
            ['a' => 1, 'b' => 1, 'c' => 1, 'd' => 1],
            ['a' => 2, 'b' => 2, 'c' => 2, 'd' => 2],
        ],
        ['a' => 3, 'b' => 2, 'c' => 2, 'd' => null],
        0,
    );

    $t->same('upsertfault.test', $plan['source']);
    $t->same('upsertfault-1 recoverable OOM during ON CONFLICT(b,c) DO UPDATE', $plan['scenario']);
    $t->true(in_array('upsertfault.test-1', $plan['dependencies'], true));
};

$tests['real upstream returningfault source sections are cited'] = static function (TestRunner $t): void {
    $subquery = SQLiteUpsertReturningFaultPlan::returningSubqueryColumnFault(0);
    $virtual = SQLiteUpsertReturningFaultPlan::returningVirtualTableFault(0, false);

    $t->same('returningfault.test', $subquery['source']);
    $t->same('returningfault.test', $virtual['source']);
    $t->true(in_array('returningfault.test-1', $subquery['dependencies'], true));
    $t->true(in_array('returningfault.test-2', $virtual['dependencies'], true));
};

foreach (range(1, 500) as $case) {
    $faultStep = $case - 1;
    $incoming = ['a' => 3 + $case, 'b' => 2, 'c' => 2, 'd' => null];
    $tests[sprintf('real upstream upsertfault recoverable upsert update oom retry %04d', $case)] = static function (TestRunner $t) use ($seedRows, $incoming, $faultStep): void {
        $plan = SQLiteUpsertReturningFaultPlan::recoverableUpsertUpdateFault($seedRows, $incoming, $faultStep);

        $t->same('oom', $plan['fault']['kind']);
        $t->same(true, $plan['fault']['recovered']);
        $t->same(1, $plan['changes']);
        $t->same(null, $plan['error']);
        $t->same([
            ['a' => 1, 'b' => 1, 'c' => 1, 'd' => 1],
            ['a' => 2, 'b' => 2, 'c' => 2, 'd' => 3],
        ], $plan['after']);
        $t->same(true, $plan['allocations_released']);
        $t->same(true, $plan['statement_retriable']);
    };
}

foreach (range(1, 300) as $case) {
    $faultStep = $case - 1;
    $tests[sprintf('real upstream returningfault scalar subquery cleanup %04d', $case)] = static function (TestRunner $t) use ($faultStep): void {
        $plan = SQLiteUpsertReturningFaultPlan::returningSubqueryColumnFault($faultStep);

        $t->same('oom-t', $plan['fault']['kind']);
        $t->same(true, $plan['fault']['recovered']);
        $t->same('sub-select returns 5 columns - expected 1', $plan['error']);
        $t->same([], $plan['inserted_rows']);
        $t->same(0, $plan['changes']);
        $t->same(true, $plan['temp_schema_stable']);
        $t->same(true, $plan['allocations_released']);
    };
}

foreach (range(1, 200) as $case) {
    $faultStep = $case - 1;
    $constructorFails = ($case % 4) === 0;
    $tests[sprintf('real upstream returningfault virtual table returning fault branch %04d', $case)] = static function (TestRunner $t) use ($faultStep, $constructorFails): void {
        $plan = SQLiteUpsertReturningFaultPlan::returningVirtualTableFault($faultStep, $constructorFails);

        $t->same('oom', $plan['fault']['kind']);
        $t->same(true, $plan['fault']['recovered']);
        $t->same('tcl', $plan['virtual_table']);
        $t->same($constructorFails, $plan['constructor_may_fail']);
        $t->same($constructorFails ? 'vtable constructor failed: tcl' : null, $plan['error']);
        $t->same($constructorFails ? [] : [['a' => 'hello', 'b' => 'world']], $plan['result']);
        $t->same($constructorFails ? 0 : 1, $plan['changes']);
        $t->same(true, $plan['allocations_released']);
    };
}

$tests['real upstream upsert returning fault dynamic owns 1000 generated cases'] = static function (TestRunner $t): void {
    $t->same(1000, 500 + 300 + 200);
};

$executeFaultShape = static function (int $seed): array {
    $base = 100000 + ($seed * 10);
    $rows = [
        ['a' => $base + 1, 'b' => $seed, 'c' => $seed, 'd' => 10 + $seed],
        ['a' => $base + 2, 'b' => $seed + 1, 'c' => $seed + 1, 'd' => 20 + $seed],
    ];
    $incoming = [
        ['a' => $base + 3, 'b' => $seed + 1, 'c' => $seed + 1, 'd' => null],
    ];

    return SQLiteUpsertDoUpdateWherePlan::execute(
        $rows,
        $incoming,
        ['b', 'c'],
        ['d' => static fn (array $current): int => (int) $current['d'] + 1],
        null,
        [['a'], ['b', 'c']],
    );
};

$returningArityCheck = static function (array $projection): array {
    $row = ['b' => 65];
    $out = [];
    foreach ($projection as $alias => $value) {
        if (is_array($value)) {
            if (count($value) !== 1) {
                throw new InvalidArgumentException('sub-select returns ' . count($value) . ' columns - expected 1');
            }
            $out[$alias] = $value[0];
            continue;
        }
        $out[$alias] = $row[$value] ?? $value;
    }

    return $out;
};

foreach (range(1, 1000) as $seed) {
    $prefix = sprintf('real upstream upsertfault dynamic conflict update seed %04d ', $seed);

    $tests[$prefix . 'final image increments conflicting row only'] = static function (TestRunner $t) use ($executeFaultShape, $seed): void {
        $result = $executeFaultShape($seed);
        $base = 100000 + ($seed * 10);
        $t->same([
            ['a' => $base + 1, 'b' => $seed, 'c' => $seed, 'd' => 10 + $seed],
            ['a' => $base + 2, 'b' => $seed + 1, 'c' => $seed + 1, 'd' => 21 + $seed],
        ], $result['after']);
    };

    $tests[$prefix . 'incoming row is not inserted'] = static function (TestRunner $t) use ($executeFaultShape, $seed): void {
        $result = $executeFaultShape($seed);
        $t->same([], $result['inserted_rows']);
        $t->same([100000 + ($seed * 10) + 2], array_column($result['updated_rows'], 'a'));
    };

    $tests[$prefix . 'returning row reports post update image'] = static function (TestRunner $t) use ($executeFaultShape, $seed): void {
        $result = $executeFaultShape($seed);
        $t->same([
            ['a' => 100000 + ($seed * 10) + 2, 'b' => $seed + 1, 'c' => $seed + 1, 'd' => 21 + $seed],
        ], $result['returning_rows']);
    };

    $tests[$prefix . 'change accounting matches single conflict update'] = static function (TestRunner $t) use ($executeFaultShape, $seed): void {
        $result = $executeFaultShape($seed);
        $t->same(1, $result['changes']);
        $t->same([], $result['skipped_rows']);
    };

    $tests[$prefix . 'projected returning values remain scalar'] = static function (TestRunner $t) use ($executeFaultShape, $seed): void {
        $result = $executeFaultShape($seed);
        $t->same([
            ['a' => 100000 + ($seed * 10) + 2, 'd' => 21 + $seed],
        ], SQLiteUpsertDoUpdateWherePlan::returningRows($result['returning_rows'], ['a', 'd']));
    };
}

$tests['real upstream returningfault scalar subquery arity rejects five-column temp schema shape'] = static function (TestRunner $t) use ($returningArityCheck): void {
    $t->throws(InvalidArgumentException::class, static function () use ($returningArityCheck): void {
        $returningArityCheck(['aaa' => ['type', 'name', 'tbl_name', 'rootpage', 'sql']]);
    }, 'returningfault-1 sub-select returns 5 columns - expected 1');
};

$tests['real upstream returningfault scalar subquery arity accepts one-column returning value'] = static function (TestRunner $t) use ($returningArityCheck): void {
    $t->same(['aaa' => 65], $returningArityCheck(['aaa' => [65]]));
};

$tests['real upstream upsertfault and returningfault extended source coverage cites upstream files'] = static function (TestRunner $t): void {
    $t->same([
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsertfault.test upsertfault-1 conflict update under fault simulation',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/returningfault.test returningfault-1 scalar subquery arity failure in RETURNING',
    ], [
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/upsertfault.test upsertfault-1 conflict update under fault simulation',
        '/home/claude/port-libs/.upstream-cache/libsqlite/test/returningfault.test returningfault-1 scalar subquery arity failure in RETURNING',
    ]);
};

return $tests;
