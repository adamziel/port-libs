<?php

declare(strict_types=1);

require __DIR__ . '/../src/SQLiteBlobValue.php';
require __DIR__ . '/../src/SQLiteDatabase.php';
require __DIR__ . '/../src/SQLiteJson5Parser.php';
require __DIR__ . '/../src/SQLiteJsonB.php';
require __DIR__ . '/../src/SQLiteJsonCanonical.php';
require __DIR__ . '/../src/SQLiteJsonConstructor.php';
require __DIR__ . '/../src/SQLiteJsonInspection.php';
require __DIR__ . '/../src/SQLiteJsonPath.php';
require __DIR__ . '/../src/SQLiteJsonQuote.php';
require __DIR__ . '/../src/SQLiteJsonSubtypeValue.php';
require __DIR__ . '/../src/SQLiteJsonValidity.php';
require __DIR__ . '/../src/SQLiteJsonEach.php';
require __DIR__ . '/../src/SQLiteJsonTree.php';
require __DIR__ . '/../src/SQLiteJsonTablePlan.php';

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteJsonB;
use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$options = [
    [
        'option_id' => 1,
        'option_name' => 'site_plugin_settings',
        'autoload' => 'yes',
        'option_value' => '{"plugin":{"rules":[{"name":"seo","priority":2},{"name":"cache","priority":7}]}}',
    ],
    [
        'option_id' => 2,
        'option_name' => 'site_theme_settings',
        'autoload' => 'yes',
        'option_value' => new SQLiteBlobValue(SQLiteJsonB::encode([
            'plugin' => [
                'rules' => [
                    ['name' => 'forms', 'priority' => 4],
                    ['name' => 'media', 'priority' => 1],
                ],
            ],
        ])),
    ],
    [
        'option_id' => 3,
        'option_name' => 'empty_plugin_settings',
        'autoload' => 'no',
        'option_value' => '{"plugin":{"rules":[]}}',
    ],
];

$ruleRows = SQLiteJsonTablePlan::hostJoinRows($options, 'option_value', 'json_tree', [
    ['column' => 'root', 'operator' => '=', 'value' => '$.plugin.rules'],
    ['column' => 'key', 'operator' => '=', 'value' => 'priority'],
    ['column' => 'atom', 'operator' => '>=', 'value' => 4],
], ['key', 'atom', 'fullkey'], 'left', 'rule_');

echo json_encode([
    'scenario' => 'application-json-table-host-join',
    'planned' => [
        'rows' => array_map(
            static fn (array $row): array => [
                'optionName' => $row['option_name'],
                'autoload' => $row['autoload'],
                'jsonKey' => $row['rule_key'],
                'priority' => $row['rule_atom'],
                'fullkey' => $row['rule_fullkey'],
            ],
            $ruleRows,
        ),
    ],
    'applicationUse' => 'Local-only wp_options diagnostics can join copied option rows to json_tree() output for plugin-rule scans, preserving LEFT JOIN rows for empty or invalid JSON without ext/sqlite.',
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
