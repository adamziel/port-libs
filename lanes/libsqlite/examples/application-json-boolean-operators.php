<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteSelectSql;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$options = [
    [
        'option_id' => 1,
        'option_name' => 'plugin_cache_settings',
        'option_value' => '{"plugin":{"enabled":true,"network":false,"priority":7,"label":"cache"}}',
        'autoload' => 'yes',
    ],
    [
        'option_id' => 2,
        'option_name' => 'plugin_forms_settings',
        'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode(['plugin' => ['enabled' => false, 'network' => true, 'priority' => 3, 'label' => 'forms']])),
        'autoload' => 'no',
    ],
    [
        'option_id' => 3,
        'option_name' => 'plugin_empty_settings',
        'option_value' => '{"plugin":{"enabled":false,"network":false,"priority":0,"label":"empty"}}',
        'autoload' => 'no',
    ],
];

$enabledSql = "SELECT option_name FROM wp_options WHERE option_value ->> '$.plugin.enabled' ORDER BY option_id";
$needsReviewSql = "SELECT option_name FROM wp_options WHERE NOT option_value ->> '$.plugin.enabled' OR option_value ->> '$.plugin.network' ORDER BY option_id";
$prioritySql = "SELECT option_name FROM wp_options WHERE option_value ->> '$.plugin.priority' AND NOT option_value ->> '$.plugin.network' ORDER BY option_id";

echo json_encode([
    'applicationUse' => 'Preview copied wp_options plugin JSON/JSONB rows filtered by SQLite truth-value operators over ->> boolean and numeric extracts without requiring ext/sqlite.',
    'enabledSql' => $enabledSql,
    'enabledOptions' => array_column(SQLiteSelectSql::execute($enabledSql, ['wp_options' => $options]), 'option_name'),
    'needsReviewSql' => $needsReviewSql,
    'needsReviewOptions' => array_column(SQLiteSelectSql::execute($needsReviewSql, ['wp_options' => $options]), 'option_name'),
    'prioritySql' => $prioritySql,
    'priorityOptions' => array_column(SQLiteSelectSql::execute($prioritySql, ['wp_options' => $options]), 'option_name'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
