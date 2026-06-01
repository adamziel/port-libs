<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$tests = [];

$enc210 = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row210 = static fn (int $id, string $name, int|string $encoding): array => [
    'setting_id' => $id,
    'key_name_bytes' => $enc210($name, $encoding),
    'text_encoding' => match ($encoding) {
        'UTF-8', 1 => 1,
        'UTF-16LE', 2 => 2,
        'UTF-16BE', 3 => 3,
    },
];
$bad210 = static fn (int $id, string $bytes, int $encoding): array => [
    'setting_id' => $id,
    'key_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$current210 = [
    $row210(1, "plugin\0cache", 'UTF-16LE'),
    $row210(2, "Plugin\0Cache  ", 'UTF-16BE'),
    $row210(3, "plugin\0cache_extra", 'UTF-8'),
    $row210(4, "plugin\0other", 'UTF-16LE'),
    $row210(5, 'plugin', 'UTF-16BE'),
    $row210(6, "plugin\0cache\t", 'UTF-16LE'),
    $row210(7, "theme\0cache", 'UTF-16BE'),
    $bad210(8, "\x00\xd8", 2),
];
$nextTwoOneZero = [
    $row210(1, "plugin\0cache", 'UTF-16BE'),
    $row210(2, "Plugin\0Cache", 'UTF-16LE'),
    $row210(3, "plugin\0cache_extra", 'UTF-16BE'),
    $row210(4, "plugin\0other", 'UTF-16LE'),
    $row210(5, 'plugin', 'UTF-8'),
    $row210(6, "plugin\0cache\t", 'UTF-16BE'),
    $row210(9, "PLUGIN\0CACHE_NEW", 'UTF-16LE'),
    $row210(10, "plugin\0cache  ", 'UTF-16BE'),
    $bad210(11, "\x00\xd8", 2),
];

$plan210 = static fn (?array $current = null, ?array $next = null, string $pattern = "plugin\0cache%"): array => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyEmbeddedNulPlan(
    $current ?? $current210,
    $next ?? $nextTwoOneZero,
    $pattern,
);

