<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16PatternLikeGlobAffinityCurrentSourceNextPlan;

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
$pattern = static fn (string $text, string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);

$current = [
    $row(1, 'loadflag:yes', 'UTF-8'),
    $row(2, 'cache:%literal', 'UTF-16BE'),
    $row(3, 'plugin_α:enabled', 'UTF-16LE'),
    ['setting_id' => 4, 'key_value' => 10],
    ['setting_id' => 9, 'key_value_bytes' => "\x00\xd8", 'text_encoding' => 2],
];

$next = [
    $row(1, 'loadflag:yes', 'UTF-16LE'),
    $row(2, 'cache:%literal', 'UTF-16LE'),
    $row(3, 'plugin_γ:enabled', 'UTF-16BE'),
    ['setting_id' => 4, 'key_value' => '10'],
    $row(5, 'loadflag:fresh', 'UTF-16BE'),
];

$result = [
    'scenario' => 'application-utf16-pattern-like-glob-affinity-current-source-next114',
    'loadflagLike' => SQLiteUtf16PatternLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan($current, $next, $pattern('loadflag:%', 'UTF-16LE'), 'UTF-16LE'),
    'literalPercent' => SQLiteUtf16PatternLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan($current, $next, $pattern('cache:!%%', 'UTF-16BE'), 'UTF-16BE', 'LIKE', $pattern('!', 'UTF-16BE'), 'UTF-16BE'),
    'greekGlob' => SQLiteUtf16PatternLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan($current, $next, $pattern('plugin_[αγ]:*', 'UTF-16LE'), 'UTF-16LE', 'GLOB'),
    'numericLike' => SQLiteUtf16PatternLikeGlobAffinityCurrentSourceNextPlan::keyValueRowValuePlan($current, $next, $pattern('10', 'UTF-16LE'), 'UTF-16LE'),
];

if (($argv[1] ?? null) === '--self-test') {
    assert($result['loadflagLike']['currentRowids'] === [1]);
    assert($result['loadflagLike']['nextRowids'] === [1, 5]);
    assert($result['loadflagLike']['changedEncodingRowids'] === [1]);
    assert($result['loadflagLike']['currentMalformedRowids'] === [9]);
    assert($result['literalPercent']['currentRowids'] === [2]);
    assert($result['literalPercent']['changedBytesRowids'] === [2]);
    assert($result['greekGlob']['currentRowids'] === [3]);
    assert($result['greekGlob']['nextRowids'] === [3]);
    assert($result['numericLike']['changedStorageRowids'] === [4]);
    echo "application-utf16-pattern-like-glob-affinity-current-source-next114 self-test passed\n";
    return;
}

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
