<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimRhsCurrentSourceNextPlan;

$tests = [];

$enc = static fn (string $text, int $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);
$row = static fn (int $id, string $name, int $encoding): array => [
    'setting_id' => $id,
    'key_name_bytes' => $enc($name, $encoding),
    'text_encoding' => $encoding,
];
$bad = static fn (int $id, string $bytes, int $encoding): array => [
    'setting_id' => $id,
    'key_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$currentRows = [
    $row(1, 'Module_Cache  ', 2),
    $row(2, 'module_cache', 3),
    $row(3, "module_cache\t", 2),
    $row(4, 'module_config', 1),
    $row(5, 'module_cache_miss', 3),
    $row(6, 'module_Æther  ', 2),
    $row(7, 'module_æther', 2),
    $row(8, 'theme_cache', 2),
    $bad(9, "p\0l", 2),
];
$nextRows = [
    $row(1, 'module_cache', 2),
    $row(2, 'MODULE_CACHE  ', 3),
    $row(3, "module_cache\t", 2),
    $row(4, 'module_config_v2', 1),
    $row(5, 'module_cache_miss', 3),
    $row(6, 'module_Æther', 3),
    $row(7, 'module_æther  ', 2),
    $row(10, 'module_cache_new  ', 3),
    $bad(11, "\x00\xd8", 2),
];

