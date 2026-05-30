<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteSelectSql;

$options = [
    ['option_id' => 1, 'option_name' => 'siteurl', 'autoload' => 'yes', 'priority' => 30],
    ['option_id' => 2, 'option_name' => 'home', 'autoload' => 'yes', 'priority' => 20],
    ['option_id' => 4, 'option_name' => 'active_plugins', 'autoload' => 'no', 'priority' => 40],
];
$staged = [
    ['stage_id' => 10, 'stage_name' => 'blogdescription', 'load_flag' => 'yes', 'rank' => 15],
    ['stage_id' => 11, 'stage_name' => 'active_plugins', 'load_flag' => 'no', 'rank' => 50],
    ['stage_id' => 13, 'stage_name' => 'siteurl', 'load_flag' => 'yes', 'rank' => 35],
];

$tables = ['wp_options' => $options, 'wp_options_stage' => $staged];
$sql = "
SELECT option_name AS option_key, option_id + priority AS import_score
FROM wp_options
UNION ALL
SELECT stage_name AS staged_key, stage_id + rank AS staged_score
FROM wp_options_stage
ORDER BY staged_score DESC, staged_key
LIMIT 4
";

$rows = SQLiteSelectSql::execute($sql, $tables);
$plan = SQLiteSelectSql::plan($sql, $tables);
$summary = [
    'scenario' => 'compound-name-resolution-current-source-next121',
    'applicationUse' => 'Copied wp_options import staging can order a compound SELECT by the staged arm aliases while returning the current left-most output names used by Application import code.',
    'orderColumns' => array_column($plan['compound']['orderBy'], 'column'),
    'rows' => $rows,
];

if (in_array('--self-test', $argv, true)) {
    $expected = [
        ['option_key' => 'active_plugins', 'import_score' => 61],
        ['option_key' => 'siteurl', 'import_score' => 48],
        ['option_key' => 'active_plugins', 'import_score' => 44],
        ['option_key' => 'siteurl', 'import_score' => 31],
    ];
    if ($summary['orderColumns'] !== ['import_score', 'option_key'] || $rows !== $expected) {
        fwrite(STDERR, json_encode(['expected' => $expected, 'actual' => $summary], JSON_PRETTY_PRINT) . PHP_EOL);
        exit(1);
    }
    echo "application-compound-name-resolution-current-source-next121 self-test passed\n";

    return;
}

echo json_encode($summary, JSON_PRETTY_PRINT) . PHP_EOL;
