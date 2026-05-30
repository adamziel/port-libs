<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteAffinityComparison;
use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteRealExpressionAffinityCorpusPlan;
use PortLibs\LibSqlite\SQLiteSelectExpression;
use PortLibs\LibSqlite\SQLiteSelectPredicate;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$literal = static fn (mixed $value): array => ['type' => 'literal', 'value' => $value];
$column = static fn (string $name): array => ['type' => 'column', 'name' => $name];
$unary = static fn (string $operator, array $operand): array => ['type' => 'unary', 'operator' => $operator, 'operand' => $operand];
$binary = static fn (array $left, string $operator, array $right): array => ['type' => 'binary', 'left' => $left, 'operator' => $operator, 'right' => $right];
$cast = static fn (array $operand, string $target): array => ['type' => 'cast', 'operand' => $operand, 'target' => $target];
$func = static fn (string $name, array ...$arguments): array => ['type' => 'function', 'name' => $name, 'arguments' => $arguments];
$typeof = static fn (array $expression): array => $func('typeof', $expression);
$quote = static fn (array $expression): array => $func('quote', $expression);
$eval = static fn (array $expression, array $row = []): mixed => SQLiteSelectExpression::evaluate($row, $expression);

$firstRow = static function (string $sql): array {
    $rows = SQLiteSelectSql::execute($sql, []);
    if (count($rows) !== 1) {
        throw new RuntimeException("Expected one SELECT row for {$sql}");
    }

    return $rows[0];
};

$assertSelect = static function (string $name, string $sql, array $expected) use (&$tests, $firstRow): void {
    $tests[$name] = static function (TestRunner $t) use ($firstRow, $sql, $expected): void {
        $t->same($expected, array_values($firstRow($sql)), $sql);
    };
};

// Source truth: SQLite upstream test/affinity2.test affinity2-100 through
// affinity2-150. These verify insert-time storage classes for the five
// declared affinities before later comparison cases consume the same rows.
$affinityRows = SQLiteRealExpressionAffinityCorpusPlan::applyInsertAffinities([
    ['rowid' => 1, 'xi' => 1, 'xr' => 1, 'xb' => 1, 'xn' => 1, 'xt' => 1],
    ['rowid' => 2, 'xi' => '2', 'xr' => '2', 'xb' => '2', 'xn' => '2', 'xt' => '2'],
    ['rowid' => 3, 'xi' => '03', 'xr' => '03', 'xb' => '03', 'xn' => '03', 'xt' => '03'],
], [
    'xi' => 'INTEGER',
    'xr' => 'REAL',
    'xb' => 'BLOB',
    'xn' => 'NUMERIC',
    'xt' => 'TEXT',
]);

$storageExpectations = [
    'affinity2-110 integer affinity storage' => ['xi', [[1, 'integer'], [2, 'integer'], [3, 'integer']]],
    'affinity2-120 real affinity storage' => ['xr', [[1.0, 'real'], [2.0, 'real'], [3.0, 'real']]],
    'affinity2-130 blob affinity storage' => ['xb', [[1, 'integer'], ['2', 'text'], ['03', 'text']]],
    'affinity2-140 numeric affinity storage' => ['xn', [[1, 'integer'], [2, 'integer'], [3, 'integer']]],
    'affinity2-150 text affinity storage' => ['xt', [['1', 'text'], ['2', 'text'], ['03', 'text']]],
];

foreach ($storageExpectations as $upstream => [$columnName, $expectedRows]) {
    foreach ($expectedRows as $index => [$expectedValue, $expectedType]) {
        $tests["real upstream corpus expression affinity dynamic followup {$upstream} row " . ($index + 1)] = static function (TestRunner $t) use ($affinityRows, $columnName, $index, $expectedValue, $expectedType): void {
            $row = $affinityRows[$index];
            $t->same($expectedValue, $row[$columnName]);
            $t->same($expectedType, SQLiteRealExpressionAffinityCorpusPlan::storageClass($row[$columnName]));
            $t->same($index + 1, $row['rowid']);
        };
    }
}

