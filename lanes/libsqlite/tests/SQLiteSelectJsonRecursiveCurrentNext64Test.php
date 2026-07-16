<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteSelectRecursiveJsonMaterialization;
use PortLibs\LibSqlite\SQLiteSelectSql;

$options = [
    ['option_id' => 101, 'option_name' => 'wp_route_seed', 'autoload' => 'yes', 'option_value' => '{"next":[102,103],"rules":[{"slug":"cache","priority":30,"enabled":1},{"slug":"seo","priority":20,"enabled":1},{"slug":"media","priority":10,"enabled":0}]}'],
    ['option_id' => 102, 'option_name' => 'wp_route_cache', 'autoload' => 'yes', 'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(['next' => [104], 'rules' => [['slug' => 'cache-warm', 'priority' => 50, 'enabled' => 1], ['slug' => 'cache-purge', 'priority' => 40, 'enabled' => 0], ['slug' => 'cache-preload', 'priority' => 35, 'enabled' => 1]]]))],
    ['option_id' => 103, 'option_name' => 'wp_route_media', 'autoload' => 'no', 'option_value' => '{"next":[104,105],"rules":[{"slug":"image","priority":25,"enabled":1},{"slug":"video","priority":15,"enabled":0},{"slug":"embed","priority":5,"enabled":1}]}'],
    ['option_id' => 104, 'option_name' => 'wp_route_store', 'autoload' => 'yes', 'option_value' => '{"next":[106],"rules":[{"slug":"cart","priority":45,"enabled":1},{"slug":"checkout","priority":42,"enabled":1},{"slug":"receipt","priority":12,"enabled":0}]}'],
    ['option_id' => 105, 'option_name' => 'wp_route_forms', 'autoload' => 'no', 'option_value' => '{"next":[106],"rules":[{"slug":"contact","priority":33,"enabled":1},{"slug":"captcha","priority":17,"enabled":1},{"slug":"survey","priority":7,"enabled":0}]}'],
    ['option_id' => 106, 'option_name' => 'wp_route_leaf', 'autoload' => 'yes', 'option_value' => '{"next":[102],"rules":[{"slug":"sync","priority":60,"enabled":1},{"slug":"cleanup","priority":22,"enabled":0},{"slug":"audit","priority":18,"enabled":1}]}'],
    ['option_id' => 107, 'option_name' => 'wp_route_unused', 'autoload' => 'no', 'option_value' => '{"next":[],"rules":[{"slug":"unused","priority":99,"enabled":0}]}'],
];

$tables = ['wp_options' => $options];
$sql = <<<'SQL'
WITH RECURSIVE route(option_id, depth, parent_name) AS MATERIALIZED (
    VALUES (101, 0, 'seed')
    UNION
    SELECT CAST(edge.atom AS INTEGER), route.depth + 1, host.option_name
      FROM route
      JOIN wp_options AS host ON host.option_id = route.option_id
      JOIN json_each(host.option_value, '$.next') AS edge ON edge.type = 'integer'
     WHERE route.depth < 4
)
SELECT route.option_id AS option_id,
       route.depth AS depth,
       route.parent_name AS parent_name,
       host.option_name AS option_name,
       rule.key AS attr,
       rule.atom AS atom,
       rule.type AS json_type,
       rule.fullkey AS fullkey
  FROM route
  JOIN wp_options AS host ON host.option_id = route.option_id
  JOIN json_tree(host.option_value, '$.rules') AS rule ON rule.type IN ('text', 'integer', 'true', 'false')
 ORDER BY route.depth, route.option_id, rule.fullkey
SQL;

$plan = static fn (): array => SQLiteSelectRecursiveJsonMaterialization::materialize($sql, $tables, ['option_id', 'attr'], ['fullkey']);
$window = static fn (): array => SQLiteSelectRecursiveJsonMaterialization::jsonCurrentNextWindow($plan(), ['option_id', 'attr'], ['depth', 'fullkey']);
$select = static fn (string $selectSql): array => SQLiteSelectSql::execute($selectSql, $tables);

$tests = [
    'select json recursive current next64 exposes recursive json window dependency' => static function (TestRunner $t) use ($plan): void {
        $t->true(in_array('sqlite-recursive-current-next-json-yield-boundary', $plan()['dependencies'], true));
    },
    'select json recursive current next64 materializes reachable rule attributes only' => static function (TestRunner $t) use ($plan): void {
        $t->same(81, count($plan()['rows']));
    },
    'select json recursive current next64 excludes unreachable application route' => static function (TestRunner $t) use ($plan): void {
        $t->same(false, in_array('wp_route_unused', array_column($plan()['rows'], 'option_name'), true));
    },
    'select json recursive current next64 has one window row per materialized json row' => static function (TestRunner $t) use ($plan, $window): void {
        $t->same(count($plan()['rows']), count($window()));
    },
    'select json recursive current next64 sorts partitions by encoded key' => static function (TestRunner $t) use ($window): void {
        $t->same(['option_id' => 101, 'attr' => 'enabled'], $window()[0]['partition']);
    },
    'select json recursive current next64 first partition current is seed enabled field' => static function (TestRunner $t) use ($window): void {
        $t->same([101, 'enabled', '$.rules[0].enabled'], [$window()[0]['row']['option_id'], $window()[0]['row']['attr'], $window()[0]['row']['fullkey']]);
    },
    'select json recursive current next64 first partition lead is second enabled field' => static function (TestRunner $t) use ($window): void {
        $t->same('$.rules[1].enabled', $window()[0]['next']['fullkey']);
    },
    'select json recursive current next64 terminal partition row has null next' => static function (TestRunner $t) use ($window): void {
        $lastSeedEnabled = array_values(array_filter($window(), static fn (array $row): bool => $row['partition'] === ['option_id' => 101, 'attr' => 'enabled'] && $row['last']))[0];
        $t->same(null, $lastSeedEnabled['next']);
    },
    'select json recursive current next64 first partition row has null previous' => static function (TestRunner $t) use ($window): void {
        $t->same(null, $window()[0]['previous']);
    },
    'select json recursive current next64 marks repeated recursive target with larger partition' => static function (TestRunner $t) use ($window): void {
        $leafSlugs = array_values(array_filter($window(), static fn (array $row): bool => $row['partition'] === ['option_id' => 106, 'attr' => 'slug']));
        $t->same(6, count($leafSlugs));
    },
    'select json recursive current next64 keeps jsonb route rows in current next window' => static function (TestRunner $t) use ($window): void {
        $priorities = array_values(array_map(static fn (array $row): mixed => $row['row']['atom'], array_filter($window(), static fn (array $row): bool => $row['partition'] === ['option_id' => 102, 'attr' => 'priority'])));
        $t->same([50, 40, 35, 50, 40, 35], $priorities);
    },
    'select json recursive current next64 maps recursive iteration for seed row' => static function (TestRunner $t) use ($window): void {
        $t->same(0, $window()[0]['recursiveIteration']);
    },
    'select json recursive current next64 maps recursive iteration for repeated leaf row' => static function (TestRunner $t) use ($window): void {
        $leafRows = array_values(array_filter($window(), static fn (array $row): bool => $row['partition'] === ['option_id' => 106, 'attr' => 'priority']));
        $t->same([6, 7, 6, 7, 6, 7], array_column($leafRows, 'recursiveIteration'));
    },
    'select json recursive current next64 records union duplicate cycle skip' => static function (TestRunner $t) use ($plan): void {
        $t->same('union-duplicate-cycle', $plan()['trace']['skipped'][0]['reason']);
    },
    'select json recursive current next64 derived select can filter window source priorities' => static function (TestRunner $t) use ($select, $sql): void {
        $rows = $select("SELECT option_name FROM ({$sql}) AS recursive_json WHERE attr = 'priority' AND atom >= 45 ORDER BY atom DESC");
        $t->same(['wp_route_leaf', 'wp_route_leaf', 'wp_route_cache', 'wp_route_cache', 'wp_route_store', 'wp_route_store'], array_column($rows, 'option_name'));
    },
    'select json recursive current next64 derived select can group recursive json source' => static function (TestRunner $t) use ($select, $sql): void {
        $rows = $select("SELECT attr, count(atom) AS total FROM ({$sql}) AS recursive_json GROUP BY attr ORDER BY attr");
        $t->same([27, 27, 27], array_column($rows, 'total'));
    },
    'select json recursive current next64 rejects missing partition column' => static function (TestRunner $t) use ($plan): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectRecursiveJsonMaterialization::jsonCurrentNextWindow($plan(), ['missing'], ['fullkey']));
    },
    'select json recursive current next64 rejects missing order column' => static function (TestRunner $t) use ($plan): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectRecursiveJsonMaterialization::jsonCurrentNextWindow($plan(), ['option_id'], ['missing']));
    },
    'select json recursive current next64 rejects empty partition list' => static function (TestRunner $t) use ($plan): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectRecursiveJsonMaterialization::jsonCurrentNextWindow($plan(), [], ['fullkey']));
    },
    'select json recursive current next64 rejects empty order list' => static function (TestRunner $t) use ($plan): void {
        $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectRecursiveJsonMaterialization::jsonCurrentNextWindow($plan(), ['option_id'], []));
    },
];

