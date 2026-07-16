<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16LikeGlobAffinityCurrentSourceNextPlan;

require_once __DIR__ . '/../../../tools/bootstrap.php';

$row = static function (int $id, string $value, string $encoding): array {
    return [
        'setting_id' => $id,
        'key_value_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($value, $encoding),
        'text_encoding' => match ($encoding) {
            'UTF-8' => 1,
            'UTF-16LE' => 2,
            'UTF-16BE' => 3,
        },
    ];
};

$current = [
    $row(1, 'load_policy:yes', 'UTF-8'),
    $row(2, 'cache:%literal', 'UTF-16LE'),
    ['setting_id' => 3, 'key_value' => 42],
    ['setting_id' => 9, 'key_value_bytes' => "\x00\xd8", 'text_encoding' => 2],
];

$next = [
    $row(1, 'load_policy:yes', 'UTF-16LE'),
    $row(2, 'cache:%literal', 'UTF-16BE'),
    ['setting_id' => 3, 'key_value' => '42'],
    $row(4, 'load_policy:fresh', 'UTF-16BE'),
];

$result = [
    'scenario' => 'application-utf16-like-glob-affinity-current-source-next92',
    'load_policyLike' => SQLiteUtf16LikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan($current, $next, 'load_policy:%'),
    'literalPercent' => SQLiteUtf16LikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan($current, $next, 'cache:!%%', 'LIKE', '!'),
    'numericGlob' => SQLiteUtf16LikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan($current, $next, '4*', 'GLOB'),
];

if (($argv[1] ?? null) === '--self-test') {
    assert($result['load_policyLike']['currentRowids'] === [1]);
    assert($result['load_policyLike']['nextRowids'] === [1, 4]);
    assert($result['load_policyLike']['changedEncodingRowids'] === [1]);
    assert($result['load_policyLike']['currentMalformedRowids'] === [9]);
    assert($result['literalPercent']['changedBytesRowids'] === [2]);
    assert($result['numericGlob']['changedStorageRowids'] === [3]);
    echo "application-utf16-like-glob-affinity-current-source-next92 self-test passed\n";
    return;
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
