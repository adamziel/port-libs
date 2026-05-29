<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteCompoundWindowRecursiveAffinityCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$currentOptions = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'weight' => 1, 'priority' => 50],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'weight' => 1.0, 'priority' => 40],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'weight' => '1', 'priority' => 30],
    ['option_id' => 4, 'option_name' => 'active_plugins', 'autoload' => 'no', 'weight' => 2, 'priority' => 20],
];
$nextOptions = [
    ...$currentOptions,
    ['option_id' => 5, 'option_name' => 'plugin_alpha', 'autoload' => 'no', 'weight' => '2', 'priority' => 10],
    ['option_id' => 6, 'option_name' => 'plugin_beta', 'autoload' => 'yes', 'weight' => 3, 'priority' => 5],
];
$currentEdges = [
    ['src' => 1, 'dst' => 2, 'weight' => 1.0],
    ['src' => 2, 'dst' => 3, 'weight' => '1'],
    ['src' => 3, 'dst' => 4, 'weight' => 2],
];
$nextEdges = [
    ...$currentEdges,
    ['src' => 4, 'dst' => 5, 'weight' => '2'],
    ['src' => 5, 'dst' => 6, 'weight' => 3],
];

$currentTables = ['wp_options' => $currentOptions, 'wp_option_edges' => $currentEdges];
$nextTables = ['wp_options' => $nextOptions, 'wp_option_edges' => $nextEdges];

$sql = <<<'SQL'
WITH RECURSIVE option_walk(item_id, key_value, source, score) AS MATERIALIZED (
    VALUES (1, 1, 'seed', 50)
    UNION
    SELECT wp_option_edges.dst, wp_option_edges.weight, 'edge', score - 7
      FROM wp_option_edges JOIN option_walk ON wp_option_edges.src = item_id
     WHERE item_id < 6
    UNION
    SELECT item_id, key_value + 0.0, source, score
      FROM option_walk
     WHERE item_id = 1
)
SELECT item_id AS id,
       key_value,
       source,
       sum(score) FILTER (WHERE key_value = 1) OVER (
           ORDER BY item_id, source
           ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING
       ) AS window_score
  FROM option_walk
UNION
SELECT option_id AS id,
       weight AS key_value,
       option_name AS source,
       sum(priority) FILTER (WHERE autoload = 'no') OVER (
           ORDER BY option_id
           ROWS BETWEEN CURRENT ROW AND CURRENT ROW
       ) AS window_score
  FROM wp_options
 WHERE option_id IN (SELECT item_id FROM option_walk)
 ORDER BY id, key_value, source
SQL;

$page = static fn (int $limit = 4, int $offset = 0, ?array $cursor = null): array => SQLiteCompoundWindowRecursiveAffinityCurrentSourceNextPlan::pageNext147($sql, $currentTables, $nextTables, $limit, $offset, $cursor);
$tests = [];

