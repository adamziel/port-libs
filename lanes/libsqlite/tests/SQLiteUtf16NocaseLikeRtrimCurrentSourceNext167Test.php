<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$tests = [];

$enc167 = static fn (string $text, int $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row167 = static fn (int $id, string $name, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc167($name, $encoding),
    'text_encoding' => $encoding,
];

$current167 = [
    $row167(1, 'éclair_cache  ', 2),
    $row167(2, 'Éclair_Cache', 3),
    $row167(3, 'eclair_cache', 2),
    $row167(4, 'éclair_shadow  ', 1),
    $row167(5, 'plugin_éclair_cache', 2),
    $row167(6, 'élan_cache', 3),
    ['option_id' => 7, 'option_name_bytes' => "\x00\xd8", 'text_encoding' => 2],
];
$nextOneSixSeven = [
    $row167(1, 'éclair_cache', 3),
    $row167(2, 'Éclair_Cache  ', 3),
    $row167(4, 'éclair_shadow', 1),
    $row167(5, 'plugin_éclair_cache', 2),
    $row167(8, 'éclair_new  ', 2),
    $row167(9, 'ÉCLAIR_ADMIN', 3),
    ['option_id' => 10, 'option_name_bytes' => "x\0y", 'text_encoding' => 2],
];

$plan167 = static fn (?array $current = null, ?array $next = null, string $pattern = 'éclair%', ?string $escape = null): array => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyFallbackPlan(
    $current ?? $current167,
    $next ?? $nextOneSixSeven,
    $pattern,
    $escape,
);

$valueAt167 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        $value = $value[$part];
    }

    return $value;
};

$cases167 = [
    'status' => ['status', 'utf16-nocase-like-rtrim-current-source-nextoneSixSeven'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(option_name) COLLATE NOCASE'],
    'pattern' => ['pattern', 'éclair%'],
    'escape' => ['escape', null],
    'collation' => ['collation', 'NOCASE'],
    'case sensitive flag' => ['caseSensitiveLike', false],
    'prefix' => ['prefix', 'éclair'],
    'prefix characters' => ['prefixCharacters', 6],
    'prefix ascii false' => ['prefixIsAscii', false],
    'index unusable' => ['indexUsable', false],
    'scan mode' => ['scanMode', 'full-residual-scan'],
    'rejected reason' => ['rejectedReason', 'nocase_like_prefix_must_be_ascii_for_range'],
    'range is null' => ['range', null],
    'current source' => ['currentSource', 'main.app_settings@166'],
    'next source' => ['nextSource', 'main.app_settings@167'],
    'current cookie' => ['currentSchemaCookie', 166],
    'next cookie' => ['nextSchemaCookie', 167],
    'current order keeps full scan rows' => ['currentOrderRowids', [3, 5, 2, 1, 4, 6]],
    'next order keeps full scan rows' => ['nextOrderRowids', [5, 9, 2, 1, 8, 4]],
    'current candidates are decoded full scan' => ['currentCandidateRowids', [3, 5, 2, 1, 4, 6]],
    'next candidates are decoded full scan' => ['nextCandidateRowids', [5, 9, 2, 1, 8, 4]],
    'current matched unicode residuals' => ['currentMatchedRowids', [1, 4]],
    'next matched unicode residuals' => ['nextMatchedRowids', [1, 8, 4]],
    'current false positives retained' => ['currentFalsePositiveRowids', [3, 5, 2, 6]],
    'next false positives retained' => ['nextFalsePositiveRowids', [5, 9, 2]],
    'retained matched' => ['retainedMatchedRowids', [1, 4]],
    'entered matched' => ['enteredMatchedRowids', [8]],
    'exited matched' => ['exitedMatchedRowids', []],
    'current malformed' => ['currentMalformedRowids', [7]],
    'next malformed utf16' => ['nextMalformedRowids', [10]],
    'current row one text' => ['currentTexts.1', 'éclair_cache  '],
    'next row one text' => ['nextTexts.1', 'éclair_cache'],
    'current row one rtrim' => ['currentRtrimTexts.1', 'éclair_cache'],
    'next row one rtrim' => ['nextRtrimTexts.1', 'éclair_cache'],
    'row two nocase ascii only keeps capital accent' => ['currentNocaseKeys.2', 'Éclair_cache'],
    'row nine nocase ascii only folds ascii suffix' => ['nextNocaseKeys.9', 'Éclair_admin'],
    'row one current encoding' => ['currentEncodings.1', 'UTF-16LE'],
    'row one next encoding' => ['nextEncodings.1', 'UTF-16BE'],
    'row one residual true' => ['currentResidualMatches.1', true],
    'row two residual false' => ['currentResidualMatches.2', false],
    'row eight residual true' => ['nextResidualMatches.8', true],
    'changed text rows' => ['changedTextRowids', [1, 2, 4]],
    'changed rtrim rows' => ['changedRtrimRowids', []],
    'changed nocase rows' => ['changedNocaseKeyRowids', []],
    'changed encoding rows' => ['changedEncodingRowids', [1]],
    'changed bytes rows' => ['changedBytesRowids', [1, 2, 4]],
    'changed residual rows' => ['changedResidualRowids', []],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor reusable false' => ['cursorReusable', false],
    'reason source' => ['invalidationReasons.0', 'source-name'],
    'reason schema' => ['invalidationReasons.1', 'schema-cookie'],
    'reason full scan fallback' => ['invalidationReasons.2', 'full-scan-like-residual'],
    'reason decoded text' => ['invalidationReasons.3', 'decoded-text'],
    'reason text encoding' => ['invalidationReasons.4', 'text-encoding'],
    'reason encoded bytes' => ['invalidationReasons.5', 'encoded-bytes'],
    'reason malformed' => ['invalidationReasons.6', 'malformed-text'],
    'reason candidate rowset' => ['invalidationReasons.7', 'candidate-rowset'],
    'reason matched rowset' => ['invalidationReasons.8', 'matched-rowset'],
    'full scan fallback flag' => ['fullScanFallbackPreservesResidualLike', true],
    'rtrim ascii only' => ['rtrimTrimsOnlyAsciiSpace', true],
    'nocase ascii only' => ['nocaseFoldsAsciiOnly', true],
    'dependency utf16' => ['dependencies.0', 'sqlite-utf16-decode'],
    'dependency rtrim' => ['dependencies.1', 'sqlite-rtrim-expression'],
    'dependency full scan' => ['dependencies.2', 'sqlite-like-nocase-full-scan-fallback'],
    'dependency nextOneSixSeven' => ['dependencies.3', 'sqlite-current-source-nextoneSixSeven'],
];

foreach ($cases167 as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim current source nextOneSixSeven ' . $name] = static function (TestRunner $t) use ($plan167, $valueAt167, $path, $expected): void {
        $t->same($expected, $valueAt167($plan167(), $path));
    };
}

