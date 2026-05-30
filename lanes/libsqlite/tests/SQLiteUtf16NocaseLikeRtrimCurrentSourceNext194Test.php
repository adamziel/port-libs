<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$tests = [];

$enc194 = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row194 = static fn (int $id, string $name, int|string $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc194($name, $encoding),
    'text_encoding' => match ($encoding) {
        'UTF-8', 1 => 1,
        'UTF-16LE', 2 => 2,
        'UTF-16BE', 3 => 3,
    },
];
$bad194 = static fn (int $id, string $bytes, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$current194 = [
    $row194(1, 'plugin%cache', 'UTF-16LE'),
    $row194(2, 'Plugin%Cache  ', 'UTF-16BE'),
    $row194(3, 'plugin%cache_extra', 'UTF-8'),
    $row194(4, 'plugin&cache', 'UTF-16LE'),
    $row194(5, 'plugin_cache', 'UTF-16BE'),
    $row194(6, "plugin%cache\t", 'UTF-16LE'),
    $row194(7, 'theme%cache', 'UTF-16BE'),
    $bad194(8, "\x00\xd8", 2),
];
$nextOneNineFour = [
    $row194(1, 'PLUGIN%CACHE ', 'UTF-16BE'),
    $row194(2, 'Plugin%Cache', 'UTF-16LE'),
    $row194(3, 'plugin%cache_extra', 'UTF-8'),
    $row194(6, "plugin%cache\t", 'UTF-16LE'),
    $row194(9, 'plugin%new', 'UTF-16BE'),
    $row194(10, 'plugin&cache', 'UTF-16LE'),
    $row194(11, 'plugin_cache', 'UTF-16BE'),
    $bad194(12, "x\0y", 2),
];

$plan194 = static fn (
    ?array $current = null,
    ?array $next = null,
    string $pattern = 'plugin!%%',
    ?string $escape = '!',
    string $currentSource = 'main.wp_options@193',
    string $nextSource = 'main.wp_options@194',
    int $currentCookie = 193,
    int $nextCookie = 194,
): array => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyEscapedWildcardPrefixPlan(
    $current ?? $current194,
    $next ?? $nextOneNineFour,
    $pattern,
    $escape,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt194 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases194 = [
    'status' => ['status', 'utf16-nocase-like-rtrim-current-source-nextoneNineFour'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* escaped wildcard literal prefix */'],
    'pattern' => ['pattern', 'plugin!%%'],
    'escape' => ['escape', '!'],
    'collation' => ['collation', 'NOCASE'],
    'base status' => ['baseStatus', 'utf16-nocase-like-rtrim-current-source-nextoneEightThree'],
    'current source' => ['currentSource', 'main.wp_options@193'],
    'next source' => ['nextSource', 'main.wp_options@194'],
    'current cookie' => ['currentSchemaCookie', 193],
    'next cookie' => ['nextSchemaCookie', 194],
    'prefix' => ['prefix', 'plugin%'],
    'range lower' => ['rangeLowerInclusive', 'plugin%'],
    'range upper' => ['rangeUpperBound', 'plugin&'],
    'index usable' => ['indexUsable', true],
    'prefix cursor' => ['usesPrefixRangeCursor', true],
    'literal wildcards' => ['escapedWildcardLiteralsInPrefix', ['%']],
    'percent literal marker' => ['escapedPercentIsLiteralPrefixByte', true],
    'underscore literal marker' => ['escapedUnderscoreIsLiteralPrefixByte', false],
    'current candidates' => ['currentCandidateRowids', [1, 2, 6, 3]],
    'next candidates' => ['nextCandidateRowids', [1, 2, 6, 3, 9]],
    'current matched' => ['currentMatchedRowids', [1, 2, 6, 3]],
    'next matched' => ['nextMatchedRowids', [1, 2, 6, 3, 9]],
    'current range false positives' => ['currentRangeFalsePositiveRowids', []],
    'next range false positives' => ['nextRangeFalsePositiveRowids', []],
    'current literal false positives' => ['currentLiteralPrefixFalsePositiveRowids', []],
    'next literal false positives' => ['nextLiteralPrefixFalsePositiveRowids', []],
    'current text one' => ['currentMatchedTexts.1', 'plugin%cache'],
    'next text one rtrim' => ['nextMatchedTexts.1', 'PLUGIN%CACHE'],
    'current tab remains' => ['currentRtrimTexts.6', "plugin%cache\t"],
    'next inserted literal percent' => ['nextRtrimTexts.9', 'plugin%new'],
    'row two nocase key' => ['currentNocaseKeys.2', 'plugin%cache'],
    'excluded current after upper' => ['currentExcludedDecodedRowids', [4, 5, 7]],
    'excluded next after upper' => ['nextExcludedDecodedRowids', [10, 11]],
    'current malformed' => ['currentMalformedRowids', [8]],
    'next malformed' => ['nextMalformedRowids', [12]],
    'current malformed error' => ['currentErrors.8', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'next malformed error' => ['nextErrors.12', 'SQLite encoding source UTF-16 text payload has an odd byte length'],
    'range retained' => ['rangeRetainedRowids', [1, 2, 6, 3]],
    'range exited' => ['rangeExitedRowids', []],
    'range entered' => ['rangeEnteredRowids', [9]],
    'matched changed rowids' => ['matchedLiteralPrefixChangedRowids', [1, 9]],
    'candidate changed' => ['candidateRowsetChanged', true],
    'matched changed' => ['matchedRowsetChanged', true],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor reusable false' => ['cursorReusable', false],
    'stale risk' => ['staleRangeCursorRisk', true],
    'residual after rtrim' => ['likeResidualAppliesAfterRtrim', true],
    'rtrim ascii' => ['rtrimTrimsOnlyAsciiSpace', true],
    'nocase ascii' => ['nocaseFoldsAsciiOnly', true],
    'dependency decode' => ['dependencies.0', 'sqlite-utf16-decode'],
    'dependency prefix range' => ['dependencies.1', 'sqlite-like-escaped-wildcard-prefix-range'],
    'dependency rtrim' => ['dependencies.2', 'sqlite-rtrim-expression-key'],
    'dependency source' => ['dependencies.3', 'sqlite-current-source-nextoneNineFour'],
];

foreach ($cases194 as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim current source nextOneNineFour ' . $name] = static function (TestRunner $t) use ($plan194, $valueAt194, $path, $expected): void {
        $t->same($expected, $valueAt194($plan194(), $path));
    };
}

$tests['utf16 nocase like rtrim current source nextOneNineFour invalidation reasons include literal prefix residual'] = static function (TestRunner $t) use ($plan194): void {
    $t->same([
        'source-name',
        'schema-cookie',
        'decoded-text',
        'rtrim-expression',
        'encoded-bytes',
        'malformed-text',
        'matched-rowset',
        'escaped-like-wildcard-literal-prefix',
        'matched-literal-prefix-rowset',
    ], $plan194()['invalidationReasons']);
};

$tests['utf16 nocase like rtrim current source nextOneNineFour stable source can reuse literal percent cursor'] = static function (TestRunner $t) use ($row194): void {
    $rows = [
        $row194(1, 'plugin%cache', 'UTF-16LE'),
        $row194(2, 'Plugin%Cache  ', 'UTF-16BE'),
        $row194(3, 'plugin%cache_extra', 'UTF-8'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyEscapedWildcardPrefixPlan(
        $rows,
        $rows,
        'plugin!%%',
        '!',
        'stable',
        'stable',
        194,
        194,
    );

    $t->same([1, 2, 3], $result['currentCandidateRowids']);
    $t->same([1, 2, 3], $result['currentMatchedRowids']);
    $t->same([], $result['matchedLiteralPrefixChangedRowids']);
    $t->same(['escaped-like-wildcard-literal-prefix'], $result['invalidationReasons']);
    $t->same(false, $result['cursorReusable']);
};

$tests['utf16 nocase like rtrim current source nextOneNineFour escaped underscore becomes literal prefix'] = static function (TestRunner $t) use ($row194): void {
    $rows = [
        $row194(1, 'plugin_cache', 'UTF-16LE'),
        $row194(2, 'PLUGIN_CACHE_A', 'UTF-16BE'),
        $row194(3, 'plugin-cache', 'UTF-8'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyEscapedWildcardPrefixPlan(
        $rows,
        $rows,
        'plugin!_%',
        '!',
        'stable',
        'stable',
        194,
        194,
    );

    $t->same('plugin_', $result['prefix']);
    $t->same(['_'], $result['escapedWildcardLiteralsInPrefix']);
    $t->same(false, $result['escapedPercentIsLiteralPrefixByte']);
    $t->same(true, $result['escapedUnderscoreIsLiteralPrefixByte']);
    $t->same([1, 2], $result['currentMatchedRowids']);
    $t->same([3], $result['currentExcludedDecodedRowids']);
};

$tests['utf16 nocase like rtrim current source nextOneNineFour unescaped percent keeps shorter prefix'] = static function (TestRunner $t) use ($row194): void {
    $rows = [
        $row194(1, 'plugin%cache', 'UTF-16LE'),
        $row194(2, 'plugin_cache', 'UTF-16BE'),
        $row194(3, 'plugin-cache', 'UTF-8'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyEscapedWildcardPrefixPlan(
        $rows,
        $rows,
        'plugin%',
        null,
        'stable',
        'stable',
        194,
        194,
    );

    $t->same('plugin', $result['prefix']);
    $t->same([], $result['escapedWildcardLiteralsInPrefix']);
    $t->same([1, 3, 2], $result['currentMatchedRowids']);
    $t->same([], $result['currentLiteralPrefixFalsePositiveRowids']);
};

$tests['utf16 nocase like rtrim current source nextOneNineFour escaped literal exact match rejects suffix'] = static function (TestRunner $t) use ($row194): void {
    $rows = [
        $row194(1, 'plugin%cache', 'UTF-16LE'),
        $row194(2, 'plugin%cache_extra', 'UTF-16BE'),
        $row194(3, 'plugin&cache', 'UTF-8'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyEscapedWildcardPrefixPlan(
        $rows,
        $rows,
        'plugin!%cache',
        '!',
        'stable',
        'stable',
        194,
        194,
    );

    $t->same('plugin%cache', $result['prefix']);
    $t->same([1, 2], $result['currentCandidateRowids']);
    $t->same([1], $result['currentMatchedRowids']);
    $t->same([2], $result['currentRangeFalsePositiveRowids']);
    $t->same(['%',], $result['escapedWildcardLiteralsInPrefix']);
};

$tests['utf16 nocase like rtrim current source nextOneNineFour rejects invalid escape length via base planner'] = static function (TestRunner $t) use ($current194, $nextOneNineFour): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyEscapedWildcardPrefixPlan($current194, $nextOneNineFour, 'plugin!!%', '!!'));
};

$tests['utf16 nocase like rtrim current source nextOneNineFour rejects missing option id'] = static function (TestRunner $t) use ($nextOneNineFour): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyEscapedWildcardPrefixPlan([
        ['option_name_bytes' => 'plugin%cache', 'text_encoding' => 1],
    ], $nextOneNineFour));
};

return $tests;
