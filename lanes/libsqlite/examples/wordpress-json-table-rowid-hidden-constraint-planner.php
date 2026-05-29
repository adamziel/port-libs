<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteJsonTablePlan;
use PortLibs\LibSqlite\SQLiteSelectSql;

$current = [
    'option_id' => 501,
    'option_name' => 'wp_plugin_json_rowids',
    'option_value' => '{"rules":[{"slug":"seo","enabled":true},{"slug":"cache","enabled":false},{"slug":"forms","enabled":true}]}',
    'scan_root' => '$.rules',
    'target_rowid' => 6,
];
$next = array_replace($current, [
    'option_value' => '{"rules":[{"slug":"seo","enabled":true},{"slug":"cache","enabled":true},{"slug":"forms","enabled":true}]}',
]);

$planner = SQLiteJsonTablePlan::currentSourceRowidHiddenConstraintPlanner(
    'json_tree',
    $current,
    $next,
    'option_value',
    [['column' => 'oid', 'operator' => '=', 'value' => 6]],
    'scan_root',
);

$sql = "SELECT o.option_name AS option_name,
               j.oid AS selected_oid,
               j.atom AS selected_atom
          FROM wp_options AS o
          JOIN json_tree(o.option_value, o.scan_root) AS j
            ON j.oid = o.target_rowid";

$plan = SQLiteSelectSql::plan($sql, ['wp_options' => [$current]]);
$rows = SQLiteSelectSql::execute($sql, ['wp_options' => [$current]]);

$summary = [
    'scenario' => 'wordpress-json-table-rowid-hidden-constraint-planner',
    'rows' => $rows,
    'plannerAlias' => $planner['currentRowidAliasConstraints'][0]['originalColumn'] ?? null,
    'plannerNormalizedColumn' => $planner['currentRowidAliasConstraints'][0]['normalizedColumn'] ?? null,
    'selectAlias' => $plan['joins'][0]['jsonTableHiddenIndex']['constraints'][0]['originalColumn'] ?? null,
    'selectNormalizedColumn' => $plan['joins'][0]['jsonTableHiddenIndex']['constraints'][0]['column'] ?? null,
    'replanReasons' => $planner['rowidHiddenConstraintReplanReasons'],
    'dependencyClosure' => 'reuses parser-level JSON table SELECT/FROM and native JSON table planner; no new support component required',
];

if (in_array('--self-test', $argv, true)) {
    assert($summary['plannerAlias'] === 'oid');
    assert($summary['plannerNormalizedColumn'] === 'id');
    assert($summary['selectAlias'] === 'oid');
    assert($summary['selectNormalizedColumn'] === 'id');
    assert($summary['rows'] === [[
        'option_name' => 'wp_plugin_json_rowids',
        'selected_oid' => 6,
        'selected_atom' => 0,
    ]]);
    assert(in_array('source-json-changed', $summary['replanReasons'], true));
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
