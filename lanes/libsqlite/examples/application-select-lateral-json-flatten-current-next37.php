<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

$wpOptions = [
    [
        'option_id' => 1,
        'option_name' => 'site_plugin_settings',
        'autoload' => 'yes',
        'option_value' => '{"groups":[{"slug":"seo","rules":[{"name":"title","priority":7},{"name":"meta","priority":2}]},{"slug":"cache","rules":[{"name":"page","priority":5},{"name":"object","priority":9}]}],"flags":["network","beta"]}',
    ],
    [
        'option_id' => 2,
        'option_name' => 'theme_plugin_settings',
        'autoload' => 'yes',
        'option_value' => '{"groups":[{"slug":"forms","rules":[{"name":"contact","priority":4},{"name":"captcha","priority":1}]}],"flags":["theme"]}',
    ],
];

$rows = SQLiteSelectSql::execute(
    "SELECT o.option_name AS option_name, g.fullkey AS group_path, r.atom AS priority
     FROM wp_options AS o, json_tree(o.option_value, '$.groups') AS g, json_tree(o.option_value, g.fullkey) AS r
     WHERE g.key = 'rules' AND r.key = 'priority'
     ORDER BY priority DESC, option_name
     LIMIT 4",
    ['wp_options' => $wpOptions],
);

if (($argv[1] ?? null) === '--self-test') {
    if (array_column($rows, 'priority') !== [9, 7, 5, 4]) {
        fwrite(STDERR, 'unexpected flattened priorities: ' . json_encode(array_column($rows, 'priority')) . PHP_EOL);
        exit(1);
    }

    echo "application-select-lateral-json-flatten-current-next37 self-test passed\n";
    exit(0);
}

echo json_encode([
    'scenario' => 'application-select-lateral-json-flatten-current-next37',
    'applicationUse' => 'Copied wp_options plugin settings can be flattened through comma-form json_tree/json_each sources whose later JSON table roots use the current joined row, without requiring ext/sqlite.',
    'topPriority' => $rows[0]['priority'] ?? null,
    'flattenedRows' => count($rows),
    'rows' => $rows,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
