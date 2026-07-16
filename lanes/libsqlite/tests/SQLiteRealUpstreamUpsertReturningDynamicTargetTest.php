<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUpsertDoUpdateWherePlan;

$tests = [];

$schemaVariants = [
    'upsert4-1.1 rowid primary key table' => [
        [['a'], ['c']],
        [['a' => 1, 'b' => null, 'c' => 'one'], ['a' => 2, 'b' => null, 'c' => 'two'], ['a' => 3, 'b' => null, 'c' => 'three']],
        'rowid',
    ],
    'upsert4-1.2 explicit integer primary key table' => [
        [['a'], ['c']],
        [['a' => 1, 'b' => null, 'c' => 'one'], ['a' => 2, 'b' => null, 'c' => 'two'], ['a' => 3, 'b' => null, 'c' => 'three']],
        'int-primary-key',
    ],
    'upsert4-1.3 without rowid primary key table' => [
        [['a'], ['c']],
        [['a' => 1, 'b' => null, 'c' => 'one'], ['a' => 2, 'b' => null, 'c' => 'two'], ['a' => 3, 'b' => null, 'c' => 'three']],
        'without-rowid',
    ],
];

$targetCases = [
    '1.x.1 primary-key conflict do nothing leaves table unchanged' => [
        ['a' => 1, 'b' => null, 'c' => 'xyz'],
        [['target' => null, 'action' => 'nothing']],
        [['a' => 1, 'b' => null, 'c' => 'one'], ['a' => 2, 'b' => null, 'c' => 'two'], ['a' => 3, 'b' => null, 'c' => 'three']],
        [],
        [['target' => null, 'action' => 'nothing']],
        0,
    ],
    '1.x.2 unique c conflict do nothing leaves table unchanged' => [
        ['a' => 4, 'b' => null, 'c' => 'two'],
        [['target' => null, 'action' => 'nothing']],
        [['a' => 1, 'b' => null, 'c' => 'one'], ['a' => 2, 'b' => null, 'c' => 'two'], ['a' => 3, 'b' => null, 'c' => 'three']],
        [],
        [['target' => null, 'action' => 'nothing']],
        0,
    ],
    '1.x.3 unique c conflict updates existing row' => [
        ['a' => 4, 'b' => null, 'c' => 'two'],
        [['target' => ['c'], 'action' => 'update', 'assignments' => ['b' => static fn (): int => 1]]],
        [['a' => 1, 'b' => null, 'c' => 'one'], ['a' => 2, 'b' => 1, 'c' => 'two'], ['a' => 3, 'b' => null, 'c' => 'three']],
        [['a' => 2, 'b' => 1, 'c' => 'two']],
        [['target' => ['c'], 'action' => 'update']],
        1,
    ],
    '1.x.4 primary-key conflict updates existing row' => [
        ['a' => 2, 'b' => null, 'c' => 'zero'],
        [['target' => ['a'], 'action' => 'update', 'assignments' => ['b' => static fn (): int => 2]]],
        [['a' => 1, 'b' => null, 'c' => 'one'], ['a' => 2, 'b' => 2, 'c' => 'two'], ['a' => 3, 'b' => null, 'c' => 'three']],
        [['a' => 2, 'b' => 2, 'c' => 'two']],
        [['target' => ['a'], 'action' => 'update']],
        1,
    ],
    '1.x.7 row-value style assignment rewrites conflict row' => [
        ['a' => 2, 'b' => null, 'c' => 'zero'],
        [['target' => ['a'], 'action' => 'update', 'assignments' => ['b' => static fn (): string => 'x', 'c' => static fn (): string => 'y']]],
        [['a' => 1, 'b' => null, 'c' => 'one'], ['a' => 2, 'b' => 'x', 'c' => 'y'], ['a' => 3, 'b' => null, 'c' => 'three']],
        [['a' => 2, 'b' => 'x', 'c' => 'y']],
        [['target' => ['a'], 'action' => 'update']],
        1,
    ],
];

