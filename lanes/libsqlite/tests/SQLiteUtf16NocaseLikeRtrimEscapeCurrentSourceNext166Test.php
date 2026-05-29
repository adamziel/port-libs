<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimEscapeCurrentSourceNextPlan;

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
    $row(3, "plugin_cache\t", 2),
    $row(4, 'plugin!cache', 1),
    $row(5, 'plugin_cache_miss', 3),
    $row(6, 'plugin_config', 2),
    $row(7, 'plugin_Æther', 2),
    $row(8, 'theme_cache', 2),
    $bad(9, "p\0l", 2),
];
$nextRows = [
    $row(1, 'plugin_cache', 2),
    $row(2, 'PLUGIN_CACHE  ', 3),
    $row(3, "plugin_cache\t", 2),
    $row(4, 'plugin!cache', 1),
    $row(5, 'plugin_cache_miss', 3),
    $row(6, 'plugin_config_v2', 2),
    $row(7, 'plugin_æther', 2),
    $row(10, 'plugin_cache_new  ', 3),
    $bad(11, "\x00\xd8", 2),
];

$plan = static fn (
    string $currentPattern = 'plugin!_cache%   ',
    int $currentPatternEncoding = 2,
    string $nextPattern = 'plugin!_cache% ',
    int $nextPatternEncoding = 3,
    string $currentEscape = '!   ',
    int $currentEscapeEncoding = 2,
    string $nextEscape = '! ',
    int $nextEscapeEncoding = 3,
    ?array $current = null,
    ?array $next = null,
    string $currentSource = 'main.wp_options@165',
    string $nextSource = 'main.wp_options@166',
    int $currentCookie = 165,
    int $nextCookie = 166,
): array => SQLiteUtf16NocaseLikeRtrimEscapeCurrentSourceNextPlan::wordpressOptionNameEscapePlan(
    $current ?? $currentRows,
    $next ?? $nextRows,
    $enc($currentPattern, $currentPatternEncoding),
    $currentPatternEncoding,
    $enc($nextPattern, $nextPatternEncoding),
    $nextPatternEncoding,
    $enc($currentEscape, $currentEscapeEncoding),
    $currentEscapeEncoding,
    $enc($nextEscape, $nextEscapeEncoding),
    $nextEscapeEncoding,
    $currentSource,
    $nextSource,
    $currentCookie,
    $nextCookie,
);

$valueAt = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        if (is_array($value) && ctype_digit($part)) {
            $part = (int) $part;
        }
        $value = $value[$part];
    }

    return $value;
};

$cases = [
    'status' => ['status', 'utf16-nocase-like-rtrim-escape-current-source-next166'],
    'operator' => ['operator', 'LIKE'],
    'expression' => ['expression', 'rtrim(option_name) COLLATE NOCASE LIKE rtrim(?) ESCAPE rtrim(?)'],
    'collation' => ['collation', 'NOCASE'],
    'case sensitive false' => ['caseSensitiveLike', false],
    'current source' => ['currentSource', 'main.wp_options@165'],
    'next source' => ['nextSource', 'main.wp_options@166'],
    'current cookie' => ['currentSchemaCookie', 165],
    'next cookie' => ['nextSchemaCookie', 166],
    'current pattern' => ['currentPattern', 'plugin!_cache%   '],
    'next pattern' => ['nextPattern', 'plugin!_cache% '],
    'current trimmed pattern' => ['currentTrimmedPattern', 'plugin!_cache%'],
    'next trimmed pattern' => ['nextTrimmedPattern', 'plugin!_cache%'],
    'current escape' => ['currentEscape', '!   '],
    'next escape' => ['nextEscape', '! '],
    'current trimmed escape' => ['currentTrimmedEscape', '!'],
    'next trimmed escape' => ['nextTrimmedEscape', '!'],
    'current escape encoding' => ['currentEscapeEncoding', 'UTF-16LE'],
    'next escape encoding' => ['nextEscapeEncoding', 'UTF-16BE'],
    'current escape bytes' => ['currentEscapeBytesHex', '2100200020002000'],
    'next escape bytes' => ['nextEscapeBytesHex', '00210020'],
    'current trimmed escape bytes' => ['currentTrimmedEscapeBytesHex', '2100'],
    'next trimmed escape bytes' => ['nextTrimmedEscapeBytesHex', '0021'],
    'current prefix' => ['currentPrefix', 'plugin_cache'],
    'next prefix current escape' => ['nextPrefixWithCurrentEscape', 'plugin_cache'],
    'next prefix next escape' => ['nextPrefixWithNextEscape', 'plugin_cache'],
    'current lower' => ['currentRange.lowerInclusive', 'plugin_cache'],
    'current upper' => ['currentRange.upperBound', 'plugin_cachf'],
    'next lower' => ['nextRangeWithNextEscape.lowerInclusive', 'plugin_cache'],
    'current index usable' => ['currentIndexUsable', true],
    'next index usable current escape' => ['nextIndexUsableWithCurrentEscape', true],
    'next index usable next escape' => ['nextIndexUsableWithNextEscape', true],
    'current candidates' => ['currentCandidateRowids', [1, 2, 3, 5]],
    'next candidates current escape' => ['nextCandidateRowidsWithCurrentEscape', [1, 2, 3, 5, 10]],
    'next candidates next escape' => ['nextCandidateRowidsWithNextEscape', [1, 2, 3, 5, 10]],
    'current matched' => ['currentMatchedRowids', [1, 2, 3, 5]],
    'next matched current escape' => ['nextMatchedRowidsWithCurrentEscape', [1, 2, 3, 5, 10]],
    'next matched next escape' => ['nextMatchedRowidsWithNextEscape', [1, 2, 3, 5, 10]],
    'retained matched' => ['retainedMatchedRowids', [1, 2, 3, 5]],
    'entered matched current escape' => ['enteredMatchedRowidsWithCurrentEscape', [10]],
    'entered matched next escape' => ['enteredMatchedRowidsWithNextEscape', [10]],
    'current false positives' => ['currentFalsePositiveRowids', []],
    'next false positives current escape' => ['nextFalsePositiveRowidsWithCurrentEscape', []],
    'next false positives next escape' => ['nextFalsePositiveRowidsWithNextEscape', []],
    'current malformed' => ['currentMalformedRowids', [9]],
    'next malformed' => ['nextMalformedRowids', [11]],
    'current error' => ['currentErrors.9', 'SQLite encoding source UTF-16 text payload has an odd byte length'],
    'next error' => ['nextErrors.11', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'base reason source' => ['baseInvalidationReasons.0', 'source-name'],
    'base reason schema' => ['baseInvalidationReasons.1', 'schema-cookie'],
    'escape byte reason encoding' => ['escapeByteReasons.0', 'escape-encoding'],
    'escape byte reason bytes' => ['escapeByteReasons.1', 'escape-bytes'],
    'escape byte reason trimmed bytes' => ['escapeByteReasons.2', 'rtrim-escape-bytes'],
    'semantic source' => ['semanticInvalidationReasons.0', 'source-name'],
    'semantic matched' => ['semanticInvalidationReasons.7', 'matched-rowset'],
    'same trimmed escape' => ['sameTrimmedEscape', true],
    'byte only false' => ['byteOnlyReprepare', false],
    'invalidated' => ['cursorInvalidated', true],
    'not reusable' => ['cursorReusable', false],
    'escape trims only space' => ['rtrimEscapeTrimsOnlyAsciiSpace', true],
    'escape single char' => ['escapeMustBeSingleCharacterAfterRtrim', true],
    'nocase ascii only' => ['nocaseFoldsAsciiOnly', true],
    'dependency escape decode' => ['dependencies.1', 'sqlite-utf16-escape-decode'],
    'dependency escape expression' => ['dependencies.3', 'sqlite-rtrim-escape-expression'],
    'dependency current source' => ['dependencies.5', 'sqlite-current-source-next166'],
];

foreach ($cases as $name => [$path, $expected]) {
    $tests['utf16 nocase like rtrim escape current source next166 ' . $name] = static function (TestRunner $t) use ($plan, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($plan(), $path));
    };
}

