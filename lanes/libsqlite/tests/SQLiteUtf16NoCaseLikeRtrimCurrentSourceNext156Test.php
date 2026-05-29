<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

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

$currentRows = [
    $row(1, 'Plugin_Cache', 'UTF-16LE'),
    $row(2, 'plugin_cache', 'UTF-16BE'),
    $row(3, 'plugin_cache  ', 'UTF-16LE'),
    $row(4, "plugin_cache\t", 'UTF-8'),
    $row(5, 'plugin_cache_extra', 'UTF-16BE'),
    $row(6, 'plugin_Éclair', 'UTF-16LE'),
    $row(7, 'plugin_éclair', 'UTF-16BE'),
    $row(8, 'theme_cache', 'UTF-8'),
    $bad(9, "p\0l", 2),
];
$nextRows = [
    $row(1, 'Plugin_Cache', 'UTF-16BE'),
    $row(2, 'plugin_cache ', 'UTF-16BE'),
    $row(3, 'plugin_cache', 'UTF-16LE'),
    $row(4, "plugin_cache\t", 'UTF-8'),
    $row(5, 'plugin_cache_extra', 'UTF-16BE'),
    $row(6, 'plugin_Éclair_v2', 'UTF-16LE'),
    $row(7, 'plugin_éclair', 'UTF-16LE'),
    $row(10, 'PLUGIN_CACHE', 'UTF-16LE'),
    $bad(11, "\xd8\x00", 3),
];

