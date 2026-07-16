<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectCteFlattenMaterializePlan;

$tests = [];

$flattenable = [
    'single simple cte' => "WITH hot AS (SELECT option_id, option_name FROM wp_options WHERE autoload = 'yes') SELECT option_name FROM hot",
    'not materialized simple cte' => "WITH hot AS NOT MATERIALIZED (SELECT option_id, option_name FROM wp_options WHERE autoload = 'yes') SELECT option_name FROM hot",
    'column renamed simple cte' => "WITH hot(id, name) AS (SELECT option_id, option_name FROM wp_options) SELECT name FROM hot WHERE id > 2",
    'quoted literal containing name is ignored by keyword blockers' => "WITH hot AS (SELECT option_name FROM wp_options WHERE option_value = 'GROUP BY') SELECT option_name FROM hot",
    'subquery body remains flattenable when top level simple' => "WITH hot AS (SELECT option_id, option_name FROM wp_options WHERE option_id IN (SELECT option_id FROM option_meta)) SELECT option_name FROM hot",
    'join body without top-level aggregate' => "WITH hot AS (SELECT wp_options.option_id, option_name FROM wp_options JOIN option_meta ON option_meta.option_id = wp_options.option_id) SELECT option_name FROM hot",
    'left join body without top-level limit' => "WITH hot AS (SELECT wp_options.option_id, option_meta.source FROM wp_options LEFT JOIN option_meta ON option_meta.option_id = wp_options.option_id) SELECT source FROM hot",
    'case projection body' => "WITH hot AS (SELECT option_id, CASE WHEN autoload = 'yes' THEN option_name ELSE 'skip' END AS label FROM wp_options) SELECT label FROM hot",
    'scalar expression projection body' => "WITH hot AS (SELECT option_id + 10 AS shifted, option_name FROM wp_options) SELECT shifted FROM hot",
    'collated projection body' => "WITH hot AS (SELECT option_name COLLATE NOCASE AS name FROM wp_options) SELECT name FROM hot",
    'between predicate body' => "WITH hot AS (SELECT option_id, option_name FROM wp_options WHERE option_id BETWEEN 1 AND 4) SELECT option_name FROM hot",
    'glob predicate body' => "WITH hot AS (SELECT option_id, option_name FROM wp_options WHERE option_name GLOB 'site*') SELECT option_name FROM hot",
    'json scalar body' => "WITH hot AS (SELECT option_id, json_extract(option_value, '$.active') AS active FROM wp_options) SELECT active FROM hot",
    'qualified final reference' => "WITH hot AS (SELECT option_id, option_name FROM wp_options) SELECT hot.option_name FROM hot",
    'ordered outer select only' => "WITH hot AS (SELECT option_id, option_name FROM wp_options) SELECT option_name FROM hot ORDER BY option_id",
    'limited outer select only' => "WITH hot AS (SELECT option_id, option_name FROM wp_options) SELECT option_name FROM hot LIMIT 2",
    'where outer select only' => "WITH hot AS (SELECT option_id, option_name FROM wp_options) SELECT option_name FROM hot WHERE option_id > 1",
    'nested parentheses in expression' => "WITH hot AS (SELECT (option_id + (1)) AS id, option_name FROM wp_options) SELECT id FROM hot",
    'literal comma in body' => "WITH hot AS (SELECT option_id, 'a,b' AS marker FROM wp_options) SELECT marker FROM hot",
    'not materialized case projection' => "WITH hot AS NOT MATERIALIZED (SELECT option_id, CASE WHEN autoload = 'yes' THEN 1 ELSE 0 END AS enabled FROM wp_options) SELECT enabled FROM hot",
];

foreach ($flattenable as $name => $sql) {
    $tests['select cte flatten materialize current next35 flattenable ' . $name] = static function (TestRunner $t) use ($sql): void {
        $plan = SQLiteSelectCteFlattenMaterializePlan::plan($sql);
        $t->same(1, $plan['cteCount']);
        $t->same(['hot'], $plan['flattened']);
        $t->same([], $plan['materialized']);
        $t->same('flatten', $plan['ctes'][0]['decision']);
        $t->same('flattenable', $plan['ctes'][0]['reason']);
        $t->same([], $plan['ctes'][0]['blockers']);
    };
}

