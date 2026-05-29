<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan;
use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;

$tests = [];

$enc257 = static fn (string $text, int $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$text257 = static fn (int $id, string $name, int $encoding): array => [
    'option_id' => $id,
    'storage' => 'text',
    'option_name_bytes' => $enc257($name, $encoding),
    'name_encoding' => $encoding,
];
$numeric257 = static fn (int $id, int|float $name, string $storage): array => [
    'option_id' => $id,
    'storage' => $storage,
    'option_name' => $name,
];
$blob257 = static fn (int $id, string $bytes): array => [
    'option_id' => $id,
    'storage' => 'blob',
    'option_name_bytes' => $bytes,
];
$null257 = static fn (int $id): array => [
    'option_id' => $id,
    'storage' => 'null',
    'option_name' => null,
];
$bad257 = static fn (int $id, string $bytes, int $encoding): array => [
    'option_id' => $id,
    'storage' => 'text',
    'option_name_bytes' => $bytes,
    'name_encoding' => $encoding,
];

$current257 = [
    $text257(1, '2024_cache', 2),
    $text257(2, '2024-Import', 3),
    $text257(3, '2023_cache', 1),
    $numeric257(4, 2024, 'integer'),
    $numeric257(5, 2024.5, 'real'),
    $numeric257(6, 12024, 'integer'),
    $text257(7, '2024é', 2),
    $blob257(8, '2024_blob'),
    $null257(9),
    $bad257(10, "\x00\xd8", 2),
];
$nextTwoFiveSeven = [
    $text257(1, '2024_cache', 3),
    $numeric257(2, 2024, 'integer'),
    $text257(3, '2024_cache_rebuilt', 2),
    $text257(4, '2024', 1),
    $numeric257(5, 2025.5, 'real'),
    $numeric257(6, 2024, 'integer'),
    $text257(7, '2024É', 3),
    $numeric257(11, 2024.75, 'real'),
    $blob257(12, '2024_blob'),
    $bad257(13, "\x00\xd8", 2),
];

$plan257 = static fn (
    ?array $current = null,
    ?array $next = null,
    string $pattern = '2024%',
    ?string $escape = null,
    string $currentSource = 'main.wp_options@256',
    string $nextSource = 'main.wp_options@257',
    int $currentCookie = 256,
    int $nextCookie = 257,
): array => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressOptionNameNumericAffinityLikePlan(
    $current ?? $current257,
    $next ?? $nextTwoFiveSeven,
    $pattern,
    $escape,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt257 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases257 = [
    'status' => ['status', 'encoding-collation-affinity-like-current-source-nexttwoFiveSeven'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'option_name COLLATE NOCASE LIKE ? /* NUMERIC storage coerced through TEXT affinity */'],
    'pattern' => ['pattern', '2024%'],
    'escape' => ['escape', null],
    'collation' => ['collation', 'NOCASE'],
    'affinity' => ['affinity', 'TEXT-for-LIKE'],
    'current source' => ['currentSource', 'main.wp_options@256'],
    'next source' => ['nextSource', 'main.wp_options@257'],
    'current cookie' => ['currentSchemaCookie', 256],
    'next cookie' => ['nextSchemaCookie', 257],
    'prefix' => ['prefix', '2024'],
    'range lower' => ['range.lowerInclusive', '2024'],
    'range upper' => ['range.upperBound', '2025'],
    'index usable' => ['indexUsable', true],
    'rejected reason' => ['rejectedReason', null],
    'current candidates' => ['currentCandidateRowids', [4, 2, 5, 1, 7]],
    'next candidates' => ['nextCandidateRowids', [2, 4, 6, 11, 1, 3, 7]],
    'current matched' => ['currentMatchedRowids', [4, 2, 5, 1, 7]],
    'next matched' => ['nextMatchedRowids', [2, 4, 6, 11, 1, 3, 7]],
    'matched retained' => ['matchedRetainedRowids', [1, 2, 4, 7]],
    'matched exited' => ['matchedExitedRowids', [5]],
    'matched entered' => ['matchedEnteredRowids', [3, 6, 11]],
    'current false positives' => ['currentFalsePositiveRowids', []],
    'next false positives' => ['nextFalsePositiveRowids', []],
    'current malformed' => ['currentMalformedRowids', [8, 9, 10]],
    'next malformed' => ['nextMalformedRowids', [12, 13]],
    'blob error' => ['currentErrors.8', 'SQLite TEXT affinity LIKE does not coerce BLOB option_name bytes'],
    'null error' => ['currentErrors.9', 'SQLite LIKE over NULL option_name remains unknown'],
    'malformed current error' => ['currentErrors.10', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'malformed next error' => ['nextErrors.13', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'row one current storage' => ['currentStorageClasses.1', 'text'],
    'row four current storage' => ['currentStorageClasses.4', 'integer'],
    'row five current storage' => ['currentStorageClasses.5', 'real'],
    'row two next storage' => ['nextStorageClasses.2', 'integer'],
    'row one current text' => ['currentTextValues.1', '2024_cache'],
    'row two next text' => ['nextTextValues.2', '2024'],
    'row four current numeric text' => ['currentTextValues.4', '2024'],
    'row five current real text' => ['currentTextValues.5', '2024.5'],
    'row five next real text' => ['nextTextValues.5', '2025.5'],
    'row seven current text' => ['currentTextValues.7', '2024é'],
    'row seven next text' => ['nextTextValues.7', '2024É'],
    'row seven nocase remains ascii only current' => ['currentNocaseKeys.7', '2024é'],
    'row seven nocase remains ascii only next' => ['nextNocaseKeys.7', '2024É'],
    'row one current encoding' => ['currentEncodingNames.1', 'UTF-16LE'],
    'row one next encoding' => ['nextEncodingNames.1', 'UTF-16BE'],
    'row four current encoding null' => ['currentEncodingNames.4', null],
    'row one current residual' => ['currentResidualMatches.1', true],
    'row five next residual false' => ['nextResidualMatches.5', false],
    'row six current absent' => ['currentTextValues.6', '12024'],
    'changed text' => ['changedTextRowids', [2, 3, 5, 6, 7, 11]],
    'changed storage' => ['changedStorageRowids', [2, 4, 11]],
    'changed bytes' => ['changedBytesRowids', [1, 2, 3, 4, 7]],
    'changed encoding' => ['changedEncodingRowids', [1, 2, 3, 4, 7]],
    'changed nocase' => ['changedNocaseKeyRowids', [2, 3, 5, 6, 7, 11]],
    'changed residual' => ['changedResidualRowids', [3, 5, 6, 11]],
    'numeric storage flag' => ['numericStorageCoercedBeforeLike', true],
    'blob null flag' => ['blobAndNullRemainOutsideLikeCursor', true],
    'nocase flag' => ['nocaseFoldsAsciiOnly', true],
    'invalidated' => ['cursorInvalidated', true],
    'not reusable' => ['cursorReusable', false],
    'dependency range' => ['dependencies.0', 'sqlite-like-nocase-prefix-range'],
    'dependency affinity' => ['dependencies.1', 'sqlite-text-affinity'],
    'dependency utf16' => ['dependencies.2', 'sqlite-utf16-decode'],
    'dependency source' => ['dependencies.3', 'sqlite-current-source-nexttwoFiveSeven'],
];

foreach ($cases257 as $name => [$path, $expected]) {
    $tests['encoding collation affinity like current source nextTwoFiveSeven ' . $name] = static function (TestRunner $t) use ($plan257, $valueAt257, $path, $expected): void {
        $t->same($expected, $valueAt257($plan257(), $path));
    };
}

$tests['encoding collation affinity like current source nextTwoFiveSeven invalidation reason order'] = static function (TestRunner $t) use ($plan257): void {
    $t->same([
        'source-name',
        'schema-cookie',
        'name-affinity',
        'storage-class',
        'encoded-bytes',
        'encoding',
        'nocase-key',
        'residual-result',
        'malformed-text',
        'candidate-rowset',
        'matched-rowset',
    ], $plan257()['invalidationReasons']);
};

$tests['encoding collation affinity like current source nextTwoFiveSeven stable numeric cursor is reusable'] = static function (TestRunner $t) use ($numeric257, $text257): void {
    $rows = [$numeric257(1, 2024, 'integer'), $text257(2, '2024_cache', 1)];
    $result = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressOptionNameNumericAffinityLikePlan($rows, $rows, '2024%', null, 'stable', 'stable', 9, 9);

    $t->same([1, 2], $result['currentMatchedRowids']);
    $t->same(false, $result['cursorInvalidated']);
    $t->same(true, $result['cursorReusable']);
    $t->same([], $result['invalidationReasons']);
};

$tests['encoding collation affinity like current source nextTwoFiveSeven escaped underscore keeps numeric prefix'] = static function (TestRunner $t) use ($text257, $numeric257): void {
    $rows = [$text257(1, '2024_cache', 1), $text257(2, '2024-cache', 1), $numeric257(3, 2024, 'integer')];
    $result = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressOptionNameNumericAffinityLikePlan($rows, $rows, '2024!_%', '!', 'stable', 'stable', 9, 9);

    $t->same('2024_', $result['prefix']);
    $t->same([1], $result['currentMatchedRowids']);
    $t->same([1], $result['currentCandidateRowids']);
};

$tests['encoding collation affinity like current source nextTwoFiveSeven non ascii prefix disables nocase range'] = static function (TestRunner $t) use ($text257): void {
    $rows = [$text257(1, 'é2024', 2), $text257(2, 'É2024', 3)];
    $result = SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressOptionNameNumericAffinityLikePlan($rows, $rows, 'é%', null, 'stable', 'stable', 9, 9);

    $t->same(false, $result['indexUsable']);
    $t->same('nocase_like_prefix_must_be_ascii_for_range', $result['rejectedReason']);
    $t->same([], $result['currentCandidateRowids']);
};

$tests['encoding collation affinity like current source nextTwoFiveSeven rejects missing storage'] = static function (TestRunner $t): void {
    $rows = [['option_id' => 1]];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingCollationAffinityLikeCurrentSourceNextPlan::wordpressOptionNameNumericAffinityLikePlan($rows, $rows));
};

return $tests;
