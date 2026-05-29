<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$tests = [];

$enc190 = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row190 = static fn (int $id, string $name, int|string $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc190($name, $encoding),
    'text_encoding' => match ($encoding) {
        'UTF-8', 1 => 1,
        'UTF-16LE', 2 => 2,
        'UTF-16BE', 3 => 3,
    },
];
$bad190 = static fn (int $id, string $bytes, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$current190 = [
    $row190(1, 'plugin_cache  ', 'UTF-16LE'),
    $row190(2, "Plugin_Cache\t", 'UTF-16BE'),
    $row190(3, "plugin_cache\u{00a0}", 'UTF-16LE'),
    $row190(4, 'plugin_extra ', 'UTF-8'),
    $row190(5, "plugin_other\n", 'UTF-16BE'),
    $row190(6, 'plugio', 'UTF-16LE'),
    $row190(7, 'theme_plugin  ', 'UTF-16LE'),
    $bad190(8, "\x00\xd8", 2),
];
$next190 = [
    $row190(1, "plugin_cache\t", 'UTF-16BE'),
    $row190(2, 'Plugin_Cache  ', 'UTF-16LE'),
    $row190(3, 'plugin_cache  ', 'UTF-16BE'),
    $row190(4, "plugin_extra\u{00a0}", 'UTF-8'),
    $row190(5, "plugin_other\n", 'UTF-16BE'),
    $row190(9, 'plugin_added  ', 'UTF-16LE'),
    $row190(10, 'plugj', 'UTF-16LE'),
    $bad190(11, "x\0y", 2),
];

$plan190 = static fn (
    ?array $current = null,
    ?array $next = null,
    string $pattern = 'plugin%',
    ?string $escape = null,
    string $currentSource = 'main.wp_options@189',
    string $nextSource = 'main.wp_options@190',
    int $currentCookie = 189,
    int $nextCookie = 190,
): array => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameAsciiSpaceTrimBoundaryPlan(
    $current ?? $current190,
    $next ?? $next190,
    $pattern,
    $escape,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt190 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases190 = [
    'status' => ['status', 'utf16-nocase-like-rtrim-current-source-next190'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(option_name) COLLATE NOCASE LIKE ?'],
    'base status' => ['baseStatus', 'utf16-nocase-like-rtrim-current-source-next183'],
    'pattern' => ['pattern', 'plugin%'],
    'escape' => ['escape', null],
    'prefix' => ['prefix', 'plugin'],
    'range lower' => ['rangeLowerInclusive', 'plugin'],
    'range upper' => ['rangeUpperBound', 'plugio'],
    'uses prefix cursor' => ['usesPrefixRangeCursor', true],
    'current source' => ['currentSource', 'main.wp_options@189'],
    'next source' => ['nextSource', 'main.wp_options@190'],
    'current cookie' => ['currentSchemaCookie', 189],
    'next cookie' => ['nextSchemaCookie', 190],
    'current candidates' => ['currentCandidateRowids', [1, 2, 3, 4, 5]],
    'next candidates' => ['nextCandidateRowids', [9, 2, 3, 1, 4, 5]],
    'current matched' => ['currentMatchedRowids', [1, 2, 3, 4, 5]],
    'next matched' => ['nextMatchedRowids', [9, 2, 3, 1, 4, 5]],
    'current rtrim ascii spaces' => ['currentRtrimTexts.1', 'plugin_cache'],
    'current tab not trimmed' => ['currentRtrimTexts.2', "Plugin_Cache\t"],
    'current nbsp not trimmed' => ['currentRtrimTexts.3', "plugin_cache\u{00a0}"],
    'current newline not trimmed' => ['currentRtrimTexts.5', "plugin_other\n"],
    'next row one tab not trimmed' => ['nextRtrimTexts.1', "plugin_cache\t"],
    'next row three spaces trimmed' => ['nextRtrimTexts.3', 'plugin_cache'],
    'row one current suffix hex' => ['currentTrailingWhitespace.1.suffixHex', '2020'],
    'row one next suffix hex' => ['nextTrailingWhitespace.1.suffixHex', ''],
    'row one next tab suffix' => ['nextTrailingWhitespace.1.hasTabSuffix', true],
    'row two current tab suffix' => ['currentTrailingWhitespace.2.hasTabSuffix', true],
    'row two next ascii spaces' => ['nextTrailingWhitespace.2.asciiSpaceCount', 2],
    'row three current nbsp suffix' => ['currentTrailingWhitespace.3.hasNonBreakingSpaceSuffix', true],
    'row three next trimmed by rtrim' => ['nextTrailingWhitespace.3.trimmedByRtrim', true],
    'row five newline not trimmed' => ['currentTrailingWhitespace.5.hasNewlineSuffix', true],
    'changed whitespace rows' => ['changedTrailingWhitespaceClassRowids', [1, 2, 3, 4]],
    'ascii trim boundary changed rows' => ['asciiSpaceTrimBoundaryChangedRowids', [1, 2, 3, 4]],
    'retained range changed rows' => ['retainedRangeRtrimKeyChangedRowids', [1, 2, 3, 4]],
    'retained match changed rows' => ['retainedMatchRtrimKeyChangedRowids', [1, 2, 3, 4]],
    'range retained' => ['rangeRetainedRowids', [1, 2, 3, 4, 5]],
    'range exited' => ['rangeExitedRowids', []],
    'range entered' => ['rangeEnteredRowids', [9]],
    'matched retained' => ['matchedRetainedRowids', [1, 2, 3, 4, 5]],
    'matched exited' => ['matchedExitedRowids', []],
    'matched entered' => ['matchedEnteredRowids', [9]],
    'current excluded decoded' => ['currentExcludedDecodedRowids', [6, 7]],
    'next excluded decoded' => ['nextExcludedDecodedRowids', [10]],
    'current malformed' => ['currentMalformedRowids', [8]],
    'next malformed' => ['nextMalformedRowids', [11]],
    'current malformed error' => ['currentErrors.8', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'next malformed error' => ['nextErrors.11', 'SQLite encoding source UTF-16 text payload has an odd byte length'],
    'candidate changed' => ['candidateRowsetChanged', true],
    'matched changed' => ['matchedRowsetChanged', true],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor not reusable' => ['cursorReusable', false],
    'stale range risk' => ['staleRangeCursorRisk', true],
    'rtrim ascii only' => ['rtrimTrimsOnlyAsciiSpace', true],
    'nbsp not trimmed' => ['nonBreakingSpaceNotTrimmed', true],
    'tab not trimmed' => ['tabNotTrimmed', true],
    'nocase ascii only' => ['nocaseFoldsAsciiOnly', true],
    'dependency decode' => ['dependencies.0', 'sqlite-utf16-decode'],
    'dependency prefix range' => ['dependencies.1', 'sqlite-like-nocase-prefix-range'],
    'dependency rtrim boundary' => ['dependencies.2', 'sqlite-rtrim-ascii-space-boundary'],
    'dependency current source' => ['dependencies.3', 'sqlite-current-source-next190'],
];

foreach ($cases190 as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim current source next190 ' . $name] = static function (TestRunner $t) use ($plan190, $valueAt190, $path, $expected): void {
        $t->same($expected, $valueAt190($plan190(), $path));
    };
}

$tests['utf16 nocase like rtrim current source next190 invalidation reasons include retained prefix rtrim key'] = static function (TestRunner $t) use ($plan190): void {
    $t->same([
        'source-name',
        'schema-cookie',
        'decoded-text',
        'rtrim-expression',
        'nocase-key',
        'encoded-bytes',
        'malformed-text',
        'matched-rowset',
        'trailing-whitespace-class',
        'retained-prefix-rtrim-key',
    ], $plan190()['invalidationReasons']);
};

$tests['utf16 nocase like rtrim current source next190 stable source reusable when whitespace class unchanged'] = static function (TestRunner $t) use ($row190): void {
    $rows = [
        $row190(1, 'plugin_cache  ', 'UTF-16LE'),
        $row190(2, "Plugin_Cache\t", 'UTF-16BE'),
        $row190(3, "plugin_cache\u{00a0}", 'UTF-8'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameAsciiSpaceTrimBoundaryPlan(
        $rows,
        $rows,
        'plugin%',
        null,
        'stable',
        'stable',
        190,
        190,
    );
    $t->same([1, 2, 3], $result['currentMatchedRowids']);
    $t->same([], $result['changedTrailingWhitespaceClassRowids']);
    $t->same([], $result['asciiSpaceTrimBoundaryChangedRowids']);
    $t->same([], $result['invalidationReasons']);
    $t->same(true, $result['cursorReusable']);
};

$tests['utf16 nocase like rtrim current source next190 non-breaking space remains a distinct key'] = static function (TestRunner $t) use ($row190): void {
    $current = [
        $row190(1, "plugin_cache\u{00a0}", 'UTF-16LE'),
    ];
    $next = [
        $row190(1, 'plugin_cache  ', 'UTF-16LE'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameAsciiSpaceTrimBoundaryPlan(
        $current,
        $next,
        'plugin_cache%',
        null,
        'stable',
        'stable',
        190,
        190,
    );
    $t->same("plugin_cache\u{00a0}", $result['currentRtrimTexts'][1]);
    $t->same('plugin_cache', $result['nextRtrimTexts'][1]);
    $t->same([1], $result['changedTrailingWhitespaceClassRowids']);
    $t->same([1], $result['retainedRangeRtrimKeyChangedRowids']);
    $t->same(false, $result['cursorReusable']);
};

$tests['utf16 nocase like rtrim current source next190 invalid escape length rejected by base planner'] = static function (TestRunner $t) use ($current190, $next190): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameAsciiSpaceTrimBoundaryPlan($current190, $next190, 'plugin!!', '!!'));
};

return $tests;