$plan = static fn (
    string $pattern = 'plugin\\_cache',
    ?string $escape = '\\',
    ?array $current = null,
    ?array $next = null,
    string $currentSource = 'main.wp_options@155',
    string $nextSource = 'main.wp_options@156',
    int $currentCookie = 155,
    int $nextCookie = 156,
): array => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameNoCasePlan(
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
    'status' => ['plugin\\_cache', '\\', 'status', 'utf16-nocase-like-rtrim-current-source-next156'],
    'operator' => ['plugin\\_cache', '\\', 'operator', 'LIKE'],
    'index collation' => ['plugin\\_cache', '\\', 'indexCollation', 'RTRIM'],
    'residual collation' => ['plugin\\_cache', '\\', 'residualCollation', 'NOCASE'],
    'case insensitive like' => ['plugin\\_cache', '\\', 'caseSensitiveLike', false],
    'prefix' => ['plugin\\_cache', '\\', 'prefix', 'plugin_cache'],
    'prefix characters' => ['plugin\\_cache', '\\', 'prefixCharacters', 12],
    'prefix ascii' => ['plugin\\_cache', '\\', 'prefixIsAscii', true],
    'no wildcard' => ['plugin\\_cache', '\\', 'hasWildcard', false],
    'not dangling escape' => ['plugin\\_cache', '\\', 'hasDanglingEscape', false],
    'range lower' => ['plugin\\_cache', '\\', 'rtrimRange.lowerInclusive', 'plugin_cache'],
    'range upper' => ['plugin\\_cache', '\\', 'rtrimRange.upperBound', 'plugin_cachf'],
    'index usable' => ['plugin\\_cache', '\\', 'indexUsable', true],
    'residual untrimmed' => ['plugin\\_cache', '\\', 'residualUsesUntrimmedText', true],
    'residual ascii nocase' => ['plugin\\_cache', '\\', 'residualUsesAsciiNoCase', true],
    'current order rowids' => ['plugin\\_cache', '\\', 'currentOrderRowids', [1, 2, 3, 4, 5, 6, 7, 8]],
    'next order rowids' => ['plugin\\_cache', '\\', 'nextOrderRowids', [10, 1, 2, 3, 4, 5, 6, 7]],
    'current candidates include padded rtrim row' => ['plugin\\_cache', '\\', 'currentCandidateRowids', [1, 2, 3, 4, 5]],
    'next candidates include upper nocase row' => ['plugin\\_cache', '\\', 'nextCandidateRowids', [10, 1, 2, 3, 4, 5]],
    'current matched excludes padded row' => ['plugin\\_cache', '\\', 'currentMatchedRowids', [1, 2]],
    'next matched admits ascii case row' => ['plugin\\_cache', '\\', 'nextMatchedRowids', [10, 1, 3]],
    'current false positives are rtrim-only' => ['plugin\\_cache', '\\', 'currentFalsePositiveRowids', [3, 4, 5]],
    'next false positives are padded rtrim-only' => ['plugin\\_cache', '\\', 'nextFalsePositiveRowids', [2, 4, 5]],
    'retained matches' => ['plugin\\_cache', '\\', 'retainedMatchedRowids', [1]],
    'entered matches' => ['plugin\\_cache', '\\', 'enteredMatchedRowids', [10, 3]],
    'exited matches' => ['plugin\\_cache', '\\', 'exitedMatchedRowids', [2]],
    'current row one text' => ['plugin\\_cache', '\\', 'currentTexts.1', 'Plugin_Cache'],
    'next row two text padded' => ['plugin\\_cache', '\\', 'nextTexts.2', 'plugin_cache '],
    'current rtrim row three' => ['plugin\\_cache', '\\', 'currentRtrimKeys.3', 'plugin_cache'],
    'current tab not rtrimmed' => ['plugin\\_cache', '\\', 'currentRtrimKeys.4', "plugin_cache\t"],
    'current nocase ascii row one' => ['plugin\\_cache', '\\', 'currentNoCaseKeys.1', 'plugin_cache'],
    'unicode nocase remains ascii only' => ['plugin\\_cache', '\\', 'currentNoCaseKeys.6', 'plugin_Éclair'],
    'row one encoding changed' => ['plugin\\_cache', '\\', 'nextEncodings.1', 'UTF-16BE'],
    'row one current bytes' => ['plugin\\_cache', '\\', 'currentBytesHex.1', '50006c007500670069006e005f0043006100630068006500'],
    'row one next bytes' => ['plugin\\_cache', '\\', 'nextBytesHex.1', '0050006c007500670069006e005f00430061006300680065'],
    'current residual row three false' => ['plugin\\_cache', '\\', 'currentResidualMatches.3', false],
    'next residual row ten true' => ['plugin\\_cache', '\\', 'nextResidualMatches.10', true],
    'current malformed rowids' => ['plugin\\_cache', '\\', 'currentMalformedRowids', [9]],
    'next malformed rowids' => ['plugin\\_cache', '\\', 'nextMalformedRowids', [11]],
    'current malformed odd error' => ['plugin\\_cache', '\\', 'currentErrors.9', 'SQLite encoding source UTF-16 text payload has an odd byte length'],
    'next malformed surrogate error' => ['plugin\\_cache', '\\', 'nextErrors.11', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'changed text rowids' => ['plugin\\_cache', '\\', 'changedTextRowids', [2, 3, 6]],
    'changed rtrim key rowids' => ['plugin\\_cache', '\\', 'changedRtrimKeyRowids', [6]],
    'changed nocase rowids' => ['plugin\\_cache', '\\', 'changedNoCaseKeyRowids', [2, 3, 6]],
    'changed encoding rowids' => ['plugin\\_cache', '\\', 'changedEncodingRowids', [1, 7]],
    'changed bytes rowids' => ['plugin\\_cache', '\\', 'changedBytesRowids', [1, 2, 3, 6, 7]],
    'invalidated' => ['plugin\\_cache', '\\', 'cursorInvalidated', true],
    'not reusable' => ['plugin\\_cache', '\\', 'cursorReusable', false],
    'reason source' => ['plugin\\_cache', '\\', 'invalidationReasons.0', 'source-name'],
    'reason schema' => ['plugin\\_cache', '\\', 'invalidationReasons.1', 'schema-cookie'],
    'reason malformed' => ['plugin\\_cache', '\\', 'invalidationReasons.2', 'malformed-text'],
    'reason text' => ['plugin\\_cache', '\\', 'invalidationReasons.3', 'text-value'],
    'reason rtrim' => ['plugin\\_cache', '\\', 'invalidationReasons.4', 'rtrim-key'],
    'reason nocase' => ['plugin\\_cache', '\\', 'invalidationReasons.5', 'nocase-key'],
    'reason encoding' => ['plugin\\_cache', '\\', 'invalidationReasons.6', 'text-encoding'],
    'reason bytes' => ['plugin\\_cache', '\\', 'invalidationReasons.7', 'encoded-bytes'],
    'reason candidates' => ['plugin\\_cache', '\\', 'invalidationReasons.8', 'candidate-rowset'],
    'reason matches' => ['plugin\\_cache', '\\', 'invalidationReasons.9', 'matched-rowset'],
    'dependency decode' => ['plugin\\_cache', '\\', 'dependencies.0', 'sqlite-utf16-text-decode'],
    'dependency rtrim range' => ['plugin\\_cache', '\\', 'dependencies.1', 'sqlite-rtrim-collation-range'],
    'dependency nocase residual' => ['plugin\\_cache', '\\', 'dependencies.2', 'sqlite-like-nocase-residual'],
    'dependency current source' => ['plugin\\_cache', '\\', 'dependencies.3', 'sqlite-current-source-next156'],
    'wildcard current admits padded' => ['plugin\\_cache%', '\\', 'currentMatchedRowids', [1, 2, 3, 4, 5]],
    'wildcard next admits padded and upper' => ['plugin\\_cache%', '\\', 'nextMatchedRowids', [10, 1, 2, 3, 4, 5]],
    'unicode pattern lower' => ['plugin\\_éclair', '\\', 'rtrimRange.lowerInclusive', 'plugin_éclair'],
    'unicode pattern current matches lowercase only' => ['plugin\\_éclair', '\\', 'currentMatchedRowids', [7]],
    'unicode pattern next matches lowercase only' => ['plugin\\_éclair', '\\', 'nextMatchedRowids', [7]],
];

foreach ($cases as $name => [$pattern, $escape, $path, $expected]) {
    $tests['utf16 nocase like rtrim current source next156 ' . $name] = static function (TestRunner $t) use ($plan, $valueAt, $pattern, $escape, $path, $expected): void {
        $t->same($expected, $valueAt($plan($pattern, $escape), $path));
    };
}

$tests['utf16 nocase like rtrim current source next156 stable identical rows reusable'] = static function (TestRunner $t) use ($row): void {
    $rows = [
        $row(1, 'Plugin_Cache', 'UTF-16LE'),
        $row(2, 'plugin_cache  ', 'UTF-16BE'),
    ];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameNoCasePlan($rows, $rows, 'plugin\\_cache%', '\\', 'stable', 'stable', 7, 7);
    $t->same([1, 2], $result['currentMatchedRowids']);
    $t->same([], $result['invalidationReasons']);
    $t->same(true, $result['cursorReusable']);
};

$tests['utf16 nocase like rtrim current source next156 dangling escape disables residual'] = static function (TestRunner $t) use ($plan): void {
    $result = $plan('plugin\\_cache\\', '\\');
    $t->same(true, $result['hasDanglingEscape']);
    $t->same(false, $result['indexUsable']);
    $t->same([], $result['currentMatchedRowids']);
    $t->same('dangling-escape', $result['invalidationReasons'][2]);
};

$tests['utf16 nocase like rtrim current source next156 rejects invalid escape length'] = static function (TestRunner $t) use ($plan): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan('plugin%', 'xx'));
};

$tests['utf16 nocase like rtrim current source next156 rejects missing option bytes'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameNoCasePlan([['option_id' => 1, 'text_encoding' => 2]], $nextRows, 'plugin%'));
};

$tests['utf16 nocase like rtrim current source next156 rejects non integer rowid'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameNoCasePlan([['option_id' => '1', 'option_name_bytes' => 'p', 'text_encoding' => 1]], $nextRows, 'plugin%'));
};

$tests['utf16 nocase like rtrim current source next156 records unknown encoding name'] = static function (TestRunner $t) use ($bad, $nextRows): void {
    $rows = [$bad(1, 'plugin_cache', 99)];
    $result = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::wordpressOptionNameNoCasePlan($rows, $nextRows, 'plugin%');
    $t->same([1], $result['currentMalformedRowids']);
    $t->same('SQLite text encoding must be UTF-8, UTF-16LE, or UTF-16BE', $result['currentErrors'][1]);
};

return $tests;
