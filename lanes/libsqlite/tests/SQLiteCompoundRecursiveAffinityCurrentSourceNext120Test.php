<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteSelectSql;

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => '1', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => '1.0', 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'blogname', 'option_value' => '01', 'autoload' => 'no'],
    ['option_id' => 4, 'option_name' => 'active_plugins', 'option_value' => 'plugin', 'autoload' => 'yes'],
];

$tables = ['wp_options' => $options];

$normalize = null;
$normalize = static function (mixed $value) use (&$normalize): mixed {
    if ($value instanceof SQLiteBlobValue) {
        return ['blob' => bin2hex($value->bytes)];
    }
    if (is_array($value)) {
        return array_map(static fn (mixed $item): mixed => $normalize($item), $value);
    }

    return $value;
};

$rows = static fn (string $sql): array => SQLiteSelectSql::execute($sql, $tables);
$column = static fn (string $sql, string $column = 'v'): array => array_column($rows($sql), $column);
$trace = static fn (string $sql): array => SQLiteSelectSql::recursiveCteCycleTrace($sql, $tables);

$cases = [
    'recursive union treats integer and real duplicates as one row' => [
        "WITH RECURSIVE seq(v) AS (VALUES (1) UNION SELECT v + 0.0 FROM seq WHERE v = 1) SELECT v FROM seq",
        [1],
    ],
    'recursive union all keeps integer and real numeric rows' => [
        "WITH RECURSIVE seq(v) AS (VALUES (1) UNION ALL SELECT v + 0.0 FROM seq WHERE v = 1 LIMIT 2) SELECT v FROM seq",
        [1, 1.0],
    ],
    'recursive union keeps text one distinct from numeric one' => [
        "WITH RECURSIVE seq(v) AS (VALUES (1) UNION SELECT CAST(v AS TEXT) FROM seq WHERE typeof(v) = 'integer') SELECT v FROM seq ORDER BY typeof(v)",
        [1, '1'],
    ],
    'recursive union keeps blob one distinct from text one' => [
        "WITH RECURSIVE seq(v) AS (VALUES (CAST('1' AS TEXT)) UNION SELECT CAST(v AS BLOB) FROM seq WHERE typeof(v) = 'text') SELECT v FROM seq ORDER BY typeof(v)",
        [new SQLiteBlobValue('1'), '1'],
    ],
    'recursive union deduplicates generated real before current source union' => [
        "WITH RECURSIVE seq(v) AS (VALUES (1) UNION SELECT v + 0.0 FROM seq WHERE v = 1) SELECT v FROM seq UNION SELECT option_id FROM wp_options WHERE option_id = 1 ORDER BY v",
        [1],
    ],
    'recursive union all duplicate real is removed by outer current source union' => [
        "WITH RECURSIVE seq(v) AS (VALUES (1) UNION ALL SELECT v + 0.0 FROM seq WHERE v = 1 LIMIT 2) SELECT v FROM seq UNION SELECT option_id FROM wp_options WHERE option_id = 1 ORDER BY v",
        [1],
    ],
    'recursive numeric duplicate intersects current integer source' => [
        "WITH RECURSIVE seq(v) AS (VALUES (1.0) UNION SELECT v + 0 FROM seq WHERE v = 1) SELECT v FROM seq INTERSECT SELECT option_id FROM wp_options WHERE option_id = 1",
        [1.0],
    ],
    'recursive numeric duplicate except removes current integer source' => [
        "WITH RECURSIVE seq(v) AS (VALUES (1.0) UNION SELECT v + 0 FROM seq WHERE v = 1) SELECT v FROM seq EXCEPT SELECT option_id FROM wp_options WHERE option_id = 1",
        [],
    ],
    'recursive text one does not intersect current integer source' => [
        "WITH RECURSIVE seq(v) AS (VALUES (CAST(1 AS TEXT)) UNION SELECT v FROM seq WHERE 0) SELECT v FROM seq INTERSECT SELECT option_id FROM wp_options WHERE option_id = 1",
        [],
    ],
    'recursive text one except keeps text against current integer source' => [
        "WITH RECURSIVE seq(v) AS (VALUES (CAST(1 AS TEXT)) UNION SELECT v FROM seq WHERE 0) SELECT v FROM seq EXCEPT SELECT option_id FROM wp_options WHERE option_id = 1",
        ['1'],
    ],
    'recursive real and current text stay distinct under union' => [
        "WITH RECURSIVE seq(v) AS (VALUES (1.0) UNION SELECT v FROM seq WHERE 0) SELECT v FROM seq UNION SELECT option_value FROM wp_options WHERE option_name = 'siteurl'",
        [1.0, '1'],
    ],
    'recursive text and current real-like text stay distinct from numeric arm' => [
        "WITH RECURSIVE seq(v) AS (VALUES (1) UNION SELECT CAST(v AS TEXT) FROM seq WHERE typeof(v) = 'integer') SELECT v FROM seq UNION SELECT option_value FROM wp_options WHERE option_name = 'home'",
        [1, '1', '1.0'],
    ],
    'recursive null duplicate is skipped before current source union' => [
        "WITH RECURSIVE seq(v) AS (VALUES (NULL) UNION SELECT NULL FROM seq WHERE v IS NULL) SELECT v FROM seq UNION SELECT NULL AS v FROM wp_options WHERE option_id = 1",
        [null],
    ],
    'recursive null intersects current null source' => [
        "WITH RECURSIVE seq(v) AS (VALUES (NULL) UNION SELECT NULL FROM seq WHERE v IS NULL) SELECT v FROM seq INTERSECT SELECT NULL AS v FROM wp_options WHERE option_id = 1",
        [null],
    ],
    'recursive null except removes current null source' => [
        "WITH RECURSIVE seq(v) AS (VALUES (NULL) UNION SELECT NULL FROM seq WHERE v IS NULL) SELECT v FROM seq EXCEPT SELECT NULL AS v FROM wp_options WHERE option_id = 1",
        [],
    ],
    'recursive two column union deduplicates numeric columns together' => [
        "WITH RECURSIVE seq(v, w) AS (VALUES (1, 2.0) UNION SELECT v + 0.0, w + 0 FROM seq WHERE v = 1) SELECT v || ':' || w AS v FROM seq",
        ['1:2'],
    ],
    'recursive two column union keeps text mismatch distinct' => [
        "WITH RECURSIVE seq(v, w) AS (VALUES (1, '2') UNION SELECT v + 0.0, 2 FROM seq WHERE typeof(w) = 'text') SELECT typeof(v) || ':' || typeof(w) AS v FROM seq ORDER BY v",
        ['integer:text', 'real:integer'],
    ],
    'recursive numeric duplicate skipped before queue limit decrements again' => [
        "WITH RECURSIVE seq(v) AS (VALUES (1) UNION SELECT v + 0.0 FROM seq WHERE v = 1 LIMIT 5) SELECT v FROM seq",
        [1],
    ],
    'recursive alternating integer real cycle terminates under union' => [
        "WITH RECURSIVE seq(v) AS (VALUES (1) UNION SELECT CASE typeof(v) WHEN 'integer' THEN v + 0.0 ELSE CAST(v AS INTEGER) END FROM seq WHERE v = 1) SELECT v FROM seq",
        [1],
    ],
    'recursive text cast cycle keeps only numeric and text representatives' => [
        "WITH RECURSIVE seq(v) AS (VALUES (1) UNION SELECT CASE typeof(v) WHEN 'integer' THEN CAST(v AS TEXT) ELSE CAST(v AS INTEGER) END FROM seq WHERE v = 1 OR v = '1') SELECT v FROM seq ORDER BY typeof(v)",
        [1, '1'],
    ],
    'recursive numeric duplicate can still feed outer union all current row' => [
        "WITH RECURSIVE seq(v) AS (VALUES (1) UNION SELECT v + 0.0 FROM seq WHERE v = 1) SELECT v FROM seq UNION ALL SELECT option_id FROM wp_options WHERE option_id = 1 ORDER BY v",
        [1, 1],
    ],
    'recursive real representative is preserved when left anchor is real' => [
        "WITH RECURSIVE seq(v) AS (VALUES (1.0) UNION SELECT CAST(v AS INTEGER) FROM seq WHERE v = 1) SELECT v FROM seq UNION SELECT option_id FROM wp_options WHERE option_id = 1",
        [1.0],
    ],
    'recursive integer representative is preserved when left anchor is integer' => [
        "WITH RECURSIVE seq(v) AS (VALUES (1) UNION SELECT v + 0.0 FROM seq WHERE v = 1) SELECT v FROM seq",
        [1],
    ],
    'recursive numeric duplicate keeps current source text under except' => [
        "WITH RECURSIVE seq(v) AS (VALUES (1) UNION SELECT v + 0.0 FROM seq WHERE v = 1) SELECT option_value AS v FROM wp_options WHERE option_name = 'siteurl' EXCEPT SELECT v FROM seq",
        ['1'],
    ],
    'recursive numeric duplicate removes current source real under except' => [
        "WITH RECURSIVE seq(v) AS (VALUES (1) UNION SELECT v + 0.0 FROM seq WHERE v = 1) SELECT 1.0 AS v FROM wp_options WHERE option_id = 1 EXCEPT SELECT v FROM seq",
        [],
    ],
];

