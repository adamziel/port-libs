<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'option_value' => '{"links":[2,3],"kind":"root","weight":10}'],
    ['option_id' => 2, 'option_name' => 'blogname', 'autoload' => 'yes', 'option_value' => '{"links":[3],"kind":"branch","weight":20}'],
    ['option_id' => 3, 'option_name' => 'theme_mods', 'autoload' => 'no', 'option_value' => '{"links":[],"kind":"leaf","weight":30}'],
    ['option_id' => 4, 'option_name' => 'orphan', 'autoload' => 'no', 'option_value' => '{"links":[1],"kind":"cycle","weight":40}'],
];

$tables = [
    'wp_options' => $options,
    'wp_sites' => [
        ['site_id' => 10, 'label' => 'main'],
        ['site_id' => 11, 'label' => 'network'],
    ],
];

$walkSql = <<<'SQL'
WITH RECURSIVE walk(id, depth) AS (
    VALUES (1, 0)
    UNION
    SELECT CAST(j.value AS INTEGER), walk.depth + 1
    FROM walk
    JOIN wp_options ON wp_options.option_id = walk.id
    JOIN json_each(wp_options.option_value, '$.links') AS j
    WHERE walk.depth < 2
)
SQL;

$run = static fn (string $selectSql, array $inputTables = null): array => SQLiteSelectSql::execute($selectSql, $inputTables ?? $tables);

$cases = [
    'plain join without on json rows' => [
        "SELECT option_id, j.value AS linked FROM wp_options JOIN json_each(option_value, '$.links') AS j ORDER BY option_id, linked",
        [
            ['option_id' => 1, 'linked' => 2],
            ['option_id' => 1, 'linked' => 3],
            ['option_id' => 2, 'linked' => 3],
            ['option_id' => 4, 'linked' => 1],
        ],
    ],
    'inner join without on json rows' => [
        "SELECT option_id, j.key AS idx, j.value AS linked FROM wp_options INNER JOIN json_each(option_value, '$.links') AS j ORDER BY option_id, idx",
        [
            ['option_id' => 1, 'idx' => 0, 'linked' => 2],
            ['option_id' => 1, 'idx' => 1, 'linked' => 3],
            ['option_id' => 2, 'idx' => 0, 'linked' => 3],
            ['option_id' => 4, 'idx' => 0, 'linked' => 1],
        ],
    ],
    'left join without on null extends empty json rows' => [
        "SELECT option_id, j.value AS linked FROM wp_options LEFT JOIN json_each(option_value, '$.links') AS j ORDER BY option_id, linked",
        [
            ['option_id' => 1, 'linked' => 2],
            ['option_id' => 1, 'linked' => 3],
            ['option_id' => 2, 'linked' => 3],
            ['option_id' => 3, 'linked' => null],
            ['option_id' => 4, 'linked' => 1],
        ],
    ],
    'plain join without on ordinary cartesian rows' => [
        'SELECT option_id, site_id FROM wp_options JOIN wp_sites ORDER BY option_id, site_id LIMIT 5',
        [
            ['option_id' => 1, 'site_id' => 10],
            ['option_id' => 1, 'site_id' => 11],
            ['option_id' => 2, 'site_id' => 10],
            ['option_id' => 2, 'site_id' => 11],
            ['option_id' => 3, 'site_id' => 10],
        ],
    ],
    'recursive json join without on emits current and next rows' => [
        $walkSql . ' SELECT id, depth FROM walk ORDER BY depth, id',
        [
            ['id' => 1, 'depth' => 0],
            ['id' => 2, 'depth' => 1],
            ['id' => 3, 'depth' => 1],
            ['id' => 3, 'depth' => 2],
        ],
    ],
    'recursive json join feeds row number window' => [
        $walkSql . ' SELECT id, depth, row_number() OVER (ORDER BY depth, id) AS rn FROM walk ORDER BY depth, id',
        [
            ['id' => 1, 'depth' => 0, 'rn' => 1],
            ['id' => 2, 'depth' => 1, 'rn' => 2],
            ['id' => 3, 'depth' => 1, 'rn' => 3],
            ['id' => 3, 'depth' => 2, 'rn' => 4],
        ],
    ],
    'recursive json join feeds partitioned rank window' => [
        $walkSql . ' SELECT id, depth, rank() OVER (PARTITION BY depth ORDER BY id) AS r FROM walk ORDER BY depth, id',
        [
            ['id' => 1, 'depth' => 0, 'r' => 1],
            ['id' => 2, 'depth' => 1, 'r' => 1],
            ['id' => 3, 'depth' => 1, 'r' => 2],
            ['id' => 3, 'depth' => 2, 'r' => 1],
        ],
    ],
    'recursive json join feeds lead window' => [
        $walkSql . " SELECT id, lead(id, 1, -1) OVER (ORDER BY depth, id) AS next_id FROM walk ORDER BY depth, id",
        [
            ['id' => 1, 'next_id' => 2],
            ['id' => 2, 'next_id' => 3],
            ['id' => 3, 'next_id' => 3],
            ['id' => 3, 'next_id' => -1],
        ],
    ],
    'recursive json join feeds lag window' => [
        $walkSql . " SELECT id, lag(id, 1, 0) OVER (ORDER BY depth, id) AS prev_id FROM walk ORDER BY depth, id",
        [
            ['id' => 1, 'prev_id' => 0],
            ['id' => 2, 'prev_id' => 1],
            ['id' => 3, 'prev_id' => 2],
            ['id' => 3, 'prev_id' => 3],
        ],
    ],
    'recursive json join feeds framed count current next' => [
        $walkSql . ' SELECT id, count(*) OVER (ORDER BY depth, id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS frame_count FROM walk ORDER BY depth, id',
        [
            ['id' => 1, 'frame_count' => 2],
            ['id' => 2, 'frame_count' => 2],
            ['id' => 3, 'frame_count' => 2],
            ['id' => 3, 'frame_count' => 1],
        ],
    ],
    'recursive json join feeds framed sum current next' => [
        $walkSql . ' SELECT id, sum(id) OVER (ORDER BY depth, id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS frame_sum FROM walk ORDER BY depth, id',
        [
            ['id' => 1, 'frame_sum' => 3],
            ['id' => 2, 'frame_sum' => 5],
            ['id' => 3, 'frame_sum' => 6],
            ['id' => 3, 'frame_sum' => 3],
        ],
    ],
    'recursive json join feeds exclude current frame' => [
        $walkSql . ' SELECT id, count(*) OVER (ORDER BY depth, id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING EXCLUDE CURRENT ROW) AS peers FROM walk ORDER BY depth, id',
        [
            ['id' => 1, 'peers' => 1],
            ['id' => 2, 'peers' => 1],
            ['id' => 3, 'peers' => 1],
            ['id' => 3, 'peers' => 0],
        ],
    ],
];

