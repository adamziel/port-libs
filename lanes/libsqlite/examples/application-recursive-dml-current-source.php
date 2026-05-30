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
require_once __DIR__ . '/../src/SQLiteSelectCompound.php';
require_once __DIR__ . '/../src/SQLiteAffinityComparison.php';
require_once __DIR__ . '/../src/SQLiteDatabase.php';

use PortLibs\LibSqlite\SQLiteRecursiveDmlCurrentSource;

$edges = [
    ['src' => 1, 'dst' => 2],
    ['src' => 2, 'dst' => 3],
    ['src' => 3, 'dst' => 1],
    ['src' => 3, 'dst' => 4],
];
$options = [
    ['setting_id' => 1, 'key_name' => 'site_endpoint', 'key_value' => 'old', 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_name' => 'home', 'key_value' => 'old', 'load_policy' => 'yes'],
    ['setting_id' => 3, 'key_name' => 'site_title', 'key_value' => 'old', 'load_policy' => 'no'],
    ['setting_id' => 4, 'key_name' => 'cache_seed', 'key_value' => 'old', 'load_policy' => 'no'],
];

$result = SQLiteRecursiveDmlCurrentSource::insertSelect(
    "WITH RECURSIVE walk(id, depth) AS (
        VALUES (1, 0)
        UNION
        SELECT edges.dst, walk.depth + 1 FROM edges JOIN walk ON edges.src = walk.id WHERE walk.depth < 4
    ) INSERT INTO archive_settings(setting_id, key_name, key_value, load_policy)
    SELECT DISTINCT setting_id + 100, key_name, key_value || ':archived', load_policy
    FROM app_settings JOIN walk ON walk.id = app_settings.setting_id
    ORDER BY setting_id",
    ['edges' => $edges, 'app_settings' => $options, 'archive_settings' => []],
);

echo json_encode([
    'changes' => $result['changes'],
    'archived_setting_ids' => array_column($result['after'], 'setting_id'),
    'archived_names' => array_column($result['inserted_rows'], 'key_name'),
], JSON_PRETTY_PRINT) . PHP_EOL;
