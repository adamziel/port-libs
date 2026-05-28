<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteSelectSql;

$settings = [
    [
        'option_id' => 1,
        'option_name' => 'site_navigation_tree',
        'autoload' => 'yes',
        'option_value' => json_encode([
            'menus' => [
                [
                    'id' => 'root',
                    'label' => 'Root',
                    'enabled' => 1,
                    'priority' => 10,
                    'children' => [
                        [
                            'id' => 'blog',
                            'label' => 'Blog',
                            'enabled' => 1,
                            'priority' => 7,
                            'children' => [
                                ['id' => 'archive', 'label' => 'Archive', 'enabled' => 1, 'priority' => 3],
                                ['id' => 'drafts', 'label' => 'Drafts', 'enabled' => 0, 'priority' => 1],
                            ],
                        ],
                        [
                            'id' => 'shop',
                            'label' => 'Shop',
                            'enabled' => 0,
                            'priority' => 5,
                        ],
                    ],
                ],
                [
                    'id' => 'footer',
                    'label' => 'Footer',
                    'enabled' => 1,
                    'priority' => 2,
                    'children' => [
                        ['id' => 'privacy', 'label' => 'Privacy', 'enabled' => 1, 'priority' => 1],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR),
    ],
    [
        'option_id' => 2,
        'option_name' => 'jsonb_navigation_tree',
        'autoload' => 'yes',
        'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode([
            'menus' => [
                [
                    'id' => 'media',
                    'label' => 'Media',
                    'enabled' => 1,
                    'priority' => 8,
                    'children' => [
                        ['id' => 'gallery', 'label' => 'Gallery', 'enabled' => 1, 'priority' => 4],
                        ['id' => 'video', 'label' => 'Video', 'enabled' => 0, 'priority' => 2],
                    ],
                ],
            ],
        ])),
    ],
    [
        'option_id' => 3,
        'option_name' => 'empty_navigation_tree',
        'autoload' => 'no',
        'option_value' => '{"menus":[]}',
    ],
    [
        'option_id' => 4,
        'option_name' => 'null_navigation_tree',
        'autoload' => 'no',
        'option_value' => null,
    ],
];

$tables = ['wp_options' => $settings];
$baseSql = <<<'SQL'
WITH RECURSIVE nav(option_id, origin, root, depth) AS MATERIALIZED (
    SELECT host.option_id, host.option_name, menu.fullkey, 0
    FROM wp_options AS host
    JOIN json_tree(host.option_value, '$.menus') AS menu ON menu.type = 'object' AND menu.path = '$.menus'
    WHERE host.autoload = 'yes'
    UNION ALL
    SELECT nav.option_id, nav.origin, child.fullkey, nav.depth + 1
    FROM nav
    JOIN wp_options AS host ON host.option_id = nav.option_id
    JOIN json_each(host.option_value, nav.root || '.children') AS child ON child.type = 'object'
    WHERE nav.depth < 3
)
SELECT nav.option_id AS option_id, nav.origin AS origin, nav.depth AS depth, id.atom AS node_id, priority.atom AS priority, enabled.atom AS enabled
FROM nav
JOIN wp_options AS host ON host.option_id = nav.option_id
JOIN json_each(host.option_value, nav.root) AS id ON id.key = 'id'
JOIN json_each(host.option_value, nav.root) AS priority ON priority.key = 'priority'
JOIN json_each(host.option_value, nav.root) AS enabled ON enabled.key = 'enabled'
SQL;

$run = static fn (string $tail): array => SQLiteSelectSql::execute(
    preg_match('/^\s*(?:SELECT|WITH)\b/i', $tail) === 1 ? $tail : $baseSql . "\n" . $tail,
    $tables,
);
$column = static fn (string $tail, string $name): array => array_column($run($tail), $name);
$cell = static fn (string $tail, string $name, int $index): mixed => $run($tail)[$index][$name] ?? null;

$cases = [
    'recursive json rows sorted by priority' => ["ORDER BY priority DESC, node_id", 'node_id', ['root', 'media', 'blog', 'shop', 'gallery', 'archive', 'footer', 'video', 'drafts', 'privacy']],
    'recursive json rows expose depth order' => ["ORDER BY depth, priority DESC", 'depth', [0, 0, 0, 1, 1, 1, 1, 1, 2, 2]],
    'lead finds next priority node' => ["SELECT node_id, lead(node_id) OVER (ORDER BY priority DESC, node_id) AS next_node FROM (" . $baseSql . ") AS flattened ORDER BY priority DESC, node_id", 'next_node', ['media', 'blog', 'shop', 'gallery', 'archive', 'footer', 'video', 'drafts', 'privacy', null]],
    'lag finds previous priority node' => ["SELECT node_id, lag(node_id) OVER (ORDER BY priority DESC, node_id) AS previous_node FROM (" . $baseSql . ") AS flattened ORDER BY priority DESC, node_id", 'previous_node', [null, 'root', 'media', 'blog', 'shop', 'gallery', 'archive', 'footer', 'video', 'drafts']],
    'lead offset default crosses jsonb rows' => ["SELECT node_id, lead(node_id, 2, 'tail') OVER (ORDER BY priority DESC, node_id) AS next2 FROM (" . $baseSql . ") AS flattened ORDER BY priority DESC, node_id", 'next2', ['blog', 'shop', 'gallery', 'archive', 'footer', 'video', 'drafts', 'privacy', 'tail', 'tail']],
    'lag offset default crosses jsonb rows' => ["SELECT node_id, lag(node_id, 2, 'head') OVER (ORDER BY priority DESC, node_id) AS prev2 FROM (" . $baseSql . ") AS flattened ORDER BY priority DESC, node_id", 'prev2', ['head', 'head', 'root', 'media', 'blog', 'shop', 'gallery', 'archive', 'footer', 'video']],
    'partition lead stays inside source option' => ["SELECT node_id, lead(node_id, 1, 'done') OVER (PARTITION BY option_id ORDER BY priority DESC, node_id) AS next_node FROM (" . $baseSql . ") AS flattened ORDER BY option_id, priority DESC, node_id", 'next_node', ['blog', 'shop', 'archive', 'footer', 'drafts', 'privacy', 'done', 'gallery', 'video', 'done']],
    'partition lag stays inside source option' => ["SELECT node_id, lag(node_id, 1, 'start') OVER (PARTITION BY option_id ORDER BY priority DESC, node_id) AS previous_node FROM (" . $baseSql . ") AS flattened ORDER BY option_id, priority DESC, node_id", 'previous_node', ['start', 'root', 'blog', 'shop', 'archive', 'footer', 'drafts', 'start', 'media', 'gallery']],
    'current following frame first value keeps current node' => ["SELECT node_id, first_value(node_id) OVER (ORDER BY priority DESC, node_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS current_node FROM (" . $baseSql . ") AS flattened ORDER BY priority DESC, node_id", 'current_node', ['root', 'media', 'blog', 'shop', 'gallery', 'archive', 'footer', 'video', 'drafts', 'privacy']],
    'current following frame last value sees next node' => ["SELECT node_id, last_value(node_id) OVER (ORDER BY priority DESC, node_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS next_node FROM (" . $baseSql . ") AS flattened ORDER BY priority DESC, node_id", 'next_node', ['media', 'blog', 'shop', 'gallery', 'archive', 'footer', 'video', 'drafts', 'privacy', 'privacy']],
    'current following nth value sees next node' => ["SELECT node_id, nth_value(node_id, 2) OVER (ORDER BY priority DESC, node_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS next_node FROM (" . $baseSql . ") AS flattened ORDER BY priority DESC, node_id", 'next_node', ['media', 'blog', 'shop', 'gallery', 'archive', 'footer', 'video', 'drafts', 'privacy', null]],
    'partition current following last value respects option boundary' => ["SELECT node_id, last_value(node_id) OVER (PARTITION BY option_id ORDER BY priority DESC, node_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS next_node FROM (" . $baseSql . ") AS flattened ORDER BY option_id, priority DESC, node_id", 'next_node', ['blog', 'shop', 'archive', 'footer', 'drafts', 'privacy', 'privacy', 'gallery', 'video', 'video']],
    'partition current following nth value respects option boundary' => ["SELECT node_id, nth_value(node_id, 2) OVER (PARTITION BY option_id ORDER BY priority DESC, node_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS next_node FROM (" . $baseSql . ") AS flattened ORDER BY option_id, priority DESC, node_id", 'next_node', ['blog', 'shop', 'archive', 'footer', 'drafts', 'privacy', null, 'gallery', 'video', null]],
    'preceding current frame first value sees prior node' => ["SELECT node_id, first_value(node_id) OVER (ORDER BY priority DESC, node_id ROWS BETWEEN 1 PRECEDING AND CURRENT ROW) AS previous_node FROM (" . $baseSql . ") AS flattened ORDER BY priority DESC, node_id", 'previous_node', ['root', 'root', 'media', 'blog', 'shop', 'gallery', 'archive', 'footer', 'video', 'drafts']],
    'range current following last value follows priority range' => ["SELECT node_id, last_value(node_id) OVER (ORDER BY priority DESC RANGE BETWEEN CURRENT ROW AND 2 FOLLOWING) AS range_tail FROM (" . $baseSql . ") AS flattened ORDER BY priority DESC, node_id", 'range_tail', ['root', 'media', 'blog', 'shop', 'gallery', 'archive', 'video', 'video', 'drafts', 'drafts']],
    'groups current following first value sees peer leader' => ["SELECT node_id, first_value(node_id) OVER (ORDER BY enabled DESC GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS group_head FROM (" . $baseSql . ") AS flattened ORDER BY priority DESC, node_id", 'group_head', ['root', 'root', 'root', 'shop', 'root', 'root', 'root', 'shop', 'shop', 'root']],
    'groups current following last value sees next peer group tail' => ["SELECT node_id, last_value(node_id) OVER (ORDER BY enabled DESC GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS group_tail FROM (" . $baseSql . ") AS flattened ORDER BY priority DESC, node_id", 'group_tail', ['drafts', 'drafts', 'drafts', 'drafts', 'drafts', 'drafts', 'drafts', 'drafts', 'drafts', 'drafts']],
    'recursive json window can order final rows by window alias' => ["SELECT node_id, row_number() OVER (ORDER BY priority DESC, node_id) AS rn FROM (" . $baseSql . ") AS flattened ORDER BY rn LIMIT 4", 'node_id', ['root', 'media', 'blog', 'shop']],
    'recursive json window supports comma limit after window order' => ["SELECT node_id, row_number() OVER (ORDER BY priority DESC, node_id) AS rn FROM (" . $baseSql . ") AS flattened ORDER BY rn LIMIT 3, 4", 'node_id', ['shop', 'gallery', 'archive', 'footer']],
    'recursive json window supports distinct projected pairs' => ["SELECT DISTINCT enabled, first_value(enabled) OVER (PARTITION BY enabled ORDER BY priority DESC ROWS BETWEEN CURRENT ROW AND CURRENT ROW) AS current_enabled FROM (" . $baseSql . ") AS flattened ORDER BY enabled", 'current_enabled', [0, 1]],
];

foreach ($cases as $name => [$tail, $field, $expected]) {
    $tests['select json recursive window current next54 ' . $name] = static function (TestRunner $t) use ($column, $tail, $field, $expected): void {
        $t->same($expected, $column($tail, $field));
    };
}

foreach (range(1, 18) as $limit) {
    $tests['select json recursive window current next54 generated priority limit ' . $limit] = static function (TestRunner $t) use ($column, $baseSql, $limit): void {
        $rows = $column("SELECT node_id, row_number() OVER (ORDER BY priority DESC, node_id) AS rn FROM (" . $baseSql . ") AS flattened ORDER BY rn LIMIT " . $limit, 'node_id');
        $t->same(min($limit, 10), count($rows));
        $t->same('root', $rows[0] ?? null);
    };
}

foreach (range(0, 14) as $offset) {
    $tests['select json recursive window current next54 generated priority offset ' . $offset] = static function (TestRunner $t) use ($column, $baseSql, $offset): void {
        $rows = $column("SELECT node_id, lead(node_id) OVER (ORDER BY priority DESC, node_id) AS next_node FROM (" . $baseSql . ") AS flattened ORDER BY node_id LIMIT 2 OFFSET " . $offset, 'node_id');
        $t->same(max(0, min(2, 10 - $offset)), count($rows));
    };
}

$tests['select json recursive window current next54 plan records value-window frame metadata'] = static function (TestRunner $t) use ($baseSql, $tables): void {
    $plan = SQLiteSelectSql::plan("SELECT node_id, last_value(node_id) OVER (ORDER BY priority DESC, node_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS next_node FROM (" . $baseSql . ") AS flattened ORDER BY next_node", $tables);
    $t->same('window', $plan['select'][1]['type']);
    $t->same('last_value', $plan['select'][1]['function']);
    $t->same(['unit' => 'ROWS', 'preceding' => 0, 'following' => 1, 'exclude' => 'NO OTHERS'], $plan['select'][1]['frame']);
    $t->same([['column' => 'next_node']], $plan['orderBy']);
};

$tests['select json recursive window current next54 trace keeps current recursive json root'] = static function (TestRunner $t) use ($tables): void {
    $trace = SQLiteSelectSql::recursiveCteCycleTrace(<<<'SQL'
WITH RECURSIVE nav(option_id, root, depth) AS MATERIALIZED (
    SELECT host.option_id, menu.fullkey, 0
    FROM wp_options AS host
    JOIN json_tree(host.option_value, '$.menus') AS menu ON menu.type = 'object' AND menu.path = '$.menus'
    WHERE host.autoload = 'yes'
    UNION ALL
    SELECT nav.option_id, child.fullkey, nav.depth + 1
    FROM nav
    JOIN wp_options AS host ON host.option_id = nav.option_id
    JOIN json_each(host.option_value, nav.root || '.children') AS child ON child.type = 'object'
    WHERE nav.depth < 2
)
SELECT root FROM nav
SQL, $tables);

    $t->same('nav', $trace['name']);
    $t->same(['option_id', 'root', 'depth'], $trace['columns']);
    $t->same('$.menus[0]', $trace['trace'][0]['current']['root'] ?? null);
    $t->true(in_array('sqlite-recursive-cte-current-row', $trace['dependencies'], true));
};

$tests['select json recursive window current next54 malformed dynamic child root raises'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute(<<<'SQL'
WITH RECURSIVE nav(option_id, root, depth) AS MATERIALIZED (
    VALUES (1, '$.bad[', 0)
    UNION ALL
    SELECT option_id, root, depth + 1 FROM nav WHERE depth < 1
)
SELECT child.key FROM nav JOIN wp_options AS host ON host.option_id = nav.option_id JOIN json_each(host.option_value, nav.root) AS child ON child.type = 'object'
SQL, [
        'wp_options' => [['option_id' => 1, 'option_value' => '{"menus":[]}']],
    ]));
};

return $tests;
