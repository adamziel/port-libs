<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSelectSql;

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'bytes' => 24],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'bytes' => 24],
    ['option_id' => 3, 'option_name' => 'blogname', 'autoload' => 'yes', 'bytes' => 9],
    ['option_id' => 4, 'option_name' => '_transient_feed', 'autoload' => 'no', 'bytes' => 12],
    ['option_id' => 5, 'option_name' => '_site_transient_update_plugins', 'autoload' => 'no', 'bytes' => 110],
    ['option_id' => 6, 'option_name' => 'rewrite_rules', 'autoload' => 'yes', 'bytes' => 2048],
];

$sql = <<<'SQL'
WITH RECURSIVE wanted(id) AS MATERIALIZED (
    VALUES (1)
    UNION ALL
    SELECT id + 1 FROM wanted WHERE id < 6 LIMIT 3 OFFSET 2
)
SELECT option_id, option_name, autoload
FROM wp_options
WHERE option_id IN (SELECT id FROM wanted)
ORDER BY option_id
SQL;

$rows = SQLiteSelectSql::execute($sql, ['wp_options' => $options]);
$payload = [
    'scenario' => 'application-select-recursive-cte-materialized-current-next26',
    'applicationUse' => 'Preview a bounded recursive import ID window over copied wp_options rows while preserving SQLite recursive-term LIMIT/OFFSET semantics for MATERIALIZED CTEs without requiring ext/sqlite.',
    'selectedOptionIds' => array_column($rows, 'option_id'),
    'selectedOptionNames' => array_column($rows, 'option_name'),
    'rows' => $rows,
];

if (PHP_SAPI === 'cli' && in_array('--self-test', $argv, true)) {
    assert($payload['selectedOptionIds'] === [3, 4, 5]);
    assert($payload['selectedOptionNames'] === ['blogname', '_transient_feed', '_site_transient_update_plugins']);
    echo "application-select-recursive-cte-materialized-current-next26 self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
