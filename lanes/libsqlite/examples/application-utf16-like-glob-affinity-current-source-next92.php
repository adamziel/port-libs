<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16LikeGlobAffinityCurrentSourceNextPlan;

require_once __DIR__ . '/../../../tools/bootstrap.php';

$row = static function (int $id, string $value, string $encoding): array {
    return [
        'option_id' => $id,
        'option_value_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($value, $encoding),
        'text_encoding' => match ($encoding) {
            'UTF-8' => 1,
            'UTF-16LE' => 2,
            'UTF-16BE' => 3,
        },
    ];
};

$current = [
    $row(1, 'autoload:yes', 'UTF-8'),
    $row(2, 'cache:%literal', 'UTF-16LE'),
    ['option_id' => 3, 'option_value' => 42],
    ['option_id' => 9, 'option_value_bytes' => "\x00\xd8", 'text_encoding' => 2],
];

$next = [
    $row(1, 'autoload:yes', 'UTF-16LE'),
    $row(2, 'cache:%literal', 'UTF-16BE'),
    ['option_id' => 3, 'option_value' => '42'],
    $row(4, 'autoload:fresh', 'UTF-16BE'),
];

$result = [
    'scenario' => 'application-utf16-like-glob-affinity-current-source-next92',
    'autoloadLike' => SQLiteUtf16LikeGlobAffinityCurrentSourceNextPlan::optionRowValuePlan($current, $next, 'autoload:%'),
    'literalPercent' => SQLiteUtf16LikeGlobAffinityCurrentSourceNextPlan::optionRowValuePlan($current, $next, 'cache:!%%', 'LIKE', '!'),
    'numericGlob' => SQLiteUtf16LikeGlobAffinityCurrentSourceNextPlan::optionRowValuePlan($current, $next, '4*', 'GLOB'),
];

if (($argv[1] ?? null) === '--self-test') {
    assert($result['autoloadLike']['currentRowids'] === [1]);
    assert($result['autoloadLike']['nextRowids'] === [1, 4]);
    assert($result['autoloadLike']['changedEncodingRowids'] === [1]);
    assert($result['autoloadLike']['currentMalformedRowids'] === [9]);
    assert($result['literalPercent']['changedBytesRowids'] === [2]);
    assert($result['numericGlob']['changedStorageRowids'] === [3]);
    echo "application-utf16-like-glob-affinity-current-source-next92 self-test passed\n";
    return;
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
