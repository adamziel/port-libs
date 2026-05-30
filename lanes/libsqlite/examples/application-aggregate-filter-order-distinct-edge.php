<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteTextAggregate;

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

$optionRows = [
    ['autoload', 40, 1],
    ['autoload', 10, 1],
    ['cache', 20, 1],
    ['theme', 5, 0],
    ['cron', 30, '1'],
    [null, 1, 1],
];

$packed = SQLiteTextAggregate::groupConcatDistinctOrderByFilter($optionRows, '|');

$result = [
    'applicationUse' => 'Preview copied wp_options aggregate summaries using SQLite group_concat(DISTINCT value ORDER BY key) FILTER (WHERE predicate) semantics, including first-duplicate order-key retention and SQL truthiness, without requiring ext/sqlite.',
    'sqlShape' => 'group_concat(DISTINCT option_group ORDER BY first_seen) FILTER (WHERE include_group)',
    'packedOptionGroups' => $packed,
    'expected' => 'cache|cron|autoload',
];

if (in_array('--self-test', $argv, true)) {
    if ($packed !== $result['expected']) {
        fwrite(STDERR, 'Unexpected aggregate summary: ' . var_export($packed, true) . PHP_EOL);
        exit(1);
    }

    echo "PASS application aggregate filter order distinct edge\n";
    exit(0);
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
