<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteMalformedTextCurrentNextCursor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$rows = [
    ['option_id' => 1, 'option_name' => 'plugin_alpha', 'autoload' => 'yes'],
    ['option_id' => 2, 'option_name' => "plugin_\xc3", 'autoload' => 'yes'],
    ['option_id' => 3, 'option_name' => "Plugin_\xc3", 'autoload' => 'no'],
    ['option_id' => 4, 'option_name' => "plugin_\xc3 ", 'autoload' => 'yes'],
    ['option_id' => 5, 'option_name' => "plugin_\xe2\x82", 'autoload' => 'yes'],
    ['option_id' => 6, 'option_name' => 'plugin_é', 'autoload' => 'yes'],
];

$cursor = new SQLiteMalformedTextCurrentNextCursor(
    array_map(static fn (array $row): array => ['key' => $row['option_name'], 'rowid' => $row['option_id'], 'payload' => $row], $rows),
    'NOCASE',
);
$cursor->seek("PLUGIN_\xc3");
$range = SQLiteMalformedTextCurrentNextCursor::optionRowNameRange($rows, "plugin_\xc3", "plugin_\xc4", 'NOCASE', ['autoload' => 'yes']);

$report = [
    'scenario' => 'application-malformed-text-current-next70',
    'currentNext' => $cursor->currentNextPlan("PLUGIN_\xc3"),
    'autoloadYesRangeRowids' => array_column($range, 'rowid'),
    'autoloadYesRangeNamesHex' => array_map(
        static fn (array $entry): string => bin2hex((string) $entry['payload']['option_name']),
        $range,
    ),
    'applicationUse' => 'Copied wp_options imports can inspect malformed option_name bytes with SQLite-style BINARY/NOCASE/RTRIM ordering, preserving current/next cursor diagnostics before repair tools rewrite or drop damaged rows.',
];

if (($argv[1] ?? null) === '--self-test') {
    if (
        $report['currentNext']['currentRowid'] !== 2
        || $report['currentNext']['nextRowid'] !== 3
        || $report['currentNext']['currentMalformedUtf8'] !== true
        || $report['autoloadYesRangeRowids'] !== [2, 4, 6]
    ) {
        fwrite(STDERR, "application-malformed-text-current-next70 self-test failed\n");
        exit(1);
    }
    fwrite(STDOUT, "application-malformed-text-current-next70 self-test passed\n");
    exit(0);
}

echo json_encode($report, JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE) . PHP_EOL;
