<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteMalformedTextCurrentNextCursor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$rows = [
    ['setting_id' => 1, 'key_name' => 'module_alpha', 'load_policy' => 'yes'],
    ['setting_id' => 2, 'key_name' => "module_\xc3", 'load_policy' => 'yes'],
    ['setting_id' => 3, 'key_name' => "Module_\xc3", 'load_policy' => 'no'],
    ['setting_id' => 4, 'key_name' => "module_\xc3 ", 'load_policy' => 'yes'],
    ['setting_id' => 5, 'key_name' => "module_\xe2\x82", 'load_policy' => 'yes'],
    ['setting_id' => 6, 'key_name' => 'module_é', 'load_policy' => 'yes'],
];

$cursor = new SQLiteMalformedTextCurrentNextCursor(
    array_map(static fn (array $row): array => ['key' => $row['key_name'], 'rowid' => $row['setting_id'], 'payload' => $row], $rows),
    'NOCASE',
);
$cursor->seek("MODULE_\xc3");
$range = SQLiteMalformedTextCurrentNextCursor::settingRowKeyRange($rows, "module_\xc3", "module_\xc4", 'NOCASE', ['load_policy' => 'yes']);

$report = [
    'scenario' => 'application-malformed-text-current-next70',
    'currentNext' => $cursor->currentNextPlan("MODULE_\xc3"),
    'loadPolicyYesRangeRowids' => array_column($range, 'rowid'),
    'loadPolicyYesRangeNamesHex' => array_map(
        static fn (array $entry): string => bin2hex((string) $entry['payload']['key_name']),
        $range,
    ),
    'applicationUse' => 'Application settings imports can inspect malformed key_name bytes with SQLite-style BINARY/NOCASE/RTRIM ordering, preserving current/next cursor diagnostics before repair tools rewrite or drop damaged rows.',
];

if (($argv[1] ?? null) === '--self-test') {
    if (
        $report['currentNext']['currentRowid'] !== 2
        || $report['currentNext']['nextRowid'] !== 3
        || $report['currentNext']['currentMalformedUtf8'] !== true
        || $report['loadPolicyYesRangeRowids'] !== [2, 4, 6]
    ) {
        fwrite(STDERR, "application-malformed-text-current-next70 self-test failed\n");
        exit(1);
    }
    fwrite(STDOUT, "application-malformed-text-current-next70 self-test passed\n");
    exit(0);
}

echo json_encode($report, JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE) . PHP_EOL;
