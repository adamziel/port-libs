<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'bytes' => 20, 'scope' => 'core'],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'bytes' => 20, 'scope' => 'core'],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'bytes' => 12, 'scope' => 'theme'],
    ['option_id' => 4, 'option_name' => '_transient_feed', 'autoload' => 'no', 'bytes' => 12, 'scope' => 'cache'],
    ['option_id' => 5, 'option_name' => '_site_transient_update_plugins', 'autoload' => 'no', 'bytes' => 12, 'scope' => 'cache'],
    ['option_id' => 6, 'option_name' => 'orphaned', 'autoload' => null, 'bytes' => null, 'scope' => 'orphan'],
    ['option_id' => 7, 'option_name' => 'binary_a', 'autoload' => 'no', 'bytes' => 2, 'scope' => 'media'],
    ['option_id' => 8, 'option_name' => 'binary_b', 'autoload' => 'no', 'bytes' => 2, 'scope' => 'media'],
];

$meta = [
    ['option_id' => 1, 'meta_key' => 'kind', 'weight' => 10],
    ['option_id' => 2, 'meta_key' => 'kind', 'weight' => 10],
    ['option_id' => 3, 'meta_key' => 'kind', 'weight' => 30],
    ['option_id' => 4, 'meta_key' => 'cache', 'weight' => 5],
    ['option_id' => 5, 'meta_key' => 'cache', 'weight' => 5],
    ['option_id' => 7, 'meta_key' => 'media', 'weight' => 2],
    ['option_id' => 8, 'meta_key' => 'media', 'weight' => 2],
];

$run = static fn (string $sql, array $parameters = [], array $extraTables = []): array => SQLiteSelectSql::execute(
    $sql,
    ['wp_options' => $options, 'option_meta' => $meta] + $extraTables,
    $parameters,
);

$tests = [];