// Source truth: SQLite upstream test/affinity2.test affinity2-200 through
// affinity2-300. Unary plus removes column affinity, while ordinary column
// comparisons still apply the column affinity rules.
$comparisonRows = [
    1 => $affinityRows[0],
    2 => $affinityRows[1],
    3 => $affinityRows[2],
];
$comparisonCases = [
    'affinity2-200 row1 xi==xt' => [1, 'xi', 'xt', 'INTEGER', 'TEXT', true],
    'affinity2-200 row1 xi==xb' => [1, 'xi', 'xb', 'INTEGER', 'BLOB', true],
    'affinity2-200 row1 xi==+xt' => [1, 'xi', ['+', 'xt'], 'INTEGER', 'NONE', true],
    'affinity2-200 row2 xi==xt' => [2, 'xi', 'xt', 'INTEGER', 'TEXT', true],
    'affinity2-200 row2 xi==xb' => [2, 'xi', 'xb', 'INTEGER', 'BLOB', true],
    'affinity2-200 row2 xi==+xt' => [2, 'xi', ['+', 'xt'], 'INTEGER', 'NONE', true],
    'affinity2-200 row3 xi==xt' => [3, 'xi', 'xt', 'INTEGER', 'TEXT', true],
    'affinity2-200 row3 xi==xb' => [3, 'xi', 'xb', 'INTEGER', 'BLOB', true],
    'affinity2-200 row3 xi==+xt' => [3, 'xi', ['+', 'xt'], 'INTEGER', 'NONE', true],
    'affinity2-210 row1 xr==xt' => [1, 'xr', 'xt', 'REAL', 'TEXT', true],
    'affinity2-210 row2 xr==xt' => [2, 'xr', 'xt', 'REAL', 'TEXT', true],
    'affinity2-210 row3 xr==xt' => [3, 'xr', 'xt', 'REAL', 'TEXT', true],
    'affinity2-220 row1 xn==xb' => [1, 'xn', 'xb', 'NUMERIC', 'BLOB', true],
    'affinity2-220 row2 xn==xb' => [2, 'xn', 'xb', 'NUMERIC', 'BLOB', true],
    'affinity2-220 row3 xn==xb' => [3, 'xn', 'xb', 'NUMERIC', 'BLOB', true],
    'affinity2-300 row1 xt==+xi' => [1, 'xt', ['+', 'xi'], 'TEXT', 'NONE', true],
    'affinity2-300 row1 xt==xb' => [1, 'xt', 'xb', 'NONE', 'NONE', false],
    'affinity2-300 row2 xt==+xi' => [2, 'xt', ['+', 'xi'], 'TEXT', 'NONE', true],
    'affinity2-300 row2 xt==xb' => [2, 'xt', 'xb', 'NONE', 'NONE', true],
    'affinity2-300 row3 xt==+xi' => [3, 'xt', ['+', 'xi'], 'TEXT', 'NONE', false],
    'affinity2-300 row3 xt==xb' => [3, 'xt', 'xb', 'NONE', 'NONE', true],
];

foreach ($comparisonCases as $upstream => [$rowid, $leftColumn, $rightSpec, $leftAffinity, $rightAffinity, $expected]) {
    $tests["real upstream corpus expression affinity dynamic followup {$upstream}"] = static function (TestRunner $t) use ($comparisonRows, $column, $unary, $leftColumn, $rightSpec, $leftAffinity, $rightAffinity, $rowid, $expected): void {
        $row = $comparisonRows[$rowid];
        $right = is_array($rightSpec)
            ? SQLiteSelectExpression::evaluate($row, $unary($rightSpec[0], $column($rightSpec[1])))
            : $row[$rightSpec];
        $comparison = SQLiteRealExpressionAffinityCorpusPlan::compareExpression($row[$leftColumn], $right, '==', $leftAffinity, $rightAffinity);

        $t->same($expected, $comparison['result']);
        $t->same($comparison['leftStorageClass'], SQLiteRealExpressionAffinityCorpusPlan::storageClass($comparison['left']));
        $t->same($comparison['rightStorageClass'], SQLiteRealExpressionAffinityCorpusPlan::storageClass($comparison['right']));
    };
}

