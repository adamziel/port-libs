<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimNulCurrentSourceNextPlan;

$tests = [];

$enc = static fn (string $text, int $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row = static fn (int $id, string $name, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $enc($name, $encoding),
    'text_encoding' => $encoding,
];
$bad = static fn (int $id, string $bytes, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$currentRows = [
    $row(1, 'Plugin_Cache', 2),
    $row(2, "plugin_cache\0disabled", 2),
    $row(3, "plugin_cache\0", 3),
    $row(4, "plugin_cache\0  ", 2),
    $row(5, "plugin_cache\t", 3),
    $row(6, 'plugin_config', 2),
    $row(7, 'theme_cache', 2),
    $bad(8, "\x00\xd8", 2),
];
$nextRows = [
    $row(1, 'plugin_cache', 3),
    $row(2, 'plugin_cache', 2),
    $row(3, "plugin_cache\0suffix", 3),
    $row(4, "plugin_cache\0", 2),
    $row(5, "plugin_cache\t", 3),
    $row(6, 'plugin_config', 2),
    $row(9, 'PLUGIN_CACHE', 2),
    $bad(10, "p\0x", 2),
];

$plan = static fn (
    ?array $current = null,
    ?array $next = null,
    string $pattern = 'plugin!_cache',
    ?string $escape = '!',
    string $currentSource = 'main.wp_options@173',
    string $nextSource = 'main.wp_options@174',
    int $currentCookie = 173,
    int $nextCookie = 174,
): array => SQLiteUtf16NocaseLikeRtrimNulCurrentSourceNextPlan::wordpressOptionNameEmbeddedNulPlan(
    $current ?? $currentRows,
    $next ?? $nextRows,
    $pattern,
    $escape,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        $value = $value[$part];
    }

    return $value;
};

$cases = [
    'status' => ['status', 'utf16-nocase-like-rtrim-nul-current-source-nextoneSevenFour'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(option_name) COLLATE NOCASE LIKE ?'],
    'pattern' => ['pattern', 'plugin!_cache'],
    'escape' => ['escape', '!'],
    'current source' => ['currentSource', 'main.wp_options@173'],
    'next source' => ['nextSource', 'main.wp_options@174'],
    'current cookie' => ['currentSchemaCookie', 173],
    'next cookie' => ['nextSchemaCookie', 174],
    'prefix' => ['prefix', 'plugin_cache'],
    'range lower' => ['range.lowerInclusive', 'plugin_cache'],
    'range upper' => ['range.upperBound', 'plugin_cachf'],
    'index usable' => ['indexUsable', true],
    'current candidates' => ['currentCandidateRowids', [1, 3, 4, 2, 5]],
    'next candidates' => ['nextCandidateRowids', [1, 2, 9, 4, 3, 5]],
    'current matched only exact full text' => ['currentMatchedRowids', [1]],
    'next matched exact full text' => ['nextMatchedRowids', [1, 2, 9]],
    'current false positives include nul suffixes' => ['currentFalsePositiveRowids', [3, 4, 2, 5]],
    'next false positives include nul suffixes' => ['nextFalsePositiveRowids', [4, 3, 5]],
    'current cstring false matches' => ['currentCstringFalseMatchRowids', [2, 3, 4]],
    'next cstring false matches' => ['nextCstringFalseMatchRowids', [3, 4]],
    'current embedded nul rowids' => ['currentEmbeddedNulRowids', [2, 3, 4]],
    'next embedded nul rowids' => ['nextEmbeddedNulRowids', [3, 4]],
    'current malformed rowids' => ['currentMalformedRowids', [8]],
    'next malformed rowids' => ['nextMalformedRowids', [10]],
    'current error' => ['currentErrors.8', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'next error' => ['nextErrors.10', 'SQLite encoding source UTF-16 text payload has an odd byte length'],
    'row two full text' => ['currentTexts.2', "plugin_cache\0disabled"],
    'row two cstring prefix' => ['currentCstringPrefixes.2', 'plugin_cache'],
    'row two nul offset' => ['currentNulOffsets.2', 12],
    'row three next full text' => ['nextTexts.3', "plugin_cache\0suffix"],
    'row four rtrim preserves nul' => ['currentRtrimTexts.4', "plugin_cache\0"],
    'row five tab remains text' => ['currentRtrimTexts.5', "plugin_cache\t"],
    'row nine nocase key' => ['nextNocaseKeys.9', 'plugin_cache'],
    'row two current residual false' => ['currentResidualMatches.2', false],
    'row two current cstring residual true' => ['currentCstringResidualMatches.2', true],
    'row two next residual true' => ['nextResidualMatches.2', true],
    'row three next residual false' => ['nextResidualMatches.3', false],
    'changed text rowids' => ['changedTextRowids', [1, 2, 3, 4]],
    'changed rtrim rowids' => ['changedRtrimRowids', [1, 2, 3]],
    'changed nocase rowids' => ['changedNocaseKeyRowids', [2, 3]],
    'changed nul position rowids' => ['changedNulPositionRowids', [2]],
    'changed cstring prefix rowids' => ['changedCstringPrefixRowids', [1]],
    'changed bytes rowids' => ['changedBytesRowids', [1, 2, 3, 4]],
    'changed residual rowids' => ['changedResidualRowids', [2]],
    'invalidated' => ['cursorInvalidated', true],
    'not reusable' => ['cursorReusable', false],
    'embedded nul full text' => ['embeddedNulPreservesSuffixForLike', true],
    'rtrim leaves nul' => ['rtrimDoesNotTrimNul', true],
    'nocase ascii around nul' => ['nocaseFoldsAsciiAroundNulOnly', true],
    'dependency decode' => ['dependencies.0', 'sqlite-utf16-decode'],
    'dependency range' => ['dependencies.1', 'sqlite-like-nocase-prefix-range'],
    'dependency nul' => ['dependencies.2', 'sqlite-embedded-nul-text-comparison'],
    'dependency current source' => ['dependencies.3', 'sqlite-current-source-nextoneSevenFour'],
];

foreach ($cases as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim nul current source nextOneSevenFour ' . $name] = static function (TestRunner $t) use ($plan, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($plan(), $path));
    };
}

$tests['utf16 nocase like rtrim nul current source nextOneSevenFour invalidation reason order'] = static function (TestRunner $t) use ($plan): void {
    $t->same([
        'source-name',
        'schema-cookie',
        'decoded-text',
        'rtrim-expression',
        'nocase-key',
        'nul-position',
        'cstring-prefix',
        'encoded-bytes',
        'residual-result',
        'malformed-text',
        'candidate-rowset',
        'matched-rowset',
        'embedded-nul-full-text-recheck',
    ], $plan()['invalidationReasons']);
};

$tests['utf16 nocase like rtrim nul current source nextOneSevenFour stable embedded nul false positive is reusable after recheck'] = static function (TestRunner $t) use ($row): void {
    $rows = [$row(1, 'plugin_cache', 2), $row(2, "plugin_cache\0disabled", 3)];
    $result = SQLiteUtf16NocaseLikeRtrimNulCurrentSourceNextPlan::wordpressOptionNameEmbeddedNulPlan(
        $rows,
        $rows,
        'plugin!_cache',
        '!',
        'stable',
        'stable',
        7,
        7,
    );
    $t->same([1, 2], $result['currentCandidateRowids']);
    $t->same([1], $result['currentMatchedRowids']);
    $t->same([2], $result['currentCstringFalseMatchRowids']);
    $t->same(['embedded-nul-full-text-recheck'], $result['invalidationReasons']);
    $t->same(false, $result['cursorReusable']);
};

$tests['utf16 nocase like rtrim nul current source nextOneSevenFour wildcard pattern can match nul suffixes'] = static function (TestRunner $t) use ($row): void {
    $rows = [$row(1, "plugin_cache\0disabled", 2), $row(2, "plugin_cache\0", 3)];
    $result = SQLiteUtf16NocaseLikeRtrimNulCurrentSourceNextPlan::wordpressOptionNameEmbeddedNulPlan(
        $rows,
        $rows,
        'plugin!_cache%',
        '!',
        'stable',
        'stable',
        7,
        7,
    );
    $t->same([2, 1], $result['currentMatchedRowids']);
    $t->same([], $result['currentCstringFalseMatchRowids']);
    $t->same([], array_values(array_diff($result['invalidationReasons'], ['embedded-nul-full-text-recheck'])));
};

$tests['utf16 nocase like rtrim nul current source nextOneSevenFour ascii case folds around embedded nul'] = static function (TestRunner $t) use ($row): void {
    $rows = [$row(1, "PLUGIN_CACHE\0disabled", 2), $row(2, 'Plugin_Cache', 2)];
    $result = SQLiteUtf16NocaseLikeRtrimNulCurrentSourceNextPlan::wordpressOptionNameEmbeddedNulPlan(
        $rows,
        $rows,
        'plugin!_cache',
        '!',
        'stable',
        'stable',
        7,
        7,
    );
    $t->same("plugin_cache\0disabled", $result['currentNocaseKeys'][1]);
    $t->same([2], $result['currentMatchedRowids']);
    $t->same([1], $result['currentCstringFalseMatchRowids']);
};

$tests['utf16 nocase like rtrim nul current source nextOneSevenFour non ascii prefix remains unplanned'] = static function (TestRunner $t) use ($row): void {
    $rows = [$row(1, "Æther\0plugin", 2), $row(2, 'æther', 2)];
    $result = SQLiteUtf16NocaseLikeRtrimNulCurrentSourceNextPlan::wordpressOptionNameEmbeddedNulPlan(
        $rows,
        $rows,
        'Æther%',
        null,
        'stable',
        'stable',
        7,
        7,
    );
    $t->same(false, $result['indexUsable']);
    $t->same(null, $result['range']);
    $t->same([], $result['currentCandidateRowids']);
};

$tests['utf16 nocase like rtrim nul current source nextOneSevenFour rejects bad row shape'] = static function (TestRunner $t) use ($enc): void {
    $rows = [['option_id' => 1, 'option_name_bytes' => $enc('plugin_cache', 2)]];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimNulCurrentSourceNextPlan::wordpressOptionNameEmbeddedNulPlan($rows, $rows, 'plugin%'));
};

return $tests;