foreach ([101 => 3, 102 => 6, 103 => 3, 104 => 6, 105 => 3, 106 => 6] as $optionId => $partitionSize) {
    foreach (['slug', 'priority', 'enabled'] as $attr) {
        $tests["select json recursive current next64 option {$optionId} {$attr} partition size"] = static function (TestRunner $t) use ($window, $optionId, $attr, $partitionSize): void {
            $rows = array_values(array_filter($window(), static fn (array $row): bool => $row['partition'] === ['option_id' => $optionId, 'attr' => $attr]));
            $t->same($partitionSize, count($rows));
            $t->same(array_fill(0, $partitionSize, $partitionSize), array_column($rows, 'partitionSize'));
        };
        $tests["select json recursive current next64 option {$optionId} {$attr} row numbers"] = static function (TestRunner $t) use ($window, $optionId, $attr, $partitionSize): void {
            $rows = array_values(array_filter($window(), static fn (array $row): bool => $row['partition'] === ['option_id' => $optionId, 'attr' => $attr]));
            $t->same(range(1, $partitionSize), array_column($rows, 'rowNumber'));
        };
    }
}

foreach ([101, 102, 103, 104, 105, 106] as $optionId) {
    $tests["select json recursive current next64 option {$optionId} slug lead chain"] = static function (TestRunner $t) use ($window, $optionId): void {
        $rows = array_values(array_filter($window(), static fn (array $row): bool => $row['partition'] === ['option_id' => $optionId, 'attr' => 'slug']));
        $next = array_map(static fn (array $row): mixed => $row['next']['atom'] ?? null, $rows);
        $t->same(array_slice(array_column($rows, 'row'), 1), array_values(array_filter(array_column($rows, 'next'), static fn (mixed $row): bool => $row !== null)));
        $t->same(null, $next[count($next) - 1]);
    };
    $tests["select json recursive current next64 option {$optionId} priority lag chain"] = static function (TestRunner $t) use ($window, $optionId): void {
        $rows = array_values(array_filter($window(), static fn (array $row): bool => $row['partition'] === ['option_id' => $optionId, 'attr' => 'priority']));
        $previous = array_map(static fn (array $row): mixed => $row['previous']['atom'] ?? null, $rows);
        $t->same(null, $previous[0]);
        $t->same(array_column(array_slice($rows, 0, -1), 'row'), array_values(array_filter(array_column($rows, 'previous'), static fn (mixed $row): bool => $row !== null)));
    };
}

foreach (range(1, 10) as $limit) {
    $tests["select json recursive current next64 derived source limit {$limit}"] = static function (TestRunner $t) use ($select, $sql, $limit): void {
        $rows = $select("SELECT atom FROM ({$sql}) AS recursive_json WHERE attr = 'slug' ORDER BY atom LIMIT {$limit}");
        $t->same(min($limit, 18), count($rows));
    };
}

return $tests;
