<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$tests = [];

$enc219 = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row219 = static fn (int $id, string $name, int|string $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc219($name, $encoding),
    'text_encoding' => match ($encoding) {
        'UTF-8', 1 => 1,
        'UTF-16LE', 2 => 2,
        'UTF-16BE', 3 => 3,
    },
];
$bad219 = static fn (int $id, string $bytes, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$emoji219 = "\xf0\x9f\x98\x80";
$rocket219 = "\xf0\x9f\x9a\x80";
$currentRows219 = [
    $row219(1, 'plugin_cacheA', 'UTF-16LE'),
    $row219(2, 'Plugin_Cache' . $emoji219, 'UTF-16BE'),
    $row219(3, 'plugin_cache' . $emoji219 . 'x', 'UTF-16LE'),
    $row219(4, 'plugin_cache  ', 'UTF-16BE'),
    $row219(5, 'plugin_cache', 'UTF-8'),
    $row219(6, 'plugin_cache' . $rocket219, 'UTF-16LE'),
    $row219(7, 'plugin_cacheAB', 'UTF-16BE'),
    $row219(8, 'theme_cache' . $emoji219, 'UTF-16LE'),
    $bad219(9, "\x00\xd8", 2),
];
$nextRows219 = [
    $row219(1, 'plugin_cacheA', 'UTF-16BE'),
    $row219(2, 'Plugin_Cache' . $emoji219, 'UTF-16LE'),
    $row219(3, 'plugin_cache' . $emoji219, 'UTF-16BE'),
    $row219(4, 'plugin_cache' . "\t", 'UTF-16LE'),
    $row219(5, 'plugin_cacheZ', 'UTF-8'),
    $row219(6, 'plugin_cache' . $rocket219, 'UTF-16BE'),
    $row219(10, 'PLUGIN_CACHE' . $emoji219, 'UTF-16LE'),
    $row219(11, 'plugin_cache' . $emoji219 . 'x', 'UTF-16LE'),
    $bad219(12, "\x00\xd8", 2),
];

$plan219 = static fn (
    ?array $current = null,
    ?array $next = null,
    string $pattern = 'plugin!_cache_',
    ?string $escape = '!',
    string $currentSource = 'main.wp_options@218',
    string $nextSource = 'main.wp_options@219',
    int $currentCookie = 218,
    int $nextCookie = 219,
): array => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNameSupplementaryWildcardPlan(
    $current ?? $currentRows219,
    $next ?? $nextRows219,
    $pattern,
    $escape,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt219 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases219 = [
    'status' => ['status', 'utf16-nocase-like-rtrim-current-source-nexttwoOneNine'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* supplementary wildcard */'],
    'pattern' => ['pattern', 'plugin!_cache_'],
    'escape' => ['escape', '!'],
    'collation' => ['collation', 'NOCASE'],
    'current source' => ['currentSource', 'main.wp_options@218'],
    'next source' => ['nextSource', 'main.wp_options@219'],
    'current cookie' => ['currentSchemaCookie', 218],
    'next cookie' => ['nextSchemaCookie', 219],
    'prefix' => ['prefix', 'plugin_cache'],
    'range lower' => ['rangeLowerInclusive', 'plugin_cache'],
    'range upper' => ['rangeUpperBound', 'plugin_cachf'],
    'index usable' => ['indexUsable', true],
    'current candidates' => ['currentCandidateRowids', [4, 5, 1, 7, 2, 3, 6]],
    'next candidates' => ['nextCandidateRowids', [4, 1, 5, 2, 3, 10, 11, 6]],
    'current matched' => ['currentMatchedRowids', [1, 2, 6]],
    'next matched' => ['nextMatchedRowids', [4, 1, 5, 2, 3, 10, 6]],
    'matched retained' => ['matchedRetainedRowids', [1, 2, 6]],
    'matched exited' => ['matchedExitedRowids', []],
    'matched entered' => ['matchedEnteredRowids', [3, 4, 5, 10]],
    'current false positives' => ['currentFalsePositiveRowids', [4, 5, 7, 3]],
    'next false positives' => ['nextFalsePositiveRowids', [11]],
    'current code unit traps' => ['currentCodeUnitWildcardTrapRowids', [2, 6]],
    'next code unit traps' => ['nextCodeUnitWildcardTrapRowids', [2, 3, 6, 10]],
    'current supplementary rowids' => ['currentSupplementaryRowids', [2, 3, 6, 8]],
    'next supplementary rowids' => ['nextSupplementaryRowids', [2, 3, 6, 10, 11]],
    'current malformed' => ['currentMalformedRowids', [9]],
    'next malformed' => ['nextMalformedRowids', [12]],
    'current error' => ['currentErrors.9', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'next error' => ['nextErrors.12', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'current row two rtrim' => ['currentRtrimTexts.2', 'Plugin_Cache' . $emoji219],
    'next row three rtrim' => ['nextRtrimTexts.3', 'plugin_cache' . $emoji219],
    'row two nocase key' => ['currentNocaseKeys.2', 'plugin_cache' . $emoji219],
    'row ten nocase key' => ['nextNocaseKeys.10', 'plugin_cache' . $emoji219],
    'row two character count' => ['currentCharacterCounts.2', 13],
    'row two utf16 units' => ['currentUtf16CodeUnitCounts.2', 14],
    'row two supplementary count' => ['currentSupplementaryCounts.2', 1],
    'row seven character count' => ['currentCharacterCounts.7', 14],
    'row seven utf16 units' => ['currentUtf16CodeUnitCounts.7', 14],
    'row three next residual' => ['nextResidualMatches.3', true],
    'row eleven next residual' => ['nextResidualMatches.11', false],
    'row three next trap' => ['nextCodeUnitTrapMatches.3', true],
    'row one current trap false' => ['currentCodeUnitTrapMatches.1', false],
    'changed text' => ['changedTextRowids', [3, 4, 5]],
    'changed rtrim' => ['changedRtrimRowids', [3, 4, 5]],
    'changed nocase' => ['changedNocaseKeyRowids', [3, 4, 5]],
    'changed supplementary' => ['changedSupplementaryRowids', []],
    'changed utf16 units' => ['changedUtf16CodeUnitRowids', [3, 4, 5]],
    'changed residual' => ['changedResidualRowids', [3, 4, 5]],
    'invalidated' => ['cursorInvalidated', true],
    'not reusable' => ['cursorReusable', false],
    'underscore character flag' => ['likeUnderscoreConsumesUnicodeCharacter', true],
    'surrogate pair flag' => ['utf16SurrogatePairIsOneLikeCharacter', true],
    'rtrim ascii' => ['rtrimTrimsOnlyAsciiSpace', true],
    'nocase ascii' => ['nocaseFoldsAsciiOnly', true],
    'dependency decode' => ['dependencies.0', 'sqlite-utf16-decode'],
    'dependency range' => ['dependencies.1', 'sqlite-like-nocase-prefix-range'],
    'dependency rtrim' => ['dependencies.2', 'sqlite-rtrim-expression-key'],
    'dependency supplementary' => ['dependencies.3', 'sqlite-supplementary-plane-like-character'],
    'dependency source' => ['dependencies.4', 'sqlite-current-source-nexttwoOneNine'],
];

foreach ($cases219 as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim current source nextTwoOneNine ' . $name] = static function (TestRunner $t) use ($plan219, $valueAt219, $path, $expected): void {
        $t->same($expected, $valueAt219($plan219(), $path));
    };
}

$tests['utf16 nocase like rtrim current source nextTwoOneNine invalidation reason order'] = static function (TestRunner $t) use ($plan219): void {
    $t->same([
        'source-name',
        'schema-cookie',
        'decoded-text',
        'rtrim-expression',
        'nocase-key',
        'utf16-code-units',
        'residual-result',
        'malformed-text',
        'candidate-rowset',
        'matched-rowset',
        'utf16-code-unit-wildcard-trap',
    ], $plan219()['invalidationReasons']);
};

$tests['utf16 nocase like rtrim current source nextTwoOneNine stable supplementary wildcard still requires character split'] = static function (TestRunner $t) use ($row219, $emoji219): void {
    $rows = [
        $row219(1, 'plugin_cache' . $emoji219, 'UTF-16LE'),
        $row219(2, 'plugin_cacheA', 'UTF-16BE'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNameSupplementaryWildcardPlan(
        $rows,
        $rows,
        'plugin!_cache_',
        '!',
        'stable',
        'stable',
        219,
        219,
    );

    $t->same([2, 1], $result['currentMatchedRowids']);
    $t->same([1], $result['currentCodeUnitWildcardTrapRowids']);
    $t->same(['utf16-code-unit-wildcard-trap'], $result['invalidationReasons']);
    $t->same(false, $result['cursorReusable']);
};

$tests['utf16 nocase like rtrim current source nextTwoOneNine two underscores do not match one emoji'] = static function (TestRunner $t) use ($row219, $emoji219): void {
    $rows = [
        $row219(1, 'plugin_cache' . $emoji219, 'UTF-16LE'),
        $row219(2, 'plugin_cacheAB', 'UTF-16BE'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNameSupplementaryWildcardPlan(
        $rows,
        $rows,
        'plugin!_cache__',
        '!',
        'stable',
        'stable',
        219,
        219,
    );

    $t->same([2], $result['currentMatchedRowids']);
    $t->same([1], $result['currentFalsePositiveRowids']);
    $t->same(13, $result['currentCharacterCounts'][1]);
    $t->same(14, $result['currentUtf16CodeUnitCounts'][1]);
};

$tests['utf16 nocase like rtrim current source nextTwoOneNine ascii-space rtrim can expose a single trailing character'] = static function (TestRunner $t) use ($row219): void {
    $rows = [
        $row219(1, 'plugin_cacheA  ', 'UTF-16LE'),
        $row219(2, 'plugin_cache  ', 'UTF-16BE'),
        $row219(3, 'plugin_cache' . "\t", 'UTF-8'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNameSupplementaryWildcardPlan(
        $rows,
        $rows,
        'plugin!_cache_',
        '!',
        'stable',
        'stable',
        219,
        219,
    );

    $t->same([2, 3, 1], $result['currentCandidateRowids']);
    $t->same([3, 1], $result['currentMatchedRowids']);
    $t->same([2], $result['currentFalsePositiveRowids']);
};

$tests['utf16 nocase like rtrim current source nextTwoOneNine rejects malformed row shape'] = static function (TestRunner $t) use ($enc219): void {
    $rows = [['option_id' => 1, 'option_name_bytes' => $enc219('plugin_cacheA', 'UTF-16LE')]];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::optionRowNameSupplementaryWildcardPlan($rows, $rows));
};

return $tests;