$blocked = [
    'materialized hint' => ["WITH hot AS MATERIALIZED (SELECT option_id FROM wp_options) SELECT option_id FROM hot", 'materialized-hint'],
    'multiple references' => ["WITH hot AS (SELECT option_id FROM wp_options) SELECT option_id FROM hot UNION ALL SELECT option_id FROM hot", 'multiple-references'],
    'unused cte' => ["WITH hot AS (SELECT option_id FROM wp_options) SELECT option_id FROM wp_options", 'unused'],
    'values body' => ["WITH hot(id, name) AS (VALUES (1, 'siteurl'), (2, 'home')) SELECT name FROM hot", 'values-body'],
    'distinct body' => ["WITH hot AS (SELECT DISTINCT autoload FROM wp_options) SELECT autoload FROM hot", 'distinct'],
    'group body' => ["WITH hot AS (SELECT autoload, count(*) AS n FROM wp_options GROUP BY autoload) SELECT autoload FROM hot", 'group-by'],
    'having body' => ["WITH hot AS (SELECT autoload FROM wp_options GROUP BY autoload HAVING count(*) > 1) SELECT autoload FROM hot", 'group-by'],
    'limit body' => ["WITH hot AS (SELECT option_id FROM wp_options LIMIT 2) SELECT option_id FROM hot", 'limit'],
    'compound body' => ["WITH hot AS (SELECT option_id FROM wp_options UNION SELECT option_id FROM option_meta) SELECT option_id FROM hot", 'union'],
    'window body' => ["WITH hot AS (SELECT row_number() OVER (ORDER BY option_id) AS rn FROM wp_options) SELECT rn FROM hot", 'window-function'],
    'recursive self reference' => ["WITH RECURSIVE hot(id) AS (SELECT 1 UNION ALL SELECT id + 1 FROM hot WHERE id < 3) SELECT id FROM hot", 'recursive'],
    'not materialized multiple references still materializes' => ["WITH hot AS NOT MATERIALIZED (SELECT option_id FROM wp_options) SELECT option_id FROM hot UNION ALL SELECT option_id FROM hot", 'multiple-references'],
    'not materialized distinct still materializes' => ["WITH hot AS NOT MATERIALIZED (SELECT DISTINCT autoload FROM wp_options) SELECT autoload FROM hot", 'distinct'],
    'not materialized limit still materializes' => ["WITH hot AS NOT MATERIALIZED (SELECT option_id FROM wp_options LIMIT 1) SELECT option_id FROM hot", 'limit'],
    'not materialized aggregate still materializes' => ["WITH hot AS NOT MATERIALIZED (SELECT count(*) AS n FROM wp_options) SELECT n FROM hot", 'aggregate'],
    'not materialized window still materializes' => ["WITH hot AS NOT MATERIALIZED (SELECT row_number() OVER (ORDER BY option_id) AS rn FROM wp_options) SELECT rn FROM hot", 'window-function'],
    'intersect body' => ["WITH hot AS (SELECT option_id FROM wp_options INTERSECT SELECT option_id FROM option_meta) SELECT option_id FROM hot", 'intersect'],
    'except body' => ["WITH hot AS (SELECT option_id FROM wp_options EXCEPT SELECT option_id FROM option_meta) SELECT option_id FROM hot", 'except'],
    'unused materialized hint prefers hint' => ["WITH hot AS MATERIALIZED (SELECT option_id FROM wp_options) SELECT 1", 'materialized-hint'],
    'avg aggregate body' => ["WITH hot AS (SELECT avg(option_id) AS n FROM wp_options) SELECT n FROM hot", 'aggregate'],
    'sum aggregate body' => ["WITH hot AS (SELECT sum(option_id) AS n FROM wp_options) SELECT n FROM hot", 'aggregate'],
    'min aggregate body' => ["WITH hot AS (SELECT min(option_id) AS n FROM wp_options) SELECT n FROM hot", 'aggregate'],
    'max aggregate body' => ["WITH hot AS (SELECT max(option_id) AS n FROM wp_options) SELECT n FROM hot", 'aggregate'],
    'group concat aggregate body' => ["WITH hot AS (SELECT group_concat(option_name) AS names FROM wp_options) SELECT names FROM hot", 'aggregate'],
    'json aggregate body' => ["WITH hot AS (SELECT json_group_array(option_name) AS names FROM wp_options) SELECT names FROM hot", 'aggregate'],
    'body reference from later cte creates extra reference' => ["WITH hot AS (SELECT option_id FROM wp_options), picked AS (SELECT option_id FROM hot) SELECT hot.option_id FROM hot JOIN picked ON picked.option_id = hot.option_id", 'multiple-references'],
];

