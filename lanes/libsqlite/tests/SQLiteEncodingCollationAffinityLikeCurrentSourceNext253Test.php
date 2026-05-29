<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;

$tests = [];

$enc253 = static fn (string $text, int $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$text253 = static fn (int $id, string $value, int $encoding): array => [
    'option_id' => $id,
    'storage' => 'text',
    'option_value_bytes' => $enc253($value, $encoding),
    'value_encoding' => $encoding,
];
$scalar253 = static fn (int $id, int|float|null $value, string $storage): array => [
    'option_id' => $id,
    'storage' => $storage,
    'option_value' => $value,
];
$blob253 = static fn (int $id, string $bytes): array => [
    'option_id' => $id,
    'storage' => 'blob',
    'option_value_bytes' => $bytes,
];
$bad253 = static fn (int $id, string $bytes, int $encoding): array => [
    'option_id' => $id,
    'storage' => 'text',
    'option_value_bytes' => $bytes,
    'value_encoding' => $encoding,
];

$current253 = [
    $text253(1, 'yes', 2),
    $text253(2, 'YES-cache', 3),
    $text253(3, 'YeS plugin', 1),
    $text253(4, 'yesterday', 2),
    $text253(5, 'no', 2),
    $text253(6, 'yes' . "\u{00e9}", 3),
    $scalar253(7, 1, 'integer'),
    $scalar253(8, 1.5, 'real'),
    $blob253(9, 'yes-blob'),
    $bad253(10, "\x00\xd8", 2),
];
$nextTwoFiveThree = [
    $text253(1, 'YES', 3),
    $text253(2, 'no-cache', 2),
    $text253(3, 'YeS plugin', 1),
    $text253(4, 'yesterday-imported', 3),
    $text253(5, 'YES-new', 2),
    $text253(6, 'yes' . "\u{00e9}", 2),
    $scalar253(7, 1, 'integer'),
    $scalar253(8, 1.5, 'real'),
    $text253(11, 'yes-later', 1),
    $bad253(12, "\x00\xd8", 2),
];

$plan253 = static fn (
    ?array $current = null,
    ?array $next = null,
    string $pattern = 'yes%',
    ?string $escape = null,
    string $currentSource = 'main.wp_options@252',
    string $nextSource = 'main.wp_options@253',
    int $currentCookie = 252,
    int $nextCookie = 253,
): array => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressAutoloadValuePlan(
    $current ?? $current253,
    $next ?? $nextTwoFiveThree,
    $pattern,
    $escape,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt253 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases253 = [
    'status' => ['status', 'encoding-collation-affinity-like-current-source-nexttwoFiveThree'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'option_value COLLATE NOCASE LIKE ? /* TEXT affinity cursor */'],
    'pattern' => ['pattern', 'yes%'],
    'escape' => ['escape', null],
    'collation' => ['collation', 'NOCASE'],
    'affinity' => ['affinity', 'TEXT'],
    'current source' => ['currentSource', 'main.wp_options@252'],
    'next source' => ['nextSource', 'main.wp_options@253'],
    'current cookie' => ['currentSchemaCookie', 252],
    'next cookie' => ['nextSchemaCookie', 253],
    'prefix' => ['prefix', 'yes'],
    'range lower' => ['range.lowerInclusive', 'yes'],
    'range upper' => ['range.upperBound', 'yet'],
    'index usable' => ['indexUsable', true],
    'rejected reason' => ['rejectedReason', null],
    'current candidates' => ['currentCandidateRowids', [1, 3, 2, 4, 6]],
    'next candidates' => ['nextCandidateRowids', [1, 3, 11, 5, 4, 6]],
    'current matched' => ['currentMatchedRowids', [1, 3, 2, 4, 6]],
    'next matched' => ['nextMatchedRowids', [1, 3, 11, 5, 4, 6]],
    'matched retained' => ['matchedRetainedRowids', [1, 3, 4, 6]],
    'matched exited' => ['matchedExitedRowids', [2]],
    'matched entered' => ['matchedEnteredRowids', [5, 11]],
    'current false positives' => ['currentFalsePositiveRowids', []],
    'next false positives' => ['nextFalsePositiveRowids', []],
    'current malformed' => ['currentMalformedRowids', [9, 10]],
    'next malformed' => ['nextMalformedRowids', [12]],
    'blob error' => ['currentErrors.9', 'SQLite TEXT affinity LIKE does not coerce BLOB option_value bytes'],
    'malformed utf16 error' => ['currentErrors.10', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'next malformed utf16 error' => ['nextErrors.12', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'row one current storage' => ['currentStorageClasses.1', 'text'],
    'row seven current storage' => ['currentStorageClasses.7', 'integer'],
    'row eight current storage' => ['currentStorageClasses.8', 'real'],
    'row one current text' => ['currentTextValues.1', 'yes'],
    'row one next text' => ['nextTextValues.1', 'YES'],
    'row two current text' => ['currentTextValues.2', 'YES-cache'],
    'row two next text' => ['nextTextValues.2', 'no-cache'],
    'row seven text affinity' => ['currentTextValues.7', '1'],
    'row eight text affinity' => ['currentTextValues.8', '1.5'],
    'row one current nocase' => ['currentNocaseKeys.1', 'yes'],
    'row one next nocase' => ['nextNocaseKeys.1', 'yes'],
    'row six nocase keeps non ascii' => ['currentNocaseKeys.6', 'yes' . "\u{00e9}"],
    'row one current encoding' => ['currentEncodingNames.1', 'UTF-16LE'],
    'row one next encoding' => ['nextEncodingNames.1', 'UTF-16BE'],
    'row seven encoding null' => ['currentEncodingNames.7', null],
    'row one current residual' => ['currentResidualMatches.1', true],
    'row two next residual false' => ['nextResidualMatches.2', false],
    'row seven residual false' => ['currentResidualMatches.7', false],
    'changed text' => ['changedTextRowids', [1, 2, 4, 5, 11]],
    'changed affinity' => ['changedTextAffinityRowids', [1, 2, 4, 5, 11]],
    'changed storage' => ['changedStorageRowids', [11]],
    'changed bytes' => ['changedBytesRowids', [1, 2, 4, 5, 6, 11]],
    'changed encoding' => ['changedEncodingRowids', [1, 2, 4, 6, 11]],
    'changed nocase' => ['changedNocaseKeyRowids', [2, 4, 5, 11]],
    'changed residual' => ['changedResidualRowids', [2, 5, 11]],
    'text affinity flag' => ['textAffinityAppliedBeforeLike', true],
    'blob flag' => ['blobValuesDoNotMatchTextLike', true],
    'nocase ascii flag' => ['nocaseFoldsAsciiOnly', true],
    'invalidated' => ['cursorInvalidated', true],
    'not reusable' => ['cursorReusable', false],
    'dependency decode' => ['dependencies.0', 'sqlite-utf16-decode'],
    'dependency affinity' => ['dependencies.1', 'sqlite-text-affinity'],
    'dependency range' => ['dependencies.2', 'sqlite-like-nocase-prefix-range'],
    'dependency source' => ['dependencies.3', 'sqlite-current-source-nexttwoFiveThree'],
];

foreach ($cases253 as $name => [$path, $expected]) {
    $tests['encoding collation affinity like current source nextTwoFiveThree ' . $name] = static function (TestRunner $t) use ($plan253, $valueAt253, $path, $expected): void {
        $t->same($expected, $valueAt253($plan253(), $path));
    };
}

$tests['encoding collation affinity like current source nextTwoFiveThree invalidation reason order'] = static function (TestRunner $t) use ($plan253): void {
    $t->same([
        'source-name',
        'schema-cookie',
        'decoded-text',
        'text-affinity',
        'storage-class',
        'encoded-bytes',
        'encoding',
        'nocase-key',
        'residual-result',
        'malformed-text',
        'matched-rowset',
        'candidate-rowset',
    ], $plan253()['invalidationReasons']);
};

$tests['encoding collation affinity like current source nextTwoFiveThree stable text and scalar cursor is reusable'] = static function (TestRunner $t) use ($text253, $scalar253): void {
    $rows = [$text253(1, 'yes', 2), $scalar253(2, 10, 'integer')];
    $result = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressAutoloadValuePlan($rows, $rows, 'yes%', null, 'stable', 'stable', 7, 7);

    $t->same([1], $result['currentMatchedRowids']);
    $t->same(false, $result['cursorInvalidated']);
    $t->same(true, $result['cursorReusable']);
    $t->same([], $result['invalidationReasons']);
};

$tests['encoding collation affinity like current source nextTwoFiveThree escaped literal percent narrows matches'] = static function (TestRunner $t) use ($text253): void {
    $rows = [$text253(1, 'yes%literal', 2), $text253(2, 'yes-cache', 2)];
    $result = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressAutoloadValuePlan($rows, $rows, 'yes!%%', '!', 'stable', 'stable', 7, 7);

    $t->same('yes%', $result['prefix']);
    $t->same([1], $result['currentMatchedRowids']);
    $t->same([1], $result['currentCandidateRowids']);
};

$tests['encoding collation affinity like current source nextTwoFiveThree non ascii prefix disables nocase range'] = static function (TestRunner $t) use ($text253): void {
    $rows = [$text253(1, 'éyes', 2), $text253(2, 'Éyes', 3)];
    $result = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressAutoloadValuePlan($rows, $rows, 'éy%', null, 'stable', 'stable', 7, 7);

    $t->same(false, $result['indexUsable']);
    $t->same('nocase_like_prefix_must_be_ascii_for_range', $result['rejectedReason']);
    $t->same([], $result['currentCandidateRowids']);
};

$tests['encoding collation affinity like current source nextTwoFiveThree rejects missing storage'] = static function (TestRunner $t): void {
    $rows = [['option_id' => 1]];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressAutoloadValuePlan($rows, $rows));
};

return $tests;
