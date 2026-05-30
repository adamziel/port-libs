<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteSelectSql.php';
require_once __DIR__ . '/../src/SQLiteSelectQuery.php';
require_once __DIR__ . '/../src/SQLiteSelectResult.php';
require_once __DIR__ . '/../src/SQLiteSelectProjection.php';
require_once __DIR__ . '/../src/SQLiteSelectPredicate.php';
require_once __DIR__ . '/../src/SQLiteSelectExpression.php';
require_once __DIR__ . '/../src/SQLiteJsonTablePlan.php';
require_once __DIR__ . '/../src/SQLiteJsonEach.php';
require_once __DIR__ . '/../src/SQLiteJsonTree.php';
require_once __DIR__ . '/../src/SQLiteJsonPath.php';
require_once __DIR__ . '/../src/SQLiteJsonValidity.php';
require_once __DIR__ . '/../src/SQLiteJsonInspection.php';
require_once __DIR__ . '/../src/SQLiteJsonConstructor.php';
require_once __DIR__ . '/../src/SQLiteJsonCanonical.php';
require_once __DIR__ . '/../src/SQLiteJsonQuote.php';
require_once __DIR__ . '/../src/SQLiteJsonB.php';
require_once __DIR__ . '/../src/SQLiteJson5Parser.php';
require_once __DIR__ . '/../src/SQLiteJsonSubtypeValue.php';
require_once __DIR__ . '/../src/SQLiteBlobValue.php';

use PortLibs\LibSqlite\SQLiteSelectSql;

$wpOptions = [
    [
        'option_id' => 1,
        'option_name' => 'plugin_alpha_settings',
        'option_value' => '{"rules":["seo","cache"],"meta":{"enabled":true}}',
        'scan_root' => '$.rules',
    ],
    [
        'option_id' => 2,
        'option_name' => 'plugin_beta_settings',
        'option_value' => '{"rules":[],"meta":{"enabled":false}}',
        'scan_root' => '$.rules',
    ],
    [
        'option_id' => 3,
        'option_name' => 'plugin_gamma_settings',
        'option_value' => '{"rules":["forms"],"meta":{"enabled":true}}',
        'scan_root' => '$.rules',
    ],
];

$rows = SQLiteSelectSql::execute(
    "SELECT o.option_name AS option_name,
            j.rowid AS rule_rowid,
            j._rowid_ AS rule__rowid_,
            j.oid AS rule_oid,
            j.atom AS rule_name
       FROM wp_options AS o
       LEFT JOIN json_each AS j
              ON j.json = o.option_value
             AND j.root = o.scan_root
             AND j.atom IS NOT NULL
      ORDER BY o.option_id, j.rowid",
    ['wp_options' => $wpOptions],
);

echo json_encode([
    'scenario' => 'application-json-table-lateral-hidden-rowid-current-source-next85',
    'rowCount' => count($rows),
    'optionNames' => array_column($rows, 'option_name'),
    'ruleRowids' => array_column($rows, 'rule_rowid'),
    'ruleNames' => array_column($rows, 'rule_name'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
