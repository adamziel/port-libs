<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNext254Plan;

$current = [
    ['option_id' => 1, 'option_name' => 'plugin_cache', 'option_value' => 'plugin_cache'],
    ['option_id' => 2, 'option_name' => 'plugin_literal', 'option_value' => 'plugin_%literal'],
    ['option_id' => 3, 'option_name' => 'plugin_upper', 'option_value' => 'PLUGIN_cache'],
    ['option_id' => 4, 'option_name' => 'plugin_blob', 'option_value' => new SQLiteBlobValue('plugin_blob')],
];

$next = [
    ['option_id' => 1, 'option_name' => 'plugin_cache', 'option_value' => 'plugin_cache'],
    ['option_id' => 2, 'option_name' => 'plugin_literal', 'option_value' => 'plugin_%literal'],
    ['option_id' => 3, 'option_name' => 'plugin_upper', 'option_value' => 'PLUGIN_cache'],
    ['option_id' => 5, 'option_name' => 'plugin_new', 'option_value' => 'plugin_new'],
];

$plan = SQLiteEncodingCollationAffinityLikeCurrentSourceNext254Plan::wordpressNullableEscapeLikePlan($current, $next);
$summary = [
    'status' => $plan['status'],
    'currentEscapeIsSqlNull' => $plan['currentEscapeIsSqlNull'],
    'nextEscapeText' => $plan['nextEscapeText'],
    'currentMatchedRowids' => $plan['currentMatchedRowids'],
    'nextMatchedRowids' => $plan['nextMatchedRowids'],
    'currentUnknownRowids' => $plan['currentUnknownRowids'],
    'enteredMatchedRowids' => $plan['enteredMatchedRowids'],
    'cursorInvalidated' => $plan['cursorInvalidated'],
    'invalidationReasons' => $plan['invalidationReasons'],
    'dependency_closure' => $plan['dependency_closure'],
];

if (($argv[1] ?? null) === '--self-test') {
    assert($summary['currentEscapeIsSqlNull'] === true);
    assert($summary['nextEscapeText'] === '!');
    assert($summary['currentMatchedRowids'] === []);
    assert($summary['nextMatchedRowids'] === [1, 2, 3, 5]);
    assert($summary['currentUnknownRowids'] === [1, 2, 3, 4]);
    assert(in_array('escape-nullability', $summary['invalidationReasons'], true));
    echo "wordpress-encoding-collation-affinity-like-current-source-next254 self-test passed\n";
    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
