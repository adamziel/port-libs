<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectQuery;
use PortLibs\LibSqlite\SQLiteSelectSql;

$recursivePlan = new ReflectionMethod(SQLiteSelectSql::class, 'compileReusableRecursiveArm');
$cartesianCount = new ReflectionMethod(SQLiteSelectQuery::class, 'cartesianCountAll');

return [
    'recursive performance fast path reuses a single-source arm plan' => static function (TestRunner $t) use ($recursivePlan): void {
        $compiled = $recursivePlan->invoke(
            null,
            'SELECT n + 1 FROM seq WHERE n < 200',
            'seq',
            ['seq' => [['n' => 1]]],
            ['n' => 1],
        );

        $t->true(is_array($compiled));
        $t->same(['n' => 'n'], $compiled['bindings'] ?? null);
        $t->same(
            [['total' => 20100]],
            SQLiteSelectSql::execute(
                'WITH RECURSIVE seq(n) AS ('
                . 'VALUES(1) UNION ALL SELECT n + 1 FROM seq WHERE n < 200'
                . ') SELECT sum(n) AS total FROM seq',
                [],
            ),
        );
    },

    'recursive performance fast path preserves aliases and changing storage classes' => static function (TestRunner $t): void {
        $t->same(
            [
                ['value' => 1, 'storage' => 'integer'],
                ['value' => '1', 'storage' => 'text'],
            ],
            SQLiteSelectSql::execute(
                "WITH RECURSIVE seq(value) AS ("
                . "VALUES(1) UNION SELECT CAST(s.value AS TEXT) FROM seq AS s "
                . "WHERE typeof(s.value) = 'integer'"
                . ') SELECT value, typeof(value) AS storage FROM seq',
                [],
            ),
        );
    },

    'recursive performance fast path falls back for joined recursive arms' => static function (TestRunner $t) use ($recursivePlan): void {
        $sql = 'SELECT seq.n + increments.step '
            . 'FROM seq CROSS JOIN increments WHERE seq.n < 5';
        $tables = [
            'seq' => [['n' => 1]],
            'increments' => [['step' => 1]],
        ];

        $t->same(null, $recursivePlan->invoke(null, $sql, 'seq', $tables, ['n' => 1]));
        $t->same(
            [['n' => 1], ['n' => 2], ['n' => 3], ['n' => 4], ['n' => 5]],
            SQLiteSelectSql::execute(
                'WITH RECURSIVE seq(n) AS ('
                . 'VALUES(1) UNION ALL '
                . 'SELECT seq.n + increments.step '
                . 'FROM seq CROSS JOIN increments WHERE seq.n < 5'
                . ') SELECT n FROM seq',
                ['increments' => [['step' => 1]]],
            ),
        );
    },

    'recursive performance fast path falls back for dynamic scalar subqueries' => static function (TestRunner $t) use ($recursivePlan): void {
        $sql = 'SELECT n + 1 FROM seq '
            . 'WHERE n < (SELECT max_n FROM limits)';
        $tables = [
            'seq' => [['n' => 1]],
            'limits' => [['max_n' => 5]],
        ];

        $t->same(null, $recursivePlan->invoke(null, $sql, 'seq', $tables, ['n' => 1]));
        $t->same(
            [['n' => 1], ['n' => 2], ['n' => 3], ['n' => 4], ['n' => 5]],
            SQLiteSelectSql::execute(
                'WITH RECURSIVE seq(n) AS ('
                . 'VALUES(1) UNION ALL SELECT n + 1 FROM seq '
                . 'WHERE n < (SELECT max_n FROM limits)'
                . ') SELECT n FROM seq',
                ['limits' => [['max_n' => 5]]],
            ),
        );
    },

    'cartesian count fast path multiplies static source cardinalities' => static function (TestRunner $t) use ($cartesianCount): void {
        $left = array_map(static fn (int $id): array => ['id' => $id], range(1, 150));
        $plan = SQLiteSelectSql::plan(
            'SELECT count(*) AS pairs FROM records AS a CROSS JOIN records AS b',
            ['records' => $left],
        );

        $t->same(
            22500,
            $cartesianCount->invoke(null, $plan['from'], $plan['joins'], $plan),
        );
        $t->same(
            [['pairs' => 22500]],
            SQLiteSelectQuery::execute($plan),
        );
    },

    'cartesian count fast path handles empty and multiple right sources' => static function (TestRunner $t) use ($cartesianCount): void {
        $tables = [
            'a' => [['id' => 1], ['id' => 2]],
            'b' => [['id' => 1], ['id' => 2], ['id' => 3]],
            'c' => [],
        ];
        $plan = SQLiteSelectSql::plan(
            'SELECT count(*) AS total FROM a CROSS JOIN b CROSS JOIN c',
            $tables,
        );

        $t->same(0, $cartesianCount->invoke(null, $plan['from'], $plan['joins'], $plan));
        $t->same([['total' => 0]], SQLiteSelectQuery::execute($plan));
    },

    'cartesian count fast path declines row-dependent count queries' => static function (TestRunner $t) use ($cartesianCount): void {
        $tables = [
            'a' => [['id' => 1], ['id' => 2]],
            'b' => [['id' => 1], ['id' => null]],
        ];
        $countColumnPlan = SQLiteSelectSql::plan(
            'SELECT count(b.id) AS total FROM a CROSS JOIN b',
            $tables,
        );
        $filteredPlan = SQLiteSelectSql::plan(
            'SELECT count(*) AS total FROM a CROSS JOIN b WHERE b.id IS NOT NULL',
            $tables,
        );
        $havingPlan = SQLiteSelectSql::plan(
            'SELECT count(*) AS total FROM a CROSS JOIN b HAVING count(*) = 4',
            $tables,
        );

        $t->same(
            null,
            $cartesianCount->invoke(
                null,
                $countColumnPlan['from'],
                $countColumnPlan['joins'],
                $countColumnPlan,
            ),
        );
        $t->same([['total' => 2]], SQLiteSelectQuery::execute($countColumnPlan));
        $t->same(
            null,
            $cartesianCount->invoke(
                null,
                $filteredPlan['from'],
                $filteredPlan['joins'],
                $filteredPlan,
            ),
        );
        $t->same([['total' => 2]], SQLiteSelectQuery::execute($filteredPlan));
        $t->same(
            null,
            $cartesianCount->invoke(
                null,
                $havingPlan['from'],
                $havingPlan['joins'],
                $havingPlan,
            ),
        );
        $t->same([['total' => 4]], SQLiteSelectQuery::execute($havingPlan));
    },
];