$tests['utf16 nocase like rtrim escape current source next166 byte only escape reprepare is reusable'] = static function (TestRunner $t) use ($row, $enc): void {
    $rows = [$row(1, 'Plugin_Cache  ', 2), $row(2, 'plugin_cache', 3)];
    $result = SQLiteUtf16NocaseLikeRtrimEscapeCurrentSourceNextPlan::wordpressOptionNameEscapePlan(
        $rows,
        $rows,
        $enc('plugin!_cache% ', 2),
        2,
        $enc('plugin!_cache% ', 2),
        2,
        $enc('!   ', 2),
        2,
        $enc('! ', 3),
        3,
        'stable',
        'stable',
        5,
        5,
    );
    $t->same(['escape-encoding', 'escape-bytes', 'rtrim-escape-bytes'], $result['escapeByteReasons']);
    $t->same([], $result['semanticInvalidationReasons']);
    $t->same(true, $result['byteOnlyReprepare']);
    $t->same(true, $result['cursorReusable']);
};

$tests['utf16 nocase like rtrim escape current source next166 semantic escape change replans wildcard prefix'] = static function (TestRunner $t) use ($plan): void {
    $result = $plan('plugin!%%', 2, 'plugin!%%', 2, '! ', 2, '# ', 2);
    $t->same('!', $result['currentTrimmedEscape']);
    $t->same('#', $result['nextTrimmedEscape']);
    $t->same('plugin%', $result['nextPrefixWithCurrentEscape']);
    $t->same('plugin!', $result['nextPrefixWithNextEscape']);
    $t->same([4], $result['nextMatchedRowidsWithNextEscape']);
    $t->same(true, in_array('rtrim-escape-text', $result['semanticInvalidationReasons'], true));
    $t->same(true, in_array('escape-matched-rowset', $result['semanticInvalidationReasons'], true));
};

$tests['utf16 nocase like rtrim escape current source next166 escape tabs are not trimmed'] = static function (TestRunner $t) use ($currentRows, $nextRows, $enc): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimEscapeCurrentSourceNextPlan::wordpressOptionNameEscapePlan(
        $currentRows,
        $nextRows,
        $enc('plugin!_cache%', 2),
        2,
        $enc('plugin!_cache%', 2),
        2,
        $enc("!\t", 2),
        2,
        $enc('! ', 2),
        2,
    ));
};

$tests['utf16 nocase like rtrim escape current source next166 rejects malformed escape bytes'] = static function (TestRunner $t) use ($currentRows, $nextRows, $enc): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimEscapeCurrentSourceNextPlan::wordpressOptionNameEscapePlan(
        $currentRows,
        $nextRows,
        $enc('plugin!_cache%', 2),
        2,
        $enc('plugin!_cache%', 2),
        2,
        "!\0x",
        2,
        $enc('! ', 2),
        2,
    ));
};

$tests['utf16 nocase like rtrim escape current source next166 rejects empty escape after rtrim'] = static function (TestRunner $t) use ($currentRows, $nextRows, $enc): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimEscapeCurrentSourceNextPlan::wordpressOptionNameEscapePlan(
        $currentRows,
        $nextRows,
        $enc('plugin!_cache%', 2),
        2,
        $enc('plugin!_cache%', 2),
        2,
        $enc('   ', 2),
        2,
        $enc('! ', 2),
        2,
    ));
};

return $tests;
