<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectFlatteningPlan;

$tests = [];

$flattenableCases = [
    [
        "SELECT option_name FROM (SELECT option_id, option_name FROM wp_options WHERE autoload = 'yes') AS staged WHERE option_id > 1 ORDER BY option_name",
        'staged',
        "(autoload = 'yes') AND (option_id > 1)",
        "SELECT option_name FROM wp_options WHERE (autoload = 'yes') AND (option_id > 1) ORDER BY option_name",
    ],
    [
        "SELECT name FROM (SELECT option_name AS name, option_id AS id FROM wp_options) AS picked WHERE id BETWEEN 1 AND 3",
        'picked',
        'id BETWEEN 1 AND 3',
        "SELECT name FROM wp_options WHERE id BETWEEN 1 AND 3",
    ],
    [
        "SELECT label FROM (SELECT option_name || ':' || autoload AS label, option_id FROM wp_options WHERE option_id <= 5) d WHERE label LIKE 'site%' LIMIT 1",
        'd',
        "(option_id <= 5) AND (label LIKE 'site%')",
        "SELECT label FROM wp_options WHERE (option_id <= 5) AND (label LIKE 'site%') LIMIT 1",
    ],
    [
        "SELECT option_id FROM (SELECT option_id FROM wp_options ORDER BY option_id) AS ordered WHERE option_id < 4",
        'ordered',
        'option_id < 4',
        "SELECT option_id FROM wp_options WHERE option_id < 4 ORDER BY option_id",
    ],
    [
        "SELECT option_name FROM (SELECT option_name, option_id FROM wp_options WHERE option_name GLOB 'plugin_*') WHERE option_id IS NOT NULL",
        'subquery',
        "(option_name GLOB 'plugin_*') AND (option_id IS NOT NULL)",
        "SELECT option_name FROM wp_options WHERE (option_name GLOB 'plugin_*') AND (option_id IS NOT NULL)",
    ],
    [
        "SELECT option_name FROM (SELECT ALL option_name, option_id FROM wp_options WHERE autoload IS 'yes') AS d WHERE option_id IN (1, 2)",
        'd',
        "(autoload IS 'yes') AND (option_id IN (1, 2))",
        "SELECT option_name FROM wp_options WHERE (autoload IS 'yes') AND (option_id IN (1, 2))",
    ],
];

foreach ($flattenableCases as $index => [$sql, $alias, $where, $flattenedSql]) {
    $tests['select query flattening current next29 flattenable case ' . ($index + 1)] = static function (TestRunner $t) use ($sql, $alias, $where, $flattenedSql): void {
        $plan = SQLiteSelectFlatteningPlan::plan($sql);
        $t->true($plan['flattenable']);
        $t->same('flattenable', $plan['reason']);
        $t->same([], $plan['blockers']);
        $t->same($alias, $plan['alias']);
        $t->same($where, $plan['mergedWhere']);
        $t->same($flattenedSql, $plan['flattenedSql']);
        $t->true(array_key_exists('projectionMap', $plan));
    };
}

$blockedCases = [
    'inner distinct blocks flattening' => [
        "SELECT autoload FROM (SELECT DISTINCT autoload FROM wp_options) AS d",
        'inner-distinct',
    ],
    'inner limit blocks flattening' => [
        "SELECT option_name FROM (SELECT option_name FROM wp_options ORDER BY option_id LIMIT 2) AS d WHERE option_name IS NOT NULL",
        'inner-limit',
    ],
    'inner aggregate blocks flattening' => [
        "SELECT autoload FROM (SELECT autoload, count(*) AS n FROM wp_options GROUP BY autoload) AS d WHERE n > 1",
        'inner-aggregate',
    ],
    'inner having blocks flattening' => [
        "SELECT autoload FROM (SELECT autoload FROM wp_options GROUP BY autoload HAVING count(*) > 1) AS d",
        'inner-aggregate',
    ],
    'inner compound blocks flattening' => [
        "SELECT option_name FROM (SELECT option_name FROM wp_options UNION SELECT option_name FROM wp_options_archive) AS d",
        'inner-compound',
    ],
    'outer distinct blocks flattening' => [
        "SELECT DISTINCT option_name FROM (SELECT option_name FROM wp_options) AS d",
        'outer-distinct',
    ],
    'outer aggregate blocks flattening' => [
        "SELECT count(*) AS n FROM (SELECT option_name FROM wp_options) AS d",
        'outer-aggregate',
    ],
    'outer group by blocks flattening' => [
        "SELECT autoload, count(*) AS n FROM (SELECT autoload FROM wp_options) AS d GROUP BY autoload",
        'outer-aggregate',
    ],
    'outer join source blocks flattening' => [
        "SELECT d.option_name FROM (SELECT option_id, option_name FROM wp_options) AS d JOIN option_meta AS m ON m.option_id = d.option_id",
        'outer-join-source',
    ],
    'window projection blocks flattening' => [
        "SELECT rn FROM (SELECT row_number() OVER (ORDER BY option_id) AS rn FROM wp_options) AS d",
        'window-function',
    ],
    'inner order is sensitive when outer limit exists' => [
        "SELECT option_name FROM (SELECT option_name FROM wp_options ORDER BY option_id) AS d LIMIT 2",
        'inner-order-sensitive',
    ],
    'inner order is sensitive when outer order exists' => [
        "SELECT option_name FROM (SELECT option_name FROM wp_options ORDER BY option_id) AS d ORDER BY option_name",
        'inner-order-sensitive',
    ],
];

foreach ($blockedCases as $name => [$sql, $reason]) {
    $tests['select query flattening current next29 ' . $name] = static function (TestRunner $t) use ($sql, $reason): void {
        $plan = SQLiteSelectFlatteningPlan::plan($sql);
        $t->same(false, $plan['flattenable']);
        $t->same($reason, $plan['reason']);
        $t->true(in_array($reason, $plan['blockers'], true));
        $t->same(false, array_key_exists('flattenedSql', $plan));
    };
}

$tests['select query flattening current next29 projection map records aliases'] = static function (TestRunner $t): void {
    $plan = SQLiteSelectFlatteningPlan::plan("SELECT name, id FROM (SELECT option_name AS name, option_id id, autoload FROM wp_options WHERE autoload = 'yes') AS d WHERE id > 1");
    $t->same('option_name', $plan['projectionMap']['name']);
    $t->same('option_id', $plan['projectionMap']['id']);
    $t->same('autoload', $plan['projectionMap']['autoload']);
    $t->same('d', $plan['alias']);
    $t->same("(autoload = 'yes') AND (id > 1)", $plan['mergedWhere']);
    $t->same('wp_options', $plan['inner']['from']);
    $t->same('id > 1', $plan['outer']['where']);
};

$tests['select query flattening current next29 reports no derived source'] = static function (TestRunner $t): void {
    $plan = SQLiteSelectFlatteningPlan::plan('SELECT option_name FROM wp_options WHERE autoload = \'yes\'');
    $t->same(false, $plan['flattenable']);
    $t->same('no-derived-source', $plan['reason']);
    $t->same(['no-derived-source'], $plan['blockers']);
    $t->same('wp_options', $plan['outer']['from']);
};

$tests['select query flattening current next29 rejects malformed derived alias'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectFlatteningPlan::plan('SELECT option_name FROM (SELECT option_name FROM wp_options) AS 1bad'));
};

$tests['select query flattening current next29 rejects non select sql'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectFlatteningPlan::plan('DELETE FROM wp_options'));
};

return $tests;
