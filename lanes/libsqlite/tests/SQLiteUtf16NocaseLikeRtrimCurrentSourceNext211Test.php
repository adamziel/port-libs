<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$tests = [];

$enc211 = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row211 = static fn (int $id, string $name, int|string $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc211($name, $encoding),
    'text_encoding' => match ($encoding) {
        'UTF-8', 1 => 1,
        'UTF-16LE', 2 => 2,
        'UTF-16BE', 3 => 3,
    },
];
$bad211 = static fn (int $id, string $bytes, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$current211 = [
    $row211(1, 'plugin_cache', 'UTF-16LE'),
    $row211(2, 'Plugin_Cache  ', 'UTF-16BE'),
    $row211(3, 'plugin_cache_alpha', 'UTF-8'),
    $row211(4, 'plugin-cache', 'UTF-16LE'),
    $row211(5, 'plugin_cache' . "\t", 'UTF-16BE'),
    $row211(6, 'plugin_cacheZ', 'UTF-16LE'),
    $row211(7, 'plügIN_cache', 'UTF-16BE'),
    $row211(8, 'theme_cache', 'UTF-8'),
    $bad211(9, "\x00\xd8", 2),
];
$nextTwoOneOne = [
    $row211(1, 'plugin_cache', 'UTF-16BE'),
    $row211(2, 'Plugin_Cache', 'UTF-16LE'),
    $row211(3, 'plugin_cache_alpha', 'UTF-16BE'),
    $row211(4, 'plugin-cache', 'UTF-16LE'),
    $row211(5, 'plugin_cache' . "\t", 'UTF-16LE'),
    $row211(6, 'plugin_cacheZ', 'UTF-16BE'),
    $row211(7, 'plügIN_cache', 'UTF-16BE'),
    $row211(10, 'PLUGIN_CACHE_NEW', 'UTF-16LE'),
    $row211(11, 'plugin_cache   ', 'UTF-16BE'),
    $bad211(12, "\x00\xd8", 2),
];

$plan211 = static fn (
    ?array $current = null,
    ?array $next = null,
    string $pattern = 'plugin!_cache%',
    ?string $escape = '!',
    string $currentSource = 'main.wp_options@210',
    string $nextSource = 'main.wp_options@211',
    int $currentCookie = 210,
    int $nextCookie = 211,
): array => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNameSourceRefreshPlan(
    $current ?? $current211,
    $next ?? $nextTwoOneOne,
    $pattern,
    $escape,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt211 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases211 = [
    'status' => ['status', 'utf16-nocase-like-rtrim-current-source-nexttwoOneOne'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* current-source refresh */'],
    'pattern' => ['pattern', 'plugin!_cache%'],
    'escape' => ['escape', '!'],
    'collation' => ['collation', 'NOCASE'],
    'current source' => ['currentSource', 'main.wp_options@210'],
    'next source' => ['nextSource', 'main.wp_options@211'],
    'current cookie' => ['currentSchemaCookie', 210],
    'next cookie' => ['nextSchemaCookie', 211],
    'prefix' => ['prefix', 'plugin_cache'],
    'range lower' => ['rangeLowerInclusive', 'plugin_cache'],
    'range upper' => ['rangeUpperBound', 'plugin_cachf'],
    'index usable' => ['indexUsable', true],
    'current candidates' => ['currentCandidateRowids', [1, 2, 5, 3, 6]],
    'next candidates' => ['nextCandidateRowids', [1, 2, 11, 5, 3, 10, 6]],
    'candidate retained' => ['candidateRetainedRowids', [1, 2, 3, 5, 6]],
    'candidate exited' => ['candidateExitedRowids', []],
    'candidate entered' => ['candidateEnteredRowids', [10, 11]],
    'current matched' => ['currentMatchedRowids', [1, 2, 5, 3, 6]],
    'next matched' => ['nextMatchedRowids', [1, 2, 11, 5, 3, 10, 6]],
    'matched retained' => ['matchedRetainedRowids', [1, 2, 3, 5, 6]],
    'matched exited' => ['matchedExitedRowids', []],
    'matched entered' => ['matchedEnteredRowids', [10, 11]],
    'current false positives' => ['currentFalsePositiveRowids', []],
    'next false positives' => ['nextFalsePositiveRowids', []],
    'current excluded' => ['currentExcludedDecodedRowids', [4, 7, 8]],
    'next excluded' => ['nextExcludedDecodedRowids', [4, 7]],
    'current row2 rtrim' => ['currentRtrimTexts.2', 'Plugin_Cache'],
    'next row11 rtrim' => ['nextRtrimTexts.11', 'plugin_cache'],
    'row5 tab not trimmed' => ['nextRtrimTexts.5', 'plugin_cache' . "\t"],
    'current row2 nocase' => ['currentNocaseKeys.2', 'plugin_cache'],
    'next row10 nocase' => ['nextNocaseKeys.10', 'plugin_cache_new'],
    'unicode nocase ascii only' => ['currentNocaseKeys.7', 'plügin_cache'],
    'current encoding row1' => ['currentEncodings.1', 'UTF-16LE'],
    'next encoding row1' => ['nextEncodings.1', 'UTF-16BE'],
    'byte order rowids' => ['byteOrderOnlyRowids', [1, 2, 3, 5, 6]],
    'encoding changed rowids' => ['encodingChangedRowids', [1, 2, 3, 5, 6]],
    'decoded changed rowids' => ['decodedRtrimTextChangedRowids', []],
    'next matched text ten' => ['nextMatchedTexts.10', 'PLUGIN_CACHE_NEW'],
    'next matched text eleven' => ['nextMatchedTexts.11', 'plugin_cache'],
    'current malformed' => ['currentMalformedRowids', [9]],
    'next malformed' => ['nextMalformedRowids', [12]],
    'current error' => ['currentErrors.9', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'next error' => ['nextErrors.12', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor reusable' => ['cursorReusable', false],
    'byte order reusable false' => ['byteOrderOnlyRefreshReusable', false],
    'rtrim ascii' => ['rtrimTrimsOnlyAsciiSpace', true],
    'nocase ascii' => ['nocaseFoldsAsciiOnly', true],
    'residual after rtrim' => ['residualCheckedAfterRtrim', true],
    'dependency decode' => ['dependencies.0', 'sqlite-utf16-decode'],
    'dependency range' => ['dependencies.1', 'sqlite-like-nocase-prefix-range'],
    'dependency rtrim' => ['dependencies.2', 'sqlite-rtrim-expression-key'],
    'dependency source' => ['dependencies.3', 'sqlite-current-source-nexttwoOneOne'],
];

foreach ($cases211 as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim current source nextTwoOneOne ' . $name] = static function (TestRunner $t) use ($plan211, $valueAt211, $path, $expected): void {
        $t->same($expected, $valueAt211($plan211(), $path));
    };
}

$tests['utf16 nocase like rtrim current source nextTwoOneOne invalidation reasons name rowset malformed'] = static function (TestRunner $t) use ($plan211): void {
    $t->same([
        'source-name',
        'schema-cookie',
        'candidate-rowset',
        'matched-rowset',
        'malformed-text',
    ], $plan211()['invalidationReasons']);
};

$tests['utf16 nocase like rtrim current source nextTwoOneOne byte order only refresh stays reusable'] = static function (TestRunner $t) use ($row211): void {
    $current = [
        $row211(1, 'plugin_cache', 'UTF-16LE'),
        $row211(2, 'Plugin_Cache  ', 'UTF-16BE'),
    ];
    $next = [
        $row211(1, 'plugin_cache', 'UTF-16BE'),
        $row211(2, 'Plugin_Cache  ', 'UTF-16LE'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNameSourceRefreshPlan(
        $current,
        $next,
        'plugin!_cache%',
        '!',
        'stable',
        'stable',
        211,
        211,
    );

    $t->same([1, 2], $result['byteOrderOnlyRowids']);
    $t->same(['byte-order-only-refresh'], $result['invalidationReasons']);
    $t->same(false, $result['cursorInvalidated']);
    $t->same(true, $result['cursorReusable']);
    $t->same(true, $result['byteOrderOnlyRefreshReusable']);
};

$tests['utf16 nocase like rtrim current source nextTwoOneOne decoded text change invalidates even stable source'] = static function (TestRunner $t) use ($row211): void {
    $current = [
        $row211(1, 'plugin_cache', 'UTF-16LE'),
        $row211(2, 'plugin_cache_old', 'UTF-16BE'),
    ];
    $next = [
        $row211(1, 'plugin_cache', 'UTF-16BE'),
        $row211(2, 'plugin_option_old', 'UTF-16BE'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNameSourceRefreshPlan(
        $current,
        $next,
        'plugin!_cache%',
        '!',
        'stable',
        'stable',
        211,
        211,
    );

    $t->same([2], $result['decodedRtrimTextChangedRowids']);
    $t->same([1, 2], $result['currentMatchedRowids']);
    $t->same([1], $result['nextMatchedRowids']);
    $t->same(['candidate-rowset', 'matched-rowset', 'decoded-rtrim-text'], $result['invalidationReasons']);
};

$tests['utf16 nocase like rtrim current source nextTwoOneOne range false positives retain residual fence'] = static function (TestRunner $t) use ($row211): void {
    $rows = [
        $row211(1, 'plugin_cache', 'UTF-16LE'),
        $row211(2, 'plugin_cache' . "\t", 'UTF-16BE'),
        $row211(3, 'plugin_cache_old', 'UTF-8'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNameSourceRefreshPlan(
        $rows,
        $rows,
        'plugin!_cache',
        '!',
        'stable',
        'stable',
        211,
        211,
    );

    $t->same([1], $result['currentMatchedRowids']);
    $t->same([2, 3], $result['currentFalsePositiveRowids']);
    $t->same([1 => 'plugin_cache'], $result['currentMatchedTexts']);
};

$tests['utf16 nocase like rtrim current source nextTwoOneOne rejects malformed row shape'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNameSourceRefreshPlan([
        ['option_id' => 1, 'option_name_bytes' => 'plugin_cache', 'text_encoding' => 'UTF-8'],
    ], []));
};

return $tests;
