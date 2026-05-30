<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$tests = [];

$enc173 = static fn (string $text, int $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row173 = static fn (int $id, string $name, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc173($name, $encoding),
    'text_encoding' => $encoding,
];
$bad173 = static fn (int $id, string $bytes, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$current173 = [
    $row173(1, 'Plugin_Cache  ', 2),
    $row173(2, 'plugin_cache', 3),
    $row173(3, "plugin_cache\t", 2),
    $row173(4, 'plugin_cache' . "\xc2\xa0", 3),
    $row173(5, 'plugin_cache_extra', 2),
    $row173(6, 'plugin_admin', 2),
    $row173(7, 'theme_cache', 2),
    $bad173(12, "\x00\xd8", 2),
];
$nextOneSevenThree = [
    $row173(1, 'Plugin_Cache', 3),
    $row173(2, 'plugin_cache   ', 3),
    $row173(3, "plugin_cache\t", 2),
    $row173(4, 'plugin_cache' . "\xc2\xa0", 3),
    $row173(5, 'plugin_cache_extra_v2', 2),
    $row173(6, 'plugin_admin', 2),
    $row173(8, 'PLUGIN_CACHE_NEW  ', 3),
    $bad173(13, "x\0y", 2),
];

$plan173 = static fn (
    ?array $current = null,
    ?array $next = null,
    string $pattern = 'plugin!_cache',
    ?string $escape = '!',
    string $currentSource = 'stable',
    string $nextSource = 'stable',
    int $currentCookie = 173,
    int $nextCookie = 173,
): array => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeySourcePlan(
    $current ?? $current173,
    $next ?? $nextOneSevenThree,
    $pattern,
    $escape,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt173 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        $value = $value[$part];
    }

    return $value;
};

$cases173 = [
    'status' => ['status', 'utf16-nocase-like-rtrim-current-source-nextoneSevenThree'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(option_name) COLLATE NOCASE'],
    'pattern' => ['pattern', 'plugin!_cache'],
    'escape' => ['escape', '!'],
    'collation' => ['collation', 'NOCASE'],
    'case sensitive false' => ['caseSensitiveLike', false],
    'rtrim ascii space only' => ['rtrimTrimsOnlyAsciiSpace', true],
    'nocase ascii only' => ['nocaseFoldsAsciiOnly', true],
    'source stable' => ['currentSource', 'stable'],
    'next source stable' => ['nextSource', 'stable'],
    'current cookie stable' => ['currentSchemaCookie', 173],
    'next cookie stable' => ['nextSchemaCookie', 173],
    'prefix' => ['prefix', 'plugin_cache'],
    'prefix ascii' => ['prefixIsAscii', true],
    'range lower' => ['range.lowerInclusive', 'plugin_cache'],
    'range upper' => ['range.upperBound', 'plugin_cachf'],
    'index usable' => ['indexUsable', true],
    'scan mode' => ['scanMode', 'nocase-rtrim-range'],
    'current order' => ['currentOrderRowids', [6, 1, 2, 3, 5, 4, 7]],
    'next order' => ['nextOrderRowids', [6, 1, 2, 3, 5, 8, 4]],
    'current candidates' => ['currentCandidateRowids', [1, 2, 3, 5, 4]],
    'next candidates' => ['nextCandidateRowids', [1, 2, 3, 5, 8, 4]],
    'current matched' => ['currentMatchedRowids', [1, 2]],
    'next matched' => ['nextMatchedRowids', [1, 2]],
    'current false positive tabs nbsp' => ['currentFalsePositiveRowids', [3, 5, 4]],
    'next false positive tabs nbsp' => ['nextFalsePositiveRowids', [3, 5, 8, 4]],
    'retained matched' => ['retainedMatchedRowids', [1, 2]],
    'entered matched' => ['enteredMatchedRowids', []],
    'exited matched' => ['exitedMatchedRowids', []],
    'row one current rtrim' => ['currentRtrimKeys.1', 'Plugin_Cache'],
    'row two next rtrim' => ['nextRtrimKeys.2', 'plugin_cache'],
    'tab is not trimmed' => ['currentRtrimKeys.3', "plugin_cache\t"],
    'nbsp is not trimmed' => ['currentRtrimKeys.4', 'plugin_cache' . "\xc2\xa0"],
    'row one nocase' => ['currentNocaseKeys.1', 'plugin_cache'],
    'row eight nocase' => ['nextNocaseKeys.8', 'plugin_cache_new'],
    'row one current encoding' => ['currentEncodings.1', 'UTF-16LE'],
    'row one next encoding' => ['nextEncodings.1', 'UTF-16BE'],
    'changed text' => ['changedTextRowids', [1, 2, 5]],
    'trailing spaces only' => ['changedTrailingSpaceOnlyRowids', [1, 2]],
    'changed rtrim key' => ['changedRtrimKeyRowids', [5]],
    'changed nocase key' => ['changedNocaseKeyRowids', [5]],
    'changed encoding' => ['changedEncodingRowids', [1]],
    'changed bytes' => ['changedBytesRowids', [1, 2, 5]],
    'changed residual' => ['changedResidualRowids', []],
    'current malformed' => ['currentMalformedRowids', [12]],
    'next malformed' => ['nextMalformedRowids', [13]],
    'byte reasons text' => ['byteReprepareReasons.0', 'decoded-text'],
    'byte reasons trailing' => ['byteReprepareReasons.1', 'trailing-space-bytes'],
    'byte reasons encoding' => ['byteReprepareReasons.2', 'text-encoding'],
    'byte reasons bytes' => ['byteReprepareReasons.3', 'encoded-bytes'],
    'semantic rtrim' => ['semanticInvalidationReasons.0', 'rtrim-key'],
    'semantic nocase' => ['semanticInvalidationReasons.1', 'nocase-key'],
    'semantic candidate' => ['semanticInvalidationReasons.2', 'candidate-rowset'],
    'semantic malformed' => ['semanticInvalidationReasons.3', 'malformed-text'],
    'byte only false' => ['byteOnlyReprepare', false],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor reusable false' => ['cursorReusable', false],
    'yield order unsafe' => ['safeToKeepYieldOrder', false],
    'dependency decode' => ['dependencies.0', 'sqlite-utf16-decode'],
    'dependency nextOneSevenThree' => ['dependencies.3', 'sqlite-current-source-nextoneSevenThree'],
];

foreach ($cases173 as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim current source nextOneSevenThree ' . $name] = static function (TestRunner $t) use ($plan173, $valueAt173, $path, $expected): void {
        $t->same($expected, $valueAt173($plan173(), $path));
    };
}

$tests['utf16 nocase like rtrim current source nextOneSevenThree byte only trailing spaces can keep cursor'] = static function (TestRunner $t) use ($row173): void {
    $current = [
        $row173(1, 'Plugin_Cache  ', 2),
        $row173(2, 'plugin_cache', 3),
    ];
    $next = [
        $row173(1, 'Plugin_Cache', 3),
        $row173(2, 'plugin_cache   ', 3),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeySourcePlan($current, $next, 'plugin!_cache', '!', 'stable', 'stable', 5, 5);
    $t->same(['decoded-text', 'trailing-space-bytes', 'text-encoding', 'encoded-bytes'], $result['byteReprepareReasons']);
    $t->same([], $result['semanticInvalidationReasons']);
    $t->same(true, $result['byteOnlyReprepare']);
    $t->same(false, $result['cursorInvalidated']);
    $t->same(true, $result['cursorReusable']);
    $t->same(true, $result['safeToKeepYieldOrder']);
};

$tests['utf16 nocase like rtrim current source nextOneSevenThree source change remains semantic even when bytes only'] = static function (TestRunner $t) use ($row173): void {
    $current = [$row173(1, 'Plugin_Cache  ', 2)];
    $next = [$row173(1, 'Plugin_Cache', 3)];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeySourcePlan($current, $next, 'plugin!_cache', '!', 'main.app_settings@172', 'main.app_settings@173', 172, 173);
    $t->same(['decoded-text', 'trailing-space-bytes', 'text-encoding', 'encoded-bytes'], $result['byteReprepareReasons']);
    $t->same(['source-name', 'schema-cookie'], $result['semanticInvalidationReasons']);
    $t->same(false, $result['byteOnlyReprepare']);
    $t->same(true, $result['cursorInvalidated']);
};

$tests['utf16 nocase like rtrim current source nextOneSevenThree unicode prefix falls back to full scan'] = static function (TestRunner $t) use ($row173): void {
    $rows = [
        $row173(1, 'éclair_cache  ', 2),
        $row173(2, 'Éclair_Cache', 3),
        $row173(3, 'plugin_cache', 2),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeySourcePlan($rows, $rows, 'éclair%', null, 'stable', 'stable', 9, 9);
    $t->same(false, $result['indexUsable']);
    $t->same('full-residual-scan', $result['scanMode']);
    $t->same([1], $result['currentMatchedRowids']);
    $t->same(['full-scan-like-residual'], $result['semanticInvalidationReasons']);
    $t->same(false, $result['cursorReusable']);
};

$tests['utf16 nocase like rtrim current source nextOneSevenThree rejects missing option bytes'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeySourcePlan(
        [['option_id' => 1, 'text_encoding' => 2]],
        [],
        'plugin%',
    ));
};

return $tests;
