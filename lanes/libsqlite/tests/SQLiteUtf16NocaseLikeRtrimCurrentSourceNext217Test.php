<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$tests = [];

$enc217 = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row217 = static fn (int $id, string $name, int|string $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc217($name, $encoding),
    'text_encoding' => match ($encoding) {
        'UTF-8', 1 => 1,
        'UTF-16LE', 2 => 2,
        'UTF-16BE', 3 => 3,
    },
];
$bad217 = static fn (int $id, string $bytes, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$currentRows217 = [
    $row217(1, 'plugin_cache', 'UTF-16LE'),
    $row217(2, 'Plugin_Cache  ', 'UTF-16BE'),
    $row217(3, 'plugin_cache alpha', 'UTF-16LE'),
    $row217(4, 'PLUGIN_CACHE alpha  ', 'UTF-16BE'),
    $row217(5, 'plugin_cachealpha', 'UTF-8'),
    $row217(6, 'plugin-cache alpha', 'UTF-16LE'),
    $row217(7, 'theme_plugin_cache alpha', 'UTF-16BE'),
    $row217(8, 'plugin_cache  beta', 'UTF-16LE'),
    $bad217(9, "\x00\xd8", 2),
];
$nextRows217 = [
    $row217(1, 'plugin_cache', 'UTF-16BE'),
    $row217(2, 'Plugin_Cache', 'UTF-16LE'),
    $row217(3, 'plugin_cache alpha', 'UTF-16BE'),
    $row217(4, 'PLUGIN_CACHE alpha', 'UTF-16LE'),
    $row217(5, 'plugin_cachealpha', 'UTF-8'),
    $row217(8, 'plugin_cache  beta', 'UTF-16LE'),
    $row217(10, 'plugin_cache later', 'UTF-16LE'),
    $row217(11, 'plugin-cache later', 'UTF-16BE'),
    $bad217(12, "\x00\xd8", 2),
];

$plan217 = static fn (
    ?array $current = null,
    ?array $next = null,
    string $currentPattern = 'plugin!_cache %',
    string $nextPattern = 'plugin!_cache%',
    int|string $currentPatternEncoding = 'UTF-16LE',
    int|string $nextPatternEncoding = 'UTF-16BE',
    ?string $escape = '!',
    string $currentSource = 'main.wp_options@216',
    string $nextSource = 'main.wp_options@217',
    int $currentCookie = 216,
    int $nextCookie = 217,
): array => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNamePreparedPatternSpacePlan(
    $current ?? $currentRows217,
    $next ?? $nextRows217,
    $enc217($currentPattern, $currentPatternEncoding),
    $currentPatternEncoding,
    $enc217($nextPattern, $nextPatternEncoding),
    $nextPatternEncoding,
    $escape,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt217 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases217 = [
    'status' => ['status', 'utf16-nocase-like-rtrim-current-source-next217'],
    'base status' => ['baseStatus', 'utf16-nocase-like-rtrim-current-source-next200'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* prepared UTF-16 pattern space */'],
    'current pattern' => ['currentPattern', 'plugin!_cache %'],
    'next pattern' => ['nextPattern', 'plugin!_cache%'],
    'current encoding' => ['currentPatternEncoding', 'UTF-16LE'],
    'next encoding' => ['nextPatternEncoding', 'UTF-16BE'],
    'current bytes' => ['currentPatternBytesHex', bin2hex($enc217('plugin!_cache %', 'UTF-16LE'))],
    'next bytes' => ['nextPatternBytesHex', bin2hex($enc217('plugin!_cache%', 'UTF-16BE'))],
    'escape' => ['escape', '!'],
    'current source' => ['currentSource', 'main.wp_options@216'],
    'next source' => ['nextSource', 'main.wp_options@217'],
    'current cookie' => ['currentSchemaCookie', 216],
    'next cookie' => ['nextSchemaCookie', 217],
    'current space count' => ['currentSpaceBeforeWildcardCount', 1],
    'next space count' => ['nextSpaceBeforeWildcardCount', 0],
    'current space offset' => ['currentSpaceBeforeWildcardOffset', 13],
    'next space offset' => ['nextSpaceBeforeWildcardOffset', null],
    'current stripped pattern' => ['currentPatternWithoutSpaceBeforeWildcard', 'plugin!_cache%'],
    'next stripped pattern' => ['nextPatternWithoutSpaceBeforeWildcard', 'plugin!_cache%'],
    'current prefix' => ['currentPrefix', 'plugin_cache '],
    'next prefix' => ['nextPrefix', 'plugin_cache'],
    'current lower' => ['currentRangeLowerInclusive', 'plugin_cache '],
    'current upper' => ['currentRangeUpperBound', 'plugin_cache!'],
    'next lower' => ['nextRangeLowerInclusive', 'plugin_cache'],
    'next upper' => ['nextRangeUpperBound', 'plugin_cachf'],
    'current index usable' => ['currentIndexUsable', true],
    'next index usable' => ['nextIndexUsable', true],
    'current candidates' => ['currentCandidateRowids', [8, 3, 4]],
    'next candidates' => ['nextCandidateRowids', [1, 2, 8, 3, 4, 10, 5]],
    'current matched' => ['currentMatchedRowids', [8, 3, 4]],
    'next matched' => ['nextMatchedRowids', [1, 2, 8, 3, 4, 10, 5]],
    'current without-space matched' => ['currentMatchedWithoutPatternSpaceRowids', [1, 2, 8, 3, 4, 5]],
    'next without-space matched' => ['nextMatchedWithoutPatternSpaceRowids', [1, 2, 8, 3, 4, 10, 5]],
    'current space filtered' => ['currentPatternSpaceFilteredRowids', [1, 2, 5]],
    'next space filtered' => ['nextPatternSpaceFilteredRowids', []],
    'matched exited' => ['matchedExitedRowids', []],
    'matched entered' => ['matchedEnteredRowids', [1, 2, 5, 10]],
    'current false positives' => ['currentFalsePositiveRowids', []],
    'next false positives' => ['nextFalsePositiveRowids', []],
    'current excluded' => ['currentExcludedDecodedRowids', [6, 1, 2, 5, 7]],
    'next excluded' => ['nextExcludedDecodedRowids', [11]],
    'current rtrim two' => ['currentRtrimTexts.2', 'Plugin_Cache'],
    'current rtrim four' => ['currentRtrimTexts.4', 'PLUGIN_CACHE alpha'],
    'next rtrim ten' => ['nextRtrimTexts.10', 'plugin_cache later'],
    'current key four' => ['currentNocaseKeys.4', 'plugin_cache alpha'],
    'next key ten' => ['nextNocaseKeys.10', 'plugin_cache later'],
    'current matched text eight' => ['currentMatchedTexts.8', 'plugin_cache  beta'],
    'next matched text ten' => ['nextMatchedTexts.10', 'plugin_cache later'],
    'current rows trimmed' => ['currentRowsWithTrimmedAsciiSpace', [2, 4]],
    'next rows trimmed' => ['nextRowsWithTrimmedAsciiSpace', []],
    'current malformed' => ['currentMalformedRowids', [9]],
    'next malformed' => ['nextMalformedRowids', [12]],
    'current error' => ['currentErrors.9', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'next error' => ['nextErrors.12', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor reusable' => ['cursorReusable', false],
    'decode before prefix' => ['mustDecodePatternBeforePrefixPlanning', true],
    'pattern spaces significant' => ['preparedPatternSpacesRemainSignificant', true],
    'left rtrim only' => ['leftRtrimDoesNotTrimPattern', true],
    'rtrim ascii' => ['rtrimTrimsOnlyAsciiSpace', true],
    'nocase ascii' => ['nocaseFoldsAsciiOnly', true],
    'dependency decode' => ['dependencies.0', 'sqlite-utf16-decode'],
    'dependency prepared pattern' => ['dependencies.1', 'sqlite-prepared-like-pattern-decode'],
    'dependency range' => ['dependencies.2', 'sqlite-like-nocase-prefix-range'],
    'dependency rtrim' => ['dependencies.3', 'sqlite-rtrim-expression-key'],
    'dependency source' => ['dependencies.4', 'sqlite-current-source-next217'],
];

foreach ($cases217 as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim current source next217 ' . $name] = static function (TestRunner $t) use ($plan217, $valueAt217, $path, $expected): void {
        $t->same($expected, $valueAt217($plan217(), $path));
    };
}

$tests['utf16 nocase like rtrim current source next217 invalidation reasons include pattern space'] = static function (TestRunner $t) use ($plan217): void {
    $t->same([
        'source-name',
        'schema-cookie',
        'pattern',
        'like-prefix',
        'like-range',
        'malformed-text',
        'escape-residual-rowset',
        'matched-rowset',
        'prepared-pattern-space-count',
        'prepared-pattern-space-rowset',
    ], $plan217()['invalidationReasons']);
};

$tests['utf16 nocase like rtrim current source next217 stable pattern space can reuse cursor'] = static function (TestRunner $t) use ($enc217, $row217): void {
    $rows = [
        $row217(1, 'plugin_cache alpha', 'UTF-16LE'),
        $row217(2, 'Plugin_Cache alpha  ', 'UTF-16BE'),
        $row217(3, 'plugin_cache', 'UTF-8'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNamePreparedPatternSpacePlan(
        $rows,
        $rows,
        $enc217('plugin!_cache %', 'UTF-16LE'),
        'UTF-16LE',
        $enc217('plugin!_cache %', 'UTF-16BE'),
        'UTF-16BE',
        '!',
        'stable',
        'stable',
        217,
        217,
    );

    $t->same([1, 2], $result['currentMatchedRowids']);
    $t->same([1, 2], $result['nextMatchedRowids']);
    $t->same([3], $result['currentPatternSpaceFilteredRowids']);
    $t->same([3], $result['nextPatternSpaceFilteredRowids']);
    $t->same([], $result['invalidationReasons']);
    $t->same(true, $result['cursorReusable']);
};

$tests['utf16 nocase like rtrim current source next217 escaped wildcard before pattern space is literal'] = static function (TestRunner $t) use ($plan217, $row217): void {
    $rows = [
        $row217(1, 'plugin_cache alpha', 'UTF-16LE'),
        $row217(2, 'pluginXcache alpha', 'UTF-16BE'),
        $row217(3, 'plugin_cache', 'UTF-8'),
    ];
    $result = $plan217($rows, $rows, 'plugin!_cache %', 'plugin!_cache %', 'UTF-16LE', 'UTF-16BE', '!', 'stable', 'stable', 217, 217);

    $t->same('plugin_cache ', $result['currentPrefix']);
    $t->same([1], $result['currentMatchedRowids']);
    $t->same([1], $result['nextMatchedRowids']);
    $t->same([3], $result['currentPatternSpaceFilteredRowids']);
    $t->same([3, 2], $result['currentExcludedDecodedRowids']);
};

$tests['utf16 nocase like rtrim current source next217 rejects malformed prepared pattern bytes'] = static function (TestRunner $t) use ($currentRows217, $nextRows217, $enc217): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNamePreparedPatternSpacePlan(
        $currentRows217,
        $nextRows217,
        "\x00\xd8",
        'UTF-16LE',
        $enc217('plugin!_cache%', 'UTF-16BE'),
        'UTF-16BE',
    ));
};

$tests['utf16 nocase like rtrim current source next217 rejects invalid encoding label'] = static function (TestRunner $t) use ($currentRows217, $nextRows217, $enc217): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNamePreparedPatternSpacePlan(
        $currentRows217,
        $nextRows217,
        $enc217('plugin!_cache %', 'UTF-16LE'),
        'UTF-32',
        $enc217('plugin!_cache%', 'UTF-16BE'),
        'UTF-16BE',
    ));
};

return $tests;
