<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteMalformedUtf16LikeRangeCurrentSourceNextPlan;

$row = static function (int $id, string $name, string $encoding): array {
    return [
        'option_id' => $id,
        'option_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($name, $encoding),
        'text_encoding' => $encoding === 'UTF-16LE' ? 2 : 3,
        'autoload' => 'yes',
    ];
};

$currentRows = [
    $row(1, 'plugin_alpha', 'UTF-16LE'),
    ['option_id' => 2, 'option_name_bytes' => "p\x00l\x00u\x00g\x00i\x00n\x00_\x00=\xd8", 'text_encoding' => 2, 'autoload' => 'yes'],
];
$nextRows = [
    $row(1, 'plugin_alpha', 'UTF-16LE'),
    $row(3, 'plugin_new', 'UTF-16BE'),
    ['option_id' => 2, 'option_name_bytes' => "p\x00l\x00u\x00g\x00i\x00n\x00_\x00=\xd8", 'text_encoding' => 2, 'autoload' => 'yes'],
];

echo json_encode(
    SQLiteMalformedUtf16LikeRangeCurrentSourceNextPlan::optionRowNameLikeRange(
        $currentRows,
        $nextRows,
        'plugin%',
        'NOCASE',
        null,
        false,
        'main.wp_options@cookie92',
        'main.wp_options@cookie93',
    ),
    JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
) . "\n";
