<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteSelectSql;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => '10', 'autoload' => 'yes', 'bucket' => '2.5'],
    ['option_id' => 2, 'option_name' => 'home', 'option_value' => '10abc', 'autoload' => 'no', 'bucket' => '02'],
    ['option_id' => 3, 'option_name' => 'blogname', 'option_value' => '9.75', 'autoload' => 'yes', 'bucket' => '9e1'],
    ['option_id' => 4, 'option_name' => 'stylesheet', 'option_value' => 'abc', 'autoload' => 'no', 'bucket' => '0'],
    ['option_id' => 5, 'option_name' => 'template', 'option_value' => '', 'autoload' => 'yes', 'bucket' => null],
    ['option_id' => 6, 'option_name' => 'active_plugins', 'option_value' => new SQLiteBlobValue('11plugin'), 'autoload' => 'no', 'bucket' => '11.5'],
];

$numericSql = "SELECT option_name, CAST(option_value AS INTEGER) AS numeric_value FROM wp_options WHERE CAST(option_value AS INTEGER) >= 10 ORDER BY numeric_value DESC, option_id";
$lexicalSql = "SELECT option_name FROM wp_options WHERE option_value BETWEEN 1 AND 10 ORDER BY option_id";
$storageRankSql = "SELECT option_name FROM wp_options WHERE CAST(option_id AS TEXT) > 100 ORDER BY option_id";

echo json_encode([
    'applicationUse' => 'Preview copied wp_options option_value comparisons where parser-level CAST() controls SQLite storage-class comparison and numeric affinity without requiring ext/sqlite.',
    'numericSql' => $numericSql,
    'numericOptions' => array_map(
        static fn (array $row): string => $row['option_name'] . ':' . $row['numeric_value'],
        SQLiteSelectSql::execute($numericSql, ['wp_options' => $options]),
    ),
    'lexicalSql' => $lexicalSql,
    'lexicalOptionsWithoutCast' => array_column(SQLiteSelectSql::execute($lexicalSql, ['wp_options' => $options]), 'option_name'),
    'storageRankSql' => $storageRankSql,
    'textStorageRankOptions' => array_column(SQLiteSelectSql::execute($storageRankSql, ['wp_options' => $options]), 'option_name'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
