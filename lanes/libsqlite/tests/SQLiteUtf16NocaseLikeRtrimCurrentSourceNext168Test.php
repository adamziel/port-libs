<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

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
    $row(1, 'Plugin_Cache  ', 2),
    $row(2, 'plugin_cache', 3),
    $row(3, 'PLUGIN_CACHE_TRANSIENT  ', 2),
    $row(4, 'plugin_cache_shadow', 1),
    $row(5, 'plugin_config', 2),
    $row(6, 'Plugin_Case', 2),
    $row(7, 'plugin_cache' . "\t", 3),
    $row(8, 'theme_cache', 2),
    $bad(9, "p\0x", 2),
];
$nextRows = [
    $row(1, 'Plugin_Cache  ', 2),
    $row(2, 'plugin_cache', 3),
    $row(3, 'PLUGIN_CACHE_TRANSIENT  ', 2),
    $row(4, 'plugin_cache_shadow', 1),
    $row(5, 'plugin_config', 2),
    $row(6, 'plugin_case', 2),
    $row(7, 'plugin_cache' . "\t", 3),
    $row(10, 'plugin_cache_new  ', 3),
    $bad(11, "\x00\xd8", 2),
];

$plan = static fn (
    ?array $current = null,
    ?array $next = null,
    string $pattern = 'plugin!_cache%',
    ?string $escape = '!',
    bool $currentCaseSensitive = false,
    bool $nextCaseSensitive = true,
    string $currentSource = 'main.wp_options@167',
    string $nextSource = 'main.wp_options@168',
    int $currentCookie = 167,
    int $nextCookie = 168,
): array => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameCaseSensitiveLikePlan(
    $current ?? $currentRows,
    $next ?? $nextRows,
    $pattern,
    $escape,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
    $currentCaseSensitive,
    $nextCaseSensitive,
);

$valueAt = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        $value = $value[$part];
    }

    return $value;
};