$tests = [];

foreach ($cases as $name => [$sql, $expected]) {
    $tests['compound recursive affinity current source next120 ' . $name] = static function (TestRunner $t) use ($column, $sql, $expected, $normalize): void {
        $t->same($normalize($expected), $normalize($column($sql)));
    };
}

$tests['compound recursive affinity current source next120 trace skips integer real union duplicate'] = static function (TestRunner $t) use ($trace): void {
    $result = $trace("WITH RECURSIVE seq(v) AS (VALUES (1) UNION SELECT v + 0.0 FROM seq WHERE v = 1) SELECT v FROM seq");
    $t->same([1], array_column($result['rows'], 'v'));
    $t->same([['v' => 1.0]], $result['trace'][0]['skipped_duplicates']);
    $t->same([['v' => 1.0]], array_map(static fn (array $row): array => $row['row'], $result['skipped']));
};

$tests['compound recursive affinity current source next120 trace keeps text duplicate distinct'] = static function (TestRunner $t) use ($trace): void {
    $result = $trace("WITH RECURSIVE seq(v) AS (VALUES (1) UNION SELECT CAST(v AS TEXT) FROM seq WHERE typeof(v) = 'integer') SELECT v FROM seq");
    $t->same([1, '1'], array_column($result['rows'], 'v'));
    $t->same([], $result['trace'][0]['skipped_duplicates']);
};

$tests['compound recursive affinity current source next120 trace dependencies name compound duplicate semantics'] = static function (TestRunner $t) use ($trace): void {
    $result = $trace("WITH RECURSIVE seq(v) AS (VALUES (1) UNION SELECT v + 0.0 FROM seq WHERE v = 1) SELECT v FROM seq");
    $t->true(in_array('sqlite-recursive-cte-current-row', $result['dependencies'], true));
    $t->true(in_array('sqlite-recursive-union-cycle-dedup', $result['dependencies'], true));
};

foreach (range(1, 18) as $limit) {
    $tests['compound recursive affinity current source next120 generated numeric duplicate limit ' . $limit] = static function (TestRunner $t) use ($column, $limit): void {
        $sql = "WITH RECURSIVE seq(v) AS (VALUES (1) UNION SELECT v + 0.0 FROM seq WHERE v = 1 LIMIT {$limit}) SELECT v FROM seq";
        $t->same([1], $column($sql));
    };
}

return $tests;
