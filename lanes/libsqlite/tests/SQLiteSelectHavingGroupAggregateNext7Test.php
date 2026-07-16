<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'option_value' => 'https://example.test', 'bytes' => 20, 'tier' => 'core'],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'option_value' => 'https://example.test', 'bytes' => 20, 'tier' => 'core'],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'option_value' => 'Example Site', 'bytes' => 12, 'tier' => 'theme'],
    ['option_id' => 4, 'option_name' => '_transient_feed', 'autoload' => 'no', 'option_value' => 'cached', 'bytes' => 12, 'tier' => 'cache'],
    ['option_id' => 5, 'option_name' => '_site_transient_update_plugins', 'autoload' => 'no', 'option_value' => 'cached', 'bytes' => 12, 'tier' => 'cache'],
    ['option_id' => 6, 'option_name' => 'orphaned', 'autoload' => null, 'option_value' => null, 'bytes' => null, 'tier' => 'orphan'],
    ['option_id' => 7, 'option_name' => 'binary_a', 'autoload' => 'no', 'option_value' => 'AB', 'bytes' => 2, 'tier' => 'media'],
    ['option_id' => 8, 'option_name' => 'binary_b', 'autoload' => 'no', 'option_value' => 'AB', 'bytes' => 2, 'tier' => 'media'],
];

$meta = [
    ['option_id' => 1, 'source' => 'core', 'weight' => 10],
    ['option_id' => 2, 'source' => 'core', 'weight' => 10],
    ['option_id' => 3, 'source' => 'theme', 'weight' => 30],
    ['option_id' => 4, 'source' => 'cache', 'weight' => 5],
    ['option_id' => 5, 'source' => 'cache', 'weight' => 5],
    ['option_id' => 7, 'source' => 'media', 'weight' => 2],
    ['option_id' => 8, 'source' => 'media', 'weight' => 2],
];

$run = static fn (string $sql, array $tables = [], array $parameters = []): array => SQLiteSelectSql::execute(
    $sql,
    ['wp_options' => $options, 'option_meta' => $meta] + $tables,
    $parameters,
);

$tests = [];

