<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSelectSql;

$options = [
    ['option_id' => 4, 'option_name' => 'active_plugins', 'autoload' => 'yes'],
    ['option_id' => 8, 'option_name' => 'rewrite_rules', 'autoload' => 'no'],
    ['option_id' => 12, 'option_name' => 'widget_text', 'autoload' => 'yes'],
];

$sql = "
WITH RECURSIVE import_queue(id, label) AS (
    VALUES (1, 'seed')
    UNION ALL
    SELECT id + 1, label || ':' || (id + 1)
    FROM import_queue
    WHERE id < 6
    LIMIT 4
)
SELECT id, label FROM import_queue
UNION ALL
SELECT option_id, option_name FROM wp_options WHERE autoload = 'no'
ORDER BY id
";

$rows = SQLiteSelectSql::execute($sql, ['wp_options' => $options]);

if (in_array('--self-test', $argv, true)) {
    $expected = [
        ['id' => 1, 'label' => 'seed'],
        ['id' => 2, 'label' => 'seed:2'],
        ['id' => 3, 'label' => 'seed:2:3'],
        ['id' => 4, 'label' => 'seed:2:3:4'],
        ['id' => 8, 'label' => 'rewrite_rules'],
    ];
    if ($rows !== $expected) {
        fwrite(STDERR, json_encode(['expected' => $expected, 'actual' => $rows], JSON_PRETTY_PRINT) . PHP_EOL);
        exit(1);
    }
    echo "application-compound-recursive-limit-current-source-next117 self-test passed\n";

    return;
}

echo json_encode($rows, JSON_PRETTY_PRINT) . PHP_EOL;
