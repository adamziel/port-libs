<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteSelectRecursiveJsonMaterialization;
use PortLibs\LibSqlite\SQLiteSelectSql;

$options = [
    ['option_id' => 1, 'option_name' => 'plugin_root_routes', 'autoload' => 'yes', 'option_value' => '{"next":[2,3],"rules":[{"name":"root-cache","priority":7,"enabled":1},{"name":"root-seo","priority":5,"enabled":1}]}'],
    ['option_id' => 2, 'option_name' => 'plugin_media_routes', 'autoload' => 'yes', 'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(['next' => [4, 5], 'rules' => [['name' => 'media-gallery', 'priority' => 9, 'enabled' => 1], ['name' => 'media-video', 'priority' => 3, 'enabled' => 0]]]))],
    ['option_id' => 3, 'option_name' => 'plugin_forms_routes', 'autoload' => 'no', 'option_value' => '{"next":[5,6],"rules":[{"name":"forms-contact","priority":4,"enabled":1},{"name":"forms-captcha","priority":2,"enabled":0}]}'],
    ['option_id' => 4, 'option_name' => 'plugin_shop_routes', 'autoload' => 'yes', 'option_value' => '{"next":[7],"rules":[{"name":"shop-cart","priority":8,"enabled":1},{"name":"shop-checkout","priority":6,"enabled":1}]}'],
    ['option_id' => 5, 'option_name' => 'plugin_cache_routes', 'autoload' => 'yes', 'option_value' => '{"next":[7],"rules":[{"name":"cache-page","priority":10,"enabled":1},{"name":"cache-object","priority":1,"enabled":0}]}'],
    ['option_id' => 6, 'option_name' => 'plugin_theme_routes', 'autoload' => 'no', 'option_value' => '{"next":[],"rules":[{"name":"theme-style","priority":6,"enabled":1},{"name":"theme-font","priority":2,"enabled":1}]}'],
    ['option_id' => 7, 'option_name' => 'plugin_leaf_routes', 'autoload' => 'yes', 'option_value' => '{"next":[2],"rules":[{"name":"leaf-sync","priority":11,"enabled":1},{"name":"leaf-clean","priority":0,"enabled":0}]}'],
    ['option_id' => 8, 'option_name' => 'plugin_orphan_routes', 'autoload' => 'no', 'option_value' => '{"next":[],"rules":[{"name":"orphan","priority":99,"enabled":0}]}'],
];

$tables = ['wp_options' => $options];
$sql = "WITH RECURSIVE crawl(option_id, depth, route) AS MATERIALIZED (
            VALUES (1, 0, 'seed')
            UNION
            SELECT CAST(edge.atom AS INTEGER), crawl.depth + 1, host.option_name
              FROM crawl
              JOIN wp_options AS host ON host.option_id = crawl.option_id
              JOIN json_each(host.option_value, '$.next') AS edge ON edge.type = 'integer'
             WHERE crawl.depth < 4
             ORDER BY depth DESC, option_id ASC
             LIMIT 7 OFFSET 1
        )
        SELECT crawl.option_id AS option_id,
               crawl.depth AS depth,
               crawl.route AS route,
               host.option_name AS option_name,
               rule.key AS attr,
               rule.atom AS atom,
               rule.fullkey AS fullkey
          FROM crawl
          JOIN wp_options AS host ON host.option_id = crawl.option_id
          JOIN json_tree(host.option_value, '$.rules') AS rule ON rule.type IN ('text', 'integer', 'true', 'false')
         ORDER BY crawl.depth, crawl.option_id, rule.fullkey";

$plan = static fn (): array => SQLiteSelectRecursiveJsonMaterialization::materialize($sql, $tables, ['option_id', 'attr'], ['fullkey']);
$select = static fn (string $selectSql): array => SQLiteSelectSql::execute($selectSql, $tables);

