<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$tests = [];

$enc204 = static fn (string $text, int|string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row204 = static fn (int $id, string $name, int|string $encoding): array => [
    'setting_id' => $id,
    'key_name_bytes' => $enc204($name, $encoding),
    'text_encoding' => match ($encoding) {
        'UTF-8', 1 => 1,
        'UTF-16LE', 2 => 2,
        'UTF-16BE', 3 => 3,
    },
];
$bad204 = static fn (int $id, string $bytes, int $encoding): array => [
    'setting_id' => $id,
    'key_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$current204 = [
    $row204(1, 'plugin_éclair  ', 'UTF-16LE'),
    $row204(2, 'Plugin_éclair', 'UTF-16BE'),
    $row204(3, 'plugin_Éclair', 'UTF-16LE'),
    $row204(4, 'plugin_eclair', 'UTF-8'),
    $row204(5, 'plugin_élan', 'UTF-16BE'),
    $row204(6, "plugin_éclair\t", 'UTF-16LE'),
    $row204(7, 'theme_éclair', 'UTF-16BE'),
    $bad204(8, "\x00\xd8", 2),
    $row204(9, 'PLUGIN_éTAG', 'UTF-16BE'),
];
$nextTwoZeroFour = [
    $row204(1, 'PLUGIN_éclair ', 'UTF-16BE'),
    $row204(2, 'Plugin_Éclair', 'UTF-16LE'),
    $row204(3, 'plugin_éclair', 'UTF-16LE'),
    $row204(4, 'plugin_eclair', 'UTF-8'),
    $row204(5, 'plugin_élan', 'UTF-16BE'),
    $row204(6, "plugin_éclair\t", 'UTF-16LE'),
    $row204(7, 'theme_éclair', 'UTF-16BE'),
    $bad204(10, "x\0y", 2),
    $row204(9, 'PLUGIN_ÉTAG', 'UTF-16BE'),
    $row204(11, 'plugin_été', 'UTF-16LE'),
];

$plan204 = static fn (
    ?array $current = null,
    ?array $next = null,
    string $pattern = 'plugin!_é%',
    ?string $escape = '!',
    string $currentSource = 'main.app_settings@203',
    string $nextSource = 'main.app_settings@204',
    int $currentCookie = 203,
    int $nextCookie = 204,
): array => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyNonAsciiPrefixFullScanPlan(
    $current ?? $current204,
    $next ?? $nextTwoZeroFour,
    $pattern,
    $escape,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt204 = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return null;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases204 = [
    'status' => ['status', 'utf16-nocase-like-rtrim-current-source-nexttwoZeroFour'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(key_name) COLLATE NOCASE LIKE ? ESCAPE ? /* non-ASCII prefix full scan */'],
    'pattern' => ['pattern', 'plugin!_é%'],
    'escape' => ['escape', '!'],
    'collation' => ['collation', 'NOCASE'],
    'case sensitive false' => ['caseSensitiveLike', false],
    'prefix includes accent' => ['prefix', 'plugin_é'],
    'prefix non ascii' => ['prefixIsAscii', false],
    'range lower null' => ['rangeLowerInclusive', null],
    'range upper null' => ['rangeUpperBound', null],
    'index unusable' => ['indexUsable', false],
    'prefix cursor false' => ['usesPrefixRangeCursor', false],
    'full scan true' => ['usesFullScanFallback', true],
    'rejected reason' => ['rejectedReason', 'nocase_like_prefix_must_be_ascii_for_range'],
    'like plan rejected reason' => ['likePlan.rejectedReason', 'nocase_like_prefix_must_be_ascii_for_range'],
    'like plan prefix chars' => ['likePlan.prefixCharacters', 8],
    'like plan wildcard' => ['likePlan.hasWildcard', true],
    'current source' => ['currentSource', 'main.app_settings@203'],
    'next source' => ['nextSource', 'main.app_settings@204'],
    'current cookie' => ['currentSchemaCookie', 203],
    'next cookie' => ['nextSchemaCookie', 204],
    'current decoded' => ['currentDecodedRowids', [4, 3, 1, 2, 6, 5, 9, 7]],
    'next decoded' => ['nextDecodedRowids', [4, 2, 9, 1, 3, 6, 5, 11, 7]],
    'current candidates full decoded' => ['currentCandidateRowids', [4, 3, 1, 2, 6, 5, 9, 7]],
    'next candidates full decoded' => ['nextCandidateRowids', [4, 2, 9, 1, 3, 6, 5, 11, 7]],
    'current matched' => ['currentMatchedRowids', [1, 2, 6, 5, 9]],
    'next matched' => ['nextMatchedRowids', [1, 3, 6, 5, 11]],
    'current rejected' => ['currentFullScanRejectedRowids', [4, 3, 7]],
    'next rejected' => ['nextFullScanRejectedRowids', [4, 2, 9, 7]],
    'matched retained' => ['matchedRetainedRowids', [1, 6, 5]],
    'matched exited' => ['matchedExitedRowids', [2, 9]],
    'matched entered' => ['matchedEnteredRowids', [3, 11]],
    'current text one' => ['currentTexts.1', 'plugin_éclair  '],
    'next rtrim one' => ['nextRtrimTexts.1', 'PLUGIN_éclair'],
    'tab not trimmed' => ['currentRtrimTexts.6', "plugin_éclair\t"],
    'current nocase ascii folds only' => ['currentNocaseKeys.2', 'plugin_éclair'],
    'current uppercase accent key distinct' => ['currentNocaseKeys.3', 'plugin_Éclair'],
    'next uppercase accent key distinct' => ['nextNocaseKeys.9', 'plugin_Étag'],
    'current matched text nine' => ['currentMatchedTexts.9', 'PLUGIN_éTAG'],
    'next matched text eleven' => ['nextMatchedTexts.11', 'plugin_été'],
    'current malformed' => ['currentMalformedRowids', [8]],
    'next malformed' => ['nextMalformedRowids', [10]],
    'current malformed error' => ['currentErrors.8', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'next malformed error' => ['nextErrors.10', 'SQLite encoding source UTF-16 text payload has an odd byte length'],
    'changed text' => ['changedTextRowids', [1, 2, 3, 9]],
    'changed rtrim' => ['changedRtrimRowids', [1, 2, 3, 9]],
    'changed nocase' => ['changedNocaseKeyRowids', [2, 3, 9]],
    'changed bytes' => ['changedBytesRowids', [1, 2, 3, 9]],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor reusable false' => ['cursorReusable', false],
    'residual after rtrim' => ['likeResidualAppliesAfterRtrim', true],
    'non ascii full scan' => ['nonAsciiPrefixRequiresFullScan', true],
    'accent distinct' => ['asciiNocaseOnlyKeepsAccentCaseDistinct', true],
    'malformed isolation' => ['malformedRowsDoNotAbortFullScan', true],
    'rtrim ascii' => ['rtrimTrimsOnlyAsciiSpace', true],
    'nocase ascii' => ['nocaseFoldsAsciiOnly', true],
    'dependency decode' => ['dependencies.0', 'sqlite-utf16-decode'],
    'dependency full scan' => ['dependencies.1', 'sqlite-like-nocase-non-ascii-prefix-full-scan'],
    'dependency rtrim' => ['dependencies.2', 'sqlite-rtrim-residual-match'],
    'dependency source' => ['dependencies.3', 'sqlite-current-source-nexttwoZeroFour'],
];

foreach ($cases204 as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim current source nextTwoZeroFour ' . $name] = static function (TestRunner $t) use ($plan204, $valueAt204, $path, $expected): void {
        $t->same($expected, $valueAt204($plan204(), $path));
    };
}

$tests['utf16 nocase like rtrim current source nextTwoZeroFour invalidation reasons include non ascii full scan'] = static function (TestRunner $t) use ($plan204): void {
    $t->same([
        'source-name',
        'schema-cookie',
        'non-ascii-nocase-prefix-full-scan',
        'decoded-text',
        'rtrim-expression',
        'nocase-key',
        'encoded-bytes',
        'malformed-text',
        'matched-rowset',
    ], $plan204()['invalidationReasons']);
};

$tests['utf16 nocase like rtrim current source nextTwoZeroFour stable non ascii still cannot use range cursor'] = static function (TestRunner $t) use ($row204): void {
    $rows = [
        $row204(1, 'plugin_éclair', 'UTF-16LE'),
        $row204(2, 'Plugin_éTAG  ', 'UTF-16BE'),
        $row204(3, 'plugin_Éclair', 'UTF-8'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyNonAsciiPrefixFullScanPlan(
        $rows,
        $rows,
        'plugin!_é%',
        '!',
        'stable',
        'stable',
        204,
        204,
    );

    $t->same([3, 1, 2], $result['currentCandidateRowids']);
    $t->same([1, 2], $result['currentMatchedRowids']);
    $t->same([3], $result['currentFullScanRejectedRowids']);
    $t->same(['non-ascii-nocase-prefix-full-scan'], $result['invalidationReasons']);
    $t->same(false, $result['cursorReusable']);
};

$tests['utf16 nocase like rtrim current source nextTwoZeroFour rejects ascii prefix pattern'] = static function (TestRunner $t) use ($row204): void {
    $rows = [$row204(1, 'plugin_cache', 'UTF-16LE')];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyNonAsciiPrefixFullScanPlan($rows, $rows, 'plugin_%'));
};

$tests['utf16 nocase like rtrim current source nextTwoZeroFour rejects no fixed prefix pattern'] = static function (TestRunner $t) use ($row204): void {
    $rows = [$row204(1, 'plugin_éclair', 'UTF-16LE')];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyNonAsciiPrefixFullScanPlan($rows, $rows, '%é%'));
};

$tests['utf16 nocase like rtrim current source nextTwoZeroFour rejects missing setting id'] = static function (TestRunner $t) use ($nextTwoZeroFour): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyNonAsciiPrefixFullScanPlan([
        ['key_name_bytes' => 'plugin_éclair', 'text_encoding' => 1],
    ], $nextTwoZeroFour));
};

$tests['utf16 nocase like rtrim current source nextTwoZeroFour rejects missing bytes'] = static function (TestRunner $t) use ($nextTwoZeroFour): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyNonAsciiPrefixFullScanPlan([
        ['setting_id' => 1, 'text_encoding' => 1],
    ], $nextTwoZeroFour));
};

$tests['utf16 nocase like rtrim current source nextTwoZeroFour rejects missing encoding'] = static function (TestRunner $t) use ($nextTwoZeroFour): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyNonAsciiPrefixFullScanPlan([
        ['setting_id' => 1, 'key_name_bytes' => 'plugin_éclair'],
    ], $nextTwoZeroFour));
};

return $tests;