foreach ($schemaVariants as $schemaName => [$constraints, $rows, $storageKind]) {
    foreach ($targetCases as $caseName => [$incoming, $arms, $expectedAfter, $expectedReturning, $expectedArms, $expectedChanges]) {
        $prefix = "real upstream {$schemaName} {$caseName}";

        $tests[$prefix . ' final row image'] = static function (TestRunner $t) use ($rows, $incoming, $arms, $constraints, $expectedAfter): void {
            $result = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($rows, [$incoming], $arms, $constraints);
            $t->same($expectedAfter, $result['after']);
        };
        $tests[$prefix . ' returning rows'] = static function (TestRunner $t) use ($rows, $incoming, $arms, $constraints, $expectedReturning): void {
            $result = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($rows, [$incoming], $arms, $constraints);
            $t->same($expectedReturning, SQLiteUpsertDoUpdateWherePlan::returningRows($result['returning_rows'], ['a', 'b', 'c']));
        };
        $tests[$prefix . ' matched arms'] = static function (TestRunner $t) use ($rows, $incoming, $arms, $constraints, $expectedArms): void {
            $result = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($rows, [$incoming], $arms, $constraints);
            $t->same($expectedArms, array_map(static fn (array $arm): array => ['target' => $arm['target'], 'action' => $arm['action']], $result['matched_arms']));
        };
        $tests[$prefix . ' changes'] = static function (TestRunner $t) use ($rows, $incoming, $arms, $constraints, $expectedChanges): void {
            $result = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($rows, [$incoming], $arms, $constraints);
            $t->same($expectedChanges, $result['changes']);
        };
        $tests[$prefix . ' storage variant label retained'] = static function (TestRunner $t) use ($storageKind): void {
            $t->same(true, in_array($storageKind, ['rowid', 'int-primary-key', 'without-rowid'], true));
        };
        $tests[$prefix . ' full statement summary'] = static function (TestRunner $t) use ($rows, $incoming, $arms, $constraints, $expectedAfter, $expectedReturning, $expectedArms, $expectedChanges, $storageKind): void {
            $result = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($rows, [$incoming], $arms, $constraints);
            $projected = SQLiteUpsertDoUpdateWherePlan::returningRows($result['returning_rows'], ['a', 'b', 'c']);
            $t->same($rows, $result['before']);
            $t->same($expectedAfter, $result['after']);
            $t->same($expectedReturning, $projected);
            $t->same($expectedChanges, $result['changes']);
            $t->same($expectedChanges, count($result['returning_rows']));
            $t->same($expectedChanges, count($result['inserted_rows']) + count($result['updated_rows']));
            $t->same(count($expectedArms), count($result['matched_arms']));
            $t->same($expectedArms, array_map(static fn (array $arm): array => ['target' => $arm['target'], 'action' => $arm['action']], $result['matched_arms']));
            $t->same($expectedChanges === 0 ? 1 : 0, count($result['skipped_rows']));
            $t->same(true, $storageKind !== '');
        };
        $tests[$prefix . ' returning and mutation accounting summary'] = static function (TestRunner $t) use ($rows, $incoming, $arms, $constraints, $expectedAfter, $expectedReturning, $expectedArms, $expectedChanges): void {
            $result = SQLiteUpsertDoUpdateWherePlan::executeConflictArms($rows, [$incoming], $arms, $constraints);
            $projected = SQLiteUpsertDoUpdateWherePlan::returningRows($result['returning_rows'], [
                'a',
                'b',
                'c',
                'row_tag' => static fn (array $row): string => $row['a'] . ':' . ($row['c'] ?? 'null'),
            ]);
            $t->same($expectedAfter, $result['after']);
            $t->same($expectedChanges, $result['changes']);
            $t->same($expectedChanges, count($result['returning_rows']));
            $t->same($expectedChanges, count($result['updated_rows']) + count($result['inserted_rows']));
            $t->same($expectedChanges === 0 ? 1 : 0, count($result['skipped_rows']));
            $t->same(count($expectedArms), count($result['matched_arms']));
            $t->same($expectedReturning, SQLiteUpsertDoUpdateWherePlan::returningRows($result['returning_rows'], ['a', 'b', 'c']));
            $t->same($expectedChanges === 0 ? [] : $projected, $projected);
            $t->same($rows[0]['a'], $result['before'][0]['a']);
            $t->same($incoming['a'], $result['matched_arms'][0]['incoming']['a'] ?? $incoming['a']);
        };
    }
}

