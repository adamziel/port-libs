<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16RtrimNocaseCurrentSourceNextPlan;

$row = static function (int $id, string $name, string $encoding): array {
    return [
        'option_id' => $id,
        'option_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($name, $encoding),
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
    $row(3, "plugin_cache\t", 'UTF-16LE'),
];

$next = [
    $row(1, 'Plugin_Cache', 'UTF-16BE'),
    $row(2, 'plugin_cache  ', 'UTF-16LE'),
    $row(3, "plugin_cache\t", 'UTF-16LE'),
];

$plan = SQLiteUtf16RtrimNocaseCurrentSourceNextPlan::optionRowNameCurrentNext(
    $current,
    $next,
    'plugin_cache',
    'main.wp_options@131',
    'main.wp_options@132',
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['currentRowids'] === [1, 2]);
    assert($plan['nextRowids'] === [1, 2]);
    assert($plan['retainedEncodingChangedRowids'] === [1, 2]);
    assert($plan['retainedBytesChangedRowids'] === [1, 2]);
    assert($plan['reprepareReasons'] === ['source-name', 'text-encoding', 'key-bytes']);
    echo "application-utf16-rtrim-nocase-current-source-next132 self-test passed\n";
    return;
}

echo json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
