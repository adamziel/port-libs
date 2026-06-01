<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteMalformedUtf16LikeRangeCurrentSourceNextPlan;

$row = static function (int $id, string $name, string $encoding): array {
    return [
        'setting_id' => $id,
        'key_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($name, $encoding),
        'text_encoding' => $encoding === 'UTF-16LE' ? 2 : 3,
        'load_policy' => 'yes',
    ];
};

$currentRows = [
    $row(1, 'plugin_alpha', 'UTF-16LE'),
    ['setting_id' => 2, 'key_name_bytes' => "p\x00l\x00u\x00g\x00i\x00n\x00_\x00=\xd8", 'text_encoding' => 2, 'load_policy' => 'yes'],
];
$nextRows = [
    $row(1, 'plugin_alpha', 'UTF-16LE'),
    $row(3, 'plugin_new', 'UTF-16BE'),
    ['setting_id' => 2, 'key_name_bytes' => "p\x00l\x00u\x00g\x00i\x00n\x00_\x00=\xd8", 'text_encoding' => 2, 'load_policy' => 'yes'],
];

echo json_encode(
    SQLiteMalformedUtf16LikeRangeCurrentSourceNextPlan::keyValueRowKeyLikeRange(
        $currentRows,
        $nextRows,
        'plugin%',
        'NOCASE',
        null,
        false,
        'main.app_settings@cookie92',
        'main.app_settings@cookie93',
    ),
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
) . "\n";
