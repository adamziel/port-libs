<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVdbeWindowAggregateCursor;

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

$rows = [
    ['rowid' => 1, 'site' => 1, 'window_key' => 10, 'option_name' => 'siteurl', 'priority' => 30, 'option_group' => 'url', 'ok' => 1],
    ['rowid' => 2, 'site' => 1, 'window_key' => 20, 'option_name' => 'home', 'priority' => 10, 'option_group' => 'home', 'ok' => 1],
    ['rowid' => 3, 'site' => 1, 'window_key' => 30, 'option_name' => 'blogname', 'priority' => 20, 'option_group' => 'title', 'ok' => 0],
    ['rowid' => 4, 'site' => 1, 'window_key' => 40, 'option_name' => 'plugin_cache', 'priority' => 20, 'option_group' => 'cache', 'ok' => '1'],
    ['rowid' => 5, 'site' => 2, 'window_key' => 15, 'option_name' => 'network_home', 'priority' => 40, 'option_group' => 'network', 'ok' => 1],
    ['rowid' => 6, 'site' => 2, 'window_key' => 25, 'option_name' => 'network_cache', 'priority' => 15, 'option_group' => 'cache', 'ok' => 1],
];

$cursor = new SQLiteVdbeWindowAggregateCursor(
    $rows,
    'option_group',
    ['site'],
    ['window_key'],
    'ok',
    2,
    1,
    ['INTEGER'],
    [],
    ['NUMERIC']
);

$cursor->next();
$cursor->next();
$pair = $cursor->currentNextOrderedAggregateSummary(
    ['priority', 'option_name'],
    'rowid',
    '|',
    ['NUMERIC', 'TEXT'],
    ['BINARY', 'NOCASE'],
    [],
    ['LAST', null]
);

$result = [
    'applicationUse' => 'Preview copied wp_options window aggregate input ordering where the window frame follows import order but group_concat(... ORDER BY priority, option_name) uses a separate aggregate sorter after FILTER.',
    'sqlShape' => 'group_concat(option_group ORDER BY priority, option_name) FILTER (WHERE ok) OVER (PARTITION BY site ORDER BY window_key ROWS BETWEEN 2 PRECEDING AND 1 FOLLOWING)',
    'currentRowid' => $pair['current']['row']['rowid'],
    'currentFrameRowids' => $pair['current']['frameRowids'],
    'currentAggregateOrderRowids' => $pair['current']['orderedFrameRowids'],
    'currentOptionGroups' => $pair['current']['groupConcat'],
    'nextRowid' => $pair['next']['row']['rowid'] ?? null,
    'nextFrameRowids' => $pair['next']['frameRowids'] ?? null,
    'nextAggregateOrderRowids' => $pair['next']['orderedFrameRowids'] ?? null,
    'nextOptionGroups' => $pair['next']['groupConcat'] ?? null,
    'expectedCurrent' => 'home|cache|url',
    'expectedNext' => 'home|cache',
];

if (in_array('--self-test', $argv, true)) {
    if ($result['currentOptionGroups'] !== $result['expectedCurrent'] || $result['nextOptionGroups'] !== $result['expectedNext']) {
        fwrite(STDERR, 'Unexpected aggregate window ORDER current/next result: ' . json_encode($result, JSON_UNESCAPED_SLASHES) . PHP_EOL);
        exit(1);
    }

    echo "PASS application sql aggregate window order current next72\n";
    exit(0);
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
