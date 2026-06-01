<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$tests = [];

$enc212 = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row212 = static fn (int $id, string $name, int|string $encoding): array => [
    'setting_id' => $id,
    'key_name_bytes' => $enc212($name, $encoding),
    'text_encoding' => match ($encoding) {
        'UTF-8', 1 => 1,
        'UTF-16LE', 2 => 2,
        'UTF-16BE', 3 => 3,
    },
];
$bad212 = static fn (int $id, string $bytes, int $encoding): array => [
    'setting_id' => $id,
    'key_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$fullwidthBang212 = "\xef\xbc\x81";
$currentPattern212 = "plugin{$fullwidthBang212}_%";
$nextPattern212 = "plugin{$fullwidthBang212}%%";
$currentRows212 = [
    $row212(1, 'plugin_cache', 'UTF-16LE'),
    $row212(2, 'Plugin_Cache  ', 'UTF-16BE'),
    $row212(3, 'plugin%cache', 'UTF-16LE'),
    $row212(4, 'plugin_cache_extra', 'UTF-8'),
    $row212(5, 'plugin!cache', 'UTF-16BE'),
    $row212(6, "plugin_cache\t", 'UTF-16LE'),
    $row212(7, 'theme_plugin_cache', 'UTF-16BE'),
    $bad212(8, "\x00\xd8", 2),
];
$nextRows212 = [
    $row212(1, 'plugin_cache', 'UTF-16BE'),
    $row212(2, 'Plugin_Cache', 'UTF-16LE'),
    $row212(3, 'plugin%cache', 'UTF-16BE'),
    $row212(4, 'plugin_cache_extra', 'UTF-8'),
    $row212(5, 'plugin!cache', 'UTF-16BE'),
    $row212(6, "plugin_cache\t", 'UTF-16LE'),
    $row212(9, 'PLUGIN%NEW  ', 'UTF-16LE'),
    $bad212(10, "\x00\xd8", 2),
];

$plan212 = static fn (
    ?array $current = null,
    ?array $next = null,
    ?string $currentPattern = null,
    ?string $nextPattern = null,
    int|string $currentPatternEncoding = 'UTF-16LE',
    int|string $nextPatternEncoding = 'UTF-16BE',
    ?string $currentEscape = null,
    ?string $nextEscape = null,
    int|string $currentEscapeEncoding = 'UTF-16LE',
    int|string $nextEscapeEncoding = 'UTF-16BE',
): array => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyUnicodeEscapePlan(
    $current ?? $currentRows212,
    $next ?? $nextRows212,
    $enc212($currentPattern ?? $currentPattern212, $currentPatternEncoding),
    $currentPatternEncoding,
    $enc212($nextPattern ?? $nextPattern212, $nextPatternEncoding),
    $nextPatternEncoding,
    $enc212($currentEscape ?? $fullwidthBang212, $currentEscapeEncoding),
    $currentEscapeEncoding,
    $enc212($nextEscape ?? $fullwidthBang212, $nextEscapeEncoding),
    $nextEscapeEncoding,
);

$valueAt212 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases212 = [
    'status' => ['status', 'utf16-nocase-like-rtrim-current-source-nexttwoOneTwo'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(key_name) COLLATE NOCASE LIKE ? ESCAPE ? /* UTF-16 Unicode ESCAPE */'],
    'current pattern' => ['currentPattern', $currentPattern212],
    'next pattern' => ['nextPattern', $nextPattern212],
    'current escape' => ['currentEscape', $fullwidthBang212],
    'next escape' => ['nextEscape', $fullwidthBang212],
    'current pattern encoding' => ['currentPatternEncoding', 'UTF-16LE'],
    'next pattern encoding' => ['nextPatternEncoding', 'UTF-16BE'],
    'current escape encoding' => ['currentEscapeEncoding', 'UTF-16LE'],
    'next escape encoding' => ['nextEscapeEncoding', 'UTF-16BE'],
    'unicode escape flag' => ['unicodeEscapeCharacter', true],
    'current escape length' => ['currentEscapeTextLength', 1],
    'next escape length' => ['nextEscapeTextLength', 1],
    'current ascii equivalent' => ['currentAsciiEquivalentPattern', 'plugin!_%'],
    'next ascii equivalent' => ['nextAsciiEquivalentPattern', 'plugin!%%'],
    'current source' => ['currentSource', 'main.app_settings@211'],
    'next source' => ['nextSource', 'main.app_settings@212'],
    'current cookie' => ['currentSchemaCookie', 211],
    'next cookie' => ['nextSchemaCookie', 212],
    'prefix' => ['prefix', 'plugin_'],
    'next prefix' => ['nextPrefix', 'plugin%'],
    'range lower' => ['rangeLowerInclusive', 'plugin_'],
    'range upper' => ['rangeUpperBound', 'plugin`'],
    'next range lower' => ['nextRangeLowerInclusive', 'plugin%'],
    'next range upper' => ['nextRangeUpperBound', 'plugin&'],
    'current index usable' => ['currentIndexUsable', true],
    'next index usable' => ['nextIndexUsable', true],
    'current candidates' => ['currentCandidateRowids', [1, 2, 6, 4]],
    'next candidates' => ['nextCandidateRowids', [3, 9]],
    'current matched' => ['currentMatchedRowids', [1, 2, 6, 4]],
    'next matched' => ['nextMatchedRowids', [3, 9]],
    'current ascii matched' => ['currentAsciiEquivalentMatchedRowids', [1, 2, 6, 4]],
    'next ascii matched' => ['nextAsciiEquivalentMatchedRowids', [3, 9]],
    'current ascii candidates' => ['currentAsciiEquivalentCandidateRowids', [1, 2, 6, 4]],
    'next ascii candidates' => ['nextAsciiEquivalentCandidateRowids', [3, 9]],
    'current equivalent' => ['unicodeEscapeNormalizedCurrentEquivalent', true],
    'next equivalent' => ['unicodeEscapeNormalizedNextEquivalent', true],
    'matched exited' => ['matchedExitedRowids', [1, 2, 4, 6]],
    'matched entered' => ['matchedEnteredRowids', [3, 9]],
    'current false positives' => ['currentFalsePositiveRowids', []],
    'next false positives' => ['nextFalsePositiveRowids', []],
    'current excluded' => ['currentExcludedDecodedRowids', [5, 3, 7]],
    'next excluded' => ['nextExcludedDecodedRowids', [5, 1, 2, 6, 4]],
    'current rtrim row two' => ['currentRtrimTexts.2', 'Plugin_Cache'],
    'next rtrim row nine' => ['nextRtrimTexts.9', 'PLUGIN%NEW'],
    'current key two' => ['currentNocaseKeys.2', 'plugin_cache'],
    'next key nine' => ['nextNocaseKeys.9', 'plugin%new'],
    'current matched text four' => ['currentMatchedTexts.4', 'plugin_cache_extra'],
    'next matched text nine' => ['nextMatchedTexts.9', 'PLUGIN%NEW'],
    'current malformed' => ['currentMalformedRowids', [8]],
    'next malformed' => ['nextMalformedRowids', [10]],
    'current error' => ['currentErrors.8', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'next error' => ['nextErrors.10', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor reusable' => ['cursorReusable', false],
    'normalize escape before planning' => ['mustNormalizeEscapeBeforePrefixPlanning', true],
    'must reprepare' => ['mustReprepareForUnicodeEscapeChange', true],
    'rtrim ascii' => ['rtrimTrimsOnlyAsciiSpace', true],
    'nocase ascii' => ['nocaseFoldsAsciiOnly', true],
    'dependency decode' => ['dependencies.0', 'sqlite-utf16-decode'],
    'dependency unicode escape' => ['dependencies.1', 'sqlite-prepared-like-unicode-escape'],
    'dependency range' => ['dependencies.2', 'sqlite-like-nocase-prefix-range'],
    'dependency rtrim' => ['dependencies.3', 'sqlite-rtrim-expression-key'],
    'dependency source' => ['dependencies.4', 'sqlite-current-source-nexttwoOneTwo'],
];

foreach ($cases212 as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim current source nextTwoOneTwo ' . $name] = static function (TestRunner $t) use ($plan212, $valueAt212, $path, $expected): void {
        $t->same($expected, $valueAt212($plan212(), $path));
    };
}

$tests['utf16 nocase like rtrim current source nextTwoOneTwo invalidation reasons include unicode escape'] = static function (TestRunner $t) use ($plan212): void {
    $t->same([
        'source-name',
        'schema-cookie',
        'pattern',
        'like-prefix',
        'like-range',
        'malformed-text',
        'escape-residual-rowset',
        'matched-rowset',
        'unicode-escape-character',
        'decoded-pattern',
    ], $plan212()['invalidationReasons']);
};

$tests['utf16 nocase like rtrim current source nextTwoOneTwo stable unicode escape can reuse cursor'] = static function (TestRunner $t) use ($enc212, $row212, $fullwidthBang212): void {
    $rows = [
        $row212(1, 'plugin_cache', 'UTF-16LE'),
        $row212(2, 'Plugin_Cache  ', 'UTF-16BE'),
        $row212(3, 'plugin%cache', 'UTF-8'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyUnicodeEscapePlan(
        $rows,
        $rows,
        $enc212("plugin{$fullwidthBang212}_%", 'UTF-16LE'),
        'UTF-16LE',
        $enc212("plugin{$fullwidthBang212}_%", 'UTF-16BE'),
        'UTF-16BE',
        $enc212($fullwidthBang212, 'UTF-16LE'),
        'UTF-16LE',
        $enc212($fullwidthBang212, 'UTF-16BE'),
        'UTF-16BE',
        'stable',
        'stable',
        212,
        212,
    );

    $t->same([1, 2], $result['currentMatchedRowids']);
    $t->same([1, 2], $result['nextMatchedRowids']);
    $t->same([1, 2], $result['currentAsciiEquivalentMatchedRowids']);
    $t->same([1, 2], $result['nextAsciiEquivalentMatchedRowids']);
    $t->same(['unicode-escape-character'], $result['invalidationReasons']);
    $t->same(false, $result['cursorReusable']);
};

$tests['utf16 nocase like rtrim current source nextTwoOneTwo unicode escape protects literal percent'] = static function (TestRunner $t) use ($plan212, $row212, $fullwidthBang212): void {
    $rows = [
        $row212(1, 'plugin%cache', 'UTF-16LE'),
        $row212(2, 'plugin_cache', 'UTF-16BE'),
        $row212(3, 'plugin%cache_extra', 'UTF-8'),
    ];
    $result = $plan212($rows, $rows, "plugin{$fullwidthBang212}%%", "plugin{$fullwidthBang212}%%");

    $t->same('plugin%', $result['prefix']);
    $t->same([1, 3], $result['currentCandidateRowids']);
    $t->same([1, 3], $result['currentMatchedRowids']);
    $t->same([1, 3], $result['currentAsciiEquivalentMatchedRowids']);
    $t->same([2], $result['currentExcludedDecodedRowids']);
};

$tests['utf16 nocase like rtrim current source nextTwoOneTwo rejects malformed unicode escape bytes'] = static function (TestRunner $t) use ($currentRows212, $nextRows212, $enc212, $currentPattern212, $nextPattern212, $fullwidthBang212): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyUnicodeEscapePlan(
        $currentRows212,
        $nextRows212,
        $enc212($currentPattern212, 'UTF-16LE'),
        'UTF-16LE',
        $enc212($nextPattern212, 'UTF-16BE'),
        'UTF-16BE',
        "\x00\xd8",
        'UTF-16LE',
        $enc212($fullwidthBang212, 'UTF-16BE'),
        'UTF-16BE',
    ));
};

$tests['utf16 nocase like rtrim current source nextTwoOneTwo rejects two character escape'] = static function (TestRunner $t) use ($currentRows212, $nextRows212, $enc212, $currentPattern212, $nextPattern212, $fullwidthBang212): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyUnicodeEscapePlan(
        $currentRows212,
        $nextRows212,
        $enc212($currentPattern212, 'UTF-16LE'),
        'UTF-16LE',
        $enc212($nextPattern212, 'UTF-16BE'),
        'UTF-16BE',
        $enc212($fullwidthBang212 . 'x', 'UTF-16LE'),
        'UTF-16LE',
        $enc212($fullwidthBang212, 'UTF-16BE'),
        'UTF-16BE',
    ));
};

return $tests;
