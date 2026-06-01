<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$tests = [];

$enc230 = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row230 = static fn (int $id, string $name, int|string $encoding): array => [
    'setting_id' => $id,
    'key_name_bytes' => $enc230($name, $encoding),
    'text_encoding' => match ($encoding) {
        'UTF-8', 1 => 1,
        'UTF-16LE', 2 => 2,
        'UTF-16BE', 3 => 3,
    },
];
$bad230 = static fn (int $id, string $bytes, int $encoding): array => [
    'setting_id' => $id,
    'key_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$current230 = [
    $row230(1, 'module_cache', 'UTF-16LE'),
    $row230(2, "Module_Cache\n", 'UTF-16BE'),
    $row230(3, "module_cache\r", 'UTF-16LE'),
    $row230(4, "module_cache\f", 'UTF-16BE'),
    $row230(5, 'MODULE_CACHE ', 'UTF-8'),
    $row230(6, 'module_cachex', 'UTF-16LE'),
    $row230(7, 'layout_cache', 'UTF-16BE'),
    $bad230(8, "\x00\xd8", 2),
];
$nextTwoThreeZero = [
    $row230(1, 'module_cache ', 'UTF-16BE'),
    $row230(2, 'Module_Cache', 'UTF-16LE'),
    $row230(3, "module_cache\n", 'UTF-16BE'),
    $row230(4, "module_cache\f", 'UTF-16LE'),
    $row230(5, 'MODULE_CACHE ', 'UTF-8'),
    $row230(6, 'module_cachex', 'UTF-16BE'),
    $row230(9, 'module_cache', 'UTF-16LE'),
    $bad230(10, "\x00\xd8", 2),
];

