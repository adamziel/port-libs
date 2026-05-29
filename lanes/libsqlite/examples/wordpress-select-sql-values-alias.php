<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$currentOptions = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.example', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://old.example', 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'blogname', 'option_value' => 'Old Site', 'autoload' => 'yes'],
    ['option_id' => 4, 'option_name' => 'active_plugins', 'option_value' => 'a:0:{}', 'autoload' => 'no'],
];

$sql = <<<SQL
SELECT name, value, autoload
FROM (VALUES
    ('siteurl', 'https://new.example', 'yes'),
    ('home', 'https://old.example', 'yes'),
    ('blogname', 'Ported Site', 'yes'),
    ('blogdescription', 'Imported tagline', 'yes')
) AS staged(name, value, autoload)
WHERE NOT EXISTS (
    SELECT 1 FROM wp_options
    WHERE option_name = name AND option_value IS value
)
ORDER BY name
SQL;

$rows = SQLiteSelectSql::execute($sql, ['wp_options' => $currentOptions]);

echo json_encode([
    'wordpressUse' => 'Preview inline VALUES import staging tuples with SQLite column-alias lists before applying WordPress wp_options updates/inserts without ext/sqlite.',
    'sql' => $sql,
    'candidateNames' => array_column($rows, 'name'),
    'rows' => $rows,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