foreach ($blocked as $name => [$sql, $reason]) {
    $tests['select cte flatten materialize current next35 materialized ' . $name] = static function (TestRunner $t) use ($sql, $reason): void {
        $plan = SQLiteSelectCteFlattenMaterializePlan::plan($sql);
        $t->true(in_array('hot', $plan['materialized'], true));
        $t->same('materialize', $plan['ctes'][0]['decision']);
        $t->same($reason, $plan['ctes'][0]['reason']);
        $t->true(in_array($reason, $plan['ctes'][0]['blockers'], true));
    };
}

$tests['select cte flatten materialize current next35 records chained decisions'] = static function (TestRunner $t): void {
    $plan = SQLiteSelectCteFlattenMaterializePlan::plan(
        "WITH base AS (SELECT option_id, autoload FROM wp_options), rollup AS MATERIALIZED (SELECT autoload, count(*) AS n FROM base GROUP BY autoload), picked AS NOT MATERIALIZED (SELECT option_id FROM base WHERE option_id > 1) SELECT option_id FROM picked",
    );
    $t->same(3, $plan['cteCount']);
    $t->same(['picked'], $plan['flattened']);
    $t->same(['base', 'rollup'], $plan['materialized']);
    $t->same('multiple-references', $plan['ctes'][0]['reason']);
    $t->same('materialized-hint', $plan['ctes'][1]['reason']);
    $t->same('flattenable', $plan['ctes'][2]['reason']);
    $t->same('rollup', $plan['ctes'][1]['name']);
    $t->same('MATERIALIZED', $plan['ctes'][1]['hint']);
    $t->same('NOT MATERIALIZED', $plan['ctes'][2]['hint']);
};

$tests['select cte flatten materialize current next35 preserves trailing main select'] = static function (TestRunner $t): void {
    $sql = "WITH hot AS NOT MATERIALIZED (SELECT option_id FROM wp_options WHERE autoload = 'yes') SELECT option_id FROM hot WHERE option_id > 1 ORDER BY option_id LIMIT 2";
    $plan = SQLiteSelectCteFlattenMaterializePlan::plan($sql);
    $t->same("SELECT option_id FROM hot WHERE option_id > 1 ORDER BY option_id LIMIT 2", $plan['mainSql']);
    $t->same(false, $plan['recursive']);
    $t->same('hot', $plan['ctes'][0]['name']);
    $t->same("SELECT option_id FROM wp_options WHERE autoload = 'yes'", $plan['ctes'][0]['sql']);
    $t->same(1, $plan['ctes'][0]['references']);
};

$tests['select cte flatten materialize current next35 reports recursive keyword'] = static function (TestRunner $t): void {
    $plan = SQLiteSelectCteFlattenMaterializePlan::plan("WITH RECURSIVE seed AS (SELECT option_id FROM wp_options) SELECT option_id FROM seed");
    $t->same(true, $plan['recursive']);
    $t->same(['seed'], $plan['flattened']);
    $t->same([], $plan['materialized']);
    $t->same('flatten', $plan['ctes'][0]['decision']);
    $t->same(1, $plan['ctes'][0]['references']);
};

$tests['select cte flatten materialize current next35 rejects malformed statements'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectCteFlattenMaterializePlan::plan('SELECT option_id FROM wp_options'));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectCteFlattenMaterializePlan::plan('WITH bad AS (DELETE FROM wp_options) SELECT * FROM bad'));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectCteFlattenMaterializePlan::plan('WITH bad AS SELECT option_id FROM wp_options SELECT * FROM bad'));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectCteFlattenMaterializePlan::plan('WITH bad() AS (SELECT option_id FROM wp_options) SELECT * FROM bad'));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectCteFlattenMaterializePlan::plan('WITH bad(1bad) AS (SELECT option_id FROM wp_options) SELECT * FROM bad'));
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectCteFlattenMaterializePlan::plan('WITH bad AS (SELECT option_id FROM wp_options) DELETE FROM bad'));
};

return $tests;