$plan230 = static fn (
    ?array $current = null,
    ?array $next = null,
    string $pattern = 'module_cache',
    ?string $escape = null,
    string $currentSource = 'main.app_settings@229',
    string $nextSource = 'main.app_settings@230',
    int $currentCookie = 229,
    int $nextCookie = 230,
): array => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyLineBreakBoundaryPlan(
    $current ?? $current230,
    $next ?? $nextTwoThreeZero,
    $pattern,
    $escape,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt230 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases230 = [
    'status' => ['status', 'utf16-nocase-like-rtrim-current-source-nexttwoThreeZero'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(key_name) COLLATE NOCASE LIKE ? /* line-break RTRIM boundary */'],
    'pattern' => ['pattern', 'module_cache'],
    'escape' => ['escape', null],
    'collation' => ['collation', 'NOCASE'],
    'current source' => ['currentSource', 'main.app_settings@229'],
    'next source' => ['nextSource', 'main.app_settings@230'],
    'current cookie' => ['currentSchemaCookie', 229],
    'next cookie' => ['nextSchemaCookie', 230],
    'prefix' => ['prefix', 'module'],
    'range lower' => ['rangeLowerInclusive', 'module'],
    'range upper' => ['rangeUpperBound', 'modulf'],
    'index usable' => ['indexUsable', true],
    'current candidates' => ['currentCandidateRowids', [1, 5, 2, 4, 3, 6]],
    'next candidates' => ['nextCandidateRowids', [1, 2, 5, 9, 3, 4, 6]],
    'current matched' => ['currentMatchedRowids', [1, 5]],
    'next matched' => ['nextMatchedRowids', [1, 2, 5, 9]],
    'matched retained' => ['matchedRetainedRowids', [1, 5]],
    'matched exited' => ['matchedExitedRowids', []],
    'matched entered' => ['matchedEnteredRowids', [2, 9]],
    'current false positives' => ['currentFalsePositiveRowids', [2, 4, 3, 6]],
    'next false positives' => ['nextFalsePositiveRowids', [3, 4, 6]],
    'current ascii spaces' => ['currentAsciiSpaceSuffixRowids', [5]],
    'next ascii spaces' => ['nextAsciiSpaceSuffixRowids', [1, 5]],
    'current line breaks' => ['currentLineBreakSuffixRowids', [2, 3]],
    'next line breaks' => ['nextLineBreakSuffixRowids', [3]],
    'current form feeds' => ['currentFormFeedSuffixRowids', [4]],
    'next form feeds' => ['nextFormFeedSuffixRowids', [4]],
    'current malformed' => ['currentMalformedRowids', [8]],
    'next malformed' => ['nextMalformedRowids', [10]],
    'current error' => ['currentErrors.8', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'next error' => ['nextErrors.10', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'current row two decoded' => ['currentDecodedTexts.2', "Module_Cache\n"],
    'current row two rtrim keeps newline' => ['currentRtrimTexts.2', "Module_Cache\n"],
    'current row three rtrim keeps carriage return' => ['currentRtrimTexts.3', "module_cache\r"],
    'current row four rtrim keeps form feed' => ['currentRtrimTexts.4', "module_cache\f"],
    'next row one rtrim trims ascii space' => ['nextRtrimTexts.1', 'module_cache'],
    'next row two rtrim exact' => ['nextRtrimTexts.2', 'Module_Cache'],
    'current row two key keeps newline' => ['currentNocaseKeys.2', "module_cache\n"],
    'current row three key keeps carriage return' => ['currentNocaseKeys.3', "module_cache\r"],
    'next row two key exact' => ['nextNocaseKeys.2', 'module_cache'],
    'current row two suffix' => ['currentSuffixClasses.2', 'line-break'],
    'current row four suffix' => ['currentSuffixClasses.4', 'form-feed'],
    'next row two suffix' => ['nextSuffixClasses.2', 'none'],
    'current newline residual' => ['currentResidualMatches.2', false],
    'current carriage return residual' => ['currentResidualMatches.3', false],
    'current form feed residual' => ['currentResidualMatches.4', false],
    'next row two residual' => ['nextResidualMatches.2', true],
    'next row three residual' => ['nextResidualMatches.3', false],
    'changed text' => ['changedTextRowids', [1, 2, 3]],
    'changed rtrim' => ['changedRtrimRowids', [2, 3]],
    'changed nocase' => ['changedNocaseKeyRowids', [2, 3]],
    'changed line break class' => ['changedLineBreakClassRowids', [1, 2]],
    'changed residual' => ['changedResidualRowids', [2]],
    'invalidated' => ['cursorInvalidated', true],
    'not reusable' => ['cursorReusable', false],
    'ascii space flag' => ['asciiSpaceSuffixMatchesAfterRtrim', true],
    'line break flag' => ['lineBreakSuffixDoesNotRtrim', true],
    'form feed flag' => ['formFeedSuffixDoesNotRtrim', true],
    'nocase ascii' => ['nocaseFoldsAsciiOnly', true],
    'rtrim ascii' => ['rtrimTrimsOnlyAsciiSpace', true],
    'dependency decode' => ['dependencies.0', 'sqlite-utf16-decode'],
    'dependency range' => ['dependencies.1', 'sqlite-like-nocase-prefix-range'],
    'dependency rtrim' => ['dependencies.2', 'sqlite-rtrim-expression-key'],
    'dependency boundary' => ['dependencies.3', 'sqlite-line-break-rtrim-boundary'],
    'dependency source' => ['dependencies.4', 'sqlite-current-source-nexttwoThreeZero'],
];

foreach ($cases230 as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim current source nextTwoThreeZero ' . $name] = static function (TestRunner $t) use ($plan230, $valueAt230, $path, $expected): void {
        $t->same($expected, $valueAt230($plan230(), $path));
    };
}

$tests['utf16 nocase like rtrim current source nextTwoThreeZero invalidation reason order'] = static function (TestRunner $t) use ($plan230): void {
    $t->same([
        'source-name',
        'schema-cookie',
        'decoded-text',
        'rtrim-expression',
        'nocase-key',
        'line-break-suffix',
        'residual-result',
        'malformed-text',
        'candidate-rowset',
        'matched-rowset',
        'non-space-rtrim-line-boundary',
    ], $plan230()['invalidationReasons']);
};

$tests['utf16 nocase like rtrim current source nextTwoThreeZero stable line break false positive is reusable after residual recheck'] = static function (TestRunner $t) use ($row230): void {
    $rows = [
        $row230(1, 'module_cache', 'UTF-16LE'),
        $row230(2, "module_cache\n", 'UTF-16BE'),
        $row230(3, 'MODULE_CACHE ', 'UTF-8'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyLineBreakBoundaryPlan(
        $rows,
        $rows,
        'module_cache',
        null,
        'stable',
        'stable',
        230,
        230,
    );

    $t->same([1, 3], $result['currentMatchedRowids']);
    $t->same([2], $result['currentFalsePositiveRowids']);
    $t->same([], $result['invalidationReasons']);
    $t->same(true, $result['cursorReusable']);
};

$tests['utf16 nocase like rtrim current source nextTwoThreeZero escaped underscore still treats newline as significant'] = static function (TestRunner $t) use ($row230): void {
    $rows = [
        $row230(1, 'module_cache', 'UTF-16LE'),
        $row230(2, "module_cache\r", 'UTF-16LE'),
        $row230(3, 'moduleXcache', 'UTF-16BE'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyLineBreakBoundaryPlan(
        $rows,
        $rows,
        'module!_cache',
        '!',
        'stable',
        'stable',
        230,
        230,
    );

    $t->same([1], $result['currentMatchedRowids']);
    $t->same([2], $result['currentFalsePositiveRowids']);
    $t->same([], $result['invalidationReasons']);
};

$tests['utf16 nocase like rtrim current source nextTwoThreeZero rejects malformed row shape'] = static function (TestRunner $t) use ($enc230): void {
    $rows = [['setting_id' => 1, 'key_name_bytes' => $enc230('module_cache', 'UTF-16LE')]];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyLineBreakBoundaryPlan($rows, $rows));
};

return $tests;
