<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16RtrimLikeCurrentSourceNextPlan;

$tests = [];

$row = static function (int $id, string $name, string $encoding, string $autoload = 'yes'): array {
    return [
        'option_id' => $id,
        'option_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($name, $encoding),
        'text_encoding' => match ($encoding) {
            'UTF-8' => 1,
            'UTF-16LE' => 2,
            'UTF-16BE' => 3,
            default => throw new InvalidArgumentException('bad encoding'),
        },
        'autoload' => $autoload,
    ];
};

$bad = static fn (int $id, string $bytes, int $encoding): array => [
    'option_id' => $id,
    'option_name_bytes' => $bytes,
    'text_encoding' => $encoding,
    'autoload' => 'yes',
];

$currentRows = [
    $row(1, 'plugin_cache', 'UTF-16LE'),
    $row(2, 'plugin_cache ', 'UTF-16BE'),
    $row(3, 'plugin_cache  ', 'UTF-8', 'no'),
    $row(4, "plugin_cache\t", 'UTF-16LE'),
    $row(5, "plugin_cache\xc2\xa0", 'UTF-16BE'),
    $row(6, 'plugin_cache_extra', 'UTF-16LE'),
    $row(7, 'Plugin_Cache', 'UTF-8'),
    $row(8, 'plugin_éclair ', 'UTF-16LE'),
    $row(9, 'plugin_Éclair ', 'UTF-16BE'),
    $row(10, 'plugin_😀 ', 'UTF-16LE'),
    $row(11, 'theme_cache ', 'UTF-16LE'),
    $bad(12, "p\x00l\x00u\x00g\x00i\x00n\x00_\x00c", 2),
];

$nextRows = [
    $row(1, 'plugin_cache', 'UTF-16BE'),
    $row(2, 'plugin_cache ', 'UTF-16BE'),
    $row(3, 'plugin_cache', 'UTF-8', 'no'),
    $row(4, "plugin_cache\t", 'UTF-16LE'),
    $row(5, "plugin_cache\xc2\xa0", 'UTF-16BE'),
    $row(6, 'plugin_cache_extra_v2', 'UTF-16LE'),
    $row(7, 'Plugin_Cache', 'UTF-8'),
    $row(8, 'plugin_éclair ', 'UTF-16BE'),
    $row(9, 'plugin_Éclair ', 'UTF-16BE'),
    $row(10, 'plugin_😀', 'UTF-16BE'),
    $row(13, 'plugin_cache_new', 'UTF-16LE'),
    $bad(14, "\xd8\x00", 3),
];

$plan = static fn (
    string $pattern,
    ?string $escape = null,
    bool $caseSensitiveLike = true,
    string $currentSource = 'main.wp_options@120',
    string $nextSource = 'main.wp_options@121',
): array => SQLiteUtf16RtrimLikeCurrentSourceNextPlan::keyValueRowKeyPlan(
    $currentRows,
    $nextRows,
    $pattern,
    $escape,
    $caseSensitiveLike,
    $currentSource,
    $nextSource,
);