$plan = static fn (
    string $currentPattern = 'module!_cache%   ',
    int $currentEncoding = 2,
    string $nextPattern = 'module!_cache% ',
    int $nextEncoding = 3,
    ?array $current = null,
    ?array $next = null,
    ?string $escape = '!',
    string $currentSource = 'main.app_settings@162',
    string $nextSource = 'main.app_settings@163',
    int $currentCookie = 162,
    int $nextCookie = 163,
): array => SQLiteUtf16NocaseLikeRtrimRhsCurrentSourceNextPlan::keyValueRowKeyRtrimPatternPlan(
    $current ?? $currentRows,
    $next ?? $nextRows,
    $enc($currentPattern, $currentEncoding),
    $currentEncoding,
    $enc($nextPattern, $nextEncoding),
    $nextEncoding,
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
    'status' => ['status', 'utf16-nocase-like-rtrim-rhs-current-source-nextoneSixThree'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(key_name) COLLATE NOCASE LIKE rtrim(?)'],
    'collation' => ['collation', 'NOCASE'],
    'case sensitive false' => ['caseSensitiveLike', false],
    'current source' => ['currentSource', 'main.app_settings@162'],
    'next source' => ['nextSource', 'main.app_settings@163'],
    'current cookie' => ['currentSchemaCookie', 162],
    'next cookie' => ['nextSchemaCookie', 163],
    'current raw pattern' => ['currentPattern', 'module!_cache%   '],
    'next raw pattern' => ['nextPattern', 'module!_cache% '],
    'current trimmed pattern' => ['currentTrimmedPattern', 'module!_cache%'],
    'next trimmed pattern' => ['nextTrimmedPattern', 'module!_cache%'],
    'current encoding' => ['currentPatternEncoding', 'UTF-16LE'],
    'next encoding' => ['nextPatternEncoding', 'UTF-16BE'],
    'current pattern bytes' => ['currentPatternBytesHex', '6d006f00640075006c00650021005f00630061006300680065002500200020002000'],
    'next pattern bytes' => ['nextPatternBytesHex', '006d006f00640075006c00650021005f0063006100630068006500250020'],
    'current trimmed bytes' => ['currentTrimmedPatternBytesHex', '6d006f00640075006c00650021005f00630061006300680065002500'],
    'next trimmed bytes' => ['nextTrimmedPatternBytesHex', '006d006f00640075006c00650021005f006300610063006800650025'],
    'escape' => ['escape', '!'],
    'current prefix' => ['currentPrefix', 'module_cache'],
    'next prefix' => ['nextPrefix', 'module_cache'],
    'current range lower' => ['currentRange.lowerInclusive', 'module_cache'],
    'current range upper' => ['currentRange.upperBound', 'module_cachf'],
    'next range lower' => ['nextRange.lowerInclusive', 'module_cache'],
    'current index usable' => ['currentIndexUsable', true],
    'next index usable' => ['nextIndexUsable', true],
    'current candidates' => ['currentCandidateRowids', [1, 2, 3, 5]],
    'next candidates' => ['nextCandidateRowids', [1, 2, 3, 5, 10]],
    'current matched' => ['currentMatchedRowids', [1, 2, 3, 5]],
    'next matched' => ['nextMatchedRowids', [1, 2, 3, 5, 10]],
    'current false positives' => ['currentFalsePositiveRowids', []],
    'next false positives' => ['nextFalsePositiveRowids', []],
    'retained matched' => ['retainedMatchedRowids', [1, 2, 3, 5]],
    'entered matched' => ['enteredMatchedRowids', [10]],
    'exited matched' => ['exitedMatchedRowids', []],
    'row one current rtrim' => ['currentRtrimTexts.1', 'Module_Cache'],
    'row two next rtrim' => ['nextRtrimTexts.2', 'MODULE_CACHE'],
    'row three tab retained' => ['currentRtrimTexts.3', "module_cache\t"],
    'row one current nocase' => ['currentNocaseKeys.1', 'module_cache'],
    'row two next nocase' => ['nextNocaseKeys.2', 'module_cache'],
    'row six ascii nocase only' => ['currentNocaseKeys.6', 'module_Æther'],
    'row seven ascii nocase lower ae' => ['currentNocaseKeys.7', 'module_æther'],
    'row one residual' => ['currentResidualMatches.1', true],
    'row five residual' => ['currentResidualMatches.5', true],
    'next row ten residual' => ['nextResidualMatches.10', true],
    'current malformed rowids' => ['currentMalformedRowids', [9]],
    'next malformed rowids' => ['nextMalformedRowids', [11]],
    'current error' => ['currentErrors.9', 'SQLite encoding source UTF-16 text payload has an odd byte length'],
    'next error' => ['nextErrors.11', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'rtrim pattern ascii only' => ['rtrimPatternTrimsOnlyAsciiSpace', true],
    'rtrim column ascii only' => ['rtrimColumnTrimsOnlyAsciiSpace', true],
    'nocase ascii only' => ['nocaseFoldsAsciiOnly', true],
    'invalidated' => ['cursorInvalidated', true],
    'not reusable' => ['cursorReusable', false],
    'reason source' => ['invalidationReasons.0', 'source-name'],
    'reason schema' => ['invalidationReasons.1', 'schema-cookie'],
    'reason pattern text' => ['invalidationReasons.2', 'pattern-text'],
    'reason pattern encoding' => ['invalidationReasons.3', 'pattern-encoding'],
    'reason pattern bytes' => ['invalidationReasons.4', 'pattern-bytes'],
    'reason rtrim pattern bytes' => ['invalidationReasons.5', 'rtrim-pattern-bytes'],
    'reason candidate rowset' => ['invalidationReasons.6', 'candidate-rowset'],
    'reason matched rowset' => ['invalidationReasons.7', 'matched-rowset'],
    'reason malformed' => ['invalidationReasons.8', 'malformed-text'],
    'dependency pattern decode' => ['dependencies.0', 'sqlite-utf16-pattern-decode'],
    'dependency rtrim rhs' => ['dependencies.1', 'sqlite-rtrim-rhs-expression'],
    'dependency current source' => ['dependencies.4', 'sqlite-current-source-nextoneSixThree'],
];

foreach ($cases as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim rhs current source nextOneSixThree ' . $name] = static function (TestRunner $t) use ($plan, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($plan(), $path));
    };
}

$tests['utf16 nocase like rtrim rhs current source nextOneSixThree stable trailing pattern spaces reusable'] = static function (TestRunner $t) use ($row, $enc): void {
    $rows = [$row(1, 'Module_Cache  ', 2), $row(2, 'module_cache', 3)];
    $result = SQLiteUtf16NocaseLikeRtrimRhsCurrentSourceNextPlan::keyValueRowKeyRtrimPatternPlan(
        $rows,
        $rows,
        $enc('module!_cache%   ', 2),
        2,
        $enc('module!_cache%   ', 2),
        2,
        '!',
        'stable',
        'stable',
        7,
        7,
    );
    $t->same('module!_cache%', $result['currentTrimmedPattern']);
    $t->same([1, 2], $result['currentMatchedRowids']);
    $t->same([], $result['invalidationReasons']);
    $t->same(true, $result['cursorReusable']);
};

$tests['utf16 nocase like rtrim rhs current source nextOneSixThree trimmed pattern text change invalidates range'] = static function (TestRunner $t) use ($plan): void {
    $result = $plan('module!_cache%   ', 2, 'module!_config% ', 2);
    $t->same('module!_cache%', $result['currentTrimmedPattern']);
    $t->same('module!_config%', $result['nextTrimmedPattern']);
    $t->same([4], $result['nextMatchedRowids']);
    $t->same('rtrim-pattern-text', $result['invalidationReasons'][3]);
};

$tests['utf16 nocase like rtrim rhs current source nextOneSixThree non ascii prefix remains unplanned'] = static function (TestRunner $t) use ($plan): void {
    $result = $plan('Æther%   ', 2, 'Æther% ', 2);
    $t->same('Æther%', $result['currentTrimmedPattern']);
    $t->same(false, $result['currentIndexUsable']);
    $t->same(null, $result['currentRange']);
    $t->same([], $result['currentCandidateRowids']);
};

$tests['utf16 nocase like rtrim rhs current source nextOneSixThree tabs are not trimmed from rhs'] = static function (TestRunner $t) use ($plan): void {
    $result = $plan("module!_cache\t", 2, "module!_cache\t", 2, null, null, '!');
    $t->same("module!_cache\t", $result['currentTrimmedPattern']);
    $t->same("module_cache\t", $result['currentPrefix']);
    $t->same([3], $result['currentMatchedRowids']);
};

$tests['utf16 nocase like rtrim rhs current source nextOneSixThree rejects malformed rhs pattern'] = static function (TestRunner $t) use ($currentRows, $nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimRhsCurrentSourceNextPlan::keyValueRowKeyRtrimPatternPlan(
        $currentRows,
        $nextRows,
        "p\0x",
        2,
        "p\0%",
        2,
    ));
};

$tests['utf16 nocase like rtrim rhs current source nextOneSixThree rejects bad row shape'] = static function (TestRunner $t) use ($enc): void {
    $rows = [['setting_id' => 1, 'key_name_bytes' => $enc('module_cache', 2)]];
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimRhsCurrentSourceNextPlan::keyValueRowKeyRtrimPatternPlan(
        $rows,
        $rows,
        $enc('module%', 2),
        2,
        $enc('module%', 2),
        2,
    ));
};

return $tests;
