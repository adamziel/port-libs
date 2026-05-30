<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16LikeEscapeCurrentSourceNextPlan;

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
    $row(1, 'Plugin_100%_Enabled', 'UTF-16LE'),
    $row(2, 'plugin_100%_enabled', 'UTF-16BE'),
    $row(3, 'plugin_100X_enabled', 'UTF-8'),
    $row(4, 'plugin_100%_enabled ', 'UTF-16LE'),
    $row(5, 'plugin_100%_enabled_extra', 'UTF-8'),
    $row(6, 'plugin_éclair%_enabled', 'UTF-16BE'),
    $row(7, 'plugin_Éclair%_enabled', 'UTF-16LE'),
    $row(8, 'theme_100%_enabled', 'UTF-8'),
    $bad(9, "p\x00l\x00u", 2),
];
$nextRows = [
    $row(1, 'Plugin_100%_Enabled', 'UTF-16BE'),
    $row(2, 'plugin_100%_enabled', 'UTF-16BE'),
    $row(3, 'plugin_100%_enabled', 'UTF-8'),
    $row(4, 'plugin_100%_enabled', 'UTF-16LE'),
    $row(5, 'plugin_100%_enabled_extra', 'UTF-8'),
    $row(6, 'plugin_éclair%_enabled', 'UTF-16BE'),
    $row(7, 'plugin_Éclair%_enabled_v2', 'UTF-16LE'),
    $row(10, 'plugin_100%_enabled_new', 'UTF-16LE'),
    $bad(11, "\xd8\x00", 3),
];