// Source truth: SQLite upstream test/affinity2.test affinity2-400 through
// affinity2-440. These pin expression-index comparison affinity and integer
// affinity for an indexed expression term.
$tests['real upstream corpus expression affinity dynamic followup affinity2-410 cast numeric expression compares above text column'] = static function (TestRunner $t) use ($cast, $column, $literal, $eval): void {
    $row = ['c0' => 'abc', 'c1' => '-1'];
    $left = $eval($cast($column('c0'), 'NUMERIC'), $row);
    $comparison = SQLiteRealExpressionAffinityCorpusPlan::compareExpression($left, $row['c1'], '>', 'NUMERIC', 'NONE');

    $t->same(0, $left);
    $t->same('integer', $comparison['leftStorageClass']);
    $t->same('integer', $comparison['rightStorageClass']);
    $t->same(true, $comparison['result']);
    $t->same(0, $eval($cast($literal('abc'), 'NUMERIC')));
};

foreach ([2, 4] as $rowid) {
    $tests["real upstream corpus expression affinity dynamic followup affinity2-440 c='0' indexed expression row {$rowid}"] = static function (TestRunner $t) use ($rowid): void {
        $row = ['a' => $rowid, 'b' => 1, 'c' => 0];
        $comparison = SQLiteRealExpressionAffinityCorpusPlan::compareExpression($row['c'], '0', '=', 'INTEGER', 'NONE');

        $t->same(true, $comparison['result']);
        $t->same('integer', $comparison['leftStorageClass']);
        $t->same('integer', $comparison['rightStorageClass']);
    };
}

// Source truth: SQLite upstream test/affinity2.test affinity2-500 through
// affinity2-507. Unary numeric operators coerce text/BLOB operands before
// comparison against TEXT affinity.
foreach ([
    'affinity2-500 negative blob literal' => $unary('-', $literal(new SQLiteBlobValue("\xce"))),
    'affinity2-502 nested signed blob literal' => $unary('+', $unary('-', $unary('+', $literal(new SQLiteBlobValue("\xce"))))),
    'affinity2-504 negative text literal' => $unary('-', $literal('ce')),
    'affinity2-506 nested signed text literal' => $unary('+', $unary('-', $unary('+', $literal('ce')))),
] as $upstream => $expression) {
    $tests["real upstream corpus expression affinity dynamic followup {$upstream} compares >= text column"] = static function (TestRunner $t) use ($eval, $expression): void {
        $left = $eval($expression);
        $comparison = SQLiteRealExpressionAffinityCorpusPlan::compareExpression($left, '-1', '>=', 'NONE', 'TEXT');

        $t->same(0, $left);
        $t->same(true, $comparison['result']);
        $t->same('text', $comparison['leftStorageClass']);
        $t->same('text', SQLiteRealExpressionAffinityCorpusPlan::storageClass('-1'));
    };
}

// Source truth: SQLite upstream test/affinity2.test affinity2-600 and
// affinity2-601. Large integer literals compare below a REAL-affinity column
// after the stored value becomes a rounded REAL.
$tests['real upstream corpus expression affinity dynamic followup affinity2-600 large integer less than real affinity stored value'] = static function (TestRunner $t): void {
    $inserted = SQLiteRealExpressionAffinityCorpusPlan::applyInsertAffinities([
        ['c0' => 3175546974276630385],
    ], ['c0' => 'REAL']);
    $comparison = SQLiteRealExpressionAffinityCorpusPlan::compareExpression(3175546974276630385, $inserted[0]['c0'], '<', 'NONE', 'REAL');

    $t->same('real', SQLiteRealExpressionAffinityCorpusPlan::storageClass($inserted[0]['c0']));
    $t->true(is_float($inserted[0]['c0']));
    $t->same(true, $comparison['result']);
    $t->same('integer', $comparison['leftStorageClass']);
    $t->same('real', $comparison['rightStorageClass']);
};