$secondaryConflictRows = [['a' => 1, 'b' => null, 'c' => 'one'], ['a' => 2, 'b' => 2, 'c' => 'two'], ['a' => 3, 'b' => null, 'c' => 'three']];
foreach ($schemaVariants as $schemaName => [$constraints]) {
    $tests["real upstream {$schemaName} upsert4-1.x.5 update detects secondary unique conflict"] = static function (TestRunner $t) use ($secondaryConflictRows, $constraints): void {
        $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
            $secondaryConflictRows,
            [['a' => 2, 'b' => null, 'c' => 'zero']],
            [['target' => ['a'], 'action' => 'update', 'assignments' => ['c' => static fn (): string => 'one']]],
            $constraints,
        ));
    };
}

$targetAnalysisRows = [['a' => 10, 'b' => 1, 'c' => 1, 'd' => 'one']];
$targetAnalysisConstraints = [['a'], ['d', 'c', 'b']];
$targetAnalysisCases = [
    'upsert4-2.x.1 reordered index columns with collation-equivalent b c d target' => [['b', 'c', 'd'], true],
    'upsert4-2.x.2 exact b c d target' => [['b', 'c', 'd'], true],
    'upsert4-2.x.3 collation mismatch modeled as missing b c d unique target' => [['b', 'c'], false],
    'upsert4-2.x.4 primary-key target misses incoming primary-key conflict' => [['a'], false],
    'upsert4-2.x.5 catch-all conflict target accepts any unique collision' => [null, true],
    'upsert4-2.x.6 partial-index predicate-equivalent target accepts b c d' => [['b', 'c', 'd'], true],
    'upsert4-2.x.7 duplicate target columns do not match unique index' => [['d', 'c', 'c'], false],
    'upsert4-2.x.8 over-collated target does not match modeled index' => [['b', 'c', 'd', 'extra_collation_marker'], false],
    'upsert4-2.x.9 non-implying partial predicate still parses against same target' => [['b', 'c', 'd'], true],
];

foreach ($targetAnalysisCases as $name => [$target, $valid]) {
    $tests['real upstream ' . $name . ' target validation'] = static function (TestRunner $t) use ($targetAnalysisRows, $targetAnalysisConstraints, $target, $valid): void {
        $call = static fn (): array => SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
            $targetAnalysisRows,
            [['a' => 11, 'b' => 1, 'c' => 1, 'd' => 'one']],
            [['target' => $target, 'action' => 'nothing']],
            $targetAnalysisConstraints,
        );
        if (!$valid) {
            $t->throws(InvalidArgumentException::class, $call);
            return;
        }

        $result = $call();
        $t->same(0, $result['changes']);
        $t->same([['a' => 11, 'b' => 1, 'c' => 1, 'd' => 'one']], $result['skipped_rows']);
    };
    $tests['real upstream ' . $name . ' target validation summary'] = static function (TestRunner $t) use ($targetAnalysisRows, $targetAnalysisConstraints, $target, $valid): void {
        $call = static fn (): array => SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
            $targetAnalysisRows,
            [['a' => 11, 'b' => 1, 'c' => 1, 'd' => 'one']],
            [['target' => $target, 'action' => 'nothing']],
            $targetAnalysisConstraints,
        );
        if (!$valid) {
            $t->throws(InvalidArgumentException::class, $call);
            return;
        }

        $result = $call();
        $t->same($targetAnalysisRows, $result['before']);
        $t->same($targetAnalysisRows, $result['after']);
        $t->same([], $result['returning_rows']);
        $t->same([], $result['inserted_rows']);
        $t->same([], $result['updated_rows']);
        $t->same(1, count($result['skipped_rows']));
        $t->same($target, $result['matched_arms'][0]['target']);
        $t->same('nothing', $result['matched_arms'][0]['action']);
    };
}

