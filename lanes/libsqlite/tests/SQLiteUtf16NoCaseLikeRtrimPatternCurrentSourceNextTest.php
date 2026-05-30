<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NoCaseLikeRtrimPatternCurrentSourceNextPlan;

$tests = [];

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
$bad = static fn (int $id, string $bytes, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$currentRows = [
    $row(1, 'Plugin_Cache', 'UTF-16LE'),
    $row(2, 'plugin_cache  ', 'UTF-16BE'),
    $row(3, 'plugin_cache_extra', 'UTF-16LE'),
    $row(4, 'plugin-cache', 'UTF-8'),
    $row(5, 'plugin_Éclair', 'UTF-16BE'),
    $bad(6, "p\0l", 2),
];
$nextRows = [
    $row(1, 'Plugin_Cache', 'UTF-16BE'),
    $row(2, 'plugin_cache', 'UTF-16BE'),
    $row(3, 'plugin_cache_extra', 'UTF-16LE'),
    $row(4, 'plugin-cache', 'UTF-8'),
    $row(7, 'PLUGIN_CACHE', 'UTF-16LE'),
    $bad(8, "\xd8\x00", 3),
];

$plan = static fn (
    ?array $current = null,
    ?array $next = null,
    string $currentPattern = 'plugin\\_cache',
    string $currentPatternEncoding = 'UTF-16LE',
    string $nextPattern = 'plugin\\_cache%',
    string $nextPatternEncoding = 'UTF-16BE',
    ?string $currentEscape = '\\',
    string $currentEscapeEncoding = 'UTF-16LE',
    ?string $nextEscape = '\\',
    string $nextEscapeEncoding = 'UTF-16BE',
): array => SQLiteUtf16NoCaseLikeRtrimPatternCurrentSourceNextPlan::optionRowNamePatternPlan(
    $current ?? $currentRows,
    $next ?? $nextRows,
    $enc($currentPattern, $currentPatternEncoding),
    match ($currentPatternEncoding) {
        'UTF-8' => 1,
        'UTF-16LE' => 2,
        'UTF-16BE' => 3,
    },
    $enc($nextPattern, $nextPatternEncoding),
    match ($nextPatternEncoding) {
        'UTF-8' => 1,
        'UTF-16LE' => 2,
        'UTF-16BE' => 3,
    },
    $currentEscape === null ? null : $enc($currentEscape, $currentEscapeEncoding),
    match ($currentEscapeEncoding) {
        'UTF-8' => 1,
        'UTF-16LE' => 2,
        'UTF-16BE' => 3,
    },
    $nextEscape === null ? null : $enc($nextEscape, $nextEscapeEncoding),
    match ($nextEscapeEncoding) {
        'UTF-8' => 1,
        'UTF-16LE' => 2,
        'UTF-16BE' => 3,
    },
);

$valueAt = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        $value = $value[$part];
    }

    return $value;
};

$cases = [
    'status' => ['status', 'utf16-nocase-like-rtrim-pattern-current-source-nextoneSixZero'],
    'operator' => ['operator', 'LIKE'],
    'index collation' => ['indexCollation', 'RTRIM'],
    'residual collation' => ['residualCollation', 'NOCASE'],
    'case insensitive' => ['caseSensitiveLike', false],
    'current pattern' => ['currentPattern', 'plugin\\_cache'],
    'next pattern' => ['nextPattern', 'plugin\\_cache%'],
    'current pattern encoding' => ['currentPatternEncoding', 'UTF-16LE'],
    'next pattern encoding' => ['nextPatternEncoding', 'UTF-16BE'],
    'current pattern bytes' => ['currentPatternBytesHex', '70006c007500670069006e005c005f0063006100630068006500'],
    'next pattern bytes' => ['nextPatternBytesHex', '0070006c007500670069006e005c005f006300610063006800650025'],
    'current escape' => ['currentEscape', '\\'],
    'next escape' => ['nextEscape', '\\'],
    'current escape bytes' => ['currentEscapeBytesHex', '5c00'],
    'next escape bytes' => ['nextEscapeBytesHex', '005c'],
    'current prefix' => ['currentPrefix', 'plugin_cache'],
    'next prefix' => ['nextPrefix', 'plugin_cache'],
    'current range lower' => ['currentRtrimRange.lowerInclusive', 'plugin_cache'],
    'next range upper' => ['nextRtrimRange.upperBound', 'plugin_cachf'],
    'current index usable' => ['currentIndexUsable', true],
    'next index usable' => ['nextIndexUsable', true],
    'current candidates' => ['currentCandidateRowids', [1, 2, 3]],
    'next candidates' => ['nextCandidateRowids', [7, 1, 2, 3]],
    'current exact matches' => ['currentMatchedRowids', [1]],
    'next wildcard matches' => ['nextMatchedRowids', [7, 1, 2, 3]],
    'current rtrim false positives' => ['currentFalsePositiveRowids', [2, 3]],
    'next no rtrim false positives' => ['nextFalsePositiveRowids', []],
    'retained row' => ['retainedMatchedRowids', [1]],
    'entered rows' => ['enteredMatchedRowids', [7, 2, 3]],
    'exited rows' => ['exitedMatchedRowids', []],
    'current malformed rowids' => ['currentMalformedRowids', [6]],
    'next malformed rowids' => ['nextMalformedRowids', [8]],
    'source invalidated' => ['cursorInvalidated', true],
    'not reusable' => ['cursorReusable', false],
    'reason source' => ['invalidationReasons.0', 'source-name'],
    'reason schema' => ['invalidationReasons.1', 'schema-cookie'],
    'reason text' => ['invalidationReasons.2', 'pattern-text'],
    'reason encoding' => ['invalidationReasons.3', 'pattern-encoding'],
    'reason bytes' => ['invalidationReasons.4', 'pattern-bytes'],
    'reason escape bytes' => ['invalidationReasons.5', 'escape-bytes'],
    'reason candidates' => ['invalidationReasons.6', 'candidate-rowset'],
    'reason matches' => ['invalidationReasons.7', 'matched-rowset'],
    'reason rtrim false positives' => ['invalidationReasons.8', 'rtrim-false-positive-rowset'],
    'reason malformed' => ['invalidationReasons.9', 'malformed-text'],
    'dependency pattern decode' => ['dependencies.0', 'sqlite-utf16-pattern-decode'],
    'dependency current source' => ['dependencies.3', 'sqlite-current-source-nextoneSixZero'],
];

