<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteSelectSql;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$options = [
    [
        'option_id' => 1,
        'option_name' => 'site_navigation_tree',
        'autoload' => 'yes',
        'option_value' => '{"menus":[{"id":"root","enabled":1,"priority":10,"children":[{"id":"blog","enabled":1,"priority":7},{"id":"shop","enabled":0,"priority":5}]},{"id":"footer","enabled":1,"priority":2}]}',
    ],
    [
        'option_id' => 2,
        'option_name' => 'jsonb_navigation_tree',
        'autoload' => 'yes',
        'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode([
            'menus' => [
                [
                    'id' => 'media',
                    'enabled' => 1,
                    'priority' => 8,
                    'children' => [
                        ['id' => 'gallery', 'enabled' => 1, 'priority' => 4],
                    ],
                ],
            ],
        ])),
    ],
];

$sql = <<<'SQL'
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
    WHERE nav.depth < 2
),
flattened AS (
    SELECT nav.option_id AS option_id, nav.origin AS origin, nav.depth AS depth, id.atom AS node_id, priority.atom AS priority
    FROM nav
    JOIN wp_options AS host ON host.option_id = nav.option_id
    JOIN json_each(host.option_value, nav.root) AS id ON id.key = 'id'
    JOIN json_each(host.option_value, nav.root) AS priority ON priority.key = 'priority'
)
SELECT node_id,
       priority,
       lead(node_id, 1, 'end') OVER (ORDER BY priority DESC, node_id) AS next_node,
       last_value(node_id) OVER (ORDER BY priority DESC, node_id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS current_or_next
FROM flattened
ORDER BY priority DESC, node_id
SQL;

$rows = SQLiteSelectSql::execute($sql, ['wp_options' => $options]);

if (($argv[1] ?? '') === '--self-test') {
    assert(array_column($rows, 'node_id') === ['root', 'media', 'blog', 'shop', 'gallery', 'footer']);
    assert(array_column($rows, 'next_node') === ['media', 'blog', 'shop', 'gallery', 'footer', 'end']);
    echo "application-select-json-recursive-window-current-next54 self-test passed\n";
    return;
}

echo json_encode([
    'scenario' => 'application-select-json-recursive-window-current-next54',
    'applicationUse' => 'Copied wp_options navigation JSON and JSONB blobs can be recursively expanded through parser-level json_tree/json_each joins and then traversed with current/next window functions for migration previews without requiring ext/sqlite.',
    'sql' => $sql,
    'rows' => $rows,
    'dependency' => 'native PHP SELECT SQL recursive CTE, JSON table-valued source, and window executor; no ext/sqlite required',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
