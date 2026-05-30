<?php

declare(strict_types=1);

foreach (glob(__DIR__ . '/../src/*.php') ?: [] as $file) {
    require_once $file;
}

use PortLibs\LibSqlite\SQLiteVdbeWindowAggregateCursor;

$rows = [
    ['rowid' => 1, 'site' => 1, 'option_name' => 'active_plugins', 'bytes' => 10, 'include' => 1],
    ['rowid' => 2, 'site' => 1, 'option_name' => 'cron', 'bytes' => 10, 'include' => 0],
    ['rowid' => 3, 'site' => 1, 'option_name' => 'rewrite_rules', 'bytes' => 20, 'include' => 1],
    ['rowid' => 4, 'site' => 1, 'option_name' => 'theme_mods', 'bytes' => 30, 'include' => '0.5'],
    ['rowid' => 5, 'site' => 1, 'option_name' => 'theme_mods', 'bytes' => 30, 'include' => ''],
];

$cursor = new SQLiteVdbeWindowAggregateCursor(
    $rows,
    'bytes',
    ['site'],
    ['bytes', 'option_name'],
    'include',
    0,
    1,
    ['INTEGER'],
    ['BINARY'],
    ['NUMERIC', 'TEXT'],
    ['BINARY', 'BINARY'],
    [],
    [],
    'GROUPS',
    'CURRENT ROW',
);

$diagnostics = [];
while (!$cursor->eof()) {
    $snapshot = $cursor->currentNextAggregateSummary('rowid', '|', 2, true);
    $diagnostics[] = [
        'currentOption' => $snapshot['current']['row']['option_name'],
        'currentFilteredRowids' => $snapshot['current']['filteredFrameRowids'],
        'currentSum' => $snapshot['current']['sum'],
        'nextOption' => $snapshot['next']['row']['option_name'] ?? null,
        'nextFilteredRowids' => $snapshot['next']['filteredFrameRowids'] ?? null,
        'nextSum' => $snapshot['next']['sum'] ?? null,
        'advanced' => $snapshot['advanced'],
    ];
    $cursor->next();
}

$payload = [
    'sqlShape' => "sum(bytes) FILTER (WHERE include) OVER (PARTITION BY site ORDER BY bytes, option_name GROUPS BETWEEN CURRENT ROW AND 1 FOLLOWING EXCLUDE CURRENT ROW)",
    'applicationUse' => 'Preview current and next VDBE window aggregate frames for copied wp_options imports without advancing the cursor or requiring ext/sqlite.',
    'rows' => $diagnostics,
];

if (($argv[1] ?? null) === '--self-test') {
    if ($payload['rows'][0]['currentFilteredRowids'] !== [] || $payload['rows'][0]['nextFilteredRowids'] !== [3]) {
        fwrite(STDERR, "Unexpected current/next VDBE window frame diagnostics\n");
        exit(1);
    }
    if ($payload['rows'][3]['currentSum'] !== null || $payload['rows'][4]['currentSum'] !== 30 || $payload['rows'][4]['nextSum'] !== null) {
        fwrite(STDERR, "Unexpected excluded tail-frame aggregate diagnostics\n");
        exit(1);
    }
    echo "application-vdbe-window-filter-exclude-frame-current-next55 self-test passed\n";
    exit(0);
}

echo json_encode($payload, JSON_PRETTY_PRINT) . PHP_EOL;