foreach ($cases as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim pattern current source nextOneSixZero ' . $name] = static function (TestRunner $t) use ($plan, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($plan(), $path));
    };
}

$tests['utf16 nocase like rtrim pattern current source nextOneSixZero stable pattern bytes reusable'] = static function (TestRunner $t) use ($row, $enc): void {
    $rows = [
        $row(1, 'Plugin_Cache', 'UTF-16LE'),
        $row(2, 'plugin_cache  ', 'UTF-16BE'),
    ];
    $result = SQLiteUtf16NoCaseLikeRtrimPatternCurrentSourceNextPlan::optionRowNamePatternPlan(
        $rows,
        $rows,
        $enc('plugin\\_cache%', 'UTF-16LE'),
        2,
        $enc('plugin\\_cache%', 'UTF-16LE'),
        2,
        $enc('\\', 'UTF-16LE'),
        2,
        $enc('\\', 'UTF-16LE'),
        2,
        'stable',
        'stable',
        9,
        9,
    );
    $t->same([1, 2], $result['currentMatchedRowids']);
    $t->same([1, 2], $result['nextMatchedRowids']);
    $t->same([], $result['invalidationReasons']);
    $t->same(true, $result['cursorReusable']);
};

$tests['utf16 nocase like rtrim pattern current source nextOneSixZero ascii nocase does not fold unicode'] = static function (TestRunner $t) use ($plan): void {
    $result = $plan(null, null, 'plugin\\_éclair', 'UTF-16LE', 'plugin\\_éclair', 'UTF-16BE');
    $t->same([], $result['currentMatchedRowids']);
    $t->same([], $result['nextMatchedRowids']);
    $t->same('plugin_éclair', $result['currentRtrimRange']['lowerInclusive']);
};

$tests['utf16 nocase like rtrim pattern current source nextOneSixZero null escape disables escape bytes'] = static function (TestRunner $t) use ($plan): void {
    $result = $plan(null, null, 'plugin_cache%', 'UTF-8', 'plugin_cache%', 'UTF-8', null, 'UTF-8', null, 'UTF-8');
    $t->same(null, $result['currentEscape']);
    $t->same(null, $result['currentEscapeBytesHex']);
    $t->same([1, 4, 2, 3], $result['currentMatchedRowids']);
};

$tests['utf16 nocase like rtrim pattern current source nextOneSixZero rejects malformed pattern bytes'] = static function (TestRunner $t) use ($currentRows, $nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NoCaseLikeRtrimPatternCurrentSourceNextPlan::optionRowNamePatternPlan($currentRows, $nextRows, "p\0x", 2, "p\0%", 2));
};

$tests['utf16 nocase like rtrim pattern current source nextOneSixZero rejects multi-character escape'] = static function (TestRunner $t) use ($currentRows, $nextRows, $enc): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NoCaseLikeRtrimPatternCurrentSourceNextPlan::optionRowNamePatternPlan(
        $currentRows,
        $nextRows,
        $enc('plugin%', 'UTF-8'),
        1,
        $enc('plugin%', 'UTF-8'),
        1,
        $enc('xx', 'UTF-8'),
        1,
    ));
};

return $tests;
