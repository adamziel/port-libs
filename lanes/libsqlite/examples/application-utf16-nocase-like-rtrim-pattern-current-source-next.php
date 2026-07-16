<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NoCaseLikeRtrimPatternCurrentSourceNextPlan;

$enc = static fn (string $text, string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row = static function (int $id, string $name, string $encoding) use ($enc): array {
    return [
        'option_id' => $id,
        'option_name_bytes' => $enc($name, $encoding),
        'text_encoding' => match ($encoding) {
            'UTF-8' => 1,
            'UTF-16LE' => 2,
            'UTF-16BE' => 3,
        },
    ];
};

$plan = SQLiteUtf16NoCaseLikeRtrimPatternCurrentSourceNextPlan::optionRowNamePatternPlan(
    [
        $row(1, 'Plugin_Cache', 'UTF-16LE'),
        $row(2, 'plugin_cache  ', 'UTF-16BE'),
        $row(3, 'plugin_cache_extra', 'UTF-16LE'),
    ],
    [
        $row(1, 'Plugin_Cache', 'UTF-16BE'),
        $row(2, 'plugin_cache', 'UTF-16BE'),
        $row(4, 'PLUGIN_CACHE', 'UTF-16LE'),
    ],
    $enc('plugin\\_cache', 'UTF-16LE'),
    2,
    $enc('plugin\\_cache%', 'UTF-16BE'),
    3,
    $enc('\\', 'UTF-16LE'),
    2,
    $enc('\\', 'UTF-16BE'),
    3,
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['status'] === 'utf16-nocase-like-rtrim-pattern-current-source-next160');
    assert($plan['currentMatchedRowids'] === [1]);
    assert($plan['nextMatchedRowids'] === [4, 1, 2]);
    assert(in_array('pattern-encoding', $plan['invalidationReasons'], true));
    echo "application-utf16-nocase-like-rtrim-pattern-current-source-next self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'currentPatternEncoding' => $plan['currentPatternEncoding'],
    'nextPatternEncoding' => $plan['nextPatternEncoding'],
    'currentMatches' => $plan['currentMatchedRowids'],
    'nextMatches' => $plan['nextMatchedRowids'],
    'invalidated' => $plan['cursorInvalidated'],
    'reasons' => $plan['invalidationReasons'],
    'dependencyClosure' => $plan['dependency_closure'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
