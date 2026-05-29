<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteJsonPath.php';
require_once __DIR__ . '/../src/SQLiteJson5Parser.php';
require_once __DIR__ . '/../src/SQLiteJsonCanonical.php';
require_once __DIR__ . '/../src/SQLiteJsonInspection.php';
require_once __DIR__ . '/../src/SQLiteJsonValidity.php';
require_once __DIR__ . '/../src/SQLiteJsonB.php';
require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteJsonSubtypeValue.php';
require_once __DIR__ . '/../src/SQLiteJsonExtract.php';
require_once __DIR__ . '/../src/SQLiteJsonEach.php';
require_once __DIR__ . '/../src/SQLiteJsonTree.php';
require_once __DIR__ . '/../src/SQLiteJsonTablePlan.php';

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current = [
    'option_id' => 132,
    'option_name' => 'wp_plugin_generated_order',
    'option_value' => '{"rules":[{"slug":"seo","priority":2,"enabled":true},{"slug":"cache","priority":7,"enabled":false},{"slug":"forms","priority":4,"enabled":true}]}',
    'scan_root' => '$.rules',
];
$next = [
    'option_id' => 132,
    'option_name' => 'wp_plugin_generated_order',
    'option_value' => '{"rules":[{"slug":"seo","priority":6,"enabled":true},{"slug":"cache","priority":1,"enabled":false},{"slug":"forms","priority":4,"enabled":true},{"slug":"shop","priority":5,"enabled":true}]}',
    'scan_root' => '$.rules',
];

$plan = SQLiteJsonTablePlan::currentSourceHiddenGeneratedOrder(
    'json_tree',
    $current,
    $next,
    'option_value',
    [
        ['column' => 'type', 'operator' => '=', 'value' => 'object'],
        ['column' => 'fullkey', 'operator' => 'LIKE', 'value' => '$.rules[%]'],
    ],
    'scan_root',
    [['column' => 'json'], ['column' => 'id']],
    [
        ['name' => 'generated_priority', 'source' => 'value', 'path' => '$.priority', 'direction' => 'ASC'],
        ['name' => 'generated_slug', 'source' => 'value', 'path' => '$.slug', 'direction' => 'DESC'],
    ],
);

$summary = [
    'operation' => 'json-table-hidden-generated-order-current-source-next132',
    'optionName' => $current['option_name'],
    'currentGeneratedKeys' => $plan['currentHiddenGeneratedOrder']['rowGeneratedKeys'],
    'nextGeneratedKeys' => $plan['nextHiddenGeneratedOrder']['rowGeneratedKeys'],
    'currentOrderedRowids' => $plan['currentHiddenGeneratedOrder']['orderedRowids'],
    'nextOrderedRowids' => $plan['nextHiddenGeneratedOrder']['orderedRowids'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'replanReasons' => $plan['next132ReplanReasons'],
    'dependencies' => $plan['dependencies'],
    'wordpressUse' => 'Copied wp_options plugin settings can keep a hidden json/root json_tree() cursor open while generated priority/slug ORDER BY keys are recomputed for the next source row.',
];

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;

if (PHP_SAPI === 'cli' && realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    if ($summary['currentOrderedRowids'] !== [1, 9, 5]) {
        fwrite(STDERR, "expected current generated priority order\n");
        exit(1);
    }
    if ($summary['nextOrderedRowids'] !== [5, 9, 13, 1]) {
        fwrite(STDERR, "expected next generated priority order\n");
        exit(1);
    }
    if (!in_array('sqlite-json-table-hidden-generated-order-current-source-next132', $summary['dependencies'], true)) {
        fwrite(STDERR, "expected next132 dependency marker\n");
        exit(1);
    }
}
