<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$options = [
    ['option_id' => 1, 'site_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes'],
    ['option_id' => 2, 'site_id' => 1, 'option_name' => 'home', 'autoload' => 'yes'],
    ['option_id' => 3, 'site_id' => 1, 'option_name' => 'blogname', 'autoload' => 'yes'],
    ['option_id' => 4, 'site_id' => 1, 'option_name' => '_transient_feed', 'autoload' => 'no'],
    ['option_id' => 5, 'site_id' => 2, 'option_name' => 'network_admin_email', 'autoload' => 'yes'],
    ['option_id' => null, 'site_id' => 3, 'option_name' => 'null_option', 'autoload' => 'no'],
];

$meta = [
    ['option_id' => 1, 'site_id' => 1, 'source' => 'core', 'priority' => 10],
    ['option_id' => 2, 'site_id' => 1, 'source' => 'core', 'priority' => 20],
    ['option_id' => 3, 'site_id' => 1, 'source' => 'theme', 'priority' => 30],
    ['option_id' => 4, 'site_id' => 1, 'source' => 'cache', 'priority' => 5],
    ['option_id' => 6, 'site_id' => 2, 'source' => 'orphan_meta', 'priority' => 1],
    ['option_id' => null, 'site_id' => 3, 'source' => 'null_meta', 'priority' => 99],
];

$labels = [
    ['site_id' => 1, 'source' => 'core', 'label' => 'public'],
    ['site_id' => 1, 'source' => 'theme', 'label' => 'theme'],
    ['site_id' => 1, 'source' => 'cache', 'label' => 'runtime'],
    ['site_id' => 2, 'source' => 'orphan_meta', 'label' => 'network orphan'],
];

$tables = ['wp_options' => $options, 'option_meta' => $meta, 'source_labels' => $labels];

