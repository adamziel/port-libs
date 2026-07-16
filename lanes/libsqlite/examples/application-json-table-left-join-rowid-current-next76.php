<?php

declare(strict_types=1);

foreach (glob(__DIR__ . '/../src/*.php') as $sourceFile) {
    require_once $sourceFile;
}

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteSelectSql;

$options = [
    [
        'option_id' => 1,
        'option_name' => 'plugin_route_settings',
        'option_value' => '{"flags":["network","beta","seo"]}',
        'json_root' => '$.flags',
    ],
    [
        'option_id' => 2,
        'option_name' => 'plugin_media_settings',
        'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(['flags' => ['media', 'forms']])),
        'json_root' => '$.flags',
    ],
    [
        'option_id' => 3,
        'option_name' => 'plugin_empty_settings',
        'option_value' => '{"flags":[]}',
        'json_root' => '$.flags',
    ],
];

$rows = SQLiteSelectSql::execute(
    "SELECT o.option_name AS option_name, f.rowid AS json_rowid, f._rowid_ AS json__rowid_, f.oid AS json_oid, f.atom AS flag
       FROM wp_options AS o
       LEFT JOIN json_each(o.option_value, o.json_root) AS f ON f.rowid = 2
      ORDER BY option_name",
    ['wp_options' => $options],
);

echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), PHP_EOL;
