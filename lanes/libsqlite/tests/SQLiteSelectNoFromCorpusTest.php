<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

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

$cases = [
    'integer literal' => ['SELECT 1 AS one', [['one' => 1]]],
    'negative integer literal' => ['SELECT -7 AS v', [['v' => -7]]],
    'real literal' => ['SELECT 2.5 AS v', [['v' => 2.5]]],
    'null literal' => ['SELECT NULL AS v', [['v' => null]]],
    'quoted text literal' => ["SELECT 'canary''s' AS label", [['label' => "canary's"]]],
    'blob literal' => ["SELECT X'4142' AS payload", [['payload' => new SQLiteBlobValue('AB')]]],
    'arithmetic expression' => ['SELECT 2 + 3 * 4 AS v', [['v' => 14]]],
    'parenthesized arithmetic' => ['SELECT (2 + 3) * 4 AS v', [['v' => 20]]],
    'bitwise expression' => ['SELECT (7 & 3) | 8 AS flags', [['flags' => 11]]],
    'shift expression' => ['SELECT 1 << 4 AS shifted', [['shifted' => 16]]],
    'concat expression' => ["SELECT 'site' || 'url' AS name", [['name' => 'siteurl']]],
    'unary bitwise expression' => ['SELECT ~1 AS inverted', [['inverted' => -2]]],
    'scalar lower function' => ["SELECT lower('SiteURL') AS name", [['name' => 'siteurl']]],
    'scalar upper function' => ["SELECT upper('cache_key') AS name", [['name' => 'CACHE_KEY']]],
    'scalar length function' => ["SELECT length('cache') AS bytes", [['bytes' => 5]]],
    'scalar coalesce function' => ["SELECT coalesce(NULL, 'home') AS name", [['name' => 'home']]],
    'scalar ifnull function' => ["SELECT ifnull(NULL, 'fallback') AS name", [['name' => 'fallback']]],
    'scalar nullif function' => ['SELECT nullif(5, 5) AS v', [['v' => null]]],
    'json extract function' => ['SELECT json_extract(\'{"name":"cache","enabled":true}\', \'$.name\') AS name', [['name' => 'cache']]],
    'json type function' => ['SELECT json_type(\'{"enabled":true}\', \'$.enabled\') AS type', [['type' => 'true']]],
    'zeroblob function' => ['SELECT zeroblob(3) AS payload', [['payload' => new SQLiteBlobValue("\0\0\0")]]],
    'where true keeps implicit row' => ['SELECT 1 AS v WHERE 2 > 1', [['v' => 1]]],
    'where false filters implicit row' => ['SELECT 1 AS v WHERE 2 < 1', []],
    'where between keeps implicit row' => ['SELECT 5 AS v WHERE 5 BETWEEN 4 AND 6', [['v' => 5]]],
    'where not between filters implicit row' => ['SELECT 5 AS v WHERE 5 NOT BETWEEN 4 AND 6', []],
    'where like escape keeps implicit row' => ["SELECT 'site_%' AS name WHERE 'site_%' LIKE 'site!_%' ESCAPE '!'", [['name' => 'site_%']]],
    'where glob filters implicit row' => ["SELECT 'cache_key' AS name WHERE 'cache_key' GLOB 'cache_*'", [['name' => 'cache_key']]],
    'where in list keeps implicit row' => ["SELECT 'home' AS name WHERE 'home' IN ('siteurl', 'home')", [['name' => 'home']]],
    'where not in null filters implicit row' => ['SELECT 1 AS v WHERE 1 NOT IN (2, NULL)', []],
    'where is null keeps implicit row' => ['SELECT NULL AS v WHERE NULL IS NULL', [['v' => null]]],
    'where is not null filters implicit row' => ['SELECT NULL AS v WHERE NULL IS NOT NULL', []],
    'order by projected alias' => ['SELECT 2 + 1 AS weight ORDER BY weight DESC', [['weight' => 3]]],
    'order by hidden expression' => ["SELECT 'cache' AS name ORDER BY length('cache') DESC", [['name' => 'cache']]],
    'limit zero removes implicit row' => ['SELECT 1 AS v LIMIT 0', []],
    'limit one keeps implicit row' => ['SELECT 1 AS v LIMIT 1', [['v' => 1]]],
    'limit offset skips implicit row' => ['SELECT 1 AS v LIMIT 1 OFFSET 1', []],
    'comma limit skips implicit row' => ['SELECT 1 AS v LIMIT 1, 1', []],
    'distinct constant row' => ['SELECT DISTINCT 1 AS v', [['v' => 1]]],
    'all constant row' => ['SELECT ALL 1 AS v', [['v' => 1]]],
    'bind positional expression' => ['SELECT ?1 + ?2 AS v', [['v' => 7]], [1 => 3, 2 => 4]],
    'bind named expression' => ['SELECT :prefix || :suffix AS label', [['label' => 'siteurl']], [':prefix' => 'site', ':suffix' => 'url']],
    'cte scalar subquery value' => ['WITH seed(v) AS (VALUES (7)) SELECT (SELECT v FROM seed) AS v', [['v' => 7]]],
    'cte exists predicate' => ['WITH seed(v) AS (VALUES (7)) SELECT 1 AS ok WHERE EXISTS (SELECT v FROM seed WHERE v = 7)', [['ok' => 1]]],
    'cte not exists predicate filters' => ['WITH seed(v) AS (VALUES (7)) SELECT 1 AS ok WHERE NOT EXISTS (SELECT v FROM seed WHERE v = 7)', []],
    'cte in predicate' => ['WITH seed(v) AS (VALUES (7)) SELECT 7 AS v WHERE 7 IN (SELECT v FROM seed)', [['v' => 7]]],
    'cte not in predicate with null filters' => ['WITH seed(v) AS (VALUES (NULL)) SELECT 7 AS v WHERE 7 NOT IN (SELECT v FROM seed)', []],
];

foreach ($cases as $name => $case) {
    [$sql, $expected] = $case;
    $parameters = $case[2] ?? [];
    $tests['select no FROM corpus ' . $name] = static function (TestRunner $t) use ($sql, $expected, $parameters, $normalize): void {
        $t->same($normalize($expected), $normalize(SQLiteSelectSql::execute($sql, [], $parameters)));
    };
}

$plan = SQLiteSelectSql::plan('SELECT 1 AS v WHERE 1 = 1 ORDER BY v LIMIT 1', []);

$tests['select no FROM corpus plans implicit single row source'] = static function (TestRunner $t) use ($plan): void {
    $t->same([[]], $plan['from']);
    $t->same('v', $plan['select'][0]['alias']);
    $t->same('=', $plan['where']['operator']);
    $t->same([['column' => 'v']], $plan['orderBy']);
    $t->same(1, $plan['limit']);
};

$errorCases = [
    'empty projection' => 'SELECT',
    'fromless group by remains unsupported' => 'SELECT count(*) AS c GROUP BY c',
    'fromless having remains unsupported' => 'SELECT 1 AS v HAVING v = 1',
    'wildcard without source' => 'SELECT *',
    'unknown column in constant row' => 'SELECT missing AS v',
];

foreach ($errorCases as $name => $sql) {
    $tests['select no FROM corpus rejects ' . $name] = static function (TestRunner $t) use ($sql): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute($sql, []));
    };
}

$tests['select no FROM corpus treats unbound bind parameter as null'] = static function (TestRunner $t): void {
    $t->same([['v' => null]], SQLiteSelectSql::execute('SELECT ?1 AS v', []));
};

return $tests;