$cases = [
    'records operator' => ['plugin_cache', null, true, 'operator', 'LIKE'],
    'records rtrim collation' => ['plugin_cache', null, true, 'collation', 'RTRIM'],
    'records pattern' => ['plugin_cache', null, true, 'pattern', 'plugin_cache'],
    'case sensitive rejected reason' => ['plugin_cache', null, true, 'rejectedReason', 'case_sensitive_like_requires_binary_index'],
    'has no range under rtrim' => ['plugin_cache', null, true, 'range', null],
    'index not usable' => ['plugin_cache', null, true, 'indexUsable', false],
    'uses residual scan' => ['plugin_cache', null, true, 'residualScan', true],
    'like does not trim marker' => ['plugin_cache', null, true, 'likeDoesNotTrimTrailingSpaces', true],
    'exact current rowids do not include space padded peers' => ['plugin_cache', null, true, 'currentRowids', [1]],
    'exact next rowids include repaired trimmed row' => ['plugin_cache', null, true, 'nextRowids', [1, 3]],
    'exact retained rowids' => ['plugin_cache', null, true, 'retainedRowids', [1]],
    'exact entered rowids' => ['plugin_cache', null, true, 'enteredRowids', [3]],
    'exact exited rowids empty' => ['plugin_cache', null, true, 'exitedRowids', []],
    'wildcard current includes space padded peers' => ['plugin_cache%', null, true, 'currentRowids', [1, 2, 3, 4, 6, 5]],
    'wildcard current includes tab peer' => ['plugin_cache%', null, true, 'currentMatchedRows.3.rowid', 4],
    'wildcard current includes nbsp peer' => ['plugin_cache%', null, true, 'currentMatchedRows.5.rowid', 5],
    'wildcard next includes new row' => ['plugin_cache%', null, true, 'nextRowids', [1, 2, 3, 4, 6, 13, 5]],
    'wildcard next changed text row six' => ['plugin_cache%', null, true, 'changedTextRowids', [3, 6, 10]],
    'wildcard next changed encoding row one eight ten' => ['plugin_cache%', null, true, 'changedEncodingRowids', [1, 8, 10]],
    'wildcard next changed bytes' => ['plugin_cache%', null, true, 'changedBytesRowids', [1, 3, 6, 8, 10]],
    'case insensitive ascii current includes uppercase' => ['plugin_cache', null, false, 'currentRowids', [7, 1]],
    'case insensitive ascii next includes uppercase and repaired row' => ['plugin_cache', null, false, 'nextRowids', [7, 1, 3]],
    'case insensitive rejected reason default nocase needed' => ['plugin_cache', null, false, 'rejectedReason', 'default_like_requires_nocase_index'],
    'space pattern matches single padded row' => ['plugin_cache ', null, true, 'currentRowids', [2]],
    'two-space pattern matches two-space row only' => ['plugin_cache  ', null, true, 'currentRowids', [3]],
    'tab pattern matches tab row only' => ["plugin_cache\t", null, true, 'currentRowids', [4]],
    'nbsp pattern matches nbsp row only' => ["plugin_cache\xc2\xa0", null, true, 'currentRowids', [5]],
    'escaped literal underscore current' => ['plugin!_cache%', '!', true, 'currentRowids', [1, 2, 3, 4, 6, 5]],
    'escaped literal underscore next' => ['plugin!_cache%', '!', true, 'nextRowids', [1, 2, 3, 4, 6, 13, 5]],
    'escaped literal percent has no matches' => ['plugin!%cache%', '!', true, 'currentRowids', []],
    'eclair lower matches lower only' => ['plugin_éclair%', null, true, 'currentRowids', [8]],
    'eclair upper matches upper only' => ['plugin_Éclair%', null, true, 'currentRowids', [9]],
    'emoji space exact current' => ['plugin_😀 ', null, true, 'currentRowids', [10]],
    'emoji no-space exact next' => ['plugin_😀', null, true, 'nextRowids', [10]],
    'theme exits next' => ['theme_cache%', null, true, 'exitedRowids', [11]],
    'current first decoded key trims for sort' => ['plugin_cache', null, true, 'currentDecodedRows.0.rtrimKey', 'Plugin_Cache'],
    'current row one text preserved' => ['plugin_cache', null, true, 'currentDecodedRows.1.text', 'plugin_cache'],
    'current row two text preserves space' => ['plugin_cache', null, true, 'currentDecodedRows.2.text', 'plugin_cache '],
    'current row three rtrim key trims spaces' => ['plugin_cache', null, true, 'currentDecodedRows.3.rtrimKey', 'plugin_cache'],
    'current tab rtrim key keeps tab' => ['plugin_cache', null, true, 'currentDecodedRows.4.rtrimKey', "plugin_cache\t"],
    'current nbsp rtrim key keeps nbsp' => ['plugin_cache', null, true, 'currentDecodedRows.6.rtrimKey', "plugin_cache\xc2\xa0"],
    'current row one encoding' => ['plugin_cache', null, true, 'currentDecodedRows.1.encoding', 'UTF-16LE'],
    'next row one encoding switches' => ['plugin_cache', null, true, 'nextDecodedRows.1.encoding', 'UTF-16BE'],
    'current malformed rowids' => ['plugin_cache', null, true, 'currentMalformedRowids', [12]],
    'next malformed rowids' => ['plugin_cache', null, true, 'nextMalformedRowids', [14]],
    'current malformed error' => ['plugin_cache', null, true, 'currentErrors.12', 'SQLite encoding source UTF-16 text payload has an odd byte length'],
    'next malformed error' => ['plugin_cache', null, true, 'nextErrors.14', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'invalidated' => ['plugin_cache', null, true, 'cursorInvalidated', true],
    'not reusable' => ['plugin_cache', null, true, 'cursorReusable', false],
    'first invalidation source' => ['plugin_cache', null, true, 'invalidationReasons.0', 'source-name'],
    'second invalidation full scan' => ['plugin_cache', null, true, 'invalidationReasons.1', 'full-scan-rtrim-like'],
    'third invalidation malformed' => ['plugin_cache', null, true, 'invalidationReasons.2', 'malformed-text'],
    'last invalidation rowset' => ['plugin_cache', null, true, 'invalidationReasons.6', 'matched-rowset'],
    'dependency decode' => ['plugin_cache', null, true, 'dependencies.0', 'sqlite-utf16-decode'],
    'dependency residual scan' => ['plugin_cache', null, true, 'dependencies.1', 'sqlite-like-rtrim-residual-scan'],
    'dependency marker' => ['plugin_cache', null, true, 'dependencies.2', 'sqlite-current-source-nextoneTwoOne'],
];

foreach ($cases as $name => [$pattern, $escape, $caseSensitiveLike, $path, $expected]) {
    $tests['utf16 rtrim like current source nextOneTwoOne ' . $name] = static function (TestRunner $t) use ($plan, $pattern, $escape, $caseSensitiveLike, $path, $expected): void {
        $value = $plan($pattern, $escape, $caseSensitiveLike);
        foreach (explode('.', $path) as $part) {
            $value = $value[$part];
        }
        $t->same($expected, $value);
    };
}

$tests['utf16 rtrim like current source nextOneTwoOne stable unchanged still records full scan rejection only'] = static function (TestRunner $t) use ($row): void {
    $rows = [$row(1, 'plugin_cache ', 'UTF-16LE'), $row(2, 'theme_cache', 'UTF-16BE')];
    $plan = SQLiteUtf16RtrimLikeCurrentSourceNextPlan::keyValueRowKeyPlan($rows, $rows, 'plugin_cache%', null, true, 'stable', 'stable');
    $t->same(['full-scan-rtrim-like'], $plan['invalidationReasons']);
    $t->same([1], $plan['currentRowids']);
    $t->same([1], $plan['nextRowids']);
};

$tests['utf16 rtrim like current source nextOneTwoOne rejects invalid escape length'] = static function (TestRunner $t) use ($plan): void {
    $t->throws(InvalidArgumentException::class, static fn () => $plan('plugin%', '!!'));
};

$tests['utf16 rtrim like current source nextOneTwoOne rejects missing option id'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16RtrimLikeCurrentSourceNextPlan::keyValueRowKeyPlan([['option_name_bytes' => 'p', 'text_encoding' => 1]], $nextRows, 'p%'));
};

$tests['utf16 rtrim like current source nextOneTwoOne rejects missing bytes'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16RtrimLikeCurrentSourceNextPlan::keyValueRowKeyPlan([['option_id' => 1, 'text_encoding' => 1]], $nextRows, 'p%'));
};

$tests['utf16 rtrim like current source nextOneTwoOne rejects missing encoding'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16RtrimLikeCurrentSourceNextPlan::keyValueRowKeyPlan([['option_id' => 1, 'option_name_bytes' => 'p']], $nextRows, 'p%'));
};

return $tests;