$plan = static fn (
    string $pattern = 'plugin\\_100\\%\\_enabled',
    ?string $escape = '\\',
    string $collation = 'NOCASE',
    ?array $current = null,
    ?array $next = null,
    string $currentSource = 'main.wp_options@142',
    string $nextSource = 'main.wp_options@143',
    int $currentCookie = 142,
    int $nextCookie = 143,
): array => SQLiteUtf16LikeEscapeCurrentSourceNextPlan::keyValueRowKeyLikeEscape(
    $current ?? $currentRows,
    $next ?? $nextRows,
    $pattern,
    $escape,
    $collation,
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
    'operator recorded' => ['plugin\\_100\\%\\_enabled', '\\', 'NOCASE', 'operator', 'LIKE'],
    'collation recorded' => ['plugin\\_100\\%\\_enabled', '\\', 'NOCASE', 'collation', 'NOCASE'],
    'case-insensitive like for nocase' => ['plugin\\_100\\%\\_enabled', '\\', 'NOCASE', 'caseSensitiveLike', false],
    'escaped wildcard prefix' => ['plugin\\_100\\%\\_enabled', '\\', 'NOCASE', 'prefix', 'plugin_100%_enabled'],
    'escaped wildcard prefix characters' => ['plugin\\_100\\%\\_enabled', '\\', 'NOCASE', 'prefixCharacters', 19],
    'escaped wildcard prefix ascii' => ['plugin\\_100\\%\\_enabled', '\\', 'NOCASE', 'prefixIsAscii', true],
    'escaped wildcard no residual wildcard' => ['plugin\\_100\\%\\_enabled', '\\', 'NOCASE', 'hasWildcard', false],
    'escaped wildcard lower range' => ['plugin\\_100\\%\\_enabled', '\\', 'NOCASE', 'range.lowerInclusive', 'plugin_100%_enabled'],
    'escaped wildcard upper range' => ['plugin\\_100\\%\\_enabled', '\\', 'NOCASE', 'range.upperBound', 'plugin_100%_enablee'],
    'escaped wildcard index usable' => ['plugin\\_100\\%\\_enabled', '\\', 'NOCASE', 'indexUsable', true],
    'nocase current order rowids' => ['plugin\\_100\\%\\_enabled', '\\', 'NOCASE', 'currentOrderRowids', [1, 2, 4, 5, 3, 7, 6, 8]],
    'nocase next order rowids' => ['plugin\\_100\\%\\_enabled', '\\', 'NOCASE', 'nextOrderRowids', [1, 2, 3, 4, 5, 10, 7, 6]],
    'nocase current candidates' => ['plugin\\_100\\%\\_enabled', '\\', 'NOCASE', 'currentCandidateRowids', [1, 2, 4, 5]],
    'nocase next candidates' => ['plugin\\_100\\%\\_enabled', '\\', 'NOCASE', 'nextCandidateRowids', [1, 2, 3, 4, 5, 10]],
    'nocase current matched exact and ascii case' => ['plugin\\_100\\%\\_enabled', '\\', 'NOCASE', 'currentMatchedRowids', [1, 2]],
    'nocase next matched repaired wildcard literal' => ['plugin\\_100\\%\\_enabled', '\\', 'NOCASE', 'nextMatchedRowids', [1, 2, 3, 4]],
    'nocase current false positives include padding and suffix' => ['plugin\\_100\\%\\_enabled', '\\', 'NOCASE', 'currentFalsePositiveRowids', [4, 5]],
    'nocase next false positives include suffixes' => ['plugin\\_100\\%\\_enabled', '\\', 'NOCASE', 'nextFalsePositiveRowids', [5, 10]],
    'retained matches' => ['plugin\\_100\\%\\_enabled', '\\', 'NOCASE', 'retainedMatchedRowids', [1, 2]],
    'entered matches' => ['plugin\\_100\\%\\_enabled', '\\', 'NOCASE', 'enteredMatchedRowids', [3, 4]],
    'exited matches' => ['plugin\\_100\\%\\_enabled', '\\', 'NOCASE', 'exitedMatchedRowids', []],
    'row one next encoding changed' => ['plugin\\_100\\%\\_enabled', '\\', 'NOCASE', 'nextEncodings.1', 'UTF-16BE'],
    'row one current bytes' => ['plugin\\_100\\%\\_enabled', '\\', 'NOCASE', 'currentBytesHex.1', '50006c007500670069006e005f0031003000300025005f0045006e00610062006c0065006400'],
    'row one next bytes' => ['plugin\\_100\\%\\_enabled', '\\', 'NOCASE', 'nextBytesHex.1', '0050006c007500670069006e005f0031003000300025005f0045006e00610062006c00650064'],
    'current malformed rowids' => ['plugin\\_100\\%\\_enabled', '\\', 'NOCASE', 'currentMalformedRowids', [9]],
    'next malformed rowids' => ['plugin\\_100\\%\\_enabled', '\\', 'NOCASE', 'nextMalformedRowids', [11]],
    'current malformed odd utf16' => ['plugin\\_100\\%\\_enabled', '\\', 'NOCASE', 'currentErrors.9', 'SQLite encoding source UTF-16 text payload has an odd byte length'],
    'next malformed high surrogate' => ['plugin\\_100\\%\\_enabled', '\\', 'NOCASE', 'nextErrors.11', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'changed text rowids' => ['plugin\\_100\\%\\_enabled', '\\', 'NOCASE', 'changedTextRowids', [3, 4, 7]],
    'changed encoding rowids' => ['plugin\\_100\\%\\_enabled', '\\', 'NOCASE', 'changedEncodingRowids', [1]],
    'changed bytes rowids' => ['plugin\\_100\\%\\_enabled', '\\', 'NOCASE', 'changedBytesRowids', [1, 3, 4, 7]],
    'changed collation keys' => ['plugin\\_100\\%\\_enabled', '\\', 'NOCASE', 'changedCollationKeyRowids', [3, 4, 7]],
    'invalidated' => ['plugin\\_100\\%\\_enabled', '\\', 'NOCASE', 'cursorInvalidated', true],
    'not reusable' => ['plugin\\_100\\%\\_enabled', '\\', 'NOCASE', 'cursorReusable', false],
    'reason source' => ['plugin\\_100\\%\\_enabled', '\\', 'NOCASE', 'invalidationReasons.0', 'source-name'],
    'reason schema' => ['plugin\\_100\\%\\_enabled', '\\', 'NOCASE', 'invalidationReasons.1', 'schema-cookie'],
    'reason malformed' => ['plugin\\_100\\%\\_enabled', '\\', 'NOCASE', 'invalidationReasons.2', 'malformed-text'],
    'reason text' => ['plugin\\_100\\%\\_enabled', '\\', 'NOCASE', 'invalidationReasons.3', 'text-value'],
    'reason encoding' => ['plugin\\_100\\%\\_enabled', '\\', 'NOCASE', 'invalidationReasons.4', 'text-encoding'],
    'reason bytes' => ['plugin\\_100\\%\\_enabled', '\\', 'NOCASE', 'invalidationReasons.5', 'encoded-bytes'],
    'reason key' => ['plugin\\_100\\%\\_enabled', '\\', 'NOCASE', 'invalidationReasons.6', 'collation-key'],
    'reason candidates' => ['plugin\\_100\\%\\_enabled', '\\', 'NOCASE', 'invalidationReasons.7', 'candidate-rowset'],
    'reason matches' => ['plugin\\_100\\%\\_enabled', '\\', 'NOCASE', 'invalidationReasons.8', 'matched-rowset'],
    'dependency decode' => ['plugin\\_100\\%\\_enabled', '\\', 'NOCASE', 'dependencies.0', 'sqlite-utf16-text-decode'],
    'dependency like escape' => ['plugin\\_100\\%\\_enabled', '\\', 'NOCASE', 'dependencies.1', 'sqlite-like-escape-prefix'],
    'binary case-sensitive excludes upper row' => ['plugin\\_100\\%\\_enabled', '\\', 'BINARY', 'currentMatchedRowids', [2]],
    'binary range lower preserves case' => ['plugin\\_100\\%\\_enabled', '\\', 'BINARY', 'range.lowerInclusive', 'plugin_100%_enabled'],
    'rtrim range admits padded row' => ['plugin\\_100\\%\\_enabled', '\\', 'RTRIM', 'currentCandidateRowids', [2, 4, 5]],
    'rtrim residual still uses untrimmed text' => ['plugin\\_100\\%\\_enabled', '\\', 'RTRIM', 'currentFalsePositiveRowids', [4, 5]],
    'wildcard suffix current matches' => ['plugin\\_100\\%\\_enabled%', '\\', 'NOCASE', 'currentMatchedRowids', [1, 2, 4, 5]],
    'wildcard suffix next matches' => ['plugin\\_100\\%\\_enabled%', '\\', 'NOCASE', 'nextMatchedRowids', [1, 2, 3, 4, 5, 10]],
    'unicode escaped percent lower' => ['plugin\\_éclair\\%\\_enabled', '\\', 'NOCASE', 'range.lowerInclusive', 'plugin_éclair%_enabled'],
    'unicode escaped percent current matched ascii nocase only' => ['plugin\\_éclair\\%\\_enabled', '\\', 'NOCASE', 'currentMatchedRowids', [6]],
    'unicode escaped percent next matched' => ['plugin\\_éclair\\%\\_enabled', '\\', 'NOCASE', 'nextMatchedRowids', [6]],
    'custom escape prefix' => ['plugin!_100!%!_enabled', '!', 'NOCASE', 'prefix', 'plugin_100%_enabled'],
    'custom escape matches current' => ['plugin!_100!%!_enabled', '!', 'NOCASE', 'currentMatchedRowids', [1, 2]],
];

foreach ($cases as $name => [$pattern, $escape, $collation, $path, $expected]) {
    $tests['utf16 like escape current source nextOneFourThree ' . $name] = static function (TestRunner $t) use ($plan, $valueAt, $pattern, $escape, $collation, $path, $expected): void {
        $t->same($expected, $valueAt($plan($pattern, $escape, $collation), $path));
    };
}

$tests['utf16 like escape current source nextOneFourThree identical source can reuse cursor'] = static function (TestRunner $t) use ($row, $plan): void {
    $rows = [$row(1, 'plugin_100%_enabled', 'UTF-16LE'), $row(2, 'plugin_100%_enabled_extra', 'UTF-16BE')];
    $result = $plan('plugin\\_100\\%\\_enabled%', '\\', 'NOCASE', $rows, $rows, 'stable', 'stable', 77, 77);
    $t->same([1, 2], $result['currentMatchedRowids']);
    $t->same([], $result['invalidationReasons']);
    $t->same(true, $result['cursorReusable']);
};

$tests['utf16 like escape current source nextOneFourThree dangling escape produces no residual matches'] = static function (TestRunner $t) use ($plan): void {
    $result = $plan('plugin\\_100\\%\\_enabled\\', '\\', 'NOCASE');
    $t->same(true, $result['hasDanglingEscape']);
    $t->same(false, $result['indexUsable']);
    $t->same([], $result['currentMatchedRowids']);
    $t->same('dangling-escape', $result['invalidationReasons'][2]);
};

$tests['utf16 like escape current source nextOneFourThree rejects unsupported collation'] = static function (TestRunner $t) use ($plan): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan('plugin%', '\\', 'UNICODE'));
};

$tests['utf16 like escape current source nextOneFourThree rejects missing option bytes'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16LikeEscapeCurrentSourceNextPlan::keyValueRowKeyLikeEscape([['option_id' => 1, 'text_encoding' => 2]], $nextRows, 'plugin%'));
};

$tests['utf16 like escape current source nextOneFourThree rejects non integer rowid'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16LikeEscapeCurrentSourceNextPlan::keyValueRowKeyLikeEscape([['option_id' => '1', 'option_name_bytes' => 'p', 'text_encoding' => 1]], $nextRows, 'plugin%'));
};

$tests['utf16 like escape current source nextOneFourThree rejects invalid escape length'] = static function (TestRunner $t) use ($plan): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan('plugin%', 'xx', 'NOCASE'));
};

return $tests;