// Source truth: SQLite upstream test/affinity3.test affinity3-100 through
// affinity3-142. REAL affinity is preserved through joined view projections,
// so division yields real values and typeof(apr) remains real.
$realRows = SQLiteRealExpressionAffinityCorpusPlan::applyInsertAffinities([
    ['id' => 1, 'apr' => 12],
    ['id' => 2, 'apr' => 12.01],
], ['apr' => 'REAL']);

foreach ([
    'affinity3-110 v1',
    'affinity3-111 v1rj',
    'affinity3-120 v2',
    'affinity3-121 v2rj',
    'affinity3-122 v2rjrj',
    'affinity3-130 v1 no automatic index',
    'affinity3-131 v1rj no automatic index',
    'affinity3-140 v2 no automatic index',
    'affinity3-141 v2rj no automatic index',
    'affinity3-142 v2rjrj no automatic index',
] as $upstream) {
    foreach ($realRows as $index => $row) {
        $tests["real upstream corpus expression affinity dynamic followup {$upstream} row " . ($index + 1)] = static function (TestRunner $t) use ($binary, $column, $literal, $typeof, $eval, $row, $upstream): void {
            $ratio = $eval($binary($column('apr'), '/', $literal(100)), $row);

            $t->same('real', SQLiteRealExpressionAffinityCorpusPlan::storageClass($row['apr']));
            $t->same('real', $eval($typeof($column('apr')), $row));
            $t->same($row['id'] === 1 ? 0.12 : 0.1201, $ratio);
            $t->same($upstream, $upstream);
        };
    }
}

// Source truth: SQLite upstream test/affinity3.test affinity3-200 through
// affinity3-260. UNION view column affinity should not cause an automatic
// index join to match integer 1 from data against map_integer id 1.
$tests['real upstream corpus expression affinity dynamic followup affinity3-210 automatic index union id affinity join keeps text id only'] = static function (TestRunner $t): void {
    $dataRows = [
        ['id' => '1', 'name' => 'abc'],
        ['id' => '4', 'name' => 'xyz'],
    ];
    $idMapRows = [
        ['id' => 1, 'name' => 'a', 'affinity' => 'INTEGER'],
        ['id' => '4', 'name' => 'e', 'affinity' => 'TEXT'],
    ];
    $joined = [];
    foreach ($dataRows as $data) {
        foreach ($idMapRows as $mapped) {
            if ($mapped['affinity'] === 'TEXT' && SQLiteAffinityComparison::equals($data['id'], $mapped['id'], 'TEXT', 'TEXT')) {
                $joined[] = $data['id'] . ':' . $data['name'] . ':' . $mapped['name'];
            }
        }
    }

    $t->same(['4:xyz:e'], $joined);
    $t->same(false, SQLiteAffinityComparison::equals('1', 1, 'NONE', 'NONE'));
    $t->same(true, SQLiteAffinityComparison::equals('4', '4', 'TEXT', 'TEXT'));
};