$cases = [
    'count wildcard having true returns one row' => [
        'SELECT count(*) AS rows FROM wp_options HAVING count(*) = 8',
        [['rows' => 8]],
    ],
    'count wildcard having false suppresses row' => [
        'SELECT count(*) AS rows FROM wp_options HAVING count(*) > 20',
        [],
    ],
    'count value excludes nulls in implicit group' => [
        'SELECT count(bytes) AS rows FROM wp_options HAVING count(bytes) = 7',
        [['rows' => 7]],
    ],
    'sum having threshold' => [
        'SELECT sum(bytes) AS total FROM wp_options HAVING sum(bytes) = 80',
        [['total' => 80]],
    ],
    'total having real zero for empty filtered rows' => [
        'SELECT total(bytes) AS total FROM wp_options WHERE option_name = \'missing\' HAVING total(bytes) = 0.0',
        [['total' => 0.0]],
    ],
    'count wildcard empty rowset remains one aggregate row' => [
        'SELECT count(*) AS rows FROM wp_options WHERE option_name = \'missing\' HAVING count(*) = 0',
        [['rows' => 0]],
    ],
    'sum empty rowset remains null and can pass is null' => [
        'SELECT sum(bytes) AS total FROM wp_options WHERE option_name = \'missing\' HAVING sum(bytes) IS NULL',
        [['total' => null]],
    ],
    'sum empty rowset suppressed by numeric comparison' => [
        'SELECT sum(bytes) AS total FROM wp_options WHERE option_name = \'missing\' HAVING sum(bytes) > 0',
        [],
    ],
    'avg having comparison' => [
        'SELECT avg(bytes) AS average FROM wp_options HAVING avg(bytes) > 10',
        [['average' => 11.428571428571429]],
    ],
    'min having comparison' => [
        'SELECT min(bytes) AS smallest FROM wp_options HAVING min(bytes) = 2',
        [['smallest' => 2]],
    ],
    'max having comparison' => [
        'SELECT max(bytes) AS largest FROM wp_options HAVING max(bytes) = 20',
        [['largest' => 20]],
    ],
    'group concat having comparison' => [
        'SELECT group_concat(scope) AS scopes FROM wp_options WHERE option_id <= 3 HAVING group_concat(scope) = \'core|core|theme\'',
        [['scopes' => 'core|core|theme']],
    ],
    'having uses aggregate not projected' => [
        'SELECT count(*) AS rows FROM wp_options HAVING sum(bytes) = 80',
        [['rows' => 8]],
    ],
    'having aggregate expression plus count' => [
        'SELECT sum(bytes) AS total FROM wp_options HAVING sum(bytes) + count(bytes) = 87',
        [['total' => 80]],
    ],
    'having aggregate expression minus count' => [
        'SELECT sum(bytes) AS total FROM wp_options HAVING sum(bytes) - count(bytes) = 73',
        [['total' => 80]],
    ],
    'having aggregate expression times count' => [
        'SELECT count(bytes) AS rows FROM wp_options HAVING count(bytes) * 2 = 14',
        [['rows' => 7]],
    ],
    'having aggregate expression division' => [
        'SELECT sum(bytes) AS total FROM wp_options HAVING sum(bytes) / count(bytes) = 11',
        [],
    ],
    'having aggregate expression modulo' => [
        'SELECT sum(bytes) AS total FROM wp_options HAVING sum(bytes) % count(bytes) = 3',
        [['total' => 80]],
    ],
    'having between aggregate' => [
        'SELECT count(*) AS rows FROM wp_options HAVING count(*) BETWEEN 5 AND 10',
        [['rows' => 8]],
    ],
    'having not between aggregate' => [
        'SELECT count(*) AS rows FROM wp_options HAVING count(*) NOT BETWEEN 5 AND 10',
        [],
    ],
    'having aggregate in literal list' => [
        'SELECT count(bytes) AS rows FROM wp_options HAVING count(bytes) IN (1, 7, 9)',
        [['rows' => 7]],
    ],
    'having aggregate not in literal list' => [
        'SELECT count(bytes) AS rows FROM wp_options HAVING count(bytes) NOT IN (7, 8)',
        [],
    ],
    'where feeds implicit aggregate' => [
        'SELECT sum(bytes) AS total FROM wp_options WHERE autoload = \'no\' HAVING sum(bytes) = 28',
        [['total' => 28]],
    ],
    'where with null aggregate input' => [
        'SELECT count(bytes) AS rows FROM wp_options WHERE autoload IS NULL HAVING count(*) = 1 AND count(bytes) = 0',
        [['rows' => 0]],
    ],
    'where with glob feeds implicit aggregate' => [
        'SELECT count(*) AS rows FROM wp_options WHERE option_name GLOB \'_*\' HAVING count(*) = 2',
        [['rows' => 2]],
    ],
    'where with like feeds implicit aggregate' => [
        'SELECT sum(bytes) AS total FROM wp_options WHERE option_name LIKE \'%transient%\' HAVING sum(bytes) = 24',
        [['total' => 24]],
    ],
    'joined source implicit aggregate count' => [
        'SELECT count(*) AS rows FROM wp_options JOIN option_meta AS m ON wp_options.option_id = m.option_id HAVING count(*) = 7',
        [['rows' => 7]],
    ],
    'joined source implicit aggregate sum' => [
        'SELECT sum(m.weight) AS total FROM wp_options JOIN option_meta AS m ON wp_options.option_id = m.option_id HAVING sum(m.weight) = 64',
        [['total' => 64]],
    ],
    'left join implicit aggregate includes unmatched row' => [
        'SELECT count(*) AS rows FROM wp_options LEFT JOIN option_meta AS m ON wp_options.option_id = m.option_id HAVING count(*) = 8',
        [['rows' => 8]],
    ],
    'left join count value skips unmatched right column' => [
        'SELECT count(m.weight) AS rows FROM wp_options LEFT JOIN option_meta AS m ON wp_options.option_id = m.option_id HAVING count(m.weight) = 7',
        [['rows' => 7]],
    ],
    'cross join implicit aggregate multiplies rows' => [
        'SELECT count(*) AS rows FROM wp_options CROSS JOIN flags HAVING count(*) = 16',
        [['rows' => 16]],
        [],
        ['flags' => [['flag' => 'a'], ['flag' => 'b']]],
    ],
    'cte implicit aggregate' => [
        'WITH seed(name, bytes) AS (VALUES (\'a\', 4), (\'b\', 5), (\'c\', NULL)) SELECT count(bytes) AS rows FROM seed HAVING count(*) = 3 AND count(bytes) = 2',
        [['rows' => 2]],
    ],
    'cte empty implicit aggregate' => [
        'WITH seed(name, bytes) AS (VALUES (\'a\', 4)) SELECT count(*) AS rows FROM seed WHERE name = \'missing\' HAVING count(*) = 0',
        [['rows' => 0]],
    ],
    'bound having threshold named' => [
        'SELECT sum(bytes) AS total FROM wp_options HAVING sum(bytes) > :minimum',
        [['total' => 80]],
        [':minimum' => 70],
    ],
    'bound having threshold positional' => [
        'SELECT count(*) AS rows FROM wp_options HAVING count(*) = ?1',
        [['rows' => 8]],
        [1 => 8],
    ],
    'order by aggregate output after implicit having' => [
        'SELECT sum(bytes) AS total FROM wp_options HAVING sum(bytes) = 80 ORDER BY total DESC',
        [['total' => 80]],
    ],
    'order by hidden aggregate expression after implicit having' => [
        'SELECT count(*) AS rows FROM wp_options HAVING count(*) = 8 ORDER BY 1',
        [['rows' => 8]],
    ],
    'limit keeps implicit aggregate row' => [
        'SELECT count(*) AS rows FROM wp_options HAVING count(*) = 8 LIMIT 1',
        [['rows' => 8]],
    ],
    'offset skips implicit aggregate row' => [
        'SELECT count(*) AS rows FROM wp_options HAVING count(*) = 8 LIMIT 1 OFFSET 1',
        [],
    ],
    'comma limit skips implicit aggregate row' => [
        'SELECT count(*) AS rows FROM wp_options HAVING count(*) = 8 LIMIT 1, 1',
        [],
    ],
    'distinct after implicit aggregate remains one row' => [
        'SELECT DISTINCT count(*) AS rows FROM wp_options HAVING count(*) = 8',
        [['rows' => 8]],
    ],
    'having true constant with aggregate select' => [
        'SELECT count(*) AS rows FROM wp_options HAVING 1',
        [['rows' => 8]],
    ],
    'having false constant with aggregate select' => [
        'SELECT count(*) AS rows FROM wp_options HAVING 0',
        [],
    ],
    'having null constant with aggregate select' => [
        'SELECT count(*) AS rows FROM wp_options HAVING NULL',
        [],
    ],
    'having is null on nonprojected sum' => [
        'SELECT count(*) AS rows FROM wp_options WHERE option_name = \'missing\' HAVING sum(bytes) IS NULL',
        [['rows' => 0]],
    ],
    'having total on filtered empty rows' => [
        'SELECT count(*) AS rows FROM wp_options WHERE option_name = \'missing\' HAVING total(bytes) = 0.0',
        [['rows' => 0]],
    ],
    'having count wildcard and count value differ' => [
        'SELECT count(*) AS rows FROM wp_options HAVING count(*) = 8 AND count(bytes) = 7',
        [['rows' => 8]],
    ],
    'having filtered aggregate over core rows' => [
        'SELECT sum(bytes) AS total FROM wp_options WHERE scope = \'core\' HAVING count(*) = 2 AND sum(bytes) = 40',
        [['total' => 40]],
    ],
    'having filtered aggregate over media rows' => [
        'SELECT avg(bytes) AS average FROM wp_options WHERE scope = \'media\' HAVING avg(bytes) = 2',
        [['average' => 2.0]],
    ],
    'having max over joined weights' => [
        'SELECT max(m.weight) AS largest FROM wp_options JOIN option_meta AS m ON wp_options.option_id = m.option_id HAVING max(m.weight) = 30',
        [['largest' => 30]],
    ],
    'having min over joined weights suppressed' => [
        'SELECT min(m.weight) AS smallest FROM wp_options JOIN option_meta AS m ON wp_options.option_id = m.option_id HAVING min(m.weight) > 3',
        [],
    ],
    'having group concat on joined keys' => [
        'SELECT group_concat(m.meta_key) AS keys FROM wp_options JOIN option_meta AS m ON wp_options.option_id = m.option_id WHERE m.weight = 5 HAVING group_concat(m.meta_key) = \'cache|cache\'',
        [['keys' => 'cache|cache']],
    ],
    'having implicit aggregate from derived table' => [
        'SELECT count(*) AS rows FROM (SELECT option_id, bytes FROM wp_options WHERE bytes >= 12) AS staged HAVING count(*) = 5',
        [['rows' => 5]],
    ],
    'having sum from derived table' => [
        'SELECT sum(bucket) AS total FROM (SELECT bytes / 10 AS bucket FROM wp_options WHERE bytes >= 10) AS staged HAVING sum(bucket) > 7',
        [['total' => 7.6000000000000005]],
    ],
    'having no projected value aggregate with derived table' => [
        'SELECT count(*) AS rows FROM (SELECT bytes FROM wp_options WHERE bytes >= 10) AS staged HAVING sum(bytes) = 76',
        [['rows' => 5]],
    ],
];

foreach ($cases as $name => $case) {
    $tests['select implicit aggregate having current next34 ' . $name] = static function (TestRunner $t) use ($run, $case): void {
        $t->same($case[1], $run($case[0], $case[2] ?? [], $case[3] ?? []));
    };
}

return $tests;