$cases = [
    'having sum greater than threshold' => [
        'SELECT autoload, sum(bytes) AS total FROM wp_options GROUP BY autoload HAVING sum(bytes) > 20 ORDER BY total DESC',
        [['autoload' => 'yes', 'total' => 52], ['autoload' => 'no', 'total' => 28]],
    ],
    'having count wildcard includes null value rows' => [
        'SELECT autoload, count(bytes) AS total FROM wp_options GROUP BY autoload HAVING count(bytes) >= 1 ORDER BY autoload',
        [['autoload' => 'no', 'total' => 4], ['autoload' => 'yes', 'total' => 3]],
    ],
    'having count value excludes null aggregate inputs' => [
        'SELECT autoload, count(bytes) AS total FROM wp_options GROUP BY autoload HAVING count(bytes) = 0',
        [['autoload' => null, 'total' => 0]],
    ],
    'having avg comparison' => [
        'SELECT autoload, avg(bytes) AS average FROM wp_options GROUP BY autoload HAVING avg(bytes) >= 13 ORDER BY average DESC',
        [['autoload' => 'yes', 'average' => 17.333333333333332]],
    ],
    'having min comparison' => [
        'SELECT autoload, min(bytes) AS smallest FROM wp_options GROUP BY autoload HAVING min(bytes) < 10 ORDER BY autoload',
        [['autoload' => 'no', 'smallest' => 2]],
    ],
    'having max comparison' => [
        'SELECT autoload, max(bytes) AS largest FROM wp_options GROUP BY autoload HAVING max(bytes) = 20',
        [['autoload' => 'yes', 'largest' => 20]],
    ],
    'having total includes zero for all-null group' => [
        'SELECT autoload, total(bytes) AS total_bytes FROM wp_options GROUP BY autoload HAVING total(bytes) = 0.0',
        [['autoload' => null, 'total_bytes' => 0.0]],
    ],
    'having group column equality' => [
        'SELECT autoload, sum(bytes) AS total FROM wp_options GROUP BY autoload HAVING autoload = \'no\'',
        [['autoload' => 'no', 'total' => 28]],
    ],
    'having group column is null' => [
        'SELECT autoload, count(bytes) AS total FROM wp_options GROUP BY autoload HAVING autoload IS NULL',
        [['autoload' => null, 'total' => 0]],
    ],
    'having group column is not null' => [
        'SELECT autoload, count(bytes) AS total FROM wp_options GROUP BY autoload HAVING autoload IS NOT NULL ORDER BY autoload DESC',
        [['autoload' => 'yes', 'total' => 3], ['autoload' => 'no', 'total' => 4]],
    ],
    'having and predicate' => [
        'SELECT autoload, sum(bytes) AS total FROM wp_options GROUP BY autoload HAVING sum(bytes) > 20 AND autoload = \'yes\'',
        [['autoload' => 'yes', 'total' => 52]],
    ],
    'having or predicate' => [
        'SELECT autoload, sum(bytes) AS total FROM wp_options GROUP BY autoload HAVING autoload IS NULL OR sum(bytes) > 30 ORDER BY total DESC',
        [['autoload' => 'yes', 'total' => 52], ['autoload' => null, 'total' => null]],
    ],
    'having not equal predicate' => [
        'SELECT autoload, count(bytes) AS total FROM wp_options GROUP BY autoload HAVING autoload != \'yes\' ORDER BY total DESC',
        [['autoload' => 'no', 'total' => 4]],
    ],
    'having between aggregate' => [
        'SELECT autoload, sum(bytes) AS total FROM wp_options GROUP BY autoload HAVING sum(bytes) BETWEEN 20 AND 30',
        [['autoload' => 'no', 'total' => 28]],
    ],
    'having not between aggregate' => [
        'SELECT autoload, sum(bytes) AS total FROM wp_options GROUP BY autoload HAVING sum(bytes) NOT BETWEEN 20 AND 60',
        [],
    ],
    'having aggregate in literal list' => [
        'SELECT autoload, count(bytes) AS total FROM wp_options GROUP BY autoload HAVING count(bytes) IN (0, 3) ORDER BY autoload',
        [['autoload' => null, 'total' => 0], ['autoload' => 'yes', 'total' => 3]],
    ],
    'having aggregate not in literal list' => [
        'SELECT autoload, count(bytes) AS total FROM wp_options GROUP BY autoload HAVING count(bytes) NOT IN (0, 3) ORDER BY autoload',
        [['autoload' => 'no', 'total' => 4]],
    ],
    'having like group column' => [
        'SELECT tier, sum(bytes) AS total FROM wp_options GROUP BY tier HAVING tier LIKE \'c%\' ORDER BY tier',
        [['tier' => 'cache', 'total' => 24], ['tier' => 'core', 'total' => 40]],
    ],
    'having glob group column' => [
        'SELECT tier, count(bytes) AS total FROM wp_options GROUP BY tier HAVING tier GLOB \'c*\' ORDER BY tier',
        [['tier' => 'cache', 'total' => 2], ['tier' => 'core', 'total' => 2]],
    ],
    'composite group having sum' => [
        'SELECT autoload, tier, sum(bytes) AS total FROM wp_options GROUP BY autoload, tier HAVING sum(bytes) >= 20 ORDER BY autoload DESC, tier',
        [['autoload' => 'yes', 'tier' => 'core', 'total' => 40], ['autoload' => 'no', 'tier' => 'cache', 'total' => 24]],
    ],
    'composite group having count' => [
        'SELECT autoload, tier, count(bytes) AS total FROM wp_options GROUP BY autoload, tier HAVING count(bytes) = 2 ORDER BY tier',
        [['autoload' => 'no', 'tier' => 'cache', 'total' => 2], ['autoload' => 'yes', 'tier' => 'core', 'total' => 2], ['autoload' => 'no', 'tier' => 'media', 'total' => 2]],
    ],
    'composite group null key preserved' => [
        'SELECT autoload, tier, count(bytes) AS total FROM wp_options GROUP BY autoload, tier HAVING autoload IS NULL',
        [['autoload' => null, 'tier' => 'orphan', 'total' => 0]],
    ],
    'where feeds having' => [
        'SELECT autoload, sum(bytes) AS total FROM wp_options WHERE option_id <= 5 GROUP BY autoload HAVING sum(bytes) > 20 ORDER BY autoload',
        [['autoload' => 'no', 'total' => 24], ['autoload' => 'yes', 'total' => 52]],
    ],
    'distinct after grouped having projection' => [
        'SELECT DISTINCT count(bytes) AS total FROM wp_options GROUP BY tier HAVING count(bytes) = 2',
        [['total' => 2]],
    ],
    'order by aggregate expression after having' => [
        'SELECT autoload, sum(bytes) AS total FROM wp_options GROUP BY autoload HAVING count(*) > 1 ORDER BY sum(bytes) + count(*) DESC',
        [['autoload' => 'yes', 'total' => 52], ['autoload' => 'no', 'total' => 28]],
    ],
    'limit after having keeps top aggregate row' => [
        'SELECT autoload, sum(bytes) AS total FROM wp_options GROUP BY autoload HAVING sum(bytes) > 0 ORDER BY total DESC LIMIT 1',
        [['autoload' => 'yes', 'total' => 52]],
    ],
    'offset after having skips top aggregate row' => [
        'SELECT autoload, sum(bytes) AS total FROM wp_options GROUP BY autoload HAVING sum(bytes) > 0 ORDER BY total DESC LIMIT 1 OFFSET 1',
        [['autoload' => 'no', 'total' => 28]],
    ],
    'comma limit after having' => [
        'SELECT tier, sum(bytes) AS total FROM wp_options GROUP BY tier HAVING sum(bytes) > 0 ORDER BY total DESC LIMIT 1, 2',
        [['tier' => 'cache', 'total' => 24], ['tier' => 'theme', 'total' => 12]],
    ],
    'values cte grouped having' => [
        'WITH seed(autoload, bytes) AS (VALUES (\'yes\', 8), (\'yes\', 12), (\'no\', 3)) SELECT autoload, sum(bytes) AS total FROM seed GROUP BY autoload HAVING sum(bytes) >= 20',
        [['autoload' => 'yes', 'total' => 20]],
    ],
    'values cte composite grouped having' => [
        'WITH seed(autoload, tier, bytes) AS (VALUES (\'yes\', \'core\', 8), (\'yes\', \'core\', 12), (\'yes\', \'theme\', 3)) SELECT autoload, tier, sum(bytes) AS total FROM seed GROUP BY autoload, tier HAVING count(*) = 2',
        [['autoload' => 'yes', 'tier' => 'core', 'total' => 20]],
    ],
    'bind parameter in having threshold' => [
        'SELECT autoload, sum(bytes) AS total FROM wp_options GROUP BY autoload HAVING sum(bytes) > :minimum ORDER BY total',
        [['autoload' => 'no', 'total' => 28], ['autoload' => 'yes', 'total' => 52]],
        [':minimum' => 24],
    ],
    'bind parameter in having group predicate' => [
        'SELECT autoload, count(bytes) AS total FROM wp_options GROUP BY autoload HAVING autoload = ?1',
        [['autoload' => 'yes', 'total' => 3]],
        [1 => 'yes'],
    ],
    'joined rows grouped having' => [
        'SELECT m.source, sum(m.weight) AS total FROM wp_options JOIN option_meta AS m ON wp_options.option_id = m.option_id GROUP BY m.source HAVING sum(m.weight) >= 20 ORDER BY total DESC',
        [['m.source' => 'theme', 'total' => 30], ['m.source' => 'core', 'total' => 20]],
    ],
    'joined rows count having' => [
        'SELECT m.source, count(m.weight) AS total FROM wp_options JOIN option_meta AS m ON wp_options.option_id = m.option_id GROUP BY m.source HAVING count(m.weight) = 2 ORDER BY m.source',
        [['m.source' => 'cache', 'total' => 2], ['m.source' => 'core', 'total' => 2], ['m.source' => 'media', 'total' => 2]],
    ],
    'left join null extended grouped having' => [
        'SELECT m.source, count(m.weight) AS total FROM wp_options LEFT JOIN option_meta AS m ON wp_options.option_id = m.option_id GROUP BY m.source HAVING m.source IS NULL',
        [['m.source' => null, 'total' => 0]],
    ],
    'having sum plus count expression' => [
        'SELECT autoload, sum(bytes) AS total FROM wp_options GROUP BY autoload HAVING sum(bytes) + count(bytes) > 40',
        [['autoload' => 'yes', 'total' => 52]],
    ],
    'having max minus min expression' => [
        'SELECT autoload, max(bytes) AS largest FROM wp_options GROUP BY autoload HAVING max(bytes) - min(bytes) = 8',
        [['autoload' => 'yes', 'largest' => 20]],
    ],
    'having avg times count expression' => [
        'SELECT autoload, avg(bytes) AS average FROM wp_options GROUP BY autoload HAVING avg(bytes) * count(bytes) > 30',
        [['autoload' => 'yes', 'average' => 17.333333333333332]],
    ],
    'having division expression' => [
        'SELECT autoload, sum(bytes) AS total FROM wp_options GROUP BY autoload HAVING sum(bytes) / count(bytes) = 7',
        [['autoload' => 'no', 'total' => 28]],
    ],
    'having modulo expression' => [
        'SELECT autoload, sum(bytes) AS total FROM wp_options GROUP BY autoload HAVING sum(bytes) % count(bytes) = 0 ORDER BY autoload',
        [['autoload' => 'no', 'total' => 28]],
    ],
    'having concatenated group expression' => [
        'SELECT autoload, count(bytes) AS total FROM wp_options GROUP BY autoload HAVING autoload || \':copy\' = \'yes:copy\'',
        [['autoload' => 'yes', 'total' => 3]],
    ],
    'having group concat comparison' => [
        'SELECT autoload, group_concat(bytes) AS packed FROM wp_options GROUP BY autoload HAVING group_concat(bytes) = \'12|12|2|2\'',
        [['autoload' => 'no', 'packed' => '12|12|2|2']],
    ],
    'having group concat inequality empty result' => [
        'SELECT autoload, group_concat(bytes) AS packed FROM wp_options GROUP BY autoload HAVING group_concat(bytes) = \'missing\'',
        [],
    ],
    'having count distinct projection with count predicate' => [
        'SELECT autoload, count(bytes) AS total FROM wp_options GROUP BY autoload HAVING count(bytes) >= 3 ORDER BY autoload DESC',
        [['autoload' => 'yes', 'total' => 3], ['autoload' => 'no', 'total' => 4]],
    ],
    'having total less than threshold' => [
        'SELECT tier, total(bytes) AS total_bytes FROM wp_options GROUP BY tier HAVING total(bytes) < 15 ORDER BY tier',
        [['tier' => 'media', 'total_bytes' => 4.0], ['tier' => 'orphan', 'total_bytes' => 0.0], ['tier' => 'theme', 'total_bytes' => 12.0]],
    ],
    'having min is null' => [
        'SELECT autoload, min(bytes) AS smallest FROM wp_options GROUP BY autoload HAVING min(bytes) IS NULL',
        [['autoload' => null, 'smallest' => null]],
    ],
    'having max is not null' => [
        'SELECT autoload, max(bytes) AS largest FROM wp_options GROUP BY autoload HAVING max(bytes) IS NOT NULL ORDER BY autoload',
        [['autoload' => 'no', 'largest' => 12], ['autoload' => 'yes', 'largest' => 20]],
    ],
    'having filtered values cte with null group' => [
        'WITH seed(label, bytes) AS (VALUES (NULL, NULL), (\'cache\', 4), (\'cache\', 5)) SELECT label, sum(bytes) AS total FROM seed GROUP BY label HAVING sum(bytes) IS NULL OR sum(bytes) > 8 ORDER BY total DESC',
        [['label' => 'cache', 'total' => 9], ['label' => null, 'total' => null]],
    ],
    'having after cross join row multiplication' => [
        'SELECT wp_options.autoload, count(wp_options.bytes) AS total FROM wp_options CROSS JOIN flags GROUP BY wp_options.autoload HAVING count(wp_options.bytes) >= 6 ORDER BY wp_options.autoload',
        [['wp_options.autoload' => 'no', 'total' => 8], ['wp_options.autoload' => 'yes', 'total' => 6]],
        [],
        ['flags' => [['flag' => 'a'], ['flag' => 'b']]],
    ],
    'having preserves empty rowset' => [
        'SELECT autoload, sum(bytes) AS total FROM wp_options WHERE option_name = \'missing\' GROUP BY autoload HAVING sum(bytes) > 0',
        [],
    ],
];

foreach ($cases as $name => $case) {
    $tests['select having group aggregate next7 ' . $name] = static function (TestRunner $t) use ($run, $case): void {
        $t->same($case[1], $run($case[0], $case[3] ?? [], $case[2] ?? []));
    };
}

return $tests;
