<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteMalformedLikeGlobSourceNextPlan;

$tests = [];

$row = static function (int $id, string $name, string $encoding, string $autoload = 'yes'): array {
    return [
        'option_id' => $id,
        'option_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($name, $encoding),
        'text_encoding' => match ($encoding) {
            'UTF-8' => 1,
            'UTF-16LE' => 2,
            'UTF-16BE' => 3,
            default => throw new InvalidArgumentException('bad fixture encoding'),
        },
        'autoload' => $autoload,
    ];
};

$bad = static function (int $id, string $bytes, int $encoding, string $autoload = 'yes'): array {
    return [
        'option_id' => $id,
        'option_name_bytes' => $bytes,
        'text_encoding' => $encoding,
        'autoload' => $autoload,
    ];
};

$currentRows = [
    $row(1, 'Plugin_Alpha', 'UTF-8', 'no'),
    $row(2, 'plugin_alpha', 'UTF-16LE'),
    $row(3, 'plugin_beta', 'UTF-16BE'),
    $bad(4, "p\x00l\x00u\x00g\x00i\x00n\x00_", 2),
    $bad(5, "\x00p\x00l\x00u\x00g\x00i\x00n\x00_\xd8\x3d", 3),
    $bad(6, "\x00p\x00l\x00u\x00g\x00i\x00n\x00_\xdc\x00", 3, 'no'),
    $row(7, 'theme_alpha', 'UTF-16LE'),
    $row(8, 'plugin_éclair', 'UTF-16LE'),
    $row(9, 'plugin_😀_cache', 'UTF-16BE'),
    $row(10, 'plugin_100%_enabled', 'UTF-16LE'),
    $bad(13, "\x00p\x00l\x00u\x00g\x00i\x00n\x00_\xd8\x3d\x00A", 3),
];

$nextRows = [
    $row(1, 'Plugin_Alpha', 'UTF-16LE', 'no'),
    $row(2, 'plugin_alpha', 'UTF-16LE'),
    $row(3, 'plugin_beta_v2', 'UTF-16BE'),
    $row(4, 'plugin_delta', 'UTF-16LE'),
    $bad(5, "\x00p\x00l\x00u\x00g\x00i\x00n\x00_\xd8\x3d", 3),
    $row(6, 'plugin_low_repaired', 'UTF-8', 'no'),
    $bad(7, "p\x00l\x00u\x00g\x00i\x00n\x00_\x00X", 2),
    $row(8, 'plugin_éclair', 'UTF-16BE'),
    $row(9, 'plugin_😀_cache_v2', 'UTF-16LE'),
    $row(11, 'plugin_new', 'UTF-16BE'),
    $bad(12, "p\x00l\x00u\x00g\x00i\x00n\x00_\x00\x3d\xd8", 2),
    $bad(13, "\x00p\x00l\x00u\x00g\x00i\x00n\x00_\xd8\x3d\x00A", 3),
];

$plan = static fn (
    string $pattern = 'plugin%',
    string $operator = 'LIKE',
    string $collation = 'NOCASE',
    ?string $escape = null,
    bool $caseSensitiveLike = false,
    string $currentSource = 'main.app_settings@cookie96',
    string $nextSource = 'main.app_settings@cookie97',
): array => SQLiteMalformedLikeGlobSourceNextPlan::keyValueRowKeyCurrentNext(
    $currentRows,
    $nextRows,
    $pattern,
    $operator,
    $collation,
    $escape,
    $caseSensitiveLike,
    $currentSource,
    $nextSource,
);

