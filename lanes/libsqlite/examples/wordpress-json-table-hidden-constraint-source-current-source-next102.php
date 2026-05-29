<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$current = [
    'option_name' => 'plugin_rule_cache',
    'option_value' => '{"rules":[{"slug":"seo","enabled":true},{"slug":"cache","enabled":false},{"slug":"forms","enabled":true}]}',
    'scan_root' => '$.rules',
    'target_id' => 6,
    'target_type' => 'false',
];
$next = [
    'option_name' => 'plugin_rule_cache',
    'option_value' => '{"rules":[{"slug":"seo","enabled":true},{"slug":"cache","enabled":true},{"slug":"forms","enabled":true},{"slug":"shop","enabled":true}]}',
    'scan_root' => '$.rules',
    'target_id' => 6,
    'target_type' => 'true',
];

$plan = SQLiteJsonTablePlan::hiddenConstraintSourceCurrentSource(
    'json_tree',
    $current,
    $next,
    [
        ['column' => 'json', 'sourceColumn' => 'option_value'],
        ['column' => 'root', 'sourceColumn' => 'scan_root'],
        ['column' => 'rowid', 'sourceColumn' => 'target_id'],
        ['column' => 'type', 'sourceColumn' => 'target_type'],
    ],
    [['column' => 'id']],
);

$payload = [
    'scenario' => 'wordpress-json-table-hidden-constraint-source-current-source-next102',
    'wordpressUse' => 'Copied wp_options JSON scans can keep a current json_tree cursor pinned while the next option row supplies hidden json/root/rowid constraints and visible type constraints from source columns, without requiring ext/sqlite.',
    'currentAtom' => $plan['currentRows'][0]['atom'] ?? null,
    'nextAtom' => $plan['nextRows'][0]['atom'] ?? null,
    'replanReasons' => $plan['replanReasons'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'dependency' => $plan['dependencies'][0],
];

if (($argv[1] ?? null) === '--self-test') {
    if ($payload['currentAtom'] !== 0 || $payload['nextAtom'] !== 1) {
        fwrite(STDERR, "unexpected hidden constraint source atom transition\n");
        exit(1);
    }
    if (!in_array('hidden-constraint-source-value-changed', $payload['replanReasons'], true)) {
        fwrite(STDERR, "missing hidden constraint source replan reason\n");
        exit(1);
    }

    echo "wordpress-json-table-hidden-constraint-source-current-source-next102 self-test passed\n";
    return;
}

echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
