<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

$tests = [];

$edges = [
    ['src' => 1, 'dst' => 2, 'kind' => 'autoload'],
    ['src' => 1, 'dst' => 3, 'kind' => 'autoload'],
    ['src' => 2, 'dst' => 4, 'kind' => 'plugin'],
    ['src' => 3, 'dst' => 5, 'kind' => 'theme'],
    ['src' => 4, 'dst' => 2, 'kind' => 'cycle'],
    ['src' => 5, 'dst' => 1, 'kind' => 'cycle'],
];

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes'],
    ['option_id' => 4, 'option_name' => 'active_plugins', 'autoload' => 'yes'],
    ['option_id' => 5, 'option_name' => 'stylesheet', 'autoload' => 'yes'],
];

$walkSql = <<<'SQL'
WITH RECURSIVE walk(id) AS (
    VALUES (1)
    UNION
    SELECT edges.dst FROM edges JOIN walk ON edges.src = walk.id ORDER BY 1 ASC
)
SELECT id FROM walk
SQL;

$walkDescSql = <<<'SQL'
WITH RECURSIVE walk(id) AS (
    VALUES (1)
    UNION
    SELECT edges.dst FROM edges JOIN walk ON edges.src = walk.id ORDER BY 1 DESC
)
SELECT id FROM walk
SQL;

$depthSql = <<<'SQL'
WITH RECURSIVE walk(id, depth) AS (
    VALUES (1, 0)
    UNION
    SELECT edges.dst, walk.depth + 1 FROM edges JOIN walk ON edges.src = walk.id WHERE walk.depth < 3 ORDER BY 2 DESC, 1 ASC
)
SELECT id, depth FROM walk
SQL;

$limitedSql = <<<'SQL'
WITH RECURSIVE walk(id) AS (
    VALUES (1)
    UNION
    SELECT edges.dst FROM edges JOIN walk ON edges.src = walk.id ORDER BY 1 ASC LIMIT 4
)
SELECT id FROM walk
SQL;

$offsetSql = <<<'SQL'
WITH RECURSIVE walk(id) AS (
    VALUES (1)
    UNION
    SELECT edges.dst FROM edges JOIN walk ON edges.src = walk.id ORDER BY 1 ASC LIMIT 3 OFFSET 2
)
SELECT id FROM walk
SQL;

$trace = static fn (string $sql, array $extraTables = []): array => SQLiteSelectSql::recursiveCteCycleTrace($sql, ['edges' => $edges] + $extraTables);
$execute = static fn (string $sql, array $extraTables = []): array => SQLiteSelectSql::execute($sql, ['edges' => $edges] + $extraTables);

