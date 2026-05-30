<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteEncodingLikeGlobRtrimCurrentSourceNextPlan;

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
    $row(1, 'plugin_cache', 'UTF-8'),
    $row(2, 'plugin_cache  ', 'UTF-16LE'),
    $row(3, "plugin_cache\t", 'UTF-16BE'),
    $row(4, 'plugin_cache_extra', 'UTF-8'),
    $row(5, 'plugin_100%_enabled ', 'UTF-16LE'),
    $row(6, 'Plugin_Cache', 'UTF-8'),
    $row(7, 'plugin_éclair ', 'UTF-16BE'),
    $row(8, 'plugin_éclair_extra', 'UTF-16LE'),
    $row(9, 'theme_cache', 'UTF-8'),
    $bad(10, "p\x00l\x00u\x00g\x00i\x00n\x00_\x00c", 2),
];
$nextRows = [
    $row(1, 'plugin_cache ', 'UTF-16BE'),
    $row(2, 'plugin_cache', 'UTF-16LE'),
    $row(3, "plugin_cache\t", 'UTF-16BE'),
    $row(4, 'plugin_cache_extra_v2', 'UTF-8'),
    $row(5, 'plugin_100%_enabled', 'UTF-16BE'),
    $row(6, 'Plugin_Cache', 'UTF-8'),
    $row(7, 'plugin_éclair', 'UTF-16BE'),
    $row(8, 'plugin_éclair_extra', 'UTF-16LE'),
    $row(11, 'plugin_cache_new', 'UTF-8'),
    $bad(12, "\x3d\xd8", 2),
];

