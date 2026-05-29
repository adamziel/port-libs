<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16LikeGlobAffinityRangeCurrentSourceNextPlan;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$bytes = static fn (string $text, string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);

$currentRows = [
    ['option_id' => 1, 'option_value' => 'autoload:yes'],
    ['option_id' => 2, 'option_value' => 10],
    ['option_id' => 3, 'option_value' => 'plugin_α:enabled'],
    ['option_id' => 4, 'option_value' => 'cache:%literal'],
];

$nextRows = [
    ['option_id' => 1, 'option_value' => 'autoload:yes-v2'],
    ['option_id' => 2, 'option_value' => '10'],
    ['option_id' => 3, 'option_value' => 'plugin_β:enabled'],
    ['option_id' => 4, 'option_value' => 'cache:%literal'],
    ['option_id' => 5, 'option_value' => 'autoload:fresh'],
];

$plan = SQLiteUtf16LikeGlobAffinityRangeCurrentSourceNextPlan::wordpressOptionValuePlan(
    $currentRows,
    $nextRows,
    'option_value',
    $bytes('autoload:%', 'UTF-16LE'),
    'UTF-16LE',
    'LIKE',
    'TEXT',
    'BINARY',
    null,
    null,
    true,
    'main.wp_options@cookie1240',
    'main.wp_options@cookie1241',
    1240,
    1241,
);

$preview = [
    'scenario' => 'wordpress-utf16-like-glob-affinity-range-current-source-next124',
    'wordpressUse' => 'Copied wp_options import scans can decode a UTF-16 LIKE pattern, preserve affinity-coerced option_value matches, and expose encoded range bounds before a schema-cookie source switch forces reprepare.',
    'decodedPattern' => $plan['decodedPattern'],
    'patternEncoding' => $plan['patternEncoding'],
    'rangeUtf16LeHex' => $plan['rangeUtf16LeHex'],
    'currentRowids' => $plan['currentRowids'],
    'nextRowids' => $plan['nextRowids'],
    'enteredRowids' => $plan['enteredRowids'],
    'changedTextRowids' => $plan['changedTextRowids'],
    'cursorInvalidated' => $plan['cursorInvalidated'],
    'invalidationReasons' => $plan['invalidationReasons'],
    'dependencies' => $plan['dependencies'],
];

if (($argv[1] ?? null) === '--self-test') {
    assert($preview['decodedPattern'] === 'autoload:%');
    assert($preview['patternEncoding'] === 'UTF-16LE');
    assert($preview['currentRowids'] === [1]);
    assert($preview['nextRowids'] === [5, 1]);
    assert($preview['enteredRowids'] === [5]);
    assert($preview['changedTextRowids'] === [1]);
    assert($preview['cursorInvalidated'] === true);
    assert(in_array('schema-cookie', $preview['invalidationReasons'], true));
    echo "wordpress-utf16-like-glob-affinity-range-current-source-next124 self-test passed\n";
    return;
}

echo json_encode($preview, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
