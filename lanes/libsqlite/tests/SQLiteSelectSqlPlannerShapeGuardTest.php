<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$records = [
    ['id' => 1, 'score' => 10, 'label' => 'alpha'],
    ['id' => 2, 'score' => 20, 'label' => 'beta'],
    ['id' => 3, 'score' => 5, 'label' => 'gamma'],
];
$tables = [
    'records' => $records,
    'limits' => [['value' => 7]],
];

$tests = [];

$tests['select sql planner shape guard keeps ordinary projections unchanged'] =
    static function (TestRunner $t) use ($records, $tables): void {
        $plan = SQLiteSelectSql::plan(
            'SELECT id, label FROM records WHERE score >= 10 ORDER BY id',
            $tables,
        );

        $t->same(
            [
                ['type' => 'column', 'name' => 'id'],
                ['type' => 'column', 'name' => 'label'],
            ],
            $plan['select'],
        );
        $t->same(
            [
                ['id' => 1, 'label' => 'alpha'],
                ['id' => 2, 'label' => 'beta'],
            ],
            SQLiteSelectSql::execute(
                'SELECT id, label FROM records WHERE score >= 10 ORDER BY id',
                ['records' => $records],
            ),
        );
    };

$tests['select sql planner shape guard still annotates unqualified wildcards'] =
    static function (TestRunner $t) use ($records): void {
        $sql = 'SELECT * FROM records ORDER BY id';
        $plan = SQLiteSelectSql::plan($sql, ['records' => $records]);

        $t->same('wildcard', $plan['select'][0]['type']);
        $t->same(['id', 'score', 'label'], $plan['select'][0]['columns']);
        $t->same($records, SQLiteSelectSql::execute($sql, ['records' => $records]));
    };

$tests['select sql planner shape guard still annotates qualified wildcards'] =
    static function (TestRunner $t) use ($records): void {
        $sql = 'SELECT r.* FROM records AS r ORDER BY r.id';
        $plan = SQLiteSelectSql::plan($sql, ['records' => $records]);

        $t->same('wildcard', $plan['select'][0]['type']);
        $t->same('r', $plan['select'][0]['prefix']);
        $t->same(['id', 'score', 'label'], $plan['select'][0]['columns']);
        $t->same($records, SQLiteSelectSql::execute($sql, ['records' => $records]));
    };

$tests['select sql planner shape guard still lifts outer aggregate scalar subqueries'] =
    static function (TestRunner $t) use ($records): void {
        $sql = 'SELECT (SELECT sum(score)) AS total FROM records';
        $plan = SQLiteSelectSql::plan($sql, ['records' => $records]);

        $t->same(true, $plan['groupBy']['implicitAggregate']);
        $t->same('sum', $plan['select'][0]['name']);
        $t->same('sum', $plan['select'][0]['sourceExpression']['name']);
        $t->same([['total' => 35]], SQLiteSelectSql::execute($sql, ['records' => $records]));
    };

$tests['select sql planner shape guard keeps ordinary scalar subqueries executable'] =
    static function (TestRunner $t) use ($tables): void {
        $sql = 'SELECT id, (SELECT value FROM limits) AS cap FROM records ORDER BY id';
        $plan = SQLiteSelectSql::plan($sql, $tables);

        $t->same('subquery', $plan['select'][1]['type']);
        $t->true(is_callable($plan['select'][1]['subquery']));
        $t->same(
            [
                ['id' => 1, 'cap' => 7],
                ['id' => 2, 'cap' => 7],
                ['id' => 3, 'cap' => 7],
            ],
            SQLiteSelectSql::execute($sql, $tables),
        );
    };

return $tests;