$cases = [
    'like records current source' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'currentSource', 'main.app_settings@cookie96'],
    'like records next source' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'nextSource', 'main.app_settings@cookie97'],
    'like records pattern' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'pattern', 'plugin%'],
    'like records operator' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'operator', 'LIKE'],
    'like records collation' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'collation', 'NOCASE'],
    'like requires reprepare' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'reprepareRequired', true],
    'like invalidates by source first' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'reprepareReasons.0', 'source-name'],
    'like invalidates by malformed text second' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'reprepareReasons.1', 'malformed-text'],
    'like invalidates by rowset third' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'reprepareReasons.2', 'matched-rowset'],
    'like current range lower' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'currentRange.lowerInclusive', 'plugin'],
    'like current range upper' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'currentRange.upperBound', 'plugio'],
    'like next range lower' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'nextRange.lowerInclusive', 'plugin'],
    'like current valid rows exclude malformed utf16' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'currentValidRowids', [1, 2, 3, 7, 8, 9, 10]],
    'like next valid rows exclude malformed utf16' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'nextValidRowids', [1, 2, 3, 4, 6, 8, 9, 11]],
    'like current rowids include only valid matched rows' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'currentRowids', [10, 1, 2, 3, 8, 9]],
    'like next rowids include repaired rows' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'nextRowids', [1, 2, 3, 4, 6, 11, 8, 9]],
    'like retained rowids' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'retainedRowids', [1, 2, 3, 8, 9]],
    'like entered rowids include repaired and new rows' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'enteredRowids', [4, 6, 11]],
    'like exited rowids include old literal percent row' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'exitedRowids', [10]],
    'like current malformed rowids' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'currentMalformedRowids', [4, 5, 6, 13]],
    'like next malformed rowids' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'nextMalformedRowids', [5, 7, 12, 13]],
    'like repaired rowids' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'repairedRowids', [4, 6]],
    'like newly malformed rowids' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'newlyMalformedRowids', [7, 12]],
    'like odd utf16le current error' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'currentErrors.4', 'SQLite encoding source UTF-16 text payload has an odd byte length'],
    'like high surrogate current error' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'currentErrors.5', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'like low surrogate current error' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'currentErrors.6', 'SQLite encoding source UTF-16 text payload has an unpaired low surrogate'],
    'like high surrogate mismatch current error' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'currentErrors.13', 'SQLite encoding source UTF-16 text payload has an unpaired high surrogate'],
    'like newly odd next error' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'nextErrors.7', 'SQLite encoding source UTF-16 text payload has an odd byte length'],
    'like newly high next error' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'nextErrors.12', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'like current bytes preserve high surrogate fixture' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'currentBytesHex.5', '0070006c007500670069006e005fd83d'],
    'like next bytes preserve odd fixture' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'nextBytesHex.7', '70006c007500670069006e005f0058'],
    'like dependency includes malformed source next' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'dependencies.2', 'sqlite-malformed-current-source-next'],
    'case sensitive like skips uppercase current row' => ['plugin%', 'LIKE', 'BINARY', null, true, 'currentRowids', [10, 2, 3, 8, 9]],
    'case sensitive like skips uppercase next row' => ['plugin%', 'LIKE', 'BINARY', null, true, 'nextRowids', [2, 3, 4, 6, 11, 8, 9]],
    'case sensitive like entered rows' => ['plugin%', 'LIKE', 'BINARY', null, true, 'enteredRowids', [4, 6, 11]],
    'case sensitive like exited rows' => ['plugin%', 'LIKE', 'BINARY', null, true, 'exitedRowids', [10]],
    'escaped literal percent current row' => ['plugin\_100\%%', 'LIKE', 'NOCASE', '\\', false, 'currentRowids', [10]],
    'escaped literal percent next rowset empty' => ['plugin\_100\%%', 'LIKE', 'NOCASE', '\\', false, 'nextRowids', []],
    'escaped literal percent exits current row' => ['plugin\_100\%%', 'LIKE', 'NOCASE', '\\', false, 'exitedRowids', [10]],
    'escaped literal percent still reports malformed rows' => ['plugin\_100\%%', 'LIKE', 'NOCASE', '\\', false, 'nextMalformedRowids', [5, 7, 12, 13]],
    'glob current rowids' => ['plugin_*', 'GLOB', 'BINARY', null, false, 'currentRowids', [10, 2, 3, 8, 9]],
    'glob next rowids' => ['plugin_*', 'GLOB', 'BINARY', null, false, 'nextRowids', [2, 3, 4, 6, 11, 8, 9]],
    'glob entered rows' => ['plugin_*', 'GLOB', 'BINARY', null, false, 'enteredRowids', [4, 6, 11]],
    'glob retained rows' => ['plugin_*', 'GLOB', 'BINARY', null, false, 'retainedRowids', [2, 3, 8, 9]],
    'glob latin range current row' => ['plugin_[À-ÿ]*', 'GLOB', 'BINARY', null, false, 'currentRowids', [8]],
    'glob latin range next row' => ['plugin_[À-ÿ]*', 'GLOB', 'BINARY', null, false, 'nextRowids', [8]],
    'glob emoji current row' => ['plugin_😀*', 'GLOB', 'BINARY', null, false, 'currentRowids', [9]],
    'glob emoji next row' => ['plugin_😀*', 'GLOB', 'BINARY', null, false, 'nextRowids', [9]],
    'same source still invalidates on malformed repair' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'reprepareReasons', ['malformed-text', 'matched-rowset'], 'stable', 'stable'],
    'same source theme still invalidates when next source loses valid row' => ['theme%', 'LIKE', 'NOCASE', null, false, 'reprepareReasons', ['malformed-text', 'matched-rowset'], 'stable', 'stable'],
    'same source theme exits current row' => ['theme%', 'LIKE', 'NOCASE', null, false, 'exitedRowids', [7], 'stable', 'stable'],
    'leading wildcard has no range current' => ['%plugin', 'LIKE', 'NOCASE', null, false, 'currentRange', null],
    'leading wildcard has no matched rows' => ['%plugin', 'LIKE', 'NOCASE', null, false, 'currentRowids', []],
    'leading wildcard still reports malformed current rows' => ['%plugin', 'LIKE', 'NOCASE', null, false, 'currentMalformedRowids', [4, 5, 6, 13]],
];

foreach ($cases as $name => $case) {
    $tests['malformed utf16 like range current source next97 ' . $name] = static function (TestRunner $t) use ($plan, $case): void {
        [$pattern, $operator, $collation, $escape, $caseSensitive, $path, $expected] = $case;
        $currentSource = $case[7] ?? 'main.app_settings@cookie96';
        $nextSource = $case[8] ?? 'main.app_settings@cookie97';
        $value = $plan($pattern, $operator, $collation, $escape, $caseSensitive, $currentSource, $nextSource);
        foreach (explode('.', $path) as $part) {
            $value = $value[$part];
        }
        $t->same($expected, $value);
    };
}

$tests['malformed utf16 like range current source next97 rejects unsupported operator'] = static function (TestRunner $t) use ($currentRows, $nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteMalformedLikeGlobSourceNextPlan::keyValueRowKeyCurrentNext($currentRows, $nextRows, 'plugin%', 'REGEXP'));
};

$tests['malformed utf16 like range current source next97 rejects missing bytes'] = static function (TestRunner $t) use ($nextRows): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteMalformedLikeGlobSourceNextPlan::keyValueRowKeyCurrentNext([['option_id' => 1, 'text_encoding' => 2]], $nextRows, 'plugin%'));
};

return $tests;
