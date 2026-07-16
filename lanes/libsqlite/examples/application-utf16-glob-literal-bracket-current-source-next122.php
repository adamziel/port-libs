<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/SQLiteDatabase.php';
require_once __DIR__ . '/../src/SQLiteBlobValue.php';
require_once __DIR__ . '/../src/SQLiteAffinityComparison.php';
require_once __DIR__ . '/../src/SQLiteLikeCollationPlan.php';
require_once __DIR__ . '/../src/SQLiteEncodingCollationSourceCursor.php';
require_once __DIR__ . '/../src/SQLiteUtf16LikeGlobAffinityCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteUtf16PatternLikeGlobAffinityCurrentSourceNextPlan.php';
require_once __DIR__ . '/../src/SQLiteUtf16CollationAffinityPatternCurrentSourceNextPlan.php';

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16CollationAffinityPatternCurrentSourceNextPlan;

$bytes = static fn (string $value, string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($value, $encoding);
$row = static fn (int $id, string $value, string $encoding = 'UTF-16LE'): array => [
    'setting_id' => $id,
    'key_value_bytes' => $bytes($value, $encoding),
    'text_encoding' => match ($encoding) {
        'UTF-8' => 1,
        'UTF-16LE' => 2,
        'UTF-16BE' => 3,
    },
];

$currentRows = [
    $row(1, 'plugin_[draft]'),
    $row(2, 'plugin_[draft]_cache'),
    $row(3, 'plugin_a_cache'),
    $row(4, 'plugin_[stable'),
];

$nextRows = [
    $row(1, 'plugin_[draft]'),
    $row(2, 'plugin_[draft]_cache', 'UTF-16BE'),
    $row(3, 'plugin_a_cache'),
    $row(4, 'plugin_[stable'),
    $row(5, 'plugin_[draft_new', 'UTF-16BE'),
];

$plan = SQLiteUtf16CollationAffinityPatternCurrentSourceNextPlan::keyValueRowValuePlan(
    $currentRows,
    $nextRows,
    $bytes('plugin_[draft*', 'UTF-16LE'),
    'UTF-16LE',
    'GLOB',
    'BINARY',
    null,
    null,
    false,
    'main.app_settings@current',
    'main.app_settings@next',
    'UTF-16LE',
    'UTF-16BE',
);

if (($argv[1] ?? null) === '--self-test') {
    $expected = [
        'lower' => 'plugin_[draft',
        'upper' => 'plugin_[drafu',
        'current' => [1, 2],
        'next' => [1, 2, 5],
        'entered' => [5],
        'reasons' => ['source-name', 'text-encoding', 'value-bytes', 'matched-rowset', 'range-encoding', 'range-bytes'],
    ];
    $actual = [
        'lower' => $plan['rangePlan']['range']['lowerInclusive'],
        'upper' => $plan['rangePlan']['range']['upperBound'],
        'current' => $plan['currentRowids'],
        'next' => $plan['nextRowids'],
        'entered' => $plan['enteredRowids'],
        'reasons' => $plan['invalidationReasons'],
    ];
    if ($actual !== $expected) {
        fwrite(STDERR, "application-utf16-glob-literal-bracket-current-source-next122 self-test failed\n");
        fwrite(STDERR, var_export($actual, true) . "\n");
        exit(1);
    }

    echo "application-utf16-glob-literal-bracket-current-source-next122 self-test passed\n";
    exit(0);
}

echo json_encode([
    'pattern' => $plan['decodedPattern'],
    'range' => $plan['rangePlan']['range'],
    'currentRowids' => $plan['currentRowids'],
    'nextRowids' => $plan['nextRowids'],
    'enteredRowids' => $plan['enteredRowids'],
    'invalidationReasons' => $plan['invalidationReasons'],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
