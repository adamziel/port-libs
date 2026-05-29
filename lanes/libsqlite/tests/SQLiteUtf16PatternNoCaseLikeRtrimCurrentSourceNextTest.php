<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16PatternNoCaseLikeRtrimCurrentSourceNextPlan;

$tests = [];

$row = static function (int $id, string $name, string $encoding): array {
    return [
        'option_id' => $id,
        'option_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($name, $encoding),
        'text_encoding' => match ($encoding) {
            'UTF-8' => 1,
            'UTF-16LE' => 2,
            'UTF-16BE' => 3,
        },
    ];
};
$bad = static fn (int $id, string $bytes, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];
$enc = static fn (string $text, string $encoding): string => SQLiteEncodingCollationSourceCursor::encodeText($text, $encoding);

$currentRows = [
    $row(1, 'Plugin_Cache', 'UTF-16LE'),
    $row(2, 'plugin_cache  ', 'UTF-16BE'),
    $row(3, 'plugin_cache_extra', 'UTF-8'),
    $row(4, 'plugin_cache_backup', 'UTF-16LE'),
    $row(5, "plugin_cache\t", 'UTF-16BE'),
    $row(6, 'plugin_Éclair', 'UTF-16LE'),
    $bad(7, "p\0l", 2),
];
$nextRows = [
    $row(1, 'Plugin_Cache', 'UTF-16BE'),
    $row(2, 'plugin_cache', 'UTF-16BE'),
    $row(3, 'plugin_cache_extra', 'UTF-8'),
    $row(4, 'plugin_cache_backup  ', 'UTF-16LE'),
    $row(5, "plugin_cache\t", 'UTF-16BE'),
    $row(6, 'plugin_Éclair', 'UTF-16BE'),
    $row(8, 'PLUGIN_CACHE_BACKUP', 'UTF-16LE'),
    $bad(9, "\xd8\x00", 3),
];

$plan = static fn (
    string $currentPattern = 'plugin\\_cache%',
    string $currentPatternEncoding = 'UTF-16LE',
    string $nextPattern = 'plugin\\_cache\\_backup%',
    string $nextPatternEncoding = 'UTF-16BE',
    ?string $currentEscape = '\\',
    ?string $currentEscapeEncoding = 'UTF-16LE',
    ?string $nextEscape = '\\',
    ?string $nextEscapeEncoding = 'UTF-16BE',
    ?array $current = null,
    ?array $next = null,
): array => SQLiteUtf16PatternNoCaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNamePlan(
    $current ?? $currentRows,
    $next ?? $nextRows,
    $enc($currentPattern, $currentPatternEncoding),
    $currentPatternEncoding,
    $enc($nextPattern, $nextPatternEncoding),
    $nextPatternEncoding,
    $currentEscape === null ? null : $enc($currentEscape, $currentEscapeEncoding ?? $currentPatternEncoding),
    $currentEscapeEncoding,
    $nextEscape === null ? null : $enc($nextEscape, $nextEscapeEncoding ?? $nextPatternEncoding),
    $nextEscapeEncoding,
);

$valueAt = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        $value = $value[$part];
    }

    return $value;
};

