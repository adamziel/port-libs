<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteRecursiveDmlCurrentSource.php';
require_once __DIR__ . '/../src/SQLiteInsertSelectSql.php';
require_once __DIR__ . '/../src/SQLiteUpdateFromSql.php';
require_once __DIR__ . '/../src/SQLiteUpdateDeleteReturningSql.php';
require_once __DIR__ . '/../src/SQLiteSelectSql.php';
require_once __DIR__ . '/../src/SQLiteSelectQuery.php';
require_once __DIR__ . '/../src/SQLiteSelectResult.php';
require_once __DIR__ . '/../src/SQLiteSelectProjection.php';
require_once __DIR__ . '/../src/SQLiteSelectPredicate.php';
require_once __DIR__ . '/../src/SQLiteSelectExpression.php';
require_once __DIR__ . '/../src/SQLiteDatabase.php';

use PortLibs\LibSqlite\SQLiteRecursiveDmlCurrentSource;

$edges = [
    ['src' => 1, 'dst' => 2],
    ['src' => 2, 'dst' => 3],
    ['src' => 3, 'dst' => 1],
    ['src' => 3, 'dst' => 4],
];
$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => 'old', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => 'old', 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => 'blogname', 'option_value' => 'old', 'autoload' => 'no'],
    ['option_id' => 4, 'option_name' => 'cache_seed', 'option_value' => 'old', 'autoload' => 'no'],
];

$result = SQLiteRecursiveDmlCurrentSource::insertSelect(
    "WITH RECURSIVE walk(id, depth) AS (
        VALUES (1, 0)
        UNION
        SELECT edges.dst, walk.depth + 1 FROM edges JOIN walk ON edges.src = walk.id WHERE walk.depth < 4
    ) INSERT INTO archive_options(option_id, option_name, option_value, autoload)
    SELECT DISTINCT option_id + 100, option_name, option_value || ':archived', autoload
    FROM wp_options JOIN walk ON walk.id = wp_options.option_id
    ORDER BY option_id",
    ['edges' => $edges, 'wp_options' => $options, 'archive_options' => []],
);

echo json_encode([
    'changes' => $result['changes'],
    'archived_option_ids' => array_column($result['after'], 'option_id'),
    'archived_names' => array_column($result['inserted_rows'], 'option_name'),
], JSON_PRETTY_PRINT) . PHP_EOL;
