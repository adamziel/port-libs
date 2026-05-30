<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16RtrimGlobCurrentSourceNextPlan;

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
    $row(5, "plugin_cache\n", 'UTF-16BE'),
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
    $row(5, "plugin_cache\n", 'UTF-16BE'),
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
    string $currentSource = 'main.wp_options@124',
    string $nextSource = 'main.wp_options@125',
    ?array $current = null,
    ?array $next = null,
): array => SQLiteUtf16RtrimGlobCurrentSourceNextPlan::optionRowNamePlan(
    $current ?? $currentRows,
    $next ?? $nextRows,
    $pattern,
    $currentSource,
    $nextSource,
);

$valueAt = static function (array $value, string $path): mixed {
    foreach (explode('.', $path) as $part) {
        $value = $value[$part];
    }

    return $value;
};

$cases = [
    'records operator' => ['plugin_cache', 'operator', 'GLOB'],
    'records rtrim collation' => ['plugin_cache', 'collation', 'RTRIM'],
    'records pattern' => ['plugin_cache', 'pattern', 'plugin_cache'],
    'range lower from exact prefix' => ['plugin_cache', 'range.lowerInclusive', 'plugin_cache'],
    'range upper from exact prefix' => ['plugin_cache', 'range.upperBound', 'plugin_cachf'],
    'index is usable when prefix exists' => ['plugin_cache', 'indexUsable', true],
    'uses residual scan' => ['plugin_cache', 'residualScan', true],
    'glob does not trim marker' => ['plugin_cache', 'globDoesNotTrimTrailingSpaces', true],
    'exact current candidates include rtrim peers and prefix follower' => ['plugin_cache', 'currentCandidateRowids', [1, 2, 3, 4, 5, 6]],
    'exact next candidates include repaired row and new prefix follower' => ['plugin_cache', 'nextCandidateRowids', [1, 2, 3, 4, 5, 6, 13]],
    'exact current residual rejects padded peers and prefix follower' => ['plugin_cache', 'currentResidualRejectedRowids', [2, 3, 4, 5, 6]],
    'exact next residual rejects padded peers and prefix followers' => ['plugin_cache', 'nextResidualRejectedRowids', [2, 4, 5, 6, 13]],
    'exact current rowids only exact key' => ['plugin_cache', 'currentRowids', [1]],
    'exact next rowids include repaired trim' => ['plugin_cache', 'nextRowids', [1, 3]],
    'exact retained rowids' => ['plugin_cache', 'retainedRowids', [1]],
    'exact entered rowids' => ['plugin_cache', 'enteredRowids', [3]],
    'exact exited rowids empty' => ['plugin_cache', 'exitedRowids', []],
    'wildcard current candidate rowids' => ['plugin_cache*', 'currentCandidateRowids', [1, 2, 3, 4, 5, 6]],
    'wildcard current has no residual rejects after binary range' => ['plugin_cache*', 'currentResidualRejectedRowids', []],
    'wildcard current matched rowids include space tab newline' => ['plugin_cache*', 'currentRowids', [1, 2, 3, 4, 5, 6]],
    'wildcard next includes new row' => ['plugin_cache*', 'nextRowids', [1, 2, 3, 4, 5, 6, 13]],
    'wildcard next changed text rowids' => ['plugin_cache*', 'changedTextRowids', [3, 6, 10]],
    'wildcard next changed encoding rowids' => ['plugin_cache*', 'changedEncodingRowids', [1, 8, 10]],
    'wildcard next changed bytes rowids' => ['plugin_cache*', 'changedBytesRowids', [1, 3, 6, 8, 10]],
    'space pattern current only single space' => ['plugin_cache ', 'currentRowids', [2]],
    'two-space pattern current only double space' => ['plugin_cache  ', 'currentRowids', [3]],
    'tab pattern current only tab' => ["plugin_cache\t", 'currentRowids', [4]],
    'newline pattern current only newline' => ["plugin_cache\n", 'currentRowids', [5]],
    'uppercase pattern current only uppercase' => ['Plugin_Cache', 'currentRowids', [7]],
    'lowercase wildcard binary range excludes uppercase before residual' => ['plugin_*', 'currentResidualRejectedRowids', []],
    'lowercase wildcard current rowids' => ['plugin_*', 'currentRowids', [1, 2, 3, 4, 5, 6, 9, 8, 10]],
    'lowercase wildcard next rowids' => ['plugin_*', 'nextRowids', [1, 2, 3, 4, 5, 6, 13, 9, 8, 10]],
    'unicode lower pattern current trims candidate but matches padded text' => ['plugin_éclair*', 'currentRowids', [8]],
    'unicode upper pattern current matches upper only' => ['plugin_Éclair*', 'currentRowids', [9]],
    'emoji space exact current' => ['plugin_😀 ', 'currentRowids', [10]],
    'emoji no-space exact next' => ['plugin_😀', 'nextRowids', [10]],
    'theme pattern exits next' => ['theme_cache*', 'exitedRowids', [11]],
    'leading class has no range' => ['[Pp]lugin_*', 'range', null],
    'leading class has no candidates' => ['[Pp]lugin_*', 'currentCandidateRowids', []],
    'leading class records no-prefix reason' => ['[Pp]lugin_*', 'invalidationReasons.1', 'no-prefix-range'],
    'decoded first row is uppercase by rtrim key' => ['plugin_cache', 'currentDecodedRows.0.text', 'Plugin_Cache'],
    'decoded row one text preserved' => ['plugin_cache', 'currentDecodedRows.1.text', 'plugin_cache'],
    'decoded row two rtrim key trims space' => ['plugin_cache', 'currentDecodedRows.2.rtrimKey', 'plugin_cache'],
    'decoded tab rtrim key keeps tab' => ['plugin_cache', 'currentDecodedRows.4.rtrimKey', "plugin_cache\t"],
    'decoded newline rtrim key keeps newline' => ['plugin_cache', 'currentDecodedRows.5.rtrimKey', "plugin_cache\n"],
    'current row one encoding' => ['plugin_cache', 'currentDecodedRows.1.encoding', 'UTF-16LE'],
    'next row one encoding switches' => ['plugin_cache', 'nextDecodedRows.1.encoding', 'UTF-16BE'],
    'current row one bytes hex' => ['plugin_cache', 'currentDecodedRows.1.bytesHex', '70006c007500670069006e005f0063006100630068006500'],
    'next row one bytes hex switches endian' => ['plugin_cache', 'nextDecodedRows.1.bytesHex', '0070006c007500670069006e005f00630061006300680065'],
    'current malformed rowids' => ['plugin_cache', 'currentMalformedRowids', [12]],
    'next malformed rowids' => ['plugin_cache', 'nextMalformedRowids', [14]],
    'current malformed error' => ['plugin_cache', 'currentErrors.12', 'SQLite encoding source UTF-16 text payload has an odd byte length'],
    'next malformed error' => ['plugin_cache', 'nextErrors.14', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'invalidated' => ['plugin_cache', 'cursorInvalidated', true],
    'not reusable' => ['plugin_cache', 'cursorReusable', false],
    'source reason first' => ['plugin_cache', 'invalidationReasons.0', 'source-name'],
    'malformed reason second for prefix pattern' => ['plugin_cache', 'invalidationReasons.1', 'malformed-text'],
    'candidate rowset reason after encoded bytes' => ['plugin_cache*', 'invalidationReasons.5', 'candidate-rowset'],
    'matched rowset reason last' => ['plugin_cache*', 'invalidationReasons.6', 'matched-rowset'],
    'dependency decode' => ['plugin_cache', 'dependencies.0', 'sqlite-utf16-decode'],
    'dependency rtrim range' => ['plugin_cache', 'dependencies.1', 'sqlite-rtrim-glob-prefix-range'],
    'dependency residual' => ['plugin_cache', 'dependencies.2', 'sqlite-glob-residual-scan'],
    'dependency marker' => ['plugin_cache', 'dependencies.3', 'sqlite-current-source-nextoneTwoFive'],
];

foreach ($cases as $name => [$pattern, $path, $expected]) {
    $tests['utf16 rtrim glob current source nextOneTwoFive ' . $name] = static function (TestRunner $t) use ($plan, $valueAt, $pattern, $path, $expected): void {
        $t->same($expected, $valueAt($plan($pattern), $path));
    };
}

$tests['utf16 rtrim glob current source nextOneTwoFive stable exact still rejects rtrim peers without invalidation'] = static function (TestRunner $t) use ($row): void {
    $rows = [$row(1, 'plugin_cache', 'UTF-16LE'), $row(2, 'plugin_cache ', 'UTF-16BE')];
    $plan = SQLiteUtf16RtrimGlobCurrentSourceNextPlan::optionRowNamePlan($rows, $rows, 'plugin_cache', 'stable', 'stable');
    $t->same([1, 2], $plan['currentCandidateRowids']);
    $t->same([2], $plan['currentResidualRejectedRowids']);
    $t->same([1], $plan['currentRowids']);
    $t->same([], $plan['invalidationReasons']);
    $t->same(true, $plan['cursorReusable']);
};

$tests['utf16 rtrim glob current source nextOneTwoFive stable leading class has no prefix invalidation'] = static function (TestRunner $t) use ($row): void {
    $rows = [$row(1, 'plugin_cache', 'UTF-16LE'), $row(2, 'Plugin_Cache', 'UTF-8')];
    $plan = SQLiteUtf16RtrimGlobCurrentSourceNextPlan::optionRowNamePlan($rows, $rows, '[Pp]lugin_*', 'stable', 'stable');
    $t->same(null, $plan['range']);
    $t->same([], $plan['currentRowids']);
    $t->same(['no-prefix-range'], $plan['invalidationReasons']);
};

$tests['utf16 rtrim glob current source nextOneTwoFive rejects missing option id'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16RtrimGlobCurrentSourceNextPlan::optionRowNamePlan([['option_name_bytes' => 'p', 'text_encoding' => 1]], $nextRows, 'p*'));
};

$tests['utf16 rtrim glob current source nextOneTwoFive rejects missing bytes'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16RtrimGlobCurrentSourceNextPlan::optionRowNamePlan([['option_id' => 1, 'text_encoding' => 1]], $nextRows, 'p*'));
};

$tests['utf16 rtrim glob current source nextOneTwoFive rejects missing encoding'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteUtf16RtrimGlobCurrentSourceNextPlan::optionRowNamePlan([['option_id' => 1, 'option_name_bytes' => 'p']], $nextRows, 'p*'));
};

return $tests;
