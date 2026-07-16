<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteEncodingLikeGlobSourceSwitchPlan;

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
    $row(1, 'Plugin_Alpha', 'UTF-8', 'no'),
    $row(2, 'plugin_alpha', 'UTF-16LE'),
    $row(3, 'plugin_100%_enabled', 'UTF-16LE'),
    $row(4, 'plugin_old', 'UTF-8'),
    $row(5, 'theme_alpha', 'UTF-8'),
];

$nextRows = [
    $row(1, 'Plugin_Alpha', 'UTF-16LE', 'no'),
    $row(2, 'plugin_alpha', 'UTF-16LE'),
    $row(3, 'plugin_100%_enabled', 'UTF-16BE'),
    $row(6, 'plugin_new', 'UTF-16BE'),
    $row(5, 'theme_alpha', 'UTF-8'),
];

$pluginPlan = SQLiteEncodingLikeGlobSourceSwitchPlan::keyValueRowKeySourceSwitch(
    $currentRows,
    $nextRows,
    'plugin%',
    'LIKE',
    'NOCASE',
    null,
    false,
    'main.app_settings@schema-cookie-10',
    'main.app_settings@schema-cookie-11',
);

$literalPercentPlan = SQLiteEncodingLikeGlobSourceSwitchPlan::keyValueRowKeySourceSwitch(
    $currentRows,
    $nextRows,
    'plugin\_100\%%',
    'LIKE',
    'NOCASE',
    '\\',
    false,
    'main.app_settings@schema-cookie-10',
    'main.app_settings@schema-cookie-11',
);

$result = [
    'scenario' => 'copied app_settings encoding LIKE/GLOB current-source to next-source switch current-next86',
    'pluginInvalidated' => $pluginPlan['cursorInvalidated'],
    'pluginReasons' => $pluginPlan['invalidationReasons'],
    'pluginExitedRowids' => $pluginPlan['exitedRowids'],
    'pluginEnteredRowids' => $pluginPlan['enteredRowids'],
    'pluginChangedEncodingRowids' => $pluginPlan['changedEncodingRowids'],
    'literalPercentRetainedRowids' => $literalPercentPlan['retainedRowids'],
    'literalPercentChangedBytesRowids' => $literalPercentPlan['changedBytesRowids'],
    'dependencies' => $pluginPlan['dependencies'],
];

if (in_array('--self-test', $argv, true)) {
    assert($result['pluginInvalidated'] === true);
    assert($result['pluginReasons'] === ['source-name', 'text-encoding', 'key-bytes', 'matched-rowset']);
    assert($result['pluginExitedRowids'] === [4]);
    assert($result['pluginEnteredRowids'] === [6]);
    assert($result['pluginChangedEncodingRowids'] === [1, 3]);
    assert($result['literalPercentRetainedRowids'] === [3]);
    assert($result['literalPercentChangedBytesRowids'] === [3]);
    echo "application-setting-key-source-switch-current-next86 self-test passed\n";
    return;
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
