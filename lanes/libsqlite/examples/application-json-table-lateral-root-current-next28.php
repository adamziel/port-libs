<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

$wpOptions = [
    [
        'option_id' => 1,
        'option_name' => 'site_plugin_settings',
        'autoload' => 'yes',
        'option_value' => '{"groups":[{"slug":"seo","rules":[{"name":"title","enabled":1,"priority":7},{"name":"meta","enabled":0,"priority":2}]},{"slug":"cache","rules":[{"name":"page","enabled":1,"priority":5},{"name":"object","enabled":1,"priority":9}]}],"flags":["network","beta"]}',
    ],
    [
        'option_id' => 2,
        'option_name' => 'theme_plugin_settings',
        'autoload' => 'yes',
        'option_value' => '{"groups":[{"slug":"forms","rules":[{"name":"contact","enabled":1,"priority":4},{"name":"captcha","enabled":0,"priority":1}]}],"flags":["theme"]}',
    ],
];

$rows = SQLiteSelectSql::execute(
    "SELECT o.option_name AS option_name, g.fullkey AS group_path, leaf.atom AS priority
     FROM wp_options AS o
     JOIN json_tree(o.option_value, '$.groups') AS g ON g.key = 'rules'
     JOIN json_tree(o.option_value, g.fullkey) AS leaf ON leaf.key = 'priority'
     WHERE leaf.atom >= 5
     ORDER BY priority DESC, option_name",
    ['wp_options' => $wpOptions],
);

if (($argv[1] ?? null) === '--self-test') {
    $priorities = array_column($rows, 'priority');
    if ($priorities !== [9, 7, 5]) {
        fwrite(STDERR, 'Unexpected lateral JSON root priorities: ' . json_encode($priorities) . PHP_EOL);
        exit(1);
    }

    echo "application-json-table-lateral-root-current-next28 self-test passed\n";
    exit(0);
}

echo json_encode([
    'scenario' => 'wp_options JSON table lateral current-root scan',
    'topPriority' => $rows[0]['priority'] ?? null,
    'topGroupPath' => $rows[0]['group_path'] ?? null,
    'matchedRules' => count($rows),
    'rows' => $rows,
], JSON_PRETTY_PRINT) . PHP_EOL;