$valueAt210 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases210 = [
    'status' => ['status', 'utf16-nocase-like-rtrim-current-source-nexttwoOneZero'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(key_name) COLLATE NOCASE LIKE ? /* embedded NUL */'],
    'pattern' => ['pattern', "plugin\0cache%"],
    'pattern hex' => ['patternHex', '706c7567696e00636163686525'],
    'escape' => ['escape', null],
    'collation' => ['collation', 'NOCASE'],
    'current source' => ['currentSource', 'main.app_settings@209'],
    'next source' => ['nextSource', 'main.app_settings@210'],
    'current cookie' => ['currentSchemaCookie', 209],
    'next cookie' => ['nextSchemaCookie', 210],
    'prefix' => ['prefix', "plugin\0cache"],
    'prefix hex' => ['prefixHex', '706c7567696e006361636865'],
    'prefix contains nul' => ['prefixContainsNul', true],
    'range lower' => ['rangeLowerInclusive', "plugin\0cache"],
    'range lower hex' => ['rangeLowerInclusiveHex', '706c7567696e006361636865'],
    'range upper' => ['rangeUpperBound', "plugin\0cachf"],
    'range upper hex' => ['rangeUpperBoundHex', '706c7567696e006361636866'],
    'nul byte position' => ['nulBytePositionInPrefix', 6],
    'current index' => ['currentIndexUsable', true],
    'next index' => ['nextIndexUsable', true],
    'prefix cursor' => ['usesPrefixRangeCursor', true],
    'current candidates' => ['currentCandidateRowids', [1, 2, 6, 3]],
    'next candidates' => ['nextCandidateRowids', [1, 2, 10, 6, 3, 9]],
    'current matched' => ['currentMatchedRowids', [1, 2, 6, 3]],
    'next matched' => ['nextMatchedRowids', [1, 2, 10, 6, 3, 9]],
    'matched retained' => ['matchedRetainedRowids', [1, 2, 6, 3]],
    'matched exited' => ['matchedExitedRowids', []],
    'matched entered' => ['matchedEnteredRowids', [10, 9]],
    'current false positives' => ['currentFalsePositiveRowids', []],
    'next false positives' => ['nextFalsePositiveRowids', []],
    'current excluded' => ['currentExcludedDecodedRowids', [5, 4, 7]],
    'next excluded' => ['nextExcludedDecodedRowids', [5, 4]],
    'current row two rtrim text' => ['currentRtrimTexts.2', "Plugin\0Cache"],
    'current row six tab preserved' => ['currentRtrimTexts.6', "plugin\0cache\t"],
    'next row ten rtrim text' => ['nextRtrimTexts.10', "plugin\0cache"],
    'current row two rtrim hex' => ['currentRtrimHex.2', '506c7567696e004361636865'],
    'next row ten rtrim hex' => ['nextRtrimHex.10', '706c7567696e006361636865'],
    'current row two nocase' => ['currentNocaseKeys.2', "plugin\0cache"],
    'next row nine nocase' => ['nextNocaseKeys.9', "plugin\0cache_new"],
    'current row two nocase hex' => ['currentNocaseKeyHex.2', '706c7567696e006361636865'],
    'next row nine nocase hex' => ['nextNocaseKeyHex.9', '706c7567696e0063616368655f6e6577'],
    'current matched row one hex' => ['currentMatchedHex.1', '706c7567696e006361636865'],
    'next matched row nine hex' => ['nextMatchedHex.9', '504c5547494e0043414348455f4e4557'],
    'current embedded nul rows' => ['currentEmbeddedNulRowids', [1, 2, 3, 4, 6, 7]],
    'next embedded nul rows' => ['nextEmbeddedNulRowids', [1, 2, 3, 4, 6, 9, 10]],
    'current embedded nul matched' => ['currentEmbeddedNulMatchedRowids', [1, 2, 3, 6]],
    'next embedded nul matched' => ['nextEmbeddedNulMatchedRowids', [1, 2, 3, 6, 9, 10]],
    'current embedded nul false positives' => ['currentEmbeddedNulFalsePositiveRowids', []],
    'next embedded nul false positives' => ['nextEmbeddedNulFalsePositiveRowids', []],
    'current nul position one' => ['currentEmbeddedNulPositions.1', 6],
    'next nul position nine' => ['nextEmbeddedNulPositions.9', 6],
    'current text after nul one' => ['currentTextAfterNul.1', 'cache'],
    'next text after nul nine' => ['nextTextAfterNul.9', 'CACHE_NEW'],
    'current text after nul one hex' => ['currentTextAfterNulHex.1', '6361636865'],
    'next text after nul nine hex' => ['nextTextAfterNulHex.9', '43414348455f4e4557'],
    'current truncated prefix would match' => ['currentTruncatedPrefixWouldMatchRowids', [1, 2, 3, 4, 6]],
    'next truncated prefix would match' => ['nextTruncatedPrefixWouldMatchRowids', [1, 2, 3, 4, 6, 9, 10]],
    'current truncated prefix false positives' => ['currentTruncatedPrefixFalsePositiveRowids', [4]],
    'next truncated prefix false positives' => ['nextTruncatedPrefixFalsePositiveRowids', [4]],
    'current malformed' => ['currentMalformedRowids', [8]],
    'next malformed' => ['nextMalformedRowids', [11]],
    'current error' => ['currentErrors.8', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'next error' => ['nextErrors.11', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor reusable' => ['cursorReusable', false],
    'embedded nul flag' => ['embeddedNulDoesNotTerminateText', true],
    'residual sees after nul' => ['likeResidualSeesBytesAfterNul', true],
    'rtrim flag' => ['rtrimTrimsOnlyAsciiSpaceAfterNulAwareDecode', true],
    'nocase flag' => ['nocaseFoldsAsciiOnlyAcrossNul', true],
    'dependency decode' => ['dependencies.0', 'sqlite-utf16-decode'],
    'dependency range' => ['dependencies.1', 'sqlite-like-nocase-prefix-range'],
    'dependency rtrim' => ['dependencies.2', 'sqlite-rtrim-expression-key'],
    'dependency nul' => ['dependencies.3', 'sqlite-embedded-nul-text'],
    'dependency source' => ['dependencies.4', 'sqlite-current-source-nexttwoOneZero'],
];

foreach ($cases210 as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim current source nextTwoOneZero ' . $name] = static function (TestRunner $t) use ($plan210, $valueAt210, $path, $expected): void {
        $t->same($expected, $valueAt210($plan210(), $path));
    };
}

$tests['utf16 nocase like rtrim current source nextTwoOneZero invalidation reasons include nul rowsets'] = static function (TestRunner $t) use ($plan210): void {
    $t->same([
        'source-name',
        'schema-cookie',
        'malformed-text',
        'matched-rowset',
        'embedded-nul-rowset',
        'embedded-nul-matched-rowset',
    ], $plan210()['invalidationReasons']);
};

$tests['utf16 nocase like rtrim current source nextTwoOneZero stable embedded nul cursor can be reused'] = static function (TestRunner $t) use ($row210): void {
    $rows = [
        $row210(1, "Plugin\0Cache  ", 'UTF-16LE'),
        $row210(2, "plugin\0cache_more", 'UTF-16BE'),
        $row210(3, "plugin\0other", 'UTF-8'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyEmbeddedNulPlan(
        $rows,
        $rows,
        "plugin\0cache%",
        null,
        'stable',
        'stable',
        210,
        210,
    );

    $t->same([1, 2], $result['currentMatchedRowids']);
    $t->same([1, 2], $result['nextMatchedRowids']);
    $t->same([1, 2, 3], $result['currentEmbeddedNulRowids']);
    $t->same([3], $result['currentTruncatedPrefixFalsePositiveRowids']);
    $t->same([], $result['invalidationReasons']);
    $t->same(true, $result['cursorReusable']);
};

$tests['utf16 nocase like rtrim current source nextTwoOneZero rejects pattern without nul'] = static function (TestRunner $t) use ($current210, $nextTwoOneZero): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyEmbeddedNulPlan(
        $current210,
        $nextTwoOneZero,
        'plugin%',
    ));
};

$tests['utf16 nocase like rtrim current source nextTwoOneZero rejects invalid row shape'] = static function (TestRunner $t) use ($row210): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyEmbeddedNulPlan(
        [['setting_id' => '1', 'key_name_bytes' => 'plugin', 'text_encoding' => 1]],
        [$row210(1, "plugin\0cache", 'UTF-8')],
    ));
};

return $tests;