$plan = static fn (
    string $operator = 'LIKE',
    string $pattern = 'plugin\\_cache',
    ?string $escape = '\\',
    ?array $current = null,
    ?array $next = null,
    string $currentSource = 'main.wp_options@139',
    string $nextSource = 'main.wp_options@140',
    int $currentCookie = 139,
    int $nextCookie = 140,
): array => SQLiteEncodingLikeGlobRtrimCurrentSourceNextPlan::keyValueRowKeyPlan(
    $current ?? $currentRows,
    $next ?? $nextRows,
    $operator,
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
    'like operator' => ['LIKE', 'plugin\\_cache', '\\', 'operator', 'LIKE'],
    'expression recorded' => ['LIKE', 'plugin\\_cache', '\\', 'expression', 'rtrim(option_name)'],
    'collation recorded' => ['LIKE', 'plugin\\_cache', '\\', 'collation', 'RTRIM'],
    'like range lower' => ['LIKE', 'plugin\\_cache', '\\', 'range.lowerInclusive', 'plugin_cache'],
    'like range upper' => ['LIKE', 'plugin\\_cache', '\\', 'range.upperBound', 'plugin_cachf'],
    'like index usable' => ['LIKE', 'plugin\\_cache', '\\', 'indexUsable', true],
    'residual uses untrimmed text' => ['LIKE', 'plugin\\_cache', '\\', 'residualUsesUntrimmedText', true],
    'rtrim trims only spaces' => ['LIKE', 'plugin\\_cache', '\\', 'rtrimTrimsOnlyAsciiSpace', true],
    'current order rowids' => ['LIKE', 'plugin\\_cache', '\\', 'currentOrderRowids', [6, 5, 1, 2, 3, 4, 7, 8, 9]],
    'next order rowids' => ['LIKE', 'plugin\\_cache', '\\', 'nextOrderRowids', [6, 5, 1, 2, 3, 4, 11, 7, 8]],
    'current candidates include rtrim padded and prefix peers' => ['LIKE', 'plugin\\_cache', '\\', 'currentCandidateRowids', [1, 2, 3, 4]],
    'next candidates include trimmed repair and prefix peers' => ['LIKE', 'plugin\\_cache', '\\', 'nextCandidateRowids', [1, 2, 3, 4, 11]],
    'current matched only exact untrimmed' => ['LIKE', 'plugin\\_cache', '\\', 'currentMatchedRowids', [1]],
    'next matched row two repaired' => ['LIKE', 'plugin\\_cache', '\\', 'nextMatchedRowids', [2]],
    'current false positive padded and prefix peers' => ['LIKE', 'plugin\\_cache', '\\', 'currentFalsePositiveRowids', [2, 3, 4]],
    'next false positive source switched and prefix peers' => ['LIKE', 'plugin\\_cache', '\\', 'nextFalsePositiveRowids', [1, 3, 4, 11]],
    'retained matched empty' => ['LIKE', 'plugin\\_cache', '\\', 'retainedMatchedRowids', []],
    'entered matched row two' => ['LIKE', 'plugin\\_cache', '\\', 'enteredMatchedRowids', [2]],
    'exited matched row one' => ['LIKE', 'plugin\\_cache', '\\', 'exitedMatchedRowids', [1]],
    'row two current rtrim key' => ['LIKE', 'plugin\\_cache', '\\', 'currentRtrimKeys.2', 'plugin_cache'],
    'row three tab not trimmed' => ['LIKE', 'plugin\\_cache', '\\', 'currentRtrimKeys.3', "plugin_cache\t"],
    'row one encoding changed' => ['LIKE', 'plugin\\_cache', '\\', 'nextEncodings.1', 'UTF-16BE'],
    'row two current utf16le bytes' => ['LIKE', 'plugin\\_cache', '\\', 'currentBytesHex.2', '70006c007500670069006e005f006300610063006800650020002000'],
    'row one next utf16be bytes' => ['LIKE', 'plugin\\_cache', '\\', 'nextBytesHex.1', '0070006c007500670069006e005f006300610063006800650020'],
    'current malformed rowids' => ['LIKE', 'plugin\\_cache', '\\', 'currentMalformedRowids', [10]],
    'next malformed rowids' => ['LIKE', 'plugin\\_cache', '\\', 'nextMalformedRowids', [12]],
    'current malformed error' => ['LIKE', 'plugin\\_cache', '\\', 'currentErrors.10', 'SQLite encoding source UTF-16 text payload has an odd byte length'],
    'next malformed error' => ['LIKE', 'plugin\\_cache', '\\', 'nextErrors.12', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'changed text rowids' => ['LIKE', 'plugin\\_cache', '\\', 'changedTextRowids', [1, 2, 4, 5, 7]],
    'changed encoding rowids' => ['LIKE', 'plugin\\_cache', '\\', 'changedEncodingRowids', [1, 5]],
    'changed bytes rowids' => ['LIKE', 'plugin\\_cache', '\\', 'changedBytesRowids', [1, 2, 4, 5, 7]],
    'changed rtrim rowids' => ['LIKE', 'plugin\\_cache', '\\', 'changedRtrimKeyRowids', [4]],
    'cursor invalidated' => ['LIKE', 'plugin\\_cache', '\\', 'cursorInvalidated', true],
    'cursor not reusable' => ['LIKE', 'plugin\\_cache', '\\', 'cursorReusable', false],
    'reason source' => ['LIKE', 'plugin\\_cache', '\\', 'invalidationReasons.0', 'source-name'],
    'reason schema' => ['LIKE', 'plugin\\_cache', '\\', 'invalidationReasons.1', 'schema-cookie'],
    'reason malformed' => ['LIKE', 'plugin\\_cache', '\\', 'invalidationReasons.2', 'malformed-text'],
    'reason text value' => ['LIKE', 'plugin\\_cache', '\\', 'invalidationReasons.3', 'text-value'],
    'reason text encoding' => ['LIKE', 'plugin\\_cache', '\\', 'invalidationReasons.4', 'text-encoding'],
    'reason encoded bytes' => ['LIKE', 'plugin\\_cache', '\\', 'invalidationReasons.5', 'encoded-bytes'],
    'reason rtrim key' => ['LIKE', 'plugin\\_cache', '\\', 'invalidationReasons.6', 'rtrim-key'],
    'reason candidate rowset' => ['LIKE', 'plugin\\_cache', '\\', 'invalidationReasons.7', 'candidate-rowset'],
    'reason matched rowset' => ['LIKE', 'plugin\\_cache', '\\', 'invalidationReasons.8', 'matched-rowset'],
    'dependency encoding cursor' => ['LIKE', 'plugin\\_cache', '\\', 'dependencies.0', 'sqlite-encoding-source-cursor'],
    'dependency rtrim index' => ['LIKE', 'plugin\\_cache', '\\', 'dependencies.1', 'sqlite-rtrim-expression-index'],
    'dependency residual' => ['LIKE', 'plugin\\_cache', '\\', 'dependencies.2', 'sqlite-like-glob-binary-residual'],
    'dependency current source' => ['LIKE', 'plugin\\_cache', '\\', 'dependencies.3', 'sqlite-current-source-next140'],
    'escaped percent lower' => ['LIKE', 'plugin\\_100\\%\\_enabled', '\\', 'range.lowerInclusive', 'plugin_100%_enabled'],
    'escaped percent current candidate' => ['LIKE', 'plugin\\_100\\%\\_enabled', '\\', 'currentCandidateRowids', [5]],
    'escaped percent current false positive' => ['LIKE', 'plugin\\_100\\%\\_enabled', '\\', 'currentFalsePositiveRowids', [5]],
    'escaped percent next match' => ['LIKE', 'plugin\\_100\\%\\_enabled', '\\', 'nextMatchedRowids', [5]],
    'like wildcard current rowids' => ['LIKE', 'plugin\\_cache%', '\\', 'currentMatchedRowids', [1, 2, 3, 4]],
    'like wildcard next rowids' => ['LIKE', 'plugin\\_cache%', '\\', 'nextMatchedRowids', [1, 2, 3, 4, 11]],
    'like wildcard candidate includes new row' => ['LIKE', 'plugin\\_cache%', '\\', 'nextCandidateRowids', [1, 2, 3, 4, 11]],
    'glob operator' => ['GLOB', 'plugin_cache', null, 'operator', 'GLOB'],
    'glob range lower' => ['GLOB', 'plugin_cache', null, 'range.lowerInclusive', 'plugin_cache'],
    'glob exact current candidates' => ['GLOB', 'plugin_cache', null, 'currentCandidateRowids', [1, 2, 3, 4]],
    'glob exact current residual false positive' => ['GLOB', 'plugin_cache', null, 'currentFalsePositiveRowids', [2, 3, 4]],
    'glob exact next matched repaired row' => ['GLOB', 'plugin_cache', null, 'nextMatchedRowids', [2]],
    'glob wildcard current rowids' => ['GLOB', 'plugin_cache*', null, 'currentMatchedRowids', [1, 2, 3, 4]],
    'glob wildcard next rowids' => ['GLOB', 'plugin_cache*', null, 'nextMatchedRowids', [1, 2, 3, 4, 11]],
    'glob unicode range current' => ['GLOB', 'plugin_éclair*', null, 'currentMatchedRowids', [7, 8]],
    'glob unicode range next' => ['GLOB', 'plugin_éclair*', null, 'nextMatchedRowids', [7, 8]],
];

foreach ($cases as $name => [$operator, $pattern, $escape, $path, $expected]) {
    $tests['encoding like glob rtrim current source next140 ' . $name] = static function (TestRunner $t) use ($plan, $valueAt, $operator, $pattern, $escape, $path, $expected): void {
        $t->same($expected, $valueAt($plan($operator, $pattern, $escape), $path));
    };
}

$tests['encoding like glob rtrim current source next140 stable identical rows are reusable'] = static function (TestRunner $t) use ($row, $plan): void {
    $rows = [$row(1, 'plugin_cache', 'UTF-8'), $row(2, 'plugin_cache ', 'UTF-16LE')];
    $result = $plan('LIKE', 'plugin\\_cache', '\\', $rows, $rows, 'stable', 'stable', 7, 7);
    $t->same([1, 2], $result['currentCandidateRowids']);
    $t->same([1], $result['currentMatchedRowids']);
    $t->same([2], $result['currentFalsePositiveRowids']);
    $t->same([], $result['invalidationReasons']);
    $t->same(true, $result['cursorReusable']);
};

$tests['encoding like glob rtrim current source next140 leading wildcard disables prefix range'] = static function (TestRunner $t) use ($plan): void {
    $result = $plan('LIKE', '%cache', null);
    $t->same(null, $result['range']);
    $t->same(false, $result['indexUsable']);
    $t->same([], $result['currentCandidateRowids']);
    $t->same('no-prefix-range', $result['invalidationReasons'][2]);
};

$tests['encoding like glob rtrim current source next140 rejects glob escape'] = static function (TestRunner $t) use ($plan): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan('GLOB', 'plugin*', '\\'));
};

$tests['encoding like glob rtrim current source next140 rejects unsupported operator'] = static function (TestRunner $t) use ($plan): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan('REGEXP', 'plugin'));
};

$tests['encoding like glob rtrim current source next140 rejects missing option bytes'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingLikeGlobRtrimCurrentSourceNextPlan::keyValueRowKeyPlan([['option_id' => 1, 'text_encoding' => 1]], $nextRows, 'LIKE', 'plugin%'));
};

$tests['encoding like glob rtrim current source next140 rejects non integer rowid'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteEncodingLikeGlobRtrimCurrentSourceNextPlan::keyValueRowKeyPlan([['option_id' => '1', 'option_name_bytes' => 'plugin', 'text_encoding' => 1]], $nextRows, 'LIKE', 'plugin%'));
};

return $tests;
