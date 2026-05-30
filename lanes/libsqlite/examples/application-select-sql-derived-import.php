<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSelectSql;

$tables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://old.example', 'autoload' => 'yes'],
        ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://old.example', 'autoload' => 'yes'],
        ['option_id' => 3, 'option_name' => 'blogname', 'option_value' => 'Old Site', 'autoload' => 'yes'],
        ['option_id' => 4, 'option_name' => 'active_plugins', 'option_value' => 'a:0:{}', 'autoload' => 'no'],
    ],
    'wp_options_stage' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'https://new.example', 'autoload' => 'yes'],
        ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'https://new.example', 'autoload' => 'yes'],
        ['option_id' => 3, 'option_name' => 'blogname', 'option_value' => 'Ported Site', 'autoload' => 'yes'],
        ['option_id' => 5, 'option_name' => 'blogdescription', 'option_value' => 'Just another port', 'autoload' => 'yes'],
    ],
];

$sql = <<<'SQL'
SELECT op, name
FROM (
    SELECT 'update' AS op, staged.option_name AS name, staged.option_id AS rank
    FROM (
        SELECT option_id, option_name, option_value
        FROM wp_options_stage
        WHERE autoload = 'yes'
    ) AS staged
    JOIN wp_options AS current ON staged.option_name = current.option_name
    WHERE staged.option_value IS NOT current.option_value
    UNION ALL
    SELECT 'insert' AS op, staged.option_name AS name, staged.option_id AS rank
    FROM (
        SELECT option_id, option_name
        FROM wp_options_stage
        WHERE autoload = 'yes'
    ) AS staged
    LEFT JOIN wp_options AS current ON staged.option_name = current.option_name
    WHERE current.option_id IS NULL
) AS import_plan
ORDER BY rank
SQL;

$rows = SQLiteSelectSql::execute($sql, $tables);

if (($argv[1] ?? null) === '--self-test') {
    $expected = [
        ['op' => 'update', 'name' => 'siteurl'],
        ['op' => 'update', 'name' => 'home'],
        ['op' => 'update', 'name' => 'blogname'],
        ['op' => 'insert', 'name' => 'blogdescription'],
    ];
    if ($rows !== $expected) {
        fwrite(STDERR, "Unexpected derived import rows\n");
        var_export($rows);
        fwrite(STDERR, "\n");
        exit(1);
    }
}

echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
