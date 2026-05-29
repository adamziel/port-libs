<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$tests = [];

$enc227 = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row227 = static fn (int $id, string $name, int|string $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc227($name, $encoding),
    'text_encoding' => match ($encoding) {
        'UTF-8', 1 => 1,
        'UTF-16LE', 2 => 2,
        'UTF-16BE', 3 => 3,
    },
];
$bad227 = static fn (int $id, string $bytes, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$nbsp227 = "\xc2\xa0";
$current227 = [
    $row227(1, 'plugin_cache', 'UTF-16LE'),
    $row227(2, 'Plugin_Cache  ', 'UTF-16BE'),
    $row227(3, 'plugin_cache' . $nbsp227, 'UTF-16LE'),
    $row227(4, "plugin_cache\t", 'UTF-16BE'),
    $row227(5, 'PLUGIN_CACHE ', 'UTF-8'),
    $row227(6, 'plugin_cachex', 'UTF-16LE'),
    $row227(7, 'theme_cache ', 'UTF-16BE'),
    $bad227(8, "\x00\xd8", 2),
];
$nextTwoTwoSeven = [
    $row227(1, 'plugin_cache ', 'UTF-16BE'),
    $row227(2, 'Plugin_Cache' . $nbsp227, 'UTF-16LE'),
    $row227(3, 'plugin_cache  ', 'UTF-16BE'),
    $row227(4, "plugin_cache\t", 'UTF-16LE'),
    $row227(5, 'PLUGIN_CACHE ', 'UTF-8'),
    $row227(6, 'plugin_cachex', 'UTF-16BE'),
    $row227(9, 'plugin_cache', 'UTF-16LE'),
    $bad227(10, "\x00\xd8", 2),
];

$plan227 = static fn (
    ?array $current = null,
    ?array $next = null,
    string $pattern = 'plugin_cache',
    ?string $escape = null,
    string $currentSource = 'main.wp_options@226',
    string $nextSource = 'main.wp_options@227',
    int $currentCookie = 226,
    int $nextCookie = 227,
): array => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameAsciiSpaceBoundaryPlan(
    $current ?? $current227,
    $next ?? $nextTwoTwoSeven,
    $pattern,
    $escape,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt227 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases227 = [
    'status' => ['status', 'utf16-nocase-like-rtrim-current-source-nexttwoTwoSeven'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(option_name) COLLATE NOCASE LIKE ? /* ASCII-space RTRIM boundary */'],
    'pattern' => ['pattern', 'plugin_cache'],
    'escape' => ['escape', null],
    'collation' => ['collation', 'NOCASE'],
    'current source' => ['currentSource', 'main.wp_options@226'],
    'next source' => ['nextSource', 'main.wp_options@227'],
    'current cookie' => ['currentSchemaCookie', 226],
    'next cookie' => ['nextSchemaCookie', 227],
    'prefix' => ['prefix', 'plugin'],
    'range lower' => ['rangeLowerInclusive', 'plugin'],
    'range upper' => ['rangeUpperBound', 'plugio'],
    'index usable' => ['indexUsable', true],
    'equality prefix' => ['usesEqualityPrefixRange', true],
    'current candidates' => ['currentCandidateRowids', [1, 2, 5, 4, 6, 3]],
    'next candidates' => ['nextCandidateRowids', [1, 3, 5, 9, 4, 6, 2]],
    'current matched' => ['currentMatchedRowids', [1, 2, 5]],
    'next matched' => ['nextMatchedRowids', [1, 3, 5, 9]],
    'matched retained' => ['matchedRetainedRowids', [1, 5]],
    'matched exited' => ['matchedExitedRowids', [2]],
    'matched entered' => ['matchedEnteredRowids', [3, 9]],
    'current false positives' => ['currentFalsePositiveRowids', [4, 6, 3]],
    'next false positives' => ['nextFalsePositiveRowids', [4, 6, 2]],
    'current ascii spaces' => ['currentAsciiSpaceSuffixRowids', [2, 5, 7]],
    'next ascii spaces' => ['nextAsciiSpaceSuffixRowids', [1, 3, 5]],
    'current nbsp' => ['currentNbspSuffixRowids', [3]],
    'next nbsp' => ['nextNbspSuffixRowids', [2]],
    'current tabs' => ['currentTabSuffixRowids', [4]],
    'next tabs' => ['nextTabSuffixRowids', [4]],
    'current malformed' => ['currentMalformedRowids', [8]],
    'next malformed' => ['nextMalformedRowids', [10]],
    'current error' => ['currentErrors.8', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'next error' => ['nextErrors.10', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'current row two rtrim' => ['currentRtrimTexts.2', 'Plugin_Cache'],
    'current row three rtrim' => ['currentRtrimTexts.3', 'plugin_cache' . $nbsp227],
    'next row three rtrim' => ['nextRtrimTexts.3', 'plugin_cache'],
    'current row two key' => ['currentNocaseKeys.2', 'plugin_cache'],
    'next row two key preserves nbsp' => ['nextNocaseKeys.2', 'plugin_cache' . $nbsp227],
    'current row three suffix' => ['currentSuffixClasses.3', 'nbsp'],
    'next row three suffix' => ['nextSuffixClasses.3', 'ascii-space'],
    'current tab residual' => ['currentResidualMatches.4', false],
    'next nbsp residual' => ['nextResidualMatches.2', false],
    'changed text' => ['changedTextRowids', [1, 2, 3, 7, 9]],
    'changed rtrim' => ['changedRtrimRowids', [2, 3, 7, 9]],
    'changed nocase' => ['changedNocaseKeyRowids', [2, 3, 7, 9]],
    'changed suffix' => ['changedSuffixClassRowids', [1, 2, 3, 7, 9]],
    'invalidated' => ['cursorInvalidated', true],
    'not reusable' => ['cursorReusable', false],
    'ascii space flag' => ['asciiSpaceSuffixMatchesAfterRtrim', true],
    'nbsp flag' => ['nonAsciiSpaceSuffixDoesNotRtrim', true],
    'tab flag' => ['tabSuffixDoesNotRtrim', true],
    'nocase ascii' => ['nocaseFoldsAsciiOnly', true],
    'rtrim ascii' => ['rtrimTrimsOnlyAsciiSpace', true],
    'dependency decode' => ['dependencies.0', 'sqlite-utf16-decode'],
    'dependency prefix range' => ['dependencies.1', 'sqlite-like-nocase-prefix-range'],
    'dependency rtrim' => ['dependencies.2', 'sqlite-rtrim-expression-key'],
    'dependency source' => ['dependencies.3', 'sqlite-current-source-nexttwoTwoSeven'],
];

foreach ($cases227 as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim current source nextTwoTwoSeven ' . $name] = static function (TestRunner $t) use ($plan227, $valueAt227, $path, $expected): void {
        $t->same($expected, $valueAt227($plan227(), $path));
    };
}

$tests['utf16 nocase like rtrim current source nextTwoTwoSeven invalidation reason order'] = static function (TestRunner $t) use ($plan227): void {
    $t->same([
        'source-name',
        'schema-cookie',
        'decoded-text',
        'rtrim-expression',
        'nocase-key',
        'suffix-class',
        'malformed-text',
        'candidate-rowset',
        'matched-rowset',
        'non-ascii-space-rtrim-boundary',
    ], $plan227()['invalidationReasons']);
};

$tests['utf16 nocase like rtrim current source nextTwoTwoSeven stable ascii space cursor is reusable'] = static function (TestRunner $t) use ($row227): void {
    $rows = [
        $row227(1, 'plugin_cache', 'UTF-16LE'),
        $row227(2, 'PLUGIN_CACHE ', 'UTF-16BE'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameAsciiSpaceBoundaryPlan(
        $rows,
        $rows,
        'plugin_cache',
        null,
        'stable',
        'stable',
        227,
        227,
    );

    $t->same([1, 2], $result['currentMatchedRowids']);
    $t->same([], $result['invalidationReasons']);
    $t->same(true, $result['cursorReusable']);
};

$tests['utf16 nocase like rtrim current source nextTwoTwoSeven nbsp alone prevents equality match'] = static function (TestRunner $t) use ($row227, $nbsp227): void {
    $rows = [
        $row227(1, 'plugin_cache' . $nbsp227, 'UTF-16LE'),
        $row227(2, 'plugin_cache ', 'UTF-16BE'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameAsciiSpaceBoundaryPlan($rows, $rows);

    $t->same([2], $result['currentMatchedRowids']);
    $t->same([1], $result['currentFalsePositiveRowids']);
    $t->same([1], $result['currentNbspSuffixRowids']);
};

$tests['utf16 nocase like rtrim current source nextTwoTwoSeven rejects malformed row shape'] = static function (TestRunner $t) use ($enc227): void {
    $rows = [['option_id' => 1, 'option_name_bytes' => $enc227('plugin_cache', 'UTF-16LE')]];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameAsciiSpaceBoundaryPlan($rows, $rows));
};

return $tests;
