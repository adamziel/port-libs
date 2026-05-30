<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteJsonTablePlan;

$current = [
    'option_id' => 135,
    'option_name' => 'wp_plugin_hidden_rowid_order',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":7},{"slug":"forms","priority":4}],"meta":{"version":1}}',
    'scan_root' => '$.rules',
];
$next = [
    'option_id' => 135,
    'option_name' => 'wp_plugin_hidden_rowid_order',
    'option_value' => '{"rules":[{"slug":"seo","priority":2},{"slug":"cache","priority":8},{"slug":"forms","priority":4},{"slug":"shop","priority":8}],"meta":{"version":2}}',
    'scan_root' => '$.rules',
];

$plan = SQLiteJsonTablePlan::currentSourceHiddenRowidOrder(
    'json_tree',
    $current,
    $next,
    'option_value',
    [['column' => 'type', 'operator' => '=', 'value' => 'integer']],
    'scan_root',
    [['column' => 'atom', 'direction' => 'DESC'], ['column' => 'rowid']],
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['currentHiddenRowidOrder']['orderedRowids'] === [6, 9, 3]);
    assert($plan['nextHiddenRowidOrder']['orderedRowids'] === [6, 12, 9, 3]);
    assert($plan['nextHiddenRowidOrder']['orderKeyTape'][1]['orderKey'] === [8, 12]);
    assert(in_array('json-table-hidden-rowid-order-key-tape-changed', $plan['next135ReplanReasons'], true));
    echo "application-json-table-hidden-rowid-order self-test passed\n";
    return;
}

echo json_encode([
    'currentOrderedRowids' => $plan['currentHiddenRowidOrder']['orderedRowids'],
    'nextOrderedRowids' => $plan['nextHiddenRowidOrder']['orderedRowids'],
    'nextReaderPolicy' => $plan['nextReaderPolicy'],
    'replanReasons' => $plan['next135ReplanReasons'],
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
