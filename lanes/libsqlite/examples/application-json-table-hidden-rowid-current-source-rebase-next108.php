<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$wpOptions = [
    [
        'option_id' => 10,
        'option_name' => 'plugin_alpha_settings',
        'option_value' => '{"rules":["seo","cache","forms"]}',
        'scan_root' => '$.rules',
        'target_rowid' => 2,
    ],
    [
        'option_id' => 20,
        'option_name' => 'plugin_beta_settings',
        'option_value' => '{"rules":["media"]}',
        'scan_root' => '$.rules',
        'target_rowid' => 1,
    ],
    [
        'option_id' => 30,
        'option_name' => 'plugin_empty_settings',
        'option_value' => '{"rules":[]}',
        'scan_root' => '$.rules',
        'target_rowid' => 1,
    ],
];

$rows = SQLiteSelectSql::execute(
    "SELECT o.option_name AS option_name,
            j.atom AS selected_rule,
            j.rowid AS selected_rowid
       FROM wp_options AS o
       JOIN json_each(o.option_value, o.scan_root) AS j ON j.atom IS NOT NULL
      WHERE j.rowid = o.target_rowid
      ORDER BY o.option_id",
    ['wp_options' => $wpOptions],
);

$plan = SQLiteSelectSql::plan(
    "SELECT o.option_name, j.atom
       FROM wp_options AS o
       JOIN json_each(o.option_value, o.scan_root) AS j ON j.atom IS NOT NULL
      WHERE j.rowid = o.target_rowid",
    ['wp_options' => $wpOptions],
);

$payload = [
    'scenario' => 'application-json-table-hidden-rowid-current-source-rebase-next108',
    'applicationUse' => 'Copied wp_options JSON scans can rebase a json_each hidden rowid constraint from the current source row even when the rowid predicate is supplied by WHERE rather than ON.',
    'rowCount' => count($rows),
    'optionNames' => array_column($rows, 'option_name'),
    'selectedRules' => array_column($rows, 'selected_rule'),
    'hiddenIndexColumns' => array_column($plan['joins'][0]['jsonTableHiddenIndex']['constraints'], 'column'),
    'hiddenIndexExpressions' => array_column($plan['joins'][0]['jsonTableHiddenIndex']['constraints'], 'expression'),
    'dependencyClosure' => 'no new support component needed; reuses native JSON table parser/planner and row-array executor',
];

if (($argv[1] ?? null) === '--self-test') {
    if ($payload['rowCount'] !== 2 || $payload['selectedRules'] !== ['cache', 'media']) {
        fwrite(STDERR, "unexpected current-source rowid rebase rows\n");
        exit(1);
    }
    if ($payload['hiddenIndexColumns'] !== ['id']) {
        fwrite(STDERR, "unexpected hidden index columns\n");
        exit(1);
    }

    echo "application-json-table-hidden-rowid-current-source-rebase-next108 self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