$tests['recursive CTE union cycle current next32 trace names cte'] = static fn (TestRunner $t) => $t->same('walk', $trace($walkSql)['name']);
$tests['recursive CTE union cycle current next32 trace exposes column list'] = static fn (TestRunner $t) => $t->same(['id'], $trace($walkSql)['columns']);
$tests['recursive CTE union cycle current next32 trace records union operator'] = static fn (TestRunner $t) => $t->same('UNION', $trace($walkSql)['operator']);
$tests['recursive CTE union cycle current next32 rows converge once'] = static fn (TestRunner $t) => $t->same([[ 'id' => 1], ['id' => 2], ['id' => 3], ['id' => 4], ['id' => 5]], $trace($walkSql)['rows']);
$tests['recursive CTE union cycle current next32 execute matches trace rows'] = static fn (TestRunner $t) => $t->same($trace($walkSql)['rows'], $execute($walkSql));
$tests['recursive CTE union cycle current next32 dependencies named'] = static fn (TestRunner $t) => $t->same(['sqlite-recursive-cte-current-row', 'sqlite-recursive-union-cycle-dedup'], $trace($walkSql)['dependencies']);
$tests['recursive CTE union cycle current next32 first current is anchor'] = static fn (TestRunner $t) => $t->same(['id' => 1], $trace($walkSql)['trace'][0]['current']);
$tests['recursive CTE union cycle current next32 first queue before contains anchor'] = static fn (TestRunner $t) => $t->same([['id' => 1]], $trace($walkSql)['trace'][0]['queue_before']);
$tests['recursive CTE union cycle current next32 first generated has two children'] = static fn (TestRunner $t) => $t->same([['id' => 2], ['id' => 3]], $trace($walkSql)['trace'][0]['generated']);
$tests['recursive CTE union cycle current next32 first accepted next has two children'] = static fn (TestRunner $t) => $t->same([['id' => 2], ['id' => 3]], $trace($walkSql)['trace'][0]['accepted_next']);
$tests['recursive CTE union cycle current next32 first queue after asc'] = static fn (TestRunner $t) => $t->same([['id' => 2], ['id' => 3]], $trace($walkSql)['trace'][0]['queue_after']);
$tests['recursive CTE union cycle current next32 second current follows asc queue'] = static fn (TestRunner $t) => $t->same(['id' => 2], $trace($walkSql)['trace'][1]['current']);
$tests['recursive CTE union cycle current next32 second generated child'] = static fn (TestRunner $t) => $t->same([['id' => 4]], $trace($walkSql)['trace'][1]['generated']);
$tests['recursive CTE union cycle current next32 second queue after appends child'] = static fn (TestRunner $t) => $t->same([['id' => 3], ['id' => 4]], $trace($walkSql)['trace'][1]['queue_after']);
$tests['recursive CTE union cycle current next32 third current follows existing sibling'] = static fn (TestRunner $t) => $t->same(['id' => 3], $trace($walkSql)['trace'][2]['current']);
$tests['recursive CTE union cycle current next32 fourth current reaches plugin child'] = static fn (TestRunner $t) => $t->same(['id' => 4], $trace($walkSql)['trace'][3]['current']);
$tests['recursive CTE union cycle current next32 fourth generated duplicate cycle'] = static fn (TestRunner $t) => $t->same([['id' => 2]], $trace($walkSql)['trace'][3]['generated']);
$tests['recursive CTE union cycle current next32 fourth skips duplicate cycle'] = static fn (TestRunner $t) => $t->same([['id' => 2]], $trace($walkSql)['trace'][3]['skipped_duplicates']);
$tests['recursive CTE union cycle current next32 fourth accepts no duplicate'] = static fn (TestRunner $t) => $t->same([], $trace($walkSql)['trace'][3]['accepted_next']);
$tests['recursive CTE union cycle current next32 fifth current reaches theme child'] = static fn (TestRunner $t) => $t->same(['id' => 5], $trace($walkSql)['trace'][4]['current']);
$tests['recursive CTE union cycle current next32 fifth generated root cycle'] = static fn (TestRunner $t) => $t->same([['id' => 1]], $trace($walkSql)['trace'][4]['generated']);
$tests['recursive CTE union cycle current next32 fifth skips root cycle'] = static fn (TestRunner $t) => $t->same([['id' => 1]], $trace($walkSql)['trace'][4]['skipped_duplicates']);
$tests['recursive CTE union cycle current next32 skipped count reports two cycles'] = static fn (TestRunner $t) => $t->same(2, count($trace($walkSql)['skipped']));
$tests['recursive CTE union cycle current next32 first skipped reason'] = static fn (TestRunner $t) => $t->same('union-duplicate-cycle', $trace($walkSql)['skipped'][0]['reason']);
$tests['recursive CTE union cycle current next32 first skipped current row'] = static fn (TestRunner $t) => $t->same(['id' => 4], $trace($walkSql)['skipped'][0]['current']);
$tests['recursive CTE union cycle current next32 first skipped row'] = static fn (TestRunner $t) => $t->same(['id' => 2], $trace($walkSql)['skipped'][0]['row']);
$tests['recursive CTE union cycle current next32 second skipped current row'] = static fn (TestRunner $t) => $t->same(['id' => 5], $trace($walkSql)['skipped'][1]['current']);
$tests['recursive CTE union cycle current next32 second skipped row'] = static fn (TestRunner $t) => $t->same(['id' => 1], $trace($walkSql)['skipped'][1]['row']);
$tests['recursive CTE union cycle current next32 final queue empty'] = static fn (TestRunner $t) => $t->same([], $trace($walkSql)['trace'][4]['queue_after']);
$tests['recursive CTE union cycle current next32 every emitted flag true'] = static fn (TestRunner $t) => $t->same([true, true, true, true, true], array_column($trace($walkSql)['trace'], 'emitted'));
$tests['recursive CTE union cycle current next32 desc row order'] = static fn (TestRunner $t) => $t->same([[ 'id' => 1], ['id' => 3], ['id' => 5], ['id' => 2], ['id' => 4]], $trace($walkDescSql)['rows']);
$tests['recursive CTE union cycle current next32 desc first queue after'] = static fn (TestRunner $t) => $t->same([['id' => 3], ['id' => 2]], $trace($walkDescSql)['trace'][0]['queue_after']);
$tests['recursive CTE union cycle current next32 desc second current'] = static fn (TestRunner $t) => $t->same(['id' => 3], $trace($walkDescSql)['trace'][1]['current']);
$tests['recursive CTE union cycle current next32 desc root cycle skipped before sibling'] = static fn (TestRunner $t) => $t->same(['id' => 1], $trace($walkDescSql)['skipped'][0]['row']);
$tests['recursive CTE union cycle current next32 depth rows keep state distinct'] = static fn (TestRunner $t) => $t->same([[ 'id' => 1, 'depth' => 0], ['id' => 2, 'depth' => 1], ['id' => 4, 'depth' => 2], ['id' => 2, 'depth' => 3], ['id' => 3, 'depth' => 1], ['id' => 5, 'depth' => 2], ['id' => 1, 'depth' => 3]], $trace($depthSql)['rows']);
$tests['recursive CTE union cycle current next32 depth columns'] = static fn (TestRunner $t) => $t->same(['id', 'depth'], $trace($depthSql)['columns']);
$tests['recursive CTE union cycle current next32 depth current follows desc depth'] = static fn (TestRunner $t) => $t->same(['id' => 4, 'depth' => 2], $trace($depthSql)['trace'][2]['current']);
$tests['recursive CTE union cycle current next32 depth accepts cyclic node with new depth'] = static fn (TestRunner $t) => $t->same([['id' => 2, 'depth' => 3]], $trace($depthSql)['trace'][2]['accepted_next']);
$tests['recursive CTE union cycle current next32 depth has no duplicate skips'] = static fn (TestRunner $t) => $t->same([], $trace($depthSql)['skipped']);
$tests['recursive CTE union cycle current next32 limit emits four rows'] = static fn (TestRunner $t) => $t->same([[ 'id' => 1], ['id' => 2], ['id' => 3], ['id' => 4]], $trace($limitedSql)['rows']);
$tests['recursive CTE union cycle current next32 limit last trace stops before duplicate'] = static fn (TestRunner $t) => $t->same([], $trace($limitedSql)['trace'][3]['generated']);
$tests['recursive CTE union cycle current next32 limit remaining reaches zero'] = static fn (TestRunner $t) => $t->same(0, $trace($limitedSql)['trace'][3]['limit_remaining']);
$tests['recursive CTE union cycle current next32 offset emits after skipped anchor rows'] = static fn (TestRunner $t) => $t->same([[ 'id' => 3], ['id' => 4], ['id' => 5]], $trace($offsetSql)['rows']);
$tests['recursive CTE union cycle current next32 offset first row not emitted'] = static fn (TestRunner $t) => $t->same(false, $trace($offsetSql)['trace'][0]['emitted']);
$tests['recursive CTE union cycle current next32 offset second row not emitted'] = static fn (TestRunner $t) => $t->same(false, $trace($offsetSql)['trace'][1]['emitted']);
$tests['recursive CTE union cycle current next32 offset third row emitted'] = static fn (TestRunner $t) => $t->same(true, $trace($offsetSql)['trace'][2]['emitted']);
$tests['recursive CTE union cycle current next32 named bind root'] = static fn (TestRunner $t) => $t->same([[ 'id' => 3], ['id' => 5], ['id' => 1], ['id' => 2], ['id' => 4]], SQLiteSelectSql::recursiveCteCycleTrace(str_replace('VALUES (1)', 'VALUES (:root)', $walkSql), ['edges' => $edges], [':root' => 3])['rows']);
$tests['recursive CTE union cycle current next32 positional bind root'] = static fn (TestRunner $t) => $t->same([[ 'id' => 2], ['id' => 4]], SQLiteSelectSql::recursiveCteCycleTrace(str_replace('VALUES (1)', 'VALUES (?1)', $walkSql), ['edges' => $edges], [1 => 2])['rows']);
$tests['recursive CTE union cycle current next32 ordinary cte before recursive'] = static fn (TestRunner $t) => $t->same([[ 'id' => 1], ['id' => 2], ['id' => 3], ['id' => 4], ['id' => 5]], SQLiteSelectSql::recursiveCteCycleTrace('WITH RECURSIVE roots(id) AS (VALUES (1)), walk(id) AS (SELECT id FROM roots UNION SELECT edges.dst FROM edges JOIN walk ON edges.src = walk.id ORDER BY 1 ASC) SELECT id FROM walk', ['edges' => $edges])['rows']);
$tests['recursive CTE union cycle current next32 wp join names from trace rows'] = static fn (TestRunner $t) => $t->same(['siteurl', 'home', 'blogname', 'active_plugins', 'stylesheet'], array_column($execute('WITH RECURSIVE walk(id) AS (VALUES (1) UNION SELECT edges.dst FROM edges JOIN walk ON edges.src = walk.id ORDER BY 1 ASC) SELECT wp_options.option_name AS option_name FROM walk JOIN wp_options ON wp_options.option_id = walk.id ORDER BY walk.id', ['wp_options' => $options]), 'option_name'));
$tests['recursive CTE union cycle current next32 wp exists filter'] = static fn (TestRunner $t) => $t->same(['home', 'stylesheet'], array_column($execute('WITH RECURSIVE walk(id) AS (VALUES (1) UNION SELECT edges.dst FROM edges JOIN walk ON edges.src = walk.id ORDER BY 1 ASC) SELECT option_name FROM wp_options WHERE option_id IN (SELECT id FROM walk WHERE id IN (2, 5)) ORDER BY option_id', ['wp_options' => $options]), 'option_name'));
$tests['recursive CTE union cycle current next32 wp not exists leaves none'] = static fn (TestRunner $t) => $t->same([], $execute('WITH RECURSIVE walk(id) AS (VALUES (1) UNION SELECT edges.dst FROM edges JOIN walk ON edges.src = walk.id ORDER BY 1 ASC) SELECT option_name FROM wp_options WHERE option_id NOT IN (SELECT id FROM walk) ORDER BY option_id', ['wp_options' => $options]));
$tests['recursive CTE union cycle current next32 rejects non recursive with'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::recursiveCteCycleTrace('WITH walk(id) AS (VALUES (1)) SELECT id FROM walk', []));
$tests['recursive CTE union cycle current next32 rejects missing trailing select use'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::recursiveCteCycleTrace('WITH RECURSIVE walk(id) AS (VALUES (1) UNION SELECT id FROM walk) SELECT 1', []));

return $tests;