$tests = [
    'recursive json current next52 materializes offset limited rule rows' => static function (TestRunner $t) use ($plan): void {
        $t->same(42, count($plan()['rows']));
    },
    'recursive json current next52 skips offset seed from emitted rows' => static function (TestRunner $t) use ($plan): void {
        $t->same(false, in_array('plugin_root_routes', array_column($plan()['rows'], 'option_name'), true));
    },
    'recursive json current next52 keeps generated seed in trace current chain' => static function (TestRunner $t) use ($plan): void {
        $t->same(1, $plan()['recursiveCurrentNext'][0]['current']['option_id']);
    },
    'recursive json current next52 first emitted row follows queue order' => static function (TestRunner $t) use ($plan): void {
        $t->same(2, $plan()['trace']['rows'][0]['option_id']);
    },
    'recursive json current next52 union cycle dedup skips repeated leaf edge' => static function (TestRunner $t) use ($plan): void {
        $skipped = $plan()['trace']['skipped'];
        $t->same(1, count($skipped));
        $t->same(['option_id' => 2, 'depth' => 4, 'route' => 'plugin_leaf_routes'], $skipped[0]['row']);
        $t->same('union-duplicate-cycle', $skipped[0]['reason']);
    },
    'recursive json current next52 records recursive dependency' => static function (TestRunner $t) use ($plan): void {
        $t->true(in_array('sqlite-recursive-current-next-materialization', $plan()['dependencies'], true));
    },
    'recursive json current next52 current next chain includes terminal null' => static function (TestRunner $t) use ($plan): void {
        $pairs = $plan()['recursiveCurrentNext'];
        $t->same(null, $pairs[count($pairs) - 1]['next']);
    },
    'recursive json current next52 derived index pairs per option attr' => static function (TestRunner $t) use ($plan): void {
        $t->same(42, count($plan()['currentNext']));
    },
    'recursive json current next52 indexes option and attr columns' => static function (TestRunner $t) use ($plan): void {
        $t->same(['option_id', 'attr'], $plan()['indexColumns']);
    },
    'recursive json current next52 orders duplicate attr rows by fullkey' => static function (TestRunner $t) use ($plan): void {
        $pairs = SQLiteSelectRecursiveJsonMaterialization::currentNextFor($plan(), ['option_id' => 2, 'attr' => 'name']);
        $t->same(['media-gallery', 'media-gallery'], [$pairs[0]['current']['atom'], $pairs[0]['next']['atom']]);
    },
    'recursive json current next52 preserves jsonb option rows' => static function (TestRunner $t) use ($plan): void {
        $rows = array_values(array_filter($plan()['rows'], static fn (array $row): bool => $row['option_id'] === 2 && $row['attr'] === 'priority'));
        $t->same([9, 3, 9, 3], array_column($rows, 'atom'));
    },
    'recursive json current next52 derived select filters high priorities' => static function (TestRunner $t) use ($sql, $tables): void {
        $rows = SQLiteSelectSql::execute("SELECT option_name FROM ({$sql}) AS materialized WHERE attr = 'priority' AND atom >= 9 ORDER BY atom", $tables);
        $t->same(['plugin_media_routes', 'plugin_media_routes', 'plugin_cache_routes', 'plugin_leaf_routes', 'plugin_leaf_routes'], array_column($rows, 'option_name'));
    },
    'recursive json current next52 derived select groups enabled fields' => static function (TestRunner $t) use ($sql, $tables): void {
        $rows = SQLiteSelectSql::execute("SELECT attr, count(atom) AS total FROM ({$sql}) AS materialized GROUP BY attr ORDER BY attr", $tables);
        $t->same([14, 14, 14], array_column($rows, 'total'));
    },
    'recursive json current next52 derived select supports comma limit' => static function (TestRunner $t) use ($sql, $tables): void {
        $rows = SQLiteSelectSql::execute("SELECT atom FROM ({$sql}) AS materialized WHERE attr = 'name' ORDER BY atom LIMIT 2, 3", $tables);
        $t->same(['forms-captcha', 'forms-contact', 'leaf-clean'], array_column($rows, 'atom'));
    },
];

foreach ([2 => 12, 3 => 6, 4 => 6, 5 => 6, 6 => 0, 7 => 12] as $optionId => $rowCount) {
    $tests['recursive json current next52 option ' . $optionId . ' has three attrs per two rules'] = static function (TestRunner $t) use ($plan, $optionId): void {
        $rows = array_values(array_filter($plan()['rows'], static fn (array $row): bool => $row['option_id'] === $optionId));
        $expected = [2 => 12, 3 => 6, 4 => 6, 5 => 6, 6 => 0, 7 => 12][$optionId];
        $t->same($expected, count($rows));
    };
    $tests['recursive json current next52 option ' . $optionId . ' name lookup has two rows'] = static function (TestRunner $t) use ($plan, $optionId): void {
        $expected = [2 => 4, 3 => 2, 4 => 2, 5 => 2, 6 => 0, 7 => 4][$optionId];
        $t->same($expected, count(SQLiteSelectRecursiveJsonMaterialization::currentNextFor($plan(), ['option_id' => $optionId, 'attr' => 'name'])));
    };
    $tests['recursive json current next52 option ' . $optionId . ' priority lookup has two rows'] = static function (TestRunner $t) use ($plan, $optionId): void {
        $expected = [2 => 4, 3 => 2, 4 => 2, 5 => 2, 6 => 0, 7 => 4][$optionId];
        $t->same($expected, count(SQLiteSelectRecursiveJsonMaterialization::currentNextFor($plan(), ['option_id' => $optionId, 'attr' => 'priority'])));
    };
}

foreach (range(0, 6) as $position) {
    $tests['recursive json current next52 queue pair position ' . $position . ' has stable current position'] = static function (TestRunner $t) use ($plan, $position): void {
        $pair = $plan()['recursiveCurrentNext'][$position];
        $t->same($position, $pair['currentPosition']);
    };
    $tests['recursive json current next52 queue pair position ' . $position . ' reports generated counts'] = static function (TestRunner $t) use ($plan, $position): void {
        $pair = $plan()['recursiveCurrentNext'][$position];
        $t->true($pair['generatedCount'] >= 0);
        $t->true($pair['acceptedNextCount'] >= 0);
    };
}

foreach (range(1, 20) as $limit) {
    $tests['recursive json current next52 derived select limit ' . $limit] = static function (TestRunner $t) use ($select, $sql, $limit): void {
        $rows = $select("SELECT atom FROM ({$sql}) AS materialized WHERE attr = 'name' ORDER BY atom LIMIT {$limit}");
        $t->same(min($limit, 14), count($rows));
    };
}

foreach (['root-cache', 'root-seo', 'orphan'] as $missing) {
    $tests['recursive json current next52 excludes non emitted rule ' . $missing] = static function (TestRunner $t) use ($plan, $missing): void {
        $t->same(false, in_array($missing, array_column($plan()['rows'], 'atom'), true));
    };
}

$tests['recursive json current next52 rejects malformed trace current lookup criteria'] = static function (TestRunner $t) use ($plan): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectRecursiveJsonMaterialization::currentNextFor($plan(), ['option_id' => 2]));
};

$tests['recursive json current next52 rejects non json recursive materialization'] = static function (TestRunner $t) use ($tables): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectRecursiveJsonMaterialization::materialize('WITH RECURSIVE c(x) AS (VALUES(1) UNION ALL SELECT x + 1 FROM c WHERE x < 2) SELECT x FROM c', $tables, ['x']));
};

return $tests;
