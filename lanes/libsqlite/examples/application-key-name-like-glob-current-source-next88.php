<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteLikeGlobCurrentSourceNextPlan;

$row = static function (int $id, string $name, string $encoding, string $load_policy = 'yes'): array {
    return [
        'setting_id' => $id,
        'key_name' => $name,
        'key_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($name, $encoding),
        'text_encoding' => match ($encoding) {
            'UTF-8' => 1,
            'UTF-16LE' => 2,
            'UTF-16BE' => 3,
        },
        'load_policy' => $load_policy,
    ];
};

$currentRows = [
    $row(1, 'Module_Alpha', 'UTF-8', 'no'),
    $row(2, 'module_alpha', 'UTF-16LE'),
    $row(3, 'module_100%_enabled', 'UTF-16LE'),
    $row(4, 'module_old', 'UTF-8'),
    $row(5, 'bundle_alpha', 'UTF-8'),
];

$nextRows = [
    $row(1, 'Module_Alpha', 'UTF-16LE', 'no'),
    $row(2, 'module_alpha', 'UTF-16LE'),
    $row(3, 'module_100%_enabled', 'UTF-16BE'),
    $row(6, 'module_new', 'UTF-16BE'),
    $row(5, 'bundle_alpha', 'UTF-8'),
];

$currentStatement = [
    'source' => 'main.app_settings@schema-cookie-10',
    'operator' => 'LIKE',
    'pattern' => 'module\_100\%%',
    'collation' => 'NOCASE',
    'escape' => '\\',
    'caseSensitiveLike' => false,
];
$nextStatement = [
    'source' => 'main.app_settings@schema-cookie-11',
    'operator' => 'LIKE',
    'pattern' => 'module\_100\%%',
    'collation' => 'NOCASE',
    'escape' => '\\',
    'caseSensitiveLike' => false,
];

$literalPercentPlan = SQLiteLikeGlobCurrentSourceNextPlan::keyValueRowKeyStatement(
    $currentRows,
    $nextRows,
    $currentStatement,
    $nextStatement,
);

$globPlan = SQLiteLikeGlobCurrentSourceNextPlan::keyValueRowKeyStatement(
    $currentRows,
    $nextRows,
    [
        'source' => 'main.app_settings@schema-cookie-10',
        'operator' => 'GLOB',
        'pattern' => 'module_*',
        'collation' => 'BINARY',
    ],
    [
        'source' => 'main.app_settings@schema-cookie-11',
        'operator' => 'GLOB',
        'pattern' => 'module_*',
        'collation' => 'BINARY',
    ],
);

$result = [
    'scenario' => 'copied app_settings LIKE/GLOB current-source next88 reprepare guard',
    'literalPercentStatus' => $literalPercentPlan['status'],
    'literalPercentReasons' => $literalPercentPlan['reprepareReasons'],
    'literalPercentRetainedRowids' => $literalPercentPlan['retainedRowids'],
    'literalPercentChangedBytesRowids' => $literalPercentPlan['changedBytesRowids'],
    'globCurrentRowids' => $globPlan['current']['rowids'],
    'globNextRowids' => $globPlan['next']['rowids'],
    'globExitedRowids' => $globPlan['exitedRowids'],
    'globEnteredRowids' => $globPlan['enteredRowids'],
    'dependencies' => $literalPercentPlan['dependencies'],
];

if (in_array('--self-test', $argv, true)) {
    assert($result['literalPercentStatus'] === 'reprepare-required');
    assert($result['literalPercentReasons'] === ['source-name', 'text-encoding', 'key-bytes']);
    assert($result['literalPercentRetainedRowids'] === [3]);
    assert($result['literalPercentChangedBytesRowids'] === [3]);
    assert($result['globCurrentRowids'] === [3, 2, 4]);
    assert($result['globNextRowids'] === [3, 2, 6]);
    assert($result['globExitedRowids'] === [4]);
    assert($result['globEnteredRowids'] === [6]);
    echo "application-setting-key-like-glob-current-source-next88 self-test passed\n";
    return;
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