$tests['utf16 nocase like rtrim current source nextOneSixSeven leading wildcard also full scans'] = static function (TestRunner $t) use ($plan167): void {
    $result = $plan167(null, null, '%cache');
    $t->same('', $result['prefix']);
    $t->same('no_fixed_prefix', $result['rejectedReason']);
    $t->same('full-residual-scan', $result['scanMode']);
    $t->same([3, 5, 2, 1, 6], $result['currentMatchedRowids']);
    $t->same([5, 2, 1], $result['nextMatchedRowids']);
    $t->true(in_array('full-scan-like-residual', $result['invalidationReasons'], true));
};

$tests['utf16 nocase like rtrim current source nextOneSixSeven escaped wildcard keeps residual fallback'] = static function (TestRunner $t) use ($row167): void {
    $rows = [
        $row167(1, 'éclair_%cache', 2),
        $row167(2, 'éclair_admin_cache', 2),
        $row167(3, 'éclair_%cache  ', 3),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyFallbackPlan(
        $rows,
        $rows,
        'éclair!_!%cache',
        '!',
        'stable',
        'stable',
        167,
        167,
    );
    $t->same('full-residual-scan', $result['scanMode']);
    $t->same([1, 3], $result['currentMatchedRowids']);
    $t->same([2], $result['currentFalsePositiveRowids']);
    $t->same(['full-scan-like-residual'], $result['invalidationReasons']);
    $t->same(false, $result['cursorReusable']);
};

$tests['utf16 nocase like rtrim current source nextOneSixSeven ascii prefix still uses nocase range'] = static function (TestRunner $t) use ($row167): void {
    $rows = [
        $row167(1, 'plugin_cache  ', 2),
        $row167(2, 'Plugin_Cache', 3),
        $row167(3, 'theme_cache', 2),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyFallbackPlan(
        $rows,
        $rows,
        'plugin%',
        null,
        'stable',
        'stable',
        167,
        167,
    );
    $t->same('nocase-range', $result['scanMode']);
    $t->same(['lowerInclusive' => 'plugin', 'upperBound' => 'plugio'], $result['range']);
    $t->same([1, 2], $result['currentCandidateRowids']);
    $t->same([1, 2], $result['currentMatchedRowids']);
    $t->same([], $result['invalidationReasons']);
    $t->same(true, $result['cursorReusable']);
};

$tests['utf16 nocase like rtrim current source nextOneSixSeven rejects missing bytes'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyFallbackPlan([['option_id' => 1, 'text_encoding' => 2]], [], 'éclair%'));
};

return $tests;
