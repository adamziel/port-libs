<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSelectSql;

$wpOptions = [
    [
        'option_id' => 1,
        'option_name' => 'plugin_alpha_settings',
        'option_value' => '{"rules":["seo","cache","media"]}',
        'scan_root' => '$.rules',
        'target_rowid' => 2,
    ],
    [
        'option_id' => 2,
        'option_name' => 'plugin_beta_settings',
        'option_value' => '{"rules":["forms"]}',
        'scan_root' => '$.rules',
        'target_rowid' => 1,
    ],
    [
        'option_id' => 3,
        'option_name' => 'plugin_empty_settings',
        'option_value' => '{"rules":[]}',
        'scan_root' => '$.rules',
        'target_rowid' => 1,
    ],
];

$sql = "SELECT o.option_name AS option_name,
               j.atom AS selected_rule,
               j.rowid AS selected_rowid
          FROM wp_options AS o
          LEFT JOIN json_each(o.option_value, o.scan_root) AS j
                 ON j.rowid = o.target_rowid
         ORDER BY o.option_id";

$rows = SQLiteSelectSql::execute($sql, ['wp_options' => $wpOptions]);
$plan = SQLiteSelectSql::plan($sql, ['wp_options' => $wpOptions]);

echo json_encode([
    'scenario' => 'application-json-table-lateral-rowid-hidden-current-source-next93',
    'rows' => $rows,
    'hiddenIndex' => $plan['joins'][0]['jsonTableHiddenIndex'] ?? null,
    'summary' => [
        'rowCount' => count($rows),
        'selectedRules' => array_column($rows, 'selected_rule'),
        'hiddenColumns' => array_column($plan['joins'][0]['jsonTableHiddenIndex']['constraints'] ?? [], 'column'),
        'dependencyClosure' => 'reuses native parser-level JSON table SELECT/FROM execution; no new support component required',
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
