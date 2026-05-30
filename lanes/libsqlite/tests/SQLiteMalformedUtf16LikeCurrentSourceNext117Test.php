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
    $row(1, 'plugin_alpha', 'UTF-16LE'),
    $bad(2, "p\x00l\x00u\x00g\x00i\x00n\x00_\x00\x00\xd8", 2),
    $bad(3, "\x00p\x00l\x00u\x00g\x00i\x00n\x00_\xd8\x3d", 3),
    $bad(4, "P\x00l\x00u\x00g\x00i\x00n\x00_\x00\x00\xd8", 2, 'no'),
    $bad(5, "t\x00h\x00e\x00m\x00e\x00_\x00\x00\xd8", 2),
    $bad(6, "p\x00l\x00u\x00g\x00", 2),
    $row(7, 'plugin_éclair', 'UTF-16BE'),
    $bad(8, "p\x00l\x00u\x00g\x00i\x00n\x00_\x00X", 2),
    $bad(9, "\x00p\x00l\x00u\x00g\x00i\x00n\x00_\xdc\x00", 3),
    $row(10, 'plugin_100%_enabled', 'UTF-8'),
    $bad(11, "p\x00l\x00u\x00g\x00i\x00n\x00_\x001\x000\x000\x00%\x00_\x00\x00\xd8", 2),
];

$nextRows = [
    $row(1, 'plugin_alpha', 'UTF-16BE'),
    $row(2, 'plugin_fixed', 'UTF-16LE'),
    $bad(3, "\x00p\x00l\x00u\x00g\x00i\x00n\x00_\xd8\x3d", 3),
    $bad(4, "P\x00l\x00u\x00g\x00i\x00n\x00_\x00\x00\xd8", 2, 'no'),
    $bad(5, "t\x00h\x00e\x00m\x00e\x00_\x00\x00\xd8", 2),
    $bad(6, "p\x00l\x00u\x00g\x00", 2),
    $row(7, 'plugin_éclair', 'UTF-16LE'),
    $row(8, 'plugin_tail_fixed', 'UTF-8'),
    $bad(9, "\x00p\x00l\x00u\x00g\x00i\x00n\x00_\xdc\x00", 3),
    $row(10, 'plugin_100%_enabled', 'UTF-8'),
    $bad(11, "p\x00l\x00u\x00g\x00i\x00n\x00_\x001\x000\x000\x00%\x00_\x00\x00\xd8", 2),
    $bad(12, "p\x00l\x00u\x00g\x00i\x00n\x00_\x00\x00\xd8", 2),
    $row(13, 'plugin_new', 'UTF-16BE'),
];