$tests['compound window recursive affinity current source next147 status'] = static fn (TestRunner $t) => $t->same('compound-window-recursive-affinity-current-source-next147-ready', $page()['status']);
$tests['compound window recursive affinity current source next147 dependencies'] = static fn (TestRunner $t) => $t->same([
    'sqlite-compound-recursive-affinity-window-current-source-next142',
    'sqlite-compound-current-source-next-cursor-fence',
    'sqlite-recursive-union-affinity-page-boundary',
    'sqlite-window-before-compound-page-resume',
], $page()['dependencies']);
$tests['compound window recursive affinity current source next147 first current ids'] = static fn (TestRunner $t) => $t->same([1, 1, 2, 2], $page()['currentPageIds']);
$tests['compound window recursive affinity current source next147 first next ids'] = static fn (TestRunner $t) => $t->same([1, 1, 2, 2], $page()['nextPageIds']);
$tests['compound window recursive affinity current source next147 first sources'] = static fn (TestRunner $t) => $t->same(['seed', 'siteurl', 'edge', 'home'], $page()['currentPageSources']);
$tests['compound window recursive affinity current source next147 totals'] = static fn (TestRunner $t) => $t->same([8, 12], [$page()['currentTotalRows'], $page()['nextTotalRows']]);
$tests['compound window recursive affinity current source next147 cursor has more'] = static fn (TestRunner $t) => $t->same(true, $page()['cursor']['hasMore']);
$tests['compound window recursive affinity current source next147 cursor offset'] = static fn (TestRunner $t) => $t->same(4, $page()['cursor']['offset']);
$tests['compound window recursive affinity current source next147 signature lengths'] = static fn (TestRunner $t) => $t->same([64, 64], [strlen($page()['currentSignature']), strlen($page()['nextSignature'])]);
$tests['compound window recursive affinity current source next147 resumes second page current ids'] = static fn (TestRunner $t) => $t->same([3, 3, 4, 4], $page(4, 4, $page()['cursor'])['currentPageIds']);
$tests['compound window recursive affinity current source next147 resumes second page next ids'] = static fn (TestRunner $t) => $t->same([3, 3, 4, 4], $page(4, 4, $page()['cursor'])['nextPageIds']);
$tests['compound window recursive affinity current source next147 resumes third page current empty'] = static fn (TestRunner $t) => $t->same([], $page(4, 8, $page(4, 4, $page()['cursor'])['cursor'])['currentPageIds']);
$tests['compound window recursive affinity current source next147 resumes third page next ids'] = static fn (TestRunner $t) => $t->same([5, 5, 6, 6], $page(4, 8, $page(4, 4, $page()['cursor'])['cursor'])['nextPageIds']);
$tests['compound window recursive affinity current source next147 final cursor'] = static fn (TestRunner $t) => $t->same(false, $page(4, 8, $page(4, 4, $page()['cursor'])['cursor'])['cursor']['hasMore']);
$tests['compound window recursive affinity current source next147 window fence'] = static fn (TestRunner $t) => $t->same([['sum', 'sum'], ['window_score', 'window_score'], ['ROWS', 'ROWS']], [$page()['windowFence']['functions'], $page()['windowFence']['aliases'], $page()['windowFence']['frameUnits']]);
$tests['compound window recursive affinity current source next147 recursive fence'] = static fn (TestRunner $t) => $t->same(['UNION', 4, 6], [$page()['recursiveFence']['operator'], $page()['recursiveFence']['currentTraceCount'], $page()['recursiveFence']['nextTraceCount']]);
$tests['compound window recursive affinity current source next147 affinity changed'] = static fn (TestRunner $t) => $t->true(in_array('numeric:3', $page()['affinityFence']['changedKeyClasses'], true));
$tests['compound window recursive affinity current source next147 source delta'] = static fn (TestRunner $t) => $t->same(['plugin_alpha', 'plugin_beta'], $page()['sourceDelta']['newSources']);
$tests['compound window recursive affinity current source next147 replan reason'] = static fn (TestRunner $t) => $t->true(in_array('current-source-next147-cursor-fence', $page()['replanReasons'], true));
$tests['compound window recursive affinity current source next147 dependency closure'] = static fn (TestRunner $t) => $t->contains('no new support component needed', $page()['dependency_closure']);
$tests['compound window recursive affinity current source next147 non overlap'] = static fn (TestRunner $t) => $t->contains('stale-cursor checked', $page()['non_overlap']);
$tests['compound window recursive affinity current source next147 rejects zero limit'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $page(0));
$tests['compound window recursive affinity current source next147 rejects negative offset'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $page(4, -1));
$tests['compound window recursive affinity current source next147 rejects stale offset cursor'] = static fn (TestRunner $t) => $t->throws(InvalidArgumentException::class, static fn () => $page(4, 5, $page()['cursor']));
$tests['compound window recursive affinity current source next147 rejects stale current cursor'] = static function (TestRunner $t) use ($page): void {
    $cursor = $page()['cursor'];
    $cursor['currentSignature'] = str_repeat('0', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $page(4, 4, $cursor));
};
$tests['compound window recursive affinity current source next147 rejects stale next cursor'] = static function (TestRunner $t) use ($page): void {
    $cursor = $page()['cursor'];
    $cursor['nextSignature'] = str_repeat('0', 64);
    $t->throws(InvalidArgumentException::class, static fn () => $page(4, 4, $cursor));
};

foreach (range(1, 44) as $case) {
    $tests['compound window recursive affinity current source next147 generated cursor boundary ' . $case] = static function (TestRunner $t) use ($case): void {
        $tables = [
            'wp_options' => [
                ['option_id' => 1, 'option_name' => 'siteurl_' . $case, 'autoload' => 'yes', 'weight' => 1, 'priority' => 50],
                ['option_id' => 2, 'option_name' => 'home_' . $case, 'autoload' => 'yes', 'weight' => 1.0, 'priority' => 40],
                ['option_id' => 3, 'option_name' => 'plugin_' . $case, 'autoload' => 'no', 'weight' => (string) (1 + ($case % 2)), 'priority' => 30],
            ],
            'wp_option_edges' => [
                ['src' => 1, 'dst' => 2, 'weight' => 1.0],
                ['src' => 2, 'dst' => 3, 'weight' => (string) (1 + ($case % 2))],
            ],
        ];
        $sql = "WITH RECURSIVE option_walk(item_id, key_value, source, score) AS (VALUES (1, 1, 'seed', {$case}) UNION SELECT wp_option_edges.dst, wp_option_edges.weight, 'edge', score + 1 FROM wp_option_edges JOIN option_walk ON wp_option_edges.src = item_id WHERE item_id < 3 UNION SELECT item_id, key_value + 0.0, source, score FROM option_walk WHERE item_id = 1) SELECT item_id AS id, key_value, source, sum(score) FILTER (WHERE key_value = 1) OVER (ORDER BY item_id, source ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS window_score FROM option_walk UNION SELECT option_id AS id, weight AS key_value, option_name AS source, sum(priority) FILTER (WHERE autoload = 'no') OVER (ORDER BY option_id ROWS BETWEEN CURRENT ROW AND CURRENT ROW) AS window_score FROM wp_options WHERE option_id IN (SELECT item_id FROM option_walk) ORDER BY id, key_value, source";
        $first = SQLiteCompoundWindowRecursiveAffinityCurrentSourceNextPlan::pageNext147($sql, $tables, $tables, 3);
        $second = SQLiteCompoundWindowRecursiveAffinityCurrentSourceNextPlan::pageNext147($sql, $tables, $tables, 3, 3, $first['cursor']);

        $t->same([1, 1, 2], $first['currentPageIds']);
        $t->same(3, $second['offset']);
        $t->same(64, strlen($second['currentSignature']));
        $t->true(count(SQLiteSelectSql::execute($sql, $tables)) >= 5);
    };
}

return $tests;
