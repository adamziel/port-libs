<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$tests = [];

$enc186 = static fn (string $text, int $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row186 = static fn (int $id, string $name, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc186($name, $encoding),
    'text_encoding' => $encoding,
];
$bad186 = static fn (int $id, string $bytes, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$current186 = [
    $row186(1, 'Plugin_Cache', 2),
    $row186(2, 'plugin_cache  ', 3),
    $row186(3, 'plugin_cache' . "\t", 2),
    $row186(4, 'plugin_cache' . "\xc2\xa0", 3),
    $row186(5, 'plugin_cache_alpha', 2),
    $row186(6, 'plugin_cache_beta', 3),
    $row186(7, 'plugin_cachf_border', 2),
    $row186(8, 'theme_cache', 3),
    $bad186(9, "\x00\xd8", 2),
];
$next186 = [
    $row186(1, 'Plugin_Cache', 3),
    $row186(2, 'PLUGIN_CACHE', 2),
    $row186(3, 'plugin_cache', 3),
    $row186(4, 'plugin_cache' . "\xc2\xa0", 2),
    $row186(5, 'plugin_cache_alpha  ', 3),
    $row186(6, 'plugin_cache_gamma', 2),
    $row186(10, 'Plugin_Cache_delta', 3),
    $row186(11, 'other_cache', 2),
    $bad186(12, "x\0y", 2),
];

$plan186 = static fn (
    ?array $current = null,
    ?array $next = null,
    string $pattern = 'plugin!_cache%',
    ?string $escape = '!',
    string $currentSource = 'main.wp_options@185',
    string $nextSource = 'main.wp_options@186',
    int $currentCookie = 185,
    int $nextCookie = 186,
    ?array $resumeToken = ['key' => 'plugin_cache', 'rowid' => 2],
): array => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameResumeBoundaryPlan(
    $current ?? $current186,
    $next ?? $next186,
    $pattern,
    $escape,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
    $resumeToken,
);

$valueAt186 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases186 = [
    'status' => ['status', 'utf16-nocase-like-rtrim-current-source-next186'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* resume boundary */'],
    'pattern' => ['pattern', 'plugin!_cache%'],
    'escape' => ['escape', '!'],
    'collation' => ['collation', 'NOCASE'],
    'case sensitive false' => ['caseSensitiveLike', false],
    'prefix' => ['prefix', 'plugin_cache'],
    'prefix ascii' => ['prefixIsAscii', true],
    'range lower' => ['rangeLowerInclusive', 'plugin_cache'],
    'range upper' => ['rangeUpperBound', 'plugin_cachf'],
    'index usable' => ['indexUsable', true],
    'prefix range cursor' => ['usesPrefixRangeCursor', true],
    'current source' => ['currentSource', 'main.wp_options@185'],
    'next source' => ['nextSource', 'main.wp_options@186'],
    'current cookie' => ['currentSchemaCookie', 185],
    'next cookie' => ['nextSchemaCookie', 186],
    'current decoded rowids' => ['currentDecodedRowids', [1, 2, 3, 5, 6, 4, 7, 8]],
    'next decoded rowids' => ['nextDecodedRowids', [11, 1, 2, 3, 5, 10, 6, 4]],
    'current candidate rowids' => ['currentCandidateRowids', [1, 2, 3, 5, 6, 4]],
    'next candidate rowids' => ['nextCandidateRowids', [1, 2, 3, 5, 10, 6, 4]],
    'current matched rowids' => ['currentMatchedRowids', [1, 2, 3, 5, 6, 4]],
    'next matched rowids' => ['nextMatchedRowids', [1, 2, 3, 5, 10, 6, 4]],
    'current false positives' => ['currentRangeFalsePositiveRowids', []],
    'next false positives' => ['nextRangeFalsePositiveRowids', []],
    'current excluded decoded' => ['currentExcludedDecodedRowids', [7, 8]],
    'next excluded decoded' => ['nextExcludedDecodedRowids', [11]],
    'range retained' => ['currentRangeRetainedRowids', [1, 2, 3, 5, 6, 4]],
    'range exited' => ['currentRangeExitedRowids', []],
    'range entered' => ['nextRangeEnteredRowids', [10]],
    'matched retained' => ['matchedRetainedRowids', [1, 2, 3, 5, 6, 4]],
    'matched entered' => ['matchedEnteredRowids', [10]],
    'matched exited' => ['matchedExitedRowids', []],
    'resume token' => ['resumeToken', ['key' => 'plugin_cache', 'rowid' => 2]],
    'current resume rowids' => ['currentResumeRowids', [3, 5, 6, 4]],
    'next resume rowids' => ['nextResumeRowids', [3, 5, 10, 6, 4]],
    'current resume key three' => ['currentResumeKeys.3', 'plugin_cache' . "\t"],
    'next resume key ten' => ['nextResumeKeys.10', 'plugin_cache_delta'],
    'resume boundary changed' => ['resumeBoundaryChangedRowids', [10]],
    'semantic stable' => ['semanticStableRowids', [1, 4, 5]],
    'semantic changed' => ['semanticChangedRowids', [2, 3, 5, 6, 10]],
    'byte order only changed' => ['byteOrderOnlyChangedRowids', [1, 4]],
    'safe to resume false' => ['safeToResumeAfterToken', false],
    'must reopen source cursor' => ['mustReopenSourceCursor', true],
    'rtrim ascii only' => ['resumeKeepsRtrimAsciiOnly', true],
    'nocase ascii only' => ['resumeKeepsNocaseAsciiOnly', true],
    'byte order semantic stable' => ['utf16ByteOrderCanChangeWithoutSemanticKeyChange', true],
    'row two next rtrim' => ['nextRtrimTexts.2', 'PLUGIN_CACHE'],
    'row three current tab remains' => ['currentRtrimTexts.3', 'plugin_cache' . "\t"],
    'row four nbsp remains' => ['currentRtrimTexts.4', 'plugin_cache' . "\xc2\xa0"],
    'row one stable key' => ['currentNocaseKeys.1', 'plugin_cache'],
    'row one next stable key' => ['nextNocaseKeys.1', 'plugin_cache'],
    'changed text' => ['changedTextRowids', [2, 3, 5, 6]],
    'changed rtrim' => ['changedRtrimRowids', [2, 3, 6]],
    'changed nocase' => ['changedNocaseKeyRowids', [3, 6]],
    'changed bytes' => ['changedBytesRowids', [1, 2, 3, 4, 5, 6]],
    'malformed current' => ['currentMalformedRowids', [9]],
    'malformed next' => ['nextMalformedRowids', [12]],
    'current malformed error' => ['currentErrors.9', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'next malformed error' => ['nextErrors.12', 'SQLite encoding source UTF-16 text payload has an odd byte length'],
    'dependency decode' => ['dependencies.0', 'sqlite-utf16-decode'],
    'dependency range' => ['dependencies.1', 'sqlite-like-nocase-prefix-range'],
    'dependency rtrim' => ['dependencies.2', 'sqlite-rtrim-residual-match'],
    'dependency source' => ['dependencies.3', 'sqlite-current-source-next186'],
    'dependency resume' => ['dependencies.4', 'sqlite-utf16-resume-boundary'],
];

foreach ($cases186 as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim current source next186 ' . $name] = static function (TestRunner $t) use ($plan186, $valueAt186, $path, $expected): void {
        $t->same($expected, $valueAt186($plan186(), $path));
    };
}

$tests['utf16 nocase like rtrim current source next186 invalidation reason order'] = static function (TestRunner $t) use ($plan186): void {
    $t->same([
        'source-name',
        'schema-cookie',
        'decoded-text',
        'rtrim-expression',
        'nocase-key',
        'encoded-bytes',
        'malformed-text',
        'matched-rowset',
        'resume-boundary-rowset',
        'byte-order-only-source-refresh',
    ], $plan186()['invalidationReasons']);
};

$tests['utf16 nocase like rtrim current source next186 stable byte order refresh can resume'] = static function (TestRunner $t) use ($row186): void {
    $current = [
        $row186(1, 'Plugin_Cache', 2),
        $row186(2, 'plugin_cache_alpha', 2),
        $row186(3, 'plugin_cache_beta', 3),
    ];
    $next = [
        $row186(1, 'Plugin_Cache', 3),
        $row186(2, 'plugin_cache_alpha', 3),
        $row186(3, 'plugin_cache_beta', 2),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameResumeBoundaryPlan(
        $current,
        $next,
        'plugin!_cache%',
        '!',
        'stable-source',
        'stable-source',
        186,
        186,
        ['key' => 'plugin_cache', 'rowid' => 1],
    );
    $t->same([2, 3], $result['currentResumeRowids']);
    $t->same([2, 3], $result['nextResumeRowids']);
    $t->same([1, 2, 3], $result['semanticStableRowids']);
    $t->same([1, 2, 3], $result['byteOrderOnlyChangedRowids']);
    $t->same([], $result['resumeBoundaryChangedRowids']);
    $t->same(true, $result['safeToResumeAfterToken']);
    $t->same(false, $result['mustReopenSourceCursor']);
};

$tests['utf16 nocase like rtrim current source next186 rtrim tab boundary is not collapsed'] = static function (TestRunner $t) use ($row186): void {
    $rows = [
        $row186(1, 'plugin_cache', 2),
        $row186(2, 'plugin_cache' . "\t", 2),
        $row186(3, 'plugin_cache ', 3),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameResumeBoundaryPlan(
        $rows,
        $rows,
        'plugin!_cache',
        '!',
        'stable',
        'stable',
        7,
        7,
        ['key' => 'plugin_cache', 'rowid' => 1],
    );
    $t->same([3, 2], $result['currentResumeRowids']);
    $t->same([1, 3], $result['currentMatchedRowids']);
    $t->same([2], $result['currentRangeFalsePositiveRowids']);
};

$tests['utf16 nocase like rtrim current source next186 rejects malformed resume key'] = static function (TestRunner $t) use ($row186): void {
    $rows = [$row186(1, 'plugin_cache', 2)];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameResumeBoundaryPlan(
        $rows,
        $rows,
        'plugin%',
        null,
        'stable',
        'stable',
        1,
        1,
        ['key' => 123, 'rowid' => 1],
    ));
};

$tests['utf16 nocase like rtrim current source next186 rejects malformed resume rowid'] = static function (TestRunner $t) use ($row186): void {
    $rows = [$row186(1, 'plugin_cache', 2)];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameResumeBoundaryPlan(
        $rows,
        $rows,
        'plugin%',
        null,
        'stable',
        'stable',
        1,
        1,
        ['key' => 'plugin', 'rowid' => '1'],
    ));
};

return $tests;