foreach ($cases as $name => [$sql, $expected]) {
    $tests['select recursive json window current next50 ' . $name] = static function (TestRunner $t) use ($run, $sql, $expected): void {
        $t->same($expected, $run($sql));
    };
}

$metricSql = $walkSql . <<<'SQL'
 SELECT
    id,
    depth,
    row_number() OVER (ORDER BY depth, id) AS rn,
    dense_rank() OVER (ORDER BY depth) AS depth_rank,
    percent_rank() OVER (ORDER BY depth, id) AS pct,
    cume_dist() OVER (ORDER BY depth, id) AS cume,
    ntile(3) OVER (ORDER BY depth, id) AS tile,
    first_value(id) OVER (ORDER BY depth, id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS first_id,
    last_value(id) OVER (ORDER BY depth, id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS last_id,
    nth_value(id, 2) OVER (ORDER BY depth, id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS nth_id,
    group_concat(id) OVER (ORDER BY depth, id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS concat_ids
 FROM walk
 ORDER BY depth, id
SQL;

$metricRows = static fn (): array => SQLiteSelectSql::execute($metricSql, $tables);
$metricExpectations = [
    'metric row count' => static fn (array $rows): mixed => count($rows),
    'first row id' => static fn (array $rows): mixed => $rows[0]['id'],
    'first row depth' => static fn (array $rows): mixed => $rows[0]['depth'],
    'first row number' => static fn (array $rows): mixed => $rows[0]['rn'],
    'second row number' => static fn (array $rows): mixed => $rows[1]['rn'],
    'third row number' => static fn (array $rows): mixed => $rows[2]['rn'],
    'fourth row number' => static fn (array $rows): mixed => $rows[3]['rn'],
    'depth dense ranks' => static fn (array $rows): mixed => array_column($rows, 'depth_rank'),
    'percent rank first' => static fn (array $rows): mixed => $rows[0]['pct'],
    'percent rank last' => static fn (array $rows): mixed => $rows[3]['pct'],
    'cume dist first' => static fn (array $rows): mixed => $rows[0]['cume'],
    'cume dist middle peer' => static fn (array $rows): mixed => $rows[2]['cume'],
    'cume dist last' => static fn (array $rows): mixed => $rows[3]['cume'],
    'ntile sequence' => static fn (array $rows): mixed => array_column($rows, 'tile'),
    'first value frame sequence' => static fn (array $rows): mixed => array_column($rows, 'first_id'),
    'last value frame sequence' => static fn (array $rows): mixed => array_column($rows, 'last_id'),
    'nth value frame sequence' => static fn (array $rows): mixed => array_column($rows, 'nth_id'),
    'group concat frame sequence' => static fn (array $rows): mixed => array_column($rows, 'concat_ids'),
    'ids remain ordered after windows' => static fn (array $rows): mixed => array_column($rows, 'id'),
    'depths remain ordered after windows' => static fn (array $rows): mixed => array_column($rows, 'depth'),
];
$metricExpected = [
    'metric row count' => 4,
    'first row id' => 1,
    'first row depth' => 0,
    'first row number' => 1,
    'second row number' => 2,
    'third row number' => 3,
    'fourth row number' => 4,
    'depth dense ranks' => [1, 2, 2, 3],
    'percent rank first' => 0.0,
    'percent rank last' => 1.0,
    'cume dist first' => 0.25,
    'cume dist middle peer' => 0.75,
    'cume dist last' => 1.0,
    'ntile sequence' => [1, 1, 2, 3],
    'first value frame sequence' => [1, 2, 3, 3],
    'last value frame sequence' => [2, 3, 3, 3],
    'nth value frame sequence' => [2, 3, 3, null],
    'group concat frame sequence' => ['1,2', '2,3', '3,3', '3'],
    'ids remain ordered after windows' => [1, 2, 3, 3],
    'depths remain ordered after windows' => [0, 1, 1, 2],
];

foreach ($metricExpectations as $name => $reader) {
    $tests['select recursive json window current next50 ' . $name] = static function (TestRunner $t) use ($metricRows, $reader, $metricExpected, $name): void {
        $t->same($metricExpected[$name], $reader($metricRows()));
    };
}

$trace = static fn (): array => SQLiteSelectSql::recursiveCteCycleTrace(
    $walkSql . ' SELECT id, depth FROM walk ORDER BY depth, id',
    $tables,
);

$traceExpectations = [
    'trace names recursive table' => static fn (array $trace): mixed => $trace['name'],
    'trace uses union operator' => static fn (array $trace): mixed => $trace['operator'],
    'trace columns include depth' => static fn (array $trace): mixed => $trace['columns'],
    'trace rows include depth duplicate' => static fn (array $trace): mixed => $trace['rows'],
    'trace records first current row' => static fn (array $trace): mixed => $trace['trace'][0]['current'],
    'trace first accepted json next rows' => static fn (array $trace): mixed => $trace['trace'][0]['accepted_next'],
    'trace second current row' => static fn (array $trace): mixed => $trace['trace'][1]['current'],
    'trace second accepted json next row' => static fn (array $trace): mixed => $trace['trace'][1]['accepted_next'],
    'trace third current row' => static fn (array $trace): mixed => $trace['trace'][2]['current'],
    'trace skips duplicate no-depth row' => static fn (array $trace): mixed => $trace['trace'][2]['skipped_duplicates'],
    'trace queue empties after final row' => static fn (array $trace): mixed => $trace['trace'][3]['queue_after'],
    'trace dependency markers' => static fn (array $trace): mixed => $trace['dependencies'],
];
$traceExpected = [
    'trace names recursive table' => 'walk',
    'trace uses union operator' => 'UNION',
    'trace columns include depth' => ['id', 'depth'],
    'trace rows include depth duplicate' => [
        ['id' => 1, 'depth' => 0],
        ['id' => 2, 'depth' => 1],
        ['id' => 3, 'depth' => 1],
        ['id' => 3, 'depth' => 2],
    ],
    'trace records first current row' => ['id' => 1, 'depth' => 0],
    'trace first accepted json next rows' => [
        ['id' => 2, 'depth' => 1],
        ['id' => 3, 'depth' => 1],
    ],
    'trace second current row' => ['id' => 2, 'depth' => 1],
    'trace second accepted json next row' => [
        ['id' => 3, 'depth' => 2],
    ],
    'trace third current row' => ['id' => 3, 'depth' => 1],
    'trace skips duplicate no-depth row' => [],
    'trace queue empties after final row' => [],
    'trace dependency markers' => ['sqlite-recursive-cte-current-row', 'sqlite-recursive-union-cycle-dedup'],
];

foreach ($traceExpectations as $name => $reader) {
    $tests['select recursive json window current next50 ' . $name] = static function (TestRunner $t) use ($trace, $reader, $traceExpected, $name): void {
        $t->same($traceExpected[$name], $reader($trace()));
    };
}

$guardCases = [
    'cross join still rejects on clause' => "SELECT option_id FROM wp_options CROSS JOIN json_each(option_value, '$.links') AS j ON j.value = option_id",
    'join without table remains malformed' => 'SELECT option_id FROM wp_options JOIN ORDER BY option_id',
    'malformed json argument still fails without guard' => "SELECT option_id FROM wp_options JOIN json_each('not json') AS j",
    'recursive cte still rejects non union recursion' => 'WITH RECURSIVE walk(id) AS (VALUES (1) INTERSECT SELECT id FROM walk) SELECT id FROM walk',
    'window filter still rejects ranking function' => $walkSql . ' SELECT row_number() FILTER (WHERE id > 1) OVER (ORDER BY id) AS rn FROM walk',
    'window frame still rejects lag frame' => $walkSql . ' SELECT lag(id) OVER (ORDER BY id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS previous FROM walk',
];

foreach ($guardCases as $name => $sql) {
    $tests['select recursive json window current next50 rejects ' . $name] = static function (TestRunner $t) use ($sql, $tables): void {
        $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteSelectSql::execute($sql, $tables));
    };
}

return $tests;