$plan = static fn (
    string $pattern = 'plugin%',
    string $operator = 'LIKE',
    string $collation = 'NOCASE',
    ?string $escape = null,
    bool $caseSensitiveLike = false,
    string $currentSource = 'main.app_settings@cookie116',
    string $nextSource = 'main.app_settings@cookie117',
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
    'records current source' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'currentSource', 'main.app_settings@cookie116'],
    'records next source' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'nextSource', 'main.app_settings@cookie117'],
    'records operator' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'operator', 'LIKE'],
    'records collation' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'collation', 'NOCASE'],
    'requires reprepare' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'reprepareRequired', true],
    'source-name reason is first' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'reprepareReasons.0', 'source-name'],
    'malformed reason is second' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'reprepareReasons.1', 'malformed-text'],
    'matched rowset reason is third' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'reprepareReasons.2', 'matched-rowset'],
    'current valid rowids exclude malformed prefix candidates' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'currentValidRowids', [1, 6, 7, 10]],
    'next valid rowids include repaired rows' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'nextValidRowids', [1, 2, 6, 7, 8, 10, 13]],
    'current rowids include only decoded matches' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'currentRowids', [10, 1, 7]],
    'next rowids include fixed and new decoded matches' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'nextRowids', [10, 1, 2, 13, 8, 7]],
    'retained decoded matches preserve current order' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'retainedRowids', [10, 1, 7]],
    'entered decoded matches are repaired and new rows' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'enteredRowids', [2, 13, 8]],
    'exited decoded matches empty' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'exitedRowids', []],
    'current malformed rowids include all malformed rows' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'currentMalformedRowids', [2, 3, 4, 5, 8, 9, 11]],
    'next malformed rowids include persistent and new malformed rows' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'nextMalformedRowids', [3, 4, 5, 9, 11, 12]],
    'current malformed candidate rowids are prefix scoped' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'currentMalformedCandidateRowids', [2, 3, 4, 8, 9, 11]],
    'next malformed candidate rowids are prefix scoped' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'nextMalformedCandidateRowids', [3, 4, 9, 11, 12]],
    'theme malformed row is not a plugin candidate' => ['theme%', 'LIKE', 'NOCASE', null, false, 'currentMalformedCandidateRowids', [5]],
    'short plug malformed row is outside plugin prefix' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'currentBytesHex.6', '70006c0075006700'],
    'current repaired rowids' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'repairedRowids', [2, 8]],
    'newly malformed rowids' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'newlyMalformedRowids', [12]],
    'current high surrogate little endian error' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'currentErrors.2', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'current high surrogate big endian error' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'currentErrors.3', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'current low surrogate big endian error' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'currentErrors.9', 'SQLite encoding source UTF-16 text payload has an unpaired low surrogate'],
    'current odd prefixed row error' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'currentErrors.8', 'SQLite encoding source UTF-16 text payload has an odd byte length'],
    'next new malformed row error' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'nextErrors.12', 'SQLite encoding source UTF-16 text payload ends with a high surrogate'],
    'current high surrogate bytes retained' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'currentBytesHex.2', '70006c007500670069006e005f0000d8'],
    'next high surrogate bytes retained' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'nextBytesHex.12', '70006c007500670069006e005f0000d8'],
    'like current range lower' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'currentRange.lowerInclusive', 'plugin'],
    'like current range upper' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'currentRange.upperBound', 'plugio'],
    'like next range lower' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'nextRange.lowerInclusive', 'plugin'],
    'case sensitive skips uppercase malformed candidate' => ['plugin%', 'LIKE', 'BINARY', null, true, 'currentMalformedCandidateRowids', [2, 3, 8, 9, 11]],
    'case sensitive still records uppercase malformed row globally' => ['plugin%', 'LIKE', 'BINARY', null, true, 'currentMalformedRowids', [2, 3, 4, 5, 8, 9, 11]],
    'case sensitive current rowids' => ['plugin%', 'LIKE', 'BINARY', null, true, 'currentRowids', [10, 1, 7]],
    'escaped percent candidate narrows to literal prefix' => ['plugin\_100\%%', 'LIKE', 'NOCASE', '\\', false, 'currentMalformedCandidateRowids', [11]],
    'escaped percent next candidate narrows to literal prefix' => ['plugin\_100\%%', 'LIKE', 'NOCASE', '\\', false, 'nextMalformedCandidateRowids', [11]],
    'escaped percent decoded current row' => ['plugin\_100\%%', 'LIKE', 'NOCASE', '\\', false, 'currentRowids', [10]],
    'escaped percent decoded next row' => ['plugin\_100\%%', 'LIKE', 'NOCASE', '\\', false, 'nextRowids', [10]],
    'glob records operator' => ['plugin_*', 'GLOB', 'BINARY', null, false, 'operator', 'GLOB'],
    'glob prefix candidates current' => ['plugin_*', 'GLOB', 'BINARY', null, false, 'currentMalformedCandidateRowids', [2, 3, 8, 9, 11]],
    'glob prefix candidates next' => ['plugin_*', 'GLOB', 'BINARY', null, false, 'nextMalformedCandidateRowids', [3, 9, 11, 12]],
    'glob decoded current rowids' => ['plugin_*', 'GLOB', 'BINARY', null, false, 'currentRowids', [10, 1, 7]],
    'glob decoded next rowids' => ['plugin_*', 'GLOB', 'BINARY', null, false, 'nextRowids', [10, 1, 2, 13, 8, 7]],
    'glob plugin one hundred prefix candidate' => ['plugin_100*', 'GLOB', 'BINARY', null, false, 'currentMalformedCandidateRowids', [11]],
    'glob plugin one hundred decoded row' => ['plugin_100*', 'GLOB', 'BINARY', null, false, 'currentRowids', [10]],
    'leading wildcard has no malformed candidates' => ['%plugin', 'LIKE', 'NOCASE', null, false, 'currentMalformedCandidateRowids', []],
    'leading wildcard still reports all malformed rows' => ['%plugin', 'LIKE', 'NOCASE', null, false, 'currentMalformedRowids', [2, 3, 4, 5, 8, 9, 11]],
    'leading glob class has no malformed candidates' => ['[Pp]lugin_*', 'GLOB', 'BINARY', null, false, 'nextMalformedCandidateRowids', []],
    'dependency records malformed current next' => ['plugin%', 'LIKE', 'NOCASE', null, false, 'dependencies.2', 'sqlite-malformed-current-source-next'],
];

foreach ($cases as $name => $case) {
    $tests['malformed utf16 like current source next117 ' . $name] = static function (TestRunner $t) use ($plan, $case): void {
        [$pattern, $operator, $collation, $escape, $caseSensitive, $path, $expected] = $case;
        $value = $plan($pattern, $operator, $collation, $escape, $caseSensitive);
        foreach (explode('.', $path) as $part) {
            $value = $value[$part];
        }
        $t->same($expected, $value);
    };
}

return $tests;
