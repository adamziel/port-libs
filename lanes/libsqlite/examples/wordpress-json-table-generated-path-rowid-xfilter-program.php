<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$current = [
    'option_id' => 177,
    'option_name' => 'active_plugins',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}]}',
    'generated_path' => '$.rules[1]',
    'scan_root' => '$.rules',
];
$next = [
    'option_id' => 177,
    'option_name' => 'active_plugins',
    'option_value' => '{"rules":[{"slug":"security","priority":9},{"slug":"seo","priority":3},{"slug":"cache","priority":6},{"slug":"forms","priority":4}]}',
    'generated_path' => '$.rules[2]',
    'scan_root' => '$.rules',
];
$constraints = [
    ['column' => 'path', 'operator' => '=', 'value' => '$.rules[1]'],
    ['column' => 'rowid', 'operator' => '=', 'value' => 6],
    ['column' => '_rowid_', 'operator' => '=', 'value' => '6'],
];

$plan = SQLiteJsonTablePlan::generatedPathRowidXFilterProgramPlan(
    'json_tree',
    $current,
    $next,
    'option_value',
    'generated_path',
    $constraints,
    'scan_root',
    [['column' => 'path'], ['column' => 'rowid']],
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['currentGeneratedPathRowidXFilterProgram177']['xFilterOpcode'] === 'xfilter-current-source-covered-seek-next177');
    assert($plan['nextGeneratedPathRowidXFilterProgram177']['xFilterOpcode'] === 'xfilter-empty-current-source-rowset-next177');
    assert($plan['currentGeneratedPathRowidXFilterProgram177']['yieldRowids'] === [6]);
    assert(in_array('json-table-generated-path-rowid-xfilter-next177-source-changed', $plan['next177ReplanReasons'], true));
    echo "wordpress-json-table-generated-path-rowid-xfilter-program self-test passed\n";
    return;
}

echo json_encode([
    'currentOpcode' => $plan['currentGeneratedPathRowidXFilterProgram177']['xFilterOpcode'],
    'nextOpcode' => $plan['nextGeneratedPathRowidXFilterProgram177']['xFilterOpcode'],
    'argvColumns' => $plan['currentGeneratedPathRowidXFilterProgram177']['argvColumns'],
    'yieldRowids' => $plan['currentGeneratedPathRowidXFilterProgram177']['yieldRowids'],
    'replanReasons' => $plan['next177ReplanReasons'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
