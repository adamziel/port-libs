<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16LikeEscapeCurrentSourceNextPlan;

$tests = [];

$row = static function (int $id, string $name, string $encoding): array {
    return [
        'setting_id' => $id,
        'key_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($name, $encoding),
        'text_encoding' => match ($encoding) {
            'UTF-8' => 1,
            'UTF-16LE' => 2,
            'UTF-16BE' => 3,
        },
    ];
};
$bad = static fn (int $id, string $bytes, int $encoding): array => [
    'setting_id' => $id,
    'key_name_bytes' => $bytes,
    'text_encoding' => $encoding,
];

$currentRows = [
    $row(1, 'Module_100%_Enabled', 'UTF-16LE'),
    $row(2, 'module_100%_enabled', 'UTF-16BE'),
    $row(3, 'module_100X_enabled', 'UTF-8'),
    $row(4, 'module_100%_enabled ', 'UTF-16LE'),
    $row(5, 'module_100%_enabled_extra', 'UTF-8'),
    $row(6, 'module_éclair%_enabled', 'UTF-16BE'),
    $row(7, 'module_Éclair%_enabled', 'UTF-16LE'),
    $row(8, 'theme_100%_enabled', 'UTF-8'),
    $bad(9, "p\x00l\x00u", 2),
];
$nextRows = [
    $row(1, 'Module_100%_Enabled', 'UTF-16BE'),
    $row(2, 'module_100%_enabled', 'UTF-16BE'),
    $row(3, 'module_100%_enabled', 'UTF-8'),
    $row(4, 'module_100%_enabled', 'UTF-16LE'),
    $row(5, 'module_100%_enabled_extra', 'UTF-8'),
    $row(6, 'module_éclair%_enabled', 'UTF-16BE'),
    $row(7, 'module_Éclair%_enabled_v2', 'UTF-16LE'),
    $row(10, 'module_100%_enabled_new', 'UTF-16LE'),
    $bad(11, "\xd8\x00", 3),
];

