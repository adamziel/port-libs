<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$tests = [];

$enc183 = static fn (string $text, int $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row183 = static fn (int $id, string $name, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc183($name, $encoding),
    'text_encoding' => $encoding,
];
$bad183 = static fn (int $id, string $bytes, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$current183 = [
    $row183(1, 'Plugin_Cache   ', 2),
    $row183(2, 'plugin_cache', 3),
    $row183(3, 'plugin_cache' . "\t", 2),
    $row183(4, 'plugin_cache' . "\xc2\xa0", 3),
    $row183(5, 'plugin_caches', 2),
    $row183(6, 'plugin_cache_old', 3),
    $row183(7, 'theme_cache', 2),
    $row183(8, 'plúgin_cache', 3),
    $bad183(9, "\x00\xd8", 2),
];
$nextOneEightThree = [
    $row183(1, 'plugin_cache', 3),
    $row183(2, 'PLUGIN_CACHE  ', 2),
    $row183(3, 'plugin_cache  ', 3),
    $row183(4, 'plugin_cache' . "\xc2\xa0", 3),
    $row183(5, 'plugin_caches', 2),
    $row183(10, 'Plugin_Cache   ', 2),
    $row183(11, 'plugin_cache_new', 3),
    $row183(12, 'other_cache', 2),
    $bad183(13, "x\0y", 2),
];

$plan183 = static fn (
    ?array $current = null,
    ?array $next = null,
    string $pattern = 'plugin!_cache',
    ?string $escape = '!',
    string $currentSource = 'main.wp_options@182',
    string $nextSource = 'main.wp_options@183',
    int $currentCookie = 182,
    int $nextCookie = 183,
): array => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyAsciiPrefixRangePlan(
    $current ?? $current183,
    $next ?? $nextOneEightThree,
    $pattern,
    $escape,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt183 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases183 = [
    'status' => ['status', 'utf16-nocase-like-rtrim-current-source-nextoneEightThree'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ?'],
    'pattern' => ['pattern', 'plugin!_cache'],
    'escape' => ['escape', '!'],
    'collation' => ['collation', 'NOCASE'],
    'case sensitive false' => ['caseSensitiveLike', false],
    'ascii nocase only' => ['asciiNocaseOnly', true],
    'rtrim ascii spaces only' => ['rtrimTrimsOnlyAsciiSpace', true],
    'current source' => ['currentSource', 'main.wp_options@182'],
    'next source' => ['nextSource', 'main.wp_options@183'],
    'current cookie' => ['currentSchemaCookie', 182],
    'next cookie' => ['nextSchemaCookie', 183],
    'prefix' => ['prefix', 'plugin_cache'],
    'prefix ascii' => ['prefixIsAscii', true],
    'index usable' => ['indexUsable', true],
    'prefix cursor used' => ['usesPrefixRangeCursor', true],
    'no full scan fallback' => ['usesFullScanFallback', false],
    'no rejected reason' => ['rejectedReason', null],
    'range lower' => ['rangeLowerInclusive', 'plugin_cache'],
    'range upper' => ['rangeUpperBound', 'plugin_cachf'],
    'current decoded order' => ['currentDecodedRowids', [1, 2, 3, 6, 5, 4, 8, 7]],
    'next decoded order' => ['nextDecodedRowids', [12, 1, 2, 3, 10, 11, 5, 4]],
    'current candidates' => ['currentCandidateRowids', [1, 2, 3, 6, 5, 4]],
    'next candidates' => ['nextCandidateRowids', [1, 2, 3, 10, 11, 5, 4]],
    'current excluded decoded rowids' => ['currentExcludedDecodedRowids', [8, 7]],
    'next excluded decoded rowids' => ['nextExcludedDecodedRowids', [12]],
    'current matched' => ['currentMatchedRowids', [1, 2]],
    'next matched' => ['nextMatchedRowids', [1, 2, 3, 10]],
    'matched retained' => ['matchedRetainedRowids', [1, 2]],
    'matched entered' => ['matchedEnteredRowids', [3, 10]],
    'matched exited' => ['matchedExitedRowids', []],
    'current false positives' => ['currentRangeFalsePositiveRowids', [3, 6, 5, 4]],
    'next false positives' => ['nextRangeFalsePositiveRowids', [11, 5, 4]],
    'range retained rowids' => ['currentRangeRetainedRowids', [1, 2, 3, 5, 4]],
    'range exited rowids' => ['currentRangeExitedRowids', [6]],
    'range entered rowids' => ['nextRangeEnteredRowids', [10, 11]],
    'current non ascii range rowids' => ['currentNonAsciiPrefixRowids', [4]],
    'next non ascii range rowids' => ['nextNonAsciiPrefixRowids', [4]],
    'current ascii folded rowids' => ['currentAsciiFoldedRowids', [1]],
    'next ascii folded rowids' => ['nextAsciiFoldedRowids', [2, 10]],
    'current malformed rowids' => ['currentMalformedRowids', [9]],
    'next malformed rowids' => ['nextMalformedRowids', [13]],
    'current malformed error' => ['currentErrors.9', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'next malformed error' => ['nextErrors.13', 'SQLite encoding source UTF-16 text payload has an odd byte length'],
    'row one current rtrim' => ['currentRtrimTexts.1', 'Plugin_Cache'],
    'row two next rtrim' => ['nextRtrimTexts.2', 'PLUGIN_CACHE'],
    'row three current tab remains' => ['currentRtrimTexts.3', 'plugin_cache' . "\t"],
    'row three next spaces trim' => ['nextRtrimTexts.3', 'plugin_cache'],
    'row four nbsp remains' => ['currentRtrimTexts.4', 'plugin_cache' . "\xc2\xa0"],
    'row one current nocase key' => ['currentNocaseKeys.1', 'plugin_cache'],
    'row two next nocase key' => ['nextNocaseKeys.2', 'plugin_cache'],
    'row eight non ascii key unchanged' => ['currentNocaseKeys.8', 'plúgin_cache'],
    'changed text rowids' => ['changedTextRowids', [1, 2, 3]],
    'changed rtrim rowids' => ['changedRtrimRowids', [1, 2, 3]],
    'changed nocase rowids' => ['changedNocaseKeyRowids', [3]],
    'changed bytes rowids' => ['changedBytesRowids', [1, 2, 3]],
    'rtrim residual changed rowids' => ['rtrimResidualChangedRowids', [1, 2, 3, 10]],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor reusable false' => ['cursorReusable', false],
    'stale range risk' => ['staleRangeCursorRisk', true],
    'current matched text one' => ['currentMatchedTexts.1', 'Plugin_Cache'],
    'next matched text ten' => ['nextMatchedTexts.10', 'Plugin_Cache'],
    'dependency decode' => ['dependencies.0', 'sqlite-utf16-decode'],
    'dependency range' => ['dependencies.1', 'sqlite-like-nocase-prefix-range'],
    'dependency rtrim residual' => ['dependencies.2', 'sqlite-rtrim-residual-match'],
    'dependency current source' => ['dependencies.3', 'sqlite-current-source-nextoneEightThree'],
];

foreach ($cases183 as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim current source nextOneEightThree ' . $name] = static function (TestRunner $t) use ($plan183, $valueAt183, $path, $expected): void {
        $t->same($expected, $valueAt183($plan183(), $path));
    };
}

$tests['utf16 nocase like rtrim current source nextOneEightThree invalidation reason order'] = static function (TestRunner $t) use ($plan183): void {
    $t->same([
        'source-name',
        'schema-cookie',
        'decoded-text',
        'rtrim-expression',
        'nocase-key',
        'encoded-bytes',
        'malformed-text',
        'matched-rowset',
    ], $plan183()['invalidationReasons']);
};

$tests['utf16 nocase like rtrim current source nextOneEightThree stable cursor remains reusable'] = static function (TestRunner $t) use ($row183): void {
    $rows = [
        $row183(1, 'Plugin_Cache   ', 2),
        $row183(2, 'plugin_cache', 3),
        $row183(3, 'plugin_cache' . "\t", 2),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyAsciiPrefixRangePlan(
        $rows,
        $rows,
        'plugin!_cache',
        '!',
        'stable',
        'stable',
        44,
        44,
    );
    $t->same([1, 2, 3], $result['currentCandidateRowids']);
    $t->same([1, 2], $result['currentMatchedRowids']);
    $t->same([3], $result['currentRangeFalsePositiveRowids']);
    $t->same([], $result['invalidationReasons']);
    $t->same(true, $result['cursorReusable']);
    $t->same(false, $result['staleRangeCursorRisk']);
};

$tests['utf16 nocase like rtrim current source nextOneEightThree wildcard prefix keeps residual false positives'] = static function (TestRunner $t) use ($row183): void {
    $rows = [
        $row183(1, 'plugin_cache', 2),
        $row183(2, 'plugin_caches', 2),
        $row183(3, 'plugin_cache_new', 3),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyAsciiPrefixRangePlan(
        $rows,
        $rows,
        'plugin!_cache%',
        '!',
        'stable',
        'stable',
        45,
        45,
    );
    $t->same(true, $result['usesPrefixRangeCursor']);
    $t->same([1, 3, 2], $result['currentCandidateRowids']);
    $t->same([1, 3, 2], $result['currentMatchedRowids']);
    $t->same([], $result['currentRangeFalsePositiveRowids']);
};

$tests['utf16 nocase like rtrim current source nextOneEightThree no prefix rejects range'] = static function (TestRunner $t) use ($row183): void {
    $rows = [$row183(1, 'plugin_cache', 2)];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyAsciiPrefixRangePlan(
        $rows,
        $rows,
        '%cache',
        null,
        'stable',
        'stable',
        46,
        46,
    );
    $t->same(false, $result['usesPrefixRangeCursor']);
    $t->same('no_fixed_prefix', $result['likePlan']['rejectedReason']);
    $t->same([], $result['currentCandidateRowids']);
    $t->same([], $result['currentMatchedRowids']);
};

$tests['utf16 nocase like rtrim current source nextOneEightThree rejects missing bytes'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyAsciiPrefixRangePlan(
        [['option_id' => 1, 'text_encoding' => 2]],
        [],
    ));
};

return $tests;
