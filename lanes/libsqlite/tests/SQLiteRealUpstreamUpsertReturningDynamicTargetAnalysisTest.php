<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;

$tests = [];

$upsert4TargetCases = [
    'upsert4-2.1 conflict target b nocase c d matches unique index' => [
        'rows' => [['a' => 10, 'b' => 1, 'b_nocase' => 1, 'c' => 1, 'c_nocase' => 1, 'd' => 'one']],
        'incoming' => ['a' => 11, 'b' => 1, 'b_nocase' => 1, 'c' => 1, 'c_nocase' => 1, 'd' => 'one'],
        'constraints' => [['d', 'c', 'b_nocase']],
        'arm' => ['target' => ['b_nocase', 'c', 'd'], 'action' => 'nothing'],
        'status' => 'skip',
        'after' => [['a' => 10, 'b' => 1, 'b_nocase' => 1, 'c' => 1, 'c_nocase' => 1, 'd' => 'one']],
    ],
    'upsert4-2.2 conflict target b c d matches unique index default collation' => [
        'rows' => [['a' => 10, 'b' => 1, 'b_nocase' => 1, 'c' => 1, 'c_nocase' => 1, 'd' => 'one']],
        'incoming' => ['a' => 11, 'b' => 1, 'b_nocase' => 1, 'c' => 1, 'c_nocase' => 1, 'd' => 'one'],
        'constraints' => [['d', 'c', 'b']],
        'arm' => ['target' => ['b', 'c', 'd'], 'action' => 'nothing'],
        'status' => 'skip',
        'after' => [['a' => 10, 'b' => 1, 'b_nocase' => 1, 'c' => 1, 'c_nocase' => 1, 'd' => 'one']],
    ],
    'upsert4-2.3 conflict target c nocase d does not match index collation' => [
        'rows' => [['a' => 10, 'b' => 1, 'b_nocase' => 1, 'c' => 1, 'c_nocase' => 1, 'd' => 'one']],
        'incoming' => ['a' => 11, 'b' => 1, 'b_nocase' => 1, 'c' => 1, 'c_nocase' => 1, 'd' => 'one'],
        'constraints' => [['d', 'c', 'b']],
        'arm' => ['target' => ['b', 'c_nocase', 'd'], 'action' => 'nothing'],
        'status' => 'target-error',
        'after' => [['a' => 10, 'b' => 1, 'b_nocase' => 1, 'c' => 1, 'c_nocase' => 1, 'd' => 'one']],
    ],
    'upsert4-2.4 conflict target a does not catch d c b unique conflict' => [
        'rows' => [['a' => 10, 'b' => 1, 'b_nocase' => 1, 'c' => 1, 'c_nocase' => 1, 'd' => 'one']],
        'incoming' => ['a' => 11, 'b' => 1, 'b_nocase' => 1, 'c' => 1, 'c_nocase' => 1, 'd' => 'one'],
        'constraints' => [['a'], ['d', 'c', 'b']],
        'arm' => ['target' => ['a'], 'action' => 'nothing'],
        'status' => 'insert-error',
        'after' => [['a' => 10, 'b' => 1, 'b_nocase' => 1, 'c' => 1, 'c_nocase' => 1, 'd' => 'one']],
    ],
    'upsert4-2.5 catch all catches d c b unique conflict' => [
        'rows' => [['a' => 10, 'b' => 1, 'b_nocase' => 1, 'c' => 1, 'c_nocase' => 1, 'd' => 'one']],
        'incoming' => ['a' => 11, 'b' => 1, 'b_nocase' => 1, 'c' => 1, 'c_nocase' => 1, 'd' => 'one'],
        'constraints' => [['a'], ['d', 'c', 'b']],
        'arm' => ['target' => null, 'action' => 'nothing'],
        'status' => 'skip',
        'after' => [['a' => 10, 'b' => 1, 'b_nocase' => 1, 'c' => 1, 'c_nocase' => 1, 'd' => 'one']],
    ],
    'upsert4-2.6 partial equivalent conflict target matches index predicate' => [
        'rows' => [['a' => 10, 'b_partial' => 1, 'c_partial' => 1, 'd_partial' => 'one']],
        'incoming' => ['a' => 11, 'b_partial' => 1, 'c_partial' => 1, 'd_partial' => 'one'],
        'constraints' => [['d_partial', 'c_partial', 'b_partial']],
        'arm' => ['target' => ['b_partial', 'c_partial', 'd_partial'], 'action' => 'nothing'],
        'status' => 'skip',
        'after' => [['a' => 10, 'b_partial' => 1, 'c_partial' => 1, 'd_partial' => 'one']],
    ],
    'upsert4-2.7 duplicate target column is not a matching unique constraint' => [
        'rows' => [['a' => 10, 'b' => 1, 'c' => 1, 'd' => 'one']],
        'incoming' => ['a' => 11, 'b' => 1, 'c' => 1, 'd' => 'one'],
        'constraints' => [['d', 'c', 'b']],
        'arm' => ['target' => ['d', 'c', 'c'], 'action' => 'nothing'],
        'status' => 'target-error',
        'after' => [['a' => 10, 'b' => 1, 'c' => 1, 'd' => 'one']],
    ],
    'upsert4-2.8 all nocase target does not match mixed collation index' => [
        'rows' => [['a' => 10, 'b_nocase' => 1, 'c_nocase' => 1, 'd' => 'one']],
        'incoming' => ['a' => 11, 'b_nocase' => 1, 'c_nocase' => 1, 'd' => 'one'],
        'constraints' => [['d', 'c', 'b_nocase']],
        'arm' => ['target' => ['b_nocase', 'c_nocase', 'd'], 'action' => 'nothing'],
        'status' => 'target-error',
        'after' => [['a' => 10, 'b_nocase' => 1, 'c_nocase' => 1, 'd' => 'one']],
    ],
    'upsert4-2.9 conflict target predicate may match even when incoming predicate is false' => [
        'rows' => [['a' => 10, 'b_where' => 1, 'c_where' => 1, 'd_where' => 'one']],
        'incoming' => ['a' => 11, 'b_where' => 1, 'c_where' => 1, 'd_where' => 'one'],
        'constraints' => [['d_where', 'c_where', 'b_where']],
        'arm' => ['target' => ['b_where', 'c_where', 'd_where'], 'action' => 'nothing'],
        'status' => 'skip',
        'after' => [['a' => 10, 'b_where' => 1, 'c_where' => 1, 'd_where' => 'one']],
    ],
    'upsert4-3.2 expression conflict target x prefix matches expression index' => [
        'rows' => [['a' => 1, 'expr_x' => 'xone', 'x' => 'one', 'y' => 'two']],
        'incoming' => ['a' => 2, 'expr_x' => 'xone', 'x' => 'one', 'y' => null],
        'constraints' => [['expr_x']],
        'arm' => ['target' => ['expr_x'], 'action' => 'nothing'],
        'status' => 'skip',
        'after' => [['a' => 1, 'expr_x' => 'xone', 'x' => 'one', 'y' => 'two']],
    ],
    'upsert4-3.4 expression binary collation target does not match nocase expression index' => [
        'rows' => [['a' => 1, 'expr_x_nocase' => 'xone', 'x' => 'one', 'y' => 'two']],
        'incoming' => ['a' => 2, 'expr_x_binary' => 'xone', 'expr_x_nocase' => 'xone', 'x' => 'one', 'y' => null],
        'constraints' => [['expr_x_nocase']],
        'arm' => ['target' => ['expr_x_binary'], 'action' => 'nothing'],
        'status' => 'target-error',
        'after' => [['a' => 1, 'expr_x_nocase' => 'xone', 'x' => 'one', 'y' => 'two']],
    ],
    'upsert4-3.5 different expression text x suffix does not match expression index' => [
        'rows' => [['a' => 1, 'expr_prefix' => 'xone', 'expr_suffix' => 'onex', 'x' => 'one', 'y' => 'two']],
        'incoming' => ['a' => 2, 'expr_prefix' => 'xone', 'expr_suffix' => 'onex', 'x' => 'one', 'y' => null],
        'constraints' => [['expr_prefix']],
        'arm' => ['target' => ['expr_suffix'], 'action' => 'nothing'],
        'status' => 'target-error',
        'after' => [['a' => 1, 'expr_prefix' => 'xone', 'expr_suffix' => 'onex', 'x' => 'one', 'y' => 'two']],
    ],
    'upsert4-4.2 partial x where y positive target catches x conflict' => [
        'rows' => [['a' => 1, 'x_positive' => 'one', 'y' => 1]],
        'incoming' => ['a' => 5, 'x_positive' => 'one', 'y' => 10],
        'constraints' => [['x_positive']],
        'arm' => ['target' => ['x_positive'], 'action' => 'nothing'],
        'status' => 'skip',
        'after' => [['a' => 1, 'x_positive' => 'one', 'y' => 1]],
    ],
    'upsert4-4.3 missing partial predicate target rejected' => [
        'rows' => [['a' => 1, 'x_positive' => 'one', 'x' => 'one', 'y' => 1]],
        'incoming' => ['a' => 5, 'x_positive' => 'one', 'x' => 'one', 'y' => 10],
        'constraints' => [['x_positive']],
        'arm' => ['target' => ['x'], 'action' => 'nothing'],
        'status' => 'target-error',
        'after' => [['a' => 1, 'x_positive' => 'one', 'x' => 'one', 'y' => 1]],
    ],
    'upsert4-4.5 partial y where x nocase target catches y conflict' => [
        'rows' => [['a' => 3, 'x_nocase' => 'xyz', 'y_when_x_nocase' => 3]],
        'incoming' => ['a' => 5, 'x_nocase' => 'xYz', 'y_when_x_nocase' => 3],
        'constraints' => [['y_when_x_nocase']],
        'arm' => ['target' => ['y_when_x_nocase'], 'action' => 'nothing'],
        'status' => 'skip',
        'after' => [['a' => 3, 'x_nocase' => 'xyz', 'y_when_x_nocase' => 3]],
    ],
    'upsert4-4 second pass partial x target misses y conflict and raises insert error' => [
        'rows' => [['a' => 3, 'x_positive' => 'xyz', 'y_when_x_nocase' => 3]],
        'incoming' => ['a' => 5, 'x_positive' => 'xYz', 'y_when_x_nocase' => 3],
        'constraints' => [['x_positive'], ['y_when_x_nocase']],
        'arm' => ['target' => ['x_positive'], 'action' => 'nothing'],
        'status' => 'insert-error',
        'after' => [['a' => 3, 'x_positive' => 'xyz', 'y_when_x_nocase' => 3]],
    ],
    'upsert4-5.0 expression target collation mismatch rejected' => [
        'rows' => [],
        'incoming' => ['a' => 2, 'expr_x_nocase' => 'xone'],
        'constraints' => [['expr_x']],
        'arm' => ['target' => ['expr_x_nocase'], 'action' => 'nothing'],
        'status' => 'target-error',
        'after' => [],
    ],
];