return [
    'executes qualified join using equality over copied option ids' => static function (TestRunner $t) use ($tables): void {
        $rows = SQLiteSelectSql::execute(
            'SELECT o.option_name AS name, m.source AS source FROM wp_options AS o JOIN option_meta AS m USING (option_id) ORDER BY name',
            $tables,
        );

        $t->same(4, count($rows));
        $t->same(['_transient_feed', 'blogname', 'home', 'siteurl'], array_column($rows, 'name'));
        $t->same(['cache', 'theme', 'core', 'core'], array_column($rows, 'source'));
        $t->same(['name', 'source'], array_keys($rows[0]));
        $t->same('_transient_feed', $rows[0]['name']);
        $t->same('cache', $rows[0]['source']);
    },
    'executes left join using with null-extended right side' => static function (TestRunner $t) use ($tables): void {
        $rows = SQLiteSelectSql::execute(
            'SELECT o.option_name AS name, m.source AS source, m.priority AS priority FROM wp_options AS o LEFT JOIN option_meta AS m USING (option_id) ORDER BY name',
            $tables,
        );

        $t->same(6, count($rows));
        $t->same(['_transient_feed', 'blogname', 'home', 'network_admin_email', 'null_option', 'siteurl'], array_column($rows, 'name'));
        $t->same(['cache', 'theme', 'core', null, null, 'core'], array_column($rows, 'source'));
        $t->same([5, 30, 20, null, null, 10], array_column($rows, 'priority'));
        $t->same(null, $rows[3]['source']);
        $t->same(null, $rows[4]['priority']);
    },
    'executes multi-column using without matching partial keys' => static function (TestRunner $t) use ($tables): void {
        $rows = SQLiteSelectSql::execute(
            'SELECT o.option_name AS name, m.source AS source FROM wp_options AS o JOIN option_meta AS m USING (option_id, site_id) ORDER BY name',
            $tables,
        );

        $t->same(4, count($rows));
        $t->same(['_transient_feed', 'blogname', 'home', 'siteurl'], array_column($rows, 'name'));
        $t->same(['cache', 'theme', 'core', 'core'], array_column($rows, 'source'));
        $t->same('siteurl', $rows[3]['name']);
        $t->same('core', $rows[3]['source']);
    },
    'executes natural join from common column names' => static function (TestRunner $t) use ($tables): void {
        $rows = SQLiteSelectSql::execute(
            'SELECT o.option_name AS name, m.source AS source FROM wp_options AS o NATURAL JOIN option_meta AS m ORDER BY name',
            $tables,
        );

        $t->same(4, count($rows));
        $t->same(['_transient_feed', 'blogname', 'home', 'siteurl'], array_column($rows, 'name'));
        $t->same(['cache', 'theme', 'core', 'core'], array_column($rows, 'source'));
        $t->same(['name', 'source'], array_keys($rows[0]));
        $t->same('blogname', $rows[1]['name']);
    },
    'executes natural left outer join with unmatched option rows' => static function (TestRunner $t) use ($tables): void {
        $rows = SQLiteSelectSql::execute(
            'SELECT o.option_name AS name, m.source AS source FROM wp_options AS o NATURAL LEFT OUTER JOIN option_meta AS m ORDER BY name',
            $tables,
        );

        $t->same(6, count($rows));
        $t->same(['_transient_feed', 'blogname', 'home', 'network_admin_email', 'null_option', 'siteurl'], array_column($rows, 'name'));
        $t->same(['cache', 'theme', 'core', null, null, 'core'], array_column($rows, 'source'));
        $t->same(null, $rows[3]['source']);
        $t->same(null, $rows[4]['source']);
    },
    'executes right join using with unmatched metadata rows' => static function (TestRunner $t) use ($tables): void {
        $rows = SQLiteSelectSql::execute(
            'SELECT o.option_name AS name, m.source AS source, m.priority AS priority FROM wp_options AS o RIGHT JOIN option_meta AS m USING (option_id) ORDER BY priority',
            $tables,
        );

        $t->same(6, count($rows));
        $t->same([1, 5, 10, 20, 30, 99], array_column($rows, 'priority'));
        $t->same([null, '_transient_feed', 'siteurl', 'home', 'blogname', null], array_column($rows, 'name'));
        $t->same(['orphan_meta', 'cache', 'core', 'core', 'theme', 'null_meta'], array_column($rows, 'source'));
        $t->same(null, $rows[0]['name']);
        $t->same('orphan_meta', $rows[0]['source']);
    },
    'executes full outer join using with left and right unmatched rows' => static function (TestRunner $t) use ($tables): void {
        $rows = SQLiteSelectSql::execute(
            'SELECT o.option_name AS name, m.source AS source, m.priority AS priority FROM wp_options AS o FULL OUTER JOIN option_meta AS m USING (option_id) ORDER BY name, priority',
            $tables,
        );

        $t->same(8, count($rows));
        $t->same([null, null, '_transient_feed', 'blogname', 'home', 'network_admin_email', 'null_option', 'siteurl'], array_column($rows, 'name'));
        $t->same(['orphan_meta', 'null_meta', 'cache', 'theme', 'core', null, null, 'core'], array_column($rows, 'source'));
        $t->same([1, 99, 5, 30, 20, null, null, 10], array_column($rows, 'priority'));
        $t->same(null, $rows[0]['name']);
        $t->same(null, $rows[5]['source']);
    },
    'executes chained using join after natural join' => static function (TestRunner $t) use ($tables): void {
        $rows = SQLiteSelectSql::execute(
            'SELECT o.option_name AS name, l.label AS label FROM wp_options AS o NATURAL JOIN option_meta AS m JOIN source_labels AS l USING (site_id, source) ORDER BY name',
            $tables,
        );

        $t->same(4, count($rows));
        $t->same(['_transient_feed', 'blogname', 'home', 'siteurl'], array_column($rows, 'name'));
        $t->same(['runtime', 'theme', 'public', 'public'], array_column($rows, 'label'));
        $t->same('runtime', $rows[0]['label']);
        $t->same('public', $rows[3]['label']);
    },
    'rejects invalid using and natural cross forms' => static function (TestRunner $t) use ($tables): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute(
            'SELECT o.option_name FROM wp_options AS o JOIN option_meta AS m USING (missing)',
            $tables,
        ));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute(
            'SELECT o.option_name FROM wp_options AS o CROSS JOIN option_meta AS m USING (option_id)',
            $tables,
        ));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute(
            'SELECT o.option_name FROM wp_options AS o NATURAL CROSS JOIN option_meta AS m',
            $tables,
        ));
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute(
            'SELECT o.option_name FROM wp_options AS o JOIN option_meta AS m USING ()',
            $tables,
        ));
    },
];