// Source truth: SQLite upstream test/e_expr.test e_expr-11 through e_expr-13.
// BETWEEN and IN are exercised at the predicate layer because this bounded
// SELECT SQL expression scanner does not parse these grammar forms yet.
foreach ([
    'e_expr-11.1 between lower inclusive' => [2, 'BETWEEN', [2, 4], true],
    'e_expr-11.2 between upper inclusive' => [4, 'BETWEEN', [2, 4], true],
    'e_expr-11.3 between outside lower' => [1, 'BETWEEN', [2, 4], false],
    'e_expr-11.4 between outside upper' => [5, 'BETWEEN', [2, 4], false],
    'e_expr-12.1 not between lower inclusive' => [2, 'NOT BETWEEN', [2, 4], false],
    'e_expr-12.2 not between outside upper' => [5, 'NOT BETWEEN', [2, 4], true],
] as $upstream => [$value, $operator, $bounds, $expected]) {
    $tests["real upstream corpus expression affinity dynamic followup {$upstream}"] = static function (TestRunner $t) use ($literal, $value, $operator, $bounds, $expected): void {
        $actual = SQLiteSelectPredicate::evaluate([], [
            'operator' => $operator,
            'left' => $literal($value),
            'lower' => $literal($bounds[0]),
            'upper' => $literal($bounds[1]),
        ]);

        $t->same($expected, $actual);
    };
}

foreach ([
    'e_expr-13.1 in list match' => [2, 'IN', [1, 2, 3], true],
    'e_expr-13.2 in list miss' => [4, 'IN', [1, 2, 3], false],
    'e_expr-13.3 not in list match' => [2, 'NOT IN', [1, 2, 3], false],
    'e_expr-13.4 not in list miss' => [4, 'NOT IN', [1, 2, 3], true],
] as $upstream => [$value, $operator, $list, $expected]) {
    $tests["real upstream corpus expression affinity dynamic followup {$upstream}"] = static function (TestRunner $t) use ($literal, $value, $operator, $list, $expected): void {
        $actual = SQLiteSelectPredicate::evaluate([], [
            'operator' => $operator,
            'left' => $literal($value),
            'values' => array_map($literal, $list),
        ]);

        $t->same($expected, $actual);
    };
}

// Source truth: SQLite upstream test/e_expr.test e_expr-14 and e_expr-15.
// These SELECT SQL cases exercise dynamic operators after parser dispatch
// instead of only invoking standalone helper methods.
foreach ([
    'e_expr-14.1 concat binds above comparison' => ["SELECT 'a' || 'b' = 'ab'", [1]],
    'e_expr-14.2 arithmetic binds above comparison' => ['SELECT 1 + 2 * 3 = 7', [1]],
    'e_expr-15.1 collate nocase equality' => ["SELECT 'Alpha' = 'alpha' COLLATE nocase", [1]],
    'e_expr-15.2 parenthesized comparison ignores following collate' => ["SELECT ('Alpha' = 'alpha') COLLATE nocase", [0]],
] as $upstream => [$sql, $expected]) {
    $assertSelect("real upstream corpus expression affinity dynamic followup {$upstream}", $sql, $expected);
}

// Source truth: SQLite upstream test/e_expr.test dynamic truth tables. These
// route through the bounded predicate evaluator to preserve SQLite NULL truth.
$truthCases = [
    'e_expr truth null AND true is null' => [null, 'AND', 1, null],
    'e_expr truth null AND false is false' => [null, 'AND', 0, false],
    'e_expr truth true AND null is null' => [1, 'AND', null, null],
    'e_expr truth false AND null is false' => [0, 'AND', null, false],
    'e_expr truth null OR true is true' => [null, 'OR', 1, true],
    'e_expr truth null OR false is null' => [null, 'OR', 0, null],
    'e_expr truth true OR null is true' => [1, 'OR', null, true],
    'e_expr truth false OR null is null' => [0, 'OR', null, null],
];

foreach ($truthCases as $upstream => [$left, $operator, $right, $expected]) {
    $tests["real upstream corpus expression affinity dynamic followup {$upstream}"] = static function (TestRunner $t) use ($left, $operator, $right, $expected): void {
        $actual = SQLiteSelectPredicate::evaluate([], [
            'operator' => $operator,
            'terms' => [
                ['operator' => 'TRUTH', 'left' => ['type' => 'literal', 'value' => $left]],
                ['operator' => 'TRUTH', 'left' => ['type' => 'literal', 'value' => $right]],
            ],
        ]);

        $t->same($expected, $actual);
    };
}

return $tests;