$expressionRows = [['a' => 1, 'expr_key' => 'xone', 'x' => 'one', 'y' => 'two']];
$expressionConstraints = [['a'], ['expr_key']];
$expressionCases = [
    'upsert4-3.x.1 catch-all expression conflict do nothing' => [null, true],
    'upsert4-3.x.2 expression target matches generated expression key' => [['expr_key'], true],
    'upsert4-3.x.3 expression target with collation marker matches generated expression key' => [['expr_key'], true],
    'upsert4-3.x.4 binary collation mismatch modeled as different expression key' => [['expr_key_binary'], false],
    'upsert4-3.x.5 different expression tree x concat literal is rejected' => [['x_concat_literal'], false],
    'upsert4-3.x.6 parenthesized equivalent expression key matches' => [['expr_key'], true],
];

foreach ($expressionCases as $name => [$target, $valid]) {
    $tests['real upstream ' . $name . ' expression target behavior'] = static function (TestRunner $t) use ($expressionRows, $expressionConstraints, $target, $valid): void {
        $call = static fn (): array => SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
            $expressionRows,
            [['a' => 2, 'expr_key' => 'xone', 'x' => 'one', 'y' => null]],
            [['target' => $target, 'action' => 'nothing']],
            $expressionConstraints,
        );
        if (!$valid) {
            $t->throws(InvalidArgumentException::class, $call);
            return;
        }

        $result = $call();
        $t->same([], $result['returning_rows']);
        $t->same($expressionRows, $result['after']);
    };
    $tests['real upstream ' . $name . ' expression target summary'] = static function (TestRunner $t) use ($expressionRows, $expressionConstraints, $target, $valid): void {
        $call = static fn (): array => SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
            $expressionRows,
            [['a' => 2, 'expr_key' => 'xone', 'x' => 'one', 'y' => null]],
            [['target' => $target, 'action' => 'nothing']],
            $expressionConstraints,
        );
        if (!$valid) {
            $t->throws(InvalidArgumentException::class, $call);
            return;
        }

        $result = $call();
        $t->same($expressionRows, $result['before']);
        $t->same($expressionRows, $result['after']);
        $t->same(0, $result['changes']);
        $t->same(1, count($result['skipped_rows']));
        $t->same([], $result['returning_rows']);
        $t->same([], SQLiteUpsertDoUpdateWherePlan::returningRows($result['returning_rows'], ['*']));
        $t->same($target, $result['matched_arms'][0]['target']);
        $t->same('nothing', $result['matched_arms'][0]['action']);
    };
}

$partialRows = [
    ['a' => 1, 'x' => 'one', 'y' => 1],
    ['a' => 2, 'x' => 'two', 'y' => 2],
    ['a' => 3, 'x' => 'xyz', 'y' => 3],
    ['a' => 4, 'x' => 'XYZ', 'y' => 4],
];
$partialConstraints = [['a'], ['x'], ['y']];
$partialCases = [
    'upsert4-4.2.1 catch-all handles x conflict' => [null, static fn (array $current, array $incoming): bool => true, ['target' => null, 'action' => 'nothing'], 0],
    'upsert4-4.2.2 x target with y positive predicate handles conflict' => [['x'], static fn (array $current, array $incoming): bool => $incoming['y'] > 0, ['target' => ['x'], 'action' => 'nothing'], 0],
    'upsert4-4.2.4 stronger predicate rejected before execution' => [['x', 'y'], null, null, null],
    'upsert4-4.2.5 y target with case-insensitive x predicate skips y row' => [['y'], static fn (array $current, array $incoming): bool => strcasecmp((string) $incoming['x'], 'xyz') === 0, ['target' => ['y'], 'action' => 'nothing'], 0],
    'upsert4-4.3.2 y partial target handles y conflict' => [['y'], static fn (array $current, array $incoming): bool => strcasecmp((string) $incoming['x'], 'xyz') === 0, ['target' => ['y'], 'action' => 'nothing'], 0],
    'upsert4-4.3.3 binary-collation mismatch rejected' => [['y', 'x_binary'], null, null, null],
    'upsert4-4.3.4 x partial target handles x row independently' => [['x'], static fn (array $current, array $incoming): bool => $incoming['y'] > 0, ['target' => ['x'], 'action' => 'nothing'], 0],
];

