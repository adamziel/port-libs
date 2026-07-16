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

$baseSql = "
WITH RECURSIVE roots(option_id, root, depth, origin) AS MATERIALIZED (
    SELECT host.option_id, menu.fullkey, 0, host.option_name
    FROM wp_options AS host
    JOIN json_tree(host.option_value, '$.menus') AS menu ON menu.type = 'object' AND menu.path = '$.menus'
    WHERE host.autoload = 'yes'
    UNION ALL
    SELECT roots.option_id, child.fullkey, roots.depth + 1, roots.origin
    FROM roots
    JOIN wp_options AS host ON host.option_id = roots.option_id
    JOIN json_each(host.option_value, roots.root || '.children') AS child ON child.type = 'object'
    WHERE roots.depth < 3
)
SELECT roots.option_id AS option_id, roots.root AS root, roots.depth AS depth, node.atom AS node_id
FROM roots
JOIN wp_options AS host ON host.option_id = roots.option_id
JOIN json_each(host.option_value, roots.root) AS node ON node.key = 'id'
";

$tables = ['wp_options' => $settings];

$column = static fn (string $sql, string $column): array => array_column(
    SQLiteSelectSql::execute($sql, $tables),
    $column,
);

$cases = [
    'materialized recursive roots yield top level ids' => [$baseSql . ' WHERE roots.depth = 0 ORDER BY option_id, node_id', 'node_id', ['footer', 'root', 'media']],
    'materialized recursive roots yield child ids' => [$baseSql . ' WHERE roots.depth = 1 ORDER BY node_id', 'node_id', ['blog', 'gallery', 'privacy', 'shop', 'video']],
    'materialized recursive roots yield grandchild ids' => [$baseSql . ' WHERE roots.depth = 2 ORDER BY node_id', 'node_id', ['archive', 'drafts']],
    'materialized recursive roots preserve source option id' => [$baseSql . " WHERE node.atom = 'gallery'", 'option_id', [2]],
    'materialized recursive roots preserve json text source option id' => [$baseSql . " WHERE node.atom = 'archive'", 'option_id', [1]],
    'materialized recursive roots preserve jsonb source option id' => [$baseSql . " WHERE node.atom = 'video'", 'option_id', [2]],
    'materialized recursive roots expose selected root paths' => [$baseSql . " WHERE node.atom IN ('blog', 'gallery') ORDER BY node_id", 'root', ['$.menus[0].children[0]', '$.menus[0].children[0]']],
    'materialized recursive roots expose depth order' => [$baseSql . " WHERE node.atom IN ('root', 'blog', 'archive') ORDER BY depth, node_id", 'depth', [0, 1, 2]],
    'materialized recursive roots can filter by origin' => [$baseSql . " WHERE roots.origin = 'jsonb_navigation_tree' ORDER BY node_id", 'node_id', ['gallery', 'media', 'video']],
    'materialized recursive roots skip non autoload anchor rows' => [$baseSql . " WHERE roots.origin = 'empty_navigation_tree'", 'node_id', []],
    'materialized recursive roots skip null json anchor rows' => [$baseSql . " WHERE roots.origin = 'null_navigation_tree'", 'node_id', []],
    'materialized recursive roots support distinct depth values' => ['SELECT DISTINCT depth FROM (' . $baseSql . ') AS flattened ORDER BY depth', 'depth', [0, 1, 2]],
    'materialized recursive roots support grouped counts' => ['SELECT depth, count(node_id) AS total FROM (' . $baseSql . ') AS flattened GROUP BY depth ORDER BY depth', 'total', [3, 5, 2]],
    'materialized recursive roots support grouped having' => ['SELECT depth, count(node_id) AS total FROM (' . $baseSql . ') AS flattened GROUP BY depth HAVING count(node_id) >= 4 ORDER BY depth', 'depth', [1]],
    'materialized recursive roots support final limit' => [$baseSql . ' ORDER BY node_id LIMIT 4', 'node_id', ['archive', 'blog', 'drafts', 'footer']],
    'materialized recursive roots support comma limit' => [$baseSql . ' ORDER BY node_id LIMIT 3, 4', 'node_id', ['footer', 'gallery', 'media', 'privacy']],
    'materialized recursive roots support expression order' => [$baseSql . ' ORDER BY node_id LIMIT 4', 'node_id', ['archive', 'blog', 'drafts', 'footer']],
    'materialized recursive roots support scalar labels' => [$baseSql . " WHERE node.atom IN ('root', 'media') ORDER BY option_id", 'node_id', ['root', 'media']],
    'materialized recursive roots support json_each current root priorities' => ["
WITH RECURSIVE roots(option_id, root, depth) AS MATERIALIZED (
    SELECT host.option_id, menu.fullkey, 0
    FROM wp_options AS host
    JOIN json_tree(host.option_value, '$.menus') AS menu ON menu.type = 'object' AND menu.path = '$.menus'
    WHERE host.autoload = 'yes'
    UNION ALL
    SELECT roots.option_id, child.fullkey, roots.depth + 1
    FROM roots JOIN wp_options AS host ON host.option_id = roots.option_id
    JOIN json_each(host.option_value, roots.root || '.children') AS child ON child.type = 'object'
    WHERE roots.depth < 3
)
SELECT field.atom AS priority
FROM roots
JOIN wp_options AS host ON host.option_id = roots.option_id
JOIN json_each(host.option_value, roots.root) AS field ON field.key = 'priority'
ORDER BY priority DESC LIMIT 5", 'priority', [10, 8, 7, 5, 4]],
    'materialized recursive roots support enabled field filters' => ["
WITH RECURSIVE roots(option_id, root, depth) AS MATERIALIZED (
    SELECT host.option_id, menu.fullkey, 0
    FROM wp_options AS host
    JOIN json_tree(host.option_value, '$.menus') AS menu ON menu.type = 'object' AND menu.path = '$.menus'
    WHERE host.autoload = 'yes'
    UNION ALL
    SELECT roots.option_id, child.fullkey, roots.depth + 1
    FROM roots JOIN wp_options AS host ON host.option_id = roots.option_id
    JOIN json_each(host.option_value, roots.root || '.children') AS child ON child.type = 'object'
    WHERE roots.depth < 3
)
SELECT id.atom AS node_id
FROM roots
JOIN wp_options AS host ON host.option_id = roots.option_id
JOIN json_each(host.option_value, roots.root) AS enabled ON enabled.key = 'enabled' AND enabled.atom = 1
JOIN json_each(host.option_value, roots.root) AS id ON id.key = 'id'
ORDER BY node_id", 'node_id', ['archive', 'blog', 'footer', 'gallery', 'media', 'privacy', 'root']],
];

foreach ($cases as $name => [$sql, $field, $expected]) {
    $tests['select recursive lateral json materialized current next49 ' . $name] = static function (TestRunner $t) use ($column, $sql, $field, $expected): void {
        $t->same($expected, $column($sql, $field));
    };
}

foreach (range(1, 20) as $limit) {
    $tests['select recursive lateral json materialized current next49 generated depth limit ' . $limit] = static function (TestRunner $t) use ($column, $baseSql, $limit): void {
        $rows = $column($baseSql . ' ORDER BY depth, node_id LIMIT ' . $limit, 'node_id');
        $t->same(min($limit, 10), count($rows));
        $t->same('footer', $rows[0] ?? null);
    };
}

foreach (range(0, 14) as $offset) {
    $tests['select recursive lateral json materialized current next49 generated offset ' . $offset] = static function (TestRunner $t) use ($column, $baseSql, $offset): void {
        $rows = $column($baseSql . ' ORDER BY node_id LIMIT 2 OFFSET ' . $offset, 'node_id');
        $t->same(max(0, min(2, 10 - $offset)), count($rows));
    };
}

$tests['select recursive lateral json materialized current next49 trace records json-root dependency'] = static function (TestRunner $t) use ($tables): void {
    $trace = SQLiteSelectSql::recursiveCteCycleTrace("
WITH RECURSIVE roots(option_id, root, depth) AS MATERIALIZED (
    SELECT host.option_id, menu.fullkey, 0
    FROM wp_options AS host
    JOIN json_tree(host.option_value, '$.menus') AS menu ON menu.type = 'object' AND menu.path = '$.menus'
    WHERE host.autoload = 'yes'
    UNION ALL
    SELECT roots.option_id, child.fullkey, roots.depth + 1
    FROM roots
    JOIN wp_options AS host ON host.option_id = roots.option_id
    JOIN json_each(host.option_value, roots.root || '.children') AS child ON child.type = 'object'
    WHERE roots.depth < 2
)
SELECT root FROM roots", $tables);

    $t->same('roots', $trace['name']);
    $t->same(['option_id', 'root', 'depth'], $trace['columns']);
    $t->true(in_array('sqlite-recursive-cte-current-row', $trace['dependencies'], true));
    $t->same('$.menus[0]', $trace['trace'][0]['current']['root'] ?? null);
};

$tests['select recursive lateral json materialized current next49 plan keeps materialized cte source visible'] = static function (TestRunner $t) use ($tables): void {
    $plan = SQLiteSelectSql::plan("WITH flattened AS MATERIALIZED (SELECT option_id AS node_id FROM wp_options WHERE autoload = 'yes') SELECT node_id FROM flattened ORDER BY node_id LIMIT 2", $tables);
    $t->same(['flattened'], $plan['with']);
    $t->same(1, $plan['from'][0]['node_id'] ?? null);
    $t->same([['column' => 'node_id']], $plan['orderBy']);
};

$tests['select recursive lateral json materialized current next49 malformed dynamic root still raises'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteSelectSql::execute("
WITH RECURSIVE roots(option_id, root, depth) AS MATERIALIZED (
    VALUES (1, '$.bad[', 0)
    UNION ALL
    SELECT option_id, root, depth + 1 FROM roots WHERE depth < 1
)
SELECT child.key FROM roots JOIN wp_options AS host ON host.option_id = roots.option_id JOIN json_each(host.option_value, roots.root) AS child ON child.type = 'object'", [
        'wp_options' => [['option_id' => 1, 'option_value' => '{"menus":[]}']],
    ]));
};

return $tests;
