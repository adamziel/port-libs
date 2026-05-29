<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$tests = [];

$enc233 = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$encodingId233 = static fn (int|string $encoding): int => match ($encoding) {
    'UTF-8', 1 => 1,
    'UTF-16LE', 2 => 2,
    'UTF-16BE', 3 => 3,
};
$row233 = static fn (int $id, string $name, int|string $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc233($name, $encoding),
    'text_encoding' => $encodingId233($encoding),
];
$bad233 = static fn (int $id, string $bytes, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$pre233 = "\u{00e9}";
$comb233 = "e\u{0301}";
$upperPre233 = "\u{00c9}";

$current233 = [
    $row233(1, 'plugin_cafe', 'UTF-16LE'),
    $row233(2, 'plugin_caf' . $pre233, 'UTF-16BE'),
    $row233(3, 'plugin_caf' . $comb233, 'UTF-16LE'),
    $row233(4, 'Plugin_CafE', 'UTF-16BE'),
    $row233(5, 'plugin_caf' . $upperPre233, 'UTF-8'),
    $row233(6, 'plugin_caf' . $comb233 . '  ', 'UTF-16BE'),
    $row233(7, 'plugin_cafe_extra', 'UTF-16LE'),
    $row233(8, 'theme_caf' . $pre233, 'UTF-16LE'),
    $bad233(9, "\x00\xd8", 2),
];
$nextTwoThreeThree = [
    $row233(1, 'plugin_cafe', 'UTF-16BE'),
    $row233(2, 'plugin_caf' . $comb233, 'UTF-16LE'),
    $row233(3, 'plugin_caf' . $pre233, 'UTF-16BE'),
    $row233(4, 'Plugin_CafE', 'UTF-16LE'),
    $row233(5, 'plugin_caf' . $upperPre233, 'UTF-16BE'),
    $row233(6, 'plugin_caf' . $comb233, 'UTF-16LE'),
    $row233(10, 'PLUGIN_CAF' . $pre233, 'UTF-16LE'),
    $row233(11, 'plugin_caf' . $comb233 . 'x', 'UTF-16BE'),
    $bad233(12, "\x00\xd8", 2),
];

$plan233 = static fn (
    ?array $current = null,
    ?array $next = null,
    string $pattern = 'plugin!_caf_',
    ?string $escape = '!',
    string $currentSource = 'main.wp_options@232',
    string $nextSource = 'main.wp_options@233',
    int $currentCookie = 232,
    int $nextCookie = 233,
): array => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameCanonicalUnicodePlan(
    $current ?? $current233,
    $next ?? $nextTwoThreeThree,
    $pattern,
    $escape,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt233 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases233 = [
    'status' => ['status', 'utf16-nocase-like-rtrim-current-source-nexttwoThreeThree'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* no Unicode normalization */'],
    'pattern' => ['pattern', 'plugin!_caf_'],
    'escape' => ['escape', '!'],
    'collation' => ['collation', 'NOCASE'],
    'current source' => ['currentSource', 'main.wp_options@232'],
    'next source' => ['nextSource', 'main.wp_options@233'],
    'current cookie' => ['currentSchemaCookie', 232],
    'next cookie' => ['nextSchemaCookie', 233],
    'prefix' => ['prefix', 'plugin_caf'],
    'range lower' => ['rangeLowerInclusive', 'plugin_caf'],
    'range upper' => ['rangeUpperBound', 'plugin_cag'],
    'index usable' => ['indexUsable', true],
    'current candidates' => ['currentCandidateRowids', [1, 4, 7, 3, 6, 5, 2]],
    'next candidates' => ['nextCandidateRowids', [1, 4, 2, 6, 11, 5, 3, 10]],
    'current matched' => ['currentMatchedRowids', [1, 4, 5, 2]],
    'next matched' => ['nextMatchedRowids', [1, 4, 5, 3, 10]],
    'matched retained' => ['matchedRetainedRowids', [1, 4, 5]],
    'matched exited' => ['matchedExitedRowids', [2]],
    'matched entered' => ['matchedEnteredRowids', [3, 10]],
    'current false positives' => ['currentFalsePositiveRowids', [7, 3, 6]],
    'next false positives' => ['nextFalsePositiveRowids', [2, 6, 11]],
    'current combining rowids' => ['currentCombiningMarkRowids', [3, 6]],
    'next combining rowids' => ['nextCombiningMarkRowids', [2, 6, 11]],
    'current precomposed rowids' => ['currentPrecomposedAccentRowids', [2, 5, 8]],
    'next precomposed rowids' => ['nextPrecomposedAccentRowids', [3, 5, 10]],
    'current canonical rows' => ['currentCanonicalEquivalentRowids', [2, 3, 5, 6, 8]],
    'next canonical rows' => ['nextCanonicalEquivalentRowids', [2, 3, 5, 6, 10, 11]],
    'current combining matched' => ['currentCombiningMatchedRowids', []],
    'next combining matched' => ['nextCombiningMatchedRowids', []],
    'current single wildcard false positives' => ['currentSingleWildcardFalsePositiveRowids', [3, 6]],
    'next single wildcard false positives' => ['nextSingleWildcardFalsePositiveRowids', [2, 6, 11]],
    'current malformed' => ['currentMalformedRowids', [9]],
    'next malformed' => ['nextMalformedRowids', [12]],
    'current malformed error' => ['currentErrors.9', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'next malformed error' => ['nextErrors.12', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'current row two rtrim' => ['currentRtrimTexts.2', 'plugin_caf' . $pre233],
    'current row three rtrim' => ['currentRtrimTexts.3', 'plugin_caf' . $comb233],
    'next row two rtrim' => ['nextRtrimTexts.2', 'plugin_caf' . $comb233],
    'row four nocase' => ['currentNocaseKeys.4', 'plugin_cafe'],
    'row five nocase unchanged uppercase accent' => ['currentNocaseKeys.5', 'plugin_caf' . $upperPre233],
    'row two current codepoints' => ['currentCodepointCounts.2', 11],
    'row three current codepoints' => ['currentCodepointCounts.3', 12],
    'row two current utf16 units' => ['currentUtf16CodeUnitCounts.2', 11],
    'row three current utf16 units' => ['currentUtf16CodeUnitCounts.3', 12],
    'row two canonical key' => ['currentCanonicalKeys.2', 'plugin_cafe'],
    'row three canonical key' => ['currentCanonicalKeys.3', 'plugin_cafe'],
    'row three current residual false' => ['currentResidualMatches.3', false],
    'row three next residual true' => ['nextResidualMatches.3', true],
    'row ten next residual true' => ['nextResidualMatches.10', true],
    'changed text' => ['changedTextRowids', [2, 3, 6, 7, 8, 10, 11]],
    'changed rtrim' => ['changedRtrimRowids', [2, 3, 7, 8, 10, 11]],
    'changed nocase' => ['changedNocaseKeyRowids', [2, 3, 7, 8, 10, 11]],
    'changed codepoints' => ['changedCodepointRowids', [2, 3, 7, 8, 10, 11]],
    'changed utf16 units' => ['changedUtf16CodeUnitRowids', [2, 3, 7, 8, 10, 11]],
    'changed residual' => ['changedResidualRowids', [2, 3, 7, 10, 11]],
    'underscore codepoint flag' => ['likeUnderscoreConsumesOneUnicodeCodepoint', true],
    'combining separate flag' => ['combiningMarkIsSeparateLikeCharacter', true],
    'normalization flag' => ['unicodeNormalizationApplied', false],
    'canonical distinct flag' => ['canonicalEquivalentTextMayCompareDistinct', true],
    'nocase ascii flag' => ['nocaseFoldsAsciiOnly', true],
    'rtrim ascii flag' => ['rtrimTrimsOnlyAsciiSpace', true],
    'invalidated' => ['cursorInvalidated', true],
    'not reusable' => ['cursorReusable', false],
    'dependency decode' => ['dependencies.0', 'sqlite-utf16-decode'],
    'dependency range' => ['dependencies.1', 'sqlite-like-nocase-prefix-range'],
    'dependency rtrim' => ['dependencies.2', 'sqlite-rtrim-expression-key'],
    'dependency codepoint' => ['dependencies.3', 'sqlite-unicode-codepoint-like'],
    'dependency source' => ['dependencies.4', 'sqlite-current-source-nexttwoThreeThree'],
];

foreach ($cases233 as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim current source nextTwoThreeThree ' . $name] = static function (TestRunner $t) use ($plan233, $valueAt233, $path, $expected): void {
        $t->same($expected, $valueAt233($plan233(), $path));
    };
}

$tests['utf16 nocase like rtrim current source nextTwoThreeThree invalidation reason order'] = static function (TestRunner $t) use ($plan233): void {
    $t->same([
        'source-name',
        'schema-cookie',
        'decoded-text',
        'rtrim-expression',
        'nocase-key',
        'unicode-codepoint-count',
        'utf16-code-units',
        'residual-result',
        'canonical-equivalent-rowset',
        'combining-mark-rowset',
        'single-wildcard-codepoint-boundary',
        'malformed-text',
        'candidate-rowset',
        'matched-rowset',
        'canonical-peer-rowset',
    ], $plan233()['invalidationReasons']);
};

$tests['utf16 nocase like rtrim current source nextTwoThreeThree canonical peers remain visible'] = static function (TestRunner $t) use ($plan233): void {
    $peers = $plan233()['currentCanonicalPeerRowids'];
    $t->same([1, 2, 3, 4, 5, 6], $peers['plugin_cafe']);
};

$tests['utf16 nocase like rtrim current source nextTwoThreeThree upper accented precomposed is not NOCASE folded'] = static function (TestRunner $t) use ($row233, $upperPre233): void {
    $rows = [
        $row233(1, 'plugin_caf' . $upperPre233, 'UTF-16LE'),
        $row233(2, 'plugin_cafe', 'UTF-16BE'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameCanonicalUnicodePlan($rows, $rows, 'plugin!_caf' . "\u{00e9}");

    $t->same([], $result['currentMatchedRowids']);
    $t->same([], $result['currentCandidateRowids']);
    $t->same([1], $result['currentPrecomposedAccentRowids']);
};

$tests['utf16 nocase like rtrim current source nextTwoThreeThree decomposed accent needs two underscores'] = static function (TestRunner $t) use ($row233, $comb233): void {
    $rows = [
        $row233(1, 'plugin_caf' . $comb233, 'UTF-16LE'),
        $row233(2, 'plugin_cafe', 'UTF-16BE'),
    ];
    $one = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameCanonicalUnicodePlan($rows, $rows, 'plugin!_caf_');
    $two = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameCanonicalUnicodePlan($rows, $rows, 'plugin!_caf__');

    $t->same([2], $one['currentMatchedRowids']);
    $t->same([1], $one['currentSingleWildcardFalsePositiveRowids']);
    $t->same([1], $two['currentMatchedRowids']);
};

$tests['utf16 nocase like rtrim current source nextTwoThreeThree ascii rtrim does not remove combining mark'] = static function (TestRunner $t) use ($row233, $comb233): void {
    $rows = [
        $row233(1, 'plugin_caf' . $comb233 . '  ', 'UTF-16LE'),
        $row233(2, 'plugin_cafe  ', 'UTF-16BE'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameCanonicalUnicodePlan($rows, $rows, 'plugin!_caf_');

    $t->same('plugin_caf' . $comb233, $result['currentRtrimTexts'][1]);
    $t->same('plugin_cafe', $result['currentRtrimTexts'][2]);
    $t->same([2], $result['currentMatchedRowids']);
};

$tests['utf16 nocase like rtrim current source nextTwoThreeThree stable precomposed cursor is reusable'] = static function (TestRunner $t) use ($row233, $pre233): void {
    $rows = [
        $row233(1, 'plugin_caf' . $pre233, 'UTF-16LE'),
        $row233(2, 'plugin_cafe', 'UTF-16BE'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameCanonicalUnicodePlan($rows, $rows, 'plugin!_caf_', '!', 'stable', 'stable', 233, 233);

    $t->same([2, 1], $result['currentMatchedRowids']);
    $t->same(false, $result['cursorInvalidated']);
    $t->same(true, $result['cursorReusable']);
};

$tests['utf16 nocase like rtrim current source nextTwoThreeThree rejects malformed row shape'] = static function (TestRunner $t) use ($enc233): void {
    $rows = [['option_id' => 1, 'option_name_bytes' => $enc233('plugin_cafe', 'UTF-16LE')]];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameCanonicalUnicodePlan($rows, $rows));
};

return $tests;