$plan = static fn (
    string $pattern = 'module\\_100\\%\\_enabled',
    ?string $escape = '\\',
    string $collation = 'NOCASE',
    ?array $current = null,
    ?array $next = null,
    string $currentSource = 'main.app_settings@142',
    string $nextSource = 'main.app_settings@143',
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
    'operator recorded' => ['module\\_100\\%\\_enabled', '\\', 'NOCASE', 'operator', 'LIKE'],
    'collation recorded' => ['module\\_100\\%\\_enabled', '\\', 'NOCASE', 'collation', 'NOCASE'],
    'case-insensitive like for nocase' => ['module\\_100\\%\\_enabled', '\\', 'NOCASE', 'caseSensitiveLike', false],
    'escaped wildcard prefix' => ['module\\_100\\%\\_enabled', '\\', 'NOCASE', 'prefix', 'module_100%_enabled'],
    'escaped wildcard prefix characters' => ['module\\_100\\%\\_enabled', '\\', 'NOCASE', 'prefixCharacters', 19],
    'escaped wildcard prefix ascii' => ['module\\_100\\%\\_enabled', '\\', 'NOCASE', 'prefixIsAscii', true],
    'escaped wildcard no residual wildcard' => ['module\\_100\\%\\_enabled', '\\', 'NOCASE', 'hasWildcard', false],
    'escaped wildcard lower range' => ['module\\_100\\%\\_enabled', '\\', 'NOCASE', 'range.lowerInclusive', 'module_100%_enabled'],
    'escaped wildcard upper range' => ['module\\_100\\%\\_enabled', '\\', 'NOCASE', 'range.upperBound', 'module_100%_enablee'],
    'escaped wildcard index usable' => ['module\\_100\\%\\_enabled', '\\', 'NOCASE', 'indexUsable', true],
    'nocase current order rowids' => ['module\\_100\\%\\_enabled', '\\', 'NOCASE', 'currentOrderRowids', [1, 2, 4, 5, 3, 7, 6, 8]],
    'nocase next order rowids' => ['module\\_100\\%\\_enabled', '\\', 'NOCASE', 'nextOrderRowids', [1, 2, 3, 4, 5, 10, 7, 6]],
    'nocase current candidates' => ['module\\_100\\%\\_enabled', '\\', 'NOCASE', 'currentCandidateRowids', [1, 2, 4, 5]],
    'nocase next candidates' => ['module\\_100\\%\\_enabled', '\\', 'NOCASE', 'nextCandidateRowids', [1, 2, 3, 4, 5, 10]],
    'nocase current matched exact and ascii case' => ['module\\_100\\%\\_enabled', '\\', 'NOCASE', 'currentMatchedRowids', [1, 2]],
    'nocase next matched repaired wildcard literal' => ['module\\_100\\%\\_enabled', '\\', 'NOCASE', 'nextMatchedRowids', [1, 2, 3, 4]],
    'nocase current false positives include padding and suffix' => ['module\\_100\\%\\_enabled', '\\', 'NOCASE', 'currentFalsePositiveRowids', [4, 5]],
    'nocase next false positives include suffixes' => ['module\\_100\\%\\_enabled', '\\', 'NOCASE', 'nextFalsePositiveRowids', [5, 10]],
    'retained matches' => ['module\\_100\\%\\_enabled', '\\', 'NOCASE', 'retainedMatchedRowids', [1, 2]],
    'entered matches' => ['module\\_100\\%\\_enabled', '\\', 'NOCASE', 'enteredMatchedRowids', [3, 4]],
    'exited matches' => ['module\\_100\\%\\_enabled', '\\', 'NOCASE', 'exitedMatchedRowids', []],
    'row one next encoding changed' => ['module\\_100\\%\\_enabled', '\\', 'NOCASE', 'nextEncodings.1', 'UTF-16BE'],
    'row one current bytes' => ['module\\_100\\%\\_enabled', '\\', 'NOCASE', 'currentBytesHex.1', '4d006f00640075006c0065005f0031003000300025005f0045006e00610062006c0065006400'],
    'row one next bytes' => ['module\\_100\\%\\_enabled', '\\', 'NOCASE', 'nextBytesHex.1', '004d006f00640075006c0065005f0031003000300025005f0045006e00610062006c00650064'],
    'current malformed rowids' => ['module\\_100\\%\\_enabled', '\\', 'NOCASE', 'currentMalformedRowids', [9]],
    'next malformed rowids' => ['module\\_100\\%\\_enabled', '\\', 'NOCASE', 'nextMalformedRowids', [11]],
    'current malformed odd utf16' => ['module\\_100\\%\\_enabled', '\\', 'NOCASE', 'currentErrors.9', 'SQLite encoding source UTF-16 text payload has an odd byte length'],
    'next malformed high surrogate' => ['module\\_100\\%\\_enabled', '\\', 'NOCASE', 'nextErrors.11', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'changed text rowids' => ['module\\_100\\%\\_enabled', '\\', 'NOCASE', 'changedTextRowids', [3, 4, 7]],
    'changed encoding rowids' => ['module\\_100\\%\\_enabled', '\\', 'NOCASE', 'changedEncodingRowids', [1]],
    'changed bytes rowids' => ['module\\_100\\%\\_enabled', '\\', 'NOCASE', 'changedBytesRowids', [1, 3, 4, 7]],
    'changed collation keys' => ['module\\_100\\%\\_enabled', '\\', 'NOCASE', 'changedCollationKeyRowids', [3, 4, 7]],
    'invalidated' => ['module\\_100\\%\\_enabled', '\\', 'NOCASE', 'cursorInvalidated', true],
    'not reusable' => ['module\\_100\\%\\_enabled', '\\', 'NOCASE', 'cursorReusable', false],
    'reason source' => ['module\\_100\\%\\_enabled', '\\', 'NOCASE', 'invalidationReasons.0', 'source-name'],
    'reason schema' => ['module\\_100\\%\\_enabled', '\\', 'NOCASE', 'invalidationReasons.1', 'schema-cookie'],
    'reason malformed' => ['module\\_100\\%\\_enabled', '\\', 'NOCASE', 'invalidationReasons.2', 'malformed-text'],
    'reason text' => ['module\\_100\\%\\_enabled', '\\', 'NOCASE', 'invalidationReasons.3', 'text-value'],
    'reason encoding' => ['module\\_100\\%\\_enabled', '\\', 'NOCASE', 'invalidationReasons.4', 'text-encoding'],
    'reason bytes' => ['module\\_100\\%\\_enabled', '\\', 'NOCASE', 'invalidationReasons.5', 'encoded-bytes'],
    'reason key' => ['module\\_100\\%\\_enabled', '\\', 'NOCASE', 'invalidationReasons.6', 'collation-key'],
    'reason candidates' => ['module\\_100\\%\\_enabled', '\\', 'NOCASE', 'invalidationReasons.7', 'candidate-rowset'],
    'reason matches' => ['module\\_100\\%\\_enabled', '\\', 'NOCASE', 'invalidationReasons.8', 'matched-rowset'],
    'dependency decode' => ['module\\_100\\%\\_enabled', '\\', 'NOCASE', 'dependencies.0', 'sqlite-utf16-text-decode'],
    'dependency like escape' => ['module\\_100\\%\\_enabled', '\\', 'NOCASE', 'dependencies.1', 'sqlite-like-escape-prefix'],
    'binary case-sensitive excludes upper row' => ['module\\_100\\%\\_enabled', '\\', 'BINARY', 'currentMatchedRowids', [2]],
    'binary range lower preserves case' => ['module\\_100\\%\\_enabled', '\\', 'BINARY', 'range.lowerInclusive', 'module_100%_enabled'],
    'rtrim range admits padded row' => ['module\\_100\\%\\_enabled', '\\', 'RTRIM', 'currentCandidateRowids', [2, 4, 5]],
    'rtrim residual still uses untrimmed text' => ['module\\_100\\%\\_enabled', '\\', 'RTRIM', 'currentFalsePositiveRowids', [4, 5]],
    'wildcard suffix current matches' => ['module\\_100\\%\\_enabled%', '\\', 'NOCASE', 'currentMatchedRowids', [1, 2, 4, 5]],
    'wildcard suffix next matches' => ['module\\_100\\%\\_enabled%', '\\', 'NOCASE', 'nextMatchedRowids', [1, 2, 3, 4, 5, 10]],
    'unicode escaped percent lower' => ['module\\_éclair\\%\\_enabled', '\\', 'NOCASE', 'range.lowerInclusive', 'module_éclair%_enabled'],
    'unicode escaped percent current matched ascii nocase only' => ['module\\_éclair\\%\\_enabled', '\\', 'NOCASE', 'currentMatchedRowids', [6]],
    'unicode escaped percent next matched' => ['module\\_éclair\\%\\_enabled', '\\', 'NOCASE', 'nextMatchedRowids', [6]],
    'custom escape prefix' => ['module!_100!%!_enabled', '!', 'NOCASE', 'prefix', 'module_100%_enabled'],
    'custom escape matches current' => ['module!_100!%!_enabled', '!', 'NOCASE', 'currentMatchedRowids', [1, 2]],
];

foreach ($cases as $name => [$pattern, $escape, $collation, $path, $expected]) {
    $tests['utf16 like escape current source nextOneFourThree ' . $name] = static function (TestRunner $t) use ($plan, $valueAt, $pattern, $escape, $collation, $path, $expected): void {
        $t->same($expected, $valueAt($plan($pattern, $escape, $collation), $path));
    };
}

$tests['utf16 like escape current source nextOneFourThree identical source can reuse cursor'] = static function (TestRunner $t) use ($row, $plan): void {
    $rows = [$row(1, 'module_100%_enabled', 'UTF-16LE'), $row(2, 'module_100%_enabled_extra', 'UTF-16BE')];
    $result = $plan('module\\_100\\%\\_enabled%', '\\', 'NOCASE', $rows, $rows, 'stable', 'stable', 77, 77);
    $t->same([1, 2], $result['currentMatchedRowids']);
    $t->same([], $result['invalidationReasons']);
    $t->same(true, $result['cursorReusable']);
};

$tests['utf16 like escape current source nextOneFourThree dangling escape produces no residual matches'] = static function (TestRunner $t) use ($plan): void {
    $result = $plan('module\\_100\\%\\_enabled\\', '\\', 'NOCASE');
    $t->same(true, $result['hasDanglingEscape']);
    $t->same(false, $result['indexUsable']);
    $t->same([], $result['currentMatchedRowids']);
    $t->same('dangling-escape', $result['invalidationReasons'][2]);
};

$tests['utf16 like escape current source nextOneFourThree rejects unsupported collation'] = static function (TestRunner $t) use ($plan): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan('module%', '\\', 'UNICODE'));
};

$tests['utf16 like escape current source nextOneFourThree rejects missing key bytes'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16LikeEscapeCurrentSourceNextPlan::keyValueRowKeyLikeEscape([['setting_id' => 1, 'text_encoding' => 2]], $nextRows, 'module%'));
};

$tests['utf16 like escape current source nextOneFourThree rejects non integer rowid'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16LikeEscapeCurrentSourceNextPlan::keyValueRowKeyLikeEscape([['setting_id' => '1', 'key_name_bytes' => 'p', 'text_encoding' => 1]], $nextRows, 'module%'));
};

$tests['utf16 like escape current source nextOneFourThree rejects invalid escape length'] = static function (TestRunner $t) use ($plan): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan('module%', 'xx', 'NOCASE'));
};

return $tests;