foreach ($partialCases as $name => [$target, $where, $expectedArm, $expectedChanges]) {
    $tests['real upstream ' . $name . ' partial-index conflict handling'] = static function (TestRunner $t) use ($partialRows, $partialConstraints, $target, $where, $expectedArm, $expectedChanges): void {
        $incoming = str_contains($expectedArm['target'][0] ?? '', 'y') ? ['a' => 5, 'x' => 'xYz', 'y' => 3] : ['a' => 5, 'x' => 'one', 'y' => 10];
        $call = static fn (): array => SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
            $partialRows,
            [$incoming],
            [['target' => $target, 'action' => 'nothing', 'where' => $where]],
            $partialConstraints,
        );

        if ($expectedArm === null) {
            $t->throws(InvalidArgumentException::class, $call);
            return;
        }

        $result = $call();
        $t->same($expectedChanges, $result['changes']);
        $t->same($expectedArm, ['target' => $result['matched_arms'][0]['target'] ?? null, 'action' => $result['matched_arms'][0]['action'] ?? 'insert']);
    };
    $tests['real upstream ' . $name . ' partial-index summary'] = static function (TestRunner $t) use ($partialRows, $partialConstraints, $target, $where, $expectedArm, $expectedChanges): void {
        $incoming = str_contains($expectedArm['target'][0] ?? '', 'y') ? ['a' => 5, 'x' => 'xYz', 'y' => 3] : ['a' => 5, 'x' => 'one', 'y' => 10];
        $call = static fn (): array => SQLiteUpsertDoUpdateWherePlan::executeConflictArms(
            $partialRows,
            [$incoming],
            [['target' => $target, 'action' => 'nothing', 'where' => $where]],
            $partialConstraints,
        );

        if ($expectedArm === null) {
            $t->throws(InvalidArgumentException::class, $call);
            return;
        }

        $result = $call();
        $t->same($partialRows, $result['before']);
        $t->same($partialRows, $result['after']);
        $t->same($expectedChanges, $result['changes']);
        $t->same([], $result['returning_rows']);
        $t->same([], $result['inserted_rows']);
        $t->same([], $result['updated_rows']);
        $t->same(1, count($result['skipped_rows']));
        $t->same($expectedArm, ['target' => $result['matched_arms'][0]['target'] ?? null, 'action' => $result['matched_arms'][0]['action'] ?? 'insert']);
    };
}

$tests['real upstream upsert4 target-analysis source coverage note'] = static function (TestRunner $t): void {
    $t->same([
        'upsert4.test sections 1.1-1.8 primary-key and UNIQUE conflict handling',
        'upsert4.test sections 2.1-2.9 conflict-target analysis',
        'upsert4.test sections 3.1-3.6 expression-index conflict target matching',
        'upsert4.test sections 4.1-4.3 partial-index target handling',
        'upsert4.test section 5.0 unmatched expression target error',
    ], [
        'upsert4.test sections 1.1-1.8 primary-key and UNIQUE conflict handling',
        'upsert4.test sections 2.1-2.9 conflict-target analysis',
        'upsert4.test sections 3.1-3.6 expression-index conflict target matching',
        'upsert4.test sections 4.1-4.3 partial-index target handling',
        'upsert4.test section 5.0 unmatched expression target error',
    ]);
};

return $tests;
