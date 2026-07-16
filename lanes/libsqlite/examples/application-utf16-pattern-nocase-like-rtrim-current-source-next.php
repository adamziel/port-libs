<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16PatternNoCaseLikeRtrimCurrentSourceNextPlan;

$row = static function (int $id, string $name, string $encoding): array {
    return [
        'setting_id' => $id,
        'key_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($name, $encoding),
        'text_encoding' => match ($encoding) {
            'UTF-8' => 1,
            'UTF-16LE' => 2,
            'UTF-16BE' => 3,
        },
    ];
};

$pattern = static fn (string $text, string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);

$plan = SQLiteUtf16PatternNoCaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyPlan(
    [
        $row(1, 'Plugin_Cache', 'UTF-16LE'),
        $row(2, 'plugin_cache  ', 'UTF-16BE'),
        $row(3, 'plugin_cache_backup', 'UTF-8'),
    ],
    [
        $row(1, 'Plugin_Cache', 'UTF-16BE'),
        $row(3, 'plugin_cache_backup  ', 'UTF-16LE'),
        $row(4, 'PLUGIN_CACHE_BACKUP', 'UTF-16BE'),
    ],
    $pattern('plugin\\_cache%', 'UTF-16LE'),
    'UTF-16LE',
    $pattern('plugin\\_cache\\_backup%', 'UTF-16BE'),
    'UTF-16BE',
    $pattern('\\', 'UTF-16LE'),
    'UTF-16LE',
    $pattern('\\', 'UTF-16BE'),
    'UTF-16BE',
);

if (($argv[1] ?? null) === '--self-test') {
    assert($plan['currentPattern'] === 'plugin\\_cache%');
    assert($plan['nextPattern'] === 'plugin\\_cache\\_backup%');
    assert($plan['currentMatchedRowids'] === [1, 2, 3]);
    assert($plan['nextMatchedRowids'] === [4, 3]);
    assert(in_array('pattern-text', $plan['invalidationReasons'], true));
    assert(in_array('escape-encoding', $plan['invalidationReasons'], true));
    echo "application-utf16-pattern-nocase-like-rtrim-current-source-next self-test passed\n";
    return;
}

echo json_encode([
    'status' => $plan['status'],
    'currentPattern' => $plan['currentPattern'],
    'nextPattern' => $plan['nextPattern'],
    'currentPatternEncoding' => $plan['currentPatternEncoding'],
    'nextPatternEncoding' => $plan['nextPatternEncoding'],
    'currentMatchedRowids' => $plan['currentMatchedRowids'],
    'nextMatchedRowids' => $plan['nextMatchedRowids'],
    'invalidated' => $plan['cursorInvalidated'],
    'reasons' => $plan['invalidationReasons'],
    'dependencyClosure' => $plan['dependency_closure'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