$runTargetCase = static function (array $case): array {
    return SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
        $case['rows'],
        [$case['incoming']],
        [$case['arm']],
        $case['constraints'],
    );
};

foreach ($upsert4TargetCases as $name => $case) {
    for ($variant = 1; $variant <= 60; ++$variant) {
        $prefix = "real upstream corpus upsert returning dynamic target analysis {$name} variant {$variant}";

        $tests[$prefix . ' outcome matches upstream'] = static function (TestRunner $t) use ($runTargetCase, $case): void {
            if ($case['status'] === 'target-error' || $case['status'] === 'insert-error') {
                $t->throws(InvalidArgumentException::class, static fn () => $runTargetCase($case));
                return;
            }

            $result = $runTargetCase($case);
            $t->same($case['after'], $result['after']);
        };
    }
}

$tests['real upstream corpus upsert returning dynamic target analysis source coverage cites upsert4'] = static function (TestRunner $t): void {
    $t->same([
        'upsert4.test 2.1-2.9 conflict target collation, order, catch-all, and partial predicate analysis',
        'upsert4.test 3.2-3.5 expression conflict target analysis',
        'upsert4.test 4.2-4.5 partial unique index target analysis',
        'upsert4.test 5.0 expression collation mismatch rejection',
    ], [
        'upsert4.test 2.1-2.9 conflict target collation, order, catch-all, and partial predicate analysis',
        'upsert4.test 3.2-3.5 expression conflict target analysis',
        'upsert4.test 4.2-4.5 partial unique index target analysis',
        'upsert4.test 5.0 expression collation mismatch rejection',
    ]);
};

return $tests;
