<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteSelectQuery;
use PortLibs\LibSqlite\SQLiteSelectSql;

return [
    'executes sqlite values source rows in select sql from clauses' => static function (TestRunner $t): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT column1 AS option_id, column2 AS option_name, column3 AS autoload FROM (VALUES (2, 'home', 'yes'), (1, 'siteurl', 'yes'), (4, '_transient_feed', 'no'), (3, 'blogname', 'yes')) AS v WHERE column3 = 'yes' ORDER BY column1 DESC LIMIT 2",
            [],
        );

        $t->same(2, count($rows));
        $t->same([3, 2], array_column($rows, 'option_id'));
        $t->same(['blogname', 'home'], array_column($rows, 'option_name'));
        $t->same(['yes', 'yes'], array_column($rows, 'autoload'));
        $t->same(['option_id', 'option_name', 'autoload'], array_keys($rows[0]));
        $t->same(3, $rows[0]['option_id']);
        $t->same('blogname', $rows[0]['option_name']);
        $t->same('yes', $rows[0]['autoload']);
        $t->same(2, $rows[1]['option_id']);
        $t->same('home', $rows[1]['option_name']);

        $plan = SQLiteSelectSql::plan(
            "SELECT column1 AS id, column2 AS name FROM (VALUES (1, 'siteurl'), (2, 'home')) WHERE column1 IN (1, 3) ORDER BY name",
            [],
        );
        $t->same(['from', 'select', 'where', 'orderBy'], array_keys($plan));
        $t->same(2, count($plan['from']));
        $t->same(['column1', 'column2'], array_keys($plan['from'][0]));
        $t->same(1, $plan['from'][0]['column1']);
        $t->same('siteurl', $plan['from'][0]['column2']);
        $t->same(2, $plan['from'][1]['column1']);
        $t->same('home', $plan['from'][1]['column2']);
        $t->same('column1', $plan['select'][0]['name']);
        $t->same('id', $plan['select'][0]['alias']);
        $t->same('IN', $plan['where']['operator']);
        $t->same('column1', $plan['where']['left']['name']);
        $t->same([1, 3], array_column($plan['where']['values'], 'value'));
        $t->same([['column' => 'name']], $plan['orderBy']);

        $planRows = SQLiteSelectQuery::execute($plan);
        $t->same(1, count($planRows));
        $t->same('siteurl', $planRows[0]['name']);
    },

    'joins sqlite values source rows with copied application option rows' => static function (TestRunner $t): void {
        $options = [
            ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'bytes' => 24],
            ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'bytes' => 24],
            ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'bytes' => 9],
            ['option_id' => 4, 'option_name' => '_transient_feed', 'autoload' => 'no', 'bytes' => 12],
        ];

        $rows = SQLiteSelectSql::execute(
            "SELECT o.option_name AS option_name, incoming.column2 AS new_value, incoming.column3 AS priority FROM wp_options AS o JOIN (VALUES (1, 'https://example.test', 20), (2, 'https://example.test/home', 10), (5, 'unused', 99)) AS incoming ON incoming.column1 = o.option_id WHERE incoming.column3 >= 10 ORDER BY incoming.column3 DESC, option_name",
            ['wp_options' => $options],
        );

        $t->same(2, count($rows));
        $t->same(['siteurl', 'home'], array_column($rows, 'option_name'));
        $t->same(['https://example.test', 'https://example.test/home'], array_column($rows, 'new_value'));
        $t->same([20, 10], array_column($rows, 'priority'));
        $t->same(['option_name', 'new_value', 'priority'], array_keys($rows[0]));
        $t->same('siteurl', $rows[0]['option_name']);
        $t->same('https://example.test', $rows[0]['new_value']);
        $t->same(20, $rows[0]['priority']);
        $t->same('home', $rows[1]['option_name']);
        $t->same(10, $rows[1]['priority']);

        $leftRows = SQLiteSelectSql::execute(
            "SELECT incoming.column1 AS incoming_id, o.option_name AS option_name FROM (VALUES (1), (5)) AS incoming LEFT JOIN wp_options AS o ON incoming.column1 = o.option_id ORDER BY incoming_id",
            ['wp_options' => $options],
        );
        $t->same(2, count($leftRows));
        $t->same([1, 5], array_column($leftRows, 'incoming_id'));
        $t->same(['siteurl', null], array_column($leftRows, 'option_name'));
        $t->same(['incoming_id', 'option_name'], array_keys($leftRows[0]));
        $t->same(null, $leftRows[1]['option_name']);
    },

    'handles sqlite values source expressions blobs nulls and malformed aliases' => static function (TestRunner $t): void {
        $rows = SQLiteSelectSql::execute(
            "SELECT column1 AS label, column2 AS numeric_value, column3 AS missing_value, column4 AS blob_value FROM (VALUES ('count', 2 + 3, NULL, X'4142'), ('shift', 1 << 4, NULL, X'00ff')) v ORDER BY numeric_value DESC",
            [],
        );

        $t->same(2, count($rows));
        $t->same(['shift', 'count'], array_column($rows, 'label'));
        $t->same([16, 5], array_column($rows, 'numeric_value'));
        $t->same([null, null], array_column($rows, 'missing_value'));
        $t->same(['label', 'numeric_value', 'missing_value', 'blob_value'], array_keys($rows[0]));
        $t->true($rows[0]['blob_value'] instanceof SQLiteBlobValue);
        $t->true($rows[1]['blob_value'] instanceof SQLiteBlobValue);
        $t->same('00ff', bin2hex($rows[0]['blob_value']->bytes));
        $t->same('4142', bin2hex($rows[1]['blob_value']->bytes));
        $t->same('shift', $rows[0]['label']);
        $t->same(16, $rows[0]['numeric_value']);
        $t->same(null, $rows[0]['missing_value']);

        $crossRows = SQLiteSelectSql::execute(
            "SELECT base.column1 AS base_id, flag.column1 AS flag FROM (VALUES (1), (2)) AS base, (VALUES ('yes'), ('no')) AS flag WHERE flag.column1 = 'yes' ORDER BY base_id",
            [],
        );
        $t->same(2, count($crossRows));
        $t->same([1, 2], array_column($crossRows, 'base_id'));
        $t->same(['yes', 'yes'], array_column($crossRows, 'flag'));

        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute("SELECT column1 FROM (VALUES (1), (2, 3))", []));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute("SELECT column1 FROM (VALUES ())", []));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute("SELECT column1 FROM (VALUES (1)) AS 1bad", []));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute("SELECT column1 FROM (VALUES (1)) AS v extra", []));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute("SELECT column1 FROM (VALUES ((SELECT 1)))", []));
    },
];