$cases = [
    'status' => ['status', 'utf16-nocase-like-rtrim-current-source-nextoneSixEight'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(option_name) COLLATE NOCASE'],
    'pattern' => ['pattern', 'plugin!_cache%'],
    'escape' => ['escape', '!'],
    'current source' => ['currentSource', 'main.wp_options@167'],
    'next source' => ['nextSource', 'main.wp_options@168'],
    'current cookie' => ['currentSchemaCookie', 167],
    'next cookie' => ['nextSchemaCookie', 168],
    'current case-sensitive flag' => ['currentCaseSensitiveLike', false],
    'next case-sensitive flag' => ['nextCaseSensitiveLike', true],
    'prefix' => ['prefix', 'plugin_cache'],
    'current range lower' => ['currentRange.lowerInclusive', 'plugin_cache'],
    'current range upper' => ['currentRange.upperBound', 'plugin_cachf'],
    'next range disabled' => ['nextRange', null],
    'current index usable' => ['currentIndexUsable', true],
    'next index unusable' => ['nextIndexUsable', false],
    'next rejected reason' => ['nextRejectedReason', 'case_sensitive_like_requires_binary_index'],
    'current candidates' => ['currentCandidateRowids', [1, 2, 7, 4, 3]],
    'next candidates using old range' => ['nextCandidateRowidsUsingCurrentNocaseRange', [1, 2, 7, 10, 4, 3]],
    'retained candidates' => ['retainedCandidateRowids', [1, 2, 7, 4, 3]],
    'current matched' => ['currentMatchedRowids', [1, 2, 7, 4, 3]],
    'next matched after case-sensitive recheck' => ['nextMatchedRowids', [2, 7, 10, 4]],
    'next full scan matched' => ['nextFullScanMatchedRowids', [2, 7, 10, 4]],
    'retained matched' => ['retainedMatchedRowids', [2, 7, 4]],
    'case-sensitive dropped' => ['caseSensitiveDroppedRowids', [1, 3]],
    'case-sensitive kept' => ['caseSensitiveKeptRowids', [2, 7, 4]],
    'case-sensitive entered' => ['caseSensitiveEnteredRowids', [10]],
    'current false positives' => ['currentFalsePositiveRowids', []],
    'next false positives' => ['nextFalsePositiveRowids', [1, 3]],
    'case-sensitive range false positives' => ['caseSensitiveRangeFalsePositiveRowids', [1, 3]],
    'current malformed' => ['currentMalformedRowids', [9]],
    'next malformed' => ['nextMalformedRowids', [11]],
    'current error' => ['currentErrors.9', 'SQLite encoding source UTF-16 text payload has an odd byte length'],
    'next error' => ['nextErrors.11', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'row one text' => ['currentTexts.1', 'Plugin_Cache  '],
    'row one rtrim' => ['currentRtrimTexts.1', 'Plugin_Cache'],
    'row one nocase key' => ['currentNocaseKeys.1', 'plugin_cache'],
    'row two next encoding' => ['nextEncodings.2', 'UTF-16BE'],
    'row four next text' => ['nextTexts.4', 'plugin_cache_shadow'],
    'row seven tab rtrim preserved' => ['nextRtrimTexts.7', 'plugin_cache' . "\t"],
    'row one current residual' => ['currentResidualMatches.1', true],
    'row one next residual false' => ['nextResidualMatches.1', false],
    'row three next residual false' => ['nextResidualMatches.3', false],
    'row ten next residual true' => ['nextResidualMatches.10', true],
    'changed text rowids' => ['changedTextRowids', [6]],
    'changed rtrim rowids' => ['changedRtrimRowids', [6]],
    'changed nocase rowids' => ['changedNocaseKeyRowids', []],
    'changed encoding rowids' => ['changedEncodingRowids', []],
    'changed bytes rowids' => ['changedBytesRowids', [6]],
    'changed residual rowids' => ['changedResidualRowids', [1, 3]],
    'can seed recheck' => ['currentNocaseRangeCanSeedRecheck', true],
    'requires binary scan' => ['nextRequiresBinaryLikeScan', true],
    'invalidated' => ['cursorInvalidated', true],
    'not reusable' => ['cursorReusable', false],
    'rtrim ascii only' => ['rtrimTrimsOnlyAsciiSpace', true],
    'nocase ascii only' => ['nocaseFoldsAsciiOnly', true],
    'case-sensitive ascii' => ['caseSensitiveLikeHonorsAsciiCase', true],
    'dependency utf16' => ['dependencies.0', 'sqlite-utf16-decode'],
    'dependency rtrim' => ['dependencies.1', 'sqlite-rtrim-expression'],
    'dependency range' => ['dependencies.2', 'sqlite-like-nocase-prefix-range'],
    'dependency pragma' => ['dependencies.3', 'sqlite-case-sensitive-like'],
    'dependency nextOneSixEight' => ['dependencies.4', 'sqlite-current-source-nextoneSixEight'],
];

foreach ($cases as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim current source nextOneSixEight ' . $name] = static function (TestRunner $t) use ($plan, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($plan(), $path));
    };
}

$tests['utf16 nocase like rtrim current source nextOneSixEight invalidation reason order'] = static function (TestRunner $t) use ($plan): void {
    $t->same([
        'source-name',
        'schema-cookie',
        'case-sensitive-like',
        'next-nocase-index-unusable',
        'decoded-text',
        'rtrim-expression',
        'encoded-bytes',
        'residual-result',
        'malformed-text',
        'candidate-rowset',
        'matched-rowset',
        'case-sensitive-fullscan-required',
    ], $plan()['invalidationReasons']);
};

$tests['utf16 nocase like rtrim current source nextOneSixEight stable default like remains reusable'] = static function (TestRunner $t) use ($row): void {
    $rows = [$row(1, 'Plugin_Cache  ', 2), $row(2, 'plugin_cache_shadow', 3)];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameCaseSensitiveLikePlan(
        $rows,
        $rows,
        'plugin!_cache%',
        '!',
        'stable',
        'stable',
        7,
        7,
        false,
        false,
    );
    $t->same([1, 2], $result['currentMatchedRowids']);
    $t->same([1, 2], $result['nextMatchedRowids']);
    $t->same([], $result['invalidationReasons']);
    $t->same(true, $result['cursorReusable']);
};

$tests['utf16 nocase like rtrim current source nextOneSixEight stable case-sensitive like over lowercase rows keeps output but reparses index'] = static function (TestRunner $t) use ($row): void {
    $rows = [$row(1, 'plugin_cache  ', 2), $row(2, 'plugin_cache_shadow', 3)];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameCaseSensitiveLikePlan(
        $rows,
        $rows,
        'plugin!_cache%',
        '!',
        'stable',
        'stable',
        7,
        7,
        false,
        true,
    );
    $t->same([1, 2], $result['nextMatchedRowids']);
    $t->same(['case-sensitive-like', 'next-nocase-index-unusable'], $result['invalidationReasons']);
    $t->same(false, $result['cursorReusable']);
};

$tests['utf16 nocase like rtrim current source nextOneSixEight case-sensitive current and next still require a binary scan'] = static function (TestRunner $t) use ($row): void {
    $rows = [$row(1, 'plugin_cache', 2), $row(2, 'Plugin_Cache', 2)];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameCaseSensitiveLikePlan(
        $rows,
        $rows,
        'plugin!_cache%',
        '!',
        'stable',
        'stable',
        7,
        7,
        true,
        true,
    );
    $t->same([], $result['currentCandidateRowids']);
    $t->same([], $result['nextMatchedRowids']);
    $t->same(['current-no-nocase-prefix-range', 'next-nocase-index-unusable', 'case-sensitive-fullscan-required'], $result['invalidationReasons']);
    $t->same(false, $result['cursorReusable']);
};

$tests['utf16 nocase like rtrim current source nextOneSixEight tab is retained before residual matching'] = static function (TestRunner $t) use ($row): void {
    $rows = [$row(1, 'plugin_cache' . "\t", 2), $row(2, 'plugin_cache  ', 2)];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameCaseSensitiveLikePlan(
        $rows,
        $rows,
        "plugin!_cache\t",
        '!',
        'stable',
        'stable',
        7,
        7,
        false,
        false,
    );
    $t->same([1], $result['currentMatchedRowids']);
    $t->same("plugin_cache\t", $result['currentRtrimTexts'][1]);
};

$tests['utf16 nocase like rtrim current source nextOneSixEight escaped percent literal keeps prefix range'] = static function (TestRunner $t) use ($row): void {
    $rows = [$row(1, 'plugin_cache%', 2), $row(2, 'plugin_cache_extra', 2)];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameCaseSensitiveLikePlan(
        $rows,
        $rows,
        'plugin!_cache!%',
        '!',
        'stable',
        'stable',
        7,
        7,
        false,
        false,
    );
    $t->same('plugin_cache%', $result['prefix']);
    $t->same([1], $result['currentMatchedRowids']);
};

$tests['utf16 nocase like rtrim current source nextOneSixEight non ascii prefix rejects nocase range'] = static function (TestRunner $t) use ($row): void {
    $rows = [$row(1, 'Æther_cache', 2), $row(2, 'æther_cache', 2)];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameCaseSensitiveLikePlan(
        $rows,
        $rows,
        'Æther%',
        null,
        'stable',
        'stable',
        7,
        7,
        false,
        false,
    );
    $t->same(false, $result['currentIndexUsable']);
    $t->same('nocase_like_prefix_must_be_ascii_for_range', $result['currentLikePlan']['rejectedReason']);
    $t->same([], $result['currentMatchedRowids']);
};

$tests['utf16 nocase like rtrim current source nextOneSixEight rejects invalid escape'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameCaseSensitiveLikePlan([], [], 'plugin%', '!!'));
};

$tests['utf16 nocase like rtrim current source nextOneSixEight rejects bad row shape'] = static function (TestRunner $t) use ($enc): void {
    $rows = [['option_id' => 1, 'option_name_bytes' => $enc('plugin_cache', 2)]];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameCaseSensitiveLikePlan($rows, $rows, 'plugin%'));
};

return $tests;
