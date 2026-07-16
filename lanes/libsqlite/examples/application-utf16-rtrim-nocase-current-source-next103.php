<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteUtf16CollationAffinityCursor;
use PortLibs\LibSqlite\SQLiteUtf16RtrimNocaseCurrentSourceNextPlan;

$row = static function (int $id, string $name, string $encoding): array {
    return [
        'setting_id' => $id,
        'key_name_bytes' => SQLiteUtf16CollationAffinityCursor::encodeText($name, $encoding),
        'text_encoding' => match ($encoding) {
            'UTF-8' => 1,
            'UTF-16LE' => 2,
            'UTF-16BE' => 3,
        },
    ];
};

$current = [
    $row(1, 'Plugin_Cache   ', 'UTF-16LE'),
    $row(2, 'plugin_cache', 'UTF-16BE'),
    $row(3, 'plugin_cache' . "\t", 'UTF-16LE'),
];

$next = [
    $row(1, 'Plugin_Cache', 'UTF-16BE'),
    $row(2, 'plugin_cache  ', 'UTF-16LE'),
    $row(4, 'PLUGIN_CACHE   ', 'UTF-8'),
    $row(3, 'plugin_cache' . "\t", 'UTF-16LE'),
];

$plan = SQLiteUtf16RtrimNocaseCurrentSourceNextPlan::keyValueRowKeyCurrentNext(
    $current,
    $next,
    'plugin_cache',
    'main.app_settings@cookie102',
    'main.app_settings@cookie103',
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['currentRowids'] === [1, 2]);
    assert($plan['nextRowids'] === [1, 2, 4]);
    assert($plan['enteredRowids'] === [4]);
    assert($plan['currentComparisonKeys'][3] === "plugin_cache\t");
    echo "application-utf16-rtrim-nocase-current-source-next103 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
