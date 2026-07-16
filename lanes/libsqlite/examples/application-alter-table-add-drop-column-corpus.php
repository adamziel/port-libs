<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteAlterTableColumnCorpus;
use PortLibs\LibSqlite\SQLiteSchemaRecord;

$wpOptions = new SQLiteSchemaRecord(
    'table',
    'wp_options',
    'wp_options',
    2,
    'CREATE TABLE wp_options(option_id INTEGER PRIMARY KEY, option_name TEXT NOT NULL, option_value TEXT, autoload TEXT DEFAULT "yes")',
    1
);

$addPlan = SQLiteAlterTableColumnCorpus::addColumn(
    $wpOptions,
    'ALTER TABLE wp_options ADD COLUMN last_changed INTEGER NOT NULL DEFAULT 0'
);

$dropPlan = SQLiteAlterTableColumnCorpus::dropColumn(
    $wpOptions,
    'ALTER TABLE wp_options DROP COLUMN option_value'
);

echo json_encode([
    'add' => [
        'status' => $addPlan['status'],
        'column' => $addPlan['column'],
        'column_count' => $addPlan['column_count'],
    ],
    'drop' => [
        'status' => $dropPlan['status'],
        'column' => $dropPlan['column'],
        'preserved' => $dropPlan['preserved'],
    ],
], JSON_PRETTY_PRINT) . "\n";