$cases = [
    'status' => ['status', 'utf16-pattern-nocase-like-rtrim-current-source-next159'],
    'operator' => ['operator', 'LIKE'],
    'index collation' => ['indexCollation', 'RTRIM'],
    'residual collation' => ['residualCollation', 'NOCASE'],
    'case insensitive like' => ['caseSensitiveLike', false],
    'current source' => ['currentSource', 'main.wp_options@158'],
    'next source' => ['nextSource', 'main.wp_options@159'],
    'current schema cookie' => ['currentSchemaCookie', 158],
    'next schema cookie' => ['nextSchemaCookie', 159],
    'current decoded pattern' => ['currentPattern', 'plugin\\_cache%'],
    'next decoded pattern' => ['nextPattern', 'plugin\\_cache\\_backup%'],
    'current pattern encoding' => ['currentPatternEncoding', 'UTF-16LE'],
    'next pattern encoding' => ['nextPatternEncoding', 'UTF-16BE'],
    'current escape' => ['currentEscape', '\\'],
    'next escape' => ['nextEscape', '\\'],
    'current escape encoding' => ['currentEscapeEncoding', 'UTF-16LE'],
    'next escape encoding' => ['nextEscapeEncoding', 'UTF-16BE'],
    'current prefix' => ['currentPrefix', 'plugin_cache'],
    'next prefix' => ['nextPrefix', 'plugin_cache_backup'],
    'current range lower' => ['currentRange.lowerInclusive', 'plugin_cache'],
    'current range upper' => ['currentRange.upperBound', 'plugin_cachf'],
    'next range lower' => ['nextRange.lowerInclusive', 'plugin_cache_backup'],
    'next range upper' => ['nextRange.upperBound', 'plugin_cache_backuq'],
    'current index usable' => ['currentIndexUsable', true],
    'next index usable' => ['nextIndexUsable', true],
    'current no dangling escape' => ['currentHasDanglingEscape', false],
    'next no dangling escape' => ['nextHasDanglingEscape', false],
    'current order rowids' => ['currentOrderRowids', [1, 2, 5, 4, 3, 6]],
    'next order rowids' => ['nextOrderRowids', [8, 1, 2, 5, 4, 3, 6]],
    'current candidates include tab false positive' => ['currentCandidateRowids', [1, 2, 5, 4, 3]],
    'next candidates narrow to backup prefix' => ['nextCandidateRowids', [8, 4]],
    'current matched rows' => ['currentMatchedRowids', [1, 2, 5, 4, 3]],
    'next matched rows' => ['nextMatchedRowids', [8, 4]],
    'current false positives none for wildcard' => ['currentFalsePositiveRowids', []],
    'next false positives none for backup wildcard' => ['nextFalsePositiveRowids', []],
    'retained backup row' => ['retainedMatchedRowids', [4]],
    'entered uppercase backup row' => ['enteredMatchedRowids', [8]],
    'exited broad prefix rows' => ['exitedMatchedRowids', [1, 2, 5, 3]],
    'current text row one' => ['currentTexts.1', 'Plugin_Cache'],
    'next text row four padded' => ['nextTexts.4', 'plugin_cache_backup  '],
    'current rtrim row two' => ['currentRtrimKeys.2', 'plugin_cache'],
    'next rtrim row four' => ['nextRtrimKeys.4', 'plugin_cache_backup'],
    'current tab not rtrimmed' => ['currentRtrimKeys.5', "plugin_cache\t"],
    'current nocase ascii row one' => ['currentNoCaseKeys.1', 'plugin_cache'],
    'next nocase uppercase row eight' => ['nextNoCaseKeys.8', 'plugin_cache_backup'],
    'unicode nocase stays ascii only' => ['currentNoCaseKeys.6', 'plugin_Éclair'],
    'current residual row five true wildcard' => ['currentResidualMatches.5', true],
    'next residual row eight true nocase' => ['nextResidualMatches.8', true],
    'current malformed rowids' => ['currentMalformedRowids', [7]],
    'next malformed rowids' => ['nextMalformedRowids', [9]],
    'current malformed error' => ['currentErrors.7', 'SQLite encoding source UTF-16 text payload has an odd byte length'],
    'next malformed error' => ['nextErrors.9', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'cursor invalidated' => ['cursorInvalidated', true],
    'cursor not reusable' => ['cursorReusable', false],
    'reason source' => ['invalidationReasons.0', 'source-name'],
    'reason schema' => ['invalidationReasons.1', 'schema-cookie'],
    'reason pattern text' => ['invalidationReasons.2', 'pattern-text'],
    'reason pattern encoding' => ['invalidationReasons.3', 'pattern-encoding'],
    'reason pattern bytes' => ['invalidationReasons.4', 'pattern-bytes'],
    'reason escape encoding' => ['invalidationReasons.5', 'escape-encoding'],
    'reason escape bytes' => ['invalidationReasons.6', 'escape-bytes'],
    'reason candidate rowset' => ['invalidationReasons.7', 'candidate-rowset'],
    'reason matched rowset' => ['invalidationReasons.8', 'matched-rowset'],
    'reason malformed row text' => ['invalidationReasons.9', 'malformed-row-text'],
    'dependency pattern decode' => ['dependencies.0', 'sqlite-utf16-pattern-decode'],
    'dependency text decode' => ['dependencies.1', 'sqlite-utf16-text-decode'],
    'dependency rtrim range' => ['dependencies.2', 'sqlite-rtrim-collation-range'],
    'dependency nocase residual' => ['dependencies.3', 'sqlite-like-nocase-residual'],
    'dependency current source' => ['dependencies.4', 'sqlite-current-source-next159'],
];

foreach ($cases as $name => [$path, $expected]) {
    $tests['utf16 pattern nocase like rtrim current source next159 ' . $name] = static function (TestRunner $t) use ($plan, $valueAt, $path, $expected): void {
        $t->same($expected, $valueAt($plan(), $path));
    };
}

$tests['utf16 pattern nocase like rtrim current source next159 stable same pattern reusable'] = static function (TestRunner $t) use ($row, $enc): void {
    $rows = [
        $row(1, 'Plugin_Cache', 'UTF-16LE'),
        $row(2, 'plugin_cache  ', 'UTF-16BE'),
    ];
    $result = SQLiteUtf16PatternNoCaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNamePlan(
        $rows,
        $rows,
        $enc('plugin\\_cache%', 'UTF-16LE'),
        'UTF-16LE',
        $enc('plugin\\_cache%', 'UTF-16LE'),
        'UTF-16LE',
        $enc('\\', 'UTF-16LE'),
        'UTF-16LE',
        $enc('\\', 'UTF-16LE'),
        'UTF-16LE',
        'stable',
        'stable',
        9,
        9,
    );
    $t->same([1, 2], $result['currentMatchedRowids']);
    $t->same([1, 2], $result['nextMatchedRowids']);
    $t->same([], $result['invalidationReasons']);
    $t->same(true, $result['cursorReusable']);
};

$tests['utf16 pattern nocase like rtrim current source next159 pattern encoding change with same text invalidates bytes only'] = static function (TestRunner $t) use ($row, $enc): void {
    $rows = [$row(1, 'Plugin_Cache', 'UTF-16LE')];
    $result = SQLiteUtf16PatternNoCaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNamePlan(
        $rows,
        $rows,
        $enc('plugin\\_cache%', 'UTF-16LE'),
        'UTF-16LE',
        $enc('plugin\\_cache%', 'UTF-16BE'),
        'UTF-16BE',
        $enc('\\', 'UTF-16LE'),
        'UTF-16LE',
        $enc('\\', 'UTF-16BE'),
        'UTF-16BE',
        'stable',
        'stable',
        9,
        9,
    );
    $t->same('plugin\\_cache%', $result['nextPattern']);
    $t->same(['pattern-encoding', 'pattern-bytes', 'escape-encoding', 'escape-bytes'], $result['invalidationReasons']);
};

$tests['utf16 pattern nocase like rtrim current source next159 dangling next escape disables next residual'] = static function (TestRunner $t) use ($plan): void {
    $result = $plan('plugin\\_cache%', 'UTF-16LE', 'plugin\\_cache\\', 'UTF-16BE');
    $t->same(false, $result['currentHasDanglingEscape']);
    $t->same(true, $result['nextHasDanglingEscape']);
    $t->same(false, $result['nextIndexUsable']);
    $t->same([], $result['nextMatchedRowids']);
};

$tests['utf16 pattern nocase like rtrim current source next159 null escape uses wildcard underscore'] = static function (TestRunner $t) use ($plan): void {
    $result = $plan('plugin_cache%', 'UTF-16LE', 'plugin_cache%', 'UTF-16LE', null, null, null, null);
    $t->same(null, $result['currentEscape']);
    $t->same('plugin', $result['currentPrefix']);
    $t->same([1, 2, 5, 4, 3], $result['currentMatchedRowids']);
};

$tests['utf16 pattern nocase like rtrim current source next159 rejects malformed pattern bytes'] = static function (TestRunner $t) use ($currentRows, $nextRows, $enc): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16PatternNoCaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNamePlan(
        $currentRows,
        $nextRows,
        "p\0l",
        'UTF-16LE',
        $enc('plugin%', 'UTF-16LE'),
        'UTF-16LE',
    ));
};

$tests['utf16 pattern nocase like rtrim current source next159 rejects malformed escape bytes'] = static function (TestRunner $t) use ($currentRows, $nextRows, $enc): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16PatternNoCaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNamePlan(
        $currentRows,
        $nextRows,
        $enc('plugin%', 'UTF-16LE'),
        'UTF-16LE',
        $enc('plugin%', 'UTF-16LE'),
        'UTF-16LE',
        "p\0l",
        'UTF-16LE',
    ));
};

$tests['utf16 pattern nocase like rtrim current source next159 rejects multi character escape'] = static function (TestRunner $t) use ($currentRows, $nextRows, $enc): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16PatternNoCaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNamePlan(
        $currentRows,
        $nextRows,
        $enc('plugin%', 'UTF-16LE'),
        'UTF-16LE',
        $enc('plugin%', 'UTF-16LE'),
        'UTF-16LE',
        $enc('xx', 'UTF-16LE'),
        'UTF-16LE',
    ));
};

return $tests;
